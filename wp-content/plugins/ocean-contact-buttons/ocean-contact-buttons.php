<?php
/**
 * Plugin Name: Ocean Contact Buttons
 * Description: Thanh liên hệ nổi với popup chọn hotline theo khu vực, Zalo và Messenger.
 * Version: 1.2.2
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Ocean
 * Text Domain: ocean-contact-buttons
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'OCB_VERSION', '1.2.2' );
define( 'OCB_FILE', __FILE__ );
define( 'OCB_PATH', plugin_dir_path( __FILE__ ) );
define( 'OCB_URL', plugin_dir_url( __FILE__ ) );

require_once OCB_PATH . 'includes/class-settings.php';
require_once OCB_PATH . 'includes/class-tracking.php';
require_once OCB_PATH . 'includes/class-admin.php';
require_once OCB_PATH . 'includes/class-frontend.php';

register_activation_hook( __FILE__, array( 'OCB_Settings', 'activate' ) );

function ocb_bootstrap() {
    OCB_Settings::instance();
    OCB_Tracking::hooks();
    if ( is_admin() ) {
        OCB_Admin::instance();
    }
    OCB_Frontend::instance();
}
add_action( 'plugins_loaded', 'ocb_bootstrap' );
