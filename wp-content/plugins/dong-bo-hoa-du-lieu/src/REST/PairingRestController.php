<?php
declare(strict_types=1);

namespace DBHDL\Sync\REST;

use DBHDL\Sync\Database\SecretsVaultManager;
use DBHDL\Sync\Database\SelectiveDbSync;
use DBHDL\Sync\Diff\DiffEngine;
use DBHDL\Sync\Discovery\EnvironmentDetector;
use DBHDL\Sync\Jobs\CachePurger;
use DBHDL\Sync\Jobs\JobStateStore;
use DBHDL\Sync\Plugin;
use DBHDL\Sync\Security\CryptoVault;
use DBHDL\Sync\Security\PathGuard;
use DBHDL\Sync\Security\RequestSigner;
use DBHDL\Sync\Snapshot\SnapshotManager;
use DBHDL\Sync\Transport\GitHubTransport;
use DBHDL\Sync\Transport\ZipApplyHelper;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Signed site-to-site receiver endpoints allowing a paired Source WP site (Local or Staging)
 * to query file manifests, stream delta ZIP chunks, and atomically commit deploys.
 */
final class PairingRestController {

    public function registerRoutes(): void {
        $routes = [
            '/pairing/ping'         => 'handlePing',
            '/pairing/manifest'     => 'handleManifest',
            '/pairing/upload-chunk' => 'handleUploadChunk',
            '/pairing/commit'       => 'handleCommit',
            '/pairing/git-diff'     => 'handleGitDiff',
            '/pairing/git-pull'     => 'handleGitPull',
        ];

        foreach ( $routes as $route => $callback ) {
            register_rest_route(
                DBHDL_REST_NS,
                $route,
                [
                    'methods'             => 'POST',
                    'callback'            => [ $this, $callback ],
                    'permission_callback' => function ( WP_REST_Request $req ) use ( $route ) {
                        return $this->authorizeSignedRequest( $req, $route );
                    },
                ]
            );
        }
    }

    /**
     * Generate or rotate this site's Pairing Server credentials & return a copyable Pairing Token.
     *
     * @return array<string, mixed>
     */
    public static function generatePairingServerCredentials(): array {
        $pairId = 'pair_' . CryptoVault::randomHex( 6 );
        $secret = CryptoVault::randomHex( 32 );
        $env    = EnvironmentDetector::detectRole();

        update_option(
            Plugin::OPTION_PAIRING,
            [
                'enabled'    => true,
                'pair_id'    => $pairId,
                'secret_enc' => CryptoVault::encrypt( $secret, 'dbhdl_pairing' ),
                'created_at' => gmdate( 'c' ),
            ],
            false
        );

        $envelope = [
            'v'         => 1,
            'site_url'  => rtrim( home_url(), '/' ),
            'rest_base' => rtrim( rest_url( DBHDL_REST_NS ), '/' ),
            'pair_id'   => $pairId,
            'secret'    => $secret,
            'env_role'  => $env['role'],
        ];

        $b64 = rtrim( strtr( base64_encode( (string) wp_json_encode( $envelope, JSON_UNESCAPED_SLASHES ) ), '+/', '-_' ), '=' );

        return [
            'pair_id'    => $pairId,
            'token'      => $b64,
            'site_url'   => $envelope['site_url'],
            'env_role'   => $env['role'],
            'created_at' => gmdate( 'c' ),
        ];
    }

    public static function revokePairing(): void {
        delete_option( Plugin::OPTION_PAIRING );
    }

    /**
     * @return array{enabled: bool, pair_id: string, created_at: string}
     */
    public static function getPairingServerStatus(): array {
        $raw = get_option( Plugin::OPTION_PAIRING, [] );
        if ( ! is_array( $raw ) || empty( $raw['enabled'] ) || empty( $raw['pair_id'] ) ) {
            return [ 'enabled' => false, 'pair_id' => '', 'created_at' => '' ];
        }
        return [
            'enabled'    => true,
            'pair_id'    => (string) $raw['pair_id'],
            'created_at' => (string) ( $raw['created_at'] ?? '' ),
        ];
    }

