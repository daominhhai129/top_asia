<?php
/**
 * Top Asia — Các tính năng & tinh chỉnh custom đã chuyển từ Novamira Sandbox về Theme.
 *
 * Mục đích: Đảm bảo toàn bộ website hoạt động đầy đủ và độc lập ngay cả khi tắt plugin Novamira.
 * Bao gồm 5 module đã kiểm thử ổn định:
 *   1. Cổng tải bảng giá PDF theo SĐT (taf_quote_lead CPT + AJAX + download handler)
 *   2. Tinh chỉnh CSS thanh liên hệ nổi (.ocb-root) & Off-canvas Mobile Menu
 *   3. CSS trang Hỗ trợ kỹ thuật (Page ID 670)
 *   4. CSS bảng so sánh sản phẩm (YITH WooCommerce Compare)
 *   5. Công cụ Admin & nút tạm dừng Pro ID Guard 8 giờ trên Admin Bar
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ==========================================================================
 * 1. CỔNG TẢI BẢNG GIÁ PDF THEO SỐ ĐIỆN THOẠI (trước đây: taf-quote-download-gate.php)
 * ========================================================================== */

if ( ! function_exists( 'taf_qd_catalog' ) ) {
	function taf_qd_catalog() {
		return array(
			'asia-gold'  => array(
				'label'         => 'Asia Gold',
				'paths'         => array(
					'2026/11/Bao-gia-dong-Asia-Gold-2.pdf',
					'2026/11/Bao-gia-dong-Asia-Gold-1.pdf',
					'2026/11/Bao-gia-dong-Asia-Gold.pdf',
				),
				'download_name' => 'Bao-gia-Asia-Gold.pdf',
			),
			'asia-pearl' => array(
				'label'         => 'Asia Pearl',
				'paths'         => array(
					'2026/11/Bao-gia-dong-Asia-Pearl-2.pdf',
					'2026/11/Bao-gia-dong-Asia-Pearl-1.pdf',
					'2026/11/Bao-gia-dong-Asia-Pearl.pdf',
				),
				'download_name' => 'Bao-gia-Asia-Pearl.pdf',
			),
			'asia-lite'  => array(
				'label'         => 'Asia Lite',
				'paths'         => array(
					'2026/11/Bao-gia-dong-Asia-Lite-2.pdf',
					'2026/11/Bao-gia-dong-Asia-Lite-1.pdf',
					'2026/11/Bao-gia-dong-Asia-Lite.pdf',
				),
				'download_name' => 'Bao-gia-Asia-Lite.pdf',
			),
		);
	}
}

