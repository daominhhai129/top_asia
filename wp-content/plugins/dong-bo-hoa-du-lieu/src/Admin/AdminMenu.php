<?php
declare(strict_types=1);

namespace DBHDL\Sync\Admin;

use DBHDL\Sync\Discovery\EnvironmentDetector;
use DBHDL\Sync\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registers the WP Admin Tools (Công cụ) submenu page, backward-compatible URL redirect,
 * Admin Bar quick-action pill, mobile bottom action sheet, and scoped CSS/JS assets.
 */
final class AdminMenu {

    public const MENU_SLUG = 'dong-bo-hoa-du-lieu';

    private string $screenHook = '';

    public function register(): void {
        add_action( 'admin_menu', [ $this, 'registerAdminPages' ] );
        add_action( 'admin_init', [ $this, 'redirectLegacyMenuUrl' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueueAdminAssets' ] );
        add_filter( 'plugin_action_links_' . plugin_basename( DBHDL_FILE ), [ $this, 'addPluginActionLinks' ] );
        add_action( 'admin_bar_menu', [ $this, 'registerAdminBar' ], 95 );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueueAdminBarAssets' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueueAdminBarAssets' ] );
        add_action( 'admin_footer', [ $this, 'renderAdminBarSheet' ] );
        add_action( 'wp_footer', [ $this, 'renderAdminBarSheet' ] );
    }

    /**
     * Build canonical admin URL under Tools (Công cụ -> Đồng Bộ Hóa Dữ Liệu).
     */
    public static function pageUrl( string $tab = '' ): string {
        $args = [ 'page' => self::MENU_SLUG ];
        if ( '' !== $tab ) {
            $args['tab'] = sanitize_key( $tab );
        }
        return add_query_arg( $args, admin_url( 'tools.php' ) );
    }

    /**
     * Add quick link on the Plugins list screen.
     *
     * @param array<int|string, string> $links
     * @return array<int|string, string>
     */
    public function addPluginActionLinks( array $links ): array {
        array_unshift(
            $links,
            '<a href="' . esc_url( self::pageUrl() ) . '">' . esc_html__( 'Mở công cụ', 'dong-bo-hoa-du-lieu' ) . '</a>'
        );
        return $links;
    }

    /**
     * Keep old `admin.php?page=dong-bo-hoa-du-lieu` bookmarks working seamlessly.
     */
    public function redirectLegacyMenuUrl(): void {
        global $pagenow;
        if (
            ! is_admin()
            || 'admin.php' !== ( $pagenow ?? '' )
            || ! isset( $_GET['page'] )
            || self::MENU_SLUG !== sanitize_key( (string) wp_unslash( $_GET['page'] ) )
            || ! current_user_can( Plugin::requiredCapability() )
        ) {
            return;
        }
        $tab = isset( $_GET['tab'] ) ? sanitize_key( (string) wp_unslash( $_GET['tab'] ) ) : '';
        wp_safe_redirect( self::pageUrl( $tab ) );
        exit;
    }

    public function registerAdminPages(): void {
        $cap  = Plugin::requiredCapability();
        $hook = add_management_page(
            __( 'Đồng Bộ Hóa Dữ Liệu', 'dong-bo-hoa-du-lieu' ),
            __( 'Đồng Bộ Hóa Dữ Liệu', 'dong-bo-hoa-du-lieu' ),
            $cap,
            self::MENU_SLUG,
            [ $this, 'renderPage' ]
        );

        if ( is_string( $hook ) ) {
            $this->screenHook = $hook;
        }
    }

    public function enqueueAdminAssets( string $hookSuffix ): void {
        $isOurPage = ( '' !== $this->screenHook && $hookSuffix === $this->screenHook )
            || 'tools_page_' . self::MENU_SLUG === $hookSuffix;

        if ( ! $isOurPage || ! current_user_can( Plugin::requiredCapability() ) ) {
            return;
        }

        $ver = DBHDL_VERSION . '.' . (string) @filemtime( DBHDL_DIR . 'assets/js/dbhdl-ui-pipeline.js' );
        wp_enqueue_style( 'dbhdl-admin', DBHDL_URL . 'assets/css/dbhdl-admin.css', [], DBHDL_VERSION . '.' . (string) @filemtime( DBHDL_DIR . 'assets/css/dbhdl-admin.css' ) );

        wp_enqueue_script( 'dbhdl-api', DBHDL_URL . 'assets/js/dbhdl-api.js', [], DBHDL_VERSION . '.' . (string) @filemtime( DBHDL_DIR . 'assets/js/dbhdl-api.js' ), true );
        wp_enqueue_script( 'dbhdl-ui-pipeline', DBHDL_URL . 'assets/js/dbhdl-ui-pipeline.js', [ 'dbhdl-api' ], $ver, true );
        wp_enqueue_script( 'dbhdl-ui-deploy', DBHDL_URL . 'assets/js/dbhdl-ui-deploy.js', [ 'dbhdl-api' ], DBHDL_VERSION . '.' . (string) @filemtime( DBHDL_DIR . 'assets/js/dbhdl-ui-deploy.js' ), true );
        wp_enqueue_script( 'dbhdl-ui-settings', DBHDL_URL . 'assets/js/dbhdl-ui-settings.js', [ 'dbhdl-api' ], DBHDL_VERSION . '.' . (string) @filemtime( DBHDL_DIR . 'assets/js/dbhdl-ui-settings.js' ), true );

        wp_localize_script(
            'dbhdl-api',
            'DBHDL_BOOT',
            $this->buildBootPayload()
        );
    }

