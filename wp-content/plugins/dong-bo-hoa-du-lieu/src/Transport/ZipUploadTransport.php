<?php
declare(strict_types=1);

namespace DBHDL\Sync\Transport;

use DBHDL\Sync\Diff\DiffEngine;
use DBHDL\Sync\Jobs\JobStateStore;
use DBHDL\Sync\Security\CryptoVault;
use DBHDL\Sync\Security\PathGuard;
use ZipArchive;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles manual `.zip` package uploads from a developer's Local Project directly into Staging/Web
 * with smart root prefix detection, pre-apply Diff preview, and automatic pre-apply Snapshot.
 */
final class ZipUploadTransport {

    /**
     * Save uploaded ZIP into protected temporary storage and return a dry-run diff preview.
     *
     * @return array<string, mixed>
     */
    public static function inspectUpload( string $tmpFilePath, string $originalName, string $targetScopePrefix = '' ): array {
        if ( ! is_file( $tmpFilePath ) || ! class_exists( 'ZipArchive' ) ) {
            return [ 'ok' => false, 'error' => 'Tệp upload không hợp lệ hoặc thiếu ZipArchive.' ];
        }

        $zip = new ZipArchive();
        if ( true !== $zip->open( $tmpFilePath ) ) {
            return [ 'ok' => false, 'error' => 'Không mở được tệp ZIP tải lên (file hỏng hoặc sai định dạng).' ];
        }

        // Detect top-level folder structure inside the ZIP so developers can upload either:
        // 1) `wp-content/themes/my-theme/...`
        // 2) `my-theme/...` (mapped to chosen `$targetScopePrefix` or auto-matched)
        $hasWpContentRoot = false;
        $firstTopDirs     = [];
        for ( $i = 0; $i < $zip->numFiles; $i++ ) {
            $name = ltrim( PathGuard::normalize( (string) $zip->getNameIndex( $i ) ), '/' );
            if ( '' === $name || str_ends_with( $name, '/' ) || ! PathGuard::isSafeRelativePath( $name ) ) {
                continue;
            }
            if ( str_starts_with( strtolower( $name ), '__macosx/' ) ) {
                continue;
            }
            if ( str_starts_with( $name, 'wp-content/' ) ) {
                $hasWpContentRoot = true;
                break;
            }
            $seg = explode( '/', $name )[0] ?? '';
            if ( '' !== $seg ) {
                $firstTopDirs[ $seg ] = true;
            }
        }
        $zip->close();

        $subpathMap = [];
        $prefixes   = [];
        $cleanTarget = trim( PathGuard::normalize( $targetScopePrefix ), '/' );

        if ( ! $hasWpContentRoot && '' !== $cleanTarget && PathGuard::isSafeRelativePath( $cleanTarget ) ) {
            if ( 1 === count( $firstTopDirs ) ) {
                $singleRoot = (string) array_key_first( $firstTopDirs );
                if ( $singleRoot === basename( $cleanTarget ) ) {
                    $subpathMap[ $singleRoot ] = $cleanTarget;
                } else {
                    $subpathMap['.'] = $cleanTarget;
                }
            } else {
                $subpathMap['.'] = $cleanTarget;
            }
            $prefixes = [ $cleanTarget ];
        }

        $uploadId  = CryptoVault::randomHex( 10 );
        $dirInfo   = PathGuard::resolveSnapshotBaseDir();
        $stagedZip = $dirInfo['dir'] . 'tmp-upload-' . $uploadId . '.zip';
        if ( ! @copy( $tmpFilePath, $stagedZip ) ) {
            return [ 'ok' => false, 'error' => 'Không lưu được tệp tạm để xử lý.' ];
        }

        $diff = DiffEngine::inspectZipAgainstDirectory( $stagedZip, ABSPATH, $prefixes, $subpathMap, false );
        if ( empty( $diff['ok'] ) ) {
            @unlink( $stagedZip );
            return $diff;
        }

        set_transient(
            'dbhdl_zip_up_' . $uploadId,
            [
                'path'          => $stagedZip,
                'original_name' => sanitize_file_name( $originalName ),
                'prefixes'      => $prefixes,
                'subpath_map'   => $subpathMap,
            ],
            1800
        );

        return [
            'ok'            => true,
            'upload_id'     => $uploadId,
            'original_name' => sanitize_file_name( $originalName ),
            'mapped_target' => $cleanTarget ?: 'wp-content/ (auto)',
            'summary'       => $diff['summary'],
            'items'         => $diff['items'],
        ];
    }

    /**
     * Apply a previously inspected ZIP upload with Snapshot protection.
     *
     * @param array<int, string> $excludedFiles
     * @return array<string, mixed>
     */
    public static function applyUpload( string $uploadId, array $excludedFiles = [], bool $mirrorDelete = false ): array {
        $meta = get_transient( 'dbhdl_zip_up_' . sanitize_key( $uploadId ) );
        if ( ! is_array( $meta ) || empty( $meta['path'] ) || ! is_file( (string) $meta['path'] ) ) {
            return [ 'ok' => false, 'error' => 'Phiên upload ZIP đã hết hạn. Vui lòng tải lại file ZIP.' ];
        }

        $stagedZip = (string) $meta['path'];
        $res       = ZipApplyHelper::applyZip(
            $stagedZip,
            ABSPATH,
            (array) ( $meta['prefixes'] ?? [] ),
            (array) ( $meta['subpath_map'] ?? [] ),
            [
                'excluded_files' => $excludedFiles,
                'mirror_delete'  => $mirrorDelete,
                'trigger'        => 'local_zip_upload',
                'direction'      => 'Local ZIP (' . (string) ( $meta['original_name'] ?? 'package.zip' ) . ') → Web',
            ]
        );

        @unlink( $stagedZip );
        delete_transient( 'dbhdl_zip_up_' . sanitize_key( $uploadId ) );

        if ( ! empty( $res['ok'] ) ) {
            JobStateStore::appendLog(
                [
                    'action'    => 'zip_upload',
                    'direction' => 'Local ZIP → ' . home_url(),
                    'transport' => 'zip_upload',
                    'status'    => 'success',
                    'summary'   => sprintf(
                        'Giải nén %s (%d tệp cập nhật, %d xóa). Snapshot: %s',
                        (string) ( $meta['original_name'] ?? 'archive.zip' ),
                        (int) $res['updated_count'],
                        (int) $res['deleted_count'],
                        (string) $res['snapshot']
                    ),
                    'snapshot'  => (string) $res['snapshot'],
                    'files_cnt' => (int) $res['updated_count'] + (int) $res['deleted_count'],
                ]
            );
        }

        return $res;
    }
}
