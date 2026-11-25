<?php
// Add custom Theme Functions here

// Top Asia â€” custom primary navigation (desktop mega menu + mobile off-canvas accordion).
require_once __DIR__ . '/inc/topasia-menu.php';

// Top Asia â€” homepage redesign assets (front page only).
require_once __DIR__ . '/inc/home.php';

// Top Asia — global site footer (code-driven links & company data).
$topasia_footer_file = __DIR__ . '/inc/topasia-footer.php';
if ( file_exists( $topasia_footer_file ) ) {
	require_once $topasia_footer_file;
}
unset( $topasia_footer_file );

// Top Asia — backend model & admin CRM lưu trữ số điện thoại khách tải báo giá / liên hệ.
$topasia_quote_leads_file = __DIR__ . '/inc/topasia-quote-leads.php';
if ( file_exists( $topasia_quote_leads_file ) ) {
	require_once $topasia_quote_leads_file;
}
unset( $topasia_quote_leads_file );

// Top Asia — các tính năng custom đã chuyển từ Novamira sandbox về theme (chạy độc lập, không phụ thuộc Novamira).
$topasia_custom_features_file = __DIR__ . '/inc/topasia-custom-features.php';
if ( file_exists( $topasia_custom_features_file ) ) {
	require_once $topasia_custom_features_file;
}
unset( $topasia_custom_features_file );

// Top Asia â€” H1 cho trang chuyÃªn má»¥c + dá»n H1 thá»«a á»Ÿ hero trang chá»§.
// DÃ¹ng file_exists() Ä‘á»ƒ náº¿u chÆ°a upload inc/topasia-h1.php thÃ¬ site váº«n cháº¡y bÃ¬nh thÆ°á»ng
// (require_once tháº³ng vÃ o file khÃ´ng tá»“n táº¡i sáº½ gÃ¢y fatal error, tráº¯ng site).
$topasia_h1_file = __DIR__ . '/inc/topasia-h1.php';
if ( file_exists( $topasia_h1_file ) ) {
	require_once $topasia_h1_file;
}
unset( $topasia_h1_file );

// Top Asia — Quotation page (/bao-gia/) Desktop & Mobile optimizer.
$topasia_quote_file = __DIR__ . '/inc/topasia-quote.php';
if ( file_exists( $topasia_quote_file ) ) {
	require_once $topasia_quote_file;
}
unset( $topasia_quote_file );


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






//Thay Ä‘á»•i Giao Diá»‡n - Logo cho trang Ä‘Äƒng nháº­p

