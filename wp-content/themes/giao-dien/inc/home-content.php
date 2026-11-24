<?php
/**
 * Top Asia — Nội dung trang chủ sửa được từ wp-admin (30/07/2026).
 *
 * Mục tiêu: nhân viên đổi chữ + thay ảnh trang chủ mà không cần đụng code.
 *
 * Mô hình HAI TẦNG:
 *  - Tầng 1 (file này): các section "cứng" — Hero, "Vì sao khác biệt", "Cam kết"
 *    — đưa ra Tuỳ biến (Customizer) dạng field cố định. Nhân viên đổi được chữ/ảnh
 *    nhưng KHÔNG thêm/bớt/đổi thứ tự được => không phá vỡ layout.
 *  - Tầng 2 (xem inc/home-blocks.php): các section "mềm" — Về chúng tôi, Ứng dụng,
 *    Khách hàng nói gì — render bằng Flatsome UX Block, sửa bằng UX Builder.
 *
 * NGUYÊN TẮC: mọi giá trị mặc định nằm DUY NHẤT ở taf_home_defaults(). front-page.php
 * đọc qua taf_opt() nên khi chưa ai sửa gì, trang chủ render y hệt bản hard-code cũ.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ---------------------------------------------------------------------------
 * 1) GIÁ TRỊ MẶC ĐỊNH — nguồn sự thật duy nhất
 * ---------------------------------------------------------------------------
 * Key đặt theo dạng <section><số>_<trường>. Ảnh lưu URL đầy đủ.
 */