if ( ! function_exists( 'taf_qd_register_lead_type' ) ) {
	function taf_qd_register_lead_type() {
		register_post_type(
			'taf_quote_lead',
			array(
				'labels'              => array(
					'name'          => 'Yêu cầu tải báo giá',
					'singular_name' => 'Yêu cầu tải báo giá',
					'menu_name'     => 'Yêu cầu báo giá',
					'edit_item'     => 'Xem yêu cầu tải báo giá',
					'view_item'     => 'Xem yêu cầu tải báo giá',
					'search_items'  => 'Tìm yêu cầu tải báo giá',
					'not_found'     => 'Chưa có yêu cầu tải báo giá',
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_admin_bar'   => false,
				'show_in_nav_menus'   => false,
				'show_in_rest'        => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'supports'            => array( 'title' ),
				'capability_type'     => 'taf_quote_lead',
				'map_meta_cap'        => true,
				'capabilities'        => array(
					'edit_post'              => 'edit_taf_quote_lead',
					'read_post'              => 'read_taf_quote_lead',
					'delete_post'            => 'delete_taf_quote_lead',
					'edit_posts'             => 'manage_options',
					'edit_others_posts'      => 'manage_options',
					'publish_posts'          => 'manage_options',
					'read_private_posts'     => 'manage_options',
					'delete_posts'           => 'manage_options',
					'delete_private_posts'   => 'manage_options',
					'delete_published_posts' => 'manage_options',
					'delete_others_posts'    => 'manage_options',
					'edit_private_posts'     => 'manage_options',
					'edit_published_posts'   => 'manage_options',
					'create_posts'           => 'manage_options',
				),
				'menu_icon'           => 'dashicons-download',
			)
		);
	}
	add_action( 'init', 'taf_qd_register_lead_type' );
}

if ( ! function_exists( 'taf_qd_lead_columns' ) ) {
	function taf_qd_lead_columns( $columns ) {
		return array(
			'cb'                => isset( $columns['cb'] ) ? $columns['cb'] : '',
			'title'             => 'Yêu cầu',
			'taf_qd_phone'      => 'Số điện thoại',
			'taf_qd_product'    => 'Dòng sản phẩm',
			'taf_qd_downloaded' => 'Tải xuống',
			'date'              => 'Ngày tạo',
		);
	}
	add_filter( 'manage_taf_quote_lead_posts_columns', 'taf_qd_lead_columns' );
}

if ( ! function_exists( 'taf_qd_render_lead_column' ) ) {
	function taf_qd_render_lead_column( $column, $post_id ) {
		if ( 'taf_qd_phone' === $column ) {
			echo esc_html( get_post_meta( $post_id, '_taf_qd_phone', true ) );
		} elseif ( 'taf_qd_product' === $column ) {
			echo esc_html( get_post_meta( $post_id, '_taf_qd_product_label', true ) );
		} elseif ( 'taf_qd_downloaded' === $column ) {
			$downloaded_at = get_post_meta( $post_id, '_taf_qd_downloaded_at', true );
			echo $downloaded_at ? esc_html( mysql2date( 'd/m/Y H:i', $downloaded_at ) ) : 'Chưa tải';
		}
	}
	add_action( 'manage_taf_quote_lead_posts_custom_column', 'taf_qd_render_lead_column', 10, 2 );
}

if ( ! function_exists( 'taf_qd_resolve_pdf' ) ) {
	function taf_qd_resolve_pdf( $product ) {
		$catalog = taf_qd_catalog();
		if ( ! isset( $catalog[ $product ] ) ) {
			return false;
		}

		$uploads = wp_upload_dir();
		if ( empty( $uploads['basedir'] ) ) {
			return false;
		}

		$base      = realpath( $uploads['basedir'] );
		$base_norm = false !== $base ? trailingslashit( wp_normalize_path( $base ) ) : '';
		$paths     = isset( $catalog[ $product ]['paths'] ) ? (array) $catalog[ $product ]['paths'] : array();
		if ( ! empty( $catalog[ $product ]['path'] ) ) {
			$paths[] = $catalog[ $product ]['path'];
		}

		foreach ( $paths as $rel_path ) {
			$file      = realpath( trailingslashit( $uploads['basedir'] ) . ltrim( $rel_path, '/' ) );
			$file_norm = false !== $file ? wp_normalize_path( $file ) : '';
			if ( '' !== $base_norm && '' !== $file_norm && 0 === strpos( $file_norm, $base_norm ) && is_readable( $file ) ) {
				return array( $catalog[ $product ], $file );
			}
		}

		return false;
	}
}

if ( ! function_exists( 'taf_qd_localize_home_script' ) ) {
	function taf_qd_localize_home_script() {
		if ( ! is_front_page() ) {
			return;
		}
		$config = array(
			'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
			'downloadUrl' => admin_url( 'admin-post.php' ),
			'nonce'       => wp_create_nonce( 'taf_qd_submit' ),
		);
		if ( wp_script_is( 'ta-home', 'enqueued' ) ) {
			wp_localize_script( 'ta-home', 'TAFQuoteConfig', $config );
		}
		if ( wp_script_is( 'taf-home', 'enqueued' ) ) {
			wp_localize_script( 'taf-home', 'TAFQuoteConfig', $config );
		}
	}
	add_action( 'wp_enqueue_scripts', 'taf_qd_localize_home_script', 120 );
}

if ( ! function_exists( 'taf_qd_submit_request' ) ) {
	function taf_qd_submit_request() {
		if ( 'POST' !== strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) ) {
			wp_send_json_error( array( 'message' => 'Vui lòng gửi lại yêu cầu.' ), 405 );
		}

		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'taf_qd_submit' ) ) {
			wp_send_json_error( array( 'message' => 'Phiên tải đã hết hạn. Vui lòng tải lại trang.' ), 403 );
		}

		$honeypot = isset( $_POST['taf_qd_company'] ) ? sanitize_text_field( wp_unslash( $_POST['taf_qd_company'] ) ) : '';
		if ( '' !== $honeypot ) {
			wp_send_json_error( array( 'message' => 'Không thể xử lý yêu cầu này.' ), 400 );
		}

		$phone_raw = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$phone     = preg_replace( '/\D+/', '', $phone_raw );
		if ( 0 === strpos( $phone, '84' ) ) {
			$phone = '0' . substr( $phone, 2 );
		}
		if ( ! preg_match( '/^0\d{9,10}$/', $phone ) ) {
			wp_send_json_error( array( 'message' => 'Vui lòng nhập số điện thoại hợp lệ (10–11 chữ số, bắt đầu bằng 0).' ), 400 );
		}

		$product = isset( $_POST['product'] ) ? sanitize_key( wp_unslash( $_POST['product'] ) ) : 'asia-gold';
		$catalog = taf_qd_catalog();
		if ( ! isset( $catalog[ $product ] ) ) {
			$product = 'asia-gold';
		}

		$pdf = taf_qd_resolve_pdf( $product );
		if ( false === $pdf ) {
			wp_send_json_error( array( 'message' => 'Hiện chưa tìm thấy tệp báo giá. Vui lòng thử lại sau.' ), 404 );
		}

		$lead_id = wp_insert_post(
			array(
				'post_type'      => 'taf_quote_lead',
				'post_status'    => 'private',
				'post_title'     => 'Yêu cầu tải báo giá',
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
			),
			true
		);
		if ( is_wp_error( $lead_id ) || ! $lead_id ) {
			wp_send_json_error( array( 'message' => 'Không thể lưu yêu cầu lúc này. Vui lòng thử lại.' ), 500 );
		}

		wp_update_post(
			array(
				'ID'         => (int) $lead_id,
				'post_title' => 'Yêu cầu tải báo giá #' . (int) $lead_id,
			)
		);
		update_post_meta( $lead_id, '_taf_qd_phone', $phone );
		update_post_meta( $lead_id, '_taf_qd_product', $product );
		update_post_meta( $lead_id, '_taf_qd_product_label', $catalog[ $product ]['label'] );
		update_post_meta( $lead_id, '_taf_qd_filename', $catalog[ $product ]['download_name'] );

		$token        = wp_generate_password( 48, false, false );
		$transient_id = 'taf_qd_download_' . hash( 'sha256', $token );
		$stored       = set_transient(
			$transient_id,
			array(
				'lead_id'          => (int) $lead_id,
				'product'          => $product,
				'allowed_products' => array_keys( $catalog ),
			),
			15 * MINUTE_IN_SECONDS
		);
		if ( ! $stored ) {
			wp_send_json_error( array( 'message' => 'Không thể tạo liên kết tải lúc này. Vui lòng gửi lại yêu cầu.' ), 500 );
		}

		$download_url = add_query_arg(
			array(
				'action' => 'taf_qd_download',
				'token'  => $token,
				'line'   => $product,
			),
			admin_url( 'admin-post.php' )
		);

		$downloads = array();
		foreach ( $catalog as $key => $meta ) {
			if ( false !== taf_qd_resolve_pdf( $key ) ) {
				$downloads[] = array(
					'key'   => $key,
					'label' => $meta['label'],
					'url'   => esc_url_raw(
						add_query_arg(
							array(
								'action' => 'taf_qd_download',
								'token'  => $token,
								'line'   => $key,
							),
							admin_url( 'admin-post.php' )
						)
					),
				);
			}
		}

		wp_send_json_success(
			array(
				'downloadUrl' => esc_url_raw( $download_url ),
				'downloads'   => $downloads,
			)
		);
	}
	add_action( 'wp_ajax_taf_qd_submit', 'taf_qd_submit_request' );
	add_action( 'wp_ajax_nopriv_taf_qd_submit', 'taf_qd_submit_request' );
}

