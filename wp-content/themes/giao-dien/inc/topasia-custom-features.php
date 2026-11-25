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
 * 1. CỔNG TẢI BẢNG GIÁ PDF THEO SỐ ĐIỆN THOẠI & BACKEND MODEL
 *    Đã nâng cấp sang module inc/topasia-quote-leads.php (lưu bảng DB riêng + CRM)
 * ========================================================================== */
if ( ! defined( 'TOPASIA_QUOTE_LEADS_LOADED' ) ) {
	$topasia_ql_file = __DIR__ . '/topasia-quote-leads.php';
	if ( file_exists( $topasia_ql_file ) ) {
		require_once $topasia_ql_file;
	}
	unset( $topasia_ql_file );
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
		.ocb-root .ocb-call { background: #16A34A !important; color: #fff !important; }
		.ocb-root .ocb-call:hover { background: #15803D !important; }
		.ocb-root .ocb-zalo { background: #0068FF !important; color: #fff !important; }
		.ocb-root .ocb-zalo:hover { background: #0052CC !important; }
		.ocb-root .ocb-messenger { background: #0866FF !important; color: #fff !important; }
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