function taf_home_defaults() {
	static $d = null;
	if ( null !== $d ) {
		return $d;
	}
	$up = home_url( '/wp-content/uploads/' );
	$app1_local = file_exists( WP_CONTENT_DIR . '/uploads/2026/11/app-mai-lay-sang-opt.jpg' )
		? $up . '2026/11/app-mai-lay-sang-opt.jpg'
		: $up . '2021/05/1515556901_thi-cong-tam-lop-lay-sang.jpg';
	$app4_local = file_exists( WP_CONTENT_DIR . '/uploads/2026/11/app-vach-ngan.jpg' )
		? $up . '2026/11/app-vach-ngan.jpg'
		: $up . '2022/06/Vach-ngan-scaled.jpg';
	$app5_local = file_exists( WP_CONTENT_DIR . '/uploads/2026/11/app-nha-xuong-opt.jpg' )
		? $up . '2026/11/app-nha-xuong-opt.jpg'
		: $up . '2024/05/IMG_0512-1-scaled.jpg';
	$app7_local = file_exists( WP_CONTENT_DIR . '/uploads/2026/11/app-thuong-mai.jpg' )
		? $up . '2026/11/app-thuong-mai.jpg'
		: $up . '2024/05/DJI_0017-3-scaled.jpg';

	$d = array(
		// ----- HERO: 3 slide -----
		'hero1_img'       => $up . '2026/06/banner-san-pham-1.jpg',
		'hero1_badge'     => 'Nhà sản xuất & phân phối chính hãng',
		'hero1_h1'        => 'Tấm nhựa Polycarbonate cường lực <span style="color:var(--red-600)">chính hãng</span>',
		'hero1_sub'       => 'Mái lấy sáng chống UV chuẩn ISO, sản xuất trên dây chuyền tiêu chuẩn châu Âu. Báo giá trực tiếp từ nhà máy — bền hơn kính gấp 200 lần.',
		'hero1_cta1'      => 'Nhận báo giá nhà máy',
		'hero1_cta2'      => 'Xem sản phẩm',
		'hero1_feat1'     => 'Phủ Nano chống tia UV',
		'hero1_feat2'     => 'Chuẩn ISO 9001:2015',
		'hero1_feat3'     => 'Bảo hành 10 năm',
		'hero1_stat1_num' => '10.000',
		'hero1_stat1_lbl' => 'Tấn / năm',
		'hero1_stat2_num' => '34+',
		'hero1_stat2_lbl' => 'Tỉnh thành',

		'hero2_img'       => $up . '2026/07/hero-banner-2.jpg',
		'hero2_badge'     => 'Năng lực sản xuất',
		'hero2_h1'        => 'Duy nhất sản xuất độ dày <span style="color:var(--red-600)">18mm</span> — chuẩn xuất khẩu Châu Âu',
		'hero2_sub'       => 'Dây chuyền hiện đại, độ dày đa dạng đến 18mm, khổ tấm chuẩn chỉ theo hợp đồng — đáp ứng cả những công trình quy mô lớn, khắt khe nhất.',
		'hero2_cta1'      => 'Xem thông số',
		'hero2_cta2'      => 'Nhận báo giá',

		'hero3_img'       => $up . '2026/07/hero-banner-3.jpg',
		'hero3_badge'     => 'Cam kết chất lượng',
		'hero3_h1'        => 'Chứng nhận <span style="color:var(--red-600)">ISO 9001:2015</span> · Bảo hành 10 năm',
		'hero3_sub'       => 'Sản phẩm kiểm định theo ISO 9001:2015 & TCVN. Chính sách bảo hành rõ ràng bằng văn bản, hậu mãi tận tâm trên từng công trình.',
		'hero3_cta1'      => 'Nhận báo giá',

		// ----- VỀ CHÚNG TÔI -----
		'about_eyebrow'   => 'Về chúng tôi',
		'about_h2'        => 'Xây dựng thương hiệu Việt, chuẩn quốc tế',
		'about_lead'      => 'Sứ mệnh của Top Asia là tạo ra những sản phẩm chất lượng cao phục vụ người Việt và vươn ra thị trường quốc tế với niềm tự hào dân tộc — “giá trị thực của sản phẩm Việt”.',
		'about_b1'        => 'Nhà máy: KCN Hoàng Gia, Long An — dây chuyền chuẩn châu Âu',
		'about_b2'        => 'Phủ Nano chống tia UV, độ dày đa dạng',
		'about_b3'        => 'Năng suất 10.000 tấn/năm',
		'about_b4'        => 'Đội ngũ trẻ, năng động, liên tục đổi mới công nghệ',
		'about_img'       => $up . '2024/06/DJI_0808-scaled.jpg',
		'about_badge'     => 'Từ 2014',

		// ----- ỨNG DỤNG: 7 ô ảnh -----
		'apps_eyebrow'    => 'Ứng dụng',
		'apps_h2'         => 'Giải pháp lấy sáng cho mọi công trình',
		'app1_label'      => 'Mái lấy sáng',
		'app1_img'        => $app1_local,
		'app2_label'      => 'Giếng trời',
		'app2_img'        => $up . '2022/06/gieng-troi.jpg',
		'app3_label'      => 'Mái che Canopy',
		'app3_img'        => $up . '2022/06/canopy.jpg',
		'app4_label'      => 'Vách ngăn hiện đại',
		'app4_img'        => $app4_local,
		'app5_label'      => 'Nhà xưởng',
		'app5_img'        => $app5_local,
		'app6_label'      => 'Mái vòm uốn cong',
		'app6_img'        => $up . '2022/06/mai-vom.jpg',
		'app7_label'      => 'Công trình thương mại',
		'app7_img'        => $app7_local,

		// ----- VÌ SAO KHÁC BIỆT: 4 thẻ -----
		'why_eyebrow'     => 'Sản phẩm của chúng tôi',
		'why_h2'          => 'Vì sao tấm Polycarbonate Top Asia khác biệt',
		'why1_title'      => 'Bền hơn kính 200 lần',
		'why1_desc'       => 'Chịu va đập, chịu lực vượt trội, không lo dập vỡ khi thi công.',
		'why2_title'      => 'Trong suốt đến 98%',
		'why2_desc'       => 'Truyền sáng trung thực, không đục, không ám xanh.',
		'why3_title'      => 'Màu sắc & kích thước đa dạng',
		'why3_desc'       => 'Nhiều độ dày, khổ tấm chuẩn chỉ theo hợp đồng.',
		'why4_title'      => 'Thi công dễ dàng',
		'why4_desc'       => 'Uốn cong linh hoạt, bắn vít thoải mái, lắp đặt nhanh.',

		// ----- CAM KẾT: 6 mục -----
		'commit_eyebrow'  => 'Cam kết chất lượng',
		'commit_h2'       => 'Tiêu chuẩn quốc tế cho từng tấm Polycarbonate',
		'commit1_title'   => 'Bảo hành 10 năm',
		'commit1_desc'    => 'Cam kết đồng hành cùng mọi công trình',
		'commit2_title'   => 'ISO 9001:2015',
		'commit2_desc'    => 'Kiểm định chất lượng theo ISO 9001:2015 & TCVN',
		'commit3_title'   => 'Hậu mãi tận tâm',
		'commit3_desc'    => 'Chính sách hậu mãi tốt, phục vụ nhiệt tình',
		'commit4_title'   => 'Đồng hành nhà phân phối',
		'commit4_desc'    => 'Sát cánh cùng NPP, đưa sản phẩm đến tận tay người dùng',
		'commit5_title'   => 'Xuất khẩu 5 quốc gia',
		'commit5_desc'    => 'Khẳng định vị thế trên bản đồ thế giới',
		'commit6_title'   => 'Bộ Xây Dựng',
		'commit6_desc'    => 'Đáp ứng đầy đủ tiêu chuẩn kỹ thuật Việt Nam',

		// ----- TIN TỨC -----
		'news_eyebrow'    => 'Tin tức',
		'news_h2'         => 'Kiến thức &amp; tin tức nổi bật',
	);
	return $d;
}

