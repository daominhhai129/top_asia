<?php
declare(strict_types=1);

namespace DBHDL\Sync\REST;

use DBHDL\Sync\Admin\MigrationImporter;
use DBHDL\Sync\Database\SecretsVaultManager;
use DBHDL\Sync\Discovery\EnvironmentDetector;
use DBHDL\Sync\Discovery\ScopeScanner;
use DBHDL\Sync\Jobs\JobStateStore;
use DBHDL\Sync\Plugin;
use DBHDL\Sync\Security\CryptoVault;
use DBHDL\Sync\Security\PathGuard;
use DBHDL\Sync\Snapshot\RollbackEngine;
use DBHDL\Sync\Snapshot\SnapshotManager;
use DBHDL\Sync\Transport\GitHubTransport;
use DBHDL\Sync\Transport\RemoteRestTransport;
use DBHDL\Sync\Transport\SameServerTransport;
use DBHDL\Sync\Transport\ZipUploadTransport;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Primary Admin REST API (`/wp-json/dbhdl/v1/...`) powering the 5-tab Dashboard & Admin Bar actions.
 */
final class SyncRestController {

    public function registerRoutes(): void {
        $adminPerm = [ $this, 'checkAdminPermission' ];

        register_rest_route( DBHDL_REST_NS, '/status', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'getStatus' ],
            'permission_callback' => $adminPerm,
        ] );

        register_rest_route( DBHDL_REST_NS, '/settings', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'saveSettings' ],
            'permission_callback' => $adminPerm,
        ] );

        register_rest_route( DBHDL_REST_NS, '/settings/import-legacy', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'importLegacy' ],
            'permission_callback' => $adminPerm,
        ] );

        // GitHub 2-Way Endpoints
        register_rest_route( DBHDL_REST_NS, '/git/test', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'gitTest' ],
            'permission_callback' => $adminPerm,
        ] );
        register_rest_route( DBHDL_REST_NS, '/git/branches', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'gitBranches' ],
            'permission_callback' => $adminPerm,
        ] );
        register_rest_route( DBHDL_REST_NS, '/git/latest', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'gitLatest' ],
            'permission_callback' => $adminPerm,
        ] );
        register_rest_route( DBHDL_REST_NS, '/git/diff', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'gitDiff' ],
            'permission_callback' => $adminPerm,
        ] );
        register_rest_route( DBHDL_REST_NS, '/git/pull', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'gitPull' ],
            'permission_callback' => $adminPerm,
        ] );
        register_rest_route( DBHDL_REST_NS, '/git/push', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'gitPush' ],
            'permission_callback' => $adminPerm,
        ] );
        register_rest_route( DBHDL_REST_NS, '/git/regen-webhook', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'regenWebhookSecret' ],
            'permission_callback' => $adminPerm,
        ] );

        // Target & Pairing Endpoints
        register_rest_route( DBHDL_REST_NS, '/target/detect', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'targetDetect' ],
            'permission_callback' => $adminPerm,
        ] );
        register_rest_route( DBHDL_REST_NS, '/target/test', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'targetTest' ],
            'permission_callback' => $adminPerm,
        ] );
        register_rest_route( DBHDL_REST_NS, '/pairing/generate', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'pairingGenerate' ],
            'permission_callback' => $adminPerm,
        ] );
        register_rest_route( DBHDL_REST_NS, '/pairing/revoke', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'pairingRevoke' ],
            'permission_callback' => $adminPerm,
        ] );

        // Deploy Endpoints (Same-Server OR Remote Pairing)
        register_rest_route( DBHDL_REST_NS, '/deploy/diff', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'deployDiff' ],
            'permission_callback' => $adminPerm,
        ] );
        register_rest_route( DBHDL_REST_NS, '/deploy/run', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'deployRun' ],
            'permission_callback' => $adminPerm,
        ] );

        // Local ZIP Upload Endpoints
        register_rest_route( DBHDL_REST_NS, '/zip/inspect', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'zipInspect' ],
            'permission_callback' => $adminPerm,
        ] );
        register_rest_route( DBHDL_REST_NS, '/zip/apply', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'zipApply' ],
            'permission_callback' => $adminPerm,
        ] );

        // Snapshots & Rollback Endpoints
        register_rest_route( DBHDL_REST_NS, '/snapshots', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'snapshotsList' ],
            'permission_callback' => $adminPerm,
        ] );
        register_rest_route( DBHDL_REST_NS, '/snapshots/rollback', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'snapshotsRollback' ],
            'permission_callback' => $adminPerm,
        ] );
        register_rest_route( DBHDL_REST_NS, '/snapshots/delete', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'snapshotsDelete' ],
            'permission_callback' => $adminPerm,
        ] );
        register_rest_route( DBHDL_REST_NS, '/snapshots/ticket', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'snapshotsDownloadTicket' ],
            'permission_callback' => $adminPerm,
        ] );
        register_rest_route( DBHDL_REST_NS, '/snapshots/download', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'snapshotsDownloadFile' ],
            'permission_callback' => '__return_true', // Authorized strictly via one-time HMAC ticket transient
        ] );

        // Vault Endpoints
        register_rest_route( DBHDL_REST_NS, '/vault/save-batch', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'vaultSaveBatch' ],
            'permission_callback' => $adminPerm,
        ] );
        register_rest_route( DBHDL_REST_NS, '/vault/service', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'vaultSaveService' ],
            'permission_callback' => $adminPerm,
        ] );
        register_rest_route( DBHDL_REST_NS, '/vault/service/delete', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'vaultDeleteService' ],
            'permission_callback' => $adminPerm,
        ] );

        // Logs Endpoints
        register_rest_route( DBHDL_REST_NS, '/logs', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'logsList' ],
            'permission_callback' => $adminPerm,
        ] );
        register_rest_route( DBHDL_REST_NS, '/logs/clear', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'logsClear' ],
            'permission_callback' => $adminPerm,
        ] );
    }

    public function checkAdminPermission(): bool {
        return is_user_logged_in() && current_user_can( Plugin::requiredCapability() );
    }

    public function getStatus(): WP_REST_Response {
        $settings   = Plugin::getSettings();
        $env        = EnvironmentDetector::detectRole();
        $snapDir    = PathGuard::resolveSnapshotBaseDir();
        $gitToken   = Plugin::getSecretSetting( 'git_token_enc' );
        $whSecret   = Plugin::getSecretSetting( 'webhook_secret_enc' );
        if ( '' === $whSecret ) {
            $whSecret = CryptoVault::randomHex( 24 );
            Plugin::updateSettings( [ 'webhook_secret_enc' => CryptoVault::encrypt( $whSecret ) ] );
        }
        $pairToken = Plugin::getSecretSetting( 'target_pairing_token_enc' );
        $pairMeta  = '' !== $pairToken ? RemoteRestTransport::decodePairingToken( $pairToken ) : null;
        $prodToken = Plugin::getSecretSetting( 'prod_pairing_token_enc' );
        $prodMeta  = '' !== $prodToken ? RemoteRestTransport::decodePairingToken( $prodToken ) : null;
        $stgDetect = EnvironmentDetector::detectStagingActivation( $settings, $pairMeta );

        return new WP_REST_Response(
            [
                'ok'                => true,
                'version'           => DBHDL_VERSION,
                'environment'       => $env,
                'staging_detection' => $stgDetect,
                'inventory'         => ScopeScanner::getInventory(),
                'snapshots'      => SnapshotManager::listSnapshots( 20 ),
                'logs'           => JobStateStore::getLogs( 50 ),
                'vault_services' => SecretsVaultManager::getMaskedServices(),
                'vault_presets'  => array_values( SecretsVaultManager::getPresetTemplates() ),
                'pairing_server' => PairingRestController::getPairingServerStatus(),
                'has_legacy_cfg' => MigrationImporter::hasLegacySettings(),
                'diagnostics'    => [
                    'php_version'        => PHP_VERSION,
                    'zip_archive'        => class_exists( 'ZipArchive' ),
                    'libsodium'          => function_exists( 'sodium_crypto_aead_xchacha20poly1305_ietf_encrypt' ),
                    'openssl'            => function_exists( 'openssl_encrypt' ),
                    'max_execution_time' => (int) ini_get( 'max_execution_time' ),
                    'memory_limit'       => (string) ini_get( 'memory_limit' ),
                    'upload_max_size'    => (string) ini_get( 'upload_max_filesize' ),
                    'snapshot_dir'       => $snapDir['dir'],
                    'outside_webroot'    => $snapDir['outside_webroot'],
                    'snapshot_writable'  => is_writable( $snapDir['dir'] ),
                    'wp_content_writable'=> is_writable( WP_CONTENT_DIR ),
                ],
                'settings'       => [
                    'env_role'                  => (string) $settings['env_role'],
                    'git_provider'              => (string) $settings['git_provider'],
                    'git_api_base'              => (string) $settings['git_api_base'],
                    'git_repo'                  => (string) $settings['git_repo'],
                    'git_branch'                => (string) $settings['git_branch'],
                    'git_token_configured'      => '' !== $gitToken,
                    'git_token_hint'            => CryptoVault::mask( $gitToken ),
                    'git_author_name'           => (string) $settings['git_author_name'],
                    'git_author_email'          => (string) $settings['git_author_email'],
                    'git_subpath_map'           => (array) $settings['git_subpath_map'],
                    'webhook_url'               => rest_url( DBHDL_REST_NS . '/webhook/git-pull' ),
                    'webhook_secret_hint'       => CryptoVault::mask( $whSecret ),
                    'webhook_secret_raw'        => $whSecret, // Admin only: needed to copy into GitHub/local script
                    'webhook_allow_query_param' => ! empty( $settings['webhook_allow_query_param'] ),
                    'webhook_auto_pull_enabled' => ! empty( $settings['webhook_auto_pull_enabled'] ),
                    'target_mode'               => (string) $settings['target_mode'],
                    'target_path'               => (string) $settings['target_path'],
                    'target_pairing_configured' => null !== $pairMeta,
                    'target_pairing_site'       => $pairMeta['site_url'] ?? '',
                    'target_pairing_role'       => $pairMeta['env_role'] ?? '',
                    'allow_local_to_prod'       => ! empty( $settings['allow_local_to_prod'] ),
                    'prod_pairing_configured'   => null !== $prodMeta,
                    'prod_pairing_site'         => $prodMeta['site_url'] ?? '',
                    'prod_pairing_role'         => $prodMeta['env_role'] ?? '',
                    'snapshot_retention'        => (int) $settings['snapshot_retention'],
                    'enable_maintenance_mode'   => ! empty( $settings['enable_maintenance_mode'] ),
                    'chunk_size_kb'             => (int) $settings['chunk_size_kb'],
                    'delete_data_on_uninstall'  => ! empty( $settings['delete_data_on_uninstall'] ),
                ],
            ],
            200
        );
    }

    public function saveSettings( WP_REST_Request $req ): WP_REST_Response {
        $p     = (array) $req->get_json_params();
        $patch = [];

        if ( isset( $p['env_role'] ) && in_array( (string) $p['env_role'], array_merge( [ 'auto' ], EnvironmentDetector::VALID_ROLES ), true ) ) {
            $patch['env_role'] = (string) $p['env_role'];
        }
        if ( isset( $p['git_api_base'] ) ) {
            $patch['git_api_base'] = rtrim( esc_url_raw( (string) $p['git_api_base'] ), '/' ) ?: 'https://api.github.com';
        }
        if ( isset( $p['git_repo'] ) ) {
            $patch['git_repo'] = trim( sanitize_text_field( (string) $p['git_repo'] ), "/ \t\n\r" );
        }
        if ( isset( $p['git_branch'] ) ) {
            $patch['git_branch'] = sanitize_text_field( (string) $p['git_branch'] ) ?: 'main';
        }
        if ( ! empty( $p['git_token'] ) ) {
            $patch['git_token_enc'] = CryptoVault::encrypt( trim( (string) $p['git_token'] ) );
        } elseif ( ! empty( $p['clear_git_token'] ) ) {
            $patch['git_token_enc'] = '';
        }
        if ( isset( $p['git_author_name'] ) ) {
            $patch['git_author_name'] = sanitize_text_field( (string) $p['git_author_name'] );
        }
        if ( isset( $p['git_author_email'] ) ) {
            $patch['git_author_email'] = sanitize_email( (string) $p['git_author_email'] );
        }
        if ( isset( $p['git_subpath_map'] ) && is_array( $p['git_subpath_map'] ) ) {
            $cleanMap = [];
            foreach ( $p['git_subpath_map'] as $k => $v ) {
                $rk = trim( PathGuard::normalize( sanitize_text_field( (string) $k ) ), '/' );
                $rv = trim( PathGuard::normalize( sanitize_text_field( (string) $v ) ), '/' );
                if ( '' !== $rv && PathGuard::isSafeRelativePath( $rv ) ) {
                    $cleanMap[ '' !== $rk ? $rk : '.' ] = $rv;
                }
            }
            $patch['git_subpath_map'] = $cleanMap;
        }
        if ( isset( $p['webhook_auto_pull_enabled'] ) ) {
            $patch['webhook_auto_pull_enabled'] = (bool) $p['webhook_auto_pull_enabled'];
        }
        if ( isset( $p['webhook_allow_query_param'] ) ) {
            $patch['webhook_allow_query_param'] = (bool) $p['webhook_allow_query_param'];
        }
        if ( isset( $p['target_mode'] ) && in_array( (string) $p['target_mode'], [ 'same_server', 'remote_pairing' ], true ) ) {
            $patch['target_mode'] = (string) $p['target_mode'];
        }
        if ( isset( $p['target_path'] ) ) {
            $patch['target_path'] = rtrim( PathGuard::normalize( sanitize_text_field( (string) $p['target_path'] ) ), '/' );
        }
        if ( ! empty( $p['target_pairing_token'] ) ) {
            $decoded = RemoteRestTransport::decodePairingToken( (string) $p['target_pairing_token'] );
            if ( null === $decoded ) {
                return new WP_REST_Response( [ 'ok' => false, 'error' => 'Pairing Token không đúng định dạng.' ], 400 );
            }
            $patch['target_pairing_token_enc'] = CryptoVault::encrypt( trim( (string) $p['target_pairing_token'] ) );
        } elseif ( ! empty( $p['clear_target_pairing'] ) ) {
            $patch['target_pairing_token_enc'] = '';
        }
        if ( isset( $p['allow_local_to_prod'] ) ) {
            $patch['allow_local_to_prod'] = (bool) $p['allow_local_to_prod'];
        }
        if ( ! empty( $p['prod_pairing_token'] ) ) {
            $decoded = RemoteRestTransport::decodePairingToken( (string) $p['prod_pairing_token'] );
            if ( null === $decoded ) {
                return new WP_REST_Response( [ 'ok' => false, 'error' => 'Production Pairing Token không đúng định dạng.' ], 400 );
            }
            $patch['prod_pairing_token_enc'] = CryptoVault::encrypt( trim( (string) $p['prod_pairing_token'] ) );
        } elseif ( ! empty( $p['clear_prod_pairing'] ) ) {
            $patch['prod_pairing_token_enc'] = '';
        }
        if ( isset( $p['snapshot_retention'] ) ) {
            $patch['snapshot_retention'] = max( 3, min( 50, (int) $p['snapshot_retention'] ) );
        }
        if ( isset( $p['enable_maintenance_mode'] ) ) {
            $patch['enable_maintenance_mode'] = (bool) $p['enable_maintenance_mode'];
        }
        if ( isset( $p['chunk_size_kb'] ) ) {
            $patch['chunk_size_kb'] = max( 256, min( 4096, (int) $p['chunk_size_kb'] ) );
        }
        if ( isset( $p['delete_data_on_uninstall'] ) ) {
            $patch['delete_data_on_uninstall'] = (bool) $p['delete_data_on_uninstall'];
        }

        Plugin::updateSettings( $patch );
        return new WP_REST_Response( [ 'ok' => true, 'message' => 'Đã lưu cấu hình an toàn!' ], 200 );
    }

    public function importLegacy(): WP_REST_Response {
        $res = MigrationImporter::importFromLegacy();
        return new WP_REST_Response( $res, ! empty( $res['ok'] ) ? 200 : 400 );
    }

    public function gitTest( WP_REST_Request $req ): WP_REST_Response {
        $res = ( new GitHubTransport() )->testConnection( (array) $req->get_json_params() );
        return new WP_REST_Response( $res, ! empty( $res['ok'] ) ? 200 : 400 );
    }

    public function gitBranches(): WP_REST_Response {
        $res = ( new GitHubTransport() )->listBranches();
        return new WP_REST_Response( $res, ! empty( $res['ok'] ) ? 200 : 400 );
    }

    public function gitLatest(): WP_REST_Response {
        $res = ( new GitHubTransport() )->getLatestCommit();
        return new WP_REST_Response( $res, ! empty( $res['ok'] ) ? 200 : 400 );
    }

    public function gitDiff( WP_REST_Request $req ): WP_REST_Response {
        $params    = (array) $req->get_json_params();
        $targetEnv = sanitize_key( (string) ( $params['target_env'] ?? 'current' ) );
        $env       = EnvironmentDetector::detectRole();

        if ( 'push' !== ( $params['direction'] ?? 'pull' ) && 'current' !== $targetEnv && $targetEnv !== $env['role'] ) {
            if ( 'staging' === $targetEnv ) {
                $settings  = Plugin::getSettings();
                $pairToken = Plugin::getSecretSetting( 'target_pairing_token_enc' );
                $pairMeta  = '' !== $pairToken ? RemoteRestTransport::decodePairingToken( $pairToken ) : null;
                $stgDetect = EnvironmentDetector::detectStagingActivation( $settings, $pairMeta );
                if ( empty( $stgDetect['active'] ) ) {
                    return new WP_REST_Response( [ 'ok' => false, 'error' => $stgDetect['reason'] ], 400 );
                }
                if ( 'remote_pairing' === (string) ( $settings['target_mode'] ?? 'same_server' ) ) {
                    $params['target_slot'] = 'staging';
                    $res                   = ( new RemoteRestTransport() )->remoteGitDiff( $params );
                    return new WP_REST_Response( $res, ! empty( $res['ok'] ) ? 200 : 400 );
                }
                $params['target_abspath'] = (string) ( $settings['target_path'] ?? '' );
            } elseif ( 'production' === $targetEnv ) {
                $params['target_slot'] = 'production';
                $res                   = ( new RemoteRestTransport() )->remoteGitDiff( $params );
                return new WP_REST_Response( $res, ! empty( $res['ok'] ) ? 200 : 400 );
            }
        }

        $res = ( new GitHubTransport() )->previewDiff( $params );
        return new WP_REST_Response( $res, ! empty( $res['ok'] ) ? 200 : 400 );
    }

    public function gitPull( WP_REST_Request $req ): WP_REST_Response {
        $params    = (array) $req->get_json_params();
        $targetEnv = sanitize_key( (string) ( $params['target_env'] ?? 'current' ) );
        $env       = EnvironmentDetector::detectRole();

        if ( 'current' !== $targetEnv && $targetEnv !== $env['role'] ) {
            if ( 'staging' === $targetEnv ) {
                $settings  = Plugin::getSettings();
                $pairToken = Plugin::getSecretSetting( 'target_pairing_token_enc' );
                $pairMeta  = '' !== $pairToken ? RemoteRestTransport::decodePairingToken( $pairToken ) : null;
                $stgDetect = EnvironmentDetector::detectStagingActivation( $settings, $pairMeta );
                if ( empty( $stgDetect['active'] ) ) {
                    return new WP_REST_Response( [ 'ok' => false, 'error' => $stgDetect['reason'] ], 400 );
                }
                if ( 'remote_pairing' === (string) ( $settings['target_mode'] ?? 'same_server' ) ) {
                    $params['target_slot'] = 'staging';
                    $res                   = ( new RemoteRestTransport() )->remoteGitPull( $params );
                    return new WP_REST_Response( $res, ! empty( $res['ok'] ) ? 200 : 500 );
                }
                $params['target_abspath']  = (string) ( $settings['target_path'] ?? '' );
                $params['direction_label'] = 'GitHub → Staging (' . ( $stgDetect['target_url'] ?: $params['target_abspath'] ) . ')';
            } elseif ( 'production' === $targetEnv ) {
                if ( empty( $params['confirm_direct_prod'] ) ) {
                    return new WP_REST_Response( [ 'ok' => false, 'error' => 'Thiếu xác nhận an toàn khi đồng bộ GitHub → Production.' ], 400 );
                }
                $prodToken = Plugin::getSecretSetting( 'prod_pairing_token_enc' );
                $prodMeta  = '' !== $prodToken ? RemoteRestTransport::decodePairingToken( $prodToken ) : null;
                if ( null === $prodMeta ) {
                    return new WP_REST_Response( [ 'ok' => false, 'error' => 'Chưa cấu hình Production Pairing Token hợp lệ.' ], 400 );
                }
                $expectedHost = strtolower( (string) wp_parse_url( $prodMeta['site_url'], PHP_URL_HOST ) );
                $givenHost    = strtolower( trim( (string) ( $params['confirm_target_host'] ?? '' ) ) );
                if ( '' === $expectedHost || $givenHost !== $expectedHost ) {
                    return new WP_REST_Response( [ 'ok' => false, 'error' => 'Tên miền xác nhận không khớp với site Production (' . $expectedHost . ').' ], 400 );
                }
                $params['target_slot'] = 'production';
                $res                   = ( new RemoteRestTransport() )->remoteGitPull( $params );
                return new WP_REST_Response( $res, ! empty( $res['ok'] ) ? 200 : 500 );
            }
        }

        $res = ( new GitHubTransport() )->executePull( $params );
        return new WP_REST_Response( $res, ! empty( $res['ok'] ) ? 200 : 500 );
    }

    public function gitPush( WP_REST_Request $req ): WP_REST_Response {
        $res = ( new GitHubTransport() )->executePush( (array) $req->get_json_params() );
        return new WP_REST_Response( $res, ! empty( $res['ok'] ) ? 200 : 500 );
    }

    public function regenWebhookSecret(): WP_REST_Response {
        $newSecret = CryptoVault::randomHex( 24 );
        Plugin::updateSettings( [ 'webhook_secret_enc' => CryptoVault::encrypt( $newSecret ) ] );
        return new WP_REST_Response(
            [
                'ok'             => true,
                'webhook_secret' => $newSecret,
                'webhook_url'    => rest_url( DBHDL_REST_NS . '/webhook/git-pull' ),
            ],
            200
        );
    }

    public function targetDetect(): WP_REST_Response {
        return new WP_REST_Response(
            [
                'ok'       => true,
                'siblings' => EnvironmentDetector::detectSiblingPaths(),
            ],
            200
        );
    }

    public function targetTest( WP_REST_Request $req ): WP_REST_Response {
        $params = (array) $req->get_json_params();
        $slot   = ( 'production' === ( $params['target_slot'] ?? '' ) ) ? 'production' : 'staging';
        $mode   = ( 'production' === $slot )
            ? 'remote_pairing'
            : (string) ( $params['target_mode'] ?? Plugin::getSettings()['target_mode'] ?? 'same_server' );
        $engine = ( 'remote_pairing' === $mode ) ? new RemoteRestTransport() : new SameServerTransport();
        $res    = $engine->testConnection( $params );
        return new WP_REST_Response( $res, ! empty( $res['ok'] ) ? 200 : 400 );
    }

    public function pairingGenerate(): WP_REST_Response {
        return new WP_REST_Response(
            array_merge( [ 'ok' => true ], PairingRestController::generatePairingServerCredentials() ),
            200
        );
    }

    public function pairingRevoke(): WP_REST_Response {
        PairingRestController::revokePairing();
        return new WP_REST_Response( [ 'ok' => true ], 200 );
    }

    public function deployDiff( WP_REST_Request $req ): WP_REST_Response {
        $params = (array) $req->get_json_params();
        $slot   = ( 'production' === ( $params['target_slot'] ?? '' ) ) ? 'production' : 'staging';
        $env    = EnvironmentDetector::detectRole();

        if ( 'staging' === $slot && 'local' === ( $env['role'] ?? '' ) ) {
            $settings  = Plugin::getSettings();
            $pairToken = Plugin::getSecretSetting( 'target_pairing_token_enc' );
            $pairMeta  = '' !== $pairToken ? RemoteRestTransport::decodePairingToken( $pairToken ) : null;
            $stgDetect = EnvironmentDetector::detectStagingActivation( $settings, $pairMeta );
            if ( empty( $stgDetect['active'] ) ) {
                return new WP_REST_Response( [ 'ok' => false, 'error' => $stgDetect['reason'] ], 400 );
            }
        }

        $mode   = ( 'production' === $slot )
            ? 'remote_pairing'
            : (string) ( $params['target_mode'] ?? Plugin::getSettings()['target_mode'] ?? 'same_server' );
        $engine = ( 'remote_pairing' === $mode ) ? new RemoteRestTransport() : new SameServerTransport();
        $res    = $engine->previewDiff( $params );
        return new WP_REST_Response( $res, ! empty( $res['ok'] ) ? 200 : 400 );
    }

    public function deployRun( WP_REST_Request $req ): WP_REST_Response {
        $params = (array) $req->get_json_params();
        $slot   = ( 'production' === ( $params['target_slot'] ?? '' ) ) ? 'production' : 'staging';

        if ( 'staging' === $slot ) {
            $env = EnvironmentDetector::detectRole();
            if ( 'local' === ( $env['role'] ?? '' ) ) {
                $settings  = Plugin::getSettings();
                $pairToken = Plugin::getSecretSetting( 'target_pairing_token_enc' );
                $pairMeta  = '' !== $pairToken ? RemoteRestTransport::decodePairingToken( $pairToken ) : null;
                $stgDetect = EnvironmentDetector::detectStagingActivation( $settings, $pairMeta );
                if ( empty( $stgDetect['active'] ) ) {
                    return new WP_REST_Response( [ 'ok' => false, 'error' => $stgDetect['reason'] ], 400 );
                }
            }
        }

        if ( 'production' === $slot ) {
            $settings = Plugin::getSettings();
            $env      = EnvironmentDetector::detectRole();
            if ( 'local' !== ( $env['role'] ?? '' ) ) {
                return new WP_REST_Response( [ 'ok' => false, 'error' => 'Luồng đẩy trực tiếp Production chỉ được phép chạy từ môi trường LOCAL.' ], 403 );
            }
            if ( empty( $settings['allow_local_to_prod'] ) ) {
                return new WP_REST_Response( [ 'ok' => false, 'error' => 'Tính năng đẩy trực tiếp Local → Production đang bị tắt trong Cài đặt.' ], 403 );
            }
            if ( empty( $params['confirm_direct_prod'] ) ) {
                return new WP_REST_Response( [ 'ok' => false, 'error' => 'Thiếu xác nhận an toàn khi đẩy trực tiếp lên Production.' ], 400 );
            }

            $prodToken = Plugin::getSecretSetting( 'prod_pairing_token_enc' );
            $prodMeta  = '' !== $prodToken ? RemoteRestTransport::decodePairingToken( $prodToken ) : null;
            if ( null === $prodMeta ) {
                return new WP_REST_Response( [ 'ok' => false, 'error' => 'Chưa cấu hình Production Pairing Token hợp lệ.' ], 400 );
            }
            $expectedHost = strtolower( (string) wp_parse_url( $prodMeta['site_url'], PHP_URL_HOST ) );
            $givenHost    = strtolower( trim( (string) ( $params['confirm_target_host'] ?? '' ) ) );
            if ( '' === $expectedHost || $givenHost !== $expectedHost ) {
                return new WP_REST_Response(
                    [ 'ok' => false, 'error' => 'Tên miền xác nhận không khớp với site Production (' . $expectedHost . ').' ],
                    400
                );
            }

            $engine = new RemoteRestTransport();
            $res    = $engine->execute( $params );
            return new WP_REST_Response( $res, ! empty( $res['ok'] ) ? 200 : 500 );
        }

        $mode   = (string) ( $params['target_mode'] ?? Plugin::getSettings()['target_mode'] ?? 'same_server' );
        $engine = ( 'remote_pairing' === $mode ) ? new RemoteRestTransport() : new SameServerTransport();
        $res    = $engine->execute( $params );
        return new WP_REST_Response( $res, ! empty( $res['ok'] ) ? 200 : 500 );
    }

    public function zipInspect( WP_REST_Request $req ): WP_REST_Response {
        $files = $req->get_file_params();
        $file  = $files['package'] ?? null;
        if ( ! is_array( $file ) || empty( $file['tmp_name'] ) || ! empty( $file['error'] ) ) {
            return new WP_REST_Response( [ 'ok' => false, 'error' => 'Vui lòng chọn file .zip hợp lệ để tải lên.' ], 400 );
        }
        $targetPrefix = sanitize_text_field( (string) $req->get_param( 'target_prefix' ) );
        $res          = ZipUploadTransport::inspectUpload( (string) $file['tmp_name'], (string) ( $file['name'] ?? 'package.zip' ), $targetPrefix );
        return new WP_REST_Response( $res, ! empty( $res['ok'] ) ? 200 : 400 );
    }

    public function zipApply( WP_REST_Request $req ): WP_REST_Response {
        $p   = (array) $req->get_json_params();
        $res = ZipUploadTransport::applyUpload(
            (string) ( $p['upload_id'] ?? '' ),
            (array) ( $p['excluded_files'] ?? [] ),
            ! empty( $p['mirror_delete'] )
        );
        return new WP_REST_Response( $res, ! empty( $res['ok'] ) ? 200 : 400 );
    }

    public function snapshotsList(): WP_REST_Response {
        return new WP_REST_Response( [ 'ok' => true, 'snapshots' => SnapshotManager::listSnapshots( 30 ) ], 200 );
    }

    public function snapshotsRollback( WP_REST_Request $req ): WP_REST_Response {
        $p   = (array) $req->get_json_params();
        $res = RollbackEngine::restore( (string) ( $p['filename'] ?? '' ) );
        return new WP_REST_Response( $res, ! empty( $res['ok'] ) ? 200 : 400 );
    }

    public function snapshotsDelete( WP_REST_Request $req ): WP_REST_Response {
        $p  = (array) $req->get_json_params();
        $ok = SnapshotManager::deleteSnapshot( (string) ( $p['filename'] ?? '' ) );
        return new WP_REST_Response( [ 'ok' => $ok ], $ok ? 200 : 404 );
    }

    public function snapshotsDownloadTicket( WP_REST_Request $req ): WP_REST_Response {
        $p        = (array) $req->get_json_params();
        $filename = basename( (string) ( $p['filename'] ?? '' ) );
        if ( null === SnapshotManager::getSnapshotPath( $filename ) ) {
            return new WP_REST_Response( [ 'ok' => false, 'error' => 'Snapshot không tồn tại.' ], 404 );
        }
        $ticket = CryptoVault::randomHex( 16 );
        set_transient( 'dbhdl_dl_' . $ticket, $filename, 120 );
        return new WP_REST_Response(
            [
                'ok'           => true,
                'download_url' => add_query_arg( 'ticket', $ticket, rest_url( DBHDL_REST_NS . '/snapshots/download' ) ),
            ],
            200
        );
    }

    public function snapshotsDownloadFile( WP_REST_Request $req ) {
        $ticket = sanitize_key( (string) $req->get_param( 'ticket' ) );
        if ( '' === $ticket ) {
            return new WP_REST_Response( [ 'error' => 'Missing ticket' ], 403 );
        }
        $filename = get_transient( 'dbhdl_dl_' . $ticket );
        delete_transient( 'dbhdl_dl_' . $ticket );
        if ( ! is_string( $filename ) ) {
            return new WP_REST_Response( [ 'error' => 'Invalid or expired download ticket' ], 403 );
        }
        $fullPath = SnapshotManager::getSnapshotPath( $filename );
        if ( null === $fullPath ) {
            return new WP_REST_Response( [ 'error' => 'Snapshot not found' ], 404 );
        }

        header( 'Content-Type: application/zip' );
        header( 'Content-Disposition: attachment; filename="' . basename( $fullPath ) . '"' );
        header( 'Content-Length: ' . (string) filesize( $fullPath ) );
        readfile( $fullPath );
        exit;
    }

    public function vaultSaveBatch( WP_REST_Request $req ): WP_REST_Response {
        $p   = (array) $req->get_json_params();
        $res = SecretsVaultManager::updateSecretsBatch(
            (array) ( $p['keys'] ?? [] ),
            (array) ( $p['clear_slugs'] ?? [] )
        );
        return new WP_REST_Response(
            [
                'ok'       => true,
                'updated'  => $res['updated'],
                'cleared'  => $res['cleared'],
                'services' => SecretsVaultManager::getMaskedServices(),
            ],
            200
        );
    }

    public function vaultSaveService( WP_REST_Request $req ): WP_REST_Response {
        $res = SecretsVaultManager::saveService( (array) $req->get_json_params() );
        if ( empty( $res['ok'] ) ) {
            return new WP_REST_Response( $res, 400 );
        }
        return new WP_REST_Response(
            [
                'ok'       => true,
                'service'  => $res['service'],
                'services' => SecretsVaultManager::getMaskedServices(),
            ],
            200
        );
    }

    public function vaultDeleteService( WP_REST_Request $req ): WP_REST_Response {
        $p  = (array) $req->get_json_params();
        $ok = SecretsVaultManager::deleteService( (string) ( $p['slug'] ?? '' ) );
        return new WP_REST_Response(
            [
                'ok'       => $ok,
                'services' => SecretsVaultManager::getMaskedServices(),
            ],
            $ok ? 200 : 404
        );
    }

    public function logsList(): WP_REST_Response {
        return new WP_REST_Response( [ 'ok' => true, 'logs' => JobStateStore::getLogs( 100 ) ], 200 );
    }

    public function logsClear(): WP_REST_Response {
        JobStateStore::clearLogs();
        return new WP_REST_Response( [ 'ok' => true ], 200 );
    }
}
