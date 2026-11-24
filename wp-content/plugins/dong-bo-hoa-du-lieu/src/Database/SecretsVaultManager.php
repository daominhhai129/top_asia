<?php
declare(strict_types=1);

namespace DBHDL\Sync\Database;

use DBHDL\Sync\Security\CryptoVault;
use wpdb;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Encrypted, write-only Environment Variables & API Secrets Vault.
 * Universal across any WordPress project — supports environment-aware sync policies
 * (`overwrite`, `fill_missing`, `keep_target`) and never stores secrets in code or Git.
 */
final class SecretsVaultManager {

    public const OPTION_REGISTRY = 'dbhdl_vault_registry';
    public const OPTION_SECRETS  = 'dbhdl_vault_secrets';

    /**
     * Quick-add preset catalog (optional convenience templates for users).
     *
     * @return array<string, array<string, string>>
     */
    public static function getPresetTemplates(): array {
        return [
            'openai' => [
                'slug'   => 'openai',
                'label'  => 'OpenAI API Key',
                'option' => 'dbhdl_secret_openai',
                'note'   => 'Dùng cho tích hợp GPT / Embeddings',
            ],
            'gemini' => [
                'slug'   => 'gemini',
                'label'  => 'Google Gemini API Key',
                'option' => 'dbhdl_secret_gemini',
                'note'   => 'Dùng cho Google Gemini AI API',
            ],
            'openrouter' => [
                'slug'   => 'openrouter',
                'label'  => 'OpenRouter API Key',
                'option' => 'dbhdl_secret_openrouter',
                'note'   => 'Gateway đa model AI qua OpenRouter',
            ],
            'smtp_pass' => [
                'slug'   => 'smtp_pass',
                'label'  => 'SMTP Password / Mail API Key',
                'option' => 'dbhdl_secret_smtp_pass',
                'note'   => 'Mật khẩu SMTP hoặc SendGrid/Mailgun/SES key',
            ],
            'payment_secret' => [
                'slug'   => 'payment_secret',
                'label'  => 'Payment Gateway Secret Key',
                'option' => 'dbhdl_secret_payment',
                'note'   => 'Cổng thanh toán (nên chọn Keep Target giữa Test & Live)',
            ],
            'google_oauth_secret' => [
                'slug'   => 'google_oauth_secret',
                'label'  => 'Google OAuth Client Secret',
                'option' => 'dbhdl_secret_google_oauth',
                'note'   => 'Đăng nhập Google OAuth 2.0',
            ],
        ];
    }

    /**
     * List all registered services with write-only masked statuses (no plaintext returned).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getMaskedServices(): array {
        $registry = self::getRegistry();
        $secrets  = self::getRawEncryptedMap();
        $out      = [];

        foreach ( $registry as $slug => $svc ) {
            $const       = (string) ( $svc['constant'] ?? '' );
            $fromConfig  = '' !== $const && defined( $const ) && '' !== trim( (string) constant( $const ) );
            $plaintext   = '';

            if ( $fromConfig ) {
                $plaintext = trim( (string) constant( $const ) );
            } elseif ( ! empty( $secrets[ $slug ] ) ) {
                $plaintext = CryptoVault::decryptSafe( (string) $secrets[ $slug ] );
            } elseif ( ! empty( $svc['option'] ) && function_exists( 'get_option' ) ) {
                $plaintext = trim( (string) get_option( (string) $svc['option'], '' ) );
            }

            $out[] = [
                'slug'        => $slug,
                'label'       => (string) ( $svc['label'] ?? $slug ),
                'option'      => (string) ( $svc['option'] ?? '' ),
                'constant'    => $const,
                'note'        => (string) ( $svc['note'] ?? '' ),
                'policy'      => (string) ( $svc['policy'] ?? 'sync' ),
                'configured'  => '' !== $plaintext,
                'from_config' => $fromConfig,
                'hint'        => CryptoVault::mask( $plaintext ),
            ];
        }

        return $out;
    }

    /**
     * Retrieve a decrypted secret by slug for developers (`DBHDL\Sync\Database\SecretsVaultManager::getSecret('slug')`).
     */
    public static function getSecret( string $slug, string $default = '' ): string {
        $cleanSlug = sanitize_key( $slug );
        $registry  = self::getRegistry();
        $svc       = $registry[ $cleanSlug ] ?? [];
        $const     = (string) ( $svc['constant'] ?? '' );

        if ( '' !== $const && defined( $const ) && '' !== trim( (string) constant( $const ) ) ) {
            return trim( (string) constant( $const ) );
        }

        $secrets = self::getRawEncryptedMap();
        if ( ! empty( $secrets[ $cleanSlug ] ) ) {
            $dec = CryptoVault::decryptSafe( (string) $secrets[ $cleanSlug ] );
            if ( '' !== $dec ) {
                return $dec;
            }
        }

        $opt = (string) ( $svc['option'] ?? '' );
        if ( '' !== $opt && ! SelectiveDbSync::isProtectedOption( $opt ) && function_exists( 'get_option' ) ) {
            $val = trim( (string) get_option( $opt, '' ) );
            if ( '' !== $val ) {
                return $val;
            }
        }

        return $default;
    }