/**
 * Đọc một giá trị nội dung trang chủ (Customizer -> fallback mặc định).
 * Trả về CHUỖI THÔ, nơi gọi tự escape cho đúng ngữ cảnh.
 */
function taf_opt( $key ) {
	$d   = taf_home_defaults();
	$def = isset( $d[ $key ] ) ? $d[ $key ] : '';
	$v   = get_theme_mod( 'taf_' . $key, $def );
	// Ô để trống trong Customizer => quay về mặc định, tránh trang chủ bị khuyết mảng.
	return ( '' === $v || null === $v ) ? $def : $v;
}

/**
 * ---------------------------------------------------------------------------
 * 2) ĐĂNG KÝ PANEL TRONG TUỲ BIẾN
 * ---------------------------------------------------------------------------
 */
add_action( 'customize_register', 'taf_home_customize_register' );
function taf_home_customize_register( $wp_customize ) {
	$d = taf_home_defaults();

	$wp_customize->add_panel(
		'taf_home',
		array(
			'title'       => 'Nội dung Trang chủ',
			'priority'    => 25,
			'description' => 'Sửa chữ và thay ảnh trên trang chủ. Xem trước ngay bên phải, bấm “Đăng” mới áp dụng thật. Bỏ trống một ô = quay lại nội dung mặc định.',
		)
	);

	/** Thêm 1 ô nhập chữ. */
	$text = function ( $section, $key, $label, $type = 'text', $desc = '' ) use ( $wp_customize, $d ) {
		$wp_customize->add_setting(
			'taf_' . $key,
			array(
				'default'           => isset( $d[ $key ] ) ? $d[ $key ] : '',
				'sanitize_callback' => 'taf_sanitize_rich',
				'transport'         => 'refresh',
			)
		);
		$wp_customize->add_control(
			'taf_' . $key,
			array(
				'label'       => $label,
				'section'     => $section,
				'type'        => $type,
				'description' => $desc,
			)
		);
	};

	/** Thêm 1 ô chọn ảnh (mở Thư viện Media). */
	$image = function ( $section, $key, $label, $desc = '' ) use ( $wp_customize, $d ) {
		$wp_customize->add_setting(
			'taf_' . $key,
			array(
				'default'           => isset( $d[ $key ] ) ? $d[ $key ] : '',
				'sanitize_callback' => 'esc_url_raw',
				'transport'         => 'refresh',
			)
		);
		$wp_customize->add_control(
			new WP_Customize_Image_Control(
				$wp_customize,
				'taf_' . $key,
				array(
					'label'       => $label,
					'section'     => $section,
					'description' => $desc,
				)
			)
		);
	};

	// ---------------- HERO ----------------
	foreach ( array( 1, 2, 3 ) as $i ) {
		$sec = 'taf_hero' . $i;
		$wp_customize->add_section(
			$sec,
			array(
				'title'       => 'Hero — Slide ' . $i,
				'panel'       => 'taf_home',
				'priority'    => 10 + $i,
				'description' => 1 === $i ? 'Ảnh nền nên là ảnh ngang, tối thiểu 1920×900px.' : '',
			)
		);
		$image( $sec, 'hero' . $i . '_img', 'Ảnh nền slide' );
		$text( $sec, 'hero' . $i . '_badge', 'Nhãn nhỏ phía trên' );
		$text( $sec, 'hero' . $i . '_h1', 'Tiêu đề lớn', 'textarea', 'Muốn tô đỏ một cụm từ: bọc trong <span style="color:var(--red-600)">…</span>' );
		$text( $sec, 'hero' . $i . '_sub', 'Đoạn mô tả', 'textarea' );
		$text( $sec, 'hero' . $i . '_cta1', 'Chữ trên nút chính' );
		if ( 3 !== $i ) {
			$text( $sec, 'hero' . $i . '_cta2', 'Chữ trên nút phụ' );
		}
		if ( 1 === $i ) {
			$text( $sec, 'hero1_feat1', 'Gạch đầu dòng 1' );
			$text( $sec, 'hero1_feat2', 'Gạch đầu dòng 2' );
			$text( $sec, 'hero1_feat3', 'Gạch đầu dòng 3' );
			$text( $sec, 'hero1_stat1_num', 'Số liệu 1 — con số' );
			$text( $sec, 'hero1_stat1_lbl', 'Số liệu 1 — nhãn' );
			$text( $sec, 'hero1_stat2_num', 'Số liệu 2 — con số' );
			$text( $sec, 'hero1_stat2_lbl', 'Số liệu 2 — nhãn' );
		}
	}

	// ---------------- VỀ CHÚNG TÔI ----------------
	$wp_customize->add_section(
		'taf_about',
		array(
			'title'       => 'Về chúng tôi',
			'panel'       => 'taf_home',
			'priority'    => 15,
			'description' => 'Ảnh nhà máy nên là ảnh ngang, tối thiểu 900×700px.',
		)
	);
	$text( 'taf_about', 'about_eyebrow', 'Nhãn nhỏ' );
	$text( 'taf_about', 'about_h2', 'Tiêu đề section' );
	$text( 'taf_about', 'about_lead', 'Đoạn mở đầu', 'textarea' );
	foreach ( array( 1, 2, 3, 4 ) as $i ) {
		$text( 'taf_about', 'about_b' . $i, 'Gạch đầu dòng ' . $i );
	}
	$image( 'taf_about', 'about_img', 'Ảnh nhà máy' );
	$text( 'taf_about', 'about_badge', 'Nhãn đỏ góc ảnh' );

	// ---------------- ỨNG DỤNG ----------------
	$wp_customize->add_section(
		'taf_apps',
		array(
			'title'       => 'Ứng dụng (6 ô ảnh)',
			'panel'       => 'taf_home',
			'priority'    => 25,
			'description' => 'Ảnh nên cắt ngang, tỉ lệ khoảng 4:3, tối thiểu 800×600px.',
		)
	);
	$text( 'taf_apps', 'apps_eyebrow', 'Nhãn nhỏ' );
	$text( 'taf_apps', 'apps_h2', 'Tiêu đề section' );
	foreach ( array( 1, 2, 3, 4, 5, 6, 7 ) as $i ) {
		$image( 'taf_apps', 'app' . $i . '_img', 'Ô ' . $i . ' — ảnh' );
		$text( 'taf_apps', 'app' . $i . '_label', 'Ô ' . $i . ' — tên' );
	}

	// ---------------- VÌ SAO KHÁC BIỆT ----------------
	$wp_customize->add_section(
		'taf_why',
		array(
			'title'       => 'Vì sao khác biệt (4 thẻ)',
			'panel'       => 'taf_home',
			'priority'    => 20,
			'description' => 'Biểu tượng của từng thẻ do thiết kế quy định, không đổi từ đây.',
		)
	);
	$text( 'taf_why', 'why_eyebrow', 'Nhãn nhỏ' );
	$text( 'taf_why', 'why_h2', 'Tiêu đề section' );
	foreach ( array( 1, 2, 3, 4 ) as $i ) {
		$text( 'taf_why', 'why' . $i . '_title', 'Thẻ ' . $i . ' — tiêu đề' );
		$text( 'taf_why', 'why' . $i . '_desc', 'Thẻ ' . $i . ' — mô tả', 'textarea' );
	}

	// ---------------- CAM KẾT ----------------
	$wp_customize->add_section(
		'taf_commit',
		array(
			'title'    => 'Cam kết chất lượng (6 mục)',
			'panel'    => 'taf_home',
			'priority' => 30,
		)
	);
	$text( 'taf_commit', 'commit_eyebrow', 'Nhãn nhỏ' );
	$text( 'taf_commit', 'commit_h2', 'Tiêu đề section' );
	foreach ( array( 1, 2, 3, 4, 5, 6 ) as $i ) {
		$text( 'taf_commit', 'commit' . $i . '_title', 'Mục ' . $i . ' — tiêu đề' );
		$text( 'taf_commit', 'commit' . $i . '_desc', 'Mục ' . $i . ' — mô tả', 'textarea' );
	}

	// ---------------- TIN TỨC ----------------
	$wp_customize->add_section(
		'taf_news',
		array(
			'title'       => 'Tin tức',
			'panel'       => 'taf_home',
			'priority'    => 40,
			'description' => 'Danh sách bài viết tự lấy 4 bài mới nhất, không cần chỉnh.',
		)
	);
	$text( 'taf_news', 'news_eyebrow', 'Nhãn nhỏ' );
	$text( 'taf_news', 'news_h2', 'Tiêu đề section' );
}

