<?php
/**
 * Front page — Top Asia homepage redesign.
 * Ported from the supplied design bundle (navy/red, Poppins).
 * Header + footer stay as the site default; only the homepage body is redesigned.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'taf_ic' ) ) {
	/** Inline lucide icon holder (replaced by lucide.createIcons on load). */
	function taf_ic( $name, $size = 16, $color = '' ) {
		$c = $color ? 'color:' . $color . ';' : '';
		return '<span class="ic" style="width:' . intval( $size ) . 'px;height:' . intval( $size ) . 'px;' . $c . '"><i data-lucide="' . esc_attr( $name ) . '"></i></span>';
	}
	/** Image box: real <img> when $img given, else the design placeholder. */
	function taf_ph( $label, $icon = 'image', $h = 160, $img = '' ) {
		$inner = $img
			? '<img src="' . esc_url( $img ) . '" alt="' . esc_attr( $label ) . '" loading="lazy">'
			: ( taf_ic( $icon, 30 ) . '<span class="taf-ph-lbl">' . esc_html( $label ) . '</span>' );
		return '<div class="taf-ph" style="height:' . intval( $h ) . 'px;min-height:' . intval( $h ) . 'px">' . $inner . '</div>';
	}
}

get_header();
?>
<div class="taf-home">

	<!-- ===================== HERO SLIDER ===================== -->
	<section>
		<?php
		// Chữ + ảnh lấy từ Tuỳ biến → Nội dung Trang chủ (inc/home-content.php).
		// Biểu tượng, kiểu nút và đích đến giữ trong code vì gắn với hành vi/thiết kế.
		$taf_slides = array(
			array(
				'img'     => taf_opt( 'hero1_img' ),
				'badge'   => taf_opt( 'hero1_badge' ),
				'h1'      => taf_opt( 'hero1_h1' ),
				'sub'     => taf_opt( 'hero1_sub' ),
				'ctas'    => array( array( taf_opt( 'hero1_cta1' ), 'accent', 'quote' ), array( taf_opt( 'hero1_cta2' ), 'outline', '#taf-san-pham' ) ),
				'bullets' => array( array( 'shield-check', taf_opt( 'hero1_feat1' ) ), array( 'award', taf_opt( 'hero1_feat2' ) ), array( 'badge-check', taf_opt( 'hero1_feat3' ) ) ),
				'stats'   => array( array( taf_opt( 'hero1_stat1_num' ), taf_opt( 'hero1_stat1_lbl' ) ), array( taf_opt( 'hero1_stat2_num' ), taf_opt( 'hero1_stat2_lbl' ) ) ),
			),
			array(
				'img'     => taf_opt( 'hero2_img' ),
				'badge'   => taf_opt( 'hero2_badge' ),
				'h1'      => taf_opt( 'hero2_h1' ),
				'sub'     => taf_opt( 'hero2_sub' ),
				'ctas'    => array( array( taf_opt( 'hero2_cta1' ), 'accent', '#taf-san-pham' ), array( taf_opt( 'hero2_cta2' ), 'outline', 'quote' ) ),
				'bullets' => array(),
				'stats'   => array(),
			),
			array(
				'img'     => taf_opt( 'hero3_img' ),
				'badge'   => taf_opt( 'hero3_badge' ),
				'h1'      => taf_opt( 'hero3_h1' ),
				'sub'     => taf_opt( 'hero3_sub' ),
				'ctas'    => array( array( taf_opt( 'hero3_cta1' ), 'accent', 'quote' ) ),
				'bullets' => array(),
				'stats'   => array(),
			),
		);
		?>
		<div class="taf-hero" data-taf-hero>
			<div class="taf-hero-track">
				<?php foreach ( $taf_slides as $s ) : ?>
					<div class="taf-hero-slide">
						<div class="taf-hero-bg" style="background-image:url(<?php echo esc_url( $s['img'] ); ?>)"></div>
						<div class="taf-hero-shade"></div>
						<div class="taf-wrap taf-hero-inner2">
							<div class="taf-hero-copy">
								<div class="taf-hero-badge"><span></span><?php echo esc_html( $s['badge'] ); ?></div>
								<h1 class="taf-hero-h1"><?php echo wp_kses_post( $s['h1'] ); ?></h1>
								<p class="taf-hero-sub"><?php echo esc_html( $s['sub'] ); ?></p>
								<div class="taf-hero-ctas">
									<?php foreach ( $s['ctas'] as $c ) : $cls = 'taf-btn taf-btn--' . ( 'accent' === $c[1] ? 'accent' : 'outline' ) . ' taf-btn--lg'; ?>
										<?php if ( 'quote' === $c[2] ) : ?>
											<button type="button" class="<?php echo esc_attr( $cls ); ?>" data-taf-quote><?php echo esc_html( $c[0] ); ?></button>
										<?php else : ?>
											<a href="<?php echo esc_url( $c[2] ); ?>" class="<?php echo esc_attr( $cls ); ?>"><?php echo esc_html( $c[0] ); ?></a>
										<?php endif; ?>
									<?php endforeach; ?>
								</div>
								<?php if ( ! empty( $s['bullets'] ) ) : ?>
									<div class="taf-hero-feats">
										<?php foreach ( $s['bullets'] as $b ) : ?>
											<span class="taf-hero-feat"><?php echo taf_ic( $b[0], 18, 'var(--red-600)' ); ?><?php echo esc_html( $b[1] ); ?></span>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>
								<?php if ( ! empty( $s['stats'] ) ) : ?>
									<div class="taf-hero-stnums">
										<?php foreach ( $s['stats'] as $st ) : ?>
											<div><b><?php echo esc_html( $st[0] ); ?></b><span><?php echo esc_html( $st[1] ); ?></span></div>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
			<button type="button" class="taf-hero-arrow taf-hero-prev" aria-label="Slide trước"><?php echo taf_ic( 'chevron-left', 22, '#fff' ); ?></button>
			<button type="button" class="taf-hero-arrow taf-hero-next" aria-label="Slide sau"><?php echo taf_ic( 'chevron-right', 22, '#fff' ); ?></button>
			<div class="taf-hero-dots">
				<?php foreach ( $taf_slides as $si => $s ) : ?>
					<button type="button" class="taf-hero-dot<?php echo $si ? '' : ' is-active'; ?>" data-i="<?php echo $si; ?>" aria-label="Slide <?php echo $si + 1; ?>"></button>
				<?php endforeach; ?>
			</div>
		</div>
		<div class="taf-bar"><i></i></div>
	</section>

	<!-- ===================== VỀ CHÚNG TÔI ===================== -->
	<section class="taf-sec" style="background:#fff">
		<div class="taf-wrap taf-g2" style="padding:72px 24px">
			<div>
				<div class="taf-head"><div class="taf-eyebrow"><?php echo wp_kses_post( taf_opt( 'about_eyebrow' ) ); ?></div><h2 class="taf-h2"><?php echo wp_kses_post( taf_opt( 'about_h2' ) ); ?></h2></div>
				<p style="font-size:15.5px;line-height:1.7;color:var(--gray-700);margin:0 0 24px;max-width:520px"><?php echo wp_kses_post( taf_opt( 'about_lead' ) ); ?></p>
				<div style="display:grid;gap:14px">
					<?php foreach ( array( array( 'map-pin', taf_opt( 'about_b1' ) ), array( 'zap', taf_opt( 'about_b2' ) ), array( 'factory', taf_opt( 'about_b3' ) ), array( 'users', taf_opt( 'about_b4' ) ) ) as $b ) : ?>
						<div style="display:flex;gap:13px;align-items:flex-start">
							<span style="width:34px;height:34px;flex:0 0 auto;border-radius:2px;background:var(--navy-50);display:inline-flex;align-items:center;justify-content:center;color:var(--navy-900)"><?php echo taf_ic( $b[0], 17 ); ?></span>
							<span style="font-size:14.5px;line-height:1.5;color:var(--gray-700);padding-top:6px"><?php echo wp_kses_post( $b[1] ); ?></span>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
			<div style="position:relative">
				<?php echo taf_ph( 'Nhà máy sản xuất — KCN Hoàng Gia, Long An', 'factory', 400, taf_opt( 'about_img' ) ); ?>
				<div style="position:absolute;right:-2px;top:-2px;background:var(--red-600);color:#fff;padding:12px 18px;font-size:12px;font-weight:700;letter-spacing:.04em"><?php echo wp_kses_post( taf_opt( 'about_badge' ) ); ?></div>
			</div>
		</div>
	</section>

	<!-- ===================== VÌ SAO KHÁC BIỆT ===================== -->
	<section class="taf-sec" style="background:var(--gray-50);border-top:1px solid var(--border-1);border-bottom:1px solid var(--border-1)">
		<div class="taf-wrap" style="padding:72px 24px">
			<div class="taf-head--center"><div class="taf-eyebrow"><i></i><?php echo wp_kses_post( taf_opt( 'why_eyebrow' ) ); ?><i></i></div><h2 class="taf-h2"><?php echo wp_kses_post( taf_opt( 'why_h2' ) ); ?></h2></div>
			<div class="taf-g4">
				<?php foreach ( array( array( 'layers', taf_opt( 'why1_title' ), taf_opt( 'why1_desc' ) ), array( 'sun', taf_opt( 'why2_title' ), taf_opt( 'why2_desc' ) ), array( 'palette', taf_opt( 'why3_title' ), taf_opt( 'why3_desc' ) ), array( 'wrench', taf_opt( 'why4_title' ), taf_opt( 'why4_desc' ) ) ) as $c ) : ?>
					<div style="background:#fff;border:1px solid var(--border-1);padding:26px 22px">
						<div style="height:4px;width:38px;background:var(--red-600);margin-bottom:18px"></div>
						<span style="width:46px;height:46px;border-radius:2px;background:var(--navy-900);display:inline-flex;align-items:center;justify-content:center;color:#fff;margin-bottom:16px"><?php echo taf_ic( $c[0], 23 ); ?></span>
						<div style="font-size:16px;font-weight:700;color:var(--navy-900);margin-bottom:8px"><?php echo wp_kses_post( $c[1] ); ?></div>
						<div style="font-size:13.5px;line-height:1.6;color:var(--gray-600)"><?php echo wp_kses_post( $c[2] ); ?></div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<!-- ===================== CAM KẾT ===================== -->
	<section class="taf-sec" style="background:var(--navy-900)">
		<div class="taf-wrap" style="padding:64px 24px">
			<div class="taf-head--center"><div class="taf-eyebrow"><i></i><?php echo wp_kses_post( taf_opt( 'commit_eyebrow' ) ); ?><i></i></div><h2 class="taf-h2 taf-h2--light"><?php echo wp_kses_post( taf_opt( 'commit_h2' ) ); ?></h2></div>
			<div class="taf-g3">
				<?php foreach ( array( array( 'shield-check', taf_opt( 'commit1_title' ), taf_opt( 'commit1_desc' ) ), array( 'award', taf_opt( 'commit2_title' ), taf_opt( 'commit2_desc' ) ), array( 'headset', taf_opt( 'commit3_title' ), taf_opt( 'commit3_desc' ) ), array( 'handshake', taf_opt( 'commit4_title' ), taf_opt( 'commit4_desc' ) ), array( 'globe', taf_opt( 'commit5_title' ), taf_opt( 'commit5_desc' ) ), array( 'building-2', taf_opt( 'commit6_title' ), taf_opt( 'commit6_desc' ) ) ) as $it ) : ?>
					<div style="text-align:center;padding:12px 14px">
						<span style="width:64px;height:64px;border-radius:50%;border:2px solid rgba(255,255,255,.25);display:inline-flex;align-items:center;justify-content:center;color:#fff;margin-bottom:16px"><?php echo taf_ic( $it[0], 28, 'var(--red-600)' ); ?></span>
						<div style="font-size:16px;font-weight:700;color:#fff;margin-bottom:6px"><?php echo wp_kses_post( $it[1] ); ?></div>
						<div style="font-size:12.5px;line-height:1.5;color:#A9B4C6"><?php echo wp_kses_post( $it[2] ); ?></div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<!-- ===================== ỨNG DỤNG ===================== -->
	<section class="taf-sec" style="background:#fff">
		<div class="taf-wrap" style="padding:72px 24px">
			<div class="taf-head--center"><div class="taf-eyebrow"><i></i><?php echo wp_kses_post( taf_opt( 'apps_eyebrow' ) ); ?><i></i></div><h2 class="taf-h2"><?php echo wp_kses_post( taf_opt( 'apps_h2' ) ); ?></h2></div>
			<div class="taf-g3">
				<?php foreach ( array( array( taf_opt( 'app1_label' ), 'sun', taf_opt( 'app1_img' ) ), array( taf_opt( 'app2_label' ), 'panel-top', taf_opt( 'app2_img' ) ), array( taf_opt( 'app3_label' ), 'umbrella', taf_opt( 'app3_img' ) ), array( taf_opt( 'app4_label' ), 'columns-3', taf_opt( 'app4_img' ) ), array( taf_opt( 'app5_label' ), 'layout-grid', taf_opt( 'app5_img' ) ), array( taf_opt( 'app6_label' ), 'git-commit-horizontal', taf_opt( 'app6_img' ) ) ) as $a ) : ?>
					<div class="taf-app" style="position:relative;overflow:hidden;cursor:pointer">
						<?php echo taf_ph( $a[0], $a[1], 210, $a[2] ); ?>
						<div class="taf-app-ov" style="position:absolute;inset:0;background:linear-gradient(0deg,rgba(11,29,58,.82),rgba(11,29,58,0));opacity:0;transition:opacity var(--dur-med) var(--ease-out);display:flex;align-items:flex-end;padding:18px">
							<span style="color:#fff;font-weight:700;font-size:16px;display:inline-flex;align-items:center;gap:8px"><?php echo esc_html( $a[0] ); ?><?php echo taf_ic( 'arrow-right', 16, 'var(--red-600)' ); ?></span>
						</div>
						<div style="position:absolute;left:0;top:0;height:4px;width:34%;background:var(--red-600)"></div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<?php get_template_part( 'template-parts/front', 'products' ); ?>

	<!-- ===================== ĐÁNH GIÁ ===================== -->
	<?php if ( ! taf_block( 'trang-chu-danh-gia' ) ) : // UX Block "Khách hàng nói gì"; không có thì dùng bản dựng sẵn dưới đây. ?>
	<?php
	$taf_reviews = array(
		array( 'Trong các thiết kế không gian mở, tấm Poly đặc ruột Top Asia có độ truyền sáng cực kỳ trung thực, không bị đục hay ám xanh. Khả năng uốn cong linh hoạt giúp hiện thực hóa các mái vòm mềm mại mà kính rất khó thi công.', 'Phan Văn Vân', 'KTS · Công ty TNHH ORIS Việt Nam', '2026/07/kh-phan-van-van.png' ),
		array( 'Tấm Poly đặc ruột Top Asia có độ dẻo dai và chịu va đập cực tốt, thợ thoải mái bắn vít mà không lo dập vỡ. Khổ tấm chuẩn chỉ, độ dày đúng cam kết giúp lắp đặt rất nhanh, kịp tiến độ.', 'Anh Thản', 'Mái nhà Việt Poly', '2026/07/kh-anh-than.png' ),
		array( 'Công trình dự án lớn đòi hỏi khắt khe về CO/CQ — Top Asia cung cấp đầy đủ và nhanh chóng nên khâu nghiệm thu rất mượt. Giao hàng tận chân công trình đúng hẹn, giúp chủ động tiến độ.', 'Đàm Văn Dũng', 'GĐ XD · Royal Việt Nam', '2026/07/kh-dam-van-dung.png' ),
		array( 'Tôi đánh giá cao Top Asia ở khâu tư vấn kỹ thuật: tính toán khẩu độ xà gồ, cách đi keo, nẹp nhôm nối tấm chống dột. Bảo hành rõ ràng bằng văn bản giúp uy tín nhà thầu được nâng lên.', 'Lê Long Anh', 'GĐ Công ty thiết kế MP', '2026/07/kh-le-long-anh.png' ),
	);
	?>
	<section class="taf-sec" style="background:#fff">
		<div class="taf-wrap" style="max-width:1000px;padding:72px 24px">
			<div class="taf-head--center"><div class="taf-eyebrow"><i></i>Khách hàng nói gì<i></i></div><h2 class="taf-h2">Được tin dùng bởi kiến trúc sư &amp; nhà thầu</h2></div>
			<div class="taf-reviews" style="position:relative;background:var(--gray-50);border:1px solid var(--border-1);padding:44px 48px" data-taf-total="<?php echo count( $taf_reviews ); ?>">
				<div style="height:5px;width:64px;background:var(--red-600);margin-bottom:22px"></div>
				<span class="ic" style="width:34px;height:34px;color:var(--navy-100);position:absolute;right:40px;top:34px"><i data-lucide="quote"></i></span>
				<?php foreach ( $taf_reviews as $ri => $r ) : ?>
					<?php
					$taf_p = explode( ' ', $r[1] ); $taf_ini = mb_substr( (string) end( $taf_p ), 0, 1 );
					// Ảnh đại diện = phần tử thứ 4 của mỗi review: tên file trong wp-content/uploads (vd '2026/07/kh-phan-van-van.jpg') hoặc URL đầy đủ. Rỗng -> vòng tròn chữ cái.
					$taf_av = isset( $r[3] ) ? trim( (string) $r[3] ) : ''; $taf_av_url = '';
					if ( $taf_av ) {
						if ( preg_match( '#^https?://#', $taf_av ) ) { $taf_av_url = $taf_av; }
						elseif ( file_exists( WP_CONTENT_DIR . '/uploads/' . ltrim( $taf_av, '/' ) ) ) { $taf_av_url = content_url( '/uploads/' . ltrim( $taf_av, '/' ) ); }
					}
					?>
					<div class="taf-review" data-i="<?php echo $ri; ?>" style="<?php echo $ri ? 'display:none' : ''; ?>">
						<p style="font-size:18px;line-height:1.65;color:var(--navy-900);font-weight:500;margin:0 0 28px;min-height:120px"><?php echo esc_html( $r[0] ); ?></p>
						<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px">
							<div style="display:flex;align-items:center;gap:14px">
								<?php if ( $taf_av_url ) : ?>
									<img src="<?php echo esc_url( $taf_av_url ); ?>" alt="<?php echo esc_attr( $r[1] ); ?>" loading="lazy" style="width:52px;height:52px;border-radius:50%;object-fit:cover;flex:0 0 auto;background:var(--navy-900)">
								<?php else : ?>
									<div style="width:52px;height:52px;border-radius:50%;background:var(--navy-900);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:18px;flex:0 0 auto"><?php echo esc_html( $taf_ini ); ?></div>
								<?php endif; ?>
								<div><div style="font-size:15px;font-weight:700;color:var(--navy-900)"><?php echo esc_html( $r[1] ); ?></div><div style="font-size:12.5px;color:var(--gray-500)"><?php echo esc_html( $r[2] ); ?></div></div>
							</div>
							<div style="display:flex;gap:8px">
								<button type="button" aria-label="Trước" class="taf-rev-prev" style="all:unset;cursor:pointer;width:42px;height:42px;display:inline-flex;align-items:center;justify-content:center;border:1px solid var(--border-2);color:var(--navy-900);background:#fff"><span class="ic" style="width:18px;height:18px"><i data-lucide="chevron-left"></i></span></button>
								<button type="button" aria-label="Sau" class="taf-rev-next" style="all:unset;cursor:pointer;width:42px;height:42px;display:inline-flex;align-items:center;justify-content:center;border:1px solid var(--border-2);color:var(--navy-900);background:#fff"><span class="ic" style="width:18px;height:18px"><i data-lucide="chevron-right"></i></span></button>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
				<div class="taf-rev-dots" style="display:flex;gap:6px;margin-top:22px">
					<?php foreach ( $taf_reviews as $ri => $r ) : ?>
						<button type="button" aria-label="Đánh giá <?php echo $ri + 1; ?>" class="taf-rev-dot" data-i="<?php echo $ri; ?>" style="width:<?php echo $ri ? '8' : '24'; ?>px;height:8px;border:none;padding:0;cursor:pointer;background:<?php echo $ri ? 'var(--gray-300)' : 'var(--red-600)'; ?>;transition:all var(--dur-med) var(--ease-out)"></button>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
	</section>

	<?php endif; // /taf_block trang-chu-danh-gia ?>

	<!-- (Section THỐNG KÊ đã bỏ 25/07/2026 — tránh trùng băng số liệu ở Footer) -->

	<!-- ===================== TIN TỨC ===================== -->
	<section class="taf-sec" style="background:var(--gray-50);border-top:1px solid var(--border-1)">
		<div class="taf-wrap" style="padding:72px 24px">
			<div style="display:flex;align-items:flex-end;justify-content:space-between;gap:20px;margin-bottom:34px;flex-wrap:wrap">
				<div><div class="taf-eyebrow"><?php echo wp_kses_post( taf_opt( 'news_eyebrow' ) ); ?></div><h2 class="taf-h2"><?php echo wp_kses_post( taf_opt( 'news_h2' ) ); ?></h2></div>
				<a href="<?php echo esc_url( home_url( '/tin-tuc-top-asia/' ) ); ?>" style="font-size:13px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--red-600);display:inline-flex;align-items:center;gap:7px;padding-bottom:6px">Xem tất cả<?php echo taf_ic( 'arrow-right', 14 ); ?></a>
			</div>
			<div class="taf-g4">
				<?php
				$taf_news = new WP_Query( array( 'post_type' => 'post', 'posts_per_page' => 4, 'ignore_sticky_posts' => 1 ) );
				if ( $taf_news->have_posts() ) :
					while ( $taf_news->have_posts() ) : $taf_news->the_post();
						$thumb = get_the_post_thumbnail_url( get_the_ID(), 'medium' );
						?>
						<a href="<?php the_permalink(); ?>" style="display:block;background:#fff;border:1px solid var(--border-1)">
							<div style="position:relative">
								<?php echo taf_ph( 'Bài viết', 'newspaper', 150, $thumb ); ?>
								<span style="position:absolute;left:0;bottom:0;background:var(--red-600);color:#fff;font-size:11px;font-weight:700;padding:5px 11px"><?php echo esc_html( get_the_date( 'd \T\hn' ) ); ?></span>
							</div>
							<div style="padding:16px 16px 20px"><div style="font-size:14.5px;font-weight:600;line-height:1.45;color:var(--navy-900)"><?php echo esc_html( wp_trim_words( get_the_title(), 14 ) ); ?></div></div>
						</a>
						<?php
					endwhile;
					wp_reset_postdata();
				endif;
				?>
			</div>
		</div>
	</section>

