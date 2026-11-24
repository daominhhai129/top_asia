<?php
declare(strict_types=1);

namespace DBHDL\Sync\Database;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Pure-PHP, zero-`unserialize()` length-aware Search & Replace engine for serialized PHP,
 * JSON-escaped URLs (`https:\/\/`), and plain strings. Eliminates PHP Object Injection risks
 * and serialized string byte-length corruption when migrating between domains/paths.
 */
final class SafeSearchReplace {

    private const MAX_DEPTH = 8;

    /**
     * Build canonical search => replace map for URL migrations.
     *
     * @return array<string, string>
     */
    public static function buildMap( string $oldUrl, string $newUrl, string $oldPath = '', string $newPath = '' ): array {
        $oldUrl = rtrim( trim( $oldUrl ), '/' );
        $newUrl = rtrim( trim( $newUrl ), '/' );
        $map    = [];

        if ( '' !== $oldUrl && '' !== $newUrl && $oldUrl !== $newUrl ) {
            $map[ $oldUrl ] = $newUrl;

            $oldJson = str_replace( '/', '\\/', $oldUrl );
            $newJson = str_replace( '/', '\\/', $newUrl );
            $map[ $oldJson ] = $newJson;

            $map[ rawurlencode( $oldUrl ) ] = rawurlencode( $newUrl );

            // Handle opposite scheme variant (http vs https) cleanly.
            if ( str_starts_with( $oldUrl, 'https://' ) ) {
                $httpVariant         = 'http://' . substr( $oldUrl, 8 );
                $map[ $httpVariant ] = $newUrl;
                $map[ str_replace( '/', '\\/', $httpVariant ) ] = $newJson;
            } elseif ( str_starts_with( $oldUrl, 'http://' ) ) {
                $httpsVariant         = 'https://' . substr( $oldUrl, 7 );
                $map[ $httpsVariant ] = $newUrl;
                $map[ str_replace( '/', '\\/', $httpsVariant ) ] = $newJson;
            }

            $oldProtoRel = (string) preg_replace( '#^https?://#i', '//', $oldUrl );
            $newProtoRel = (string) preg_replace( '#^https?://#i', '//', $newUrl );
            if ( $oldProtoRel !== $newProtoRel && str_starts_with( $oldProtoRel, '//' ) ) {
                $map[ $oldProtoRel ] = $newProtoRel;
            }
        }

        $oldPath = rtrim( str_replace( '\\', '/', trim( $oldPath ) ), '/' );
        $newPath = rtrim( str_replace( '\\', '/', trim( $newPath ) ), '/' );
        if ( '' !== $oldPath && '' !== $newPath && $oldPath !== $newPath ) {
            $map[ $oldPath ] = $newPath;
            $map[ str_replace( '/', '\\/', $oldPath ) ] = str_replace( '/', '\\/', $newPath );
        }

        // Longest keys first so `/staging` matches before base domain.
        uksort(
            $map,
            static fn( string $a, string $b ): int => strlen( $b ) <=> strlen( $a )
        );

        return $map;
    }

    /**
     * Replace occurrences across a string (serialized PHP, JSON, or plain text).
     * Returns original string untouched if serialized grammar parsing fails.
     *
     * @param array<string, string> $map
     */
    public static function replaceString( string $input, array $map, int $depth = 0 ): string {
        if ( '' === $input || empty( $map ) || $depth > self::MAX_DEPTH ) {
            return $input;
        }

        if ( self::looksSerialized( $input ) ) {
            $cursor = 0;
            $parsed = self::parseNode( $input, $cursor, $map, $depth );
            if ( null !== $parsed && $cursor === strlen( $input ) ) {
                return $parsed;
            }
            // Fail-safe: do not corrupt malformed/truncated serialized strings with raw strtr().
            return $input;
        }

        return strtr( $input, $map );
    }

    /**
     * Detect serialized PHP value prefix without unserializing.
     */
    public static function looksSerialized( string $data ): bool {
        $trimmed = trim( $data );
        if ( '' === $trimmed || strlen( $trimmed ) < 2 ) {
            return false;
        }
        if ( 'N;' === $trimmed ) {
            return true;
        }
        return 1 === preg_match( '/^(?:[bidrR]:|s:\d+:"|a:\d+:\{|O:\d+:"|C:\d+:")/', $data );
    }

