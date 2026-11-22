<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class OCB_Frontend {
    private static $instance = null;
    private static $rendered = false;
    private $settings;

    public static function instance() {
        if ( null === self::$instance ) { self::$instance = new self(); }
        return self::$instance;
    }

    private function __construct() {
        $this->settings = OCB_Settings::instance()->get();
        add_shortcode( 'ocean_contact_buttons', array( $this, 'shortcode' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue' ) );
        add_action( 'wp_footer', array( $this, 'auto_render' ), 99 );
    }

    private function should_show() {
        $s = $this->settings;
        if ( is_admin() || empty( $s['enabled'] ) || ( ! $s['desktop'] && ! $s['mobile'] ) ) { return false; }
        if ( 'selected' === $s['display_mode'] ) {
            $include = array_filter( array_map( 'absint', explode( ',', $s['include_ids'] ) ) );
            if ( ! is_singular() || ! in_array( get_queried_object_id(), $include, true ) ) { return false; }
        }
        $exclude = array_filter( array_map( 'absint', explode( ',', $s['exclude_ids'] ) ) );
        if ( is_singular() && in_array( get_queried_object_id(), $exclude, true ) ) { return false; }
        return true;
    }

    public function maybe_enqueue() {
        if ( ! $this->should_show() ) { return; }
        wp_enqueue_style( 'ocb-public', OCB_URL . 'public/css/contact-buttons.css', array(), OCB_VERSION );
        wp_enqueue_script( 'ocb-public', OCB_URL . 'public/js/contact-buttons.js', array(), OCB_VERSION, true );
        wp_localize_script( 'ocb-public', 'OCB_TRACKING', array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'ocb_track_click' ) ) );
    }

    public function auto_render() {
        if ( $this->should_show() ) { echo $this->render_markup(); } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public function shortcode() {
        if ( ! $this->should_show() ) { return ''; }
        return $this->render_markup();
    }

    private function render_markup() {
        if ( self::$rendered ) { return ''; }
        $s = $this->settings;
        $hotlines = isset( $s['hotlines'] ) && is_array( $s['hotlines'] ) ? $s['hotlines'] : OCB_Settings::defaults()['hotlines'];
        $zalo = $s['zalo_url'];
        if ( ! $zalo && $s['zalo_phone'] ) { $zalo = 'https://zalo.me/' . preg_replace( '/\D+/', '', $s['zalo_phone'] ); }
        $messenger = $s['messenger_url'];
        if ( ! $messenger && $s['messenger_page_id'] ) { $messenger = 'https://m.me/' . rawurlencode( $s['messenger_page_id'] ); }

        $buttons = array(
            'call' => '<button type="button" class="ocb-button ocb-call" data-ocb-track="call" data-ocb-open aria-haspopup="dialog" aria-controls="ocb-hotline-dialog"><span class="ocb-icon" aria-hidden="true">' . $this->icon( 'phone' ) . '</span><span>Gọi ngay</span></button>',
            'zalo' => $zalo ? '<a class="ocb-button ocb-zalo" data-ocb-track="zalo" href="' . esc_url( $zalo ) . '" target="' . esc_attr( $s['zalo_target'] ) . '" ' . ( '_blank' === $s['zalo_target'] ? 'rel="noopener noreferrer"' : '' ) . ' aria-label="Liên hệ qua Zalo"><span class="ocb-icon ocb-zalo-mark" aria-hidden="true">Z</span><span>Zalo</span></a>' : '',
            'messenger' => $messenger ? '<a class="ocb-button ocb-messenger" data-ocb-track="messenger" href="' . esc_url( $messenger ) . '" target="_blank" rel="noopener noreferrer" aria-label="Liên hệ qua Messenger"><span class="ocb-icon" aria-hidden="true">' . $this->icon( 'chat' ) . '</span><span>Messenger</span></a>' : '',
        );
        $order = explode( ',', $s['button_order'] );
        $active = array();
        foreach ( $order as $key ) {
            if ( isset( $buttons[ $key ] ) && $buttons[ $key ] ) {
                if ( ( 'call' === $key && $s['call_enabled'] ) || ( 'zalo' === $key && $s['zalo_enabled'] ) || ( 'messenger' === $key && $s['messenger_enabled'] ) ) {
                    $active[] = $buttons[ $key ];
                }
            }
        }
        if ( empty( $active ) ) { return ''; }
        self::$rendered = true;

        $style = sprintf(
            '--ocb-bar-bg:%1$s;--ocb-gap:%2$dpx;--ocb-height:%3$dpx;--ocb-radius:%4$dpx;--ocb-text:%5$s;--ocb-call:%6$s;--ocb-zalo:%7$s;--ocb-messenger:%8$s;--ocb-z:%9$d;--ocb-bottom:%10$dpx;',
            esc_attr( $s['bar_bg'] ), absint( $s['gap'] ), absint( $s['button_size'] ), absint( $s['radius'] ),
            esc_attr( $s['text_color'] ), esc_attr( $s['call_bg'] ), esc_attr( $s['zalo_bg'] ), esc_attr( $s['messenger_bg'] ),
            absint( $s['z_index'] ), absint( $s['bottom_offset'] )
        );

        ob_start();
        ?>
        <div class="ocb-root <?php echo $s['desktop'] ? '' : 'ocb-no-desktop'; ?> <?php echo $s['mobile'] ? '' : 'ocb-no-mobile'; ?>" style="<?php echo esc_attr( $style ); ?>">
            <nav class="ocb-bar" aria-label="Các kênh liên hệ"><?php echo implode( '', $active ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></nav>
            <div class="ocb-overlay" data-ocb-overlay hidden>
                <section class="ocb-dialog" id="ocb-hotline-dialog" role="dialog" aria-modal="true" aria-labelledby="ocb-dialog-title" tabindex="-1">
                    <header class="ocb-dialog-header">
                        <span class="ocb-header-icon" aria-hidden="true"><?php echo $this->icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                        <div><h2 id="ocb-dialog-title">Liên hệ Hotline</h2><p>Chọn khu vực để được tư vấn nhanh nhất.</p></div>
                        <button type="button" class="ocb-close" data-ocb-close aria-label="Đóng popup">&times;</button>
                    </header>
                    <div class="ocb-regions">
                        <?php foreach ( $hotlines as $region_key => $region ) :
                            if ( empty( $region['enabled'] ) || empty( $region['phone'] ) ) { continue; }
                            $tel = preg_replace( '/(?!^\+)[^\d]/', '', $region['phone'] );
                            if ( '' === $tel ) { continue; }
                            ?>
                            <article class="ocb-region-card">
                                <span class="ocb-region-icon" aria-hidden="true"><?php echo $this->icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                                <div class="ocb-region-info"><h3><?php echo esc_html( $region['name'] ); ?></h3><p><?php echo esc_html( $region['description'] ); ?></p><strong><?php echo esc_html( $region['phone'] ); ?></strong></div>
                                <a class="ocb-region-call" data-ocb-track="<?php echo esc_attr( 'call_' . sanitize_key( $region_key ) ); ?>" href="<?php echo esc_url( 'tel:' . $tel ); ?>">Gọi ngay</a>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function icon( $type ) {
        if ( 'chat' === $type ) {
            return '<svg viewBox="0 0 24 24" focusable="false"><path d="M12 3C6.9 3 3 6.5 3 11c0 2.2 1 4.2 2.8 5.6L5 21l4.5-2.2c.8.2 1.6.3 2.5.3 5.1 0 9-3.5 9-8.1S17.1 3 12 3Z" fill="currentColor"/><path d="m7.5 13.5 3-3 2.3 1.7 3.7-3.7" fill="none" stroke="#0866ff" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        }
        return '<svg viewBox="0 0 24 24" width="24" height="24" preserveAspectRatio="xMidYMid meet" focusable="false" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.79 19.79 0 0 1 2.12 4.2 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.12.96.35 1.9.69 2.8a2 2 0 0 1-.45 2.11L8.08 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.9.34 1.84.57 2.8.69A2 2 0 0 1 22 16.92Z" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    }
}
