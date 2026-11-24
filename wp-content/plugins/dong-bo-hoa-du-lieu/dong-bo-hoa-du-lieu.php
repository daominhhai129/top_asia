<?php
/**
 * Plugin Name:       Đồng Bộ Hóa Dữ Liệu
 * Plugin URI:        https://wordpress.org/plugins/
 * Description:       Trung tâm đồng bộ mã nguồn, giao diện, cơ sở dữ liệu chọn lọc và biến môi trường cho mọi website WordPress theo quy trình Local → Staging → Production (hỗ trợ GitHub, Remote Pairing HTTPS, Same-Server & Upload ZIP).
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Open WP Engineering
 * License:           GPLv2 or later
 * Text Domain:       dong-bo-hoa-du-lieu
 * Domain Path:       /languages
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Emergency kill switch: place an empty `.disabled` file in plugin root to halt loading immediately.
if ( file_exists( __DIR__ . '/.disabled' ) ) {
    return;
}

if ( version_compare( PHP_VERSION, '8.0.0', '<' ) ) {
    add_action(
        'admin_notices',
        static function (): void {
            if ( ! current_user_can( 'activate_plugins' ) ) {
                return;
            }
            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                esc_html(
                    sprintf(
                        'Plugin Đồng Bộ Hóa Dữ Liệu yêu cầu PHP 8.0 trở lên. Phiên bản hiện tại: %s.',
                        PHP_VERSION
                    )
                )
            );
        }
    );
    return;
}

define( 'DBHDL_VERSION', '1.0.0' );
define( 'DBHDL_FILE', __FILE__ );
define( 'DBHDL_DIR', plugin_dir_path( __FILE__ ) );
define( 'DBHDL_URL', plugin_dir_url( __FILE__ ) );
define( 'DBHDL_REST_NS', 'dbhdl/v1' );

// Zero-Composer PSR-4 autoloader for DBHDL\Sync\*
spl_autoload_register(
    static function ( string $class ): void {
        $prefix = 'DBHDL\\Sync\\';
        $len    = strlen( $prefix );
        if ( strncmp( $prefix, $class, $len ) !== 0 ) {
            return;
        }
        $relative = substr( $class, $len );
        $file     = DBHDL_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';
        if ( is_file( $file ) ) {
            require_once $file;
        }
    }
);

register_activation_hook(
    __FILE__,
    static function (): void {
        if ( file_exists( __DIR__ . '/.disabled' ) ) {
            return;
        }
        \DBHDL\Sync\Plugin::activate();
    }
);

register_deactivation_hook(
    __FILE__,
    static function (): void {
        \DBHDL\Sync\Plugin::deactivate();
    }
);

add_action(
    'plugins_loaded',
    static function (): void {
        if ( file_exists( __DIR__ . '/.disabled' ) ) {
            return;
        }
        \DBHDL\Sync\Plugin::instance()->boot();
    },
    15
);
