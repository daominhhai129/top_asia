<?php
/**
 * Top Asia — global site footer data and renderer.
 *
 * Synced with https://topasia.vn/ footer data and links while resolving all
 * internal URLs dynamically via home_url() / get_term_link() so staging and
 * local environments never rely on stale hardcoded paths or DB widgets.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'topasia_footer_term_url' ) ) {
	/**
	 * Resolve a taxonomy term link safely with a clean path fallback.
	 */
	function topasia_footer_term_url( $slug, $taxonomy = 'product_cat' ) {
		$term = get_term_by( 'slug', $slug, $taxonomy );
		if ( $term && ! is_wp_error( $term ) ) {
			$link = get_term_link( $term );
			if ( ! is_wp_error( $link ) ) {
				return $link;
			}
		}

		$prefix = ( 'product_cat' === $taxonomy ) ? '/danh-muc-san-pham/' : '/';
		return home_url( $prefix . trim( (string) $slug, '/' ) . '/' );
	}
}

if ( ! function_exists( 'topasia_footer_config' ) ) {
	/**
	 * Central configuration for the Top Asia global footer.
	 */
	function topasia_footer_config() {
		$logo_rel  = '/assets/img/logo-reversed.png';
		$logo_path = get_stylesheet_directory() . $logo_rel;
		$logo_url  = file_exists( $logo_path )
			? get_stylesheet_directory_uri() . $logo_rel
			: home_url( '/wp-content/uploads/2026/06/logo-reversed.png' );

		return array(
			'intro'   => array(
				'kicker' => 'TOP ASIA',
				'title'  => 'Polycarbonate cho mọi hạng mục công trình',
				'copy'   => 'Từ giếng trời, mái canopy, trang trí mặt tiền đến mái vòm uốn cong, Top Asia đồng hành cùng chủ đầu tư và nhà thầu trong việc chọn quy cách và giải pháp thi công.',
			),
			'stats'   => array(
				array( 'value' => '2014', 'label' => 'Năm thành lập' ),
				array( 'value' => '34+', 'label' => 'Tỉnh thành phân phối' ),
				array( 'value' => '10.000', 'label' => 'Tấn / năm' ),
				array( 'value' => '5 quốc gia', 'label' => 'Đã xuất khẩu' ),
			),
			'company' => array(
				'heading' => 'Thông tin',
				'logo'    => $logo_url,
				'name_vi' => 'CÔNG TY CỔ PHẦN ĐẦU TƯ VÀ SẢN XUẤT TOP ASIA',
				'name_en' => 'TOP ASIA PRODUCTION AND INVESTMENT JOINT STOCK COMPANY',
				'places'  => array(
					array(
						'label' => 'Địa chỉ',
						'value' => 'LK65 - KĐT Dương Nội - Phường Dương Nội - Quận Hà Đông - Hà Nội - Việt Nam',
					),
					array(
						'label' => 'Kho hàng',
						'value' => 'ĐT72, La Dương, Hà Đông, Hà Nội',
					),
					array(
						'label' => 'Nhà máy',
						'value' => 'Số 702 KCN Hoàng Gia - X. Mỹ Hạnh Nam - H. Đức Hòa - Long An - Việt Nam',
					),
				),
			),
			'columns' => array(
				array(
					'heading' => 'Giới thiệu',
					'aria'    => 'Giới thiệu',
					'links'   => array(
						array( 'label' => 'Trang chủ', 'url' => home_url( '/' ) ),
						array( 'label' => 'Giới thiệu', 'url' => home_url( '/gioi-thieu/' ) ),
						array( 'label' => 'Sản phẩm', 'url' => home_url( '/tat-ca-san-pham/' ) ),
						array( 'label' => 'Dịch vụ', 'url' => home_url( '/' ) ),
						array( 'label' => 'Dự án thực tế', 'url' => home_url( '/du-an/' ) ),
						array( 'label' => 'Tin tức', 'url' => home_url( '/tin-tuc/' ) ),
						array( 'label' => 'Liên hệ', 'url' => home_url( '/lien-he/' ) ),
					),
				),
				array(
					'heading' => 'Sản phẩm & Hỗ trợ',
					'aria'    => 'Sản phẩm và hỗ trợ công trình',
					'links'   => array(
						array( 'label' => 'Asia Gold', 'url' => topasia_footer_term_url( 'tam-nhua-polycarbonate-cuong-luc-asia-gold' ) ),
						array( 'label' => 'Asia Pearl', 'url' => topasia_footer_term_url( 'tam-nhua-polycarbonate-cuong-luc-asia-pearl' ) ),
						array( 'label' => 'Asia Lite', 'url' => topasia_footer_term_url( 'tam-nhua-polycarbonate-cuong-luc-asia-lite' ) ),
						array( 'label' => 'Phụ kiện thi công', 'url' => topasia_footer_term_url( 'phu-kien-thi-cong' ) ),
						array( 'label' => 'Hỗ trợ kỹ thuật', 'url' => home_url( '/ho-tro-ky-thuat/' ) ),
						array( 'label' => 'Tài liệu và chứng chỉ', 'url' => home_url( '/tai-ve/' ) ),
						array( 'label' => 'Cẩm nang Polycarbonate', 'url' => home_url( '/tam-lop-lay-sang-polycarbonate/' ) ),
					),
				),
				array(
					'heading' => 'Chính sách',
					'aria'    => 'Chính sách',
					'links'   => array(
						array( 'label' => 'Chính sách bảo mật', 'url' => home_url( '/chinh-sach-bao-mat/' ) ),
						array( 'label' => 'Chính sách bảo hành', 'url' => home_url( '/chinh-sach-bao-hanh/' ) ),
						array( 'label' => 'Chính sách giao hàng', 'url' => home_url( '/chinh-sach-giao-hang/' ) ),
						array( 'label' => 'Điều khoản sử dụng', 'url' => home_url( '/dieu-khoan-su-dung/' ) ),
					),
				),
			),
			'contact' => array(
				'heading'  => 'Chia sẻ',
				'email'    => 'topasiaplastic@gmail.com',
				'web'      => 'topasia.vn',
				'web_url'  => 'https://topasia.vn/',
				'hotline'  => '0896699996',
				'tax_code' => '0109584716',
				'socials'  => array(
					array(
						'key'    => 'facebook',
						'label'  => 'Theo dõi trên Facebook',
						'url'    => 'https://www.facebook.com/topasia',
						'target' => '_blank',
					),
					array(
						'key'    => 'email',
						'label'  => 'Gửi email cho chúng tôi',
						'url'    => 'mailto:topasiaplastic@gmail.com',
						'target' => '',
					),
					array(
						'key'    => 'phone',
						'label'  => 'Gọi cho chúng tôi',
						'url'    => 'tel:0896699996',
						'target' => '',
					),
					array(
						'key'    => 'youtube',
						'label'  => 'Theo dõi trên YouTube',
						'url'    => 'https://www.youtube.com/@topasiavn',
						'target' => '_blank',
					),
				),
			),
			'lower'   => array(
				'tagline' => 'Tư vấn từ quy cách vật liệu đến giải pháp thi công.',
				'links'   => array(
					array( 'label' => 'Chủ đầu tư và nhà thầu', 'url' => home_url( '/chu-dau-tu-nha-thau/' ) ),
					array( 'label' => 'Đại lý & nhà phân phối', 'url' => home_url( '/tuyen-dai-ly/' ) ),
					array( 'label' => 'Nhận báo giá', 'url' => home_url( '/bao-gia/' ) ),
				),
			),
		);
	}
}

