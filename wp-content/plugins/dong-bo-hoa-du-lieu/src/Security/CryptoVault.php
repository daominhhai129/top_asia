<?php
declare(strict_types=1);

namespace DBHDL\Sync\Security;

use RuntimeException;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Authenticated symmetric encryption vault for tokens, pairing secrets, and API keys.
 * Uses libsodium XChaCha20-Poly1305 when available and OpenSSL AES-256-GCM as fallback.
 */
final class CryptoVault {

    private static string $ephemeralKey = '';

    /**
     * Encrypt plaintext using authenticated encryption bound to `$context`.
     */
    public static function encrypt( string $plaintext, string $context = 'dbhdl_secrets' ): string {
        if ( '' === $plaintext ) {
            return '';
        }

        $key = self::deriveSubkey( $context );

        if ( function_exists( 'sodium_crypto_aead_xchacha20poly1305_ietf_encrypt' ) ) {
            $nonce      = random_bytes( SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES );
            $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt( $plaintext, $context, $nonce, $key );
            return 'v1:sodium:' . base64_encode( $nonce . $ciphertext );
        }

        if ( function_exists( 'openssl_encrypt' ) ) {
            $iv         = random_bytes( 12 );
            $tag        = '';
            $ciphertext = openssl_encrypt( $plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, $context, 16 );
            if ( false === $ciphertext || strlen( $tag ) !== 16 ) {
                throw new RuntimeException( 'AES-256-GCM encryption failed.' );
            }
            return 'v1:aes256gcm:' . base64_encode( $iv . $tag . $ciphertext );
        }

        // Portable authenticated fallback (SHA-256 CTR + HMAC-SHA256 EtM) when sodium & openssl are absent.
        $nonce      = random_bytes( 16 );
        $encKey     = hash_hkdf( 'sha256', $key, 32, 'enc:' . $context );
        $macKey     = hash_hkdf( 'sha256', $key, 32, 'mac:' . $context );
        $ciphertext = self::xorCtrStream( $plaintext, $encKey, $nonce );
        $mac        = hash_hmac( 'sha256', $context . "\0" . $nonce . $ciphertext, $macKey, true );
        return 'v1:hmacsha256:' . base64_encode( $nonce . $mac . $ciphertext );
    }

    /**
     * Decrypt a value encrypted by encrypt(). Throws on authentication failure.
     */
    public static function decrypt( string $encoded, string $context = 'dbhdl_secrets' ): string {
        if ( '' === $encoded ) {
            return '';
        }

        $key = self::deriveSubkey( $context );

        if ( str_starts_with( $encoded, 'v1:sodium:' ) ) {
            if ( ! function_exists( 'sodium_crypto_aead_xchacha20poly1305_ietf_decrypt' ) ) {
                throw new RuntimeException( 'libsodium extension is required to decrypt stored secrets.' );
            }
            $raw  = base64_decode( substr( $encoded, strlen( 'v1:sodium:' ) ), true );
            $nlen = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
            if ( false === $raw || strlen( $raw ) < ( $nlen + SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_ABYTES ) ) {
                throw new RuntimeException( 'Malformed libsodium payload.' );
            }
            $plain = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
                substr( $raw, $nlen ),
                $context,
                substr( $raw, 0, $nlen ),
                $key
            );
            if ( false === $plain ) {
                throw new RuntimeException( 'Secret authentication failed (WP salts changed or data tampered).' );
            }
            return $plain;
        }

        if ( str_starts_with( $encoded, 'v1:aes256gcm:' ) ) {
            if ( ! function_exists( 'openssl_decrypt' ) ) {
                throw new RuntimeException( 'OpenSSL extension is required to decrypt stored secrets.' );
            }
            $raw = base64_decode( substr( $encoded, strlen( 'v1:aes256gcm:' ) ), true );
            if ( false === $raw || strlen( $raw ) < 28 ) {
                throw new RuntimeException( 'Malformed AES-256-GCM payload.' );
            }
            $plain = openssl_decrypt(
                substr( $raw, 28 ),
                'aes-256-gcm',
                $key,
                OPENSSL_RAW_DATA,
                substr( $raw, 0, 12 ),
                substr( $raw, 12, 16 ),
                $context
            );
            if ( false === $plain ) {
                throw new RuntimeException( 'Secret authentication failed (WP salts changed or data tampered).' );
            }
            return $plain;
        }

