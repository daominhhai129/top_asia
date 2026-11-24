<?php
declare(strict_types=1);

namespace DBHDL\Sync\Jobs;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Persistent audit log ring-buffer and chunked job state manager.
 */
final class JobStateStore {

    public const OPTION_AUDIT_LOGS = 'dbhdl_audit_logs';
    private const MAX_LOGS         = 200;

    /**
     * Append a structured audit log entry (newest first).
     *
     * @param array<string, mixed> $entry
     */
    public static function appendLog( array $entry ): void {
        if ( ! function_exists( 'get_option' ) || ! function_exists( 'update_option' ) ) {
            return;
        }

        $logs = get_option( self::OPTION_AUDIT_LOGS, [] );
        if ( ! is_array( $logs ) ) {
            $logs = [];
        }

        $actor = 'system';
        if ( function_exists( 'wp_get_current_user' ) ) {
            $user = wp_get_current_user();
            if ( $user && ! empty( $user->user_login ) ) {
                $actor = (string) $user->user_login;
            }
        }

        $record = [
            'id'         => substr( md5( uniqid( 'log_', true ) ), 0, 12 ),
            'time'       => function_exists( 'current_time' ) ? current_time( 'mysql' ) : gmdate( 'Y-m-d H:i:s' ),
            'actor'      => $actor,
            'action'     => sanitize_key( (string) ( $entry['action'] ?? 'sync' ) ),
            'direction'  => (string) ( $entry['direction'] ?? '' ),
            'transport'  => (string) ( $entry['transport'] ?? '' ),
            'status'     => (string) ( $entry['status'] ?? 'success' ),
            'summary'    => (string) ( $entry['summary'] ?? '' ),
            'snapshot'   => (string) ( $entry['snapshot'] ?? '' ),
            'files_cnt'  => (int) ( $entry['files_cnt'] ?? 0 ),
            'db_cnt'     => (int) ( $entry['db_cnt'] ?? 0 ),
            'details'    => array_slice( array_map( 'strval', (array) ( $entry['details'] ?? [] ) ), 0, 100 ),
        ];

        array_unshift( $logs, $record );
        if ( count( $logs ) > self::MAX_LOGS ) {
            $logs = array_slice( $logs, 0, self::MAX_LOGS );
        }

        update_option( self::OPTION_AUDIT_LOGS, $logs, false );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function getLogs( int $limit = 50 ): array {
        if ( ! function_exists( 'get_option' ) ) {
            return [];
        }
        $logs = get_option( self::OPTION_AUDIT_LOGS, [] );
        if ( ! is_array( $logs ) ) {
            return [];
        }
        return array_slice( $logs, 0, max( 1, $limit ) );
    }

    public static function clearLogs(): void {
        if ( function_exists( 'delete_option' ) ) {
            delete_option( self::OPTION_AUDIT_LOGS );
        }
    }

    /**
     * Store temporary job state (chunked transfer session).
     *
     * @param array<string, mixed> $state
     */
    public static function saveJob( string $jobId, array $state, int $ttl = 3600 ): void {
        if ( function_exists( 'set_transient' ) ) {
            set_transient( 'dbhdl_job_' . sanitize_key( $jobId ), $state, $ttl );
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function getJob( string $jobId ): ?array {
        if ( ! function_exists( 'get_transient' ) ) {
            return null;
        }
        $val = get_transient( 'dbhdl_job_' . sanitize_key( $jobId ) );
        return is_array( $val ) ? $val : null;
    }

    public static function deleteJob( string $jobId ): void {
        if ( function_exists( 'delete_transient' ) ) {
            delete_transient( 'dbhdl_job_' . sanitize_key( $jobId ) );
        }
    }
}