</div><!-- .taf-home -->

<!-- Quote dialog -->
<div id="taf-quote" class="taf-dlg taf-home" style="display:none;position:fixed;inset:0;z-index:100000;background:rgba(11,29,58,.55);align-items:flex-start;justify-content:center;padding-top:90px" role="dialog" aria-modal="true" aria-label="Nhận báo giá">
	<div style="width:480px;max-width:92vw;background:#fff;box-shadow:var(--shadow-lg);overflow:hidden">
		<div class="taf-bar"><i></i></div>
		<div style="padding:22px 26px 6px">
			<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px">
				<h3 style="font-size:19px;font-weight:700;color:var(--navy-900);margin:0">Nhận báo giá trực tiếp từ nhà máy</h3>
				<button type="button" data-taf-close aria-label="Đóng" style="all:unset;cursor:pointer;color:var(--gray-400);font-size:22px;line-height:1">&times;</button>
			</div>
			<p style="font-size:11px;color:var(--red-600);margin:6px 0 18px;text-transform:uppercase;letter-spacing:.12em;font-weight:600">Đăng ký tư vấn</p>
			<div style="display:grid;gap:14px">
				<label style="display:block"><span style="font-size:12.5px;font-weight:600;color:var(--navy-900);display:block;margin-bottom:6px">Họ và tên</span><input type="text" placeholder="Nguyễn Văn A" style="width:100%;height:44px;padding:0 14px;border:1px solid var(--border-2);border-radius:2px;font:15px var(--font-sans);color:var(--navy-900)"></label>
				<label style="display:block"><span style="font-size:12.5px;font-weight:600;color:var(--navy-900);display:block;margin-bottom:6px">Số điện thoại</span><input type="tel" placeholder="0896 699 996" style="width:100%;height:44px;padding:0 14px;border:1px solid var(--border-2);border-radius:2px;font:15px var(--font-sans);color:var(--navy-900)"></label>
				<label style="display:block"><span style="font-size:12.5px;font-weight:600;color:var(--navy-900);display:block;margin-bottom:6px">Dòng sản phẩm quan tâm</span><select style="width:100%;height:44px;padding:0 12px;border:1px solid var(--border-2);border-radius:2px;font:15px var(--font-sans);color:var(--navy-900);background:#fff"><option>Asia Lite (Luxury)</option><option>Asia Pearl (Deluxe)</option><option>Asia Gold (Tiêu chuẩn)</option><option>Phụ kiện chuyên dụng</option></select></label>
			</div>
		</div>
		<div style="display:flex;justify-content:flex-end;gap:10px;padding:16px 26px;margin-top:12px;border-top:1px solid var(--border-1)">
			<button type="button" data-taf-close class="taf-btn taf-btn--ghost taf-btn--sm">Huỷ</button>
			<button type="button" data-taf-submit class="taf-btn taf-btn--accent taf-btn--sm">Gửi yêu cầu</button>
		</div>
	</div>
</div>
<!-- Toast -->
<div id="taf-toast" class="taf-home" style="display:none;position:fixed;right:24px;bottom:24px;z-index:100001;align-items:center;gap:12px;background:#fff;box-shadow:var(--shadow-lg);border-left:4px solid #1E7A4C;padding:14px 18px"><span class="taf-toast-msg" style="font-size:14px;color:var(--navy-900);font-weight:500"></span></div>

<?php get_footer(); ?>
