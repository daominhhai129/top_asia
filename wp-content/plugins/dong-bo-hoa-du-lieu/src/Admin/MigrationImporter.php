<?php
declare(strict_types=1);

namespace DBHDL\Sync\Admin;

use DBHDL\Sync\Database\SecretsVaultManager;
use DBHDL\Sync\Plugin;
use DBHDL\Sync\Security\CryptoVault;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Optional one-click importer that migrates settings from legacy `proid-github-sync` options
 * without modifying or deleting the legacy plugin's stored options.
 */
final class MigrationImporter {

    public static function hasLegacySettings(): bool {
        if ( ! function_exists( 'get_option' ) ) {
            return false;
        }
        return '' !== trim( (string) get_option( 'proid_gh_repo', '' ) )
            || '' !== trim( (string) get_option( 'proid_gh_token', '' ) )
            || ! empty( get_option( 'proid_api_vault_registry', [] ) );
    }

    /**
     * Import legacy `proid-github-sync` repository, branch, token, webhook secret, and custom vault definitions.
     *
     * @return array<string, mixed>
     */
    public static function importFromLegacy(): array {
        if ( ! self::hasLegacySettings() ) {
            return [ 'ok' => false, 'error' => 'Không tìm thấy cấu hình từ plugin ProID Sync cũ trên site này.' ];
        }

        $patch = [];
        $repo  = trim( (string) get_option( 'proid_gh_repo', '' ) );
        $br    = trim( (string) get_option( 'proid_gh_branch', '' ) );
        $tok   = trim( (string) get_option( 'proid_gh_token', '' ) );
        $wh    = trim( (string) get_option( 'proid_gh_webhook_secret', '' ) );
        $aname = trim( (string) get_option( 'proid_gh_author_name', '' ) );
        $aem   = trim( (string) get_option( 'proid_gh_author_email', '' ) );

        if ( '' !== $repo ) {
            $patch['git_repo'] = $repo;
        }
        if ( '' !== $br ) {
            $patch['git_branch'] = $br;
        }
        if ( '' !== $tok ) {
            $patch['git_token_enc'] = CryptoVault::encrypt( $tok );
        }
        if ( '' !== $wh ) {
            $patch['webhook_secret_enc'] = CryptoVault::encrypt( $wh );
        }
        if ( '' !== $aname ) {
            $patch['git_author_name'] = $aname;
        }
        if ( '' !== $aem ) {
            $patch['git_author_email'] = $aem;
        }

        if ( ! empty( $patch ) ) {
            Plugin::updateSettings( $patch );
        }

        $importedServices = 0;
        $legacyCustom     = get_option( 'proid_api_vault_registry', [] );
        if ( is_array( $legacyCustom ) ) {
            foreach ( $legacyCustom as $slug => $svc ) {
                if ( ! is_array( $svc ) ) {
                    continue;
                }
                $opt = (string) ( $svc['option'] ?? '' );
                $val = '' !== $opt ? trim( (string) get_option( $opt, '' ) ) : '';
                $res = SecretsVaultManager::saveService(
                    [
                        'slug'     => (string) ( $svc['slug'] ?? $slug ),
                        'label'    => (string) ( $svc['label'] ?? $slug ),
                        'option'   => $opt,
                        'constant' => (string) ( $svc['constant'] ?? '' ),
                        'note'     => (string) ( $svc['note'] ?? '' ),
                        'secret'   => $val,
                    ]
                );
                if ( ! empty( $res['ok'] ) ) {
                    $importedServices++;
                }
            }
        }

        return [
            'ok'                => true,
            'imported_git'      => ! empty( $patch ),
            'imported_services' => $importedServices,
            'message'           => sprintf( 'Đã nhập cấu hình Git & %d dịch vụ từ cấu hình cũ sang Đồng Bộ Hóa Dữ Liệu (đã mã hóa an toàn).', $importedServices ),
        ];
    }
}
