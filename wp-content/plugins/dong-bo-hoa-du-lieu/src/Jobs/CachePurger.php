<?php
declare(strict_types=1);

namespace DBHDL\Sync\Jobs;

use DBHDL\Sync\Security\PathGuard;
use wpdb;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Multi-plugin cache flusher for PHP OPcache, WP Object Cache, Elementor CSS,
 * LiteSpeed, WP Rocket, W3 Total Cache, and WP Super Cache.
 */
final class CachePurger {

    /**
     * Flush caches on the current running WordPress installation.
     *
     * @return array<int, string> List of flushed cache layers.
     */
    public static function purgeLocal(): array {
        $flushed = [];

        if ( function_exists( 'opcache_reset' ) ) {
            if ( @opcache_reset() ) {
                $flushed[] = 'PHP OPcache';
            }
        }

        if ( function_exists( 'wp_cache_flush' ) ) {
            @wp_cache_flush();
            $flushed[] = 'WP Object Cache';
        }

        if ( defined( 'WP_CONTENT_DIR' ) ) {
            $elCssDir = rtrim( PathGuard::normalize( WP_CONTENT_DIR ), '/' ) . '/uploads/elementor/css';
            if ( is_dir( $elCssDir ) ) {
                $deleted = 0;
                foreach ( (array) @glob( $elCssDir . '/*.css' ) as $cf ) {
                    if ( is_file( $cf ) && @unlink( $cf ) ) {
                        $deleted++;
                    }
                }
                if ( $deleted > 0 ) {
                    $flushed[] = sprintf( 'Elementor CSS (%d files)', $deleted );
                }
            }
        }

        if ( function_exists( 'has_action' ) && has_action( 'litespeed_purge_all' ) ) {
            do_action( 'litespeed_purge_all' );
            $flushed[] = 'LiteSpeed Cache';
        } elseif ( class_exists( 'LiteSpeed\\Purge' ) ) {
            \LiteSpeed\Purge::purge_all();
            $flushed[] = 'LiteSpeed Cache';
        }

        if ( function_exists( 'rocket_clean_domain' ) ) {
            @rocket_clean_domain();
            $flushed[] = 'WP Rocket';
        }
        if ( function_exists( 'w3tc_flush_all' ) ) {
            @w3tc_flush_all();
            $flushed[] = 'W3 Total Cache';
        }
        if ( function_exists( 'wp_cache_clear_cache' ) ) {
            @wp_cache_clear_cache();
            $flushed[] = 'WP Super Cache';
        }

        return array_values( array_unique( $flushed ) );
    }

    /**
     * Trigger cache purge on a Same-Server target directory + DB and send a non-blocking warm request.
     *
     * @return array<int, string>
     */
    public static function purgeTarget( ?wpdb $targetDb, string $targetBaseDir = '', string $targetUrl = '' ): array {
        $flushed = self::purgeLocal();

        if ( '' !== $targetBaseDir ) {
            $elDir = rtrim( PathGuard::normalize( $targetBaseDir ), '/' ) . '/wp-content/uploads/elementor/css';
            if ( is_dir( $elDir ) ) {
                foreach ( (array) @glob( $elDir . '/*.css' ) as $cf ) {
                    if ( is_file( $cf ) ) {
                        @unlink( $cf );
                    }
                }
                $flushed[] = 'Target Elementor CSS';
            }
        }

        if ( $targetDb instanceof wpdb ) {
            $exists = $targetDb->get_var(
                $targetDb->prepare( "SELECT option_id FROM {$targetDb->options} WHERE option_name = %s LIMIT 1", 'dbhdl_pending_cache_purge' )
            );
            if ( $exists ) {
                $targetDb->update(
                    $targetDb->options,
                    [ 'option_value' => '1', 'autoload' => 'yes' ],
                    [ 'option_id' => (int) $exists ]
                );
            } else {
                $targetDb->insert(
                    $targetDb->options,
                    [ 'option_name' => 'dbhdl_pending_cache_purge', 'option_value' => '1', 'autoload' => 'yes' ]
                );
            }
            $flushed[] = 'Target Cache Flag';
        }

        if ( '' !== $targetUrl && function_exists( 'wp_remote_get' ) ) {
            wp_remote_get(
                add_query_arg( 'dbhdl_warm', (string) time(), rtrim( $targetUrl, '/' ) . '/' ),
                [
                    'timeout'  => 3,
                    'blocking' => false,
                ]
            );
        }

        return array_values( array_unique( $flushed ) );
    }
}