if ( ! function_exists( 'taf_qd_download_pdf' ) ) {
	function taf_qd_download_pdf() {
		$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
		if ( '' === $token ) {
			wp_die( 'Liên kết tải không hợp lệ. Vui lòng gửi lại yêu cầu.', 'Không thể tải báo giá', array( 'response' => 403 ) );
		}

		$transient_id = 'taf_qd_download_' . hash( 'sha256', $token );
		$grant        = get_transient( $transient_id );
		if ( ! is_array( $grant ) || empty( $grant['product'] ) || empty( $grant['lead_id'] ) ) {
			wp_die( 'Liên kết tải đã hết hạn hoặc không hợp lệ. Vui lòng nhập lại số điện thoại.', 'Không thể tải báo giá', array( 'response' => 403 ) );
		}

		$line    = isset( $_GET['line'] ) ? sanitize_key( wp_unslash( $_GET['line'] ) ) : sanitize_key( $grant['product'] );
		$allowed = isset( $grant['allowed_products'] ) && is_array( $grant['allowed_products'] ) ? $grant['allowed_products'] : array( $grant['product'] );
		if ( ! in_array( $line, $allowed, true ) ) {
			$line = sanitize_key( $grant['product'] );
		}

		$pdf = taf_qd_resolve_pdf( $line );
		if ( false === $pdf ) {
			wp_die( 'Hiện chưa tìm thấy tệp báo giá. Vui lòng thử lại sau.', 'Không thể tải báo giá', array( 'response' => 404 ) );
		}

		$lead_id = (int) $grant['lead_id'];
		if ( 'taf_quote_lead' === get_post_type( $lead_id ) ) {
			update_post_meta( $lead_id, '_taf_qd_downloaded_at', current_time( 'mysql' ) );
		}

		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}
		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $pdf[0]['download_name'] ) . '"' );
		header( 'Content-Length: ' . filesize( $pdf[1] ) );
		header( 'X-Content-Type-Options: nosniff' );
		readfile( $pdf[1] );
		exit;
	}
	add_action( 'admin_post_taf_qd_download', 'taf_qd_download_pdf' );
	add_action( 'admin_post_nopriv_taf_qd_download', 'taf_qd_download_pdf' );
}