    /**
     * Parse and rewrite a single serialized PHP node starting at `$offset`.
     *
     * @param array<string, string> $map
     */
    private static function parseNode( string $data, int &$offset, array $map, int $depth ): ?string {
        $len = strlen( $data );
        if ( $offset >= $len ) {
            return null;
        }

        $type = $data[ $offset ];

        switch ( $type ) {
            case 'N':
                if ( substr( $data, $offset, 2 ) !== 'N;' ) {
                    return null;
                }
                $offset += 2;
                return 'N;';

            case 'b':
            case 'i':
            case 'd':
            case 'r':
            case 'R':
                if ( ( $data[ $offset + 1 ] ?? '' ) !== ':' ) {
                    return null;
                }
                $semi = strpos( $data, ';', $offset + 2 );
                if ( false === $semi ) {
                    return null;
                }
                $tok    = substr( $data, $offset, $semi - $offset + 1 );
                $offset = $semi + 1;
                return $tok;

            case 's':
                if ( ( $data[ $offset + 1 ] ?? '' ) !== ':' ) {
                    return null;
                }
                $colon2 = strpos( $data, ':', $offset + 2 );
                if ( false === $colon2 ) {
                    return null;
                }
                $lenStr = substr( $data, $offset + 2, $colon2 - ( $offset + 2 ) );
                if ( ! ctype_digit( $lenStr ) || ( $data[ $colon2 + 1 ] ?? '' ) !== '"' ) {
                    return null;
                }
                $byteLen = (int) $lenStr;
                $strStart = $colon2 + 2;
                if ( $strStart + $byteLen + 2 > $len ) {
                    return null;
                }
                if ( substr( $data, $strStart + $byteLen, 2 ) !== '";' ) {
                    return null;
                }
                $rawStr  = substr( $data, $strStart, $byteLen );
                $offset  = $strStart + $byteLen + 2;
                $newStr  = self::replaceString( $rawStr, $map, $depth + 1 );
                return 's:' . strlen( $newStr ) . ':"' . $newStr . '";';

            case 'a':
                if ( ( $data[ $offset + 1 ] ?? '' ) !== ':' ) {
                    return null;
                }
                $brace = strpos( $data, '{', $offset + 2 );
                if ( false === $brace || ( $data[ $brace - 1 ] ?? '' ) !== ':' ) {
                    return null;
                }
                $countStr = substr( $data, $offset + 2, $brace - ( $offset + 2 ) - 1 );
                if ( ! ctype_digit( $countStr ) ) {
                    return null;
                }
                $count  = (int) $countStr;
                $offset = $brace + 1;
                $body   = '';
                for ( $i = 0; $i < $count; $i++ ) {
                    $k = self::parseNode( $data, $offset, $map, $depth );
                    $v = self::parseNode( $data, $offset, $map, $depth );
                    if ( null === $k || null === $v ) {
                        return null;
                    }
                    $body .= $k . $v;
                }
                if ( ( $data[ $offset ] ?? '' ) !== '}' ) {
                    return null;
                }
                $offset++;
                return 'a:' . $count . ':{' . $body . '}';

            case 'O':
                if ( ( $data[ $offset + 1 ] ?? '' ) !== ':' ) {
                    return null;
                }
                $colonCls = strpos( $data, ':', $offset + 2 );
                if ( false === $colonCls ) {
                    return null;
                }
                $clsLenStr = substr( $data, $offset + 2, $colonCls - ( $offset + 2 ) );
                if ( ! ctype_digit( $clsLenStr ) || ( $data[ $colonCls + 1 ] ?? '' ) !== '"' ) {
                    return null;
                }
                $clsLen   = (int) $clsLenStr;
                $clsStart = $colonCls + 2;
                if ( substr( $data, $clsStart + $clsLen, 2 ) !== '":' ) {
                    return null;
                }
                $clsName  = substr( $data, $clsStart, $clsLen );
                $propPos  = $clsStart + $clsLen + 2;
                $brace    = strpos( $data, '{', $propPos );
                if ( false === $brace || ( $data[ $brace - 1 ] ?? '' ) !== ':' ) {
                    return null;
                }
                $propCountStr = substr( $data, $propPos, $brace - $propPos - 1 );
                if ( ! ctype_digit( $propCountStr ) ) {
                    return null;
                }
                $propCount = (int) $propCountStr;
                $offset    = $brace + 1;
                $body      = '';
                for ( $i = 0; $i < $propCount; $i++ ) {
                    $pk = self::parseNode( $data, $offset, $map, $depth );
                    $pv = self::parseNode( $data, $offset, $map, $depth );
                    if ( null === $pk || null === $pv ) {
                        return null;
                    }
                    $body .= $pk . $pv;
                }
                if ( ( $data[ $offset ] ?? '' ) !== '}' ) {
                    return null;
                }
                $offset++;
                return 'O:' . $clsLen . ':"' . $clsName . '":' . $propCount . ':{' . $body . '}';

            case 'C':
                if ( ( $data[ $offset + 1 ] ?? '' ) !== ':' ) {
                    return null;
                }
                $colonCls = strpos( $data, ':', $offset + 2 );
                if ( false === $colonCls ) {
                    return null;
                }
                $clsLenStr = substr( $data, $offset + 2, $colonCls - ( $offset + 2 ) );
                if ( ! ctype_digit( $clsLenStr ) || ( $data[ $colonCls + 1 ] ?? '' ) !== '"' ) {
                    return null;
                }
                $clsLen   = (int) $clsLenStr;
                $clsStart = $colonCls + 2;
                if ( substr( $data, $clsStart + $clsLen, 2 ) !== '":' ) {
                    return null;
                }
                $clsName  = substr( $data, $clsStart, $clsLen );
                $payPos   = $clsStart + $clsLen + 2;
                $brace    = strpos( $data, '{', $payPos );
                if ( false === $brace || ( $data[ $brace - 1 ] ?? '' ) !== ':' ) {
                    return null;
                }
                $payLenStr = substr( $data, $payPos, $brace - $payPos - 1 );
                if ( ! ctype_digit( $payLenStr ) ) {
                    return null;
                }
                $payLen = (int) $payLenStr;
                $start  = $brace + 1;
                if ( $start + $payLen >= $len || ( $data[ $start + $payLen ] ?? '' ) !== '}' ) {
                    return null;
                }
                $payload = substr( $data, $start, $payLen );
                $offset  = $start + $payLen + 1;
                return 'C:' . $clsLen . ':"' . $clsName . '":' . $payLen . ':{' . $payload . '}';

            default:
                return null;
        }
    }
}