    /**
     * Add or update a service definition (optionally setting its secret encrypted).
     *
     * @param array<string, mixed> $data
     * @return array{ok: bool, error?: string, service?: array<string, mixed>}
     */
    public static function saveService( array $data ): array {
        $label = sanitize_text_field( (string) ( $data['label'] ?? '' ) );
        $slug  = sanitize_key( str_replace( '-', '_', (string) ( $data['slug'] ?? '' ) ) );
        if ( '' === $slug && '' !== $label ) {
            $slug = sanitize_key( str_replace( '-', '_', sanitize_title( $label ) ) );
        }
        if ( '' === $slug || ! preg_match( '/^[a-z0-9_]{2,48}$/', $slug ) ) {
            return [ 'ok' => false, 'error' => 'Slug dịch vụ phải gồm 2–48 ký tự chữ thường, số hoặc dấu gạch dưới (_).' ];
        }

        $optionName = sanitize_key( (string) ( $data['option'] ?? '' ) );
        if ( '' !== $optionName && SelectiveDbSync::isProtectedOption( $optionName ) ) {
            return [ 'ok' => false, 'error' => 'Tên wp_option này nằm trong danh sách bảo vệ hệ thống.' ];
        }

        $constName = strtoupper( preg_replace( '/[^A-Za-z0-9_]/', '_', (string) ( $data['constant'] ?? '' ) ) );
        $policy    = in_array( (string) ( $data['policy'] ?? 'sync' ), [ 'sync', 'fill_missing', 'keep_target' ], true )
            ? (string) $data['policy']
            : 'sync';

        $registry           = self::getRegistry();
        $registry[ $slug ]  = [
            'slug'     => $slug,
            'label'    => '' !== $label ? $label : strtoupper( $slug ),
            'option'   => $optionName,
            'constant' => $constName,
            'note'     => sanitize_text_field( (string) ( $data['note'] ?? '' ) ),
            'policy'   => $policy,
        ];
        update_option( self::OPTION_REGISTRY, $registry, false );

        if ( isset( $data['secret'] ) && '' !== trim( (string) $data['secret'] ) ) {
            $secret = trim( (string) $data['secret'] );
            if ( preg_match( '/[\r\n\x00]/', $secret ) ) {
                return [ 'ok' => false, 'error' => 'Giá trị Secret chứa ký tự xuống dòng hoặc NUL không hợp lệ.' ];
            }
            self::storeSecretValue( $slug, $optionName, $secret );
        }

        return [ 'ok' => true, 'service' => $registry[ $slug ] ];
    }

