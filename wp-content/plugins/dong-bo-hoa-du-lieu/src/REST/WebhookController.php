<?php
declare(strict_types=1);

namespace DBHDL\Sync\REST;

use DBHDL\Sync\Discovery\EnvironmentDetector;
use DBHDL\Sync\Discovery\ScopeScanner;
use DBHDL\Sync\Plugin;
use DBHDL\Sync\Security\RequestSigner;
use DBHDL\Sync\Transport\GitHubTransport;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Authenticated GitHub / CI Auto-Pull Webhook Controller.
 * Strictly blocks public webhook execution on Production unless explicitly permitted,
 * and enforces HMAC-SHA256 (`X-Hub-Signature-256`) or Header Secret verification.
 */
final class WebhookController {

    public function registerRoutes(): void {
        register_rest_route(
            DBHDL_REST_NS,
            '/webhook/git-pull',
            [
                'methods'             => [ 'POST', 'GET' ],
                'callback'            => [ $this, 'handleGitPull' ],
                'permission_callback' => [ $this, 'authorizeWebhook' ],
            ]
        );
    }

    /**
     * @return true|WP_Error
     */
    public function authorizeWebhook( WP_REST_Request $req ) {
        $settings = Plugin::getSettings();
        if ( empty( $settings['webhook_auto_pull_enabled'] ) ) {
            return new WP_Error( 'dbhdl_webhook_disabled', 'Auto-Pull Webhook đang tắt.', [ 'status' => 403 ] );
        }

        $secret = Plugin::getSecretSetting( 'webhook_secret_enc' );
        if ( '' === $secret ) {
            return new WP_Error( 'dbhdl_webhook_unconfigured', 'Chưa cấu hình Webhook Secret.', [ 'status' => 403 ] );
        }

        $sigHeader    = (string) $req->get_header( 'x-hub-signature-256' );
        $secretHeader = (string) $req->get_header( 'x-dbhdl-webhook-secret' );
        $authHeader   = (string) $req->get_header( 'authorization' );
        $querySecret  = ! empty( $settings['webhook_allow_query_param'] ) ? (string) $req->get_param( 'secret' ) : '';

        $authorized = false;
        if ( '' !== $sigHeader && RequestSigner::verifyGitHubSignature( $secret, $sigHeader, (string) $req->get_body() ) ) {
            $authorized = true;
        } elseif ( '' !== $secretHeader && hash_equals( $secret, trim( $secretHeader ) ) ) {
            $authorized = true;
        } elseif ( preg_match( '/^Bearer\s+(.+)$/i', trim( $authHeader ), $m ) && hash_equals( $secret, trim( $m[1] ) ) ) {
            $authorized = true;
        } elseif ( '' !== $querySecret && hash_equals( $secret, trim( $querySecret ) ) ) {
            $authorized = true;
        }

        if ( ! $authorized ) {
            return new WP_Error( 'dbhdl_webhook_forbidden', 'Chữ ký hoặc khóa Webhook không hợp lệ.', [ 'status' => 403 ] );
        }

        return true;
    }

    public function handleGitPull( WP_REST_Request $req ): WP_REST_Response {
        $env = EnvironmentDetector::detectRole();
        if ( 'production' === $env['role'] ) {
            return new WP_REST_Response(
                [
                    'ok'    => false,
                    'error' => 'Vì an toàn, Auto-Pull Webhook bị chặn trên Production. Vui lòng deploy Staging → Production từ WP Admin.',
                ],
                403
            );
        }

        $settings = Plugin::getSettings();
        $branch   = trim( (string) ( $settings['git_branch'] ?? 'main' ) );

        // If GitHub push payload specifies a ref, ignore pushes to other branches gracefully.
        $payload = json_decode( (string) $req->get_body(), true );
        if ( is_array( $payload ) && ! empty( $payload['ref'] ) ) {
            $pushedBranch = preg_replace( '#^refs/heads/#', '', (string) $payload['ref'] );
            if ( '' !== $pushedBranch && $pushedBranch !== $branch ) {
                return new WP_REST_Response(
                    [
                        'ok'      => true,
                        'skipped' => true,
                        'message' => sprintf( 'Bỏ qua push vào nhánh %s (đang theo dõi nhánh %s).', $pushedBranch, $branch ),
                    ],
                    200
                );
            }
        }

        // Pull all discovered installed plugins/themes + custom dirs unless saved scope specifies otherwise.
        $prefixes = (array) $req->get_param( 'prefixes' );
        if ( empty( $prefixes ) ) {
            $inv       = ScopeScanner::getInventory();
            $selection = [
                'plugins'     => array_column( $inv['plugins'], 'slug' ),
                'themes'      => array_column( $inv['themes'], 'slug' ),
                'mu_plugins'  => ! empty( $inv['mu_plugins']['exists'] ),
                'custom_dirs' => array_column( $inv['custom_dirs'], 'slug' ),
            ];
            $prefixes = ScopeScanner::resolveScopePrefixes( $selection );
        }

        $res = ( new GitHubTransport() )->executePull(
            [
                'prefixes' => $prefixes,
                'trigger'  => 'webhook_auto_pull',
            ]
        );

        return new WP_REST_Response( $res, ! empty( $res['ok'] ) ? 200 : 500 );
    }
}