    /**
     * @return true|WP_Error
     */
    public function authorizeSignedRequest( WP_REST_Request $req, string $route ) {
        $raw = get_option( Plugin::OPTION_PAIRING, [] );
        if ( ! is_array( $raw ) || empty( $raw['enabled'] ) || empty( $raw['pair_id'] ) || empty( $raw['secret_enc'] ) ) {
            return new WP_Error( 'dbhdl_pairing_disabled', 'Remote Pairing chưa được bật trên site này.', [ 'status' => 403 ] );
        }

        $secret = CryptoVault::decryptSafe( (string) $raw['secret_enc'], 'dbhdl_pairing' );
        $headers = [];
        foreach ( $req->get_headers() as $k => $vals ) {
            $headers[ strtolower( str_replace( '_', '-', (string) $k ) ) ] = is_array( $vals ) ? (string) ( $vals[0] ?? '' ) : (string) $vals;
        }

        $check = RequestSigner::verifySignedHeaders(
            (string) $raw['pair_id'],
            $secret,
            $headers,
            $req->get_method(),
            $route,
            (string) $req->get_body()
        );

        if ( empty( $check['ok'] ) ) {
            return new WP_Error( 'dbhdl_pairing_auth_failed', (string) ( $check['error'] ?? 'Unauthorized' ), [ 'status' => 403 ] );
        }

        return true;
    }

    public function handlePing(): WP_REST_Response {
        $env = EnvironmentDetector::detectRole();
        return new WP_REST_Response(
            [
                'ok'        => true,
                'site_url'  => rtrim( home_url(), '/' ),
                'site_name' => (string) get_bloginfo( 'name' ),
                'env_role'  => $env['role'],
                'writable'  => is_writable( WP_CONTENT_DIR ),
                'version'   => DBHDL_VERSION,
            ],
            200
        );
    }

    public function handleManifest( WP_REST_Request $req ): WP_REST_Response {
        $params       = (array) $req->get_json_params();
        $prefixes     = array_values( array_filter( array_map( 'strval', (array) ( $params['prefixes'] ?? [] ) ) ) );
        $includeMedia = ! empty( $params['include_media'] );
        $env          = EnvironmentDetector::detectRole();

        $files = ! empty( $prefixes )
            ? DiffEngine::scanDirectoryTree( ABSPATH, $prefixes, [ 'include_media' => $includeMedia ] )
            : [];

        return new WP_REST_Response(
            [
                'ok'       => true,
                'site_url' => rtrim( home_url(), '/' ),
                'env_role' => $env['role'],
                'files'    => $files,
            ],
            200
        );
    }

    public function handleUploadChunk( WP_REST_Request $req ): WP_REST_Response {
        $params     = (array) $req->get_json_params();
        $sessionId  = sanitize_key( (string) ( $params['session_id'] ?? '' ) );
        $chunkIndex = (int) ( $params['chunk_index'] ?? -1 );
        $dataB64    = (string) ( $params['data_b64'] ?? '' );

        if ( ! preg_match( '/^[a-f0-9]{12,32}$/', $sessionId ) || $chunkIndex < 0 || '' === $dataB64 ) {
            return new WP_REST_Response( [ 'ok' => false, 'error' => 'Tham số chunk không hợp lệ.' ], 400 );
        }

        $bin = base64_decode( $dataB64, true );
        if ( false === $bin ) {
            return new WP_REST_Response( [ 'ok' => false, 'error' => 'Dữ liệu Base64 không hợp lệ.' ], 400 );
        }

        $dirInfo = PathGuard::resolveSnapshotBaseDir();
        $tmpPath = $dirInfo['dir'] . 'tmp-pair-' . $sessionId . '.zip';
        $mode    = ( 0 === $chunkIndex ) ? 'wb' : 'ab';
        $fh      = @fopen( $tmpPath, $mode );
        if ( ! $fh ) {
            return new WP_REST_Response( [ 'ok' => false, 'error' => 'Không ghi được tệp chunk tạm trên máy chủ đích.' ], 500 );
        }
        fwrite( $fh, $bin );
        fclose( $fh );

        return new WP_REST_Response( [ 'ok' => true, 'chunk_index' => $chunkIndex, 'written' => strlen( $bin ) ], 200 );
    }

