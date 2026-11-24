<?php
declare(strict_types=1);

namespace DBHDL\Sync\Transport;

use DBHDL\Sync\Contracts\TransportInterface;
use DBHDL\Sync\Database\SecretsVaultManager;
use DBHDL\Sync\Database\SelectiveDbSync;
use DBHDL\Sync\Diff\DiffEngine;
use DBHDL\Sync\Discovery\ScopeScanner;
use DBHDL\Sync\Jobs\JobStateStore;
use DBHDL\Sync\Plugin;
use DBHDL\Sync\Security\CryptoVault;
use DBHDL\Sync\Security\PathGuard;
use DBHDL\Sync\Security\RequestSigner;
use ZipArchive;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * HTTPS Site-to-Site Remote REST Pairing Transport:
 * Enables pushing directly from Localhost (LocalWP/Laragon/Docker) -> Staging OR Staging -> Production
 * across completely different servers using signed HMAC-SHA256 chunked delta ZIP transfer.
 */
final class RemoteRestTransport implements TransportInterface {

    public function getId(): string {
        return 'remote_pairing';
    }

    /**
     * Decode a base64url Pairing Token string into connection components.
     *
     * @return array<string, string>|null
     */
    public static function decodePairingToken( string $token ): ?array {
        $clean = trim( $token );
        if ( '' === $clean ) {
            return null;
        }
        $pad = strlen( $clean ) % 4;
        if ( $pad > 0 ) {
            $clean .= str_repeat( '=', 4 - $pad );
        }
        $raw = base64_decode( strtr( $clean, '-_', '+/' ), true );
        if ( false === $raw ) {
            return null;
        }
        $data = json_decode( $raw, true );
        if ( ! is_array( $data ) || empty( $data['rest_base'] ) || empty( $data['pair_id'] ) || empty( $data['secret'] ) ) {
            return null;
        }
        return [
            'site_url'  => rtrim( (string) ( $data['site_url'] ?? '' ), '/' ),
            'rest_base' => rtrim( (string) $data['rest_base'], '/' ),
            'pair_id'   => (string) $data['pair_id'],
            'secret'    => (string) $data['secret'],
            'env_role'  => (string) ( $data['env_role'] ?? '' ),
        ];
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function testConnection( array $config = [] ): array {
        $pair = $this->resolvePairing( $config );
        if ( null === $pair ) {
            return [ 'ok' => false, 'error' => 'Pairing Token không hợp lệ hoặc chưa được cấu hình.' ];
        }

        $res = $this->signedRequest( $pair, 'POST', '/pairing/ping', [ 'from_url' => home_url() ] );
        if ( empty( $res['ok'] ) ) {
            return $res;
        }

        $d = $res['data'];
        return [
            'ok'        => true,
            'site_url'  => (string) ( $d['site_url'] ?? $pair['site_url'] ),
            'site_name' => (string) ( $d['site_name'] ?? '' ),
            'env_role'  => (string) ( $d['env_role'] ?? '' ),
            'writable'  => ! empty( $d['writable'] ),
            'message'   => sprintf(
                'Kết nối thành công tới %s (%s | Vai trò: %s).',
                (string) ( $d['site_name'] ?? $pair['site_url'] ),
                (string) ( $d['site_url'] ?? $pair['site_url'] ),
                strtoupper( (string) ( $d['env_role'] ?? 'unknown' ) )
            ),
        ];
    }

    /**
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    public function previewDiff( array $request ): array {
        $pair = $this->resolvePairing( $request );
        if ( null === $pair ) {
            return [ 'ok' => false, 'error' => 'Chưa cấu hình Remote Pairing Token hợp lệ.' ];
        }

        $includeMedia = ! empty( $request['scope']['include_media'] );
        $prefixes     = ScopeScanner::resolveScopePrefixes( (array) ( $request['scope'] ?? [] ) );

        $fileDiff = [
            'summary' => [ 'added' => 0, 'modified' => 0, 'deleted' => 0, 'unchanged' => 0, 'transfer_bytes' => 0 ],
            'items'   => [],
        ];

        $remoteInfo = [];
        if ( ! empty( $prefixes ) ) {
            $manifestRes = $this->signedRequest(
                $pair,
                'POST',
                '/pairing/manifest',
                [
                    'prefixes'      => $prefixes,
                    'include_media' => $includeMedia,
                ],
                45
            );
            if ( empty( $manifestRes['ok'] ) ) {
                return $manifestRes;
            }
            $remoteTree = (array) ( $manifestRes['data']['files'] ?? [] );
            $remoteInfo = [
                'site_url' => (string) ( $manifestRes['data']['site_url'] ?? $pair['site_url'] ),
                'env_role' => (string) ( $manifestRes['data']['env_role'] ?? '' ),
            ];
            $localTree  = DiffEngine::scanDirectoryTree( ABSPATH, $prefixes, [ 'include_media' => $includeMedia ] );
            $fileDiff   = DiffEngine::compareTrees( $localTree, $remoteTree, $prefixes, true );
        }

        $dbSummary  = SelectiveDbSync::previewSelection( (array) ( $request['db_scope'] ?? [] ) );
        $vaultItems = ! empty( $request['sync_vault'] )
            ? array_map( static fn( array $it ): string => $it['label'], SecretsVaultManager::exportTransferItems( (string) ( $request['vault_mode'] ?? 'fill_missing' ) ) )
            : [];

        return [
            'ok'          => true,
            'target_info' => $remoteInfo,
            'summary'     => $fileDiff['summary'],
            'items'       => $fileDiff['items'],
            'db_summary'  => $dbSummary,
            'vault_items' => $vaultItems,
        ];
    }

    /**
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    public function execute( array $request ): array {
        if ( function_exists( 'set_time_limit' ) ) {
            @set_time_limit( 300 );
        }
        if ( ! class_exists( 'ZipArchive' ) ) {
            return [ 'ok' => false, 'error' => 'Máy chủ PHP chưa bật ZipArchive.' ];
        }

        $pair = $this->resolvePairing( $request );
        if ( null === $pair ) {
            return [ 'ok' => false, 'error' => 'Chưa cấu hình Remote Pairing Token hợp lệ.' ];
        }

        $includeMedia  = ! empty( $request['scope']['include_media'] );
        $mirrorDelete  = ! empty( $request['mirror_delete'] );
        $excludedFiles = array_flip( array_map( 'strval', (array) ( $request['excluded_files'] ?? [] ) ) );
        $prefixes      = ScopeScanner::resolveScopePrefixes( (array) ( $request['scope'] ?? [] ) );

        $filesToSend   = [];
        $deletedToSend = [];
        $remoteUrl     = $pair['site_url'];

        if ( ! empty( $prefixes ) ) {
            $manifestRes = $this->signedRequest(
                $pair,
                'POST',
                '/pairing/manifest',
                [
                    'prefixes'      => $prefixes,
                    'include_media' => $includeMedia,
                ],
                45
            );
            if ( empty( $manifestRes['ok'] ) ) {
                return $manifestRes;
            }
            $remoteUrl  = (string) ( $manifestRes['data']['site_url'] ?? $remoteUrl );
            $remoteTree = (array) ( $manifestRes['data']['files'] ?? [] );
            $localTree  = DiffEngine::scanDirectoryTree( ABSPATH, $prefixes, [ 'include_media' => $includeMedia ] );
            $diff       = DiffEngine::compareTrees( $localTree, $remoteTree, $prefixes, $mirrorDelete );

            foreach ( $diff['items'] as $item ) {
                $rel = (string) $item['path'];
                if ( isset( $excludedFiles[ $rel ] ) ) {
                    continue;
                }
                if ( in_array( $item['status'], [ 'added', 'modified' ], true ) ) {
                    $filesToSend[] = $rel;
                } elseif ( 'deleted' === $item['status'] && $mirrorDelete ) {
                    $deletedToSend[] = $rel;
                }
            }
        }

        $dbSelection = (array) ( $request['db_scope'] ?? [] );
        $hasDbSync   = ! empty( $dbSelection['post_types'] ) || ! empty( $dbSelection['include_menus'] ) || ! empty( $dbSelection['include_theme_mods'] ) || ! empty( $dbSelection['options'] );
        $dbPackage   = $hasDbSync ? SelectiveDbSync::exportPackage( $dbSelection ) : null;
        $vaultItems  = ! empty( $request['sync_vault'] )
            ? SecretsVaultManager::exportTransferItems( (string) ( $request['vault_mode'] ?? 'fill_missing' ) )
            : [];

        if ( empty( $filesToSend ) && empty( $deletedToSend ) && null === $dbPackage && empty( $vaultItems ) ) {
            return [ 'ok' => true, 'no_changes' => true, 'message' => 'Không có thay đổi nào cần gửi sang máy chủ đích.' ];
        }

        // Build clean delta ZIP archive in temp directory.
        $tmpZip = ( function_exists( 'wp_tempnam' ) ? wp_tempnam( 'dbhdl_push_' ) : tempnam( sys_get_temp_dir(), 'dbhdl_push_' ) ) . '.zip';
        $zip    = new ZipArchive();
        if ( true !== $zip->open( $tmpZip, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
            return [ 'ok' => false, 'error' => 'Không tạo được gói ZIP truyền tải.' ];
        }

        foreach ( $filesToSend as $rel ) {
            $abs = PathGuard::resolveUnderBase( ABSPATH, $rel );
            if ( null !== $abs && is_file( $abs ) ) {
                $zip->addFile( $abs, $rel );
            }
        }
        $zip->close();

        // Stream delta ZIP in chunks to Target `/pairing/upload-chunk`.
        $sessionId  = CryptoVault::randomHex( 12 );
        $settings   = Plugin::getSettings();
        $chunkBytes = max( 262144, min( 4194304, ( (int) ( $settings['chunk_size_kb'] ?? 1536 ) ) * 1024 ) );
        $totalSize  = is_file( $tmpZip ) ? (int) filesize( $tmpZip ) : 0;
        $zipSha256  = is_file( $tmpZip ) ? (string) hash_file( 'sha256', $tmpZip ) : '';

        if ( $totalSize > 0 ) {
            $fh = @fopen( $tmpZip, 'rb' );
            if ( ! $fh ) {
                @unlink( $tmpZip );
                return [ 'ok' => false, 'error' => 'Không đọc được gói ZIP tạm để upload.' ];
            }
            $chunkIdx    = 0;
            $totalChunks = (int) ceil( $totalSize / $chunkBytes );
            while ( ! feof( $fh ) ) {
                $buf = fread( $fh, $chunkBytes );
                if ( false === $buf || '' === $buf ) {
                    break;
                }
                $upRes = $this->signedRequest(
                    $pair,
                    'POST',
                    '/pairing/upload-chunk',
                    [
                        'session_id'   => $sessionId,
                        'chunk_index'  => $chunkIdx,
                        'total_chunks' => $totalChunks,
                        'data_b64'     => base64_encode( $buf ),
                    ],
                    60
                );
                if ( empty( $upRes['ok'] ) ) {
                    fclose( $fh );
                    @unlink( $tmpZip );
                    return $upRes;
                }
                $chunkIdx++;
            }
            fclose( $fh );
        }
        @unlink( $tmpZip );

        // Commit bundle on Target (Target creates Snapshot, applies ZIP, imports DB & Vault, flushes caches).
        $commitRes = $this->signedRequest(
            $pair,
            'POST',
            '/pairing/commit',
            [
                'session_id'       => $sessionId,
                'has_zip'          => $totalSize > 0,
                'zip_sha256'       => $zipSha256,
                'prefixes'         => $prefixes,
                'mirror_delete'    => $mirrorDelete,
                'deleted_files'    => $deletedToSend,
                'include_media'    => $includeMedia,
                'maintenance_mode' => ! empty( $request['maintenance_mode'] ) || ! empty( $settings['enable_maintenance_mode'] ),
                'search_replace'   => ! isset( $request['search_replace'] ) || ! empty( $request['search_replace'] ),
                'db_package'       => $dbPackage,
                'vault_items'      => $vaultItems,
                'vault_mode'       => ( 'overwrite' === ( $request['vault_mode'] ?? '' ) ) ? 'overwrite' : 'fill_missing',
                'source_url'       => home_url(),
            ],
            180
        );

        $slot   = ( 'production' === ( $request['target_slot'] ?? '' ) ) ? 'production' : 'staging';
        $action = ( 'production' === $slot ) ? 'deploy_local_to_prod' : 'deploy_remote';

        if ( empty( $commitRes['ok'] ) ) {
            JobStateStore::appendLog(
                [
                    'action'    => $action,
                    'direction' => home_url() . ' → ' . $remoteUrl,
                    'transport' => 'remote_pairing',
                    'status'    => 'error',
                    'summary'   => (string) ( $commitRes['error'] ?? 'Lỗi truyền tải Remote Pairing' ),
                ]
            );
            return $commitRes;
        }

        $d       = (array) $commitRes['data'];
        $dbStats = (array) ( $d['db_stats'] ?? [] );
        $dbTotal = (int) ( $dbStats['posts_updated'] ?? 0 )
            + (int) ( $dbStats['posts_created'] ?? 0 )
            + (int) ( $dbStats['menus_synced'] ?? 0 )
            + (int) ( $dbStats['options_synced'] ?? 0 );

        JobStateStore::appendLog(
            [
                'action'    => $action,
                'direction' => home_url() . ' → ' . $remoteUrl,
                'transport' => 'remote_pairing',
                'status'    => 'success',
                'summary'   => sprintf(
                    'Đẩy thành công sang %s: %d tệp (%d xóa), %d bản ghi DB. Snapshot: %s',
                    $remoteUrl,
                    (int) ( $d['updated_count'] ?? 0 ),
                    (int) ( $d['deleted_count'] ?? 0 ),
                    $dbTotal,
                    (string) ( $d['snapshot'] ?? '' )
                ),
                'snapshot'  => (string) ( $d['snapshot'] ?? '' ),
                'files_cnt' => (int) ( $d['updated_count'] ?? 0 ) + (int) ( $d['deleted_count'] ?? 0 ),
                'db_cnt'    => $dbTotal,
            ]
        );

        return array_merge( [ 'ok' => true ], $d );
    }

    /**
     * Request a remote paired site (Staging or Production) to preview GitHub Pull Diff.
     *
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    public function remoteGitDiff( array $request ): array {
        $pair = $this->resolvePairing( $request );
        if ( null === $pair ) {
            return [ 'ok' => false, 'error' => 'Chưa cấu hình Remote Pairing Token cho môi trường đích này.' ];
        }

        $body = $this->buildRemoteGitPayload( $request );
        $res  = $this->signedRequest( $pair, 'POST', '/pairing/git-diff', $body, 60 );
        if ( empty( $res['ok'] ) ) {
            return $res;
        }
        return array_merge( [ 'ok' => true, 'remote_site' => $pair['site_url'] ], (array) $res['data'] );
    }

    /**
     * Trigger a remote paired site (Staging or Production) to pull directly from GitHub with Snapshot protection.
     *
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    public function remoteGitPull( array $request ): array {
        $pair = $this->resolvePairing( $request );
        if ( null === $pair ) {
            return [ 'ok' => false, 'error' => 'Chưa cấu hình Remote Pairing Token cho môi trường đích này.' ];
        }

        $body = $this->buildRemoteGitPayload( $request );
        $res  = $this->signedRequest( $pair, 'POST', '/pairing/git-pull', $body, 180 );
        if ( empty( $res['ok'] ) ) {
            return $res;
        }

        $d = (array) $res['data'];
        JobStateStore::appendLog(
            [
                'action'    => 'remote_git_pull',
                'direction' => 'GitHub → ' . $pair['site_url'],
                'transport' => 'remote_pairing',
                'status'    => 'success',
                'summary'   => sprintf(
                    'Điều khiển %s kéo từ GitHub: %d tệp cập nhật, %d tệp xóa. Snapshot: %s',
                    $pair['site_url'],
                    (int) ( $d['updated_count'] ?? 0 ),
                    (int) ( $d['deleted_count'] ?? 0 ),
                    (string) ( $d['snapshot'] ?? '' )
                ),
                'snapshot'  => (string) ( $d['snapshot'] ?? '' ),
                'files_cnt' => (int) ( $d['updated_count'] ?? 0 ) + (int) ( $d['deleted_count'] ?? 0 ),
            ]
        );

        return array_merge( [ 'ok' => true, 'remote_site' => $pair['site_url'] ], $d );
    }

    /**
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    private function buildRemoteGitPayload( array $request ): array {
        $s = Plugin::getSettings();
        return [
            'scope'            => (array) ( $request['scope'] ?? [] ),
            'prefixes'         => ScopeScanner::resolveScopePrefixes( (array) ( $request['scope'] ?? [] ) ),
            'mirror_delete'    => ! empty( $request['mirror_delete'] ),
            'excluded_files'   => (array) ( $request['excluded_files'] ?? [] ),
            'maintenance_mode' => ! empty( $request['maintenance_mode'] ),
            'repo'             => (string) ( $request['repo'] ?? $s['git_repo'] ?? '' ),
            'branch'           => (string) ( $request['branch'] ?? $s['git_branch'] ?? 'main' ),
            'api_base'         => (string) ( $request['api_base'] ?? $s['git_api_base'] ?? 'https://api.github.com' ),
            'token'            => (string) ( $request['token'] ?? Plugin::getSecretSetting( 'git_token_enc' ) ),
        ];
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, string>|null
     */
    private function resolvePairing( array $overrides ): ?array {
        $raw = trim( (string) ( $overrides['pairing_token'] ?? '' ) );
        if ( '' === $raw ) {
            $slot   = ( 'production' === ( $overrides['target_slot'] ?? '' ) ) ? 'production' : 'staging';
            $encKey = ( 'production' === $slot ) ? 'prod_pairing_token_enc' : 'target_pairing_token_enc';
            $raw    = Plugin::getSecretSetting( $encKey );
        }
        return self::decodePairingToken( $raw );
    }

    /**
     * @param array<string, string> $pair
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function signedRequest( array $pair, string $method, string $route, array $body, int $timeout = 25 ): array {
        $rawBody = (string) wp_json_encode( $body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
        $headers = RequestSigner::signHeaders( $pair['pair_id'], $pair['secret'], $method, $route, $rawBody );
        $headers['Content-Type'] = 'application/json';

        $url = rtrim( $pair['rest_base'], '/' ) . '/' . ltrim( $route, '/' );
        $res = wp_remote_request(
            $url,
            [
                'method'  => $method,
                'timeout' => $timeout,
                'headers' => $headers,
                'body'    => $rawBody,
            ]
        );

        if ( is_wp_error( $res ) ) {
            return [ 'ok' => false, 'error' => 'Lỗi kết nối tới site đích: ' . $res->get_error_message() ];
        }

        $code = (int) wp_remote_retrieve_response_code( $res );
        $data = json_decode( (string) wp_remote_retrieve_body( $res ), true );
        if ( $code >= 200 && $code < 300 && is_array( $data ) ) {
            return [ 'ok' => true, 'data' => $data ];
        }

        $msg = is_array( $data ) && ! empty( $data['message'] )
            ? (string) $data['message']
            : ( is_array( $data ) && ! empty( $data['error'] ) ? (string) $data['error'] : ( 'HTTP ' . $code ) );
        return [ 'ok' => false, 'code' => $code, 'error' => $msg ];
    }
}
