<?php
// Add custom Theme Functions here

// Top Asia — custom primary navigation (desktop mega menu + mobile off-canvas accordion).
require_once __DIR__ . '/inc/topasia-menu.php';

// Top Asia — homepage redesign assets (front page only).
require_once __DIR__ . '/inc/home.php';

// Top Asia — H1 cho trang chuyên mục + dọn H1 thừa ở hero trang chủ.
// Dùng file_exists() để nếu chưa upload inc/topasia-h1.php thì site vẫn chạy bình thường
// (require_once thẳng vào file không tồn tại sẽ gây fatal error, trắng site).
$topasia_h1_file = __DIR__ . '/inc/topasia-h1.php';
if ( file_exists( $topasia_h1_file ) ) {
	require_once $topasia_h1_file;
}
unset( $topasia_h1_file );


add_filter('use_block_editor_for_post', '__return_false');


 
function ilc_mce_buttons($buttons){
  array_push($buttons,
     "backcolor",
     "anchor",
     "hr",
     "sub",
     "sup",
     "fontselect",
     "fontsizeselect",
     "styleselect",
     "cleanup"
);
  return $buttons;
}
add_filter("mce_buttons", "ilc_mce_buttons");






//Thay đổi Giao Diện - Logo cho trang đăng nhập

add_action( 'login_enqueue_scripts', 'giuseart_login_enqueue_scripts' );
function giuseart_login_enqueue_scripts(){
echo '<style type="text/css" media="screen">';
// FIX: khối CSS cũ thiếu dấu } đóng nên nuốt luôn các rule phía sau.
echo '#login h1 a{background-image:url(https://i.ibb.co/C9v38Dd/logo.png);}';
echo '</style>';
}

