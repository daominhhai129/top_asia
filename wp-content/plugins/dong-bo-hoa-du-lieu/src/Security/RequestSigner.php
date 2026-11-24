<?php
declare(strict_types=1);

namespace DBHDL\Sync\Security;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * HMAC-SHA256 request signing, replay protection, and GitHub webhook signature verifier.
 */
final class RequestSigner {

    private static array $memoryNonces = [];

    /**
     * Build signed headers for a Remote Pairing REST call.
     *
     * @return array<string, string>
     */
    public static function signHeaders( string $pairId, string $secret, string $method, string $route, string $rawBody ): array {
        $timestamp = (string) time();
        $nonce     = CryptoVault::randomHex( 16 );
        $bodyHash  = hash( 'sha256', $rawBody );
        $canonical = self::canonicalString( $pairId, $timestamp, $nonce, $method, $route, $bodyHash );
        $signature = hash_hmac( 'sha256', $canonical, $secret );

        return [
            'X-DBHDL-Pair-Id'   => $pairId,
            'X-DBHDL-Timestamp' => $timestamp,
            'X-DBHDL-Nonce'     => $nonce,
            'X-DBHDL-Signature' => $signature,
        ];
    }

    /**
     * Verify incoming signed pairing request headers against expected secret and replay window.
     *
     * @param array<string, string> $headers Normalized lowercase header map.
     * @return array{ok: bool, error?: string}
     */
    public static function verifySignedHeaders(
        string $expectedPairId,
        string $secret,
        array $headers,
        string $method,
        string $route,
        string $rawBody,
        int $maxSkewSeconds = 120
    ): array {
        if ( '' === $expectedPairId || '' === $secret ) {
            return [ 'ok' => false, 'error' => 'Pairing chưa được kích hoạt trên site đích.' ];
        }

        $pairId    = trim( (string) ( $headers['x-dbhdl-pair-id'] ?? '' ) );
        $timestamp = trim( (string) ( $headers['x-dbhdl-timestamp'] ?? '' ) );
        $nonce     = trim( (string) ( $headers['x-dbhdl-nonce'] ?? '' ) );
        $signature = trim( (string) ( $headers['x-dbhdl-signature'] ?? '' ) );

        if ( '' === $pairId || '' === $timestamp || '' === $nonce || '' === $signature ) {
            return [ 'ok' => false, 'error' => 'Thiếu header xác thực chữ ký Pairing.' ];
        }
        if ( ! hash_equals( $expectedPairId, $pairId ) ) {
            return [ 'ok' => false, 'error' => 'Mã Pairing ID không khớp.' ];
        }
        if ( ! ctype_digit( $timestamp ) || abs( time() - (int) $timestamp ) > $maxSkewSeconds ) {
            return [ 'ok' => false, 'error' => 'Yêu cầu đã hết hạn (lệch thời gian quá giới hạn).' ];
        }
        if ( ! preg_match( '/^[a-f0-9]{16,64}$/i', $nonce ) ) {
            return [ 'ok' => false, 'error' => 'Nonce không hợp lệ.' ];
        }

        $bodyHash  = hash( 'sha256', $rawBody );
        $canonical = self::canonicalString( $pairId, $timestamp, $nonce, $method, $route, $bodyHash );
        $expected  = hash_hmac( 'sha256', $canonical, $secret );

        if ( ! hash_equals( $expected, strtolower( $signature ) ) ) {
            return [ 'ok' => false, 'error' => 'Chữ ký HMAC-SHA256 không hợp lệ.' ];
        }

        if ( ! self::consumeNonce( $nonce, $maxSkewSeconds + 30 ) ) {
            return [ 'ok' => false, 'error' => 'Phát hiện lặp lại yêu cầu cũ (Replay blocked).' ];
        }

        return [ 'ok' => true ];
    }

    /**
     * Verify GitHub `X-Hub-Signature-256` (`sha256=...`) or direct secret header.
     */
    public static function verifyGitHubSignature( string $secret, string $signature256, string $rawBody ): bool {
        if ( '' === $secret || '' === $signature256 ) {
            return false;
        }
        $expected = 'sha256=' . hash_hmac( 'sha256', $rawBody, $secret );
        return hash_equals( $expected, strtolower( trim( $signature256 ) ) );
    }

    private static function canonicalString(
        string $pairId,
        string $timestamp,
        string $nonce,
        string $method,
        string $route,
        string $bodyHash
    ): string {
        return implode(
            "\n",
            [
                $pairId,
                $timestamp,
                $nonce,
                strtoupper( trim( $method ) ),
                '/' . ltrim( trim( $route ), '/' ),
                $bodyHash,
            ]
        );
    }

    private static function consumeNonce( string $nonce, int $ttl ): bool {
        $key = 'dbhdl_nonce_' . substr( hash( 'sha256', $nonce ), 0, 32 );
        if ( function_exists( 'get_transient' ) && function_exists( 'set_transient' ) ) {
            if ( false !== get_transient( $key ) ) {
                return false;
            }
            set_transient( $key, 1, $ttl );
            return true;
        }

        if ( isset( self::$memoryNonces[ $key ] ) && self::$memoryNonces[ $key ] > time() ) {
            return false;
        }
        self::$memoryNonces[ $key ] = time() + $ttl;
        return true;
    }
}
