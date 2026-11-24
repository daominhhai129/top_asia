<?php
declare(strict_types=1);

namespace DBHDL\Sync\Transport;

use DBHDL\Sync\Diff\DiffEngine;
use DBHDL\Sync\Jobs\CachePurger;
use DBHDL\Sync\Plugin;
use DBHDL\Sync\Security\PathGuard;
use DBHDL\Sync\Snapshot\SnapshotManager;
use ZipArchive;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Shared safe ZIP extraction engine used by GitHub Pull, Local ZIP Upload, and Remote Pairing Commit.
 * Enforces PathGuard, user exclusions, pre-change Snapshot creation, and post-apply cache purge.
 */
final class ZipApplyHelper {

    /**
     * Inspect and extract selected entries from `$zipPath` into `$targetBaseDir` with automatic Snapshot.
     *
     * @param array<int, string> $prefixes
     * @param array<string, string> $subpathMap
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function applyZip(
        string $zipPath,
        string $targetBaseDir,
        array $prefixes = [],
        array $subpathMap = [],
        array $options = []
    ): array {
        $stripFirst    = ! empty( $options['strip_first_segment'] );
        $includeMedia  = ! empty( $options['include_media'] );
        $mirrorDelete  = ! empty( $options['mirror_delete'] );
        $excludedFiles = array_flip( array_map( 'strval', (array) ( $options['excluded_files'] ?? [] ) ) );
        $explicitDeletes = array_map( 'strval', (array) ( $options['deleted_files'] ?? [] ) );

        $inspect = DiffEngine::inspectZipAgainstDirectory(
            $zipPath,
            $targetBaseDir,
            $prefixes,
            $subpathMap,
            $stripFirst,
            [ 'include_media' => $includeMedia ]
        );

        if ( empty( $inspect['ok'] ) ) {
            return $inspect;
        }

        $entriesToApply = [];
        $affectedPaths  = [];
        $addedPaths     = [];
        $sourceAllPaths = [];

        foreach ( (array) $inspect['entries'] as $entry ) {
            $rel = (string) $entry['target_rel'];
            $sourceAllPaths[ $rel ] = true;
            if ( isset( $excludedFiles[ $rel ] ) || 'unchanged' === $entry['status'] ) {
                continue;
            }
            $entriesToApply[] = $entry;
            if ( 'modified' === $entry['status'] ) {
                $affectedPaths[] = $rel;
            } elseif ( 'added' === $entry['status'] ) {
                $addedPaths[] = $rel;
            }
        }

        $deleteRelPaths = [];
        if ( $mirrorDelete ) {
            if ( ! empty( $explicitDeletes ) ) {
                foreach ( $explicitDeletes as $delRel ) {
                    if ( ! isset( $excludedFiles[ $delRel ] ) && ! PathGuard::isExcluded( $delRel, [ 'include_media' => $includeMedia ] ) ) {
                        $deleteRelPaths[] = $delRel;
                        $affectedPaths[]  = $delRel;
                    }
                }
            } elseif ( ! empty( $prefixes ) ) {
                $curTargetTree = DiffEngine::scanDirectoryTree( $targetBaseDir, $prefixes, [ 'include_media' => $includeMedia ] );
                foreach ( array_keys( $curTargetTree ) as $curRel ) {
                    if ( ! isset( $sourceAllPaths[ $curRel ] ) && ! isset( $excludedFiles[ $curRel ] ) ) {
                        $deleteRelPaths[] = $curRel;
                        $affectedPaths[]  = $curRel;
                    }
                }
            }
        }

        $dbBackup   = isset( $options['db_backup'] ) && is_array( $options['db_backup'] ) ? $options['db_backup'] : null;
        $snapResult = SnapshotManager::createSnapshot(
            $targetBaseDir,
            $affectedPaths,
            $addedPaths,
            $dbBackup,
            [
                'trigger'   => (string) ( $options['trigger'] ?? 'zip_apply' ),
                'direction' => (string) ( $options['direction'] ?? '' ),
            ]
        );

        if ( empty( $snapResult['ok'] ) ) {
            return [
                'ok'    => false,
                'error' => 'Không thể tạo bản sao lưu trước khi đồng bộ: ' . ( $snapResult['error'] ?? 'Unknown error' ),
            ];
        }

        $useMaint = ! empty( $options['maintenance_mode'] );
        $applied  = Plugin::withMaintenanceMode(
            $useMaint,
            static function () use ( $zipPath, $targetBaseDir, $entriesToApply, $deleteRelPaths ): array {
                $zip = new ZipArchive();
                if ( true !== $zip->open( $zipPath ) ) {
                    return [ 'ok' => false, 'error' => 'Không thể mở lại file ZIP khi ghi dữ liệu.' ];
                }

                $updatedCount = 0;
                $deletedCount = 0;

                foreach ( $entriesToApply as $entry ) {
                    $dest = PathGuard::resolveUnderBase( $targetBaseDir, (string) $entry['target_rel'] );
                    if ( null === $dest ) {
                        continue;
                    }
                    $parent = dirname( $dest );
                    if ( ! is_dir( $parent ) ) {
                        if ( function_exists( 'wp_mkdir_p' ) ) {
                            wp_mkdir_p( $parent );
                        } else {
                            @mkdir( $parent, 0755, true );
                        }
                    }
                    $content = $zip->getFromIndex( (int) $entry['zip_index'] );
                    if ( false !== $content && false !== @file_put_contents( $dest, $content ) ) {
                        $updatedCount++;
                    }
                }

                $zip->close();

                foreach ( $deleteRelPaths as $delRel ) {
                    $dest = PathGuard::resolveUnderBase( $targetBaseDir, $delRel );
                    if ( null !== $dest && is_file( $dest ) && @unlink( $dest ) ) {
                        $deletedCount++;
                    }
                }

                return [
                    'ok'            => true,
                    'updated_count' => $updatedCount,
                    'deleted_count' => $deletedCount,
                ];
            }
        );

        if ( empty( $applied['ok'] ) ) {
            return $applied;
        }

        $flushed = empty( $options['skip_cache_purge'] ) ? CachePurger::purgeLocal() : [];

        return [
            'ok'            => true,
            'snapshot'      => (string) ( $snapResult['filename'] ?? '' ),
            'updated_count' => (int) $applied['updated_count'],
            'deleted_count' => (int) $applied['deleted_count'],
            'unchanged'     => (int) ( $inspect['summary']['unchanged'] ?? 0 ),
            'flushed'       => $flushed,
        ];
    }
}
