<?php
/**
 * Top Asia — SEO H1 cho trang chuyên mục + dọn H1 thừa ở hero trang chủ.
 *
 * Vị trí: wp-content/themes/giao-dien/inc/topasia-h1.php
 * Nạp bởi: functions.php  ->  require_once __DIR__ . '/inc/topasia-h1.php';
 *
 * Phiên bản: 2.1.0 (bản WPCode 2.1.0 chuyển sang theme, giữ nguyên logic)
 *
 * Chức năng:
 * - Thêm H1 cho trang Blog chính (Posts page).
 * - Thêm H1 cho các chuyên mục bài viết thuộc menu Dự án / Tin tức.
 * - Tự thêm H1 cho chuyên mục mới chưa khai báo (FALLBACK_ALL_CATEGORIES).
 * - Thêm H1 cho các danh mục sản phẩm được cấu hình.
 * - Trang nào đã có H1 thì KHÔNG thêm H1 thứ hai.
 * - Trang chủ: giữ H1 hero đầu tiên, hạ các H1 hero còn lại xuống H2.
 *
 * Không can thiệp: UX Builder, Customizer, admin, AJAX, REST, CRON, feed, robots.
 *
 * Chống xung đột:
 * - Toàn bộ nằm trong class TopAsia_SEO_H1, bọc bởi class_exists() nên nếu
 *   snippet WPCode cũ vẫn bật thì chỉ một bản chạy, không fatal.
 * - Không khai báo hàm global nào -> không đụng tiền tố topasia_menu_* / topasia_*
 *   của inc/topasia-menu.php, inc/home.php và functions.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'TopAsia_SEO_H1' ) ) :

class TopAsia_SEO_H1 {

	/* ==================================================================
	 * 1. CẤU HÌNH — chỉ sửa trong khối này
	 * ================================================================== */

	/** true = H1 hiển thị bình thường. false = ẩn kiểu screen-reader. */
	const VISIBLE = true;

	/** true = giữ H1 hero đầu tiên, hạ các H1 hero còn lại xuống H2. */
	const KEEP_FIRST_HERO_H1 = true;

	/** Class của các H1 trong hero slider trang chủ. */
	const HERO_H1_CLASS = 'taf-hero-h1';

	/** Khoảng cách (px): trên / dưới, desktop / mobile. */
	const GAP_BOTTOM        = 40;
	const GAP_BOTTOM_MOBILE = 24;
	const GAP_TOP           = 32;
	const GAP_TOP_MOBILE    = 20;

	/** Căn chữ H1: 'center' hoặc 'left'. */
	const ALIGN = 'center';

	/** H1 cho trang danh sách bài viết (Posts page). '' = không thêm. */
	const BLOG_INDEX_H1 = 'Tin tức Top Asia';

	/** Chuyên mục chưa khai báo vẫn được thêm H1 = tên chuyên mục. */
	const FALLBACK_ALL_CATEGORIES = true;

	/** Chuyên mục bài viết: slug => nội dung H1. */
	private static function blog_categories() {
		return array(
			// Menu "Dự án"
			'du-an'                => 'Dự án Top Asia',
			'cong-trinh-thuc-te'   => 'Công trình thực tế',
			'cong-trinh-tieu-bieu' => 'Công trình tiêu biểu',

			// Menu "Tin tức"
			'tin-tuc-bao-gia'      => 'Tin tức & Báo giá',
			'kien-thuc-ky-thuat'   => 'Kiến thức kỹ thuật',
			'top-asia-cap-nhat'    => 'Top Asia cập nhật',

			// Ngoài menu nhưng vẫn được index
			'khong-phan-loai'      => 'Bài viết chưa phân loại',
		);
	}

	/** Danh mục sản phẩm (product_cat): slug => nội dung H1. */
	private static function product_categories() {
		return array(
			'mai-che-canopy'          => 'Mái che Canopy Polycarbonate Top Asia',
			'phu-kien-mai-che-canopy' => 'Phụ kiện mái che Canopy',
			'phu-kien-thi-cong'       => 'Phụ kiện thi công tấm Polycarbonate',
		);
	}

	/* ==================================================================
	 * 2. KHỞI TẠO
	 * ================================================================== */

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_start_buffer' ), 1 );
		add_action( 'wp_head', array( __CLASS__, 'print_css' ), 99 );
	}

	/* ==================================================================
	 * 3. PHÁT HIỆN UX BUILDER / EDITOR
	 * ================================================================== */

	private static function is_builder_request() {

		if ( function_exists( 'ux_builder_is_active' ) && ux_builder_is_active() ) {
			return true;
		}

		if ( function_exists( 'is_customize_preview' ) && is_customize_preview() ) {
			return true;
		}

		$builder_get_keys = array( 'uxb_iframe', 'uxb', 'ux_builder', 'flatsome_builder', 'app' );

		foreach ( $builder_get_keys as $key ) {
			if ( isset( $_GET[ $key ] ) ) {
				return true;
			}
		}

		if ( isset( $_GET['page'] ) && is_string( $_GET['page'] ) ) {
			$page = sanitize_key( wp_unslash( $_GET['page'] ) );
			if ( 'uxbuilder' === $page || 'ux-builder' === $page ) {
				return true;
			}
		}

		if ( isset( $_REQUEST['ux_builder_action'] ) ) {
			return true;
		}

		if ( isset( $_REQUEST['action'] ) && is_string( $_REQUEST['action'] ) ) {
			$action = sanitize_key( wp_unslash( $_REQUEST['action'] ) );
			if ( false !== strpos( $action, 'ux_builder' ) || false !== strpos( $action, 'uxbuilder' ) ) {
				return true;
			}
		}

		$request_uri = '';

		if ( isset( $_SERVER['REQUEST_URI'] ) && is_string( $_SERVER['REQUEST_URI'] ) ) {
			$request_uri = wp_unslash( $_SERVER['REQUEST_URI'] );
		}

		if ( $request_uri ) {
			$builder_markers = array( 'uxbuilder', 'ux-builder', 'uxb_iframe', 'ux_builder' );
			foreach ( $builder_markers as $marker ) {
				if ( false !== stripos( $request_uri, $marker ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/* ==================================================================
	 * 4. H1 CỦA TRANG HIỆN TẠI
	 * ================================================================== */

	private static function target_h1_text() {

		if ( is_home() && ! is_front_page() ) {
			return ( '' === self::BLOG_INDEX_H1 ) ? false : self::BLOG_INDEX_H1;
		}

		if ( is_category() ) {

			$term = get_queried_object();

			if ( ! $term || ! isset( $term->slug ) ) {
				return false;
			}

			$map = self::blog_categories();

			if ( isset( $map[ $term->slug ] ) ) {
				return $map[ $term->slug ];
			}

			if ( self::FALLBACK_ALL_CATEGORIES && ! empty( $term->name ) ) {
				return $term->name;
			}

			return false;
		}

		if ( function_exists( 'is_product_category' ) && is_product_category() ) {

			$term = get_queried_object();

			if ( $term && isset( $term->slug ) ) {
				$map = self::product_categories();
				if ( isset( $map[ $term->slug ] ) ) {
					return $map[ $term->slug ];
				}
			}
		}

		return false;
	}

	/* ==================================================================
	 * 5. KHỞI TẠO OUTPUT BUFFER
	 * ================================================================== */

	public static function maybe_start_buffer() {

		if ( is_admin() ) {
			return;
		}

		if ( is_feed() || is_embed() ) {
			return;
		}

		if ( function_exists( 'is_robots' ) && is_robots() ) {
			return;
		}

		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
			return;
		}

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return;
		}

		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			return;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}

		if ( self::is_builder_request() ) {
			return;
		}

		if ( ! is_front_page() && false === self::target_h1_text() ) {
			return;
		}

		ob_start( array( __CLASS__, 'filter_output' ) );
	}

	/* ==================================================================
	 * 6. XỬ LÝ HTML
	 * ================================================================== */

	public static function filter_output( $html ) {

		if ( self::is_builder_request() ) {
			return $html;
		}

		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
			return $html;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return $html;
		}

		if ( ! is_string( $html ) || strlen( $html ) < 200 ) {
			return $html;
		}

		if ( stripos( $html, '</body>' ) === false ) {
			return $html;
		}

		if ( is_front_page() ) {
			return self::demote_hero_h1( $html );
		}

		$text = self::target_h1_text();

		if ( false === $text ) {
			return $html;
		}

		if ( preg_match( '#<h1[\s>]#i', $html ) ) {
			return $html;
		}

		$tag = '<h1 class="ta-archive-h1">' . esc_html( $text ) . '</h1>';

		$anchors = array(
			'#(<div[^>]+id=["\']content["\'][^>]*>)#i',
			'#(<main[^>]+id=["\']main["\'][^>]*>)#i',
			'#(<body[^>]*>)#i',
		);

		foreach ( $anchors as $pattern ) {

			$count = 0;

			$result = preg_replace( $pattern, '$1' . $tag, $html, 1, $count );

			if ( $count > 0 && null !== $result ) {
				return $result;
			}
		}

		return $html;
	}

	/* ==================================================================
	 * 7. XỬ LÝ H1 HERO TRANG CHỦ
	 * ================================================================== */

	private static function demote_hero_h1( $html ) {

		$class = preg_quote( self::HERO_H1_CLASS, '#' );

		$pattern = '#<h1([^>]*\bclass=(["\'])[^"\']*' . $class . '[^"\']*\2[^>]*)>(.*?)</h1>#is';

		$index = 0;
		$keep  = self::KEEP_FIRST_HERO_H1;

		$result = preg_replace_callback(
			$pattern,
			function ( $m ) use ( &$index, $keep ) {

				$index++;

				if ( $keep && 1 === $index ) {
					return $m[0];
				}

				return '<h2' . $m[1] . '>' . $m[3] . '</h2>';
			},
			$html
		);

		return ( null === $result ) ? $html : $result;
	}

	/* ==================================================================
	 * 8. CSS H1 ARCHIVE
	 * ================================================================== */

	public static function print_css() {

		if ( self::is_builder_request() ) {
			return;
		}

		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
			return;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}

		if ( false === self::target_h1_text() ) {
			return;
		}

		if ( self::VISIBLE ) {

			$css = '.ta-archive-h1{'
				. 'font-size:28px;line-height:1.3;font-weight:600;'
				. 'margin:' . (int) self::GAP_TOP . 'px auto ' . (int) self::GAP_BOTTOM . 'px;'
				. 'padding:0 15px;max-width:1080px;'
				. 'text-align:' . ( 'left' === self::ALIGN ? 'left' : 'center' ) . ';'
				. '}'
				. '@media(max-width:549px){.ta-archive-h1{'
				. 'font-size:22px;'
				. 'margin:' . (int) self::GAP_TOP_MOBILE . 'px auto ' . (int) self::GAP_BOTTOM_MOBILE . 'px;'
				. '}}';

		} else {

			$css = '.ta-archive-h1{position:absolute;width:1px;height:1px;padding:0;margin:-1px;'
				. 'overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap;border:0;}';
		}

		echo "\n" . '<style id="ta-archive-h1-css">' . $css . '</style>' . "\n";
	}
}

/* ======================================================================
 * 9. KHỞI CHẠY
 * ====================================================================== */

TopAsia_SEO_H1::init();

endif;
