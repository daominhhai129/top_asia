<?php
declare(strict_types=1);

namespace DBHDL\Sync\Discovery;

use DBHDL\Sync\Security\PathGuard;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Dynamically discovers installed plugins, themes, mu-plugins, custom wp-content directories,
 * templates, CPTs, navigation menus, and safe options on ANY WordPress site (zero hardcoded slugs).
 */
final class ScopeScanner {

    private const SYSTEM_CONTENT_DIRS = [
        'plugins',
        'themes',
        'mu-plugins',
        'uploads',
        'upgrade',
        'upgrade-temp-backup',
        'cache',
        'languages',
        'ai-cache',
        'backups-dup-pro',
    ];

    private const TEMPLATE_POST_TYPES = [
        'elementor_library' => 'Elementor Templates',
        'wp_template'       => 'Block Templates (FSE)',
        'wp_template_part'  => 'Block Template Parts',
        'wp_block'          => 'Synced Patterns / Reusable Blocks',
        'wp_navigation'     => 'Navigation Blocks',
        'wp_global_styles'  => 'Global Styles',
        'jet-engine'        => 'JetEngine Listings',
        'jet-popup'         => 'JetPopup Templates',
        'acf-field-group'   => 'ACF Field Groups',
    ];

    private const PROTECTED_POST_TYPES = [
        'attachment',
        'revision',
        'nav_menu_item',
        'custom_css',
        'customize_changeset',
        'oembed_cache',
        'user_request',
        'shop_order',
        'shop_order_placehold',
        'shop_order_refund',
        'shop_subscription',
        'shop_coupon',
    ];

    /**
     * Build complete dynamic inventory of syncable code & database scopes on the current site.
     *
     * @return array<string, mixed>
     */
    public static function getInventory(): array {
        return [
            'plugins'     => self::discoverPlugins(),
            'themes'      => self::discoverThemes(),
            'mu_plugins'  => self::discoverMuPlugins(),
            'custom_dirs' => self::discoverCustomContentDirs(),
            'db'          => self::discoverDatabaseScopes(),
        ];
    }

    /**
     * Resolve user-selected scope identifiers into normalized relative WP directory prefixes.
     *
     * @param array<string, mixed> $selection
     * @return array<int, string>
     */
    public static function resolveScopePrefixes( array $selection ): array {
        $prefixes = [];

        $plugins = isset( $selection['plugins'] ) && is_array( $selection['plugins'] ) ? $selection['plugins'] : [];
        foreach ( $plugins as $slug ) {
            $clean = trim( PathGuard::normalize( (string) $slug ), '/' );
            if ( '' !== $clean && PathGuard::isSafeRelativePath( $clean ) && ! str_contains( $clean, '/' ) ) {
                $prefixes[] = 'wp-content/plugins/' . $clean;
            }
        }

        $themes = isset( $selection['themes'] ) && is_array( $selection['themes'] ) ? $selection['themes'] : [];
        foreach ( $themes as $slug ) {
            $clean = trim( PathGuard::normalize( (string) $slug ), '/' );
            if ( '' !== $clean && PathGuard::isSafeRelativePath( $clean ) && ! str_contains( $clean, '/' ) ) {
                $prefixes[] = 'wp-content/themes/' . $clean;
            }
        }

        if ( ! empty( $selection['mu_plugins'] ) ) {
            $prefixes[] = 'wp-content/mu-plugins';
        }

        $customDirs = isset( $selection['custom_dirs'] ) && is_array( $selection['custom_dirs'] ) ? $selection['custom_dirs'] : [];
        foreach ( $customDirs as $dir ) {
            $clean = trim( PathGuard::normalize( (string) $dir ), '/' );
            if ( '' !== $clean && PathGuard::isSafeRelativePath( $clean ) && ! str_contains( $clean, '/' ) ) {
                if ( ! in_array( strtolower( $clean ), self::SYSTEM_CONTENT_DIRS, true ) ) {
                    $prefixes[] = 'wp-content/' . $clean;
                }
            }
        }

        if ( ! empty( $selection['include_media'] ) ) {
            $prefixes[] = 'wp-content/uploads';
        }

        return array_values( array_unique( $prefixes ) );
    }

