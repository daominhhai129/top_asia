<?php
declare(strict_types=1);

namespace DBHDL\Sync\Transport;

use DBHDL\Sync\Contracts\TransportInterface;
use DBHDL\Sync\Diff\DiffEngine;
use DBHDL\Sync\Discovery\ScopeScanner;
use DBHDL\Sync\Jobs\JobStateStore;
use DBHDL\Sync\Plugin;
use DBHDL\Sync\Security\PathGuard;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 2-Way GitHub REST API v3 Transport:
 * - Pull: downloads branch zipball, computes pre-pull Diff, snapshots current files, applies safely.
 * - Push: compares local Git blob SHA-1 against remote recursive tree, uploads ONLY changed blobs
 *   (both text and binary via Base64 Blob API), supports optional Mirror Delete within selected scopes.
 */
final class GitHubTransport implements TransportInterface {

    public function getId(): string {
        return 'github';
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function testConnection( array $config = [] ): array {
        $ctx = $this->resolveConfig( $config );
        if ( '' === $ctx['repo'] || '' === $ctx['token'] ) {
            return [ 'ok' => false, 'error' => 'Vui lòng nhập đầy đủ Repository (owner/repo) và Access Token.' ];
        }

        $res = $this->apiRequest( 'GET', $ctx['api_base'] . '/repos/' . $ctx['repo'], $ctx['token'] );
        if ( empty( $res['ok'] ) ) {
            return $res;
        }

        $body = $res['data'];
        return [
            'ok'             => true,
            'full_name'      => (string) ( $body['full_name'] ?? $ctx['repo'] ),
            'private'        => ! empty( $body['private'] ),
            'default_branch' => (string) ( $body['default_branch'] ?? 'main' ),
            'permissions'    => $body['permissions'] ?? [],
            'message'        => sprintf(
                'Kết nối thành công tới %s (%s | nhánh mặc định: %s).',
                (string) ( $body['full_name'] ?? $ctx['repo'] ),
                ! empty( $body['private'] ) ? 'Private' : 'Public',
                (string) ( $body['default_branch'] ?? 'main' )
            ),
        ];
    }

    /**
     * List available repository branches.
     *
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function listBranches( array $config = [] ): array {
        $ctx = $this->resolveConfig( $config );
        if ( '' === $ctx['repo'] || '' === $ctx['token'] ) {
            return [ 'ok' => false, 'error' => 'Thiếu thông tin Repo hoặc Token.' ];
        }

        $res = $this->apiRequest( 'GET', $ctx['api_base'] . '/repos/' . $ctx['repo'] . '/branches?per_page=100', $ctx['token'] );
        if ( empty( $res['ok'] ) ) {
            return $res;
        }

        $branches = [];
        foreach ( (array) $res['data'] as $b ) {
            if ( ! empty( $b['name'] ) ) {
                $branches[] = [
                    'name'      => (string) $b['name'],
                    'protected' => ! empty( $b['protected'] ),
                ];
            }
        }
        return [ 'ok' => true, 'branches' => $branches ];
    }

    /**
     * Fetch latest commit metadata on the selected branch.
     *
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function getLatestCommit( array $config = [] ): array {
        $ctx = $this->resolveConfig( $config );
        if ( '' === $ctx['repo'] || '' === $ctx['token'] ) {
            return [ 'ok' => false, 'error' => 'Chưa cấu hình GitHub Repo / Token.' ];
        }

        $url = $ctx['api_base'] . '/repos/' . $ctx['repo'] . '/commits/' . rawurlencode( $ctx['branch'] );
        $res = $this->apiRequest( 'GET', $url, $ctx['token'] );
        if ( empty( $res['ok'] ) ) {
            return $res;
        }

        $d = $res['data'];
        return [
            'ok'       => true,
            'sha'      => substr( (string) ( $d['sha'] ?? '' ), 0, 7 ),
            'full_sha' => (string) ( $d['sha'] ?? '' ),
            'message'  => (string) ( $d['commit']['message'] ?? '' ),
            'author'   => (string) ( $d['commit']['author']['name'] ?? 'Unknown' ),
            'date'     => gmdate( 'd/m/Y H:i:s', strtotime( (string) ( $d['commit']['author']['date'] ?? 'now' ) ) ),
            'html_url' => (string) ( $d['html_url'] ?? '' ),
        ];
    }

    /**
     * Preview Pull or Push Diff without mutating local files or remote branch.
     *
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    public function previewDiff( array $request ): array {
        $direction  = ( 'push' === ( $request['direction'] ?? 'pull' ) ) ? 'push' : 'pull';
        $ctx        = $this->resolveConfig( $request );
        $prefixes   = $this->resolvePrefixes( $request );
        $targetRoot = $this->resolveTargetRoot( $request );

        if ( '' === $ctx['repo'] || '' === $ctx['token'] ) {
            return [ 'ok' => false, 'error' => 'Vui lòng cấu hình GitHub Repo & Token trước.' ];
        }
        if ( empty( $prefixes ) ) {
            return [ 'ok' => false, 'error' => 'Vui lòng chọn ít nhất một phạm vi (Plugin, Theme hoặc Thư mục).' ];
        }

        if ( 'pull' === $direction ) {
            $zipRes = $this->downloadZipball( $ctx );
            if ( empty( $zipRes['ok'] ) ) {
                return $zipRes;
            }
            $zipPath = (string) $zipRes['zip_path'];
            $diff    = DiffEngine::inspectZipAgainstDirectory(
                $zipPath,
                $targetRoot,
                $prefixes,
                $ctx['subpath_map'],
                true,
                [ 'include_media' => ! empty( $request['scope']['include_media'] ) ]
            );
            @unlink( $zipPath );

            if ( empty( $diff['ok'] ) ) {
                return $diff;
            }
            return [
                'ok'         => true,
                'direction'  => 'pull',
                'commit_sha' => (string) ( $zipRes['commit_sha'] ?? '' ),
                'summary'    => $diff['summary'],
                'items'      => $diff['items'],
            ];
        }

        return $this->computePushPlan( $ctx, $prefixes, $request );
    }

    /**
     * Execute GitHub Pull or Push.
     *
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    public function execute( array $request ): array {
        $direction = ( 'push' === ( $request['direction'] ?? 'pull' ) ) ? 'push' : 'pull';
        return 'push' === $direction
            ? $this->executePush( $request )
            : $this->executePull( $request );
    }

    /**
     * Pull branch zipball from GitHub and apply selected scopes with pre-pull Snapshot.
     *
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    public function executePull( array $request ): array {
        if ( function_exists( 'set_time_limit' ) ) {
            @set_time_limit( 300 );
        }

        $ctx        = $this->resolveConfig( $request );
        $prefixes   = $this->resolvePrefixes( $request );
        $targetRoot = $this->resolveTargetRoot( $request );

        if ( '' === $ctx['repo'] || '' === $ctx['token'] ) {
            return [ 'ok' => false, 'error' => 'Chưa cấu hình GitHub Repo hoặc Token.' ];
        }
        if ( empty( $prefixes ) ) {
            return [ 'ok' => false, 'error' => 'Không có phạm vi đồng bộ nào được chọn.' ];
        }

        $zipRes = $this->downloadZipball( $ctx );
        if ( empty( $zipRes['ok'] ) ) {
            return $zipRes;
        }
        $zipPath = (string) $zipRes['zip_path'];

        $res = ZipApplyHelper::applyZip(
            $zipPath,
            $targetRoot,
            $prefixes,
            $ctx['subpath_map'],
            [
                'strip_first_segment' => true,
                'include_media'       => ! empty( $request['scope']['include_media'] ),
                'mirror_delete'       => ! empty( $request['mirror_delete'] ),
                'excluded_files'      => $request['excluded_files'] ?? [],
                'maintenance_mode'    => ! empty( $request['maintenance_mode'] ),
                'trigger'             => (string) ( $request['trigger'] ?? 'github_pull' ),
                'direction'           => (string) ( $request['direction_label'] ?? ( 'GitHub (' . $ctx['branch'] . ') → Web' ) ),
            ]
        );
        @unlink( $zipPath );

        if ( empty( $res['ok'] ) ) {
            JobStateStore::appendLog(
                [
                    'action'    => 'git_pull',
                    'direction' => 'GitHub → Web',
                    'transport' => 'github',
                    'status'    => 'error',
                    'summary'   => (string) ( $res['error'] ?? 'Lỗi Pull từ GitHub' ),
                ]
            );
            return $res;
        }

        JobStateStore::appendLog(
            [
                'action'    => 'git_pull',
                'direction' => $ctx['repo'] . ':' . $ctx['branch'] . ' → Web',
                'transport' => 'github',
                'status'    => 'success',
                'summary'   => sprintf(
                    'Kéo từ GitHub (%s): %d tệp cập nhật, %d tệp xóa, Snapshot: %s',
                    (string) ( $zipRes['commit_sha'] ?: $ctx['branch'] ),
                    (int) $res['updated_count'],
                    (int) $res['deleted_count'],
                    (string) $res['snapshot']
                ),
                'snapshot'  => (string) $res['snapshot'],
                'files_cnt' => (int) $res['updated_count'] + (int) $res['deleted_count'],
            ]
        );

        $res['commit_sha'] = (string) ( $zipRes['commit_sha'] ?? '' );
        return $res;
    }

    /**
     * Push changed local WP files to GitHub using Blob API + Tree API (supports binary & mirror delete).
     *
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    public function executePush( array $request ): array {
        if ( function_exists( 'set_time_limit' ) ) {
            @set_time_limit( 300 );
        }

        $ctx      = $this->resolveConfig( $request );
        $prefixes = $this->resolvePrefixes( $request );

        if ( '' === $ctx['repo'] || '' === $ctx['token'] ) {
            return [ 'ok' => false, 'error' => 'Chưa cấu hình GitHub Repo hoặc Token.' ];
        }
        if ( empty( $prefixes ) ) {
            return [ 'ok' => false, 'error' => 'Chưa chọn phạm vi để đẩy lên GitHub.' ];
        }

        $plan = $this->computePushPlan( $ctx, $prefixes, $request );
        if ( empty( $plan['ok'] ) ) {
            return $plan;
        }

        $toUpload    = (array) $plan['upload_list'];
        $toDelete    = (array) $plan['delete_list'];
        $mirrorMode  = ! empty( $request['mirror_delete'] );
        $commitMsg   = sanitize_text_field( (string) ( $request['commit_msg'] ?? '' ) );
        if ( '' === $commitMsg ) {
            $commitMsg = 'sync(wp): Cập nhật từ Đồng Bộ Hóa Dữ Liệu lúc ' . current_time( 'd/m/Y H:i' );
        }

        if ( empty( $toUpload ) && ( ! $mirrorMode || empty( $toDelete ) ) ) {
            return [
                'ok'           => true,
                'no_changes'   => true,
                'pushed_count' => 0,
                'message'      => 'Không có tệp nào thay đổi so với nhánh GitHub hiện tại.',
            ];
        }

        $treeItems = [];
        foreach ( $toUpload as $entry ) {
            $absPath = PathGuard::resolveUnderBase( ABSPATH, (string) $entry['wp_rel'] );
            if ( null === $absPath || ! is_file( $absPath ) ) {
                continue;
            }
            $rawContent = @file_get_contents( $absPath );
            if ( false === $rawContent ) {
                continue;
            }
            $blobRes = $this->apiRequest(
                'POST',
                $ctx['api_base'] . '/repos/' . $ctx['repo'] . '/git/blobs',
                $ctx['token'],
                [
                    'content'  => base64_encode( $rawContent ),
                    'encoding' => 'base64',
                ],
                30
            );
            if ( empty( $blobRes['ok'] ) || empty( $blobRes['data']['sha'] ) ) {
                return [
                    'ok'    => false,
                    'error' => 'Lỗi tạo Git Blob cho ' . $entry['repo_path'] . ': ' . ( $blobRes['error'] ?? 'Unknown' ),
                ];
            }
            $treeItems[] = [
                'path' => (string) $entry['repo_path'],
                'mode' => '100644',
                'type' => 'blob',
                'sha'  => (string) $blobRes['data']['sha'],
            ];
        }

        if ( $mirrorMode ) {
            // Keep all remote blobs NOT marked for deletion or overwritten.
            $overwrittenOrDeleted = [];
            foreach ( $toUpload as $u ) {
                $overwrittenOrDeleted[ (string) $u['repo_path'] ] = true;
            }
            foreach ( $toDelete as $d ) {
                $overwrittenOrDeleted[ (string) $d ] = true;
            }
            foreach ( (array) $plan['remote_blobs'] as $rPath => $rBlob ) {
                if ( ! isset( $overwrittenOrDeleted[ $rPath ] ) ) {
                    $treeItems[] = [
                        'path' => (string) $rPath,
                        'mode' => (string) ( $rBlob['mode'] ?? '100644' ),
                        'type' => 'blob',
                        'sha'  => (string) $rBlob['sha'],
                    ];
                }
            }
            $treePayload = [ 'tree' => $treeItems ];
        } else {
            $treePayload = [
                'base_tree' => (string) $plan['base_tree_sha'],
                'tree'      => $treeItems,
            ];
        }

        $treeRes = $this->apiRequest(
            'POST',
            $ctx['api_base'] . '/repos/' . $ctx['repo'] . '/git/trees',
            $ctx['token'],
            $treePayload,
            60
        );
        if ( empty( $treeRes['ok'] ) || empty( $treeRes['data']['sha'] ) ) {
            return [ 'ok' => false, 'error' => 'Lỗi tạo Git Tree: ' . ( $treeRes['error'] ?? 'Unknown' ) ];
        }

        $authorName  = '' !== $ctx['author_name'] ? $ctx['author_name'] : 'WP Data Sync';
        $authorEmail = '' !== $ctx['author_email'] ? $ctx['author_email'] : 'sync@localhost';

        $commitRes = $this->apiRequest(
            'POST',
            $ctx['api_base'] . '/repos/' . $ctx['repo'] . '/git/commits',
            $ctx['token'],
            [
                'message' => $commitMsg,
                'tree'    => (string) $treeRes['data']['sha'],
                'parents' => [ (string) $plan['head_sha'] ],
                'author'  => [
                    'name'  => $authorName,
                    'email' => $authorEmail,
                    'date'  => gmdate( 'Y-m-d\TH:i:s\Z' ),
                ],
            ],
            30
        );
        if ( empty( $commitRes['ok'] ) || empty( $commitRes['data']['sha'] ) ) {
            return [ 'ok' => false, 'error' => 'Lỗi tạo Git Commit: ' . ( $commitRes['error'] ?? 'Unknown' ) ];
        }

        $newSha = (string) $commitRes['data']['sha'];
        $refRes = $this->apiRequest(
            'PATCH',
            $ctx['api_base'] . '/repos/' . $ctx['repo'] . '/git/refs/heads/' . rawurlencode( $ctx['branch'] ),
            $ctx['token'],
            [
                'sha'   => $newSha,
                'force' => false,
            ],
            30
        );
        if ( empty( $refRes['ok'] ) ) {
            return [ 'ok' => false, 'error' => 'Lỗi cập nhật nhánh Git: ' . ( $refRes['error'] ?? 'Unknown' ) ];
        }

        $pushedCount  = count( $toUpload );
        $deletedCount = $mirrorMode ? count( $toDelete ) : 0;
        $commitUrl    = 'https://github.com/' . $ctx['repo'] . '/commit/' . $newSha;

        JobStateStore::appendLog(
            [
                'action'    => 'git_push',
                'direction' => 'Web → ' . $ctx['repo'] . ':' . $ctx['branch'],
                'transport' => 'github',
                'status'    => 'success',
                'summary'   => sprintf( 'Đẩy lên GitHub commit %s (%d tệp lên, %d xóa).', substr( $newSha, 0, 7 ), $pushedCount, $deletedCount ),
                'files_cnt' => $pushedCount + $deletedCount,
            ]
        );

        return [
            'ok'            => true,
            'commit_sha'    => $newSha,
            'short_sha'     => substr( $newSha, 0, 7 ),
            'commit_url'    => $commitUrl,
            'pushed_count'  => $pushedCount,
            'deleted_count' => $deletedCount,
        ];
    }

    /**
     * Compare local selected scopes against remote Git tree using exact Git Blob SHA-1.
     *
     * @param array<string, mixed> $ctx
     * @param array<int, string> $prefixes
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    private function computePushPlan( array $ctx, array $prefixes, array $request ): array {
        $refRes = $this->apiRequest(
            'GET',
            $ctx['api_base'] . '/repos/' . $ctx['repo'] . '/git/ref/heads/' . rawurlencode( $ctx['branch'] ),
            $ctx['token']
        );
        if ( empty( $refRes['ok'] ) || empty( $refRes['data']['object']['sha'] ) ) {
            return [ 'ok' => false, 'error' => 'Không đọc được nhánh Git "' . $ctx['branch'] . '": ' . ( $refRes['error'] ?? '' ) ];
        }
        $headSha = (string) $refRes['data']['object']['sha'];

        $commitRes = $this->apiRequest( 'GET', $ctx['api_base'] . '/repos/' . $ctx['repo'] . '/git/commits/' . $headSha, $ctx['token'] );
        if ( empty( $commitRes['ok'] ) || empty( $commitRes['data']['tree']['sha'] ) ) {
            return [ 'ok' => false, 'error' => 'Không đọc được base tree SHA.' ];
        }
        $baseTreeSha = (string) $commitRes['data']['tree']['sha'];

        $treeRes = $this->apiRequest( 'GET', $ctx['api_base'] . '/repos/' . $ctx['repo'] . '/git/trees/' . $baseTreeSha . '?recursive=1', $ctx['token'], null, 45 );
        if ( empty( $treeRes['ok'] ) ) {
            return $treeRes;
        }

        $remoteBlobs = [];
        foreach ( (array) ( $treeRes['data']['tree'] ?? [] ) as $node ) {
            if ( 'blob' === ( $node['type'] ?? '' ) && ! empty( $node['path'] ) ) {
                $remoteBlobs[ (string) $node['path'] ] = [
                    'sha'  => (string) $node['sha'],
                    'mode' => (string) ( $node['mode'] ?? '100644' ),
                    'size' => (int) ( $node['size'] ?? 0 ),
                ];
            }
        }

        $includeMedia  = ! empty( $request['scope']['include_media'] );
        $excludedFiles = array_flip( array_map( 'strval', (array) ( $request['excluded_files'] ?? [] ) ) );
        $localTree     = DiffEngine::scanDirectoryTree( ABSPATH, $prefixes, [ 'include_media' => $includeMedia ] );

        $items       = [];
        $uploadList  = [];
        $deleteList  = [];
        $added       = 0;
        $modified    = 0;
        $deleted     = 0;
        $unchanged   = 0;
        $bytesTotal  = 0;
        $seenRepoSet = [];

        foreach ( $localTree as $wpRel => $meta ) {
            if ( isset( $excludedFiles[ $wpRel ] ) ) {
                continue;
            }
            // GitHub blob size guard (skip single blobs > 10 MB safely).
            if ( $meta['size'] > 10485760 ) {
                continue;
            }
            $absPath = PathGuard::resolveUnderBase( ABSPATH, $wpRel );
            if ( null === $absPath ) {
                continue;
            }
            $content = @file_get_contents( $absPath );
            if ( false === $content ) {
                continue;
            }
            $gitBlobSha = sha1( 'blob ' . strlen( $content ) . "\0" . $content );
            $repoPath   = $this->mapWpPathToRepo( $wpRel, $ctx['subpath_map'] );
            $seenRepoSet[ $repoPath ] = true;

            if ( ! isset( $remoteBlobs[ $repoPath ] ) ) {
                $added++;
                $bytesTotal  += $meta['size'];
                $uploadList[] = [ 'wp_rel' => $wpRel, 'repo_path' => $repoPath, 'size' => $meta['size'] ];
                $items[]      = [ 'path' => $wpRel, 'status' => 'added', 'size' => $meta['size'], 'target_size' => 0 ];
            } elseif ( $remoteBlobs[ $repoPath ]['sha'] !== $gitBlobSha ) {
                $modified++;
                $bytesTotal  += $meta['size'];
                $uploadList[] = [ 'wp_rel' => $wpRel, 'repo_path' => $repoPath, 'size' => $meta['size'] ];
                $items[]      = [ 'path' => $wpRel, 'status' => 'modified', 'size' => $meta['size'], 'target_size' => $remoteBlobs[ $repoPath ]['size'] ];
            } else {
                $unchanged++;
            }
        }

        // Detect files on GitHub inside selected scopes that were deleted locally.
        foreach ( $remoteBlobs as $repoPath => $rBlob ) {
            if ( isset( $seenRepoSet[ $repoPath ] ) ) {
                continue;
            }
            $wpRel = DiffEngine::applySubpathMapping( $repoPath, $ctx['subpath_map'] );
            if ( isset( $excludedFiles[ $wpRel ] ) || PathGuard::isExcluded( $wpRel, [ 'include_media' => $includeMedia ] ) ) {
                continue;
            }
            if ( DiffEngine::matchesAnyPrefix( $wpRel, $prefixes ) ) {
                $deleted++;
                $deleteList[] = $repoPath;
                $items[]      = [ 'path' => $wpRel, 'status' => 'deleted', 'size' => 0, 'target_size' => $rBlob['size'] ];
            }
        }

        return [
            'ok'            => true,
            'direction'     => 'push',
            'head_sha'      => $headSha,
            'base_tree_sha' => $baseTreeSha,
            'remote_blobs'  => $remoteBlobs,
            'upload_list'   => $uploadList,
            'delete_list'   => $deleteList,
            'summary'       => [
                'added'          => $added,
                'modified'       => $modified,
                'deleted'        => $deleted,
                'unchanged'      => $unchanged,
                'transfer_bytes' => $bytesTotal,
            ],
            'items'         => $items,
        ];
    }

    /**
     * Download GitHub branch zipball into a temp file.
     *
     * @param array<string, mixed> $ctx
     * @return array<string, mixed>
     */
    private function downloadZipball( array $ctx ): array {
        if ( ! function_exists( 'wp_tempnam' ) && defined( 'ABSPATH' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $commitInfo = $this->getLatestCommit( $ctx );
        $commitSha  = ! empty( $commitInfo['ok'] ) ? (string) ( $commitInfo['sha'] ?? '' ) : '';

        $zipApiUrl = $ctx['api_base'] . '/repos/' . $ctx['repo'] . '/zipball/' . rawurlencode( $ctx['branch'] );
        $headRes   = wp_remote_get(
            $zipApiUrl,
            [
                'timeout'     => 25,
                'redirection' => 0,
                'headers'     => [
                    'Authorization' => 'Bearer ' . $ctx['token'],
                    'User-Agent'    => 'DBHDL-WP-Sync/1.0',
                    'Accept'        => 'application/vnd.github+json',
                ],
            ]
        );

        $dlUrl = $zipApiUrl;
        if ( ! is_wp_error( $headRes ) ) {
            $code = (int) wp_remote_retrieve_response_code( $headRes );
            if ( in_array( $code, [ 301, 302, 307, 308 ], true ) ) {
                $loc = (string) wp_remote_retrieve_header( $headRes, 'location' );
                if ( '' !== $loc ) {
                    $dlUrl = $loc;
                }
            } elseif ( $code >= 400 ) {
                return [ 'ok' => false, 'error' => 'GitHub từ chối tải zipball (HTTP ' . $code . '). Kiểm tra lại Repo, Branch và Token.' ];
            }
        }

        $tmpZip = ( function_exists( 'wp_tempnam' ) ? wp_tempnam( 'dbhdl_gh_' ) : tempnam( sys_get_temp_dir(), 'dbhdl_gh_' ) ) . '.zip';
        $dlArgs = [
            'timeout'  => 180,
            'stream'   => true,
            'filename' => $tmpZip,
            'headers'  => [ 'User-Agent' => 'DBHDL-WP-Sync/1.0' ],
        ];
        if ( str_contains( $dlUrl, 'api.github.com' ) ) {
            $dlArgs['headers']['Authorization'] = 'Bearer ' . $ctx['token'];
        }

        $dlRes = wp_remote_get( $dlUrl, $dlArgs );
        if ( is_wp_error( $dlRes ) || ! is_file( $tmpZip ) || filesize( $tmpZip ) < 128 ) {
            @unlink( $tmpZip );
            $err = is_wp_error( $dlRes ) ? $dlRes->get_error_message() : 'File tải về bị rỗng.';
            return [ 'ok' => false, 'error' => 'Không tải được gói mã nguồn từ GitHub: ' . $err ];
        }

        return [
            'ok'         => true,
            'zip_path'   => $tmpZip,
            'commit_sha' => $commitSha,
        ];
    }

    private function resolveTargetRoot( array $request ): string {
        $custom = trim( (string) ( $request['target_abspath'] ?? '' ) );
        if ( '' !== $custom ) {
            $real = realpath( PathGuard::normalize( $custom ) );
            if ( false !== $real && is_dir( $real ) ) {
                return rtrim( PathGuard::normalize( $real ), '/' ) . '/';
            }
        }
        return ABSPATH;
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function resolveConfig( array $overrides ): array {
        $s       = Plugin::getSettings();
        $token   = trim( (string) ( $overrides['token'] ?? '' ) );
        if ( '' === $token ) {
            $token = Plugin::getSecretSetting( 'git_token_enc' );
        }
        $repo    = trim( sanitize_text_field( (string) ( $overrides['repo'] ?? $s['git_repo'] ?? '' ) ), "/ \t\n\r" );
        $branch  = trim( sanitize_text_field( (string) ( $overrides['branch'] ?? $s['git_branch'] ?? 'main' ) ) );
        $apiBase = rtrim( esc_url_raw( (string) ( $overrides['api_base'] ?? $s['git_api_base'] ?? 'https://api.github.com' ) ), '/' );

        return [
            'api_base'     => '' !== $apiBase ? $apiBase : 'https://api.github.com',
            'repo'         => $repo,
            'branch'       => '' !== $branch ? $branch : 'main',
            'token'        => $token,
            'author_name'  => trim( sanitize_text_field( (string) ( $s['git_author_name'] ?? '' ) ) ),
            'author_email' => trim( sanitize_email( (string) ( $s['git_author_email'] ?? '' ) ) ),
            'subpath_map'  => is_array( $s['git_subpath_map'] ?? null ) ? $s['git_subpath_map'] : [],
        ];
    }

    /**
     * @param array<string, mixed> $request
     * @return array<int, string>
     */
    private function resolvePrefixes( array $request ): array {
        if ( ! empty( $request['prefixes'] ) && is_array( $request['prefixes'] ) ) {
            return array_values( array_filter( array_map( 'strval', $request['prefixes'] ) ) );
        }
        return ScopeScanner::resolveScopePrefixes( (array) ( $request['scope'] ?? [] ) );
    }

    /**
     * Reverse `repoPrefix => wpPrefix` mapping when pushing local WP paths to GitHub.
     *
     * @param array<string, string> $subpathMap
     */
    private function mapWpPathToRepo( string $wpRel, array $subpathMap ): string {
        $norm = ltrim( PathGuard::normalize( $wpRel ), '/' );
        foreach ( $subpathMap as $repoPrefix => $wpPrefix ) {
            $r = trim( PathGuard::normalize( (string) $repoPrefix ), '/' );
            $w = trim( PathGuard::normalize( (string) $wpPrefix ), '/' );
            if ( '' === $w ) {
                continue;
            }
            if ( $norm === $w ) {
                return '' === $r || '.' === $r ? basename( $norm ) : $r;
            }
            if ( str_starts_with( $norm, $w . '/' ) ) {
                $tail = substr( $norm, strlen( $w ) + 1 );
                return ( '' === $r || '.' === $r ) ? $tail : ( $r . '/' . $tail );
            }
        }
        return $norm;
    }

    /**
     * @param array<string, mixed>|null $jsonBody
     * @return array<string, mixed>
     */
    private function apiRequest( string $method, string $url, string $token, ?array $jsonBody = null, int $timeout = 20 ): array {
        $args = [
            'method'  => $method,
            'timeout' => $timeout,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'User-Agent'    => 'DBHDL-WP-Sync/1.0',
                'Accept'        => 'application/vnd.github+json',
            ],
        ];
        if ( null !== $jsonBody ) {
            $args['headers']['Content-Type'] = 'application/json';
            $args['body']                    = wp_json_encode( $jsonBody );
        }

        $res = wp_remote_request( $url, $args );
        if ( is_wp_error( $res ) ) {
            return [ 'ok' => false, 'error' => $res->get_error_message() ];
        }
        $code = (int) wp_remote_retrieve_response_code( $res );
        $body = json_decode( (string) wp_remote_retrieve_body( $res ), true );
        if ( $code >= 200 && $code < 300 ) {
            return [ 'ok' => true, 'code' => $code, 'data' => is_array( $body ) ? $body : [] ];
        }
        $msg = is_array( $body ) && ! empty( $body['message'] ) ? (string) $body['message'] : ( 'HTTP ' . $code );
        return [ 'ok' => false, 'code' => $code, 'error' => $msg ];
    }
}
