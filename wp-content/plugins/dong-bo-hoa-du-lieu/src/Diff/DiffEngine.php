<?php
declare(strict_types=1);

namespace DBHDL\Sync\Diff;

use DBHDL\Sync\Security\PathGuard;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Computes file-level dry-run diffs across directories, ZIP archives, and remote manifests.
 */
final class DiffEngine {

    /**
     * Scan selected relative prefixes under `$baseDir` into a normalized metadata map.
     *
     * @param array<int, string> $prefixes
     * @param array<string, mixed> $options
     * @return array<string, array{size: int, mtime: int, sha256: string}>
     */
    public static function scanDirectoryTree( string $baseDir, array $prefixes, array $options = [] ): array {
        $baseDir = rtrim( PathGuard::normalize( $baseDir ), '/' );
        $map     = [];

        if ( '' === $baseDir || ! is_dir( $baseDir ) ) {
            return $map;
        }

        foreach ( $prefixes as $prefix ) {
            $cleanPrefix = trim( PathGuard::normalize( $prefix ), '/' );
            if ( '' === $cleanPrefix ) {
                continue;
            }
            $absDir = PathGuard::resolveUnderBase( $baseDir, $cleanPrefix );
            if ( null === $absDir ) {
                continue;
            }

            if ( is_file( $absDir ) ) {
                if ( ! PathGuard::isExcluded( $cleanPrefix, $options ) ) {
                    $hash = @hash_file( 'sha256', $absDir );
                    if ( false !== $hash ) {
                        $map[ $cleanPrefix ] = [
                            'size'   => (int) @filesize( $absDir ),
                            'mtime'  => (int) @filemtime( $absDir ),
                            'sha256' => $hash,
                        ];
                    }
                }
                continue;
            }

            if ( ! is_dir( $absDir ) ) {
                continue;
            }

            $iter = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator( $absDir, RecursiveDirectoryIterator::SKIP_DOTS ),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ( $iter as $item ) {
                if ( $item->isDir() || $item->isLink() ) {
                    continue;
                }
                $rel = PathGuard::relativePath( $baseDir, $item->getPathname() );
                if ( '' === $rel || PathGuard::isExcluded( $rel, $options ) ) {
                    continue;
                }
                $hash = @hash_file( 'sha256', $item->getPathname() );
                if ( false === $hash ) {
                    continue;
                }
                $map[ $rel ] = [
                    'size'   => (int) $item->getSize(),
                    'mtime'  => (int) $item->getMTime(),
                    'sha256' => $hash,
                ];
            }
        }

        ksort( $map );
        return $map;
    }

    /**
     * Compare source file map against target file map.
     *
     * @param array<string, array{size: int, mtime?: int, sha256: string}> $sourceMap
     * @param array<string, array{size: int, mtime?: int, sha256: string}> $targetMap
     * @param array<int, string> $prefixes
     * @return array<string, mixed>
     */
    public static function compareTrees(
        array $sourceMap,
        array $targetMap,
        array $prefixes = [],
        bool $includeDeleted = true
    ): array {
        $items         = [];
        $added         = 0;
        $modified      = 0;
        $deleted       = 0;
        $unchanged     = 0;
        $transferBytes = 0;

        foreach ( $sourceMap as $path => $srcMeta ) {
            if ( ! empty( $prefixes ) && ! self::matchesAnyPrefix( $path, $prefixes ) ) {
                continue;
            }
            if ( ! isset( $targetMap[ $path ] ) ) {
                $added++;
                $size           = (int) ( $srcMeta['size'] ?? 0 );
                $transferBytes += $size;
                $items[]        = [
                    'path'        => $path,
                    'status'      => 'added',
                    'size'        => $size,
                    'target_size' => 0,
                ];
            } elseif ( ( $srcMeta['sha256'] ?? '' ) !== ( $targetMap[ $path ]['sha256'] ?? '' ) ) {
                $modified++;
                $size           = (int) ( $srcMeta['size'] ?? 0 );
                $transferBytes += $size;
                $items[]        = [
                    'path'        => $path,
                    'status'      => 'modified',
                    'size'        => $size,
                    'target_size' => (int) ( $targetMap[ $path ]['size'] ?? 0 ),
                ];
            } else {
                $unchanged++;
            }
        }

        if ( $includeDeleted ) {
            foreach ( $targetMap as $path => $dstMeta ) {
                if ( ! empty( $prefixes ) && ! self::matchesAnyPrefix( $path, $prefixes ) ) {
                    continue;
                }
                if ( ! isset( $sourceMap[ $path ] ) ) {
                    $deleted++;
                    $items[] = [
                        'path'        => $path,
                        'status'      => 'deleted',
                        'size'        => 0,
                        'target_size' => (int) ( $dstMeta['size'] ?? 0 ),
                    ];
                }
            }
        }

        return [
            'summary' => [
                'added'          => $added,
                'modified'       => $modified,
                'deleted'        => $deleted,
                'unchanged'      => $unchanged,
                'transfer_bytes' => $transferBytes,
            ],
            'items'   => $items,
        ];
    }

