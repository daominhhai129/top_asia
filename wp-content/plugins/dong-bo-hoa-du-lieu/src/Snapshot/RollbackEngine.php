<?php
declare(strict_types=1);

namespace DBHDL\Sync\Snapshot;

use DBHDL\Sync\Database\SelectiveDbSync;
use DBHDL\Sync\Discovery\EnvironmentDetector;
use DBHDL\Sync\Jobs\CachePurger;
use DBHDL\Sync\Jobs\JobStateStore;
use DBHDL\Sync\Security\PathGuard;
use wpdb;
use ZipArchive;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Restores both overwritten files (plus cleans newly added files) and pre-change DB records
 * from a verified Snapshot archive.
 */
final class RollbackEngine {

    /**
     * Restore a snapshot archive safely.
     *
     * @return array<string, mixed>
     */
    public static function restore( string $filename, ?string $overrideTargetDir = null, ?wpdb $overrideTargetDb = null ): array {
        $zipPath = SnapshotManager::getSnapshotPath( $filename );
        if ( null === $zipPath ) {
            return [ 'ok' => false, 'error' => 'Không tìm thấy file Snapshot hợp lệ.' ];
        }
        if ( ! class_exists( 'ZipArchive' ) ) {
            return [ 'ok' => false, 'error' => 'Máy chủ PHP chưa bật extension ZipArchive.' ];
        }

        $zip = new ZipArchive();
        if ( true !== $zip->open( $zipPath ) ) {
            return [ 'ok' => false, 'error' => 'Không thể mở tệp Snapshot.' ];
        }

        $manifestRaw = $zip->getFromName( 'manifest.json' );
        $manifest    = is_string( $manifestRaw ) ? json_decode( $manifestRaw, true ) : [];
        if ( ! is_array( $manifest ) ) {
            $zip->close();
            return [ 'ok' => false, 'error' => 'Tệp Snapshot thiếu manifest.json hợp lệ.' ];
        }

        $targetBaseDir = $overrideTargetDir ?? (string) ( $manifest['target_base_dir'] ?? '' );
        if ( '' === $targetBaseDir && defined( 'ABSPATH' ) ) {
            $targetBaseDir = ABSPATH;
        }
        $targetBaseDir = rtrim( PathGuard::normalize( $targetBaseDir ), '/' );

        if ( '' === $targetBaseDir || ! is_dir( $targetBaseDir ) ) {
            $zip->close();
            return [ 'ok' => false, 'error' => 'Thư mục đích để khôi phục không tồn tại: ' . $targetBaseDir ];
        }

        $restoredFiles = 0;
        for ( $i = 0; $i < $zip->numFiles; $i++ ) {
            $name = PathGuard::normalize( (string) $zip->getNameIndex( $i ) );
            if ( ! str_starts_with( $name, 'files/' ) || str_ends_with( $name, '/' ) ) {
                continue;
            }
            $rel = substr( $name, strlen( 'files/' ) );
            if ( '' === $rel || ! PathGuard::isSafeRelativePath( $rel ) ) {
                continue;
            }
            $dest = PathGuard::resolveUnderBase( $targetBaseDir, $rel );
            if ( null === $dest ) {
                continue;
            }
            $content = $zip->getFromIndex( $i );
            if ( false === $content ) {
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
            if ( false !== @file_put_contents( $dest, $content ) ) {
                $restoredFiles++;
            }
        }

        // Remove newly created files added during the rolled-back sync.
        $removedAdded = 0;
        foreach ( (array) ( $manifest['added_files'] ?? [] ) as $addedRel ) {
            $dest = PathGuard::resolveUnderBase( $targetBaseDir, (string) $addedRel );
            if ( null !== $dest && is_file( $dest ) && @unlink( $dest ) ) {
                $removedAdded++;
            }
        }

        // Restore Database state if `db-backup.json` is present.
        $dbStats  = [ 'posts_restored' => 0, 'posts_deleted' => 0, 'options_restored' => 0 ];
        $dbRaw    = $zip->getFromName( 'db-backup.json' );
        $zip->close();

        if ( is_string( $dbRaw ) && '' !== $dbRaw ) {
            $dbBackup = json_decode( $dbRaw, true );
            if ( is_array( $dbBackup ) ) {
                $targetDb = $overrideTargetDb ?? self::resolveTargetDb( $targetBaseDir );
                if ( $targetDb instanceof wpdb ) {
                    $dbStats = SelectiveDbSync::restorePreChangeState( $targetDb, $dbBackup );
                }
            }
        }

        CachePurger::purgeTarget( $overrideTargetDb ?? self::resolveTargetDb( $targetBaseDir ), $targetBaseDir );

        JobStateStore::appendLog(
            [
                'action'    => 'rollback',
                'direction' => (string) ( $manifest['direction'] ?? 'Snapshot Restore' ),
                'transport' => 'snapshot',
                'status'    => 'success',
                'summary'   => sprintf(
                    'Khôi phục %s (%d tệp khôi phục, %d tệp mới đã gỡ, %d bản ghi DB).',
                    basename( $zipPath ),
                    $restoredFiles,
                    $removedAdded,
                    $dbStats['posts_restored'] + $dbStats['posts_deleted'] + $dbStats['options_restored']
                ),
                'snapshot'  => basename( $zipPath ),
                'files_cnt' => $restoredFiles,
                'db_cnt'    => $dbStats['posts_restored'] + $dbStats['posts_deleted'] + $dbStats['options_restored'],
            ]
        );

        return [
            'ok'             => true,
            'filename'       => basename( $zipPath ),
            'restored_files' => $restoredFiles,
            'removed_added'  => $removedAdded,
            'db_stats'       => $dbStats,
        ];
    }

    private static function resolveTargetDb( string $targetBaseDir ): ?wpdb {
        $selfNorm = defined( 'ABSPATH' ) ? rtrim( PathGuard::normalize( (string) realpath( ABSPATH ) ), '/' ) : '';
        $dstNorm  = rtrim( PathGuard::normalize( (string) realpath( $targetBaseDir ) ), '/' );

        if ( '' !== $selfNorm && $dstNorm === $selfNorm ) {
            global $wpdb;
            return $wpdb instanceof wpdb ? $wpdb : null;
        }

        $parsed = EnvironmentDetector::parseWpConfig( $targetBaseDir . '/wp-config.php' );
        if ( ! empty( $parsed['ok'] ) && ! empty( $parsed['db'] ) ) {
            return EnvironmentDetector::openTargetDb( $parsed['db'] );
        }
        return null;
    }
}
