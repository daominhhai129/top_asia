<?php
/**
 * Plugin Name:       TA Quick Image Replace
 * Description:       Thay nhanh bất kỳ ảnh nào trực tiếp trên giao diện web bằng Media Library, dán URL hoặc Ctrl+V (copy-paste ảnh trực tiếp).
 * Version:           1.0.0
 * Author:            Top Asia
 * Text Domain:       ta-quick-image-replace
 * Requires at least: 6.0
 * Requires PHP:      7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TA_QIR_VERSION', '1.0.0' );
define( 'TA_QIR_PLUGIN_FILE', __FILE__ );
define( 'TA_QIR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TA_QIR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once TA_QIR_PLUGIN_DIR . 'includes/class-storage.php';
require_once TA_QIR_PLUGIN_DIR . 'includes/class-replacer.php';
require_once TA_QIR_PLUGIN_DIR . 'includes/class-rest-api.php';
require_once TA_QIR_PLUGIN_DIR . 'includes/class-admin.php';

/**
 * Khởi tạo các thành phần của plugin.
 */
function ta_qir_init() {
	TA_QIR_Storage::init();
	TA_QIR_Replacer::init();
	TA_QIR_REST_API::init();
	TA_QIR_Admin::init();
}
add_action( 'plugins_loaded', 'ta_qir_init' );