    /**
     * Save batch updates to secrets without wiping fields left blank.
     *
     * @param array<string, string> $keysBySlug
     * @param array<int, string> $clearSlugs
     */
    public static function updateSecretsBatch( array $keysBySlug, array $clearSlugs = [] ): array {
        $registry = self::getRegistry();
        $secrets  = self::getRawEncryptedMap();
        $updated  = [];
        $cleared  = [];

        foreach ( $clearSlugs as $slug ) {
            $cleanSlug = sanitize_key( (string) $slug );
            if ( isset( $secrets[ $cleanSlug ] ) ) {
                unset( $secrets[ $cleanSlug ] );
                $cleared[] = $registry[ $cleanSlug ]['label'] ?? $cleanSlug;
            }
            $opt = (string) ( $registry[ $cleanSlug ]['option'] ?? '' );
            if ( '' !== $opt && ! SelectiveDbSync::isProtectedOption( $opt ) ) {
                delete_option( $opt );
            }
        }

        foreach ( $keysBySlug as $slug => $val ) {
            $cleanSlug = sanitize_key( (string) $slug );
            $trimmed   = trim( (string) $val );
            if ( '' === $trimmed || ! isset( $registry[ $cleanSlug ] ) || preg_match( '/[\r\n\x00]/', $trimmed ) ) {
                continue;
            }
            $secrets[ $cleanSlug ] = CryptoVault::encrypt( $trimmed );
            $opt                   = (string) ( $registry[ $cleanSlug ]['option'] ?? '' );
            if ( '' !== $opt && ! SelectiveDbSync::isProtectedOption( $opt ) ) {
                update_option( $opt, $trimmed, false );
            }
            $updated[] = $registry[ $cleanSlug ]['label'];
        }

        update_option( self::OPTION_SECRETS, $secrets, false );

        return [
            'updated' => $updated,
            'cleared' => $cleared,
        ];
    }

    public static function deleteService( string $slug ): bool {
        $cleanSlug = sanitize_key( $slug );
        $registry  = self::getRegistry();
        if ( ! isset( $registry[ $cleanSlug ] ) ) {
            return false;
        }
        $opt = (string) ( $registry[ $cleanSlug ]['option'] ?? '' );
        unset( $registry[ $cleanSlug ] );
        update_option( self::OPTION_REGISTRY, $registry, false );

        $secrets = self::getRawEncryptedMap();
        unset( $secrets[ $cleanSlug ] );
        update_option( self::OPTION_SECRETS, $secrets, false );

        if ( '' !== $opt && ! SelectiveDbSync::isProtectedOption( $opt ) ) {
            delete_option( $opt );
        }
        return true;
    }

    /**
     * Build a transfer bundle of secrets respecting each item's policy (or explicit global mode).
     * Only sent directly to Same-Server DB or inside HMAC-signed Remote Pairing requests.
     *
     * @return array<int, array<string, string>>
     */
    public static function exportTransferItems( string $mode = 'overwrite' ): array {
        $registry = self::getRegistry();
        $items    = [];
        foreach ( $registry as $slug => $svc ) {
            if ( 'keep_target' === ( $svc['policy'] ?? 'sync' ) ) {
                continue;
            }
            $val = self::getSecret( $slug );
            if ( '' === $val ) {
                continue;
            }
            $effectiveMode = 'fill_missing' === ( $svc['policy'] ?? 'sync' ) ? 'fill_missing' : $mode;
            $items[]       = [
                'slug'     => $slug,
                'label'    => (string) $svc['label'],
                'option'   => (string) ( $svc['option'] ?? '' ),
                'constant' => (string) ( $svc['constant'] ?? '' ),
                'note'     => (string) ( $svc['note'] ?? '' ),
                'policy'   => (string) ( $svc['policy'] ?? 'sync' ),
                'mode'     => $effectiveMode,
                'value'    => $val,
            ];
        }
        return $items;
    }

