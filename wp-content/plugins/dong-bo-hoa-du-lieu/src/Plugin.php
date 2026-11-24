<?php
declare(strict_types=1);

namespace DBHDL\Sync;

use DBHDL\Sync\Admin\AdminMenu;
use DBHDL\Sync\Jobs\CachePurger;
use DBHDL\Sync\REST\PairingRestController;
use DBHDL\Sync\REST\SyncRestController;
use DBHDL\Sync\REST\WebhookController;
use DBHDL\Sync\Security\CryptoVault;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Main plugin coordinator for Đồng Bộ Hóa Dữ Liệu.
 */
final class Plugin {

    public const OPTION_SETTINGS = 'dbhdl_settings';
    public const OPTION_PAIRING  = 'dbhdl_pairing_server';

    private static ?self $instance = null;
    private bool $booted           = false;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function activate(): void {
        if ( ! get_option( self::OPTION_SETTINGS, false ) ) {
            add_option( self::OPTION_SETTINGS, self::defaultSettings(), '', false );
        }
    }

    public static function deactivate(): void {
        // Non-destructive deactivation preserves settings and snapshots.
    }

    public function boot(): void {
        if ( $this->booted ) {
            return;
        }
        $this->booted = true;

        if ( function_exists( 'load_plugin_textdomain' ) ) {
            load_plugin_textdomain(
                'dong-bo-hoa-du-lieu',
                false,
                dirname( plugin_basename( DBHDL_FILE ) ) . '/languages'
            );
        }

        add_action( 'init', [ $this, 'handleEarlyMaintenanceAndPurge' ], 1 );
        add_action( 'rest_api_init', [ $this, 'registerRestRoutes' ] );

        ( new AdminMenu() )->register();
    }

    public function registerRestRoutes(): void {
        ( new SyncRestController() )->registerRoutes();
        ( new PairingRestController() )->registerRoutes();
        ( new WebhookController() )->registerRoutes();
    }

    /**
     * Flush queued target caches after a remote/cross-env sync and clean up stale `.maintenance`.
     */
    public function handleEarlyMaintenanceAndPurge(): void {
        if ( defined( 'ABSPATH' ) ) {
            $maint = rtrim( ABSPATH, '/\\' ) . '/.maintenance';
            if ( is_file( $maint ) ) {
                $age = time() - (int) @filemtime( $maint );
                if ( $age > 120 ) {
                    @unlink( $maint );
                }
            }
        }

        if ( '1' === get_option( 'dbhdl_pending_cache_purge', '' ) ) {
            delete_option( 'dbhdl_pending_cache_purge' );
            CachePurger::purgeLocal();
        }
    }

    /**
     * Execute a critical cutover callback with optional temporary `.maintenance` protection.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public static function withMaintenanceMode( bool $enabled, callable $callback ) {
        $maintFile = ( $enabled && defined( 'ABSPATH' ) ) ? rtrim( ABSPATH, '/\\' ) . '/.maintenance' : '';
        $written   = false;
        if ( '' !== $maintFile && is_writable( dirname( $maintFile ) ) ) {
            $written = false !== @file_put_contents( $maintFile, "<?php\n\$upgrading = " . time() . ";\n" );
        }

        try {
            return $callback();
        } finally {
            if ( $written && is_file( $maintFile ) ) {
                @unlink( $maintFile );
            }
        }
    }

    public static function requiredCapability(): string {
        $cap = function_exists( 'apply_filters' )
            ? (string) apply_filters( 'dbhdl_required_capability', 'manage_options' )
            : 'manage_options';
        return '' !== $cap ? $cap : 'manage_options';
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultSettings(): array {
        return [
            'env_role'                  => 'auto',
            'git_provider'              => 'github',
            'git_api_base'              => 'https://api.github.com',
            'git_repo'                  => '',
            'git_branch'                => 'main',
            'git_token_enc'             => '',
            'git_author_name'           => '',
            'git_author_email'          => '',
            'git_subpath_map'           => [],
            'webhook_secret_enc'        => '',
            'webhook_allow_query_param' => false,
            'webhook_auto_pull_enabled' => true,
            'target_mode'               => 'same_server', // same_server | remote_pairing
            'target_path'               => '',
            'target_pairing_token_enc'  => '',
            'allow_local_to_prod'       => true,
            'prod_pairing_token_enc'    => '',
            'snapshot_retention'        => 10,
            'enable_maintenance_mode'   => false,
            'chunk_size_kb'             => 1536,
            'delete_data_on_uninstall'  => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function getSettings(): array {
        $raw = function_exists( 'get_option' ) ? get_option( self::OPTION_SETTINGS, [] ) : [];
        if ( ! is_array( $raw ) ) {
            $raw = [];
        }
        return array_merge( self::defaultSettings(), $raw );
    }

    /**
     * Merge and persist settings cleanly.
     *
     * @param array<string, mixed> $patch
     * @return array<string, mixed>
     */
    public static function updateSettings( array $patch ): array {
        $next = array_merge( self::getSettings(), $patch );
        if ( function_exists( 'update_option' ) ) {
            update_option( self::OPTION_SETTINGS, $next, false );
        }
        return $next;
    }

    /**
     * Retrieve decrypted setting secret safely.
     */
    public static function getSecretSetting( string $encKey ): string {
        $settings = self::getSettings();
        $enc      = (string) ( $settings[ $encKey ] ?? '' );
        return '' !== $enc ? CryptoVault::decryptSafe( $enc ) : '';
    }
}