    /**
     * Inspect a ZIP archive (GitHub zipball or Local build upload) against `$targetBaseDir` without extracting.
     *
     * @param array<int, string> $prefixes Selected WP relative prefixes (e.g. `wp-content/themes/my-theme`).
     * @param array<string, string> $subpathMap Optional `repo_prefix => wp_prefix` map.
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function inspectZipAgainstDirectory(
        string $zipPath,
        string $targetBaseDir,
        array $prefixes = [],
        array $subpathMap = [],
        bool $stripFirstSegment = false,
        array $options = []
    ): array {
        if ( ! class_exists( 'ZipArchive' ) ) {
            return [ 'ok' => false, 'error' => 'Máy chủ PHP chưa bật extension ZipArchive.' ];
        }

        $zip = new ZipArchive();
        if ( true !== $zip->open( $zipPath ) ) {
            return [ 'ok' => false, 'error' => 'Không thể mở tệp ZIP để kiểm tra.' ];
        }

        $rootStrip = '';
        if ( $stripFirstSegment && $zip->numFiles > 0 ) {
            $first = PathGuard::normalize( (string) $zip->getNameIndex( 0 ) );
            $seg   = explode( '/', trim( $first, '/' ) )[0] ?? '';
            if ( '' !== $seg ) {
                $rootStrip = $seg . '/';
            }
        }

        $targetBaseDir = rtrim( PathGuard::normalize( $targetBaseDir ), '/' );
        $entries       = [];
        $items         = [];
        $added         = 0;
        $modified      = 0;
        $unchanged     = 0;
        $skipped       = 0;
        $transferBytes = 0;

        for ( $i = 0; $i < $zip->numFiles; $i++ ) {
            $rawName = PathGuard::normalize( (string) $zip->getNameIndex( $i ) );
            if ( '' === $rawName || str_ends_with( $rawName, '/' ) ) {
                continue;
            }

            $rel = $rawName;
            if ( '' !== $rootStrip && str_starts_with( $rel, $rootStrip ) ) {
                $rel = substr( $rel, strlen( $rootStrip ) );
            }
            $rel = ltrim( $rel, '/' );

            if ( '' === $rel || ! PathGuard::isSafeRelativePath( $rel ) ) {
                $skipped++;
                continue;
            }

            $mappedRel = self::applySubpathMapping( $rel, $subpathMap );
            if ( PathGuard::isExcluded( $mappedRel, $options ) ) {
                $skipped++;
                continue;
            }

            if ( ! empty( $prefixes ) && ! self::matchesAnyPrefix( $mappedRel, $prefixes ) ) {
                continue;
            }

            $targetFile = PathGuard::resolveUnderBase( $targetBaseDir, $mappedRel );
            if ( null === $targetFile ) {
                $skipped++;
                continue;
            }

            $content = $zip->getFromIndex( $i );
            if ( false === $content ) {
                $skipped++;
                continue;
            }

            $size      = strlen( $content );
            $newHash   = hash( 'sha256', $content );
            $dstExists = is_file( $targetFile );
            $oldHash   = $dstExists ? (string) @hash_file( 'sha256', $targetFile ) : '';

            if ( ! $dstExists ) {
                $status = 'added';
                $added++;
                $transferBytes += $size;
            } elseif ( $newHash !== $oldHash ) {
                $status = 'modified';
                $modified++;
                $transferBytes += $size;
            } else {
                $status = 'unchanged';
                $unchanged++;
            }

            $entries[] = [
                'zip_index'  => $i,
                'target_rel' => $mappedRel,
                'status'     => $status,
                'size'       => $size,
            ];

            if ( 'unchanged' !== $status ) {
                $items[] = [
                    'path'        => $mappedRel,
                    'status'      => $status,
                    'size'        => $size,
                    'target_size' => $dstExists ? (int) @filesize( $targetFile ) : 0,
                ];
            }
        }

        $zip->close();

        return [
            'ok'      => true,
            'summary' => [
                'added'          => $added,
                'modified'       => $modified,
                'deleted'        => 0,
                'unchanged'      => $unchanged,
                'skipped'        => $skipped,
                'transfer_bytes' => $transferBytes,
            ],
            'items'   => $items,
            'entries' => $entries,
        ];
    }

    /**
     * Check whether `$relPath` lies inside any selected scope prefix.
     *
     * @param array<int, string> $prefixes
     */
    public static function matchesAnyPrefix( string $relPath, array $prefixes ): bool {
        $norm = ltrim( PathGuard::normalize( $relPath ), '/' );
        foreach ( $prefixes as $pfx ) {
            $clean = trim( PathGuard::normalize( $pfx ), '/' );
            if ( '' === $clean ) {
                continue;
            }
            if ( $norm === $clean || str_starts_with( $norm, $clean . '/' ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Map a repository-relative path into a WordPress-relative path using custom mappings or direct pass-through.
     *
     * @param array<string, string> $subpathMap
     */
    public static function applySubpathMapping( string $relPath, array $subpathMap ): string {
        $norm = ltrim( PathGuard::normalize( $relPath ), '/' );
        foreach ( $subpathMap as $repoPrefix => $wpPrefix ) {
            $r = trim( PathGuard::normalize( (string) $repoPrefix ), '/' );
            $w = trim( PathGuard::normalize( (string) $wpPrefix ), '/' );
            if ( '' === $w ) {
                continue;
            }
            if ( '' === $r || '.' === $r ) {
                return $w . '/' . $norm;
            }
            if ( $norm === $r ) {
                return $w;
            }
            if ( str_starts_with( $norm, $r . '/' ) ) {
                return $w . '/' . substr( $norm, strlen( $r ) + 1 );
            }
        }
        return $norm;
    }
}