    /**
     * List installed plugins (both directory-based and single-file plugins).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function discoverPlugins(): array {
        $pluginDir = defined( 'WP_PLUGIN_DIR' ) ? rtrim( PathGuard::normalize( WP_PLUGIN_DIR ), '/' ) : '';
        if ( '' === $pluginDir || ! is_dir( $pluginDir ) ) {
            return [];
        }

        if ( ! function_exists( 'get_plugins' ) && defined( 'ABSPATH' ) && is_file( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $active = function_exists( 'get_option' ) ? (array) get_option( 'active_plugins', [] ) : [];
        $items  = [];

        if ( function_exists( 'get_plugins' ) ) {
            foreach ( get_plugins() as $pluginFile => $meta ) {
                $slug = str_contains( $pluginFile, '/' ) ? explode( '/', $pluginFile )[0] : $pluginFile;
                $items[ $slug ] = [
                    'slug'        => $slug,
                    'plugin_file' => $pluginFile,
                    'name'        => (string) ( $meta['Name'] ?? $slug ),
                    'version'     => (string) ( $meta['Version'] ?? '' ),
                    'active'      => in_array( $pluginFile, $active, true ),
                    'is_self'     => 'dong-bo-hoa-du-lieu' === $slug,
                ];
            }
        } else {
            foreach ( (array) @scandir( $pluginDir ) as $entry ) {
                if ( '.' === $entry || '..' === $entry || str_starts_with( $entry, '.' ) ) {
                    continue;
                }
                if ( is_dir( $pluginDir . '/' . $entry ) ) {
                    $items[ $entry ] = [
                        'slug'        => $entry,
                        'plugin_file' => $entry . '/' . $entry . '.php',
                        'name'        => $entry,
                        'version'     => '',
                        'active'      => false,
                        'is_self'     => 'dong-bo-hoa-du-lieu' === $entry,
                    ];
                }
            }
        }

        uasort(
            $items,
            static function ( array $a, array $b ): int {
                if ( $a['active'] !== $b['active'] ) {
                    return $a['active'] ? -1 : 1;
                }
                return strcasecmp( $a['name'], $b['name'] );
            }
        );

        return array_values( $items );
    }

    /**
     * List installed themes, flagging active child and parent themes.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function discoverThemes(): array {
        $items = [];
        $curStylesheet = function_exists( 'get_stylesheet' ) ? get_stylesheet() : '';
        $curTemplate   = function_exists( 'get_template' ) ? get_template() : '';

        if ( function_exists( 'wp_get_themes' ) ) {
            foreach ( wp_get_themes() as $slug => $theme ) {
                $items[] = [
                    'slug'      => (string) $slug,
                    'name'      => (string) $theme->get( 'Name' ),
                    'version'   => (string) $theme->get( 'Version' ),
                    'active'    => (string) $slug === $curStylesheet,
                    'is_parent' => (string) $slug === $curTemplate && $curTemplate !== $curStylesheet,
                ];
            }
        } else {
            $themeDir = defined( 'WP_CONTENT_DIR' ) ? rtrim( PathGuard::normalize( WP_CONTENT_DIR ), '/' ) . '/themes' : '';
            if ( is_dir( $themeDir ) ) {
                foreach ( (array) @scandir( $themeDir ) as $entry ) {
                    if ( '.' === $entry || '..' === $entry || ! is_dir( $themeDir . '/' . $entry ) ) {
                        continue;
                    }
                    $items[] = [
                        'slug'      => $entry,
                        'name'      => $entry,
                        'version'   => '',
                        'active'    => $entry === $curStylesheet,
                        'is_parent' => false,
                    ];
                }
            }
        }

        usort(
            $items,
            static function ( array $a, array $b ): int {
                if ( $a['active'] !== $b['active'] ) {
                    return $a['active'] ? -1 : 1;
                }
                return strcasecmp( $a['name'], $b['name'] );
            }
        );

        return $items;
    }

    /**
     * Inspect `wp-content/mu-plugins` presence and file count.
     *
     * @return array{exists: bool, file_count: int}
     */
    public static function discoverMuPlugins(): array {
        $dir = defined( 'WPMU_PLUGIN_DIR' )
            ? rtrim( PathGuard::normalize( WPMU_PLUGIN_DIR ), '/' )
            : ( defined( 'WP_CONTENT_DIR' ) ? rtrim( PathGuard::normalize( WP_CONTENT_DIR ), '/' ) . '/mu-plugins' : '' );

        if ( '' === $dir || ! is_dir( $dir ) ) {
            return [ 'exists' => false, 'file_count' => 0 ];
        }

        $count = 0;
        foreach ( (array) @scandir( $dir ) as $entry ) {
            if ( '.' !== $entry && '..' !== $entry && ! str_starts_with( $entry, '.' ) ) {
                $count++;
            }
        }
        return [ 'exists' => $count > 0, 'file_count' => $count ];
    }

