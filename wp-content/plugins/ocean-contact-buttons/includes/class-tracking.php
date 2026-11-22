<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class OCB_Tracking {
    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'ocb_click_stats';
    }

    public static function install() {
        global $wpdb;
        $table = self::table();
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_date date NOT NULL,
            channel varchar(20) NOT NULL,
            clicks bigint(20) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY date_channel (event_date,channel),
            KEY channel (channel)
        ) {$charset};";
        dbDelta( $sql );
        update_option( 'ocb_tracking_db_version', '1', false );
    }

    public static function maybe_install() {
        if ( get_option( 'ocb_tracking_db_version' ) !== '1' ) {
            self::install();
        }
    }

    public static function hooks() {
        add_action( 'wp_ajax_ocb_track_click', array( __CLASS__, 'record' ) );
        add_action( 'wp_ajax_nopriv_ocb_track_click', array( __CLASS__, 'record' ) );
        add_action( 'admin_init', array( __CLASS__, 'maybe_install' ) );
        add_action( 'init', array( __CLASS__, 'maybe_install' ) );
    }

    public static function record() {
        check_ajax_referer( 'ocb_track_click', 'nonce' );
        $channel = isset( $_POST['channel'] ) ? sanitize_key( wp_unslash( $_POST['channel'] ) ) : '';
        if ( ! in_array( $channel, array( 'call', 'call_north', 'call_central', 'call_south', 'zalo', 'messenger' ), true ) ) {
            wp_send_json_error( array( 'message' => 'Invalid channel' ), 400 );
        }
        global $wpdb;
        $table = self::table();
        $today = current_time( 'Y-m-d' );
        $sql = $wpdb->prepare(
            "INSERT INTO {$table} (event_date, channel, clicks) VALUES (%s, %s, 1)
            ON DUPLICATE KEY UPDATE clicks = clicks + 1",
            $today,
            $channel
        );
        $result = $wpdb->query( $sql );
        if ( false === $result ) {
            wp_send_json_error( array( 'message' => 'Could not save click' ), 500 );
        }
        wp_send_json_success();
    }

    public static function totals( $days = 30 ) {
        global $wpdb;
        $table = self::table();
        $days = max( 1, min( 365, absint( $days ) ) );
        $from = wp_date( 'Y-m-d', current_time( 'timestamp' ) - ( $days - 1 ) * DAY_IN_SECONDS );
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT channel, SUM(clicks) AS total FROM {$table} WHERE event_date >= %s GROUP BY channel",
            $from
        ), OBJECT_K );
        $result = array( 'call' => 0, 'call_north' => 0, 'call_central' => 0, 'call_south' => 0, 'zalo' => 0, 'messenger' => 0 );
        foreach ( $result as $key => $unused ) {
            if ( isset( $rows[ $key ] ) ) { $result[ $key ] = (int) $rows[ $key ]->total; }
        }
        return $result;
    }

    public static function daily( $days = 30 ) {
        global $wpdb;
        $table = self::table();
        $days = max( 1, min( 365, absint( $days ) ) );
        $from = wp_date( 'Y-m-d', current_time( 'timestamp' ) - ( $days - 1 ) * DAY_IN_SECONDS );
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT event_date, channel, clicks FROM {$table} WHERE event_date >= %s ORDER BY event_date DESC",
            $from
        ) );
    }
}