/* ==========================================================================
 * 2. TINH CHỈNH THANH LIÊN HỆ NỔI (.ocb-root) & MOBILE MENU BACKDROP
 *    (trước đây: topasia-proid-inspired-header.php)
 * ========================================================================== */

if ( ! function_exists( 'topasia_enqueue_header_contact_polish' ) ) {
	function topasia_enqueue_header_contact_polish() {
		$css = '
		.ocb-root .ocb-bar { gap: 8px !important; padding: 8px 12px !important; background: transparent !important; }
		.ocb-root .ocb-button { height: 48px !important; min-height: 48px !important; border-radius: 8px !important; box-shadow: 0 6px 18px rgba(11,29,58,.16) !important; }
		.ocb-root .ocb-call { background: #D92F35 !important; color: #fff !important; }
		.ocb-root .ocb-zalo, .ocb-root .ocb-messenger { background: #142B49 !important; color: #fff !important; }
		.ocb-root .ocb-messenger svg path:last-child { stroke: #fff !important; }
		@media (max-width: 1023px) {
			.mfp-bg.off-canvas.mfp-ready { background: rgba(11,29,58,.58) !important; backdrop-filter: blur(3px); -webkit-backdrop-filter: blur(3px); }
			.mfp-wrap.off-canvas:has(#main-menu) { box-shadow: 20px 0 64px rgba(11,29,58,.2); overscroll-behavior: contain; }
			body:has(.mfp-wrap.off-canvas.mfp-ready #main-menu) .ocb-root { opacity: 0 !important; visibility: hidden !important; pointer-events: none !important; transform: translateY(10px); transition: opacity .2s ease, transform .2s ease, visibility .2s ease; }
		}
		';
		wp_register_style( 'topasia-proid-inspired-header', false, array(), '1.1' );
		wp_enqueue_style( 'topasia-proid-inspired-header' );
		wp_add_inline_style( 'topasia-proid-inspired-header', $css );
	}
	add_action( 'wp_enqueue_scripts', 'topasia_enqueue_header_contact_polish', 1000 );
}

/* ==========================================================================
 * 3. GIAO DIỆN TRANG HỖ TRỢ KỸ THUẬT (Page ID 670)
 *    (trước đây: topasia-support-content.php)
 * ========================================================================== */

if ( ! function_exists( 'topasia_enqueue_support_content_styles' ) ) {
	function topasia_enqueue_support_content_styles() {
		if ( ! is_page( 670 ) ) {
			return;
		}
		$css = '
		.ta-support-content { color: #0B1D3A; }
		.ta-support-content .ta-support-eyebrow { color: #E31E24; font-size: 13px; font-weight: 700; letter-spacing: .08em; }
		.ta-support-content h2 { color: #0B1D3A; font-weight: 800; margin-bottom: 14px; }
		.ta-support-content h3 { color: #0B1D3A; font-weight: 700; font-size: 18px; }
		.ta-support-card, .ta-support-resource { height: 100%; padding: 26px; background: #fff; border: 1px solid #F7F8FA; border-radius: 13px; box-shadow: 0 8px 24px rgba(11,29,58,.06); }
		.ta-support-card { border-top: 3px solid #E31E24; }
		.ta-support-card p, .ta-support-resource p { color: #6B7280; line-height: 1.75; }
		.ta-support-content .button.primary { background: #E31E24; border-color: #E31E24; border-radius: 8px; }
		.ta-support-content .button.is-outline { color: #0B1D3A; border-color: #0B1D3A; border-radius: 8px; }
		@media (max-width: 549px) { .ta-support-card, .ta-support-resource { padding: 20px; } }
		';
		wp_register_style( 'topasia-support-content', false, array(), '1.0' );
		wp_enqueue_style( 'topasia-support-content' );
		wp_add_inline_style( 'topasia-support-content', $css );
	}
	add_action( 'wp_enqueue_scripts', 'topasia_enqueue_support_content_styles', 99 );
}

/* ==========================================================================
 * 4. GIAO DIỆN BẢNG SO SÁNH SẢN PHẨM (YITH WooCompare)
 *    (trước đây: topasia-visual-alignment.php)
 * ========================================================================== */

if ( ! function_exists( 'topasia_enqueue_visual_alignment_styles' ) ) {
	function topasia_enqueue_visual_alignment_styles() {
		$css = '
		/* Comparison table */
		.yith-woocompare-table th,
		.yith-woocompare-table .product_title,
		#yith-woocompare table th { color: #0B1D3A; }
		.yith-woocompare-table tr:nth-child(even) th,
		.yith-woocompare-table tr:nth-child(even) td,
		#yith-woocompare table tr:nth-child(even) th,
		#yith-woocompare table tr:nth-child(even) td { background: #F7F8FA; }
		.yith-woocompare-table a.button,
		#yith-woocompare table a.button { background: #0B1D3A; border-color: #0B1D3A; color: #fff; }
		.yith-woocompare-table a.button:hover,
		#yith-woocompare table a.button:hover { background: #132A52; border-color: #132A52; }
		.yith-woocompare-table a.compare,
		#yith-woocompare table a.compare { background: #E31E24; border-color: #E31E24; color: #fff; }
		.yith-woocompare-table a.remove,
		#yith-woocompare table a.remove { color: #E31E24; }
		';
		wp_register_style( 'topasia-visual-alignment', false, array(), '1.0' );
		wp_enqueue_style( 'topasia-visual-alignment' );
		wp_add_inline_style( 'topasia-visual-alignment', $css );
	}
	add_action( 'wp_enqueue_scripts', 'topasia_enqueue_visual_alignment_styles', 99 );
}

/* ==========================================================================
 * 5. CÔNG CỤ ADMIN & TẠM DỪNG PRO ID GUARD 8 GIỜ
 *    (trước đây: topasia-staging-admin-tools.php)
 * ========================================================================== */

if ( ! function_exists( 'topasia_staging_hide_proid_loader_row' ) ) {
	add_filter( 'plugins_list', 'topasia_staging_hide_proid_loader_row', 100 );
	function topasia_staging_hide_proid_loader_row( array $plugins ): array {
		unset( $plugins['mustuse']['00-proid-guard-loader.php'] );

		return $plugins;
	}
}

if ( ! function_exists( 'topasia_proid_guard_pause_deadline' ) ) {
	function topasia_proid_guard_pause_deadline(): int {
		static $initialized = false;
		static $deadline    = 0;

		if ( $initialized ) {
			return $deadline;
		}

		$initialized = true;
		$deadline    = (int) get_option( 'topasia_proid_guard_pause_until', 0 );

		if ( $deadline <= time() ) {
			if ( $deadline > 0 ) {
				delete_option( 'topasia_proid_guard_pause_until' );
			}
			$deadline = 0;
		}

		return $deadline;
	}
}

if ( ! function_exists( 'topasia_proid_guard_remove_callbacks' ) ) {
	function topasia_proid_guard_remove_callbacks(): void {
		global $wp_filter;

		if ( ! isset( $wp_filter ) || ! is_array( $wp_filter ) ) {
			return;
		}

		$plugin_root = trailingslashit( wp_normalize_path( WP_PLUGIN_DIR . '/proid-guard' ) );

		foreach ( $wp_filter as $hook_name => $hook ) {
			if ( ! $hook instanceof WP_Hook ) {
				continue;
			}

			foreach ( $hook->callbacks as $priority => $callbacks ) {
				foreach ( $callbacks as $entry ) {
					$callback = $entry['function'] ?? null;

					try {
						if ( $callback instanceof Closure ) {
							$reflection = new ReflectionFunction( $callback );
						} elseif ( is_array( $callback ) && count( $callback ) === 2 ) {
							$reflection = new ReflectionMethod( $callback[0], $callback[1] );
						} elseif ( is_string( $callback ) && strpos( $callback, '::' ) !== false ) {
							$reflection = new ReflectionMethod( $callback );
						} elseif ( is_string( $callback ) && function_exists( $callback ) ) {
							$reflection = new ReflectionFunction( $callback );
						} elseif ( is_object( $callback ) && is_callable( $callback ) ) {
							$reflection = new ReflectionMethod( $callback, '__invoke' );
						} else {
							continue;
						}
					} catch ( Throwable $error ) {
						continue;
					}

					$source_file = $reflection->getFileName();
					if ( ! is_string( $source_file ) ) {
						continue;
					}

					if ( strpos( wp_normalize_path( $source_file ), $plugin_root ) === 0 ) {
						remove_filter( (string) $hook_name, $callback, (int) $priority );
					}
				}
			}
		}
	}
}

if ( ! function_exists( 'topasia_proid_guard_action_url' ) ) {
	function topasia_proid_guard_action_url( string $action ): string {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=' . rawurlencode( $action ) ),
			$action
		);
	}
}

if ( ! function_exists( 'topasia_proid_guard_admin_bar_controls' ) ) {
	function topasia_proid_guard_admin_bar_controls( $admin_bar ): void {
		if ( ! current_user_can( 'manage_options' ) || ! is_object( $admin_bar ) || ! method_exists( $admin_bar, 'add_node' ) ) {
			return;
		}

		$deadline = topasia_proid_guard_pause_deadline();

		if ( $deadline > 0 ) {
			$admin_bar->add_node(
				array(
					'id'    => 'proid-guard-badge',
					'title' => 'Pro ID Guard: TẠM DỪNG',
					'href'  => admin_url( 'index.php' ),
					'meta'  => array( 'title' => 'Tự hoạt động lại lúc ' . wp_date( 'H:i d/m/Y', $deadline ) ),
				)
			);
			$action = 'topasia_proid_guard_resume_now';
			$title  = 'Bật lại Pro ID Guard ngay';
		} else {
			$action = 'topasia_proid_guard_pause_8h';
			$title  = 'Tạm dừng 8 giờ';
		}

		$admin_bar->add_node(
			array(
				'id'     => 'topasia-proid-guard-pause-control',
				'parent' => 'proid-guard-badge',
				'title'  => $title,
				'href'   => topasia_proid_guard_action_url( $action ),
				'meta'   => array( 'title' => 'Tạm ngừng các hook của Pro ID Guard; plugin vẫn Active.' ),
			)
		);
	}
	add_action( 'admin_bar_menu', 'topasia_proid_guard_admin_bar_controls', 1000 );
}

if ( ! function_exists( 'topasia_proid_guard_pause_notice' ) ) {
	function topasia_proid_guard_pause_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$deadline = topasia_proid_guard_pause_deadline();
		if ( $deadline <= 0 ) {
			return;
		}

		echo '<div class="notice notice-warning"><p><strong>Pro ID Guard đang tạm dừng.</strong> Các hook bảo vệ sẽ tự hoạt động lại lúc '
			. esc_html( wp_date( 'H:i d/m/Y', $deadline ) )
			. '. Plugin vẫn Active; cài đặt khóa sửa file trong quản trị vẫn giữ nguyên. <a href="'
			. esc_url( topasia_proid_guard_action_url( 'topasia_proid_guard_resume_now' ) )
			. '">Bật lại ngay</a></p></div>';
	}
	add_action( 'admin_notices', 'topasia_proid_guard_pause_notice', 1 );
}

if ( ! function_exists( 'topasia_proid_guard_finish_admin_action' ) ) {
	function topasia_proid_guard_finish_admin_action(): void {
		$redirect = wp_get_referer();
		if ( ! $redirect ) {
			$redirect = admin_url( 'index.php' );
		}

		$redirect = remove_query_arg( array( '_wpnonce', 'action' ), $redirect );
		wp_safe_redirect( $redirect );
		exit;
	}
}

if ( ! function_exists( 'topasia_proid_guard_pause_8h' ) ) {
	function topasia_proid_guard_pause_8h(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Bạn không có quyền thực hiện thao tác này.', 'Không đủ quyền', array( 'response' => 403 ) );
		}

		check_admin_referer( 'topasia_proid_guard_pause_8h' );
		update_option( 'topasia_proid_guard_pause_until', time() + ( 8 * HOUR_IN_SECONDS ), false );
		topasia_proid_guard_finish_admin_action();
	}
	add_action( 'admin_post_topasia_proid_guard_pause_8h', 'topasia_proid_guard_pause_8h' );
}

if ( ! function_exists( 'topasia_proid_guard_resume_now' ) ) {
	function topasia_proid_guard_resume_now(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Bạn không có quyền thực hiện thao tác này.', 'Không đủ quyền', array( 'response' => 403 ) );
		}

		check_admin_referer( 'topasia_proid_guard_resume_now' );
		delete_option( 'topasia_proid_guard_pause_until' );
		topasia_proid_guard_finish_admin_action();
	}
	add_action( 'admin_post_topasia_proid_guard_resume_now', 'topasia_proid_guard_resume_now' );
}

if ( topasia_proid_guard_pause_deadline() > 0 ) {
	topasia_proid_guard_remove_callbacks();
}
