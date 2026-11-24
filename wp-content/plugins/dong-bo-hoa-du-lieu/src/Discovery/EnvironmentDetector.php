<?php
declare(strict_types=1);

namespace DBHDL\Sync\Discovery;

use DBHDL\Sync\Plugin;
use DBHDL\Sync\Security\PathGuard;
use wpdb;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Environment role detector (Local / Staging / Production), sibling WP path finder,
 * and token-based `wp-config.php` parser (zero regex fragility).
 */
final class EnvironmentDetector {

    public const VALID_ROLES = [ 'local', 'staging', 'production' ];

    /**
     * Resolve effective environment role (explicit setting overrides auto-detection).
     *
     * @return array{role: string, auto_role: string, source: string, url: string, abspath: string}
     */
    public static function detectRole(): array {
        $settings   = Plugin::getSettings();
        $configured = (string) ( $settings['env_role'] ?? 'auto' );
        $autoRole   = self::guessRole();
        $role       = in_array( $configured, self::VALID_ROLES, true ) ? $configured : $autoRole;

        return [
            'role'      => $role,
            'auto_role' => $autoRole,
            'source'    => in_array( $configured, self::VALID_ROLES, true ) ? 'manual' : 'auto',
            'url'       => function_exists( 'home_url' ) ? rtrim( home_url(), '/' ) : '',
            'abspath'   => defined( 'ABSPATH' ) ? rtrim( PathGuard::normalize( ABSPATH ), '/' ) : '',
        ];
    }

    /**
     * Infer environment role from WP_ENVIRONMENT_TYPE, domain host, or path.
     */
    public static function guessRole(): string {
        if ( function_exists( 'wp_get_environment_type' ) ) {
            $wpEnv = wp_get_environment_type();
            if ( 'local' === $wpEnv || 'development' === $wpEnv ) {
                return 'local';
            }
            if ( 'staging' === $wpEnv ) {
                return 'staging';
            }
        }

        $home   = function_exists( 'home_url' ) ? strtolower( (string) home_url() ) : '';
        $host   = (string) parse_url( $home, PHP_URL_HOST );
        $path   = (string) parse_url( $home, PHP_URL_PATH );
        $abs    = defined( 'ABSPATH' ) ? strtolower( PathGuard::normalize( ABSPATH ) ) : '';

        if (
            in_array( $host, [ 'localhost', '127.0.0.1', '::1' ], true )
            || preg_match( '/\.(local|test|localhost|dev)$/i', $host )
        ) {
            return 'local';
        }

        if (
            str_starts_with( $host, 'staging.' )
            || str_starts_with( $host, 'stg.' )
            || str_starts_with( $host, 'dev.' )
            || str_contains( $home, 'staging' )
            || str_contains( $abs, '/staging' )
        ) {
            return 'staging';
        }

        return 'production';
    }

    /**
     * Check whether Local -> Staging sync is auto-activated (requires "staging" in target/current URL).
     *
     * @param array<string, mixed>      $settings
     * @param array<string, mixed>|null $pairMeta
     * @return array{active: bool, target_url: string, current_url: string, matched_url: string, reason: string}
     */
    public static function detectStagingActivation( array $settings, ?array $pairMeta = null ): array {
        $currentUrl = function_exists( 'home_url' ) ? rtrim( (string) home_url(), '/' ) : '';
        $mode       = (string) ( $settings['target_mode'] ?? 'same_server' );
        $targetUrl  = '';

        if ( 'remote_pairing' === $mode ) {
            $targetUrl = rtrim( (string) ( $pairMeta['site_url'] ?? '' ), '/' );
        } else {
            $targetPath = rtrim( PathGuard::normalize( (string) ( $settings['target_path'] ?? '' ) ), '/' );
            if ( '' !== $targetPath && is_dir( $targetPath ) ) {
                $targetUrl = self::probeSiteUrl( $targetPath );
            }
        }

        $targetHasStaging  = '' !== $targetUrl && false !== stripos( $targetUrl, 'staging' );
        $currentHasStaging = '' !== $currentUrl && false !== stripos( $currentUrl, 'staging' );
        $active            = $targetHasStaging || $currentHasStaging;
        $matchedUrl        = $targetHasStaging ? $targetUrl : ( $currentHasStaging ? $currentUrl : '' );

        $reason = $active
            ? 'Tự động kích hoạt vì phát hiện chữ "staging" trong URL: ' . $matchedUrl
            : 'Chưa kích hoạt: URL trang đích Staging (hoặc trang hiện tại) chưa chứa chữ "staging".';

        return [
            'active'      => $active,
            'target_url'  => $targetUrl,
            'current_url' => $currentUrl,
            'matched_url' => $matchedUrl,
            'reason'      => $reason,
        ];
    }

