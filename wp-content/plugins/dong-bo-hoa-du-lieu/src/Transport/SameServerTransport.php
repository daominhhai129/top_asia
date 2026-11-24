<?php
declare(strict_types=1);

namespace DBHDL\Sync\Transport;

use DBHDL\Sync\Contracts\TransportInterface;
use DBHDL\Sync\Database\SecretsVaultManager;
use DBHDL\Sync\Database\SelectiveDbSync;
use DBHDL\Sync\Diff\DiffEngine;
use DBHDL\Sync\Discovery\EnvironmentDetector;
use DBHDL\Sync\Discovery\ScopeScanner;
use DBHDL\Sync\Jobs\CachePurger;
use DBHDL\Sync\Jobs\JobStateStore;
use DBHDL\Sync\Plugin;
use DBHDL\Sync\Security\PathGuard;
use DBHDL\Sync\Snapshot\SnapshotManager;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * High-speed Same-Server Filesystem + Cross-DB Transport (e.g., `/staging` -> root or sibling directory).
 */
final class SameServerTransport implements TransportInterface {

    public function getId(): string {
        return 'same_server';
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function testConnection( array $config = [] ): array {
        $targetPath = trim( (string) ( $config['target_path'] ?? Plugin::getSettings()['target_path'] ?? '' ) );
        return EnvironmentDetector::testSameServerTarget( $targetPath );
    }

    /**
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    public function previewDiff( array $request ): array {
        $targetPath = trim( (string) ( $request['target_path'] ?? Plugin::getSettings()['target_path'] ?? '' ) );
        $check      = EnvironmentDetector::testSameServerTarget( $targetPath );
        if ( empty( $check['ok'] ) ) {
            return $check;
        }

        $targetDir    = (string) $check['path'];
        $includeMedia = ! empty( $request['scope']['include_media'] );
        $prefixes     = ScopeScanner::resolveScopePrefixes( (array) ( $request['scope'] ?? [] ) );

        $fileDiff = [
            'summary' => [ 'added' => 0, 'modified' => 0, 'deleted' => 0, 'unchanged' => 0, 'transfer_bytes' => 0 ],
            'items'   => [],
        ];
        if ( ! empty( $prefixes ) ) {
            $srcTree  = DiffEngine::scanDirectoryTree( ABSPATH, $prefixes, [ 'include_media' => $includeMedia ] );
            $dstTree  = DiffEngine::scanDirectoryTree( $targetDir, $prefixes, [ 'include_media' => $includeMedia ] );
            $fileDiff = DiffEngine::compareTrees( $srcTree, $dstTree, $prefixes, true );
        }

        $dbSelection = (array) ( $request['db_scope'] ?? [] );
        $dbSummary   = SelectiveDbSync::previewSelection( $dbSelection );

        $vaultItems = [];
        if ( ! empty( $request['sync_vault'] ) ) {
            $mode       = (string) ( $request['vault_mode'] ?? 'fill_missing' );
            $vaultItems = array_map(
                static fn( array $it ): string => $it['label'],
                SecretsVaultManager::exportTransferItems( $mode )
            );
        }

        return [
            'ok'          => true,
            'target_info' => $check,
            'summary'     => $fileDiff['summary'],
            'items'       => $fileDiff['items'],
            'db_summary'  => $dbSummary,
            'vault_items' => $vaultItems,
        ];
    }

    /**
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    public function execute( array $request ): array {
        if ( function_exists( 'set_time_limit' ) ) {
            @set_time_limit( 300 );
        }

        $targetPath = trim( (string) ( $request['target_path'] ?? Plugin::getSettings()['target_path'] ?? '' ) );
        $check      = EnvironmentDetector::testSameServerTarget( $targetPath );
        if ( empty( $check['ok'] ) ) {
            return $check;
        }

        $targetDir  = (string) $check['path'];
        $targetUrl  = (string) ( $check['site_url'] ?? '' );
        $parsedCfg  = EnvironmentDetector::parseWpConfig( $targetDir . '/wp-config.php' );
        $targetDb   = ! empty( $parsedCfg['ok'] ) ? EnvironmentDetector::openTargetDb( $parsedCfg['db'] ) : null;
        if ( ! $targetDb ) {
            return [ 'ok' => false, 'error' => 'Không thể mở kết nối MySQL tới trang đích.' ];
        }

        $includeMedia  = ! empty( $request['scope']['include_media'] );
        $mirrorDelete  = ! empty( $request['mirror_delete'] );
        $excludedFiles = array_flip( array_map( 'strval', (array) ( $request['excluded_files'] ?? [] ) ) );
        $prefixes      = ScopeScanner::resolveScopePrefixes( (array) ( $request['scope'] ?? [] ) );

        $toCopy        = [];
        $toDelete      = [];
        $affectedPaths = [];
        $addedPaths    = [];

        if ( ! empty( $prefixes ) ) {
            $srcTree  = DiffEngine::scanDirectoryTree( ABSPATH, $prefixes, [ 'include_media' => $includeMedia ] );
            $dstTree  = DiffEngine::scanDirectoryTree( $targetDir, $prefixes, [ 'include_media' => $includeMedia ] );
            $diff     = DiffEngine::compareTrees( $srcTree, $dstTree, $prefixes, $mirrorDelete );

            foreach ( $diff['items'] as $item ) {
                $rel = (string) $item['path'];
                if ( isset( $excludedFiles[ $rel ] ) ) {
                    continue;
                }
                if ( 'added' === $item['status'] ) {
                    $toCopy[]     = $rel;
                    $addedPaths[] = $rel;
                } elseif ( 'modified' === $item['status'] ) {
                    $toCopy[]        = $rel;
                    $affectedPaths[] = $rel;
                } elseif ( 'deleted' === $item['status'] && $mirrorDelete ) {
                    $toDelete[]      = $rel;
                    $affectedPaths[] = $rel;
                }
            }
        }

        $dbSelection = (array) ( $request['db_scope'] ?? [] );
        $hasDbSync   = ! empty( $dbSelection['post_types'] ) || ! empty( $dbSelection['include_menus'] ) || ! empty( $dbSelection['include_theme_mods'] ) || ! empty( $dbSelection['options'] );
        $dbPackage   = $hasDbSync ? SelectiveDbSync::exportPackage( $dbSelection ) : null;
        $dbBackup    = null !== $dbPackage ? SelectiveDbSync::capturePreChangeState( $targetDb, $dbPackage ) : null;

        // Pre-Sync Snapshot (Files + DB).
        $snapRes = SnapshotManager::createSnapshot(
            $targetDir,
            $affectedPaths,
            $addedPaths,
            $dbBackup,
            [
                'trigger'   => 'same_server_deploy',
                'direction' => home_url() . ' → ' . $targetUrl,
            ]
        );
        if ( empty( $snapRes['ok'] ) ) {
            return [ 'ok' => false, 'error' => 'Không tạo được Snapshot an toàn: ' . ( $snapRes['error'] ?? '' ) ];
        }

        $settings = Plugin::getSettings();
        $useMaint = ! empty( $request['maintenance_mode'] ) || ! empty( $settings['enable_maintenance_mode'] );

        $result = Plugin::withMaintenanceMode(
            $useMaint,
            static function () use ( $targetDir, $targetDb, $targetUrl, $toCopy, $toDelete, $dbPackage, $request ): array {
                $copied  = 0;
                $deleted = 0;

                foreach ( $toCopy as $rel ) {
                    $src = PathGuard::resolveUnderBase( ABSPATH, $rel );
                    $dst = PathGuard::resolveUnderBase( $targetDir, $rel );
                    if ( null === $src || null === $dst || ! is_file( $src ) ) {
                        continue;
                    }
                    $parent = dirname( $dst );
                    if ( ! is_dir( $parent ) ) {
                        wp_mkdir_p( $parent );
                    }
                    if ( @copy( $src, $dst ) ) {
                        $copied++;
                    }
                }

                foreach ( $toDelete as $rel ) {
                    $dst = PathGuard::resolveUnderBase( $targetDir, $rel );
                    if ( null !== $dst && is_file( $dst ) && @unlink( $dst ) ) {
                        $deleted++;
                    }
                }

                $dbStats = [ 'posts_updated' => 0, 'posts_created' => 0, 'menus_synced' => 0, 'options_synced' => 0, 'errors' => [] ];
                if ( null !== $dbPackage ) {
                    $doReplace = ! isset( $request['search_replace'] ) || ! empty( $request['search_replace'] );
                    $dbStats   = SelectiveDbSync::importPackage( $targetDb, $dbPackage, $targetUrl, $targetDir, $doReplace );
                }

                $vaultStats = [ 'synced' => [], 'skipped' => [] ];
                if ( ! empty( $request['sync_vault'] ) ) {
                    $vMode      = ( 'overwrite' === ( $request['vault_mode'] ?? '' ) ) ? 'overwrite' : 'fill_missing';
                    $vItems     = SecretsVaultManager::exportTransferItems( $vMode );
                    $vaultStats = SecretsVaultManager::importTransferItems( $targetDb, $vItems, $vMode, false );
                }

                return [
                    'copied'      => $copied,
                    'deleted'     => $deleted,
                    'db_stats'    => $dbStats,
                    'vault_stats' => $vaultStats,
                ];
            }
        );

        $flushed = CachePurger::purgeTarget( $targetDb, $targetDir, $targetUrl );
        $dbTotal = (int) $result['db_stats']['posts_updated']
            + (int) $result['db_stats']['posts_created']
            + (int) $result['db_stats']['menus_synced']
            + (int) $result['db_stats']['options_synced'];

        JobStateStore::appendLog(
            [
                'action'    => 'deploy_same_server',
                'direction' => home_url() . ' → ' . ( $targetUrl ?: $targetDir ),
                'transport' => 'same_server',
                'status'    => empty( $result['db_stats']['errors'] ) ? 'success' : 'warning',
                'summary'   => sprintf(
                    'Đồng bộ %d tệp (%d xóa), %d bản ghi DB. Snapshot: %s',
                    $result['copied'],
                    $result['deleted'],
                    $dbTotal,
                    (string) $snapRes['filename']
                ),
                'snapshot'  => (string) $snapRes['filename'],
                'files_cnt' => $result['copied'] + $result['deleted'],
                'db_cnt'    => $dbTotal,
                'details'   => (array) $result['db_stats']['errors'],
            ]
        );

        return [
            'ok'          => true,
            'snapshot'    => (string) $snapRes['filename'],
            'copied'      => $result['copied'],
            'deleted'     => $result['deleted'],
            'db_stats'    => $result['db_stats'],
            'vault_stats' => $result['vault_stats'],
            'flushed'     => $flushed,
        ];
    }
}
