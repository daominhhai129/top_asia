<?php
declare(strict_types=1);

namespace DBHDL\Sync\Security;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Filesystem path sanitizer, Zip-Slip guard, exclusion rules, and web-access directory hardener.
 */
final class PathGuard {

    /**
     * Normalize directory separators and trim surrounding slashes.
     */
    public static function normalize( string $path ): string {
        return str_replace( '\\', '/', $path );
    }

    /**
     * Extract a clean relative path under `$baseDir`.
     */
    public static function relativePath( string $baseDir, string $fullPath ): string {
        $normBase = rtrim( self::normalize( $baseDir ), '/' );
        $normFull = self::normalize( $fullPath );
        if ( '' === $normBase || ! str_starts_with( $normFull . '/', $normBase . '/' ) ) {
            return '';
        }
        return ltrim( substr( $normFull, strlen( $normBase ) ), '/' );
    }

    /**
     * Validate that a relative archive/sync path cannot escape the target directory.
     */
    public static function isSafeRelativePath( string $relPath ): bool {
        if ( '' === $relPath || str_contains( $relPath, "\0" ) || str_contains( $relPath, '\\' ) ) {
            return false;
        }
        if ( str_starts_with( $relPath, '/' ) || preg_match( '/^[a-zA-Z]:/', $relPath ) ) {
            return false;
        }
        foreach ( explode( '/', $relPath ) as $segment ) {
            if ( '' === $segment || '.' === $segment || '..' === $segment ) {
                return false;
            }
        }
        return true;
    }

    /**
     * Resolve `$relPath` strictly inside `$baseDir`, verifying no ancestor symlink escapes `$baseDir`.
     */
    public static function resolveUnderBase( string $baseDir, string $relPath ): ?string {
        $cleanRel = trim( self::normalize( $relPath ), '/' );
        if ( ! self::isSafeRelativePath( $cleanRel ) ) {
            return null;
        }

        $baseReal = realpath( $baseDir );
        if ( false === $baseReal ) {
            return null;
        }
        $normBase = rtrim( self::normalize( $baseReal ), '/' );
        $candidate = $normBase . '/' . $cleanRel;

        // Walk upward to the deepest existing ancestor so realpath() resolves any symlinks cleanly.
        $probe = $candidate;
        while ( ! file_exists( $probe ) ) {
            $parent = dirname( $probe );
            if ( $parent === $probe ) {
                return null;
            }
            $probe = $parent;
        }

        $probeReal = realpath( $probe );
        if ( false === $probeReal ) {
            return null;
        }
        $normProbe = rtrim( self::normalize( $probeReal ), '/' );
        if ( $normProbe !== $normBase && ! str_starts_with( $normProbe . '/', $normBase . '/' ) ) {
            return null;
        }

        return $candidate;
    }

