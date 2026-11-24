<?php
declare(strict_types=1);

namespace DBHDL\Sync\Snapshot;

use DBHDL\Sync\Plugin;
use DBHDL\Sync\Security\PathGuard;
use ZipArchive;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Creates, lists, verifies, and rotates pre-sync Snapshots containing BOTH overwritten/added
 * file states and pre-change Database backups for complete 1-click rollback.
 */
final class SnapshotManager {

    /**
     * Create a protective snapshot before applying file and/or database changes on `$targetBaseDir`.
     *
     * @param array<int, string> $affectedRelPaths Existing target relative paths about to be modified/deleted.
     * @param array<int, string> $addedRelPaths Paths that do not yet exist on target (recorded so rollback can remove them).
     * @param array<string, mixed>|null $dbBackup Pre-change DB state from `SelectiveDbSync::capturePreChangeState()`.
     * @param array<string, mixed> $meta
     * @return array{ok: bool, filename?: string, size_bytes?: int, error?: string}
     */
    public static function createSnapshot(
        string $targetBaseDir,
        array $affectedRelPaths,
        array $addedRelPaths = [],
        ?array $dbBackup = null,
        array $meta = []
    ): array {
        if ( ! class_exists( 'ZipArchive' ) ) {
            return [ 'ok' => false, 'error' => 'Máy chủ PHP chưa bật extension ZipArchive.' ];
        }

        $dirInfo     = PathGuard::resolveSnapshotBaseDir();
        $snapshotDir = $dirInfo['dir'];
        if ( ! is_dir( $snapshotDir ) || ! is_writable( $snapshotDir ) ) {
            return [ 'ok' => false, 'error' => 'Thư mục lưu Snapshot không có quyền ghi: ' . $snapshotDir ];
        }

        $shortId  = substr( bin2hex( random_bytes( 4 ) ), 0, 6 );
        $filename = 'dbhdl-snap-' . gmdate( 'Ymd-His' ) . '-' . $shortId . '.zip';
        $zipPath  = $snapshotDir . $filename;

        $zip = new ZipArchive();
        if ( true !== $zip->open( $zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
            return [ 'ok' => false, 'error' => 'Không thể khởi tạo file nén Snapshot.' ];
        }

        $targetBaseDir = rtrim( PathGuard::normalize( $targetBaseDir ), '/' );
        $backedUpFiles = [];

        foreach ( array_unique( $affectedRelPaths ) as $rel ) {
            $cleanRel = ltrim( PathGuard::normalize( (string) $rel ), '/' );
            if ( '' === $cleanRel || ! PathGuard::isSafeRelativePath( $cleanRel ) ) {
                continue;
            }
            $abs = PathGuard::resolveUnderBase( $targetBaseDir, $cleanRel );
            if ( null !== $abs && is_file( $abs ) ) {
                $zip->addFile( $abs, 'files/' . $cleanRel );
                $backedUpFiles[] = $cleanRel;
            }
        }

        $hasDb = is_array( $dbBackup ) && ( ! empty( $dbBackup['existing_posts'] ) || ! empty( $dbBackup['new_post_uids'] ) || ! empty( $dbBackup['options'] ) );
        if ( $hasDb ) {
            $zip->addFromString( 'db-backup.json', (string) wp_json_encode( $dbBackup, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
        }

        $manifest = [
            'version'         => 1,
            'created_at'      => gmdate( 'c' ),
            'target_base_dir' => $targetBaseDir,
            'trigger'         => (string) ( $meta['trigger'] ?? 'sync' ),
            'direction'       => (string) ( $meta['direction'] ?? '' ),
            'actor'           => (string) ( $meta['actor'] ?? 'system' ),
            'files_count'     => count( $backedUpFiles ),
            'backed_up_files' => $backedUpFiles,
            'added_files'     => array_values( array_unique( $addedRelPaths ) ),
            'has_db'          => $hasDb,
        ];
        $zip->addFromString( 'manifest.json', (string) wp_json_encode( $manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) );
        $zip->close();

        if ( ! is_file( $zipPath ) ) {
            return [ 'ok' => false, 'error' => 'Tạo Snapshot thất bại.' ];
        }

        self::enforceRetention();

        return [
            'ok'         => true,
            'filename'   => $filename,
            'path'       => $zipPath,
            'size_bytes' => (int) filesize( $zipPath ),
        ];
    }

    /**
     * List available snapshots newest first with manifest summary.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function listSnapshots( int $limit = 20 ): array {
        $dirInfo     = PathGuard::resolveSnapshotBaseDir();
        $snapshotDir = $dirInfo['dir'];
        if ( ! is_dir( $snapshotDir ) ) {
            return [];
        }

        $files = (array) @glob( $snapshotDir . 'dbhdl-snap-*.zip' );
        rsort( $files );

        $items = [];
        foreach ( array_slice( $files, 0, max( 1, $limit ) ) as $file ) {
            $manifest = self::readManifest( $file );
            $items[]  = [
                'filename'    => basename( $file ),
                'size_bytes'  => (int) @filesize( $file ),
                'size_mb'     => round( ( (int) @filesize( $file ) ) / ( 1024 * 1024 ), 2 ),
                'time'        => gmdate( 'd/m/Y H:i:s', (int) @filemtime( $file ) ),
                'trigger'     => (string) ( $manifest['trigger'] ?? 'sync' ),
                'direction'   => (string) ( $manifest['direction'] ?? '' ),
                'files_count' => (int) ( $manifest['files_count'] ?? 0 ),
                'added_count' => count( (array) ( $manifest['added_files'] ?? [] ) ),
                'has_db'      => ! empty( $manifest['has_db'] ),
            ];
        }

        return $items;
    }

    public static function getSnapshotPath( string $filename ): ?string {
        $clean = basename( $filename );
        if ( ! preg_match( '/^dbhdl-snap-[0-9]{8}-[0-9]{6}-[a-f0-9]+\.zip$/i', $clean ) ) {
            return null;
        }
        $dirInfo = PathGuard::resolveSnapshotBaseDir();
        $full    = $dirInfo['dir'] . $clean;
        return is_file( $full ) ? $full : null;
    }

    public static function deleteSnapshot( string $filename ): bool {
        $full = self::getSnapshotPath( $filename );
        if ( null === $full ) {
            return false;
        }
        return @unlink( $full );
    }

    /**
     * @return array<string, mixed>
     */
    public static function readManifest( string $zipPath ): array {
        if ( ! class_exists( 'ZipArchive' ) || ! is_file( $zipPath ) ) {
            return [];
        }
        $zip = new ZipArchive();
        if ( true !== $zip->open( $zipPath ) ) {
            return [];
        }
        $raw = $zip->getFromName( 'manifest.json' );
        $zip->close();
        if ( false === $raw ) {
            return [];
        }
        $decoded = json_decode( $raw, true );
        return is_array( $decoded ) ? $decoded : [];
    }

    private static function enforceRetention(): void {
        $settings  = Plugin::getSettings();
        $retention = max( 3, min( 50, (int) ( $settings['snapshot_retention'] ?? 10 ) ) );
        $dirInfo   = PathGuard::resolveSnapshotBaseDir();
        $files     = (array) @glob( $dirInfo['dir'] . 'dbhdl-snap-*.zip' );
        rsort( $files );

        if ( count( $files ) > $retention ) {
            foreach ( array_slice( $files, $retention ) as $old ) {
                @unlink( $old );
            }
        }
    }
}
