<?php
/**
 * Uninstall handler for Đồng Bộ Hóa Dữ Liệu.
 * Only removes plugin options and snapshots when explicitly opted in via Settings.
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

$settings = get_option( 'dbhdl_settings', [] );
if ( ! is_array( $settings ) || empty( $settings['delete_data_on_uninstall'] ) ) {
    return;
}

$options = [
    'dbhdl_settings',
    'dbhdl_vault_registry',
    'dbhdl_vault_secrets',
    'dbhdl_audit_logs',
    'dbhdl_pairing_server',
    'dbhdl_pending_cache_purge',
    'dbhdl_snapshot_dir_token',
    'dbhdl_local_master_key',
];

foreach ( $options as $opt ) {
    delete_option( $opt );
}

global $wpdb;
if ( isset( $wpdb ) ) {
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            $wpdb->esc_like( '_transient_dbhdl_' ) . '%',
            $wpdb->esc_like( '_transient_timeout_dbhdl_' ) . '%'
        )
    );
}