if ( ! function_exists( 'topasia_footer_social_svg' ) ) {
	/**
	 * Inline SVG icon for social links so buttons never depend on icon font loading.
	 */
	function topasia_footer_social_svg( $key ) {
		switch ( $key ) {
			case 'facebook':
				return '<svg width="17" height="17" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="M13.5 21v-7.6h2.55l.38-2.96H13.5V8.55c0-.86.24-1.44 1.47-1.44h1.57V4.46c-.27-.04-1.2-.12-2.29-.12-2.27 0-3.82 1.38-3.82 3.92v2.18H7.86v2.96h2.57V21h3.07z"/></svg>';
			case 'email':
				return '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>';
			case 'phone':
				return '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>';
			case 'youtube':
				return '<svg width="17" height="17" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="M21.58 7.19a2.5 2.5 0 0 0-1.76-1.77C18.25 5 12 5 12 5s-6.25 0-7.82.42A2.5 2.5 0 0 0 2.42 7.19C2 8.77 2 12 2 12s0 3.23.42 4.81a2.5 2.5 0 0 0 1.76 1.77C5.75 19 12 19 12 19s6.25 0 7.82-.42a2.5 2.5 0 0 0 1.76-1.77C22 15.23 22 12 22 12s0-3.23-.42-4.81ZM10 15V9l5.2 3-5.2 3Z"/></svg>';
			default:
				return '';
		}
	}
}