    /**
     * Find custom folders directly under `wp-content/` (excluding core, media, and snapshot folders).
     *
     * @return array<int, array{slug: string, path: string}>
     */
    public static function discoverCustomContentDirs(): array {
        $wpContent = defined( 'WP_CONTENT_DIR' ) ? rtrim( PathGuard::normalize( WP_CONTENT_DIR ), '/' ) : '';
        if ( '' === $wpContent || ! is_dir( $wpContent ) ) {
            return [];
        }

        $out = [];
        foreach ( (array) @scandir( $wpContent ) as $entry ) {
            if ( '.' === $entry || '..' === $entry || str_starts_with( $entry, '.' ) ) {
                continue;
            }
            $lower = strtolower( $entry );
            if (
                in_array( $lower, self::SYSTEM_CONTENT_DIRS, true )
                || str_starts_with( $lower, 'dbhdl-snapshots' )
                || str_starts_with( $lower, 'proid-backups' )
            ) {
                continue;
            }
            $full = $wpContent . '/' . $entry;
            if ( is_dir( $full ) ) {
                $out[] = [
                    'slug' => $entry,
                    'path' => 'wp-content/' . $entry,
                ];
            }
        }
        return $out;
    }

    /**
     * Summarize available templates, content post types, menus, and theme_mods on this site.
     *
     * @return array<string, mixed>
     */
    public static function discoverDatabaseScopes(): array {
        global $wpdb;
        if ( ! isset( $wpdb ) ) {
            return [
                'template_types' => [],
                'content_types'  => [],
                'menus_count'    => 0,
                'theme_mods_key' => '',
            ];
        }

        $countsRaw = $wpdb->get_results(
            "SELECT post_type, COUNT(*) AS total FROM {$wpdb->posts} WHERE post_status IN ('publish','draft','private') GROUP BY post_type",
            ARRAY_A
        );
        $countsByType = [];
        foreach ( (array) $countsRaw as $row ) {
            $countsByType[ (string) $row['post_type'] ] = (int) $row['total'];
        }

        $templateTypes = [];
        foreach ( self::TEMPLATE_POST_TYPES as $pt => $label ) {
            $cnt = $countsByType[ $pt ] ?? 0;
            if ( $cnt > 0 || post_type_exists( $pt ) ) {
                $templateTypes[] = [
                    'post_type' => $pt,
                    'label'     => $label,
                    'count'     => $cnt,
                ];
            }
        }

        $contentTypes = [];
        $registered   = function_exists( 'get_post_types' ) ? get_post_types( [ 'public' => true ], 'objects' ) : [];
        foreach ( $registered as $pt => $obj ) {
            if ( isset( self::TEMPLATE_POST_TYPES[ $pt ] ) || in_array( $pt, self::PROTECTED_POST_TYPES, true ) ) {
                continue;
            }
            $cnt = $countsByType[ $pt ] ?? 0;
            $contentTypes[] = [
                'post_type' => (string) $pt,
                'label'     => (string) ( $obj->labels->name ?? $pt ),
                'count'     => $cnt,
            ];
        }

        $menusCount = function_exists( 'wp_get_nav_menus' ) ? count( wp_get_nav_menus() ) : 0;
        $stylesheet = function_exists( 'get_stylesheet' ) ? get_stylesheet() : '';

        return [
            'template_types' => $templateTypes,
            'content_types'  => $contentTypes,
            'menus_count'    => $menusCount,
            'theme_mods_key' => '' !== $stylesheet ? 'theme_mods_' . $stylesheet : '',
        ];
    }
}