add_action( 'login_enqueue_scripts', 'giuseart_login_enqueue_scripts' );
function giuseart_login_enqueue_scripts(){
echo '<style type="text/css" media="screen">';
// FIX: khá»‘i CSS cÅ© thiáº¿u dáº¥u } Ä‘Ã³ng nÃªn nuá»‘t luÃ´n cÃ¡c rule phÃ­a sau.
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

//Thay Ä‘á»•i Giao Diá»‡n - Logo cho trang Ä‘Äƒng nháº­p
// Táº¡o shortcode hiá»ƒn thá»‹ breadcrumb
add_shortcode('f_breadcrumbs', function() {
    ob_start();
    woocommerce_breadcrumb();
    return ob_get_clean();
});

// táº¡o poup trang web
/**
 * Popup tÆ° váº¥n toÃ n website
 * KhÃ´ng hiá»ƒn thá»‹ á»Ÿ Giá» hÃ ng vÃ  Thanh toÃ¡n
 * KhÃ´ng cháº¡y trong UX Builder / Customizer / admin / AJAX (gÃ¢y káº¹t UX Builder).
 */
add_action('wp_footer', 'topasia_popup_form');

function topasia_popup_form() {

    // FIX 1 â€” khÃ´ng cháº¡y trong admin, AJAX, Customizer.
    if ( is_admin() || wp_doing_ajax() || is_customize_preview() ) {
        return;
    }

    // FIX 2 â€” khÃ´ng cháº¡y trong iframe/app cá»§a UX Builder (Flatsome).
    if ( isset( $_GET['app'] ) || isset( $_GET['uxb_iframe'] ) || isset( $_GET['ux_builder'] ) ) {
        return;
    }

    // FIX 3 â€” bá»c is_cart()/is_checkout() trong function_exists Ä‘á»ƒ khÃ´ng fatal
    // khi WooCommerce bá»‹ táº¯t (vÃ­ dá»¥ lÃºc test lá»—i báº±ng cÃ¡ch deactivate plugin).
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

        // FIX 4 â€” cháº·n lá»—i JS náº¿u Magnific Popup chÆ°a Ä‘Æ°á»£c náº¡p trÃªn trang.
        if ( ! $.magnificPopup ) {
            return;
        }

        // Chá»‰ hiá»‡n láº¡i sau 60 giÃ¢y (60000 ms).
        // Muá»‘n 1 láº§n / 24h thÃ¬ Ä‘á»•i 60000 thÃ nh 86400000.
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
 * TOP ASIA DESIGN SYSTEM â€” global subpages, products & news (2026-11)
 * ========================================================================== */

// 1. Google Sans cho toÃ n site (CSS design system náº±m cuá»‘i style.css).
add_action( 'wp_enqueue_scripts', function () {
	if ( is_admin() || isset( $_GET['app'] ) || isset( $_GET['uxb_iframe'] ) ) {
		return;
	}
	// (ÄÃ£ bá» Google Fonts CDN â€” font tá»± host trong assets/fonts, xem khá»‘i "DESIGN SYSTEM v1" bÃªn dÆ°á»›i.)
}, 5 );

/* ==========================================================================
 * TOP ASIA DESIGN SYSTEM v1 â€” font tá»± host + CSS dÃ¹ng chung + CSS trang chá»§
 * ========================================================================== */
add_action( 'wp_enqueue_scripts', function () {
	if ( is_admin() || isset( $_GET['app'] ) || isset( $_GET['uxb_iframe'] ) ) {
		return;
	}
	$dir = get_stylesheet_directory();
	$uri = get_stylesheet_directory_uri();
	$ver = function ( $f ) use ( $dir ) { return file_exists( $dir . $f ) ? filemtime( $dir . $f ) : '1'; };

	wp_enqueue_style( 'ta-fonts', $uri . '/assets/topasia-fonts-flex.css', array(), $ver( '/assets/topasia-fonts-flex.css' ) );
	wp_enqueue_style( 'ta-ds', $uri . '/assets/ta-ds.css', array( 'ta-fonts' ), $ver( '/assets/ta-ds.css' ) );

	if ( is_front_page() ) {
		wp_enqueue_style( 'ta-home', $uri . '/assets/ta-home.css', array( 'ta-ds' ), $ver( '/assets/ta-home.css' ) );
		wp_enqueue_script( 'ta-home', $uri . '/assets/ta-home.js', array(), $ver( '/assets/ta-home.js' ), true );
	}
}, 100 );

// Preload font Viá»‡t + Latin (LCP/CLS).
add_action( 'wp_head', function () {
	$u = get_stylesheet_directory_uri() . '/assets/fonts/';
	foreach ( array( 'GoogleSansFlex-vietnamese.woff2', 'GoogleSansFlex-latin.woff2' ) as $f ) {
		echo '<link rel="preload" href="' . esc_url( $u . $f ) . '" as="font" type="font/woff2" crossorigin>' . "\n";
	}
}, 1 );

// 2. Body class khi sidebar shop / blog trá»‘ng -> CSS áº©n cá»™t trá»‘ng.
add_filter( 'body_class', function ( $classes ) {
	if ( ! is_active_sidebar( 'shop-sidebar' ) ) {
		$classes[] = 'ta-no-shop-sidebar';
	}
	if ( ! is_active_sidebar( 'sidebar-main' ) ) {
		$classes[] = 'ta-no-post-sidebar';
	}
	return $classes;
} );

// 3. áº¨n shortcode thÃ´ [ez-toc ...] / [rank_math_breadcrumb] bá»‹ in ra trang.
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

// 4. Link chuyÃªn má»¥c trong menu (/tin-tuc-bao-gia/, /kien-thuc-ky-thuat/,
//    /top-asia-cap-nhat/, /cong-trinh-thuc-te/) bá»‹ 404 khi permalink cÃ²n /category/
//    -> chuyá»ƒn hÆ°á»›ng 301 vá» Ä‘Ãºng link chuyÃªn má»¥c.
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

// 5. H1 cho danh má»¥c sáº£n pháº©m chÆ°a cÃ³ H1 (Asia Gold / Pearl / Lite...).
add_action( 'woocommerce_archive_description', function () {
	if ( ! function_exists( 'is_product_category' ) || ! is_product_category() ) {
		return;
	}
	$term = get_queried_object();
	if ( ! $term || empty( $term->name ) ) {
		return;
	}
	// CÃ¡c slug nÃ y Ä‘Ã£ cÃ³ H1 tá»« inc/topasia-h1.php.
	if ( in_array( $term->slug, array( 'mai-che-canopy', 'phu-kien-mai-che-canopy', 'phu-kien-thi-cong' ), true ) ) {
		return;
	}
	// Mô tả danh mục đã có H1 riêng (SEO) -> không in thêm H1 thứ hai.
	if ( false !== stripos( (string) term_description( $term->term_id, 'product_cat' ), '<h1' ) ) {
		return;
	}
	echo '<h1 class="ta-archive-h1 ta-archive-h1--product">' . esc_html( $term->name ) . '</h1>';
}, 5 );

// 6. Cache-busting: style.css cá»§a child theme dÃ¹ng filemtime lÃ m ?ver=
//    (Cloudflare cache ?ver=4.13 trong 7 ngÃ y nÃªn sá»­a CSS khÃ´ng hiá»‡n ra).
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

// 7. Trang dá»±ng báº±ng HTML (.tap): táº¯t wpautop Ä‘á»ƒ khÃ´ng chÃ¨n <p>/<br> lÃ m vá»¡ layout.
add_filter( 'the_content', function ( $content ) {
	if ( false !== strpos( $content, 'class="tap ' ) || false !== strpos( $content, 'class="tap"' ) ) {
		remove_filter( 'the_content', 'wpautop' );
		remove_filter( 'the_content', 'wptexturize' );
	}
	return $content;
}, 1 );

// 8. Shortcode [ta_posts cat="slug1,slug2" count="9" cols="3" more="/du-an/" more_label="Xem táº¥t cáº£"]
//    Hiá»ƒn thá»‹ bÃ i viáº¿t dáº¡ng card theo design system Top Asia.
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
			echo '<p>' . esc_html( wp_trim_words( get_the_excerpt(), 22, 'â€¦' ) ) . '</p>';
			echo '<div class="tap-post-meta"><span>' . esc_html( get_the_date( 'd/m/Y' ) ) . '</span><b>Xem chi tiáº¿t â†’</b></div>';
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
			font-family: var(--ta-font, 'Google Sans Flex', 'Google Sans', system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif);
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
			font-weight: 700;
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
			font-size: 13.5px;
			font-weight: 700;
			letter-spacing: .01em;
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
// Trang chủ (page 5784 gán page-blank): ép dùng front-page.php mà KHÔNG sửa dữ liệu trang.
add_filter( 'template_include', function ( $tpl ) {
	if ( is_front_page() && ! is_admin() && empty( $_GET['legacy_home'] ) && file_exists( get_stylesheet_directory() . '/front-page.php' ) ) {
		return get_stylesheet_directory() . '/front-page.php';
	}
	return $tpl;
}, 99 );


/* ==========================================================================
 * PHASE 5 — Tối ưu tải trang (chỉ front-end, không ảnh hưởng dữ liệu)
 * ========================================================================== */
// 1. Bỏ CSS Poppins cũ: 'Poppins VN' đã được alias sang Google Sans Flex trong topasia-fonts-flex.css.
add_action( 'wp_enqueue_scripts', function () {
	wp_dequeue_style( 'topasia-fonts' );
	wp_deregister_style( 'topasia-fonts' );
}, 120 );

// 2. Trang chủ: preload ảnh hero (LCP).
add_action( 'wp_head', function () {
	if ( ! is_front_page() || ! function_exists( 'taf_opt' ) ) {
		return;
	}
	$img = taf_opt( 'hero1_img' );
	if ( $img ) {
		echo '<link rel="preload" as="image" href="' . esc_url( $img ) . '" fetchpriority="high">' . "\n";
	}
}, 2 );

// 3. Preconnect không cần nữa (font tự host) — chặn Google Fonts nếu plugin khác còn chèn.
add_filter( 'wp_resource_hints', function ( $urls, $relation ) {
	if ( 'preconnect' === $relation || 'dns-prefetch' === $relation ) {
		$urls = array_filter( $urls, function ( $u ) {
			$h = is_array( $u ) ? ( isset( $u['href'] ) ? $u['href'] : '' ) : $u;
			return false === strpos( $h, 'fonts.g' );
		} );
	}
	return $urls;
}, 10, 2 );

/* ==========================================================================
 * 4. FALLBACK LOGO LOCAL & CHUẨN HÓA LINK NỘI BỘ (/staging/ & root-relative)
 * ========================================================================== */
function topasia_logo_fallback_url() {
	return get_stylesheet_directory_uri() . '/assets/img/logo-topasia.svg';
}

add_filter( 'wp_get_attachment_image_src', function ( $image, $attachment_id ) {
	if ( is_admin() || ! $attachment_id ) {
		return $image;
	}
	$logo_ids = array_filter( array_map( 'intval', array(
		get_theme_mod( 'site_logo' ),
		get_theme_mod( 'site_logo_sticky' ),
		get_theme_mod( 'site_logo_dark' ),
	) ) );
	if ( ! in_array( (int) $attachment_id, $logo_ids, true ) ) {
		return $image;
	}
	$file = get_attached_file( $attachment_id );
	if ( ! $file || ! file_exists( $file ) ) {
		return array( topasia_logo_fallback_url(), 210, 48, false );
	}
	return $image;
}, 20, 2 );

function topasia_resolve_internal_url( $url ) {
	$url = trim( (string) $url );
	if ( '' === $url || '#' === $url[0] || 0 === strpos( $url, 'mailto:' ) || 0 === strpos( $url, 'tel:' ) || 0 === strpos( $url, 'javascript:' ) || 0 === strpos( $url, '//' ) ) {
		return $url;
	}
	// Chuyển link cứng /staging/ (tuyệt đối hoặc tương đối) về đúng gốc site hiện tại.
	if ( preg_match( '#^https?://(?:www\.)?topasia\.vn/staging(/.*)?$#i', $url, $m ) ) {
		$rel = ! empty( $m[1] ) ? $m[1] : '/';
		return home_url( $rel );
	}
	if ( 0 === strpos( $url, '/staging/' ) || '/staging' === $url ) {
		$rel = substr( $url, 8 );
		return home_url( $rel ? $rel : '/' );
	}
	// Bỏ qua URL tuyệt đối ngoài site hoặc không bắt đầu bằng '/'.
	if ( preg_match( '#^https?://#i', $url ) || '/' !== $url[0] ) {
		return $url;
	}
	$home_path = rtrim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
	if ( '' !== $home_path && ( $url === $home_path || 0 === strpos( $url, $home_path . '/' ) ) ) {
		return $url;
	}
	return home_url( $url );
}

function topasia_rewrite_html_media_urls( $html ) {
	if ( is_admin() || ! is_string( $html ) || false === strpos( $html, 'wp-content/uploads/' ) ) {
		return $html;
	}
	return preg_replace_callback(
		'/(src|data-src|poster)\s*=\s*(["\'])([^"\']*wp-content\/uploads\/[^"\']+)\2/i',
		function ( $m ) {
			return $m[1] . '=' . $m[2] . esc_url( topasia_resolve_media_url( html_entity_decode( $m[3], ENT_QUOTES, 'UTF-8' ) ) ) . $m[2];
		},
		$html
	);
}

add_filter( 'the_content', function ( $content ) {
	if ( is_admin() || ! is_string( $content ) ) {
		return $content;
	}
	if ( false !== strpos( $content, 'href=' ) ) {
		$content = preg_replace_callback(
			'/(href\s*=\s*["\'])([^"\']+)(["\'])/i',
			function ( $m ) {
				return $m[1] . esc_url( topasia_resolve_internal_url( html_entity_decode( $m[2], ENT_QUOTES, 'UTF-8' ) ) ) . $m[3];
			},
			$content
		);
	}
	return topasia_rewrite_html_media_urls( $content );
}, 11 );

add_filter( 'do_shortcode_tag', function ( $output, $tag ) {
	if ( in_array( $tag, array( 'block', 'ux_text' ), true ) ) {
		return topasia_rewrite_html_media_urls( $output );
	}
	return $output;
}, 11, 2 );

function topasia_resolve_media_url( $url ) {
	$url = trim( (string) $url );
	if ( '' === $url ) {
		return $url;
	}
	if ( preg_match( '#^(?:https?://(?:www\.)?(?:topasia\.vn|localhost)(?::\d+)?(?:/staging|/top_asia)?)?/+(?:staging/|top_asia/)?wp-content/uploads/(.+)$#i', $url, $m ) ) {
		$rel = ltrim( str_replace( '\\', '/', $m[1] ), '/' );
		$rel_path = urldecode( strtok( $rel, '?#' ) );
		$abs = ABSPATH . 'wp-content/uploads/' . $rel_path;
		if ( ! file_exists( $abs ) || 0 === filesize( $abs ) ) {
			$webp = preg_replace( '/\.(png|jpe?g)$/i', '.webp', $rel_path );
			if ( $webp !== $rel_path && file_exists( ABSPATH . 'wp-content/uploads/' . $webp ) ) {
				$rel = $webp;
			}
		}
		return home_url( '/wp-content/uploads/' . $rel );
	}
	return $url;
}

// Bỏ qua các ảnh srcset không tồn tại trên đĩa & fallback về ảnh gốc nếu thiếu size crop.
add_filter( 'wp_calculate_image_srcset', function ( $sources ) {
	if ( is_admin() || ! is_array( $sources ) ) {
		return $sources;
	}
	$base_url = trailingslashit( home_url( '/wp-content/uploads' ) );
	foreach ( $sources as $w => $src ) {
		if ( empty( $src['url'] ) || 0 !== strpos( $src['url'], $base_url ) ) {
			continue;
		}
		$rel = urldecode( substr( $src['url'], strlen( $base_url ) ) );
		$abs = ABSPATH . 'wp-content/uploads/' . ltrim( $rel, '/' );
		if ( ! file_exists( $abs ) || 0 === filesize( $abs ) ) {
			unset( $sources[ $w ] );
		}
	}
	return $sources;
}, 20 );

add_filter( 'wp_get_attachment_image_src', function ( $image, $attachment_id ) {
	if ( is_admin() || ! is_array( $image ) || empty( $image[0] ) || ! $attachment_id ) {
		return $image;
	}
	$base_url = trailingslashit( home_url( '/wp-content/uploads' ) );
	if ( 0 !== strpos( $image[0], $base_url ) ) {
		return $image;
	}
	$rel = urldecode( substr( $image[0], strlen( $base_url ) ) );
	$abs = ABSPATH . 'wp-content/uploads/' . ltrim( $rel, '/' );
	if ( file_exists( $abs ) && filesize( $abs ) > 0 ) {
		return $image;
	}
	$orig = get_attached_file( $attachment_id );
	if ( $orig && file_exists( $orig ) && filesize( $orig ) > 0 ) {
		$orig_url = wp_get_attachment_url( $attachment_id );
		$sz = @getimagesize( $orig );
		return array( $orig_url, $sz ? (int) $sz[0] : $image[1], $sz ? (int) $sz[1] : $image[2], false );
	}
	return $image;
}, 30, 2 );

// Dùng ảnh vuông đầy đủ cho card sản phẩm để không bị cắt mất chữ hai bên mép.
add_filter( 'single_product_archive_thumbnail_size', function () {
	return 'full';
}, 20 );

/* ==========================================================================
 * 5. TẮT TÍNH NĂNG GIỎ HÀNG & ĐẶT HÀNG (CHẾ ĐỘ CATALOG B2B — GIỮ GIÁ)
 * ========================================================================== */
add_filter( 'theme_mod_catalog_mode', '__return_true' );
add_filter( 'theme_mod_catalog_mode_prices', '__return_zero' );
add_filter( 'woocommerce_is_purchasable', '__return_false', 99 );
add_filter( 'woocommerce_variation_is_purchasable', '__return_false', 99 );
add_filter( 'woocommerce_add_to_cart_validation', '__return_false', 99 );
add_filter( 'woocommerce_cart_item_removed_notice_type', '__return_empty_string', 99 );
add_filter( 'woocommerce_add_error', function ( $message ) {
	if ( is_string( $message ) && false !== strpos( $message, 'giỏ hàng' ) ) {
		return '';
	}
	return $message;
}, 99 );

add_action( 'woocommerce_cart_loaded_from_session', function ( $cart ) {
	if ( $cart && ! $cart->is_empty() ) {
		$cart->empty_cart( true );
	}
}, 5 );

add_action( 'wp_loaded', function () {
	if ( is_admin() ) {
		return;
	}
	if ( function_exists( 'WC' ) && WC()->cart && ! WC()->cart->is_empty() ) {
		WC()->cart->empty_cart( true );
		if ( function_exists( 'wc_clear_notices' ) ) {
			wc_clear_notices();
		}
	}
}, 20 );

add_action( 'init', function () {
	remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );
	remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
	remove_action( 'woocommerce_single_variation', 'woocommerce_single_variation_add_to_cart_button', 20 );
	remove_action( 'flatsome_product_box_actions', 'flatsome_product_box_actions_add_to_cart', 1 );

	remove_shortcode( 'ux_product_add_to_cart' );
	add_shortcode( 'ux_product_add_to_cart', '__return_empty_string' );
}, 20 );

// Hiển thị nút nhận báo giá & hotline trên trang chi tiết sản phẩm thay cho nút giỏ hàng.
add_action( 'woocommerce_single_product_summary', function () {
	if ( ! is_product() ) {
		return;
	}
	echo '<div class="ta-prod-cta">';
	echo '<a class="tx-btn tx-btn--accent" href="' . esc_url( home_url( '/bao-gia/' ) ) . '">Yêu cầu báo giá sản phẩm</a>';
	echo '<a class="tx-btn tx-btn--ghost" href="tel:0896699996">Hotline: 0896 699 996</a>';
	echo '</div>';
}, 32 );

add_action( 'wp_enqueue_scripts', function () {
	if ( wp_script_is( 'wc-cart-fragments', 'enqueued' ) ) {
		wp_dequeue_script( 'wc-cart-fragments' );
	}
}, 999 );

add_action( 'template_redirect', function () {
	if ( is_admin() || wp_doing_ajax() || is_customize_preview() ) {
		return;
	}
	if ( isset( $_GET['app'] ) || isset( $_GET['uxb_iframe'] ) || isset( $_GET['ux_builder'] ) ) {
		return;
	}
	if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() ) ) {
		wp_safe_redirect( home_url( '/bao-gia/' ), 302 );
		exit;
	}
}, 2 );


