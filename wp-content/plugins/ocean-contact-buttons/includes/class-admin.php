<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class OCB_Admin {
    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) { self::$instance = new self(); }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', array( $this, 'menu' ) );
        add_action( 'admin_init', array( $this, 'register' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
    }

    public function menu() {
        add_menu_page(
            __( 'Ocean Contact Buttons', 'ocean-contact-buttons' ),
            __( 'Ocean Contact Buttons', 'ocean-contact-buttons' ),
            'manage_options',
            'ocean-contact-buttons',
            array( $this, 'render' ),
            'dashicons-phone',
            59
        );
    }

    public function register() {
        register_setting( 'ocb_settings_group', OCB_Settings::OPTION, array(
            'type' => 'array',
            'sanitize_callback' => array( OCB_Settings::instance(), 'sanitize' ),
            'default' => OCB_Settings::defaults(),
        ) );
    }

    public function assets( $hook ) {
        if ( 'toplevel_page_ocean-contact-buttons' !== $hook ) { return; }
        wp_enqueue_style( 'ocb-admin', OCB_URL . 'admin/css/admin.css', array(), OCB_VERSION );
        wp_enqueue_script( 'ocb-admin', OCB_URL . 'admin/js/admin.js', array(), OCB_VERSION, true );
    }

    private function name( $key ) {
        return OCB_Settings::OPTION . '[' . $key . ']';
    }

    private function text_field( $key, $value, $type = 'text', $class = 'regular-text' ) {
        return sprintf(
            '<input class="%1$s" type="%2$s" name="%3$s" value="%4$s">',
            esc_attr( $class ),
            esc_attr( $type ),
            esc_attr( $this->name( $key ) ),
            esc_attr( $value )
        );
    }

    private function checkbox( $key, $checked, $label ) {
        return sprintf( '<label><input type="checkbox" name="%1$s" value="1" %2$s> %3$s</label>', esc_attr( $this->name( $key ) ), checked( $checked, 1, false ), esc_html( $label ) );
    }

    private function row( $label, $content, $description = '' ) {
        echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . $content;
        if ( $description ) { echo '<p class="description">' . esc_html( $description ) . '</p>'; }
        echo '</td></tr>';
    }

    public function render() {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        $s = OCB_Settings::instance()->get();
        $tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';
        $tabs = array(
            'general' => 'Cấu hình chung',
            'hotline' => 'Cấu hình Hotline',
            'zalo' => 'Cấu hình Zalo',
            'messenger' => 'Cấu hình Messenger',
            'display' => 'Hiển thị',
            'analytics' => 'Thống kê lượt nhấp',
        );
        if ( ! isset( $tabs[ $tab ] ) ) { $tab = 'general'; }

        echo '<div class="wrap ocb-admin"><h1>Ocean Contact Buttons</h1>';
        echo '<p class="ocb-intro">Thiết lập thanh liên hệ nổi ở cạnh dưới website. Các thay đổi được lưu bằng nút “Lưu thay đổi”.</p>';
        echo '<nav class="nav-tab-wrapper">';
        foreach ( $tabs as $key => $label ) {
            printf( '<a class="nav-tab %1$s" href="%2$s">%3$s</a>', $tab === $key ? 'nav-tab-active' : '', esc_url( admin_url( 'admin.php?page=ocean-contact-buttons&tab=' . $key ) ), esc_html( $label ) );
        }
        echo '</nav><form method="post" action="options.php">';
        settings_fields( 'ocb_settings_group' );
        // Each tab is a separate form; tell the sanitizer which fields were submitted.
        echo '<input type="hidden" name="' . esc_attr( OCB_Settings::OPTION ) . '[_ocb_tab]" value="' . esc_attr( $tab ) . '">';

        if ( 'general' === $tab ) {
            echo '<h2>Cấu hình chung</h2><table class="form-table">';
            $this->row( 'Trạng thái', $this->checkbox( 'enabled', $s['enabled'], 'Bật Contact Bar' ) );
            $this->row( 'Các nút', $this->checkbox( 'call_enabled', $s['call_enabled'], 'Gọi ngay' ) . '<br>' . $this->checkbox( 'zalo_enabled', $s['zalo_enabled'], 'Zalo' ) . '<br>' . $this->checkbox( 'messenger_enabled', $s['messenger_enabled'], 'Messenger' ) );
            $this->row( 'Thiết bị', $this->checkbox( 'desktop', $s['desktop'], 'Hiển thị trên Desktop' ) . '<br>' . $this->checkbox( 'mobile', $s['mobile'], 'Hiển thị trên Mobile' ) );
            $this->row( 'Vị trí', '<strong>Fixed Bottom</strong>', 'Thanh liên hệ được cố định ở cạnh dưới màn hình.' );
            $this->row( 'Thứ tự nút', $this->text_field( 'button_order', $s['button_order'] ), 'Nhập theo thứ tự, phân cách bằng dấu phẩy: call,zalo,messenger.' );
            $this->row( 'Khoảng cách nút (px)', $this->text_field( 'gap', $s['gap'], 'number' ) );
            $this->row( 'Chiều cao nút (px)', $this->text_field( 'button_size', $s['button_size'], 'number' ) );
            $this->row( 'Bo góc (px)', $this->text_field( 'radius', $s['radius'], 'number' ), 'Giá trị 999 tạo pill button.' );
            $this->row( 'Nền thanh', $this->text_field( 'bar_bg', $s['bar_bg'], 'color' ) );
            $this->row( 'Màu nút Gọi', $this->text_field( 'call_bg', $s['call_bg'], 'color' ) );
            $this->row( 'Màu nút Zalo', $this->text_field( 'zalo_bg', $s['zalo_bg'], 'color' ) );
            $this->row( 'Màu nút Messenger', $this->text_field( 'messenger_bg', $s['messenger_bg'], 'color' ) );
            $this->row( 'Màu chữ', $this->text_field( 'text_color', $s['text_color'], 'color' ) );
            $this->row( 'Z-index', $this->text_field( 'z_index', $s['z_index'], 'number' ) );
            echo '</table>';
        } elseif ( 'hotline' === $tab ) {
            echo '<h2>Cấu hình Hotline theo khu vực</h2><p class="description">Số điện thoại được dùng cho liên kết tel: trong popup. Có thể thay đổi bất cứ lúc nào.</p>';
            foreach ( array( 'north' => 'Miền Bắc', 'central' => 'Miền Trung', 'south' => 'Miền Nam' ) as $region => $title ) {
                $row = $s['hotlines'][ $region ];
                echo '<section class="ocb-region"><h3>' . esc_html( $title ) . '</h3><table class="form-table">';
                $prefix = 'hotlines][' . $region . '][';
                $this->row( 'Kích hoạt', '<label><input type="checkbox" name="' . esc_attr( $this->name( 'hotlines' ) . '[' . $region . '][enabled]' ) . '" value="1" ' . checked( $row['enabled'], 1, false ) . '> Hiển thị khu vực này</label>' );
                foreach ( array( 'name' => 'Tên khu vực', 'description' => 'Mô tả', 'phone' => 'Số điện thoại' ) as $field => $label ) {
                    $type = 'phone' === $field ? 'tel' : 'text';
                    $html = '<input class="regular-text" type="' . esc_attr( $type ) . '" name="' . esc_attr( $this->name( 'hotlines' ) . '[' . $region . '][' . $field . ']' ) . '" value="' . esc_attr( $row[ $field ] ) . '">';
                    $this->row( $label, $html );
                }
                echo '</table></section>';
            }
        } elseif ( 'zalo' === $tab ) {
            echo '<h2>Cấu hình Zalo</h2><table class="form-table">';
            $this->row( 'Trạng thái', $this->checkbox( 'zalo_enabled', $s['zalo_enabled'], 'Bật nút Zalo' ) );
            $this->row( 'Số điện thoại Zalo', $this->text_field( 'zalo_phone', $s['zalo_phone'], 'tel' ), 'Dùng để tạo link mặc định https://zalo.me/{số điện thoại} khi không nhập URL riêng.' );
            $this->row( 'Đường dẫn Zalo', $this->text_field( 'zalo_url', $s['zalo_url'], 'url' ), 'Ví dụ: https://zalo.me/0896699996' );
            $select = '<select name="' . esc_attr( $this->name( 'zalo_target' ) ) . '"><option value="_blank" ' . selected( $s['zalo_target'], '_blank', false ) . '>Tab mới</option><option value="_self" ' . selected( $s['zalo_target'], '_self', false ) . '>Cùng tab</option></select>';
            $this->row( 'Cách mở liên kết', $select );
            echo '</table>';
        } elseif ( 'messenger' === $tab ) {
            echo '<h2>Cấu hình Messenger</h2><table class="form-table">';
            $this->row( 'Trạng thái', $this->checkbox( 'messenger_enabled', $s['messenger_enabled'], 'Bật nút Messenger' ) );
            $this->row( 'Facebook Page ID', $this->text_field( 'messenger_page_id', $s['messenger_page_id'] ), 'Có thể nhập Page ID để tạo link m.me nếu không dùng URL tùy chỉnh.' );
            $this->row( 'Messenger URL', $this->text_field( 'messenger_url', $s['messenger_url'], 'url' ), 'Ví dụ: https://m.me/tenfanpage' );
            echo '</table>';
        } elseif ( 'analytics' === $tab ) {
            $period = isset( $_GET['period'] ) ? absint( $_GET['period'] ) : 30;
            if ( ! in_array( $period, array( 7, 30, 90, 365 ), true ) ) { $period = 30; }
            $totals = OCB_Tracking::totals( $period );
            $daily = OCB_Tracking::daily( $period );
            $all = array_sum( $totals );
            echo '<h2>Thống kê lượt nhấp nút liên hệ</h2>';
            echo '<p>Theo dõi lượt nhấp nút Gọi ngay mở popup, từng hotline Miền Bắc/Miền Trung/Miền Nam, Zalo và Messenger. Dữ liệu được cộng dồn theo ngày; plugin không lưu IP, tên hoặc thông tin cá nhân của khách.</p>';
            echo '<p class="ocb-period">';
            foreach ( array( 7 => '7 ngày', 30 => '30 ngày', 90 => '90 ngày', 365 => '365 ngày' ) as $n => $label ) {
                $url = admin_url( 'admin.php?page=ocean-contact-buttons&tab=analytics&period=' . $n );
                echo '<a class="button ' . ( $period === $n ? 'button-primary' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a> ';
            }
            echo '</p><div class="ocb-stats-grid">';
            foreach ( array( 'call' => 'Mở popup Gọi ngay', 'call_north' => 'Gọi Miền Bắc', 'call_central' => 'Gọi Miền Trung', 'call_south' => 'Gọi Miền Nam', 'zalo' => 'Zalo', 'messenger' => 'Messenger' ) as $key => $label ) {
                echo '<div class="ocb-stat-card"><span>' . esc_html( $label ) . '</span><strong>' . esc_html( number_format_i18n( $totals[ $key ] ) ) . '</strong><small>Lượt nhấp</small></div>';
            }
            echo '<div class="ocb-stat-card ocb-stat-total"><span>Tổng cộng</span><strong>' . esc_html( number_format_i18n( $all ) ) . '</strong><small>Lượt nhấp trong ' . esc_html( $period ) . ' ngày</small></div></div>';
            echo '<h3>Chi tiết theo ngày</h3><table class="widefat striped"><thead><tr><th>Ngày</th><th>Mở popup</th><th>Gọi Miền Bắc</th><th>Gọi Miền Trung</th><th>Gọi Miền Nam</th><th>Zalo</th><th>Messenger</th><th>Tổng</th></tr></thead><tbody>';
            $by_date = array();
            foreach ( $daily as $row ) {
                if ( ! isset( $by_date[ $row->event_date ] ) ) { $by_date[ $row->event_date ] = array( 'call' => 0, 'call_north' => 0, 'call_central' => 0, 'call_south' => 0, 'zalo' => 0, 'messenger' => 0 ); }
                $by_date[ $row->event_date ][ $row->channel ] = (int) $row->clicks;
            }
            if ( empty( $by_date ) ) {
                echo '<tr><td colspan="8">Chưa có dữ liệu. Lượt nhấp sẽ xuất hiện sau khi khách tương tác với các nút trên website.</td></tr>';
            } else {
                foreach ( $by_date as $date => $counts ) {
                    echo '<tr><td>' . esc_html( mysql2date( 'd/m/Y', $date ) ) . '</td><td>' . esc_html( number_format_i18n( $counts['call'] ) ) . '</td><td>' . esc_html( number_format_i18n( $counts['call_north'] ) ) . '</td><td>' . esc_html( number_format_i18n( $counts['call_central'] ) ) . '</td><td>' . esc_html( number_format_i18n( $counts['call_south'] ) ) . '</td><td>' . esc_html( number_format_i18n( $counts['zalo'] ) ) . '</td><td>' . esc_html( number_format_i18n( $counts['messenger'] ) ) . '</td><td><strong>' . esc_html( number_format_i18n( array_sum( $counts ) ) ) . '</strong></td></tr>';
                }
            }
            echo '</tbody></table><p class="description">Lưu ý: đây là số lần nhấp vào nút trên website, không phải số cuộc gọi đã kết nối hoặc số tin nhắn đã gửi thành công.</p>';
        } else {
            echo '<h2>Điều kiện hiển thị</h2><table class="form-table">';
            $mode = '<select name="' . esc_attr( $this->name( 'display_mode' ) ) . '"><option value="all" ' . selected( $s['display_mode'], 'all', false ) . '>Toàn website</option><option value="selected" ' . selected( $s['display_mode'], 'selected', false ) . '>Chỉ trang/bài viết được chỉ định</option></select>';
            $this->row( 'Phạm vi', $mode );
            $this->row( 'Chỉ hiển thị ở ID', $this->text_field( 'include_ids', $s['include_ids'] ), 'Nhập ID trang/bài viết, phân cách bằng dấu phẩy. Có hiệu lực khi chọn “Chỉ trang/bài viết được chỉ định”.' );
            $this->row( 'Loại trừ ID', $this->text_field( 'exclude_ids', $s['exclude_ids'] ), 'Nhập ID trang/bài viết cần ẩn, phân cách bằng dấu phẩy.' );
            $this->row( 'Khoảng cách cạnh dưới (px)', $this->text_field( 'bottom_offset', $s['bottom_offset'], 'number' ) );
            $this->row( 'Thiết bị', $this->checkbox( 'desktop', $s['desktop'], 'Hiển thị Desktop' ) . '<br>' . $this->checkbox( 'mobile', $s['mobile'], 'Hiển thị Mobile' ) );
            $this->row( 'Z-index', $this->text_field( 'z_index', $s['z_index'], 'number' ) );
            echo '</table>';
        }

        submit_button( 'Lưu thay đổi' );
        echo '</form><div class="ocb-shortcode"><strong>Shortcode:</strong> <code>[ocean_contact_buttons]</code><p>Dùng shortcode để đặt thanh liên hệ trong nội dung. Nếu đồng thời bật tự động hiển thị, plugin chỉ xuất một thanh trên cùng trang.</p></div></div>';
    }
}