    /**
     * Parse DB connection settings and `$table_prefix` from a target `wp-config.php` using PHP tokens.
     *
     * @param array<int, string> $extraConstants
     * @return array{ok: bool, error?: string, db?: array<string, string>, constants?: array<string, string>, dynamic_constants?: array<int, string>}
     */
    public static function parseWpConfig( string $configPath, array $extraConstants = [] ): array {
        if ( ! is_file( $configPath ) || ! is_readable( $configPath ) ) {
            return [ 'ok' => false, 'error' => 'Không đọc được tệp wp-config.php tại đường dẫn đích.' ];
        }

        $source = @file_get_contents( $configPath );
        if ( false === $source || '' === $source ) {
            return [ 'ok' => false, 'error' => 'Tệp wp-config.php rỗng hoặc không có quyền đọc.' ];
        }

        $tokens = @token_get_all( $source );
        $clean  = [];
        foreach ( $tokens as $tok ) {
            if ( is_array( $tok ) && in_array( $tok[0], [ T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ], true ) ) {
                continue;
            }
            $clean[] = $tok;
        }

        $watchConstants = array_unique(
            array_merge(
                [ 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_HOST', 'DB_CHARSET', 'WP_ENVIRONMENT_TYPE' ],
                $extraConstants
            )
        );

        $constants        = [];
        $dynamicConstants = [];
        $tablePrefix      = '';
        $count            = count( $clean );

        for ( $i = 0; $i < $count; $i++ ) {
            $tok = $clean[ $i ];

            // Parse define( 'CONST_NAME', 'value' )
            if (
                is_array( $tok )
                && T_STRING === $tok[0]
                && 'define' === strtolower( $tok[1] )
                && isset( $clean[ $i + 1 ], $clean[ $i + 2 ], $clean[ $i + 3 ], $clean[ $i + 4 ] )
                && '(' === $clean[ $i + 1 ]
                && is_array( $clean[ $i + 2 ] )
                && T_CONSTANT_ENCAPSED_STRING === $clean[ $i + 2 ][0]
                && ',' === $clean[ $i + 3 ]
            ) {
                $name = self::unquoteLiteral( $clean[ $i + 2 ][1] );
                if ( in_array( $name, $watchConstants, true ) ) {
                    $valTok = $clean[ $i + 4 ];
                    if ( is_array( $valTok ) && T_CONSTANT_ENCAPSED_STRING === $valTok[0] && ')' === ( $clean[ $i + 5 ] ?? '' ) ) {
                        $constants[ $name ] = self::unquoteLiteral( $valTok[1] );
                    } else {
                        $dynamicConstants[] = $name;
                    }
                }
            }

            // Parse $table_prefix = 'wp_';
            if (
                is_array( $tok )
                && T_VARIABLE === $tok[0]
                && '$table_prefix' === $tok[1]
                && isset( $clean[ $i + 1 ], $clean[ $i + 2 ] )
                && '=' === $clean[ $i + 1 ]
                && is_array( $clean[ $i + 2 ] )
                && T_CONSTANT_ENCAPSED_STRING === $clean[ $i + 2 ][0]
            ) {
                $tablePrefix = self::unquoteLiteral( $clean[ $i + 2 ][1] );
            }
        }

        if ( '' === ( $constants['DB_NAME'] ?? '' ) || '' === ( $constants['DB_USER'] ?? '' ) ) {
            return [
                'ok'                => false,
                'error'             => 'Không trích xuất được DB_NAME / DB_USER từ wp-config.php (có thể đang dùng biến môi trường động).',
                'constants'         => $constants,
                'dynamic_constants' => array_values( array_unique( $dynamicConstants ) ),
            ];
        }

        return [
            'ok'                => true,
            'db'                => [
                'name'    => $constants['DB_NAME'] ?? '',
                'user'    => $constants['DB_USER'] ?? '',
                'pass'    => $constants['DB_PASSWORD'] ?? '',
                'host'    => $constants['DB_HOST'] ?? 'localhost',
                'charset' => $constants['DB_CHARSET'] ?? 'utf8mb4',
                'prefix'  => '' !== $tablePrefix ? $tablePrefix : 'wp_',
            ],
            'constants'         => $constants,
            'dynamic_constants' => array_values( array_unique( $dynamicConstants ) ),
        ];
    }

    /**
     * Suggest valid WordPress installations near current ABSPATH (parent, child, or sibling directories).
     *
     * @return array<int, array{path: string, relation: string, site_url: string}>
     */
    public static function detectSiblingPaths(): array {
        if ( ! defined( 'ABSPATH' ) ) {
            return [];
        }

        $selfReal   = realpath( ABSPATH );
        $selfNorm   = false !== $selfReal ? rtrim( PathGuard::normalize( $selfReal ), '/' ) : rtrim( PathGuard::normalize( ABSPATH ), '/' );
        $candidates = [];

        $parent = dirname( $selfNorm );
        if ( '' !== $parent && $parent !== $selfNorm ) {
            $candidates[] = [ 'path' => $parent, 'relation' => 'Thư mục cha (thường là Production khi đang ở /staging)' ];
            $grand        = dirname( $parent );
            if ( is_dir( $parent ) && is_readable( $parent ) ) {
                $entries = @scandir( $parent );
                if ( is_array( $entries ) ) {
                    $checked = 0;
                    foreach ( $entries as $entry ) {
                        if ( '.' === $entry || '..' === $entry || str_starts_with( $entry, '.' ) ) {
                            continue;
                        }
                        if ( ++$checked > 25 ) {
                            break;
                        }
                        $sub = $parent . '/' . $entry;
                        if ( is_dir( $sub ) ) {
                            $candidates[] = [ 'path' => $sub, 'relation' => 'Thư mục cùng cấp' ];
                        }
                    }
                }
            }
            if ( '' !== $grand && $grand !== $parent ) {
                $candidates[] = [ 'path' => $grand . '/public_html', 'relation' => 'public_html cùng tài khoản hosting' ];
            }
        }

        $stagingChild = $selfNorm . '/staging';
        if ( is_dir( $stagingChild ) ) {
            $candidates[] = [ 'path' => $stagingChild, 'relation' => 'Thư mục con /staging' ];
        }

        $found = [];
        $seen  = [];
        foreach ( $candidates as $cand ) {
            $real = realpath( $cand['path'] );
            if ( false === $real ) {
                continue;
            }
            $norm = rtrim( PathGuard::normalize( $real ), '/' );
            if ( $norm === $selfNorm || isset( $seen[ $norm ] ) ) {
                continue;
            }
            $seen[ $norm ] = true;

            if ( is_file( $norm . '/wp-config.php' ) && is_file( $norm . '/wp-includes/version.php' ) ) {
                $found[] = [
                    'path'     => $norm,
                    'relation' => $cand['relation'],
                    'site_url' => self::probeSiteUrl( $norm ),
                ];
            }
        }

        return $found;
    }

    /**
     * Validate a Same-Server target directory, its write permissions, and its MySQL database connection.
     *
     * @return array<string, mixed>
     */
    public static function testSameServerTarget( string $targetPath ): array {
        $clean = rtrim( PathGuard::normalize( trim( $targetPath ) ), '/' );
        if ( '' === $clean || ! is_dir( $clean ) ) {
            return [ 'ok' => false, 'error' => 'Thư mục đích không tồn tại trên máy chủ này.' ];
        }

        $targetReal = realpath( $clean );
        $selfReal   = defined( 'ABSPATH' ) ? realpath( ABSPATH ) : false;
        if ( false === $targetReal ) {
            return [ 'ok' => false, 'error' => 'Không thể phân giải đường dẫn đích.' ];
        }
        if ( false !== $selfReal && PathGuard::normalize( $targetReal ) === PathGuard::normalize( $selfReal ) ) {
            return [ 'ok' => false, 'error' => 'Đường dẫn đích đang trỏ vào chính website hiện tại!' ];
        }

        $normTarget = rtrim( PathGuard::normalize( $targetReal ), '/' );
        $wpConfig   = $normTarget . '/wp-config.php';
        if ( ! is_file( $wpConfig ) || ! is_dir( $normTarget . '/wp-content' ) ) {
            return [ 'ok' => false, 'error' => 'Thư mục đích không phải một website WordPress hợp lệ (thiếu wp-config.php hoặc wp-content/).' ];
        }

        $writable = is_writable( $normTarget . '/wp-content' );
        $parsed   = self::parseWpConfig( $wpConfig );
        if ( empty( $parsed['ok'] ) || empty( $parsed['db'] ) ) {
            return [
                'ok'       => false,
                'writable' => $writable,
                'error'    => $parsed['error'] ?? 'Không đọc được cấu hình DB của trang đích.',
            ];
        }

        $db     = $parsed['db'];
        $target = self::openTargetDb( $db );
        if ( ! $target ) {
            return [
                'ok'       => false,
                'writable' => $writable,
                'error'    => 'Kết nối Database trang đích thất bại (kiểm tra DB_NAME / DB_USER / DB_HOST).',
            ];
        }

        $siteUrl = (string) $target->get_var( "SELECT option_value FROM {$target->options} WHERE option_name = 'siteurl' LIMIT 1" );
        $blog    = (string) $target->get_var( "SELECT option_value FROM {$target->options} WHERE option_name = 'blogname' LIMIT 1" );

        return [
            'ok'        => $writable,
            'path'      => $normTarget,
            'writable'  => $writable,
            'site_url'  => rtrim( $siteUrl, '/' ),
            'site_name' => $blog,
            'db_name'   => $db['name'],
            'prefix'    => $db['prefix'],
            'error'     => $writable ? '' : 'Thư mục wp-content của trang đích không có quyền ghi (writable = false).',
        ];
    }

    /**
     * Open a `wpdb` connection to a parsed Same-Server target database.
     *
     * @param array<string, string> $db
     */
    public static function openTargetDb( array $db ): ?wpdb {
        if ( ! class_exists( 'wpdb' ) ) {
            return null;
        }
        $conn = @new wpdb( $db['user'], $db['pass'], $db['name'], $db['host'] );
        if ( ! empty( $conn->error ) || ! $conn->check_connection( false ) ) {
            return null;
        }
        $conn->set_prefix( $db['prefix'] );
        return $conn;
    }

    private static function probeSiteUrl( string $wpRoot ): string {
        $parsed = self::parseWpConfig( $wpRoot . '/wp-config.php' );
        if ( empty( $parsed['ok'] ) || empty( $parsed['db'] ) ) {
            return '';
        }
        $db = self::openTargetDb( $parsed['db'] );
        if ( ! $db ) {
            return '';
        }
        return rtrim( (string) $db->get_var( "SELECT option_value FROM {$db->options} WHERE option_name = 'home' LIMIT 1" ), '/' );
    }

    private static function unquoteLiteral( string $tokenText ): string {
        if ( strlen( $tokenText ) < 2 ) {
            return $tokenText;
        }
        $quote = $tokenText[0];
        $inner = substr( $tokenText, 1, -1 );
        if ( "'" === $quote ) {
            return str_replace( [ "\\'", '\\\\' ], [ "'", '\\' ], $inner );
        }
        if ( '"' === $quote ) {
            return stripcslashes( $inner );
        }
        return $tokenText;
    }
}