    /**
     * Apply transferred vault items into `$targetDb` (re-encrypting under target's own salts if local or storing cleanly).
     *
     * @param array<int, array<string, string>> $items
     * @return array{synced: array<int, string>, skipped: array<int, string>}
     */
    public static function importTransferItems( wpdb $targetDb, array $items, string $globalMode = 'overwrite', bool $targetIsCurrentSite = false ): array {
        $synced  = [];
        $skipped = [];

        $rawReg = SelectiveDbSync::dbGetOption( $targetDb, self::OPTION_REGISTRY, null );
        $reg    = is_string( $rawReg ) ? maybe_unserialize( $rawReg ) : [];
        if ( ! is_array( $reg ) ) {
            $reg = [];
        }

        $rawSec = SelectiveDbSync::dbGetOption( $targetDb, self::OPTION_SECRETS, null );
        $secMap = is_string( $rawSec ) ? maybe_unserialize( $rawSec ) : [];
        if ( ! is_array( $secMap ) ) {
            $secMap = [];
        }

        // Also mirror into target linked wp_options when configured; if target shares DB salts or is current site, encrypt with CryptoVault.
        foreach ( $items as $item ) {
            $slug  = sanitize_key( (string) ( $item['slug'] ?? '' ) );
            $val   = (string) ( $item['value'] ?? '' );
            $label = sanitize_text_field( (string) ( $item['label'] ?? $slug ) );
            $opt   = sanitize_key( (string) ( $item['option'] ?? '' ) );
            $mode  = ( 'fill_missing' === $globalMode || 'fill_missing' === ( $item['mode'] ?? '' ) ) ? 'fill_missing' : 'overwrite';

            if ( '' === $slug || '' === $val ) {
                continue;
            }

            $existingTargetVal = ! empty( $secMap[ $slug ] );
            if ( ! $existingTargetVal && '' !== $opt && ! SelectiveDbSync::isProtectedOption( $opt ) ) {
                $existingOpt       = SelectiveDbSync::dbGetOption( $targetDb, $opt, '' );
                $existingTargetVal = is_string( $existingOpt ) && '' !== trim( $existingOpt );
            }

            if ( 'fill_missing' === $mode && $existingTargetVal ) {
                $skipped[] = $label;
                continue;
            }

            $reg[ $slug ] = [
                'slug'     => $slug,
                'label'    => $label,
                'option'   => $opt,
                'constant' => (string) ( $item['constant'] ?? '' ),
                'note'     => sanitize_text_field( (string) ( $item['note'] ?? '' ) ),
                'policy'   => (string) ( $item['policy'] ?? 'sync' ),
            ];

            // Note: When pushing over Same-Server from Staging to Live, both sites typically have independent wp-config AUTH_KEY salts,
            // so target-side encryption is handled cleanly when target site runs locally (`$targetIsCurrentSite`), OR stored in linked `wp_option` / encrypted if salts match.
            if ( $targetIsCurrentSite ) {
                $secMap[ $slug ] = CryptoVault::encrypt( $val );
            } elseif ( '' !== $opt && ! SelectiveDbSync::isProtectedOption( $opt ) ) {
                SelectiveDbSync::dbUpsertOptionRaw( $targetDb, $opt, $val, 'no' );
            } else {
                // Create default safe option name on target so cross-server/cross-salt same-server always retains the key without salt mismatch.
                $fallbackOpt               = 'dbhdl_vault_val_' . $slug;
                $reg[ $slug ]['option']    = $fallbackOpt;
                SelectiveDbSync::dbUpsertOptionRaw( $targetDb, $fallbackOpt, $val, 'no' );
            }

            if ( '' !== $opt && ! SelectiveDbSync::isProtectedOption( $opt ) && $targetIsCurrentSite ) {
                SelectiveDbSync::dbUpsertOptionRaw( $targetDb, $opt, $val, 'no' );
            }

            $synced[] = $label . ' (' . CryptoVault::mask( $val ) . ')';
        }

        SelectiveDbSync::dbUpsertOptionRaw( $targetDb, self::OPTION_REGISTRY, maybe_serialize( $reg ), 'no' );
        if ( $targetIsCurrentSite ) {
            SelectiveDbSync::dbUpsertOptionRaw( $targetDb, self::OPTION_SECRETS, maybe_serialize( $secMap ), 'no' );
        }

        return [ 'synced' => $synced, 'skipped' => $skipped ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function getRegistry(): array {
        $raw = function_exists( 'get_option' ) ? get_option( self::OPTION_REGISTRY, [] ) : [];
        return is_array( $raw ) ? $raw : [];
    }

    /**
     * @return array<string, string>
     */
    private static function getRawEncryptedMap(): array {
        $raw = function_exists( 'get_option' ) ? get_option( self::OPTION_SECRETS, [] ) : [];
        return is_array( $raw ) ? $raw : [];
    }

    private static function storeSecretValue( string $slug, string $optionName, string $plaintext ): void {
        $secrets          = self::getRawEncryptedMap();
        $secrets[ $slug ] = CryptoVault::encrypt( $plaintext );
        update_option( self::OPTION_SECRETS, $secrets, false );

        if ( '' !== $optionName && ! SelectiveDbSync::isProtectedOption( $optionName ) ) {
            update_option( $optionName, $plaintext, false );
        }
    }
}