/**
 * Lọc nội dung nhập từ Customizer: cho phép thẻ inline cơ bản (span/b/i/br/em/strong)
 * để giữ được cụm chữ tô đỏ trong tiêu đề, chặn script và thẻ khối.
 */
function taf_sanitize_rich( $value ) {
	$allowed = array(
		'span'   => array( 'style' => true, 'class' => true ),
		'b'      => array(),
		'strong' => array(),
		'i'      => array(),
		'em'     => array(),
		'br'     => array(),
	);
	return wp_kses( (string) $value, $allowed );
}

/**
 * ---------------------------------------------------------------------------
 * 3) UX BLOCK — section "mềm" sửa bằng UX Builder
 * ---------------------------------------------------------------------------
 * In ra nội dung một Flatsome UX Block nếu block đó tồn tại + đã đăng + có nội
 * dung. Trả về false khi không có, để front-page.php rơi về bản dựng sẵn.
 * Nhờ vậy xoá/gỡ đăng block là trang chủ tự quay lại giao diện gốc.
 */
function taf_block( $slug ) {
	if ( ! post_type_exists( 'blocks' ) ) {
		return false;
	}
	$found = get_posts(
		array(
			'post_type'      => 'blocks',
			'name'           => $slug,
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'no_found_rows'  => true,
		)
	);
	if ( empty( $found ) || '' === trim( $found[0]->post_content ) ) {
		return false;
	}
	$html = do_shortcode( '[block id="' . $slug . '"]' );
	if ( '' === trim( $html ) ) {
		return false;
	}
	echo '<div class="taf-blk taf-blk--' . esc_attr( $slug ) . '">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	return true;
}