if ( ! function_exists( 'topasia_render_global_footer' ) ) {
	/**
	 * Render the global Top Asia footer markup.
	 */
	function topasia_render_global_footer() {
		$cfg     = topasia_footer_config();
		$intro   = $cfg['intro'];
		$stats   = $cfg['stats'];
		$company = $cfg['company'];
		$columns = $cfg['columns'];
		$contact = $cfg['contact'];
		$lower   = $cfg['lower'];
		?>
		<div class="ta-global-footer">
			<div class="ta-footer-intro">
				<div class="ta-footer-intro-main">
					<p class="ta-footer-kicker"><?php echo esc_html( $intro['kicker'] ); ?></p>
					<h2><?php echo esc_html( $intro['title'] ); ?></h2>
					<p class="ta-footer-intro-copy"><?php echo esc_html( $intro['copy'] ); ?></p>
				</div>
				<dl class="ta-footer-stats" aria-label="Năng lực Top Asia">
					<?php foreach ( $stats as $stat ) : ?>
						<div class="ta-footer-stat">
							<dt><?php echo esc_html( $stat['value'] ); ?></dt>
							<dd><?php echo esc_html( $stat['label'] ); ?></dd>
						</div>
					<?php endforeach; ?>
				</dl>
			</div>

			<div class="ta-footer-grid">
				<section class="ta-footer-brand" aria-label="Thông tin Top Asia">
					<h3 class="ta-footer-col-title"><?php echo esc_html( $company['heading'] ); ?></h3>
					<a class="ta-footer-logo-link" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="Top Asia — Trang chủ">
						<span class="ta-footer-logo">
							<img src="<?php echo esc_url( $company['logo'] ); ?>" alt="TOP ASIA" width="900" height="338" decoding="async">
						</span>
					</a>
					<p class="ta-footer-company-vi"><?php echo esc_html( $company['name_vi'] ); ?></p>
					<p class="ta-footer-company-en"><?php echo esc_html( $company['name_en'] ); ?></p>
					<ul class="ta-footer-places">
						<?php foreach ( $company['places'] as $place ) : ?>
							<li>
								<strong><?php echo esc_html( $place['label'] ); ?>:</strong>
								<span><?php echo esc_html( $place['value'] ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				</section>

				<?php foreach ( $columns as $col ) : ?>
					<nav class="ta-footer-column" aria-label="<?php echo esc_attr( $col['aria'] ); ?>">
						<h3 class="ta-footer-col-title"><?php echo esc_html( $col['heading'] ); ?></h3>
						<ul class="ta-footer-links">
							<?php foreach ( $col['links'] as $link ) : ?>
								<li>
									<a href="<?php echo esc_url( $link['url'] ); ?>"><?php echo esc_html( $link['label'] ); ?></a>
								</li>
							<?php endforeach; ?>
						</ul>
					</nav>
				<?php endforeach; ?>

				<section class="ta-footer-contact" aria-label="Thông tin liên hệ và chia sẻ">
					<h3 class="ta-footer-col-title"><?php echo esc_html( $contact['heading'] ); ?></h3>
					<ul class="ta-footer-meta">
						<li>
							<strong>Email:</strong>
							<a href="<?php echo esc_url( 'mailto:' . $contact['email'] ); ?>"><?php echo esc_html( $contact['email'] ); ?></a>
						</li>
						<li>
							<strong>Web:</strong>
							<a href="<?php echo esc_url( $contact['web_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $contact['web'] ); ?></a>
						</li>
						<li>
							<strong>Hotline:</strong>
							<a href="<?php echo esc_url( 'tel:' . $contact['hotline'] ); ?>"><?php echo esc_html( $contact['hotline'] ); ?></a>
						</li>
						<li>
							<strong>Mã số thuế:</strong>
							<span><?php echo esc_html( $contact['tax_code'] ); ?></span>
						</li>
					</ul>

					<div class="ta-footer-social" role="group" aria-label="Kênh kết nối Top Asia">
						<?php foreach ( $contact['socials'] as $social ) : ?>
							<?php
							$attrs = '';
							if ( ! empty( $social['target'] ) ) {
								$attrs = ' target="' . esc_attr( $social['target'] ) . '" rel="noopener noreferrer"';
							}
							?>
							<a class="ta-footer-social-btn ta-footer-social-btn--<?php echo esc_attr( $social['key'] ); ?>" href="<?php echo esc_url( $social['url'] ); ?>" aria-label="<?php echo esc_attr( $social['label'] ); ?>" title="<?php echo esc_attr( $social['label'] ); ?>"<?php echo $attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
								<?php echo topasia_footer_social_svg( $social['key'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</a>
						<?php endforeach; ?>
					</div>
				</section>
			</div>

			<div class="ta-footer-lower">
				<span class="ta-footer-tagline"><?php echo esc_html( $lower['tagline'] ); ?></span>
				<nav class="ta-footer-policies" aria-label="Liên kết nhanh B2B">
					<?php foreach ( $lower['links'] as $link ) : ?>
						<a href="<?php echo esc_url( $link['url'] ); ?>"><?php echo esc_html( $link['label'] ); ?></a>
					<?php endforeach; ?>
				</nav>
			</div>
		</div>
		<?php
	}
}