    public function enqueueAdminBarAssets(): void {
        if ( ! is_admin_bar_showing() || ! current_user_can( Plugin::requiredCapability() ) ) {
            return;
        }
        wp_enqueue_style( 'dbhdl-admin-bar', DBHDL_URL . 'assets/css/dbhdl-admin-bar.css', [], DBHDL_VERSION );
        wp_enqueue_script( 'dbhdl-admin-bar', DBHDL_URL . 'assets/js/dbhdl-admin-bar.js', [], DBHDL_VERSION, true );
        wp_localize_script(
            'dbhdl-admin-bar',
            'DBHDL_BAR_BOOT',
            [
                'rest_base' => rtrim( rest_url( DBHDL_REST_NS ), '/' ),
                'nonce'     => wp_create_nonce( 'wp_rest' ),
                'admin_url' => self::pageUrl(),
                'role'      => EnvironmentDetector::detectRole()['role'],
            ]
        );
    }

    /**
     * @param \WP_Admin_Bar $wpAdminBar
     */
    public function registerAdminBar( $wpAdminBar ): void {
        if ( ! current_user_can( Plugin::requiredCapability() ) ) {
            return;
        }

        $env      = EnvironmentDetector::detectRole();
        $role     = $env['role'];
        $baseUrl  = self::pageUrl();
        $badgeMap = [
            'local'      => [ 'LOCAL', 'dbhdl-pill-local' ],
            'staging'    => [ 'STAGING', 'dbhdl-pill-staging' ],
            'production' => [ 'LIVE', 'dbhdl-pill-prod' ],
        ];
        [ $badgeText, $badgeCls ] = $badgeMap[ $role ] ?? [ 'SYNC', 'dbhdl-pill-staging' ];

        $iconSvg = '<svg class="dbhdl-bar-svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M16 16h5v5"/></svg>';

        $wpAdminBar->add_node(
            [
                'id'    => 'dbhdl-sync-hub',
                'title' => '<span class="dbhdl-bar-desktop">' . $iconSvg . '<span>Đồng Bộ Dữ Liệu</span><span class="dbhdl-bar-badge ' . esc_attr( $badgeCls ) . '">' . esc_html( $badgeText ) . '</span></span>'
                    . '<span class="dbhdl-bar-mobile" title="Đồng Bộ Dữ Liệu (' . esc_attr( $badgeText ) . ')">' . $iconSvg . '<span class="dbhdl-bar-dot ' . esc_attr( $badgeCls ) . '"></span></span>',
                'href'  => $baseUrl,
                'meta'  => [ 'class' => 'dbhdl-admin-bar-node' ],
            ]
        );

        $wpAdminBar->add_node(
            [
                'parent' => 'dbhdl-sync-hub',
                'id'     => 'dbhdl-bar-overview',
                'title'  => '🏠 Tổng quan Pipeline',
                'href'   => $baseUrl . '&tab=overview',
            ]
        );
        $wpAdminBar->add_node(
            [
                'parent' => 'dbhdl-sync-hub',
                'id'     => 'dbhdl-bar-local-github',
                'title'  => '🐙 Local → GitHub',
                'href'   => $baseUrl . '&tab=local-github',
            ]
        );
        $wpAdminBar->add_node(
            [
                'parent' => 'dbhdl-sync-hub',
                'id'     => 'dbhdl-bar-local-staging',
                'title'  => '🧪 Local → Staging',
                'href'   => $baseUrl . '&tab=local-staging',
            ]
        );
        $wpAdminBar->add_node(
            [
                'parent' => 'dbhdl-sync-hub',
                'id'     => 'dbhdl-bar-local-production',
                'title'  => 'production' === $role ? '⏪ Snapshots & Rollback' : '🌐 Local → Production',
                'href'   => $baseUrl . '&tab=local-production',
            ]
        );
        $wpAdminBar->add_node(
            [
                'parent' => 'dbhdl-sync-hub',
                'id'     => 'dbhdl-bar-github-deploy',
                'title'  => '📥 GitHub → Staging / Prod',
                'href'   => $baseUrl . '&tab=github-deploy',
            ]
        );
        $wpAdminBar->add_node(
            [
                'parent' => 'dbhdl-sync-hub',
                'id'     => 'dbhdl-bar-settings',
                'title'  => '🔐 Kết nối & Kho Bảo mật',
                'href'   => $baseUrl . '&tab=settings',
            ]
        );
    }

