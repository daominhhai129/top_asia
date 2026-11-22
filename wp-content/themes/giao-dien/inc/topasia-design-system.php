<?php
/**
 * Top Asia — Global Design System loader & layout helpers.
 *
 * Enqueues Google Sans + assets/topasia-design-system.css across all frontend pages
 * and adds helper body classes when shop/blog sidebars have no active widgets.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_enqueue_scripts', function () {
	if ( is_admin() || wp_doing_ajax() || is_customize_preview() ) {
		return;
	}
	if ( isset( $_GET['app'] ) || isset( $_GET['uxb_iframe'] ) || isset( $_GET['ux_builder'] ) ) {
		return;
	}

	wp_enqueue_style(
		'topasia-google-sans',
		'https://fonts.googleapis.com/css2?family=Google+Sans:opsz,wght@17..18,400..700&display=swap',
		array(),
		null
	);

	$rel  = '/assets/topasia-design-system.css';
	$path = get_stylesheet_directory() . $rel;
	$ver  = file_exists( $path ) ? (string) filemtime( $path ) : '1.0.0';

	wp_enqueue_style(
		'topasia-design-system',
		get_stylesheet_directory_uri() . $rel,
		array( 'topasia-google-sans' ),
		$ver
	);
}, 30 );

add_filter( 'body_class', function ( $classes ) {
	if ( is_admin() ) {
		return $classes;
	}

	if ( ! is_active_sidebar( 'shop-sidebar' ) ) {
		$classes[] = 'ta-no-shop-sidebar';
	}

	if ( ! is_active_sidebar( 'sidebar-main' ) ) {
		$classes[] = 'ta-no-post-sidebar';
	}

	return $classes;
} );

/**
 * Clean up unparsed [ez-toc ...] and [rank_math_breadcrumb] shortcodes
 * (e.g. when plugins are inactive on staging or curly quotes break parsing).
 */
add_action( 'init', function () {
	if ( ! shortcode_exists( 'ez-toc' ) ) {
		add_shortcode( 'ez-toc', '__return_empty_string' );
	}
	if ( ! shortcode_exists( 'rank_math_breadcrumb' ) ) {
		add_shortcode( 'rank_math_breadcrumb', function () {
			if ( function_exists( 'woocommerce_breadcrumb' ) ) {
				ob_start();
				woocommerce_breadcrumb();
				return ob_get_clean();
			}
			return '';
		} );
	}
}, 20 );

add_filter( 'the_content', function ( $content ) {
	if ( is_admin() || false === strpos( $content, '[' ) ) {
		return $content;
	}
	$content = preg_replace( '/<p>\s*\[(?:ez-toc|rank_math_breadcrumb)[^\]]*\]\s*<\/p>/iu', '', $content );
	$content = preg_replace( '/\[(?:ez-toc|rank_math_breadcrumb)[^\]]*\]/iu', '', $content );
	return $content;
}, 20 );

// 301 fallback for category slugs missing /category/ base on staging/sub-dir installs.
add_action( 'template_redirect', function () {
	if ( ! is_404() ) {
		return;
	}
	$path    = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '';
	$slug    = basename( untrailingslashit( (string) $path ) );
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

// Cache-busting for child-theme style.css via filemtime.
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

// Disable wpautop/wptexturize on custom .tap HTML pages so card/grid markup stays clean.
add_filter( 'the_content', function ( $content ) {
	if ( false !== strpos( $content, 'class="tap ' ) || false !== strpos( $content, 'class="tap"' ) ) {
		remove_filter( 'the_content', 'wpautop' );
		remove_filter( 'the_content', 'wptexturize' );
	}
	return $content;
}, 1 );

// Shortcode [ta_posts cat="..." count="9" cols="3" more="..." more_label="..."]
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

// Ensure homepage project cards link to detail articles & product line thumbnails use uncropped 1:1 images.
add_action( 'wp_footer', function () {
	if ( is_admin() || wp_doing_ajax() || is_customize_preview() || ! is_front_page() ) {
		return;
	}
	if ( isset( $_GET['app'] ) || isset( $_GET['uxb_iframe'] ) || isset( $_GET['ux_builder'] ) ) {
		return;
	}

	$project_urls = array(
		'vinmec'     => home_url( '/cong-trinh-tieu-bieu-he-thong-mai-sanh-benh-vien-vinmec-riverside/' ),
		'ao tiên'    => home_url( '/du-an-cang-quoc-te-ao-tien-van-don/' ),
		'palm manor' => home_url( '/du-an-tuong-chong-on-khu-do-thi-moi-tay-nam-palm-manor-tp-viet-tri/' ),
	);
	$product_urls = array(
		'asia gold'  => home_url( '/danh-muc-san-pham/tam-nhua-polycarbonate-cuong-luc-asia-gold/' ),
		'asia pearl' => home_url( '/danh-muc-san-pham/tam-nhua-polycarbonate-cuong-luc-asia-pearl/' ),
		'asia lite'  => home_url( '/danh-muc-san-pham/tam-nhua-polycarbonate-cuong-luc-asia-lite/' ),
	);
	?>
	<script>
	(function () {
		var map = <?php echo wp_json_encode( $project_urls ); ?>;
		var cards = document.querySelectorAll('.ta-project-grid .ta-project-card');
		cards.forEach(function (card) {
			var title = card.querySelector('h3');
			var body = card.querySelector('.ta-project-body');
			if (!title || !body) {
				return;
			}
			var existingLink = card.querySelector('a.ta-project-link');
			var text = (title.textContent || '').toLowerCase();
			var url = existingLink ? existingLink.getAttribute('href') : '';
			if (!url) {
				Object.keys(map).some(function (key) {
					if (text.indexOf(key) !== -1) {
						url = map[key];
						return true;
					}
					return false;
				});
			}
			if (!url) {
				return;
			}
			if (!title.querySelector('a')) {
				var ta = document.createElement('a');
				ta.href = url;
				ta.textContent = title.textContent;
				title.textContent = '';
				title.appendChild(ta);
			}
			if (!existingLink) {
				var cta = document.createElement('a');
				cta.className = 'ta-project-link';
				cta.href = url;
				cta.textContent = 'Xem chi tiết dự án →';
				body.appendChild(cta);
			}
			card.classList.add('is-linked');
			card.addEventListener('click', function (e) {
				if (e.target.closest('a')) {
					return;
				}
				if (window.getSelection && String(window.getSelection()).length > 0) {
					return;
				}
				window.location.href = url;
			});
		});

		var prodMap = <?php echo wp_json_encode( $product_urls ); ?>;
		var prodCards = document.querySelectorAll('.ta-product-grid .ta-product-card');
		prodCards.forEach(function (card) {
			var img = card.querySelector('img');
			if (img && img.src) {
				var fullSrc = img.src.replace(/-\d+x\d+(\.(?:png|jpe?g|webp))(?:\?.*)?$/i, '$1');
				if (fullSrc !== img.src) {
					img.dataset.fallbackSrc = img.src;
					img.addEventListener('error', function onErr() {
						img.removeEventListener('error', onErr);
						if (img.dataset.fallbackSrc) {
							img.src = img.dataset.fallbackSrc;
						}
					});
					img.src = fullSrc;
				}
			}
			var h3 = card.querySelector('h3');
			var link = card.querySelector('a.ta-textlink');
			if (h3 && link) {
				var name = (h3.textContent || '').toLowerCase();
				Object.keys(prodMap).some(function (k) {
					if (name.indexOf(k) !== -1) {
						link.href = prodMap[k];
						return true;
					}
					return false;
				});
			}
		});
	})();
	</script>
	<?php
}, 35 );
