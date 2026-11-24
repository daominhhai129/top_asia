<?php
declare(strict_types=1);

namespace DBHDL\Sync\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Renders the modern 5-tab SPA shell for Đồng Bộ Hóa Dữ Liệu with Pipeline Stepper Header.
 */
final class AppRenderer {

    private const TABS = [
        'overview'         => [ 'icon' => '🏠', 'label' => 'Tổng quan Pipeline' ],
        'local-github'     => [ 'icon' => '🐙', 'label' => 'Local → GitHub' ],
        'local-staging'    => [ 'icon' => '🧪', 'label' => 'Local → Staging' ],
        'local-production' => [ 'icon' => '🌐', 'label' => 'Local → Production' ],
        'github-deploy'    => [ 'icon' => '📥', 'label' => 'GitHub → Staging / Prod' ],
        'settings'         => [ 'icon' => '🔐', 'label' => 'Kết nối & Bảo mật' ],
        'logs'             => [ 'icon' => '📜', 'label' => 'Nhật ký & Hệ thống' ],
    ];

    /**
     * @param array<string, mixed> $boot
     */
    public static function render( array $boot ): void {
        $activeTab = (string) ( $boot['initial_tab'] ?? 'overview' );
        $role      = (string) ( $boot['env_role'] ?? 'staging' );
        $homeUrl   = (string) ( $boot['home_url'] ?? '' );

        $roleBadge = match ( $role ) {
            'local'      => [ 'Môi trường: LOCAL', 'dbhdl-badge-local' ],
            'production' => [ 'Môi trường: PRODUCTION', 'dbhdl-badge-prod' ],
            default      => [ 'Môi trường: STAGING', 'dbhdl-badge-staging' ],
        };
        ?>
        <div id="dbhdl-app" class="wrap dbhdl-wrap" data-initial-tab="<?php echo esc_attr( $activeTab ); ?>" data-role="<?php echo esc_attr( $role ); ?>">
            <header class="dbhdl-header">
                <div class="dbhdl-brand">
                    <div class="dbhdl-logo" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M16 16h5v5"/></svg>
                    </div>
                    <div>
                        <h1 class="dbhdl-title"><?php echo esc_html__( 'Đồng Bộ Hóa Dữ Liệu', 'dong-bo-hoa-du-lieu' ); ?></h1>
                        <p class="dbhdl-subtitle"><?php echo esc_html__( 'Quy trình đồng bộ đa năng Local Project → Staging → Production cho mọi website WordPress', 'dong-bo-hoa-du-lieu' ); ?></p>
                    </div>
                </div>
                <div class="dbhdl-header-meta">
                    <span class="dbhdl-badge <?php echo esc_attr( $roleBadge[1] ); ?>" id="dbhdl-header-role-badge">
                        <?php echo esc_html( $roleBadge[0] ); ?>
                    </span>
                    <span class="dbhdl-badge dbhdl-badge-neutral" title="<?php echo esc_attr( $homeUrl ); ?>">
                        <code><?php echo esc_html( $homeUrl ); ?></code>
                    </span>
                    <span class="dbhdl-badge dbhdl-badge-version">v<?php echo esc_html( (string) ( $boot['version'] ?? DBHDL_VERSION ) ); ?></span>
                </div>
            </header>

            <!-- Visual Multi-Route Pipeline Stepper -->
            <section class="dbhdl-pipeline-bar" aria-label="<?php echo esc_attr__( 'Sơ đồ quy trình đồng bộ', 'dong-bo-hoa-du-lieu' ); ?>">
                <div class="dbhdl-pipe-node <?php echo 'local' === $role ? 'is-current' : ''; ?>" data-pipe-role="local">
                    <span class="dbhdl-pipe-icon">💻</span>
                    <div>
                        <div class="dbhdl-pipe-name">1. Local Project</div>
                        <div class="dbhdl-pipe-desc">Điểm khởi phát: Local → GitHub / Staging / Prod</div>
                    </div>
                </div>
                <div class="dbhdl-pipe-arrow" aria-hidden="true">→</div>
                <div class="dbhdl-pipe-node" data-pipe-role="github">
                    <span class="dbhdl-pipe-icon">🐙</span>
                    <div>
                        <div class="dbhdl-pipe-name">2. GitHub Repo</div>
                        <div class="dbhdl-pipe-desc">Kéo về Staging hoặc Production</div>
                    </div>
                </div>
                <div class="dbhdl-pipe-arrow" aria-hidden="true">→</div>
                <div class="dbhdl-pipe-node <?php echo 'staging' === $role ? 'is-current' : ''; ?>" data-pipe-role="staging">
                    <span class="dbhdl-pipe-icon">🧪</span>
                    <div>
                        <div class="dbhdl-pipe-name">3. Staging Site</div>
                        <div class="dbhdl-pipe-desc">Tự bật khi URL có chữ <code>staging</code></div>
                    </div>
                </div>
                <div class="dbhdl-pipe-arrow" aria-hidden="true">→</div>
                <div class="dbhdl-pipe-node <?php echo 'production' === $role ? 'is-current' : ''; ?>" data-pipe-role="production">
                    <span class="dbhdl-pipe-icon">🌐</span>
                    <div>
                        <div class="dbhdl-pipe-name">4. Production (Live)</div>
                        <div class="dbhdl-pipe-desc">Triển khai an toàn &amp; Rollback 1-Click</div>
                    </div>
                </div>
            </section>

            <!-- Tab Bar -->
            <nav class="dbhdl-tabs" role="tablist" aria-label="<?php echo esc_attr__( 'Chức năng đồng bộ', 'dong-bo-hoa-du-lieu' ); ?>">
                <?php foreach ( self::TABS as $slug => $meta ) : ?>
                    <?php
                    $isActive = ( $slug === $activeTab );
                    $tabLabel = $meta['label'];
                    if ( 'local-production' === $slug && 'staging' === $role ) {
                        $tabLabel = 'Staging → Production';
                    } elseif ( 'local-production' === $slug && 'production' === $role ) {
                        $tabLabel = 'Snapshots & Rollback';
                    }
                    ?>
                    <button
                        type="button"
                        role="tab"
                        id="dbhdl-tab-btn-<?php echo esc_attr( $slug ); ?>"
                        class="dbhdl-tab-btn<?php echo $isActive ? ' is-active' : ''; ?>"
                        aria-selected="<?php echo $isActive ? 'true' : 'false'; ?>"
                        aria-controls="dbhdl-view-mount"
                        data-tab="<?php echo esc_attr( $slug ); ?>"
                    >
                        <span aria-hidden="true"><?php echo esc_html( $meta['icon'] ); ?></span>
                        <span><?php echo esc_html( $tabLabel ); ?></span>
                    </button>
                <?php endforeach; ?>
            </nav>

            <div id="dbhdl-progress-region" class="dbhdl-progress-region" hidden aria-live="polite"></div>
            <div id="dbhdl-toast-region" class="dbhdl-toast-region" aria-live="assertive"></div>

            <main id="dbhdl-view-mount" class="dbhdl-main" role="tabpanel" aria-labelledby="dbhdl-tab-btn-<?php echo esc_attr( $activeTab ); ?>">
                <div class="dbhdl-card dbhdl-loading-card">
                    <div class="dbhdl-skeleton dbhdl-w-50"></div>
                    <div class="dbhdl-skeleton dbhdl-w-90"></div>
                    <div class="dbhdl-skeleton dbhdl-w-70"></div>
                    <noscript>
                        <div class="dbhdl-alert dbhdl-alert-danger">Vui lòng bật JavaScript để sử dụng giao diện Đồng Bộ Hóa Dữ Liệu.</div>
                    </noscript>
                </div>
            </main>

            <div id="dbhdl-modal-root" class="dbhdl-modal-root" hidden aria-hidden="true"></div>
        </div>
        <?php
    }
}