        if ( str_starts_with( $encoded, 'v1:hmacsha256:' ) ) {
            $raw = base64_decode( substr( $encoded, strlen( 'v1:hmacsha256:' ) ), true );
            if ( false === $raw || strlen( $raw ) < 48 ) {
                throw new RuntimeException( 'Malformed hmacsha256 payload.' );
            }
            $nonce      = substr( $raw, 0, 16 );
            $mac        = substr( $raw, 16, 32 );
            $ciphertext = substr( $raw, 48 );
            $encKey     = hash_hkdf( 'sha256', $key, 32, 'enc:' . $context );
            $macKey     = hash_hkdf( 'sha256', $key, 32, 'mac:' . $context );
            $expected   = hash_hmac( 'sha256', $context . "\0" . $nonce . $ciphertext, $macKey, true );
            if ( ! hash_equals( $expected, $mac ) ) {
                throw new RuntimeException( 'Secret authentication failed (WP salts changed or data tampered).' );
            }
            return self::xorCtrStream( $ciphertext, $encKey, $nonce );
        }

        // Legacy unencrypted value fallback (only returned if explicitly non-prefixed).
        return $encoded;
    }

    private static function xorCtrStream( string $data, string $encKey, string $nonce ): string {
        $len     = strlen( $data );
        $out     = '';
        $counter = 0;
        for ( $offset = 0; $offset < $len; $offset += 32 ) {
            $block = hash_hmac( 'sha256', $nonce . pack( 'N', $counter++ ), $encKey, true );
            $chunk = substr( $data, $offset, 32 );
            $out  .= $chunk ^ substr( $block, 0, strlen( $chunk ) );
        }
        return $out;
    }

    /**
     * Safe variant that returns empty string instead of throwing if salts were rotated.
     */
    public static function decryptSafe( string $encoded, string $context = 'dbhdl_secrets' ): string {
        try {
            return self::decrypt( $encoded, $context );
        } catch ( \Throwable $e ) {
            return '';
        }
    }

    /**
     * Render a write-only masked preview (`••••abcd`) without exposing the secret.
     */
    public static function mask( string $plaintext ): string {
        $trimmed = trim( $plaintext );
        $len     = strlen( $trimmed );
        if ( 0 === $len ) {
            return '';
        }
        if ( $len <= 6 ) {
            return '••••';
        }
        return '••••' . substr( $trimmed, -4 );
    }

    /**
     * Generate a cryptographically random hex string.
     */
    public static function randomHex( int $bytes = 32 ): string {
        return bin2hex( random_bytes( max( 8, $bytes ) ) );
    }

    /**
     * Derive a 32-byte context-bound subkey from WordPress salts or local master key.
     */
    private static function deriveSubkey( string $context ): string {
        $materials = [];
        if ( defined( 'AUTH_KEY' ) && '' !== (string) AUTH_KEY ) {
            $materials[] = (string) AUTH_KEY;
        }
        if ( defined( 'SECURE_AUTH_KEY' ) && '' !== (string) SECURE_AUTH_KEY ) {
            $materials[] = (string) SECURE_AUTH_KEY;
        }
        if ( empty( $materials ) && function_exists( 'wp_salt' ) ) {
            $materials[] = wp_salt( 'auth' );
            $materials[] = wp_salt( 'secure_auth' );
        }
        if ( empty( $materials ) && function_exists( 'get_option' ) && function_exists( 'update_option' ) ) {
            $stored = (string) get_option( 'dbhdl_local_master_key', '' );
            if ( '' === $stored ) {
                $stored = self::randomHex( 32 );
                update_option( 'dbhdl_local_master_key', $stored, false );
            }
            $materials[] = $stored;
        }
        if ( empty( $materials ) ) {
            if ( '' === self::$ephemeralKey ) {
                self::$ephemeralKey = self::randomHex( 32 );
            }
            $materials[] = self::$ephemeralKey;
        }

        $master = hash( 'sha256', implode( '|', $materials ), true );
        return hash_hkdf( 'sha256', $master, 32, 'dbhdl:' . $context );
    }
}