    public function handleCommit( WP_REST_Request $req ): WP_REST_Response {
        global $wpdb;
        $params    = (array) $req->get_json_params();
        $sessionId = sanitize_key( (string) ( $params['session_id'] ?? '' ) );
        $hasZip    = ! empty( $params['has_zip'] );
        $expectedSha = strtolower( trim( (string) ( $params['zip_sha256'] ?? '' ) ) );

        $dirInfo = PathGuard::resolveSnapshotBaseDir();
        $tmpZip  = $dirInfo['dir'] . 'tmp-pair-' . $sessionId . '.zip';

        if ( $hasZip ) {
            if ( ! is_file( $tmpZip ) ) {
                return new WP_REST_Response( [ 'ok' => false, 'error' => 'Không tìm thấy gói ZIP truyền tải trên đích.' ], 400 );
            }
            if ( '' !== $expectedSha && strtolower( (string) hash_file( 'sha256', $tmpZip ) ) !== $expectedSha ) {
                @unlink( $tmpZip );
                return new WP_REST_Response( [ 'ok' => false, 'error' => 'Checksum SHA-256 gói ZIP không khớp (dữ liệu truyền bị lỗi).' ], 400 );
            }
        }

        $dbPackage  = isset( $params['db_package'] ) && is_array( $params['db_package'] ) ? $params['db_package'] : null;
        $vaultItems = isset( $params['vault_items'] ) && is_array( $params['vault_items'] ) ? $params['vault_items'] : [];
        $dbBackup   = ( null !== $dbPackage && isset( $wpdb ) ) ? SelectiveDbSync::capturePreChangeState( $wpdb, $dbPackage ) : null;
        $sourceUrl  = esc_url_raw( (string) ( $params['source_url'] ?? 'remote-source' ) );

        $snapshotName = '';
        $updatedCount = 0;
        $deletedCount = 0;

        if ( $hasZip ) {
            $zipRes = ZipApplyHelper::applyZip(
                $tmpZip,
                ABSPATH,
                array_map( 'strval', (array) ( $params['prefixes'] ?? [] ) ),
                [],
                [
                    'include_media'    => ! empty( $params['include_media'] ),
                    'mirror_delete'    => ! empty( $params['mirror_delete'] ),
                    'deleted_files'    => (array) ( $params['deleted_files'] ?? [] ),
                    'maintenance_mode' => ! empty( $params['maintenance_mode'] ),
                    'db_backup'        => $dbBackup,
                    'skip_cache_purge' => true,
                    'trigger'          => 'remote_pairing_receive',
                    'direction'        => $sourceUrl . ' → ' . home_url(),
                ]
            );
            @unlink( $tmpZip );

            if ( empty( $zipRes['ok'] ) ) {
                return new WP_REST_Response( $zipRes, 500 );
            }
            $snapshotName = (string) ( $zipRes['snapshot'] ?? '' );
            $updatedCount = (int) ( $zipRes['updated_count'] ?? 0 );
            $deletedCount = (int) ( $zipRes['deleted_count'] ?? 0 );
        } else {
            $snap = SnapshotManager::createSnapshot(
                ABSPATH,
                [],
                [],
                $dbBackup,
                [
                    'trigger'   => 'remote_pairing_db',
                    'direction' => $sourceUrl . ' → ' . home_url(),
                ]
            );
            if ( empty( $snap['ok'] ) ) {
                return new WP_REST_Response( [ 'ok' => false, 'error' => (string) ( $snap['error'] ?? 'Lỗi tạo Snapshot.' ) ], 500 );
            }
            $snapshotName = (string) $snap['filename'];
        }

        $dbStats = [ 'posts_updated' => 0, 'posts_created' => 0, 'menus_synced' => 0, 'options_synced' => 0, 'errors' => [] ];
        if ( null !== $dbPackage && isset( $wpdb ) ) {
            $doReplace = ! isset( $params['search_replace'] ) || ! empty( $params['search_replace'] );
            $dbStats   = SelectiveDbSync::importPackage(
                $wpdb,
                $dbPackage,
                rtrim( home_url(), '/' ),
                rtrim( PathGuard::normalize( ABSPATH ), '/' ),
                $doReplace
            );
        }

        $vaultStats = [ 'synced' => [], 'skipped' => [] ];
        if ( ! empty( $vaultItems ) && isset( $wpdb ) ) {
            $vMode      = ( 'overwrite' === ( $params['vault_mode'] ?? '' ) ) ? 'overwrite' : 'fill_missing';
            $vaultStats = SecretsVaultManager::importTransferItems( $wpdb, $vaultItems, $vMode, true );
        }

        $flushed = CachePurger::purgeLocal();
        $dbTotal = (int) $dbStats['posts_updated']
            + (int) $dbStats['posts_created']
            + (int) $dbStats['menus_synced']
            + (int) $dbStats['options_synced'];

        JobStateStore::appendLog(
            [
                'action'    => 'pairing_receive',
                'direction' => $sourceUrl . ' → ' . home_url(),
                'transport' => 'remote_pairing',
                'status'    => empty( $dbStats['errors'] ) ? 'success' : 'warning',
                'summary'   => sprintf(
                    'Nhận đồng bộ từ %s (%d tệp, %d bản ghi DB). Snapshot: %s',
                    $sourceUrl,
                    $updatedCount + $deletedCount,
                    $dbTotal,
                    $snapshotName
                ),
                'snapshot'  => $snapshotName,
                'files_cnt' => $updatedCount + $deletedCount,
                'db_cnt'    => $dbTotal,
            ]
        );

        return new WP_REST_Response(
            [
                'ok'            => true,
                'snapshot'      => $snapshotName,
                'updated_count' => $updatedCount,
                'deleted_count' => $deletedCount,
                'db_stats'      => $dbStats,
                'vault_stats'   => $vaultStats,
                'flushed'       => $flushed,
            ],
            200
        );
    }

