<?php
declare(strict_types=1);

namespace DBHDL\Sync\Database;

use wpdb;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Portable, ID-collision-safe selective Database exporter/importer for templates,
 * posts/CPTs, menus, and safe options with automatic serialized-safe URL replacement.
 */
final class SelectiveDbSync {

    public const SYNC_UID_META = '_dbhdl_sync_uid';

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

    private const SKIP_META_KEYS = [
        '_edit_lock',
        '_edit_last',
        '_wp_old_slug',
        '_encloseme',
        '_pingme',
        '_elementor_css',
        '_elementor_element_cache',
    ];

    /**
     * Check whether an option name is core/security-protected and must never be overwritten.
     */
    public static function isProtectedOption( string $optionName ): bool {
        $norm = strtolower( trim( $optionName ) );
        if ( '' === $norm || strlen( $norm ) > 191 || ! preg_match( '/^[a-z0-9_.\-]+$/', $norm ) ) {
            return true;
        }
        $blocked = [
            'siteurl',
            'home',
            'blogname',
            'blogdescription',
            'admin_email',
            'new_admin_email',
            'users_can_register',
            'default_role',
            'active_plugins',
            'template',
            'stylesheet',
            'rewrite_rules',
            'cron',
            'db_version',
            'auth_key',
            'secure_auth_key',
            'logged_in_key',
            'nonce_key',
            'dbhdl_settings',
            'dbhdl_vault_registry',
            'dbhdl_vault_secrets',
            'dbhdl_audit_logs',
            'dbhdl_pairing_server',
            'dbhdl_snapshot_dir_token',
            'dbhdl_local_master_key',
            'proid_gh_token',
            'proid_gh_repo',
            'proid_gh_branch',
            'proid_gh_webhook_secret',
            'proid_api_vault_registry',
        ];
        if ( in_array( $norm, $blocked, true ) ) {
            return true;
        }
        if ( str_ends_with( $norm, '_user_roles' ) || str_starts_with( $norm, '_transient' ) || str_starts_with( $norm, '_site_transient' ) ) {
            return true;
        }
        return false;
    }

    /**
     * Read an option value directly from any `wpdb` handle.
     *
     * @param mixed $default
     * @return mixed
     */
    public static function dbGetOption( wpdb $db, string $optionName, $default = null ) {
        $raw = $db->get_var(
            $db->prepare( "SELECT option_value FROM {$db->options} WHERE option_name = %s LIMIT 1", $optionName )
        );
        if ( null === $raw ) {
            return $default;
        }
        return $raw;
    }

    /**
     * Upsert a raw (already serialized or scalar) option value into any `wpdb` handle.
     */
    public static function dbUpsertOptionRaw( wpdb $db, string $optionName, string $rawValue, string $autoload = 'no' ): bool {
        $safeAutoload = ( 'yes' === $autoload ) ? 'yes' : 'no';
        $existsId     = $db->get_var(
            $db->prepare( "SELECT option_id FROM {$db->options} WHERE option_name = %s LIMIT 1", $optionName )
        );
        if ( $existsId ) {
            return false !== $db->update(
                $db->options,
                [
                    'option_value' => $rawValue,
                    'autoload'     => $safeAutoload,
                ],
                [ 'option_id' => (int) $existsId ]
            );
        }
        return false !== $db->insert(
            $db->options,
            [
                'option_name'  => $optionName,
                'option_value' => $rawValue,
                'autoload'     => $safeAutoload,
            ]
        );
    }