    /**
     * Determine whether a relative WP path must be excluded from sync/transfer/extraction.
     *
     * @param array<string, mixed> $options
     */
    public static function isExcluded( string $relPath, array $options = [] ): bool {
        $rel = ltrim( self::normalize( $relPath ), '/' );
        if ( '' === $rel || ! self::isSafeRelativePath( $rel ) ) {
            return true;
        }

        $lower    = strtolower( $rel );
        $basename = strtolower( basename( $lower ) );

        // Critical core/server files never overwritten by sync.
        $blockedAlways = [
            'wp-config.php',
            'wp-config-sample.php',
            '.maintenance',
            '.ds_store',
            'thumbs.db',
            'error_log',
            'debug.log',
            'oid-github-sync.php',
            'roid-github-sync.php',
        ];
        if ( in_array( $basename, $blockedAlways, true ) ) {
            return true;
        }

        // Block root-level server config files while still allowing plugin-bundled .htaccess if needed.
        if ( $lower === $basename && in_array( $basename, [ '.htaccess', '.user.ini', 'php.ini' ], true ) ) {
            return true;
        }

        $blockedSegments = [ '/.git/', '/.github/', '/.svn/', '/node_modules/', '/.idea/', '/.vscode/', '/__macosx/' ];
        foreach ( $blockedSegments as $seg ) {
            if ( str_contains( '/' . $lower . '/', $seg ) ) {
                return true;
            }
        }

        // Exclude vendor/ unless explicitly kept (many plugins bundle Composer vendor/ needed at runtime).
        if ( ! empty( $options['exclude_vendor'] ) && str_contains( '/' . $lower . '/', '/vendor/' ) ) {
            return true;
        }

        $blockedPrefixes = [
            'wp-content/cache/',
            'wp-content/upgrade/',
            'wp-content/upgrade-temp-backup/',
            'wp-content/ai-cache/',
            'wp-content/dbhdl-snapshots',
            'wp-content/proid-backups',
            'wp-content/backups-dup-pro/',
        ];
        foreach ( $blockedPrefixes as $pfx ) {
            if ( str_starts_with( $lower, $pfx ) ) {
                return true;
            }
        }

        $includeMedia = ! empty( $options['include_media'] );
        if ( ! $includeMedia && ( $lower === 'wp-content/uploads' || str_starts_with( $lower, 'wp-content/uploads/' ) ) ) {
            return true;
        }

        $blockedExts = [ 'bak', 'tmp', 'log', 'daf', 'pbak', 'pdr-enc' ];
        if ( empty( $options['allow_archives'] ) ) {
            $blockedExts[] = 'zip';
        }
        $ext = strtolower( pathinfo( $basename, PATHINFO_EXTENSION ) );
        if ( '' !== $ext && in_array( $ext, $blockedExts, true ) ) {
            return true;
        }

        if ( ! empty( $options['custom_excludes'] ) && is_array( $options['custom_excludes'] ) ) {
            foreach ( $options['custom_excludes'] as $pattern ) {
                $pat = ltrim( self::normalize( trim( (string) $pattern ) ), '/' );
                if ( '' !== $pat && ( $lower === strtolower( $pat ) || str_starts_with( $lower, strtolower( rtrim( $pat, '/' ) ) . '/' ) ) ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Create directory and place Apache/IIS/PHP guards so archives cannot be fetched over HTTP.
     */
    public static function protectDirectory( string $dir ): bool {
        if ( ! is_dir( $dir ) ) {
            if ( function_exists( 'wp_mkdir_p' ) ) {
                wp_mkdir_p( $dir );
            } else {
                @mkdir( $dir, 0750, true );
            }
        }
        if ( ! is_dir( $dir ) ) {
            return false;
        }

        $htaccess = rtrim( $dir, '/\\' ) . '/.htaccess';
        if ( ! file_exists( $htaccess ) ) {
            @file_put_contents(
                $htaccess,
                "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder deny,allow\nDeny from all\n</IfModule>\n"
            );
        }

        $webConfig = rtrim( $dir, '/\\' ) . '/web.config';
        if ( ! file_exists( $webConfig ) ) {
            @file_put_contents(
                $webConfig,
                "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>\n"
            );
        }

        $index = rtrim( $dir, '/\\' ) . '/index.php';
        if ( ! file_exists( $index ) ) {
            @file_put_contents( $index, "<?php\n// Silence is golden.\n" );
        }

        return is_writable( $dir );
    }

    /**
     * Pick the safest writable directory for storing snapshots (outside webroot preferred).
     *
     * @return array{dir: string, outside_webroot: bool}
     */
    public static function resolveSnapshotBaseDir(): array {
        $absBase      = defined( 'ABSPATH' ) ? rtrim( self::normalize( ABSPATH ), '/' ) : '';
        $wpContentDir = defined( 'WP_CONTENT_DIR' ) ? rtrim( self::normalize( WP_CONTENT_DIR ), '/' ) : ( $absBase . '/wp-content' );

        if ( '' !== $absBase ) {
            $parent = dirname( $absBase );
            if ( '' !== $parent && $parent !== $absBase && is_dir( $parent ) && is_writable( $parent ) ) {
                $siteHash   = substr( hash( 'sha256', $absBase ), 0, 10 );
                $outsideDir = rtrim( self::normalize( $parent ), '/' ) . '/dbhdl-snapshots-' . $siteHash;
                if ( self::protectDirectory( $outsideDir ) ) {
                    return [
                        'dir'             => $outsideDir . '/',
                        'outside_webroot' => true,
                    ];
                }
            }
        }

        $token = '';
        if ( function_exists( 'get_option' ) && function_exists( 'update_option' ) ) {
            $token = (string) get_option( 'dbhdl_snapshot_dir_token', '' );
            if ( '' === $token ) {
                $token = substr( CryptoVault::randomHex( 16 ), 0, 16 );
                update_option( 'dbhdl_snapshot_dir_token', $token, false );
            }
        } else {
            $token = substr( hash( 'sha256', $wpContentDir ), 0, 16 );
        }

        $insideDir = $wpContentDir . '/dbhdl-snapshots-' . $token;
        self::protectDirectory( $insideDir );

        return [
            'dir'             => $insideDir . '/',
            'outside_webroot' => false,
        ];
    }
}