    public function handleGitDiff( WP_REST_Request $req ): WP_REST_Response {
        $params = (array) $req->get_json_params();
        $res    = ( new GitHubTransport() )->previewDiff(
            [
                'direction' => 'pull',
                'scope'     => (array) ( $params['scope'] ?? [] ),
                'prefixes'  => (array) ( $params['prefixes'] ?? [] ),
                'repo'      => (string) ( $params['repo'] ?? '' ),
                'branch'    => (string) ( $params['branch'] ?? '' ),
                'api_base'  => (string) ( $params['api_base'] ?? '' ),
                'token'     => (string) ( $params['token'] ?? '' ),
            ]
        );
        return new WP_REST_Response( $res, ! empty( $res['ok'] ) ? 200 : 400 );
    }

    public function handleGitPull( WP_REST_Request $req ): WP_REST_Response {
        $params = (array) $req->get_json_params();
        $res    = ( new GitHubTransport() )->executePull(
            [
                'scope'           => (array) ( $params['scope'] ?? [] ),
                'prefixes'        => (array) ( $params['prefixes'] ?? [] ),
                'mirror_delete'   => ! empty( $params['mirror_delete'] ),
                'excluded_files'  => (array) ( $params['excluded_files'] ?? [] ),
                'maintenance_mode'=> ! empty( $params['maintenance_mode'] ),
                'repo'            => (string) ( $params['repo'] ?? '' ),
                'branch'          => (string) ( $params['branch'] ?? '' ),
                'api_base'        => (string) ( $params['api_base'] ?? '' ),
                'token'           => (string) ( $params['token'] ?? '' ),
                'trigger'         => 'remote_github_pull',
                'direction_label' => 'GitHub → ' . rtrim( home_url(), '/' ),
            ]
        );
        return new WP_REST_Response( $res, ! empty( $res['ok'] ) ? 200 : 500 );
    }
}