    /**
     * Summarize how many DB items match a sync selection (used in Dry-Run Diff).
     *
     * @param array<string, mixed> $selection
     * @return array{posts_count: int, menus_count: int, options_count: int, post_types: array<string, int>}
     */
    public static function previewSelection( array $selection ): array {
        global $wpdb;
        if ( ! isset( $wpdb ) ) {
            return [ 'posts_count' => 0, 'menus_count' => 0, 'options_count' => 0, 'post_types' => [] ];
        }

        $postTypes = self::sanitizePostTypes( $selection );
        $byType    = [];
        $totalPosts = 0;

        if ( ! empty( $postTypes ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $postTypes ), '%s' ) );
            $rows         = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT post_type, COUNT(*) AS cnt FROM {$wpdb->posts} WHERE post_type IN ($placeholders) AND post_status IN ('publish','draft','private') GROUP BY post_type",
                    ...$postTypes
                ),
                ARRAY_A
            );
            foreach ( (array) $rows as $row ) {
                $cnt                         = (int) $row['cnt'];
                $byType[ $row['post_type'] ] = $cnt;
                $totalPosts                 += $cnt;
            }
        }

        $menusCount = 0;
        if ( ! empty( $selection['include_menus'] ) && function_exists( 'wp_get_nav_menus' ) ) {
            $menusCount = count( wp_get_nav_menus() );
        }

        $optionsCount = count( self::resolveOptionNames( $selection ) );

        return [
            'posts_count'   => $totalPosts,
            'menus_count'   => $menusCount,
            'options_count' => $optionsCount,
            'post_types'    => $byType,
        ];
    }

    /**
     * Export selected DB records into a portable JSON-serializable package.
     *
     * @param array<string, mixed> $selection
     * @return array<string, mixed>
     */
    public static function exportPackage( array $selection ): array {
        global $wpdb;
        $package = [
            'version'        => 1,
            'source_url'     => function_exists( 'home_url' ) ? rtrim( home_url(), '/' ) : '',
            'source_abspath' => defined( 'ABSPATH' ) ? rtrim( str_replace( '\\', '/', ABSPATH ), '/' ) : '',
            'posts'          => [],
            'menus'          => [],
            'options'        => [],
        ];

        if ( ! isset( $wpdb ) ) {
            return $package;
        }

        $postTypes = self::sanitizePostTypes( $selection );
        if ( ! empty( $postTypes ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $postTypes ), '%s' ) );
            $postRows     = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->posts} WHERE post_type IN ($placeholders) AND post_status IN ('publish','draft','private') ORDER BY post_parent ASC, ID ASC",
                    ...$postTypes
                ),
                ARRAY_A
            );

            $idToUid = [];
            foreach ( (array) $postRows as $row ) {
                $id                    = (int) $row['ID'];
                $uid                   = self::ensureSyncUid( $wpdb, $id, (string) $row['post_type'], (string) $row['post_name'] );
                $idToUid[ $id ]        = $uid;
            }

            foreach ( (array) $postRows as $row ) {
                $id       = (int) $row['ID'];
                $metaRows = $wpdb->get_results(
                    $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d", $id ),
                    ARRAY_A
                );
                $meta = [];
                foreach ( (array) $metaRows as $m ) {
                    $mk = (string) $m['meta_key'];
                    if ( in_array( $mk, self::SKIP_META_KEYS, true ) ) {
                        continue;
                    }
                    $meta[] = [
                        'key'   => $mk,
                        'value' => (string) $m['meta_value'],
                    ];
                }

                $terms = [];
                $taxRows = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT t.slug, t.name, tt.taxonomy FROM {$wpdb->term_relationships} tr
                         INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                         INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
                         WHERE tr.object_id = %d",
                        $id
                    ),
                    ARRAY_A
                );
                foreach ( (array) $taxRows as $tr ) {
                    $terms[] = [
                        'taxonomy' => (string) $tr['taxonomy'],
                        'slug'     => (string) $tr['slug'],
                        'name'     => (string) $tr['name'],
                    ];
                }

                $parentUid = (int) $row['post_parent'] > 0 ? ( $idToUid[ (int) $row['post_parent'] ] ?? '' ) : '';

                $package['posts'][] = [
                    'sync_uid'       => $idToUid[ $id ],
                    'parent_uid'     => $parentUid,
                    'post_title'     => (string) $row['post_title'],
                    'post_name'      => (string) $row['post_name'],
                    'post_content'   => (string) $row['post_content'],
                    'post_excerpt'   => (string) $row['post_excerpt'],
                    'post_status'    => (string) $row['post_status'],
                    'post_type'      => (string) $row['post_type'],
                    'post_mime_type' => (string) $row['post_mime_type'],
                    'menu_order'     => (int) $row['menu_order'],
                    'comment_status' => (string) $row['comment_status'],
                    'ping_status'    => (string) $row['ping_status'],
                    'guid'           => (string) $row['guid'],
                    'post_date'      => (string) $row['post_date'],
                    'post_date_gmt'  => (string) $row['post_date_gmt'],
                    'meta'           => $meta,
                    'terms'          => $terms,
                ];
            }
        }

        // Navigation Menus
        if ( ! empty( $selection['include_menus'] ) && function_exists( 'wp_get_nav_menus' ) ) {
            foreach ( wp_get_nav_menus() as $menu ) {
                $menuItemsRaw = wp_get_nav_menu_items( $menu->term_id );
                $itemsOut     = [];
                foreach ( (array) $menuItemsRaw as $item ) {
                    $itemsOut[] = [
                        'menu_order'  => (int) $item->menu_order,
                        'title'       => (string) $item->title,
                        'url'         => (string) $item->url,
                        'type'        => (string) $item->type,
                        'object'      => (string) $item->object,
                        'object_slug' => self::resolveObjectSlug( (string) $item->type, (string) $item->object, (int) $item->object_id ),
                        'target'      => (string) $item->target,
                        'classes'     => implode( ' ', array_filter( (array) $item->classes ) ),
                        'xfn'         => (string) $item->xfn,
                        'description' => (string) $item->description,
                    ];
                }
                $package['menus'][] = [
                    'slug'  => (string) $menu->slug,
                    'name'  => (string) $menu->name,
                    'items' => $itemsOut,
                ];
            }
        }

        // Safe Options
        foreach ( self::resolveOptionNames( $selection ) as $optName ) {
            $row = $wpdb->get_row(
                $wpdb->prepare( "SELECT option_value, autoload FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", $optName ),
                ARRAY_A
            );
            if ( $row ) {
                $package['options'][] = [
                    'name'     => $optName,
                    'value'    => (string) $row['option_value'],
                    'autoload' => (string) ( $row['autoload'] ?? 'no' ),
                ];
            }
        }

        return $package;
    }

    /**
     * Capture exact pre-change state on Target DB so Rollback can reverse DB edits cleanly.
     *
     * @param array<string, mixed> $package
     * @return array<string, mixed>
     */
    public static function capturePreChangeState( wpdb $targetDb, array $package ): array {
        $backup = [
            'existing_posts' => [],
            'new_post_uids'  => [],
            'options'        => [],
        ];

        foreach ( (array) ( $package['posts'] ?? [] ) as $p ) {
            $syncUid  = (string) ( $p['sync_uid'] ?? '' );
            $postType = (string) ( $p['post_type'] ?? '' );
            $postName = (string) ( $p['post_name'] ?? '' );
            if ( '' === $syncUid || '' === $postType ) {
                continue;
            }

            $existingId = self::findTargetPostId( $targetDb, $syncUid, $postType, $postName );
            if ( $existingId > 0 ) {
                $row      = $targetDb->get_row(
                    $targetDb->prepare( "SELECT * FROM {$targetDb->posts} WHERE ID = %d LIMIT 1", $existingId ),
                    ARRAY_A
                );
                $metaRows = $targetDb->get_results(
                    $targetDb->prepare( "SELECT meta_key, meta_value FROM {$targetDb->postmeta} WHERE post_id = %d", $existingId ),
                    ARRAY_A
                );
                if ( is_array( $row ) ) {
                    $backup['existing_posts'][] = [
                        'ID'   => $existingId,
                        'row'  => $row,
                        'meta' => is_array( $metaRows ) ? $metaRows : [],
                    ];
                }
            } else {
                $backup['new_post_uids'][] = $syncUid;
            }
        }

        foreach ( (array) ( $package['options'] ?? [] ) as $opt ) {
            $name = (string) ( $opt['name'] ?? '' );
            if ( '' === $name || self::isProtectedOption( $name ) ) {
                continue;
            }
            $row = $targetDb->get_row(
                $targetDb->prepare( "SELECT option_value, autoload FROM {$targetDb->options} WHERE option_name = %s LIMIT 1", $name ),
                ARRAY_A
            );
            $backup['options'][] = [
                'name'     => $name,
                'existed'  => (bool) $row,
                'value'    => $row ? (string) $row['option_value'] : '',
                'autoload' => $row ? (string) ( $row['autoload'] ?? 'no' ) : 'no',
            ];
        }

        return $backup;
    }

    /**
     * Import a portable DB package into `$targetDb` with serialized-safe URL replacement.
     *
     * @param array<string, mixed> $package
     * @return array<string, mixed>
     */
    public static function importPackage(
        wpdb $targetDb,
        array $package,
        string $targetUrl = '',
        string $targetAbspath = '',
        bool $runSearchReplace = true
    ): array {
        $stats = [
            'posts_updated'   => 0,
            'posts_created'   => 0,
            'menus_synced'    => 0,
            'options_synced'  => 0,
            'errors'          => [],
        ];

        $replaceMap = [];
        if ( $runSearchReplace ) {
            $srcUrl  = (string) ( $package['source_url'] ?? '' );
            $srcPath = (string) ( $package['source_abspath'] ?? '' );
            if ( '' === $targetUrl ) {
                $targetUrl = rtrim( (string) self::dbGetOption( $targetDb, 'home', '' ), '/' );
            }
            $replaceMap = SafeSearchReplace::buildMap( $srcUrl, $targetUrl, $srcPath, $targetAbspath );
        }

        $useTx = self::supportsTransactions( $targetDb );
        if ( $useTx ) {
            $targetDb->query( 'START TRANSACTION' );
        }

        try {
            $uidToTargetId = [];

            foreach ( (array) ( $package['posts'] ?? [] ) as $p ) {
                $postType = sanitize_key( (string) ( $p['post_type'] ?? '' ) );
                if ( '' === $postType || in_array( $postType, self::PROTECTED_POST_TYPES, true ) ) {
                    continue;
                }

                $syncUid  = sanitize_text_field( (string) ( $p['sync_uid'] ?? '' ) );
                $postName = sanitize_title( (string) ( $p['post_name'] ?? '' ) );
                $existing = self::findTargetPostId( $targetDb, $syncUid, $postType, $postName );

                $content = SafeSearchReplace::replaceString( (string) ( $p['post_content'] ?? '' ), $replaceMap );
                $excerpt = SafeSearchReplace::replaceString( (string) ( $p['post_excerpt'] ?? '' ), $replaceMap );
                $guid    = SafeSearchReplace::replaceString( (string) ( $p['guid'] ?? '' ), $replaceMap );
                $now     = gmdate( 'Y-m-d H:i:s' );

                if ( $existing > 0 ) {
                    $targetDb->update(
                        $targetDb->posts,
                        [
                            'post_title'        => (string) ( $p['post_title'] ?? '' ),
                            'post_content'      => $content,
                            'post_excerpt'      => $excerpt,
                            'post_status'       => (string) ( $p['post_status'] ?? 'publish' ),
                            'post_name'         => $postName,
                            'menu_order'        => (int) ( $p['menu_order'] ?? 0 ),
                            'post_modified'     => $now,
                            'post_modified_gmt' => $now,
                        ],
                        [ 'ID' => $existing ]
                    );
                    $targetId = $existing;
                    $stats['posts_updated']++;
                } else {
                    $inserted = $targetDb->insert(
                        $targetDb->posts,
                        [
                            'post_author'           => 1,
                            'post_date'             => (string) ( $p['post_date'] ?? $now ),
                            'post_date_gmt'         => (string) ( $p['post_date_gmt'] ?? $now ),
                            'post_content'          => $content,
                            'post_title'            => (string) ( $p['post_title'] ?? '' ),
                            'post_excerpt'          => $excerpt,
                            'post_status'           => (string) ( $p['post_status'] ?? 'publish' ),
                            'comment_status'        => (string) ( $p['comment_status'] ?? 'closed' ),
                            'ping_status'           => (string) ( $p['ping_status'] ?? 'closed' ),
                            'post_name'             => $postName,
                            'to_ping'               => '',
                            'pinged'                => '',
                            'post_modified'         => $now,
                            'post_modified_gmt'     => $now,
                            'post_content_filtered' => '',
                            'post_parent'           => 0,
                            'guid'                  => $guid,
                            'menu_order'            => (int) ( $p['menu_order'] ?? 0 ),
                            'post_type'             => $postType,
                            'post_mime_type'        => (string) ( $p['post_mime_type'] ?? '' ),
                            'comment_count'         => 0,
                        ]
                    );
                    if ( false === $inserted ) {
                        $stats['errors'][] = 'Không thể chèn bản ghi ' . $postType . '/' . $postName;
                        continue;
                    }
                    $targetId = (int) $targetDb->insert_id;
                    $stats['posts_created']++;
                }

                if ( '' !== $syncUid ) {
                    $uidToTargetId[ $syncUid ] = $targetId;
                }

                // Replace postmeta cleanly (preserving non-skipped meta keys).
                self::upsertPostMeta( $targetDb, $targetId, self::SYNC_UID_META, $syncUid );
                foreach ( (array) ( $p['meta'] ?? [] ) as $m ) {
                    $mk = (string) ( $m['key'] ?? '' );
                    if ( '' === $mk || in_array( $mk, self::SKIP_META_KEYS, true ) || self::SYNC_UID_META === $mk ) {
                        continue;
                    }
                    $mv = SafeSearchReplace::replaceString( (string) ( $m['value'] ?? '' ), $replaceMap );
                    self::upsertPostMeta( $targetDb, $targetId, $mk, $mv );
                }
                // Drop stale Elementor CSS cache on target post so Elementor regenerates fresh URLs.
                $targetDb->delete( $targetDb->postmeta, [ 'post_id' => $targetId, 'meta_key' => '_elementor_css' ] );

                // Sync taxonomy terms (e.g. elementor_library_type, category).
                self::syncPostTerms( $targetDb, $targetId, (array) ( $p['terms'] ?? [] ) );
            }

            // Second pass: remap hierarchical post_parent IDs safely.
            foreach ( (array) ( $package['posts'] ?? [] ) as $p ) {
                $syncUid   = (string) ( $p['sync_uid'] ?? '' );
                $parentUid = (string) ( $p['parent_uid'] ?? '' );
                if ( '' !== $syncUid && '' !== $parentUid && isset( $uidToTargetId[ $syncUid ], $uidToTargetId[ $parentUid ] ) ) {
                    $targetDb->update(
                        $targetDb->posts,
                        [ 'post_parent' => $uidToTargetId[ $parentUid ] ],
                        [ 'ID' => $uidToTargetId[ $syncUid ] ]
                    );
                }
            }

            // Sync Navigation Menus if included (only when running against local/target wpdb cleanly).
            foreach ( (array) ( $package['menus'] ?? [] ) as $menuData ) {
                if ( self::importMenu( $targetDb, $menuData, $replaceMap ) ) {
                    $stats['menus_synced']++;
                }
            }

            // Sync Safe Options.
            foreach ( (array) ( $package['options'] ?? [] ) as $opt ) {
                $name = sanitize_key( (string) ( $opt['name'] ?? '' ) );
                if ( '' === $name || self::isProtectedOption( $name ) ) {
                    continue;
                }
                $val      = SafeSearchReplace::replaceString( (string) ( $opt['value'] ?? '' ), $replaceMap );
                $autoload = ( 'yes' === ( $opt['autoload'] ?? 'no' ) ) ? 'yes' : 'no';
                if ( self::dbUpsertOptionRaw( $targetDb, $name, $val, $autoload ) ) {
                    $stats['options_synced']++;
                }
            }

            if ( $useTx ) {
                $targetDb->query( 'COMMIT' );
            }
        } catch ( \Throwable $e ) {
            if ( $useTx ) {
                $targetDb->query( 'ROLLBACK' );
            }
            $stats['errors'][] = $e->getMessage();
        }

        return $stats;
    }

    /**
     * Reverse DB changes using a pre-change backup captured by `capturePreChangeState()`.
     *
     * @param array<string, mixed> $backup
     * @return array{posts_restored: int, posts_deleted: int, options_restored: int}
     */
    public static function restorePreChangeState( wpdb $targetDb, array $backup ): array {
        $restoredPosts   = 0;
        $deletedPosts    = 0;
        $restoredOptions = 0;

        foreach ( (array) ( $backup['existing_posts'] ?? [] ) as $item ) {
            $id  = (int) ( $item['ID'] ?? 0 );
            $row = $item['row'] ?? null;
            if ( $id <= 0 || ! is_array( $row ) ) {
                continue;
            }
            unset( $row['ID'] );
            $targetDb->update( $targetDb->posts, $row, [ 'ID' => $id ] );
            $targetDb->delete( $targetDb->postmeta, [ 'post_id' => $id ] );
            foreach ( (array) ( $item['meta'] ?? [] ) as $m ) {
                $targetDb->insert(
                    $targetDb->postmeta,
                    [
                        'post_id'    => $id,
                        'meta_key'   => (string) ( $m['meta_key'] ?? '' ),
                        'meta_value' => (string) ( $m['meta_value'] ?? '' ),
                    ]
                );
            }
            $restoredPosts++;
        }

        foreach ( (array) ( $backup['new_post_uids'] ?? [] ) as $uid ) {
            $postId = (int) $targetDb->get_var(
                $targetDb->prepare(
                    "SELECT post_id FROM {$targetDb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
                    self::SYNC_UID_META,
                    (string) $uid
                )
            );
            if ( $postId > 0 ) {
                $targetDb->delete( $targetDb->term_relationships, [ 'object_id' => $postId ] );
                $targetDb->delete( $targetDb->postmeta, [ 'post_id' => $postId ] );
                $targetDb->delete( $targetDb->posts, [ 'ID' => $postId ] );
                $deletedPosts++;
            }
        }

        foreach ( (array) ( $backup['options'] ?? [] ) as $opt ) {
            $name = (string) ( $opt['name'] ?? '' );
            if ( '' === $name || self::isProtectedOption( $name ) ) {
                continue;
            }
            if ( ! empty( $opt['existed'] ) ) {
                self::dbUpsertOptionRaw(
                    $targetDb,
                    $name,
                    (string) ( $opt['value'] ?? '' ),
                    (string) ( $opt['autoload'] ?? 'no' )
                );
            } else {
                $targetDb->delete( $targetDb->options, [ 'option_name' => $name ] );
            }
            $restoredOptions++;
        }

        return [
            'posts_restored'   => $restoredPosts,
            'posts_deleted'    => $deletedPosts,
            'options_restored' => $restoredOptions,
        ];
    }

    /**
     * @param array<string, mixed> $selection
     * @return array<int, string>
     */
    private static function sanitizePostTypes( array $selection ): array {
        $types = [];
        foreach ( (array) ( $selection['post_types'] ?? [] ) as $pt ) {
            $clean = sanitize_key( (string) $pt );
            if ( '' !== $clean && ! in_array( $clean, self::PROTECTED_POST_TYPES, true ) ) {
                $types[] = $clean;
            }
        }
        return array_values( array_unique( $types ) );
    }

    /**
     * @param array<string, mixed> $selection
     * @return array<int, string>
     */
    private static function resolveOptionNames( array $selection ): array {
        $names = [];
        if ( ! empty( $selection['include_theme_mods'] ) && function_exists( 'get_stylesheet' ) ) {
            $names[] = 'theme_mods_' . get_stylesheet();
        }
        foreach ( (array) ( $selection['options'] ?? [] ) as $opt ) {
            $clean = sanitize_key( (string) $opt );
            if ( '' !== $clean && ! self::isProtectedOption( $clean ) ) {
                $names[] = $clean;
            }
        }
        return array_values( array_unique( $names ) );
    }

    private static function ensureSyncUid( wpdb $db, int $postId, string $postType, string $postName ): string {
        $existing = (string) $db->get_var(
            $db->prepare(
                "SELECT meta_value FROM {$db->postmeta} WHERE post_id = %d AND meta_key = %s LIMIT 1",
                $postId,
                self::SYNC_UID_META
            )
        );
        if ( '' !== $existing ) {
            return $existing;
        }
        $uid = 'uid_' . substr( sha1( $postType . ':' . $postName . ':' . $postId ), 0, 20 );
        $db->insert(
            $db->postmeta,
            [
                'post_id'    => $postId,
                'meta_key'   => self::SYNC_UID_META,
                'meta_value' => $uid,
            ]
        );
        return $uid;
    }

    private static function findTargetPostId( wpdb $db, string $syncUid, string $postType, string $postName ): int {
        if ( '' !== $syncUid ) {
            $byUid = (int) $db->get_var(
                $db->prepare(
                    "SELECT pm.post_id FROM {$db->postmeta} pm
                     INNER JOIN {$db->posts} p ON p.ID = pm.post_id
                     WHERE pm.meta_key = %s AND pm.meta_value = %s AND p.post_type = %s
                     LIMIT 1",
                    self::SYNC_UID_META,
                    $syncUid,
                    $postType
                )
            );
            if ( $byUid > 0 ) {
                return $byUid;
            }
        }

        if ( '' !== $postName ) {
            $bySlug = (int) $db->get_var(
                $db->prepare(
                    "SELECT ID FROM {$db->posts} WHERE post_type = %s AND post_name = %s AND post_status != 'trash' LIMIT 1",
                    $postType,
                    $postName
                )
            );
            if ( $bySlug > 0 ) {
                return $bySlug;
            }
        }

        return 0;
    }

    private static function upsertPostMeta( wpdb $db, int $postId, string $key, string $value ): void {
        $existingId = $db->get_var(
            $db->prepare(
                "SELECT meta_id FROM {$db->postmeta} WHERE post_id = %d AND meta_key = %s LIMIT 1",
                $postId,
                $key
            )
        );
        if ( $existingId ) {
            $db->update( $db->postmeta, [ 'meta_value' => $value ], [ 'meta_id' => (int) $existingId ] );
        } else {
            $db->insert(
                $db->postmeta,
                [
                    'post_id'    => $postId,
                    'meta_key'   => $key,
                    'meta_value' => $value,
                ]
            );
        }
    }

    /**
     * @param array<int, array<string, string>> $terms
     */
    private static function syncPostTerms( wpdb $db, int $postId, array $terms ): void {
        if ( empty( $terms ) ) {
            return;
        }
        $db->delete( $db->term_relationships, [ 'object_id' => $postId ] );
        foreach ( $terms as $term ) {
            $tax  = sanitize_key( (string) ( $term['taxonomy'] ?? '' ) );
            $slug = sanitize_title( (string) ( $term['slug'] ?? '' ) );
            $name = sanitize_text_field( (string) ( $term['name'] ?? $slug ) );
            if ( '' === $tax || '' === $slug ) {
                continue;
            }
            $ttId = (int) $db->get_var(
                $db->prepare(
                    "SELECT tt.term_taxonomy_id FROM {$db->term_taxonomy} tt
                     INNER JOIN {$db->terms} t ON t.term_id = tt.term_id
                     WHERE tt.taxonomy = %s AND t.slug = %s LIMIT 1",
                    $tax,
                    $slug
                )
            );
            if ( 0 === $ttId ) {
                $termId = (int) $db->get_var(
                    $db->prepare( "SELECT term_id FROM {$db->terms} WHERE slug = %s LIMIT 1", $slug )
                );
                if ( 0 === $termId ) {
                    $db->insert( $db->terms, [ 'name' => $name, 'slug' => $slug, 'term_group' => 0 ] );
                    $termId = (int) $db->insert_id;
                }
                if ( $termId > 0 ) {
                    $db->insert( $db->term_taxonomy, [ 'term_id' => $termId, 'taxonomy' => $tax, 'description' => '', 'parent' => 0, 'count' => 1 ] );
                    $ttId = (int) $db->insert_id;
                }
            }
            if ( $ttId > 0 ) {
                $db->query(
                    $db->prepare(
                        "INSERT IGNORE INTO {$db->term_relationships} (object_id, term_taxonomy_id, term_order) VALUES (%d, %d, 0)",
                        $postId,
                        $ttId
                    )
                );
            }
        }
    }

    private static function resolveObjectSlug( string $type, string $object, int $objectId ): string {
        if ( $objectId <= 0 ) {
            return '';
        }
        if ( 'post_type' === $type ) {
            $p = get_post( $objectId );
            return $p ? (string) $p->post_name : '';
        }
        if ( 'taxonomy' === $type ) {
            $t = get_term( $objectId, $object );
            return ( $t && ! is_wp_error( $t ) ) ? (string) $t->slug : '';
        }
        return '';
    }

    /**
     * @param array<string, mixed> $menuData
     * @param array<string, string> $replaceMap
     */
    private static function importMenu( wpdb $db, array $menuData, array $replaceMap ): bool {
        $slug = sanitize_title( (string) ( $menuData['slug'] ?? '' ) );
        $name = sanitize_text_field( (string) ( $menuData['name'] ?? $slug ) );
        if ( '' === $slug ) {
            return false;
        }

        // Locate or create nav_menu term on target DB.
        $ttId = (int) $db->get_var(
            $db->prepare(
                "SELECT tt.term_taxonomy_id FROM {$db->term_taxonomy} tt
                 INNER JOIN {$db->terms} t ON t.term_id = tt.term_id
                 WHERE tt.taxonomy = 'nav_menu' AND t.slug = %s LIMIT 1",
                $slug
            )
        );

        if ( 0 === $ttId ) {
            $db->insert( $db->terms, [ 'name' => $name, 'slug' => $slug, 'term_group' => 0 ] );
            $termId = (int) $db->insert_id;
            if ( $termId <= 0 ) {
                return false;
            }
            $db->insert( $db->term_taxonomy, [ 'term_id' => $termId, 'taxonomy' => 'nav_menu', 'description' => '', 'parent' => 0, 'count' => 0 ] );
            $ttId = (int) $db->insert_id;
        }

        // Remove existing nav_menu_item posts attached to this menu term before rebuilding clean order.
        $oldIds = (array) $db->get_col(
            $db->prepare( "SELECT object_id FROM {$db->term_relationships} WHERE term_taxonomy_id = %d", $ttId )
        );
        foreach ( $oldIds as $oid ) {
            $oid = (int) $oid;
            $db->delete( $db->term_relationships, [ 'object_id' => $oid, 'term_taxonomy_id' => $ttId ] );
            $db->delete( $db->postmeta, [ 'post_id' => $oid ] );
            $db->delete( $db->posts, [ 'ID' => $oid, 'post_type' => 'nav_menu_item' ] );
        }

        $now = gmdate( 'Y-m-d H:i:s' );
        foreach ( (array) ( $menuData['items'] ?? [] ) as $item ) {
            $title   = (string) ( $item['title'] ?? '' );
            $type    = (string) ( $item['type'] ?? 'custom' );
            $object  = (string) ( $item['object'] ?? 'custom' );
            $objSlug = (string) ( $item['object_slug'] ?? '' );
            $url     = SafeSearchReplace::replaceString( (string) ( $item['url'] ?? '' ), $replaceMap );

            $resolvedObjId = 0;
            if ( 'post_type' === $type && '' !== $objSlug ) {
                $resolvedObjId = (int) $db->get_var(
                    $db->prepare(
                        "SELECT ID FROM {$db->posts} WHERE post_type = %s AND post_name = %s AND post_status != 'trash' LIMIT 1",
                        $object,
                        $objSlug
                    )
                );
            } elseif ( 'taxonomy' === $type && '' !== $objSlug ) {
                $resolvedObjId = (int) $db->get_var(
                    $db->prepare(
                        "SELECT t.term_id FROM {$db->terms} t
                         INNER JOIN {$db->term_taxonomy} tt ON t.term_id = tt.term_id
                         WHERE tt.taxonomy = %s AND t.slug = %s LIMIT 1",
                        $object,
                        $objSlug
                    )
                );
            }

            $db->insert(
                $db->posts,
                [
                    'post_author'           => 1,
                    'post_date'             => $now,
                    'post_date_gmt'         => $now,
                    'post_content'          => (string) ( $item['description'] ?? '' ),
                    'post_title'            => $title,
                    'post_excerpt'          => '',
                    'post_status'           => 'publish',
                    'comment_status'        => 'closed',
                    'ping_status'           => 'closed',
                    'post_name'             => '',
                    'to_ping'               => '',
                    'pinged'                => '',
                    'post_modified'         => $now,
                    'post_modified_gmt'     => $now,
                    'post_content_filtered' => '',
                    'post_parent'           => 0,
                    'guid'                  => '',
                    'menu_order'            => (int) ( $item['menu_order'] ?? 0 ),
                    'post_type'             => 'nav_menu_item',
                    'post_mime_type'        => '',
                    'comment_count'         => 0,
                ]
            );
            $newItemId = (int) $db->insert_id;
            if ( $newItemId <= 0 ) {
                continue;
            }
            $db->insert(
                $db->term_relationships,
                [
                    'object_id'        => $newItemId,
                    'term_taxonomy_id' => $ttId,
                    'term_order'       => 0,
                ]
            );
            self::upsertPostMeta( $db, $newItemId, '_menu_item_type', $type );
            self::upsertPostMeta( $db, $newItemId, '_menu_item_object', $object );
            self::upsertPostMeta( $db, $newItemId, '_menu_item_object_id', (string) $resolvedObjId );
            self::upsertPostMeta( $db, $newItemId, '_menu_item_url', $url );
            self::upsertPostMeta( $db, $newItemId, '_menu_item_target', (string) ( $item['target'] ?? '' ) );
            self::upsertPostMeta( $db, $newItemId, '_menu_item_classes', serialize( array_filter( explode( ' ', (string) ( $item['classes'] ?? '' ) ) ) ) );
            self::upsertPostMeta( $db, $newItemId, '_menu_item_xfn', (string) ( $item['xfn'] ?? '' ) );
        }

        return true;
    }

    private static function supportsTransactions( wpdb $db ): bool {
        $row = $db->get_row( $db->prepare( 'SHOW TABLE STATUS WHERE Name = %s', $db->posts ), ARRAY_A );
        return is_array( $row ) && 'InnoDB' === ( $row['Engine'] ?? '' );
    }
}