function tp_custom_logo() { ?>
    <style type="text/css">
 .login #login_error{    box-shadow: 0 30px 80px rgba(37, 37, 37, 0.1) !important;    border-radius: 15px !important;}
 .login .message{border-left: 4px solid #ec1f27 !important; border-radius: 15px;box-shadow: 0 30px 80px rgba(37, 37, 37, 0.1) !important;}
 .login #nav a:hover, .login #backtoblog a:hover{color: #eb1f27 !important;}
 .login .button-primary {background: #eb1f27 !important;border-color: #eb1f27 #eb1f27 #eb1f27 !important;box-shadow: 0 1px 0 #eb1f27 !important;border-radius: 20px !important;color: #fff !importanttext-decoration: none;text-shadow: none !important;}
 input[type=checkbox]:checked:before{color: #eb1f27;}
 .login input[type=text]:focus, .login input[type=password]:focus, input[type=email]:focus{    box-shadow: 0 0 2px rgb(255, 255, 255) !important;}
   .login h1 a{    background-size: 115px !important;     height: 115px !important; width: 209px !important; }
#login {width: 450px !important;}
 .login form {box-shadow: 0 30px 80px rgba(37, 37, 37, 0.1) !important;border-radius: 15px;    padding: 40px !important;}.login form .input, .login form input[type=checkbox], .login input[type=text] {background: #f5f5f5;border-radius: 30px;border: none;box-shadow: none;}
 @media only screen and (max-width: 480px) {
  #login {width: 320px !important;}
}
    </style>
<?php }
add_action('login_enqueue_scripts', 'tp_custom_logo');

//Thay đổi Giao Diện - Logo cho trang đăng nhập
// Tạo shortcode hiển thị breadcrumb
add_shortcode('f_breadcrumbs', function() {
    ob_start();
    woocommerce_breadcrumb();
    return ob_get_clean();
});

// tạo poup trang web
/**
 * Popup tư vấn toàn website
 * Không hiển thị ở Giỏ hàng và Thanh toán
 * Không chạy trong UX Builder / Customizer / admin / AJAX (gây kẹt UX Builder).
 */
add_action('wp_footer', 'topasia_popup_form');

function topasia_popup_form() {

    // FIX 1 — không chạy trong admin, AJAX, Customizer.
    if ( is_admin() || wp_doing_ajax() || is_customize_preview() ) {
        return;
    }

    // FIX 2 — không chạy trong iframe/app của UX Builder (Flatsome).
    if ( isset( $_GET['app'] ) || isset( $_GET['uxb_iframe'] ) || isset( $_GET['ux_builder'] ) ) {
        return;
    }

    // FIX 3 — bọc is_cart()/is_checkout() trong function_exists để không fatal
    // khi WooCommerce bị tắt (ví dụ lúc test lỗi bằng cách deactivate plugin).
    if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() ) ) {
        return;
    }
?>
    <!-- Popup hidden -->
    <div id="topasia-popup-form" style="display:none; max-width:700px;">
        <?php echo do_shortcode('[block id="popup-form-tu-van"]'); ?>
    </div>

    <script>
    jQuery(document).ready(function($){

        // FIX 4 — chặn lỗi JS nếu Magnific Popup chưa được nạp trên trang.
        if ( ! $.magnificPopup ) {
            return;
        }

        // Chỉ hiện lại sau 60 giây (60000 ms).
        // Muốn 1 lần / 24h thì đổi 60000 thành 86400000.
        var popupShown = localStorage.getItem('topasia_popup');

        if(popupShown){
            var diff = Date.now() - parseInt(popupShown);

            if(diff < 60000){
                return;
            }
        }

        setTimeout(function(){

            $.magnificPopup.open({
                items: {
                    src: '#topasia-popup-form'
                },
                type: 'inline',
                removalDelay: 300
            });

            localStorage.setItem(
                'topasia_popup',
                Date.now()
            );

        },7000);

    });
    </script>
<?php
}

/* ==========================================================================
 * TOP ASIA DESIGN SYSTEM — global subpages, products & news (2026-11)
 * ========================================================================== */

// 1. Google Sans cho toàn site (CSS design system nằm cuối style.css).
add_action( 'wp_enqueue_scripts', function () {
	if ( is_admin() || isset( $_GET['app'] ) || isset( $_GET['uxb_iframe'] ) ) {
		return;
	}
	wp_enqueue_style( 'topasia-google-sans', 'https://fonts.googleapis.com/css2?family=Google+Sans:opsz,wght@17..18,400..700&display=swap', array(), null );
}, 5 );

// 2. Body class khi sidebar shop / blog trống -> CSS ẩn cột trống.
add_filter( 'body_class', function ( $classes ) {
	if ( ! is_active_sidebar( 'shop-sidebar' ) ) {
		$classes[] = 'ta-no-shop-sidebar';
	}
	if ( ! is_active_sidebar( 'sidebar-main' ) ) {
		$classes[] = 'ta-no-post-sidebar';
	}
	return $classes;
} );

// 3. Ẩn shortcode thô [ez-toc ...] / [rank_math_breadcrumb] bị in ra trang.
add_action( 'init', function () {
	if ( ! shortcode_exists( 'ez-toc' ) ) {
		add_shortcode( 'ez-toc', '__return_empty_string' );
	}
	if ( ! shortcode_exists( 'rank_math_breadcrumb' ) ) {
		add_shortcode( 'rank_math_breadcrumb', '__return_empty_string' );
	}
}, 20 );
add_filter( 'the_content', function ( $content ) {
	if ( is_admin() || false === strpos( $content, '[' ) ) {
		return $content;
	}
	$content = preg_replace( '/<p>\s*\[(?:ez-toc|rank_math_breadcrumb)[^\]]*\]\s*<\/p>/iu', '', $content );
	return preg_replace( '/\[(?:ez-toc|rank_math_breadcrumb)[^\]]*\]/iu', '', $content );
}, 20 );

// 4. Link chuyên mục trong menu (/tin-tuc-bao-gia/, /kien-thuc-ky-thuat/,
//    /top-asia-cap-nhat/, /cong-trinh-thuc-te/) bị 404 khi permalink còn /category/
//    -> chuyển hướng 301 về đúng link chuyên mục.
add_action( 'template_redirect', function () {
	if ( ! is_404() ) {
		return;
	}
	$path = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '';
	$slug = basename( untrailingslashit( (string) $path ) );
	$allowed = array( 'tin-tuc-bao-gia', 'kien-thuc-ky-thuat', 'top-asia-cap-nhat', 'cong-trinh-thuc-te', 'cong-trinh-tieu-bieu' );
	if ( ! in_array( $slug, $allowed, true ) ) {
		return;
	}
	$term = get_category_by_slug( $slug );
	if ( $term && ! is_wp_error( $term ) ) {
		wp_safe_redirect( get_category_link( $term->term_id ), 301 );
		exit;
	}
}, 1 );

// 5. H1 cho danh mục sản phẩm chưa có H1 (Asia Gold / Pearl / Lite...).
add_action( 'woocommerce_archive_description', function () {
	if ( ! function_exists( 'is_product_category' ) || ! is_product_category() ) {
		return;
	}
	$term = get_queried_object();
	if ( ! $term || empty( $term->name ) ) {
		return;
	}
	// Các slug này đã có H1 từ inc/topasia-h1.php.
	if ( in_array( $term->slug, array( 'mai-che-canopy', 'phu-kien-mai-che-canopy', 'phu-kien-thi-cong' ), true ) ) {
		return;
	}
	echo '<h1 class="ta-archive-h1 ta-archive-h1--product">' . esc_html( $term->name ) . '</h1>';
}, 5 );

// 6. Cache-busting: style.css của child theme dùng filemtime làm ?ver=
//    (Cloudflare cache ?ver=4.13 trong 7 ngày nên sửa CSS không hiện ra).
add_filter( 'style_loader_src', function ( $src ) {
	if ( false === strpos( $src, '/themes/giao-dien/style.css' ) ) {
		return $src;
	}
	$file = get_stylesheet_directory() . '/style.css';
	if ( file_exists( $file ) ) {
		$src = add_query_arg( 'ver', (string) filemtime( $file ), remove_query_arg( 'ver', $src ) );
	}
	return $src;
}, 99 );

// 7. Trang dựng bằng HTML (.tap): tắt wpautop để không chèn <p>/<br> làm vỡ layout.
add_filter( 'the_content', function ( $content ) {
	if ( false !== strpos( $content, 'class="tap ' ) || false !== strpos( $content, 'class="tap"' ) ) {
		remove_filter( 'the_content', 'wpautop' );
		remove_filter( 'the_content', 'wptexturize' );
	}
	return $content;
}, 1 );

// 8. Shortcode [ta_posts cat="slug1,slug2" count="9" cols="3" more="/du-an/" more_label="Xem tất cả"]
//    Hiển thị bài viết dạng card theo design system Top Asia.
add_shortcode( 'ta_posts', function ( $atts ) {
	$a = shortcode_atts( array(
		'cat'        => '',
		'count'      => 9,
		'cols'       => 3,
		'more'       => '',
		'more_label' => 'Xem tất cả',
		'empty'      => 'Nội dung đang được cập nhật.',
	), $atts, 'ta_posts' );

	$args = array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'posts_per_page'      => max( 1, (int) $a['count'] ),
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	);
	if ( '' !== trim( $a['cat'] ) ) {
		$args['category_name'] = sanitize_text_field( $a['cat'] );
	}
	$q    = new WP_Query( $args );
	$cols = in_array( (int) $a['cols'], array( 2, 3, 4 ), true ) ? (int) $a['cols'] : 3;
	$cls  = 'tap-posts' . ( 3 !== $cols ? ' tap-posts--' . $cols : '' );

	ob_start();
	if ( $q->have_posts() ) {
		echo '<div class="' . esc_attr( $cls ) . '">';
		while ( $q->have_posts() ) {
			$q->the_post();
			$cats  = get_the_category();
			$label = $cats ? $cats[0]->name : '';
			echo '<a class="tap-post" href="' . esc_url( get_permalink() ) . '">';
			echo '<div class="tap-post-img">';
			if ( has_post_thumbnail() ) {
				the_post_thumbnail( 'medium_large', array( 'loading' => 'lazy', 'alt' => esc_attr( get_the_title() ) ) );
			}
			echo '</div><div class="tap-post-body">';
			if ( $label ) {
				echo '<span class="tap-tag">' . esc_html( $label ) . '</span>';
			}
			echo '<h3>' . esc_html( get_the_title() ) . '</h3>';
			echo '<p>' . esc_html( wp_trim_words( get_the_excerpt(), 22, '…' ) ) . '</p>';
			echo '<div class="tap-post-meta"><span>' . esc_html( get_the_date( 'd/m/Y' ) ) . '</span><b>Xem chi tiết →</b></div>';
			echo '</div></a>';
		}
		echo '</div>';
		wp_reset_postdata();
	} else {
		echo '<div class="tap-empty">' . esc_html( $a['empty'] ) . '</div>';
	}
	if ( $a['more'] ) {
		echo '<div class="tap-center"><a class="tap-btn" href="' . esc_url( home_url( $a['more'] ) ) . '">' . esc_html( $a['more_label'] ) . '</a></div>';
	}
	return ob_get_clean();
} );


// Top Asia — floating contact buttons (Phone & Zalo: 0896 699 996).
if ( ! function_exists( 'topasia_render_floating_contact' ) ) {
	function topasia_render_floating_contact() {
		if ( is_admin() || wp_doing_ajax() || is_customize_preview() ) {
			return;
		}
		if ( isset( $_GET['app'] ) || isset( $_GET['uxb_iframe'] ) || isset( $_GET['ux_builder'] ) ) {
			return;
		}
		if ( defined( 'OCB_VERSION' ) || class_exists( 'Ocean_Contact_Buttons' ) ) {
			return;
		}

		$phone_raw     = '0896699996';
		$phone_display = '0896 699 996';
		$zalo_url      = 'https://zalo.me/' . $phone_raw;
		?>
		<div class="ta-float-contact" role="region" aria-label="Liên hệ nhanh Top Asia">
			<a class="ta-fc-btn ta-fc-call" href="tel:<?php echo esc_attr( $phone_raw ); ?>" data-ta-contact="phone" aria-label="Gọi hotline <?php echo esc_attr( $phone_display ); ?>">
				<span class="ta-fc-ic" aria-hidden="true">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
				</span>
				<span class="ta-fc-txt">
					<strong class="ta-fc-label">Gọi ngay</strong>
					<small class="ta-fc-sub"><?php echo esc_html( $phone_display ); ?></small>
				</span>
			</a>

			<a class="ta-fc-btn ta-fc-zalo" href="<?php echo esc_url( $zalo_url ); ?>" target="_blank" rel="noopener noreferrer" data-ta-contact="zalo" aria-label="Chat Zalo <?php echo esc_attr( $phone_display ); ?>">
				<span class="ta-fc-ic ta-fc-zalo-badge" aria-hidden="true">Zalo</span>
				<span class="ta-fc-txt">
					<strong class="ta-fc-label">Chat Zalo</strong>
					<small class="ta-fc-sub"><?php echo esc_html( $phone_display ); ?></small>
				</span>
			</a>
		</div>

		<style>
		.ta-float-contact,
		.ta-float-contact * {
			box-sizing: border-box;
			font-family: 'Google Sans', Arial, sans-serif;
		}
		.ta-float-contact {
			position: fixed;
			z-index: 990;
			right: 20px;
			bottom: 84px;
			display: flex;
			flex-direction: column;
			gap: 10px;
		}
		.ta-fc-btn {
			position: relative;
			display: inline-flex;
			align-items: center;
			gap: 10px;
			min-height: 48px;
			padding: 8px 16px 8px 12px;
			border-radius: 10px;
			color: #FFFFFF !important;
			text-decoration: none !important;
			box-shadow: 0 10px 24px rgba(11, 29, 58, .22);
			transition: transform .18s ease, background-color .18s ease, box-shadow .18s ease;
		}
		.ta-fc-btn:hover {
			transform: translateY(-2px);
			box-shadow: 0 14px 28px rgba(11, 29, 58, .28);
			color: #FFFFFF !important;
		}
		.ta-fc-btn:focus-visible {
			outline: 2px solid #FFFFFF;
			outline-offset: 2px;
		}
		.ta-fc-call {
			background: #E31E24;
		}
		.ta-fc-call:hover {
			background: #C51A1F;
		}
		.ta-fc-call::before {
			content: "";
			position: absolute;
			inset: 0;
			border-radius: inherit;
			border: 2px solid rgba(227, 30, 36, .45);
			animation: ta-fc-pulse 2.2s ease-out infinite;
			pointer-events: none;
		}
		@keyframes ta-fc-pulse {
			0% { transform: scale(1); opacity: .75; }
			75% { transform: scale(1.06, 1.16); opacity: 0; }
			100% { transform: scale(1.06, 1.16); opacity: 0; }
		}
		.ta-fc-zalo {
			background: #0B1D3A;
			border: 1px solid rgba(255, 255, 255, .16);
		}
		.ta-fc-zalo:hover {
			background: #12294F;
		}
		.ta-fc-ic {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			width: 32px;
			height: 32px;
			border-radius: 8px;
			background: rgba(255, 255, 255, .16);
			flex: 0 0 auto;
		}
		.ta-fc-zalo-badge {
			font-size: 11px;
			font-weight: 800;
			letter-spacing: -.01em;
			background: #FFFFFF;
			color: #0B1D3A;
		}
		.ta-fc-txt {
			display: flex;
			flex-direction: column;
			line-height: 1.15;
		}
		.ta-fc-label {
			font-size: 13px;
			font-weight: 800;
			letter-spacing: .02em;
			color: #FFFFFF;
		}
		.ta-fc-sub {
			font-size: 11.5px;
			font-weight: 600;
			color: rgba(255, 255, 255, .88);
			font-variant-numeric: tabular-nums;
			margin-top: 2px;
		}

		@media (min-width: 768px) {
			#top-link.back-to-top {
				bottom: 20px !important;
				right: 20px !important;
			}
		}

		@media (max-width: 767px) {
			body {
				padding-bottom: calc(68px + env(safe-area-inset-bottom));
			}
			.ta-float-contact {
				left: 0;
				right: 0;
				bottom: 0;
				flex-direction: row;
				display: grid;
				grid-template-columns: 1fr 1fr;
				gap: 8px;
				padding: 8px 12px calc(8px + env(safe-area-inset-bottom));
				background: rgba(255, 255, 255, .97);
				backdrop-filter: blur(8px);
				border-top: 1px solid #E4E9F0;
				box-shadow: 0 -6px 20px rgba(11, 29, 58, .10);
			}
			.ta-fc-btn {
				width: 100%;
				min-height: 48px;
				padding: 6px 10px;
				border-radius: 8px;
				justify-content: center;
				box-shadow: none;
			}
			.ta-fc-call::before { display: none; }
			.ta-fc-ic {
				width: 28px;
				height: 28px;
				border-radius: 6px;
			}
			.ta-fc-label { font-size: 12.5px; }
			.ta-fc-sub { font-size: 11px; }
			#top-link.back-to-top {
				bottom: calc(74px + env(safe-area-inset-bottom)) !important;
			}
			body.page-id-1723 .bgq-sticky {
				bottom: calc(72px + env(safe-area-inset-bottom)) !important;
			}
		}

		@media (prefers-reduced-motion: reduce) {
			.ta-fc-call::before { animation: none; display: none; }
			.ta-fc-btn { transition: none; }
		}

		@media print {
			.ta-float-contact { display: none !important; }
		}
		</style>

		<script>
		(function () {
			document.addEventListener('click', function (e) {
				var btn = e.target.closest('.ta-fc-btn[data-ta-contact]');
				if (!btn) {
					return;
				}
				if (window.dataLayer && typeof window.dataLayer.push === 'function') {
					window.dataLayer.push({
						event: 'contact_button_click',
						contact_channel: btn.getAttribute('data-ta-contact'),
						contact_value: '0896699996'
					});
				}
			});
		})();
		</script>
		<?php
	}
}
add_action( 'wp_footer', 'topasia_render_floating_contact', 40 );