    public function renderAdminBarSheet(): void {
        if ( ! is_admin_bar_showing() || ! current_user_can( Plugin::requiredCapability() ) ) {
            return;
        }
        static $rendered = false;
        if ( $rendered ) {
            return;
        }
        $rendered = true;

        $env     = EnvironmentDetector::detectRole();
        $role    = $env['role'];
        $baseUrl = self::pageUrl();
        ?>
        <div id="dbhdl-bar-toast" class="dbhdl-bar-toast" role="status" aria-live="polite"></div>
        <div id="dbhdl-sheet-backdrop" class="dbhdl-sheet-backdrop"></div>
        <div id="dbhdl-mobile-sheet" class="dbhdl-mobile-sheet" role="dialog" aria-modal="true" aria-label="Đồng Bộ Hóa Dữ Liệu">
            <div class="dbhdl-sheet-handle"></div>
            <div class="dbhdl-sheet-head">
                <div>
                    <div class="dbhdl-sheet-title">🔄 Đồng Bộ Hóa Dữ Liệu</div>
                    <div class="dbhdl-sheet-sub">
                        Môi trường: <strong><?php echo esc_html( strtoupper( $role ) ); ?></strong>
                    </div>
                </div>
                <button type="button" id="dbhdl-sheet-close" class="dbhdl-sheet-close" aria-label="Đóng">&times;</button>
            </div>
            <div class="dbhdl-sheet-list">
                <a class="dbhdl-sheet-item" href="<?php echo esc_url( $baseUrl . '&tab=local-github' ); ?>">
                    <span class="dbhdl-sheet-ico">🐙</span>
                    <span>
                        <strong>Local → GitHub</strong>
                        <small>Kiểm tra Diff &amp; đẩy mã nguồn lên GitHub</small>
                    </span>
                </a>
                <a class="dbhdl-sheet-item" href="<?php echo esc_url( $baseUrl . '&tab=local-staging' ); ?>">
                    <span class="dbhdl-sheet-ico">🧪</span>
                    <span>
                        <strong>Local → Staging</strong>
                        <small>Tự kích hoạt khi URL có chứa chữ staging</small>
                    </span>
                </a>
                <a class="dbhdl-sheet-item" href="<?php echo esc_url( $baseUrl . '&tab=local-production' ); ?>">
                    <span class="dbhdl-sheet-ico">🌐</span>
                    <span>
                        <strong><?php echo 'production' === $role ? 'Snapshots & Rollback' : 'Local → Production'; ?></strong>
                        <small>Kiểm tra Diff, tạo Snapshot và triển khai Production</small>
                    </span>
                </a>
                <a class="dbhdl-sheet-item" href="<?php echo esc_url( $baseUrl . '&tab=github-deploy' ); ?>">
                    <span class="dbhdl-sheet-ico">📥</span>
                    <span>
                        <strong>GitHub → Staging / Prod</strong>
                        <small>Kéo mã nguồn từ GitHub về Staging hoặc Production</small>
                    </span>
                </a>
                <a class="dbhdl-sheet-item" href="<?php echo esc_url( $baseUrl . '&tab=settings' ); ?>">
                    <span class="dbhdl-sheet-ico">🔐</span>
                    <span>
                        <strong>Kết nối &amp; Kho Bảo mật</strong>
                        <small>GitHub, Remote Pairing &amp; Environment Secrets</small>
                    </span>
                </a>
                <a class="dbhdl-sheet-item" href="<?php echo esc_url( $baseUrl . '&tab=logs' ); ?>">
                    <span class="dbhdl-sheet-ico">📜</span>
                    <span>
                        <strong>Nhật ký &amp; Hệ thống</strong>
                        <small>Lịch sử đồng bộ &amp; kiểm tra sức khỏe máy chủ</small>
                    </span>
                </a>
            </div>
        </div>
        <?php
    }

    public function renderPage(): void {
        if ( ! current_user_can( Plugin::requiredCapability() ) ) {
            wp_die( esc_html__( 'Bạn không có quyền truy cập trang này.', 'dong-bo-hoa-du-lieu' ), 403 );
        }
        AppRenderer::render( $this->buildBootPayload() );
    }

    /**
     * Zero-secret boot payload injected into Admin SPA JS.
     *
     * @return array<string, mixed>
     */
    private function buildBootPayload(): array {
        $validTabs = [ 'overview', 'local-github', 'local-staging', 'local-production', 'github-deploy', 'settings', 'logs' ];
        $reqTab    = isset( $_GET['tab'] ) ? sanitize_key( (string) $_GET['tab'] ) : 'overview';
        if ( 'deploy' === $reqTab ) {
            $reqTab = 'local-production';
        }
        if ( ! in_array( $reqTab, $validTabs, true ) ) {
            $reqTab = 'overview';
        }
        $env = EnvironmentDetector::detectRole();

        return [
            'version'     => DBHDL_VERSION,
            'rest_base'   => rtrim( rest_url( DBHDL_REST_NS ), '/' ),
            'nonce'       => wp_create_nonce( 'wp_rest' ),
            'initial_tab' => $reqTab,
            'env_role'    => $env['role'],
            'home_url'    => $env['url'],
            'abspath'     => $env['abspath'],
        ];
    }
}