/**
 * Khoác lại giao diện cho UX Block "Khách hàng nói gì".
 * Flatsome dựng testimonial theo style mặc định của nó; đoạn CSS này kéo về
 * đúng ngôn ngữ thiết kế của trang chủ (thẻ nền xám, gạch đỏ, chữ Poppins VN)
 * và BỎ 5 sao mà Flatsome tự chèn — thiết kế gốc không có, và đó là đánh giá
 * không có thật nên không được để hiển thị.
 */
add_action( 'wp_enqueue_scripts', 'taf_block_styles', 30 );
function taf_block_styles() {
	if ( ! is_front_page() ) {
		return;
	}
	$b   = '.taf-blk--trang-chu-danh-gia';
	$css = "
{$b}{max-width:1100px;margin:0 auto;padding:72px 24px}
{$b} .taf-eyebrow{margin-bottom:10px}
{$b} .taf-h2{margin:0 0 34px}
/* Flatsome tự chèn 5 sao — thiết kế gốc không có và đó là đánh giá không có thật. */
{$b} .star-rating{display:none !important}
/* Mỗi đánh giá là một thẻ nền xám có gạch đỏ, xếp lưới 2x2. KHÔNG dùng slider:
   Flickity của Flatsome hiện không khởi tạo trên site này nên carousel sẽ chỉ
   hiện được 1 mục. Lưới hiện đủ 4 và không phụ thuộc JS. */
{$b} .testimonial-box{position:relative;height:100%;background:var(--gray-50);border:1px solid var(--border-1);padding:30px 28px 26px;margin:0}
{$b} .testimonial-box::before{content:'';position:absolute;left:28px;top:0;width:48px;height:4px;background:var(--red-600)}
{$b} .testimonial-image.circle{margin:0 14px 0 0}
{$b} .testimonial-image.circle img{display:block;aspect-ratio:1;height:auto;object-fit:cover}
/* Flatsome gắn class .is-italic kèm !important — thiết kế gốc dùng chữ đứng. */
{$b} .testimonial-text{font:500 15.5px/1.65 'Poppins VN',Arial,sans-serif;color:var(--navy-900);font-style:normal !important}
{$b} .testimonial-name{font:700 14.5px/1.35 'Poppins VN',Arial,sans-serif;color:var(--navy-900)}
{$b} .testimonial-company{font:400 12.5px/1.35 'Poppins VN',Arial,sans-serif;color:var(--gray-500)}
{$b} .testimonial-name-divider{color:var(--gray-300)}
@media (max-width:849px){
{$b}{padding:52px 16px}
{$b} .testimonial-box{padding:26px 20px 22px}
{$b} .testimonial-box::before{left:20px}
}
";
	wp_add_inline_style( 'taf-home', $css );
}

