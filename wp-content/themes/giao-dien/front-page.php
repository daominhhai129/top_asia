<?php
/**
 * Front page — TOP ASIA homepage (Design System v1).
 * Chỉ ĐỌC dữ liệu hiện có (WooCommerce, bài viết, Customizer). Không ghi DB.
 * Phiên bản cũ: git tag baseline-pre-redesign.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ------------------------------------------------------------------ helpers */
if ( ! function_exists( 'ta_url' ) ) {
	function ta_url( $path ) { return esc_url( home_url( '/' . ltrim( $path, '/' ) ) ); }
}
if ( ! function_exists( 'ta_arrow' ) ) {
	function ta_arrow() { return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 5l7 7-7 7"/></svg>'; }
}
if ( ! function_exists( 'ta_img' ) ) {
	/** Ảnh responsive từ attachment: lazy, có width/height để không gây CLS. */
	function ta_img( $att_id, $size = 'large', $alt = '', $eager = false, $sizes = '(min-width:1024px) 33vw, 100vw' ) {
		if ( ! $att_id ) { return ''; }
		return wp_get_attachment_image( $att_id, $size, false, array(
			'alt'           => $alt,
			'loading'       => $eager ? 'eager' : 'lazy',
			'decoding'      => 'async',
			'fetchpriority' => $eager ? 'high' : 'auto',
			'sizes'         => $sizes,
		) );
	}
}
if ( ! function_exists( 'ta_line_data' ) ) {
	/** Dữ liệu một dòng sản phẩm (đọc từ product_cat + sản phẩm thật), cache 6 giờ. */
	function ta_line_data( $slug ) {
		$key = 'ta_line_' . md5( $slug );
		$c   = get_transient( $key );
		if ( false !== $c ) { return $c; }

		$out  = array( 'link' => '', 'count' => 0, 'thumb' => 0, 'thick' => '', 'width' => '' );
		$term = get_term_by( 'slug', $slug, 'product_cat' );
		if ( $term && ! is_wp_error( $term ) ) {
			$link = get_term_link( $term );
			$out['link']  = is_wp_error( $link ) ? '' : $link;
			$out['count'] = (int) $term->count;
			$thumb        = (int) get_term_meta( $term->term_id, 'thumbnail_id', true );
			$ids = get_posts( array(
				'post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => 400, 'fields' => 'ids', 'no_found_rows' => true,
				'tax_query' => array( array( 'taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => $term->term_id ) ),
			) );
			if ( ! $thumb ) {
				foreach ( $ids as $pid ) { $t = (int) get_post_thumbnail_id( $pid ); if ( $t ) { $thumb = $t; break; } }
			}
			$out['thumb'] = $thumb;
			if ( $ids ) {
				$range = function ( $tax ) use ( $ids ) {
					$terms = wp_get_object_terms( $ids, $tax, array( 'fields' => 'names' ) );
					if ( is_wp_error( $terms ) || ! $terms ) { return ''; }
					$nums = array();
					foreach ( $terms as $n ) { if ( preg_match( '/(\d+(?:[.,]\d+)?)/', $n, $m ) ) { $nums[] = (float) str_replace( ',', '.', $m[1] ); } }
					if ( ! $nums ) { return ''; }
					sort( $nums );
					$fmt = function ( $v ) { return rtrim( rtrim( number_format( $v, 2, ',', '' ), '0' ), ',' ); };
					return ( $nums[0] === end( $nums ) ) ? $fmt( $nums[0] ) : $fmt( $nums[0] ) . ' – ' . $fmt( end( $nums ) );
				};
				$t = $range( 'pa_do-day' );  $out['thick'] = $t ? $t . ' mm' : '';
				$w = $range( 'pa_kho-rong' ); $out['width'] = $w ? $w . ' m' : '';
			}
		}
		set_transient( $key, $out, 6 * HOUR_IN_SECONDS );
		return $out;
	}
}

/* ----------------------------------------------- dữ kiện (chủ sở hữu cần xác nhận) */
// Lấy từ nội dung đã có trên website (Customizer / footer). Sửa tại đây hoặc Tuỳ biến.
$ta_facts = array(
	array( '10.000', 'tấn / năm', 'Công suất thiết kế' ),
	array( '2014', 'năm thành lập', 'Hơn một thập kỷ sản xuất' ),
	array( '10', 'năm bảo hành', 'Theo chính sách bảo hành' ),
	array( '34+', 'tỉnh thành', 'Mạng lưới cung ứng' ),
);
$ta_hero_img = taf_opt( 'hero1_img' );

get_header();
?>
<main id="ta-home" class="ta-home">

	<!-- 01 · HERO -->
	<section class="ta-hero" aria-labelledby="ta-hero-title">
		<div class="ta-hero__panel">
			<div class="ta-hero__inner">
				<span class="ta-eyebrow ta-eyebrow--light">Polycarbonate · Kiến trúc hiện đại</span>
				<h1 id="ta-hero-title" class="ta-hero__title">KIẾN TẠO KHÔNG GIAN.<br><span>NÂNG TẦM KIẾN TRÚC.</span></h1>
				<p class="ta-hero__sub">Giải pháp Polycarbonate chất lượng cao cho kiến trúc hiện đại.</p>
				<p class="ta-hero__desc">TOP ASIA là đơn vị sản xuất và cung cấp tấm Polycarbonate tại Việt Nam, mang đến giải pháp vật liệu lấy sáng cho các công trình dân dụng, thương mại và công nghiệp.</p>
				<div class="ta-hero__cta">
					<a class="tx-btn tx-btn--accent tx-btn--lg" href="#san-pham">Khám phá sản phẩm</a>
					<a class="tx-btn tx-btn--light tx-btn--lg" href="<?php echo ta_url( 'bao-gia/' ); ?>">Nhận báo giá</a>
				</div>
			</div>
		</div>
		<div class="ta-hero__media">
			<img src="<?php echo esc_url( $ta_hero_img ); ?>" alt="Mái lấy sáng Polycarbonate TOP ASIA cho công trình kiến trúc hiện đại" width="1200" height="1400" fetchpriority="high" decoding="async">
		</div>
	</section>

	<!-- 02 · NĂNG LỰC -->
	<section class="ta-sec ta-capability" id="nang-luc" aria-labelledby="ta-cap-title">
		<div class="ta-container">
			<div class="ta-cap__grid">
				<div class="ta-cap__text">
					<span class="ta-eyebrow">Năng lực nhà sản xuất</span>
					<h2 id="ta-cap-title" class="ta-h2">NĂNG LỰC SẢN XUẤT – GIÁ TRỊ TỪ NHÀ SẢN XUẤT</h2>
					<p class="ta-lead">Chủ động sản xuất, kiểm soát chất lượng và không ngừng cải tiến để mang đến các giải pháp Polycarbonate phù hợp với nhu cầu của thị trường.</p>
					<ul class="ta-cap__list">
						<li><b>Nhà máy</b><span>KCN Hoàng Gia – sản xuất tập trung, chủ động nguồn cung.</span></li>
						<li><b>Dây chuyền sản xuất</b><span>Độ dày đa dạng, khổ tấm theo quy cách và theo hợp đồng.</span></li>
						<li><b>Công nghệ</b><span>Lớp phủ chống tia UV bảo vệ bề mặt tấm.</span></li>
						<li><b>Kiểm soát chất lượng</b><span>Kiểm định theo ISO 9001:2015 và tiêu chuẩn kỹ thuật Việt Nam.</span></li>
						<li><b>Năng lực cung ứng</b><span>Báo giá trực tiếp từ nhà sản xuất cho dự án và nhà phân phối.</span></li>
					</ul>
					<a class="ta-more" href="<?php echo ta_url( 'nha-may-san-xuat-tam-polycarbonate-top-asia/' ); ?>">Xem nhà máy &amp; năng lực sản xuất</a>
				</div>
				<div class="ta-cap__visual">
					<img src="<?php echo esc_url( taf_opt( 'about_img' ) ); ?>" alt="Nhà máy sản xuất tấm Polycarbonate TOP ASIA" width="900" height="1000" loading="lazy" decoding="async">
				</div>
			</div>
			<dl class="ta-facts">
				<?php foreach ( $ta_facts as $f ) : ?>
					<div class="ta-fact"><dt><?php echo esc_html( $f[0] ); ?><small><?php echo esc_html( $f[1] ); ?></small></dt><dd><?php echo esc_html( $f[2] ); ?></dd></div>
				<?php endforeach; ?>
			</dl>
		</div>
	</section>

	<!-- 03 · SẢN PHẨM -->
	<section class="ta-sec ta-sec--soft" id="san-pham" aria-labelledby="ta-prod-title">
		<div class="ta-container">
			<header class="ta-head">
				<span class="ta-eyebrow">Sản phẩm</span>
				<h2 id="ta-prod-title" class="ta-h2">HỆ SINH THÁI SẢN PHẨM TOP ASIA</h2>
				<p class="ta-lead">Ba dòng sản phẩm, ba lựa chọn phù hợp với từng nhu cầu công trình.</p>
			</header>
			<div class="ta-lines">
				<?php
				$ta_lines = array(
					array( 'gold',  'ASIA GOLD',  'tam-nhua-polycarbonate-cuong-luc-asia-gold',  'Giải pháp tiêu chuẩn, tối ưu hiệu quả đầu tư.', 'Tiêu chuẩn' ),
					array( 'pearl', 'ASIA PEARL', 'tam-nhua-polycarbonate-cuong-luc-asia-pearl', 'Sự cân bằng giữa chất lượng, thẩm mỹ và hiệu quả sử dụng.', 'Cân bằng' ),
					array( 'lite',  'ASIA LITE',  'tam-nhua-polycarbonate-cuong-luc-asia-lite',  'Giải pháp cao cấp dành cho những công trình có yêu cầu cao về vật liệu.', 'Cao cấp' ),
				);
				foreach ( $ta_lines as $ln ) :
					$d    = ta_line_data( $ln[2] );
					$link = $d['link'] ? $d['link'] : ta_url( 'danh-muc-san-pham/' . $ln[2] . '/' );
				?>
					<article class="ta-line ta-line-<?php echo esc_attr( $ln[0] ); ?>">
						<a class="ta-line__img" href="<?php echo esc_url( $link ); ?>" tabindex="-1" aria-hidden="true">
							<?php echo ta_img( $d['thumb'], 'medium_large', $ln[1] . ' – tấm Polycarbonate TOP ASIA' ); ?>
						</a>
						<div class="ta-line__body">
							<span class="ta-line__tag"><?php echo esc_html( $ln[4] ); ?></span>
							<h3 class="ta-line__name"><?php echo esc_html( $ln[1] ); ?></h3>
							<p class="ta-line__desc"><?php echo esc_html( $ln[3] ); ?></p>
							<dl class="ta-line__specs">
								<?php if ( $d['thick'] ) : ?><div><dt>Độ dày</dt><dd><?php echo esc_html( $d['thick'] ); ?></dd></div><?php endif; ?>
								<?php if ( $d['width'] ) : ?><div><dt>Khổ rộng</dt><dd><?php echo esc_html( $d['width'] ); ?></dd></div><?php endif; ?>
								<?php if ( $d['count'] ) : ?><div><dt>Quy cách</dt><dd><?php echo esc_html( $d['count'] ); ?></dd></div><?php endif; ?>
							</dl>
							<a class="tx-btn tx-btn--ghost ta-line__btn" href="<?php echo esc_url( $link ); ?>">Xem chi tiết <?php echo ta_arrow(); ?></a>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<!-- 04 · ỨNG DỤNG -->
	<section class="ta-sec" id="ung-dung" aria-labelledby="ta-app-title">
		<div class="ta-container">
			<header class="ta-head">
				<span class="ta-eyebrow">Ứng dụng</span>
				<h2 id="ta-app-title" class="ta-h2">GIẢI PHÁP CHO MỌI CÔNG TRÌNH</h2>
			</header>
			<?php
			$ta_apps = array(
				array( 'Mái lấy sáng', taf_opt( 'app1_img' ), 'Lấy sáng tự nhiên cho nhà ở, nhà xưởng và công trình công cộng.' ),
				array( 'Giếng trời', taf_opt( 'app2_img' ), 'Đưa ánh sáng vào không gian kín, vẫn chắn mưa và chống nóng.' ),
				array( 'Mái che', taf_opt( 'app3_img' ), 'Canopy, sân, lối đi, bãi xe — nhẹ, bền, thi công nhanh.' ),
				array( 'Mái vòm', taf_opt( 'app6_img' ), 'Uốn cong linh hoạt cho những hình khối kiến trúc riêng.' ),
				array( 'Nhà xưởng', '', 'Lấy sáng cho nhà máy, kho bãi, giảm chi phí chiếu sáng ban ngày.' ),
				array( 'Công trình thương mại', '', 'Showroom, trung tâm thương mại, mặt dựng và không gian công cộng.' ),
				array( 'Vách ngăn', taf_opt( 'app4_img' ), 'Phân chia không gian mà vẫn giữ độ thông thoáng, trong sáng.' ),
			);
			?>
			<div class="ta-apps">
				<?php foreach ( $ta_apps as $i => $a ) : ?>
					<article class="ta-app<?php echo $a[1] ? '' : ' ta-app--text'; ?>">
						<?php if ( $a[1] ) : ?><img src="<?php echo esc_url( $a[1] ); ?>" alt="<?php echo esc_attr( $a[0] . ' bằng Polycarbonate' ); ?>" width="800" height="600" loading="lazy" decoding="async"><?php endif; ?>
						<div class="ta-app__cap"><span class="ta-app__n"><?php echo esc_html( sprintf( '%02d', $i + 1 ) ); ?></span><h3><?php echo esc_html( $a[0] ); ?></h3><p><?php echo esc_html( $a[2] ); ?></p></div>
					</article>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<!-- 05 · DỰ ÁN -->
	<section class="ta-sec ta-sec--soft" id="du-an" aria-labelledby="ta-proj-title">
		<div class="ta-container">
			<header class="ta-head ta-head--row">
				<div>
					<span class="ta-eyebrow">Dự án</span>
					<h2 id="ta-proj-title" class="ta-h2">DẤU ẤN TOP ASIA TRONG NHỮNG CÔNG TRÌNH THỰC TẾ</h2>
				</div>
				<a class="ta-more" href="<?php echo ta_url( 'du-an/' ); ?>">Xem tất cả dự án</a>
			</header>
			<?php
			$ta_proj = new WP_Query( array(
				'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 6, 'ignore_sticky_posts' => true, 'no_found_rows' => true,
				'category_name' => 'du-an,cong-trinh-thuc-te,cong-trinh-tieu-bieu',
				'meta_query' => array( array( 'key' => '_thumbnail_id', 'compare' => 'EXISTS' ) ),
			) );
			if ( $ta_proj->have_posts() ) : ?>
				<div class="ta-projects">
					<?php while ( $ta_proj->have_posts() ) : $ta_proj->the_post(); ?>
						<a class="ta-proj" href="<?php the_permalink(); ?>">
							<span class="ta-proj__img"><?php echo ta_img( get_post_thumbnail_id(), 'medium_large', get_the_title() ); ?></span>
							<span class="ta-proj__body"><span class="ta-proj__date"><?php echo esc_html( get_the_date( 'm/Y' ) ); ?></span><span class="ta-proj__title"><?php the_title(); ?></span></span>
						</a>
					<?php endwhile; wp_reset_postdata(); ?>
				</div>
			<?php endif; ?>
		</div>
	</section>

	<!-- 06 · CHẤT LƯỢNG -->
	<section class="ta-sec ta-quality" id="chat-luong" aria-labelledby="ta-q-title">
		<div class="ta-container">
			<header class="ta-head ta-head--light">
				<span class="ta-eyebrow ta-eyebrow--light">Chất lượng</span>
				<h2 id="ta-q-title" class="ta-h2">CHẤT LƯỢNG TẠO DỰNG NIỀM TIN</h2>
			</header>
			<div class="ta-q__grid">
				<a class="ta-q" href="<?php echo ta_url( 'tai-ve/' ); ?>"><b>Chứng nhận</b><strong>ISO 9001:2015</strong><span>Hệ thống quản lý chất lượng. Xem tài liệu và chứng chỉ.</span></a>
				<a class="ta-q" href="<?php echo ta_url( 'ho-tro-ky-thuat/' ); ?>"><b>Tiêu chuẩn kỹ thuật</b><strong>TCVN</strong><span>Đáp ứng tiêu chuẩn kỹ thuật Việt Nam cho vật liệu xây dựng.</span></a>
				<a class="ta-q" href="<?php echo ta_url( 'chinh-sach-bao-hanh/' ); ?>"><b>Chính sách bảo hành</b><strong>10 năm</strong><span>Cam kết bằng văn bản. Xem điều kiện bảo hành chi tiết.</span></a>
				<a class="ta-q" href="<?php echo ta_url( 'nha-may-san-xuat-tam-polycarbonate-top-asia/' ); ?>"><b>Kiểm soát chất lượng</b><strong>Tại nhà máy</strong><span>Quy trình kiểm soát từ nguyên liệu đến thành phẩm.</span></a>
			</div>
		</div>
	</section>

	<!-- 07 · ĐỐI TÁC -->
	<section class="ta-sec" id="doi-tac" aria-labelledby="ta-partner-title">
		<div class="ta-container ta-partner">
			<div>
				<span class="ta-eyebrow">Đối tác</span>
				<h2 id="ta-partner-title" class="ta-h2">ĐỒNG HÀNH CÙNG ĐỐI TÁC VÀ NHÀ PHÂN PHỐI</h2>
				<p class="ta-lead">Năng lực cung ứng ổn định, báo giá trực tiếp từ nhà sản xuất và đội ngũ kỹ thuật hỗ trợ từ khâu chọn quy cách đến thi công.</p>
			</div>
			<div class="ta-partner__cards">
				<a class="ta-pcard" href="<?php echo ta_url( 'tuyen-dai-ly/' ); ?>"><h3>Nhà phân phối &amp; đại lý</h3><p>Chính sách hợp tác, nguồn hàng ổn định và hỗ trợ bán hàng.</p><span class="ta-more">Trở thành đối tác</span></a>
				<a class="ta-pcard" href="<?php echo ta_url( 'chu-dau-tu-nha-thau/' ); ?>"><h3>Chủ đầu tư &amp; nhà thầu</h3><p>Tư vấn quy cách, báo giá dự án và hỗ trợ kỹ thuật thi công.</p><span class="ta-more">Hợp tác dự án</span></a>
			</div>
		</div>
	</section>

	<!-- 08 · TÀI LIỆU & KIẾN THỨC -->
	<section class="ta-sec ta-sec--soft" id="tai-lieu" aria-labelledby="ta-doc-title">
		<div class="ta-container">
			<header class="ta-head">
				<span class="ta-eyebrow">Tài liệu &amp; kiến thức</span>
				<h2 id="ta-doc-title" class="ta-h2">TRUNG TÂM TÀI LIỆU TOP ASIA</h2>
			</header>
			<?php
			$kt = get_category_by_slug( 'kien-thuc-ky-thuat' );
			$docs = array(
				array( 'Catalogue', 'Danh mục sản phẩm và quy cách.', ta_url( 'tai-ve/' ) ),
				array( 'Bảng giá', 'Báo giá tấm Polycarbonate TOP ASIA.', ta_url( 'bao-gia/' ) ),
				array( 'Tài liệu kỹ thuật', 'Thông số, chứng chỉ, hồ sơ năng lực.', ta_url( 'ho-tro-ky-thuat/' ) ),
				array( 'Hướng dẫn thi công', 'Lắp đặt đúng kỹ thuật, bền theo thời gian.', ta_url( 'ho-tro-ky-thuat/' ) ),
				array( 'Tin tức', 'Cập nhật từ TOP ASIA và thị trường.', ta_url( 'tin-tuc/' ) ),
				array( 'Kiến thức Polycarbonate', 'Cẩm nang chọn và sử dụng tấm lấy sáng.', $kt ? esc_url( get_category_link( $kt->term_id ) ) : ta_url( 'tam-lop-lay-sang-polycarbonate/' ) ),
			);
			?>
			<div class="ta-docs">
				<?php foreach ( $docs as $d ) : ?>
					<a class="ta-doc" href="<?php echo $d[2]; ?>"><h3><?php echo esc_html( $d[0] ); ?></h3><p><?php echo esc_html( $d[1] ); ?></p><?php echo ta_arrow(); ?></a>
				<?php endforeach; ?>
			</div>
			<?php
			$ta_news = new WP_Query( array( 'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 3, 'ignore_sticky_posts' => true, 'no_found_rows' => true, 'category_name' => 'kien-thuc-ky-thuat,tin-tuc-bao-gia,top-asia-cap-nhat' ) );
			if ( $ta_news->have_posts() ) : ?>
				<ul class="ta-news">
					<?php while ( $ta_news->have_posts() ) : $ta_news->the_post(); ?>
						<li><a href="<?php the_permalink(); ?>"><time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date( 'd/m/Y' ) ); ?></time><span><?php the_title(); ?></span></a></li>
					<?php endwhile; wp_reset_postdata(); ?>
				</ul>
			<?php endif; ?>
		</div>
	</section>

	<!-- 09 · CTA -->
	<section class="ta-final" aria-labelledby="ta-final-title">
		<div class="ta-container ta-final__inner">
			<div>
				<h2 id="ta-final-title" class="ta-h2">CÙNG TOP ASIA KIẾN TẠO NHỮNG CÔNG TRÌNH BỀN VỮNG.</h2>
				<p>Liên hệ TOP ASIA để được tư vấn sản phẩm và giải pháp Polycarbonate phù hợp với dự án của bạn.</p>
			</div>
			<div class="ta-final__btns">
				<a class="tx-btn tx-btn--accent tx-btn--lg" href="<?php echo ta_url( 'lien-he/' ); ?>">Liên hệ tư vấn</a>
				<a class="tx-btn tx-btn--light tx-btn--lg" href="<?php echo ta_url( 'bao-gia/' ); ?>">Yêu cầu báo giá</a>
			</div>
		</div>
	</section>
</main>
<?php get_footer(); ?>
