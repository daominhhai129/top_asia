<?php
/**
 * Front page — Products section: Featured categories + 3 series tabs.
 * Wired to real WooCommerce products / categories.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Category image (term thumbnail → else first product image → else placeholder). */
function taf_cat_img( $slug ) {
	$term = get_term_by( 'slug', $slug, 'product_cat' );
	if ( ! $term ) { return ''; }
	$tid = get_term_meta( $term->term_id, 'thumbnail_id', true );
	if ( $tid ) {
		$u = wp_get_attachment_image_url( $tid, 'medium' );
		if ( $u ) { return $u; }
	}
	$p = get_posts( array( 'post_type' => 'product', 'posts_per_page' => 1, 'fields' => 'ids', 'tax_query' => array( array( 'taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => $slug ) ) ) );
	if ( $p ) {
		$u = get_the_post_thumbnail_url( $p[0], 'medium' );
		if ( $u ) { return $u; }
	}
	return '';
}

$taf_cats = array(
	array( 'Tấm Polycarbonate cường lực', 'Dạng đặc, trong suốt đến 98% — bền hơn kính 200 lần', 'layers', 'tam-nhua-kinh-polycarbonate-cuong-luc', '/tat-ca-san-pham/tam-nhua-kinh-polycarbonate-cuong-luc/' ),
	array( 'Polycarbonate đặc sần', 'Bề mặt vân sần chống chói, tán sáng đều cho không gian', 'grip', 'polycarbonate-dac-san', '/tat-ca-san-pham/polycarbonate-dac-san/' ),
	array( 'Phụ kiện chuyên dụng', 'Nẹp nhôm, keo, ke góc — trọn bộ giải pháp thi công', 'wrench', 'phu-kien-chuyen-dung', '/tat-ca-san-pham/phu-kien-chuyen-dung/' ),
);

$taf_series = array(
	'asia-gold'  => array( 'label' => 'Asia Gold', 'tag' => 'Tiêu chuẩn', 'slug' => 'tam-nhua-polycarbonate-cuong-luc-asia-gold', 'all' => '/danh-muc-san-pham/tam-nhua-polycarbonate-cuong-luc-asia-gold/' ),
	'asia-lite'  => array( 'label' => 'Asia Lite', 'tag' => 'Cao cấp Luxury', 'slug' => 'tam-nhua-polycarbonate-cuong-luc-asia-lite', 'all' => '/danh-muc-san-pham/tam-nhua-polycarbonate-cuong-luc-asia-lite/' ),
	'asia-pearl' => array( 'label' => 'Asia Pearl', 'tag' => 'Cao cấp Deluxe', 'slug' => 'tam-nhua-polycarbonate-cuong-luc-asia-pearl', 'all' => '/danh-muc-san-pham/tam-nhua-polycarbonate-cuong-luc-asia-pearl/' ),
);
?>
<section id="taf-san-pham" class="taf-sec" style="background:var(--gray-50);border-top:1px solid var(--border-1)">
	<div class="taf-wrap" style="padding:72px 24px">

		<div class="taf-head--center"><div class="taf-eyebrow"><i></i>Danh mục<i></i></div><h2 class="taf-h2">Sản phẩm nổi bật</h2></div>
		<div class="taf-g3" style="margin-bottom:56px">
			<?php foreach ( $taf_cats as $c ) : ?>
				<a href="<?php echo esc_url( home_url( $c[4] ) ); ?>" class="taf-cat" style="display:block;position:relative;background:#fff;border:1px solid var(--border-1);overflow:hidden;transition:box-shadow var(--dur-med) var(--ease-out),transform var(--dur-med)">
					<?php echo taf_ph( $c[0], $c[2], 170, taf_cat_img( $c[3] ) ); ?>
					<div style="padding:20px 22px">
						<div style="font-size:17px;font-weight:700;color:var(--navy-900);margin-bottom:7px"><?php echo esc_html( $c[0] ); ?></div>
						<div style="font-size:13px;line-height:1.55;color:var(--gray-600);margin-bottom:14px"><?php echo esc_html( $c[1] ); ?></div>
						<span style="display:inline-flex;align-items:center;gap:7px;font-size:12.5px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--red-600)">Xem chi tiết<?php echo taf_ic( 'arrow-right', 14 ); ?></span>
					</div>
				</a>
			<?php endforeach; ?>
		</div>

		<div class="taf-head--center"><div class="taf-eyebrow"><i></i>Bảng giá nhà máy<i></i></div><h2 class="taf-h2">Ba dòng sản phẩm chủ lực</h2></div>

		<div style="display:flex;justify-content:center;margin-bottom:28px">
			<div class="taf-tabs" role="tablist" style="display:inline-flex;gap:4px;border-bottom:1px solid var(--border-1)">
				<?php $first = true; foreach ( $taf_series as $key => $s ) : ?>
					<button type="button" class="taf-tab<?php echo $first ? ' is-active' : ''; ?>" data-tab="<?php echo esc_attr( $key ); ?>" role="tab" aria-selected="<?php echo $first ? 'true' : 'false'; ?>" style="all:unset;cursor:pointer;padding:12px 20px;font:600 14px/1 var(--font-sans);letter-spacing:.02em;text-transform:uppercase;color:var(--gray-500)"><?php echo esc_html( $s['label'] ); ?></button>
				<?php $first = false; endforeach; ?>
			</div>
		</div>

		<?php $first = true; foreach ( $taf_series as $key => $s ) : ?>
			<div class="taf-tabpanel" data-panel="<?php echo esc_attr( $key ); ?>" style="<?php echo $first ? '' : 'display:none'; ?>">
				<div class="taf-g4">
					<?php
					$q = new WP_Query( array(
						'post_type'      => 'product',
						'posts_per_page' => 8,
						'post_status'    => 'publish',
						'orderby'        => 'menu_order title',
						'order'          => 'ASC',
						'tax_query'      => array( array( 'taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => $s['slug'] ) ),
					) );
					if ( $q->have_posts() ) :
						while ( $q->have_posts() ) : $q->the_post();
							$pid   = get_the_ID();
							$prod  = function_exists( 'wc_get_product' ) ? wc_get_product( $pid ) : null;
							$img   = get_the_post_thumbnail_url( $pid, 'woocommerce_thumbnail' );
							if ( ! $img && function_exists( 'wc_placeholder_img_src' ) ) { $img = wc_placeholder_img_src(); }
							$price = $prod ? $prod->get_price_html() : '';
							?>
							<div class="taf-prod" style="background:#fff;border:1px solid var(--border-1);transition:box-shadow var(--dur-med) var(--ease-out)">
								<a href="<?php the_permalink(); ?>" style="display:block;position:relative">
									<div class="taf-ph" style="height:190px;min-height:190px"><?php echo $img ? '<img src="' . esc_url( $img ) . '" alt="' . esc_attr( get_the_title() ) . '" loading="lazy">' : taf_ic( 'layers', 30 ); ?></div>
									<span style="position:absolute;left:12px;top:12px;padding:4px 9px;background:var(--navy-900);color:#fff;font-size:10px;font-weight:600;letter-spacing:.08em;text-transform:uppercase"><?php echo esc_html( $s['tag'] ); ?></span>
								</a>
								<div style="padding:16px 16px 18px">
									<div style="font-size:10.5px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:var(--red-600);margin-bottom:6px"><?php echo esc_html( $s['label'] ); ?></div>
									<a href="<?php the_permalink(); ?>" style="display:block;font-size:14.5px;font-weight:600;color:var(--navy-900);line-height:1.4;margin-bottom:10px;min-height:40px"><?php echo esc_html( get_the_title() ); ?></a>
									<div style="display:flex;align-items:center;justify-content:space-between;gap:10px">
										<div class="taf-price" style="font-size:17px;font-weight:800;color:var(--navy-900)"><?php echo wp_kses_post( $price ); ?></div>
										<a href="<?php the_permalink(); ?>" class="taf-prod-view" aria-label="Xem chi tiết sản phẩm" style="width:38px;height:38px;flex:0 0 auto;display:inline-flex;align-items:center;justify-content:center;background:var(--navy-900);color:#fff;transition:background var(--dur-fast) var(--ease-out)"><?php echo taf_ic( 'arrow-right', 16 ); ?></a>
									</div>
								</div>
							</div>
							<?php
						endwhile;
						wp_reset_postdata();
					else :
						echo '<p style="grid-column:1/-1;color:var(--gray-500)">Chưa có sản phẩm trong danh mục này.</p>';
					endif;
					?>
				</div>
				<div style="text-align:center;margin-top:34px"><a href="<?php echo esc_url( home_url( $s['all'] ) ); ?>" class="taf-btn taf-btn--secondary">Xem tất cả sản phẩm <?php echo esc_html( $s['label'] ); ?></a></div>
			</div>
		<?php $first = false; endforeach; ?>

	</div>
</section>