/**
 * ---------------------------------------------------------------------------
 * 3b) NHẬP ẢNH CŨ VÀO THƯ VIỆN — chạy một lần
 * ---------------------------------------------------------------------------
 * 4 ảnh avatar khách hàng được đưa lên bằng File Manager nên WordPress không
 * biết tới; UX Builder chỉ tham chiếu ảnh theo ID attachment. Hàm này đăng ký
 * CHÍNH file đang có (không upload lại => không sinh file trùng), tự tắt sau
 * lần chạy đầu. Xoá được sau khi đã chạy xong.
 */
add_action( 'admin_init', 'taf_import_legacy_uploads' );
function taf_import_legacy_uploads() {
	if ( get_option( 'taf_legacy_uploads_v1' ) || ! current_user_can( 'upload_files' ) ) {
		return;
	}
	$files = array(
		'2026/07/kh-phan-van-van.png' => 'Khách hàng — Phan Văn Vân',
		'2026/07/kh-anh-than.png'     => 'Khách hàng — Anh Thản',
		'2026/07/kh-dam-van-dung.png' => 'Khách hàng — Đàm Văn Dũng',
		'2026/07/kh-le-long-anh.png'  => 'Khách hàng — Lê Long Anh',
	);
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$done = array();
	foreach ( $files as $rel => $title ) {
		$abs = WP_CONTENT_DIR . '/uploads/' . $rel;
		if ( ! file_exists( $abs ) ) {
			continue;
		}
		// Đã có attachment trỏ vào đúng file này thì dùng lại, không tạo thêm.
		$existing = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					array(
						'key'   => '_wp_attached_file',
						'value' => $rel,
					),
				),
			)
		);
		if ( $existing ) {
			$done[ $rel ] = (int) $existing[0];
			continue;
		}
		$type = wp_check_filetype( $abs );
		$id   = wp_insert_attachment(
			array(
				'post_mime_type' => $type['type'],
				'post_title'     => $title,
				'post_status'    => 'inherit',
			),
			$abs
		);
		if ( is_wp_error( $id ) || ! $id ) {
			continue;
		}
		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $abs ) );
		$done[ $rel ] = (int) $id;
	}
	update_option( 'taf_legacy_uploads_v1', $done, false );
}

/**
 * ---------------------------------------------------------------------------
 * 4) CẢNH BÁO Ở TRANG 97 — tránh nhân viên sửa UX Builder rồi tưởng hỏng
 * ---------------------------------------------------------------------------
 * front-page.php của child theme đè lên nội dung page trang chủ, nên mọi thay đổi
 * trong Editor / UX Builder của trang này đều KHÔNG hiện ra ngoài site.
 */
add_action( 'admin_notices', 'taf_front_page_notice' );
function taf_front_page_notice() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'page' !== $screen->id ) {
		return;
	}
	$post_id  = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0; // phpcs:ignore WordPress.Security.NonceVerification
	$front_id = (int) get_option( 'page_on_front' );
	if ( ! $post_id || ! $front_id || $post_id !== $front_id ) {
		return;
	}
	$url = admin_url( 'customize.php?autofocus%5Bpanel%5D=taf_home' );
	echo '<div class="notice notice-warning"><p><strong>Trang chủ không sửa ở đây.</strong> '
		. 'Giao diện trang chủ do file <code>front-page.php</code> của theme dựng, nên nội dung bạn sửa trong Editor hoặc UX Builder của trang này <strong>sẽ không hiện ra ngoài site</strong>.</p>'
		. '<p>Để đổi chữ và ảnh trang chủ: <a class="button button-primary" href="' . esc_url( $url ) . '">Mở Tuỳ biến → Nội dung Trang chủ</a> '
		. '&nbsp; Các khối “Về chúng tôi”, “Ứng dụng”, “Khách hàng nói gì” sửa tại <a href="' . esc_url( admin_url( 'edit.php?post_type=blocks' ) ) . '">UX Blocks</a>.</p></div>';
}
