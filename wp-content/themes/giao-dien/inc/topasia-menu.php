<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


if ( ! function_exists( 'topasia_menu_cat_url' ) ) {
	function topasia_menu_cat_url( $slug ) {
		$term = get_category_by_slug( $slug );
		if ( $term && ! is_wp_error( $term ) ) {
			$link = get_category_link( $term->term_id );
			if ( ! is_wp_error( $link ) ) {
				return $link;
			}
		}
		return '/' . ltrim( $slug, '/' ) . '/';
	}
}


if ( ! function_exists( 'topasia_menu_pcat_url' ) ) {
	function topasia_menu_pcat_url( $slug ) {
		$term = get_term_by( 'slug', $slug, 'product_cat' );
		if ( $term && ! is_wp_error( $term ) ) {
			$link = get_term_link( $term );
			if ( ! is_wp_error( $link ) ) {
				return $link;
			}
		}
		return '/danh-muc-san-pham/' . ltrim( $slug, '/' ) . '/';
	}
}


function topasia_menu_config() {
	return array(
		array( 'label' => 'Trang chủ', 'url' => '/', 'type' => 'link', 'desktop' => false ),

		array(
			'label'    => 'Giới thiệu',
			'url'      => '/gioi-thieu/',
			'type'     => 'dropdown',
			'children' => array(
				array( 'label' => 'Về Top Asia', 'url' => '/gioi-thieu/', 'desc' => 'Câu chuyện thương hiệu và định hướng', 'icon' => 'info' ),
				array( 'label' => 'Nhà máy & Năng lực sản xuất', 'url' => '/nha-may-san-xuat-tam-polycarbonate-top-asia/', 'desc' => 'Khẳng định năng lực nhà sản xuất B2B', 'icon' => 'factory' ),
			),
		),

		array(
			'label'       => 'Sản phẩm',
			'url'         => '/tat-ca-san-pham/',
			'type'        => 'mega',
			'mobile_open' => true,
			'children'    => array(
				array(
					'label'  => 'Tấm Polycarbonate đặc cường lực',
					'url'    => '/tat-ca-san-pham/tam-nhua-kinh-polycarbonate-cuong-luc/',
					'mdesc'  => 'Ba dòng sản phẩm theo phân khúc',
					'brands' => array(
						array( 'name' => 'ASIA GOLD', 'seg' => 'Phân khúc tiêu chuẩn', 'mseg' => 'Tiêu chuẩn', 'url' => topasia_menu_pcat_url( 'tam-nhua-polycarbonate-cuong-luc-asia-gold' ), 'accent' => true ),
						array( 'name' => 'ASIA PEARL', 'seg' => 'Phân khúc chuyên nghiệp', 'mseg' => 'Chuyên nghiệp', 'url' => topasia_menu_pcat_url( 'tam-nhua-polycarbonate-cuong-luc-asia-pearl' ) ),
						array( 'name' => 'ASIA LITE', 'seg' => 'Phân khúc cao cấp', 'mseg' => 'Cao cấp', 'url' => topasia_menu_pcat_url( 'tam-nhua-polycarbonate-cuong-luc-asia-lite' ) ),
					),
				),
				array( 'label' => 'Tấm Polycarbonate đặc sần', 'url' => '/tat-ca-san-pham/polycarbonate-dac-san/', 'mdesc' => 'Chống chói, tản sáng êm dịu cho công trình' ),
				array(
					'label' => 'Phụ kiện chuyên dụng',
					'url'   => '/tat-ca-san-pham/phu-kien-chuyen-dung/',
					'desc'  => 'Nẹp · Canopy · Phụ kiện thi công đồng bộ',
					'mdesc' => 'Nẹp · Canopy · Phụ kiện thi công',
					'subs'  => array(
						array( 'label' => 'Phụ kiện thi công', 'url' => topasia_menu_pcat_url( 'phu-kien-thi-cong' ), 'seg' => 'Nẹp nhôm · Gioăng EPDM · Ke chống bão · Vít', 'mseg' => 'Nẹp nhôm · Gioăng · Ke chống bão' ),
						array( 'label' => 'Phụ kiện Mái che Canopy', 'url' => topasia_menu_pcat_url( 'phu-kien-mai-che-canopy' ), 'seg' => 'Khung B60–B150 · Nẹp nhôm · Gioăng', 'mseg' => 'Khung B60–B150 · Nẹp · Gioăng' ),
						array( 'label' => 'Mái che Canopy', 'url' => topasia_menu_pcat_url( 'mai-che-canopy' ), 'seg' => 'Bộ mái che hoàn thiện' ),
					),
				),
			),
			'card'     => array(
				'title' => 'Chưa biết chọn loại tấm nào?',
				'text'  => 'Xem Cẩm nang Tấm lợp lấy sáng để tìm sản phẩm phù hợp',
				'cta'   => 'Tìm sản phẩm phù hợp',
				'url'   => '/tam-lop-lay-sang-polycarbonate/',
			),
		),

		array(
			'label'    => 'Dự án',
			'url'      => '/du-an/',
			'type'     => 'dropdown',
			'children' => array(
				array( 'label' => 'Tất cả dự án', 'url' => '/du-an/', 'desc' => 'Tổng hợp công trình đã triển khai', 'icon' => 'building' ),
				array( 'label' => 'Công trình thực tế', 'url' => topasia_menu_cat_url( 'cong-trinh-thuc-te' ), 'desc' => 'Hình ảnh thi công tại công trình', 'icon' => 'roof' ),
				array( 'label' => 'Công trình tiêu biểu', 'url' => topasia_menu_cat_url( 'cong-trinh-tieu-bieu' ), 'desc' => 'Các dự án trọng điểm', 'icon' => 'diamond' ),
			),
		),

		array(
			'label'       => 'Hỗ trợ kỹ thuật',
			'dlabel'      => 'Kỹ thuật',
			'url'         => '/ho-tro-ky-thuat/',
			'type'        => 'dropdown',
			'children'    => array(
				array( 'label' => 'Hướng dẫn thi công & khẩu độ', 'url' => '/ho-tro-ky-thuat/#quy-trinh', 'desc' => 'Thông số, lưu ý và quy trình thực hành', 'icon' => 'guide' ),
				array( 'label' => 'Tiêu chuẩn, bảo hành & xử lý sự cố', 'url' => '/ho-tro-ky-thuat/#faq-ky-thuat', 'desc' => 'Tiêu chuẩn chất lượng, bảo hành, khắc phục lỗi', 'mdesc' => 'Chất lượng, bảo hành, khắc phục lỗi', 'icon' => 'shield' ),
				array( 'label' => 'Chính sách bảo hành & đổi trả', 'url' => '/chinh-sach-bao-hanh/', 'desc' => 'Quy trình kiểm tra & tiếp nhận bảo hành', 'icon' => 'doc' ),
			),
			'support_cta' => array( 'label' => 'Gửi yêu cầu hỗ trợ', 'url' => '/lien-he/' ),
		),

		array(
			'label'    => 'Hợp tác',
			'url'      => '/chu-dau-tu-nha-thau/',
			'type'     => 'dropdown',
			'children' => array(
				array( 'label' => 'Chủ đầu tư & Nhà thầu', 'url' => '/chu-dau-tu-nha-thau/', 'desc' => 'Giải pháp cung ứng cho dự án & nhà xưởng', 'icon' => 'building' ),
				array( 'label' => 'Đại lý & Nhà phân phối', 'url' => '/tuyen-dai-ly/', 'desc' => 'Chính sách chiết khấu và hỗ trợ mẫu mã', 'icon' => 'handshake' ),
			),
		),

		array(
			'label'    => 'Tin tức',
			'url'      => '/tin-tuc/',
			'type'     => 'dropdown',
			'children' => array(
				array( 'label' => 'Tin tức / Báo giá', 'url' => topasia_menu_cat_url( 'tin-tuc-bao-gia' ), 'desc' => 'Cập nhật giá và thông tin thị trường', 'icon' => 'info' ),
				array( 'label' => 'Kiến thức kỹ thuật', 'url' => topasia_menu_cat_url( 'kien-thuc-ky-thuat' ), 'desc' => 'Thông số, thi công, tiêu chuẩn', 'icon' => 'guide' ),
				array( 'label' => 'Top Asia cập nhật', 'url' => topasia_menu_cat_url( 'top-asia-cap-nhat' ), 'desc' => 'Hoạt động và sự kiện doanh nghiệp', 'icon' => 'doc' ),
			),
		),

		array(
			'label'    => 'Tải về',
			'url'      => '/tai-ve/',
			'type'     => 'dropdown',
			'children' => array(
				array( 'label' => 'Thư viện tài liệu & Chứng chỉ', 'url' => '/tai-ve/', 'desc' => 'Catalogue, bảng màu, datasheet, hướng dẫn lắp đặt', 'icon' => 'download' ),
				array( 'label' => 'Hồ sơ năng lực Top Asia', 'url' => '/tai-ve/#ho-so-nang-luc', 'desc' => 'Năng lực nhà máy, dự án tiêu biểu & chứng nhận ISO', 'icon' => 'doc' ),
			),
		),

		array( 'label' => 'Liên hệ', 'url' => '/lien-he/', 'type' => 'link' ),

		array( 'label' => 'Báo giá', 'url' => '/bao-gia/', 'type' => 'cta' ),
	);
}


function topasia_menu_logo_url() {
	$logo_id = (int) get_theme_mod( 'site_logo' );
	if ( $logo_id ) {
		$src = wp_get_attachment_image_src( $logo_id, 'large' );
		if ( ! empty( $src[0] ) ) {
			return $src[0];
		}
	}
	if ( file_exists( ABSPATH . 'wp-content/uploads/2026/06/logo.jpg' ) ) {
		return home_url( '/wp-content/uploads/2026/06/logo.jpg' );
	}
	return function_exists( 'topasia_logo_fallback_url' ) ? topasia_logo_fallback_url() : ( get_stylesheet_directory_uri() . '/assets/img/logo-topasia.svg' );
}


function topasia_menu_url( $url ) {
	$url = (string) $url;
	if ( '' === $url ) {
		return home_url( '/' );
	}
	if ( preg_match( '#^https?://#i', $url ) ) {
		return function_exists( 'topasia_resolve_internal_url' ) ? topasia_resolve_internal_url( $url ) : $url;
	}
	return home_url( $url );
}


function topasia_menu_norm_path( $url ) {
	$raw  = topasia_menu_url( $url );
	$path = wp_parse_url( $raw, PHP_URL_PATH );
	if ( ! $path ) {
		$path = '/';
	}
	$path = '/' . trim( $path, '/' );
	if ( '/' !== $path ) {
		$path .= '/';
	}
	return $path;
}


function topasia_menu_current_path() {
	static $current = null;
	if ( null !== $current ) {
		return $current;
	}
	$path = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '/';
	$path = '/' . trim( (string) $path, '/' );
	if ( '/' !== $path ) {
		$path .= '/';
	}
	$current = $path;
	return $current;
}


function topasia_menu_is_current( $url ) {
	if ( false !== strpos( (string) $url, '#' ) ) {
		return false;
	}
	$item      = topasia_menu_norm_path( $url );
	$cur       = topasia_menu_current_path();
	$home_path = topasia_menu_norm_path( home_url( '/' ) );
	if ( $home_path === $item ) {
		return $home_path === $cur;
	}
	return ( $cur === $item ) || ( 0 === strpos( $cur, $item ) );
}


function topasia_menu_item_active( $item ) {
	if ( ! empty( $item['url'] ) && topasia_menu_is_current( $item['url'] ) ) {
		return true;
	}
	if ( ! empty( $item['children'] ) ) {
		foreach ( $item['children'] as $child ) {
			if ( ! empty( $child['url'] ) && topasia_menu_is_current( $child['url'] ) ) {
				return true;
			}
			foreach ( array( 'brands', 'subs' ) as $group ) {
				if ( empty( $child[ $group ] ) ) {
					continue;
				}
				foreach ( $child[ $group ] as $b ) {
					if ( ! empty( $b['url'] ) && topasia_menu_is_current( $b['url'] ) ) {
						return true;
					}
				}
			}
		}
	}
	return false;
}


function topasia_menu_parent_url_duplicated( $item ) {
	if ( empty( $item['url'] ) || empty( $item['children'] ) ) {
		return false;
	}
	$ppath = topasia_menu_norm_path( $item['url'] );
	foreach ( $item['children'] as $child ) {
		if ( ! empty( $child['url'] ) && topasia_menu_norm_path( $child['url'] ) === $ppath ) {
			return true;
		}
	}
	return false;
}


function topasia_menu_icon_path( $key ) {
	$icons = array(
		'chevron'   => '<path d="M4 6l4 4 4-4"/>',
		'chevright' => '<path d="M6 4l4 4-4 4"/>',
		'back'      => '<path d="M9.5 3.5L5 8l4.5 4.5"/>',
		'close'     => '<path d="M4 4l8 8M12 4l-8 8"/>',
		'search'    => '<circle cx="7" cy="7" r="4.5"/><path d="M10.5 10.5L14 14"/>',
		'arrow'     => '<path d="M3 8h9M9 5l3 3-3 3"/>',
		'info'      => '<path d="M8 2l6 11H2z"/>',
		'factory'   => '<path d="M2 13V6l4 3V6l4 3V4h4v9z"/>',
		'guide'     => '<path d="M3 4l5 2 5-2v9l-5 2-5-2z"/><path d="M8 6v9"/>',
		'shield'    => '<path d="M8 2l5 2v4c0 3-2 5-5 6-3-1-5-3-5-6V4z"/>',
		'download'  => '<path d="M8 2v8m-3-3l3 3 3-3M3 13h10"/>',
		'building'  => '<path d="M4 14V3h8v11M6 6h1M9 6h1M6 9h1M9 9h1"/>',
		'handshake' => '<path d="M2 8l3-3 3 2 3-2 3 3-3 3-3-2-3 2z"/>',
		'compass'   => '<circle cx="8" cy="8" r="6"/><path d="M8 4l2 4-2 4-2-4z"/>',
		'roof'      => '<path d="M3.5 10.5L8 5l4.5 5.5"/>',
		'diamond'   => '<path d="M8 3l4.5 5L8 13 3.5 8z"/>',
		'doc'       => '<path d="M5 4.5h6v9H5z"/><path d="M6.5 4.5V3h3v1.5"/>',
		'cart'      => '<circle cx="6" cy="13" r="1"/><circle cx="12" cy="13" r="1"/><path d="M2 3h2l1.5 7h7.5l1.5-5H5"/>',
		'user'      => '<circle cx="8" cy="5.5" r="2.5"/><path d="M3 13.5c.8-2.2 2.7-3.3 5-3.3s4.2 1.1 5 3.3"/>',
	);
	return isset( $icons[ $key ] ) ? $icons[ $key ] : '';
}


function topasia_menu_icon( $key ) {
	$p = topasia_menu_icon_path( $key );
	if ( ! $p ) {
		return '';
	}
	return '<svg class="ta-ico" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $p . '</svg>';
}


function topasia_menu_svg( $key, $class, $size, $stroke ) {
	$p = topasia_menu_icon_path( $key );
	if ( ! $p ) {
		return '';
	}
	return '<svg class="' . esc_attr( $class ) . '" width="' . (int) $size . '" height="' . (int) $size . '" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="' . esc_attr( $stroke ) . '" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $p . '</svg>';
}


function topasia_render_desktop_nav() {
	static $inst = 0;
	$inst++;
	$items = topasia_menu_config();
	$out   = '<li class="ta-nav-wrap"><nav class="ta-nav" aria-label="Điều hướng chính"><ul class="ta-nav-list">';
	$i     = 0;

	foreach ( $items as $item ) {
		$i++;
		if ( isset( $item['desktop'] ) && false === $item['desktop'] ) {
			continue;
		}
		$type   = isset( $item['type'] ) ? $item['type'] : 'link';
		$dlabel = ! empty( $item['dlabel'] ) ? $item['dlabel'] : $item['label'];
		$active = topasia_menu_item_active( $item );
		$acls   = $active ? ' is-active' : '';

		if ( 'link' === $type || 'cta' === $type ) {
			$cta   = ( 'cta' === $type ) ? ' ta-cta' : '';
			$aria  = $active ? ' aria-current="page"' : '';
			$out  .= '<li class="ta-item' . $acls . '"><a class="ta-link' . $cta . '" href="' . esc_url( topasia_menu_url( $item['url'] ) ) . '"' . $aria . ' data-ta-menu="' . esc_attr( $item['label'] ) . '"' . ( 'cta' === $type ? ' data-ta-cta="1"' : '' ) . '>' . esc_html( $dlabel ) . '</a></li>';
			continue;
		}

		$panel_id = 'ta-dd-' . $inst . '-' . $i;
		$out     .= '<li class="ta-item ta-has-dd' . ( 'mega' === $type ? ' ta-has-mega' : '' ) . $acls . '">';

		if ( ! empty( $item['url'] ) ) {
			$pcur = topasia_menu_is_current( $item['url'] ) && ! topasia_menu_parent_url_duplicated( $item );
			$out .= '<a class="ta-link ta-dd-toggle" href="' . esc_url( topasia_menu_url( $item['url'] ) ) . '" aria-haspopup="true" aria-expanded="false" aria-controls="' . $panel_id . '"' . ( $pcur ? ' aria-current="page"' : '' ) . ' data-ta-menu="' . esc_attr( $item['label'] ) . '">';
			$out .= '<span>' . esc_html( $dlabel ) . '</span>';
			$out .= topasia_menu_svg( 'chevron', 'ta-chev', 14, '1.7' );
			$out .= '</a>';
		} else {
			$out .= '<button type="button" class="ta-link ta-dd-toggle" aria-expanded="false" aria-controls="' . $panel_id . '" data-ta-menu="' . esc_attr( $item['label'] ) . '">';
			$out .= '<span>' . esc_html( $dlabel ) . '</span>';
			$out .= topasia_menu_svg( 'chevron', 'ta-chev', 14, '1.7' );
			$out .= '</button>';
		}

		if ( 'mega' === $type ) {
			$out .= topasia_render_mega_panel( $item, $panel_id );
		} else {
			$out .= topasia_render_dropdown_panel( $item, $panel_id );
		}
		$out .= '</li>';
	}

	$out .= '</ul></nav></li>';
	return $out;
}


function topasia_render_dropdown_panel( $item, $panel_id ) {
	$out  = '<div class="ta-dd-panel" id="' . $panel_id . '" role="region" aria-label="' . esc_attr( $item['label'] ) . '">';
	$out .= '<div class="ta-dd-inner"><div class="ta-dd-head"><p class="ta-dd-title">' . esc_html( $item['label'] ) . '</p>';
	if ( ! empty( $item['url'] ) && ! topasia_menu_parent_url_duplicated( $item ) ) {
		$out .= '<a class="ta-dd-all" href="' . esc_url( topasia_menu_url( $item['url'] ) ) . '" data-ta-menu="' . esc_attr( $item['label'] ) . ' - xem tat ca">Xem tất cả →</a>';
	}
	$out .= '</div><ul class="ta-dd-list">';
	foreach ( $item['children'] as $child ) {
		$cur  = topasia_menu_is_current( $child['url'] );
		$aria = $cur ? ' aria-current="page"' : '';
		$ico  = ! empty( $child['icon'] ) ? '<span class="ta-dd-ic">' . topasia_menu_icon( $child['icon'] ) . '</span>' : '';
		$desc = ! empty( $child['desc'] ) ? '<span class="ta-dd-desc">' . esc_html( $child['desc'] ) . '</span>' : '';
		$out .= '<li><a class="ta-dd-link' . ( $cur ? ' is-active' : '' ) . '" href="' . esc_url( topasia_menu_url( $child['url'] ) ) . '"' . $aria . ' data-ta-menu="' . esc_attr( $child['label'] ) . '">' . $ico . '<span class="ta-dd-txt"><span class="ta-dd-label">' . esc_html( $child['label'] ) . '</span>' . $desc . '</span></a></li>';
	}
	$out .= '</ul></div></div>';
	return $out;
}


function topasia_render_mega_panel( $item, $panel_id ) {
	$out  = '<div class="ta-dd-panel ta-mega-panel" id="' . $panel_id . '" role="region" aria-label="' . esc_attr( $item['label'] ) . '">';
	$out .= '<div class="ta-mega-inner"><div class="ta-dd-head"><p class="ta-dd-title">' . esc_html( $item['label'] ) . '</p>';
	if ( ! empty( $item['url'] ) ) {
		$out .= '<a class="ta-dd-all" href="' . esc_url( topasia_menu_url( $item['url'] ) ) . '" data-ta-menu="Tất cả sản phẩm">Tất cả sản phẩm →</a>';
	}
	$out .= '</div><div class="ta-mega-cats">';

	foreach ( $item['children'] as $child ) {
		$cur  = topasia_menu_is_current( $child['url'] );
		$out .= '<div class="ta-mega-cat">';
		$out .= '<a class="ta-mega-cat-head' . ( $cur ? ' is-active' : '' ) . '" href="' . esc_url( topasia_menu_url( $child['url'] ) ) . '"' . ( $cur ? ' aria-current="page"' : '' ) . ' data-ta-menu="' . esc_attr( $child['label'] ) . '"><span>' . esc_html( $child['label'] ) . '</span></a>';
		if ( ! empty( $child['desc'] ) ) {
			$out .= '<p class="ta-mega-cat-desc">' . esc_html( $child['desc'] ) . '</p>';
		}
		if ( ! empty( $child['subs'] ) ) {
			$out .= '<ul class="ta-subs">';
			foreach ( $child['subs'] as $s ) {
				$scur = topasia_menu_is_current( $s['url'] );
				$seg  = ! empty( $s['seg'] ) ? '<span class="ta-sub-seg">' . esc_html( $s['seg'] ) . '</span>' : '';
				$out .= '<li><a class="ta-sub' . ( $scur ? ' is-active' : '' ) . '" href="' . esc_url( topasia_menu_url( $s['url'] ) ) . '"' . ( $scur ? ' aria-current="page"' : '' ) . ' data-ta-menu="' . esc_attr( $s['label'] ) . '"><span class="ta-sub-name">' . esc_html( $s['label'] ) . '</span>' . $seg . '</a></li>';
			}
			$out .= '</ul>';
		}
		if ( ! empty( $child['brands'] ) ) {
			$out .= '<div class="ta-brands">';
			foreach ( $child['brands'] as $b ) {
				$bcur = topasia_menu_is_current( $b['url'] );
				$acc  = ! empty( $b['accent'] ) ? ' ta-brand--accent' : '';
				$out .= '<a class="ta-brand' . $acc . ( $bcur ? ' is-active' : '' ) . '" href="' . esc_url( topasia_menu_url( $b['url'] ) ) . '"' . ( $bcur ? ' aria-current="page"' : '' ) . ' data-ta-menu="' . esc_attr( $b['name'] ) . '"><span class="ta-brand-name">' . esc_html( $b['name'] ) . '</span><span class="ta-brand-seg">' . esc_html( $b['seg'] ) . '</span></a>';
			}
			$out .= '</div>';
		}
		$out .= '</div>';
	}
	$out .= '</div>';

	if ( ! empty( $item['card'] ) ) {
		$c    = $item['card'];
		$out .= '<aside class="ta-guide"><span class="ta-guide-ic">' . topasia_menu_icon( 'compass' ) . '</span><div class="ta-guide-body"><p class="ta-guide-title">' . esc_html( $c['title'] ) . '</p><p class="ta-guide-text">' . esc_html( $c['text'] ) . '</p><a class="ta-guide-cta" href="' . esc_url( topasia_menu_url( $c['url'] ) ) . '" data-ta-guide="1" data-ta-menu="' . esc_attr( $c['cta'] ) . '">' . esc_html( $c['cta'] ) . '</a></div></aside>';
	}

	$out .= '</div></div>';
	return $out;
}


function topasia_render_mobile_mega_accordion_body( $item ) {
	$out = '';

	if ( ! empty( $item['url'] ) ) {
		$pcur = topasia_menu_is_current( $item['url'] );
		$out .= '<a class="ta-msub ta-msub--all' . ( $pcur ? ' is-active' : '' ) . '" href="' . esc_url( topasia_menu_url( $item['url'] ) ) . '"' . ( $pcur ? ' aria-current="page"' : '' ) . ' data-ta-menu="Tất cả sản phẩm"><span class="ta-dd-ic">' . topasia_menu_icon( 'arrow' ) . '</span><span class="ta-dd-txt"><span class="ta-dd-label">Tất cả sản phẩm →</span></span></a>';
	}

	foreach ( $item['children'] as $child ) {
		$cur  = topasia_menu_is_current( $child['url'] );
		$desc = ! empty( $child['desc'] ) ? $child['desc'] : ( ! empty( $child['mdesc'] ) ? $child['mdesc'] : '' );
		$card = ! empty( $child['brands'] );
		$out .= '<a class="ta-mcat ' . ( $card ? 'ta-mcat--card' : 'ta-mcat--row' ) . ( $cur ? ' is-active' : '' ) . '" href="' . esc_url( topasia_menu_url( $child['url'] ) ) . '"' . ( $cur ? ' aria-current="page"' : '' ) . ' data-ta-menu="' . esc_attr( $child['label'] ) . '">';
		$out .= '<span class="ta-mcat-txt"><span class="ta-mcat-label">' . esc_html( $child['label'] ) . '</span>' . ( $desc ? '<span class="ta-mcat-desc">' . esc_html( $desc ) . '</span>' : '' ) . '</span>';
		$out .= '<span class="ta-mega-arrow" aria-hidden="true">›</span></a>';

		if ( $card ) {
			$out .= '<div class="ta-mbrands">';
			foreach ( $child['brands'] as $b ) {
				$bcur = topasia_menu_is_current( $b['url'] );
				$acc  = ! empty( $b['accent'] ) ? ' ta-brand--accent' : '';
				$seg  = ! empty( $b['seg'] ) ? $b['seg'] : ( ! empty( $b['mseg'] ) ? $b['mseg'] : '' );
				$out .= '<a class="ta-brand' . $acc . ( $bcur ? ' is-active' : '' ) . '" href="' . esc_url( topasia_menu_url( $b['url'] ) ) . '"' . ( $bcur ? ' aria-current="page"' : '' ) . ' data-ta-menu="' . esc_attr( $b['name'] ) . '"><span class="ta-brand-name">' . esc_html( $b['name'] ) . '</span><span class="ta-brand-seg">' . esc_html( $seg ) . '</span></a>';
			}
			$out .= '</div>';
		}

		if ( ! empty( $child['subs'] ) ) {
			$out .= '<ul class="ta-msubs">';
			foreach ( $child['subs'] as $s ) {
				$scur = topasia_menu_is_current( $s['url'] );
				$sseg = ! empty( $s['seg'] ) ? $s['seg'] : ( ! empty( $s['mseg'] ) ? $s['mseg'] : '' );
				$out .= '<li><a class="ta-msub-item' . ( $scur ? ' is-active' : '' ) . '" href="' . esc_url( topasia_menu_url( $s['url'] ) ) . '"' . ( $scur ? ' aria-current="page"' : '' ) . ' data-ta-menu="' . esc_attr( $s['label'] ) . '"><span class="ta-msub-name">' . esc_html( $s['label'] ) . '</span>' . ( $sseg ? '<span class="ta-msub-seg">' . esc_html( $sseg ) . '</span>' : '' ) . '</a></li>';
			}
			$out .= '</ul>';
		}
	}

	if ( ! empty( $item['card'] ) ) {
		$c    = $item['card'];
		$out .= '<aside class="ta-guide ta-guide--m"><span class="ta-guide-ic">' . topasia_menu_icon( 'compass' ) . '</span><div class="ta-guide-body"><p class="ta-guide-title">' . esc_html( $c['title'] ) . '</p><p class="ta-guide-text">' . esc_html( $c['text'] ) . '</p><a class="ta-guide-cta" href="' . esc_url( topasia_menu_url( $c['url'] ) ) . '" data-ta-guide="1" data-ta-menu="' . esc_attr( $c['cta'] ) . '">' . esc_html( $c['cta'] ) . '</a></div></aside>';
	}

	return $out;
}


function topasia_render_mobile_nav_inner() {
	static $inst = 0;
	$inst++;
	$items = topasia_menu_config();
	$list  = '';
	$i     = 0;

	foreach ( $items as $item ) {
		$i++;
		if ( isset( $item['desktop'] ) && false === $item['desktop'] ) {
			continue;
		}
		$type   = isset( $item['type'] ) ? $item['type'] : 'link';
		$dlabel = ! empty( $item['dlabel'] ) ? $item['dlabel'] : $item['label'];
		$active = topasia_menu_item_active( $item );

		if ( 'cta' === $type ) {
			continue;
		}

		if ( 'link' === $type ) {
			$aria  = topasia_menu_is_current( $item['url'] ) ? ' aria-current="page"' : '';
			$list .= '<li class="ta-mitem"><a class="ta-mlink' . ( $active ? ' is-active' : '' ) . '" href="' . esc_url( topasia_menu_url( $item['url'] ) ) . '"' . $aria . ' data-ta-menu="' . esc_attr( $item['label'] ) . '">' . esc_html( $dlabel ) . '</a></li>';
			continue;
		}

		$panel_id = 'ta-macc-' . $inst . '-' . $i;
		$def_open = ! empty( $item['mobile_open'] );
		$open     = $def_open || $active;
		$expanded = $open ? 'true' : 'false';
		$hidden   = $open ? '' : ' hidden';
		$open_cls = $open ? ' ta-open' : '';
		$def_attr = $def_open ? ' data-ta-default-open="1"' : '';

		$list .= '<li class="ta-mitem ta-macc' . ( $active ? ' is-active' : '' ) . $open_cls . '"' . $def_attr . '>';
		$list .= '<button type="button" class="ta-mtoggle" aria-expanded="' . $expanded . '" aria-controls="' . $panel_id . '" data-ta-menu="' . esc_attr( $item['label'] ) . '"><span>' . esc_html( $dlabel ) . '</span>' . topasia_menu_svg( 'chevron', 'ta-mchev', 18, '1.8' ) . '</button>';
		$list .= '<div class="ta-mpanel" id="' . $panel_id . '"' . $hidden . '>';

		if ( 'mega' === $type ) {
			$list .= topasia_render_mobile_mega_accordion_body( $item );
		} else {
			if ( ! empty( $item['url'] ) && ! topasia_menu_parent_url_duplicated( $item ) ) {
				$pcur  = topasia_menu_is_current( $item['url'] );
				$list .= '<a class="ta-msub ta-msub--all' . ( $pcur ? ' is-active' : '' ) . '" href="' . esc_url( topasia_menu_url( $item['url'] ) ) . '"' . ( $pcur ? ' aria-current="page"' : '' ) . ' data-ta-menu="' . esc_attr( $item['label'] ) . ' - xem tat ca"><span class="ta-dd-ic">' . topasia_menu_icon( 'arrow' ) . '</span><span class="ta-dd-txt"><span class="ta-dd-label">Xem tất cả →</span></span></a>';
			}

			foreach ( $item['children'] as $child ) {
				$cur   = topasia_menu_is_current( $child['url'] );
				$ico   = ! empty( $child['icon'] ) ? '<span class="ta-dd-ic">' . topasia_menu_icon( $child['icon'] ) . '</span>' : '';
				$desc  = ! empty( $child['desc'] ) ? $child['desc'] : ( ! empty( $child['mdesc'] ) ? $child['mdesc'] : '' );
				$dhtml = $desc ? '<span class="ta-dd-desc">' . esc_html( $desc ) . '</span>' : '';
				$list .= '<a class="ta-msub' . ( $cur ? ' is-active' : '' ) . '" href="' . esc_url( topasia_menu_url( $child['url'] ) ) . '"' . ( $cur ? ' aria-current="page"' : '' ) . ' data-ta-menu="' . esc_attr( $child['label'] ) . '">' . $ico . '<span class="ta-dd-txt"><span class="ta-dd-label">' . esc_html( $child['label'] ) . '</span>' . $dhtml . '</span></a>';
			}

			if ( ! empty( $item['support_cta'] ) ) {
				$sc    = $item['support_cta'];
				$list .= '<a class="ta-btn ta-btn--support" href="' . esc_url( topasia_menu_url( $sc['url'] ) ) . '" data-ta-support="1" data-ta-menu="' . esc_attr( $sc['label'] ) . '">' . esc_html( $sc['label'] ) . '</a>';
			}
		}

		$list .= '</div></li>';
	}

	$account_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : topasia_menu_url( '/tai-khoan/' );
	if ( ! $account_url ) {
		$account_url = topasia_menu_url( '/tai-khoan/' );
	}

	$out  = '<nav class="ta-mnav" aria-label="Điều hướng chính">';
	$out .= '<div class="ta-mhead">';
	$out .= '<a class="ta-mlogo" href="' . esc_url( home_url( '/' ) ) . '" data-ta-menu="Logo" aria-label="Top Asia — Trang chủ"><img src="' . esc_url( topasia_menu_logo_url() ) . '" alt="Top Asia" width="160" height="60" loading="eager"></a>';
	$out .= '<button type="button" class="ta-mclose" data-ta-close aria-label="Đóng menu">' . topasia_menu_svg( 'close', 'ta-mclose-ic', 16, '1.9' ) . '</button>';
	$out .= '</div>';

	$out .= '<form role="search" method="get" class="ta-msearch" action="' . esc_url( home_url( '/' ) ) . '">';
	$out .= '<label class="screen-reader-text" for="ta-msearch-input">Tìm kiếm sản phẩm, bài viết</label>';
	$out .= '<input id="ta-msearch-input" type="search" name="s" value="' . esc_attr( get_search_query() ) . '" placeholder="Tìm sản phẩm, mã hàng, tài liệu...">';
	$out .= '<button type="submit" aria-label="Tìm kiếm">' . topasia_menu_svg( 'search', 'ta-msearch-ic', 16, '1.9' ) . '</button>';
	$out .= '</form>';

	$out .= '<ul class="ta-mnav-list">' . $list . '</ul>';

	$out .= '<div class="ta-mfoot">';
	$out .= '<div class="ta-mutils">';
	$out .= '<a class="ta-mutil" href="' . esc_url( $account_url ) . '">' . topasia_menu_icon( 'user' ) . '<span>Tài khoản</span></a>';
	$out .= '</div>';
	$out .= topasia_mobile_quote_btn();
	$out .= '<p class="ta-mhotline">Hotline tư vấn: <a href="tel:0896699996">0896 699 996</a></p>';
	$out .= '</div>';
	$out .= '</nav>';
	return $out;
}


function topasia_render_mobile_nav() {
	return '<li class="ta-mnav-wrap">' . topasia_render_mobile_nav_inner() . '</li>';
}


function topasia_mobile_quote_btn() {
	return '<a class="ta-btn ta-btn--quote" href="' . esc_url( topasia_menu_url( '/bao-gia/' ) ) . '" data-ta-cta="1" data-ta-menu="Báo giá">Báo giá</a>';
}


function topasia_menu_inject( $output, $args ) {
	$loc = isset( $args->theme_location ) ? $args->theme_location : '';
	if ( 'primary' === $loc ) {
		return topasia_render_desktop_nav();
	}
	if ( 'primary_mobile' === $loc ) {
		return topasia_render_mobile_nav();
	}
	return $output;
}
add_filter( 'pre_wp_nav_menu', 'topasia_menu_inject', 999, 2 );
remove_filter( 'pre_wp_nav_menu', 'topasia_refresh_menu_inject', 20 );


function topasia_menu_assets() {
	if ( is_admin() || isset( $_GET['app'] ) || isset( $_GET['uxb_iframe'] ) ) {
		return;
	}
	$dir = get_stylesheet_directory();
	$uri = get_stylesheet_directory_uri();
	$css = $dir . '/assets/topasia-menu-r5.css';
	$js  = $dir . '/assets/topasia-menu-r2.js';
	wp_enqueue_style( 'topasia-menu', $uri . '/assets/topasia-menu-r5.css', array( 'flatsome-style', 'ta-ds' ), file_exists( $css ) ? filemtime( $css ) : '1' );
	wp_enqueue_script( 'topasia-menu', $uri . '/assets/topasia-menu-r2.js', array(), file_exists( $js ) ? filemtime( $js ) : '1', true );
}
add_action( 'wp_enqueue_scripts', 'topasia_menu_assets', 110 );


function topasia_header_main_left_elements( $value ) {
	if ( is_admin() ) {
		return $value;
	}
	return array( 'nav' );
}
add_filter( 'theme_mod_header_elements_left', 'topasia_header_main_left_elements' );


function topasia_header_main_right_elements( $value ) {
	if ( is_admin() ) {
		return $value;
	}
	return array( 'search', 'account' );
}
add_filter( 'theme_mod_header_elements_right', 'topasia_header_main_right_elements' );


function topasia_header_mobile_left_elements( $value ) {
	if ( is_admin() ) {
		return $value;
	}
	return array( 'ta-cmm-menu' );
}
add_filter( 'theme_mod_header_mobile_elements_left', 'topasia_header_mobile_left_elements' );


function topasia_header_mobile_right_elements( $value ) {
	if ( is_admin() ) {
		return $value;
	}
	return array();
}
add_filter( 'theme_mod_header_mobile_elements_right', 'topasia_header_mobile_right_elements' );


function topasia_render_mobile_trigger_element( $element ) {
	if ( 'ta-cmm-menu' !== $element || is_admin() || isset( $_GET['app'] ) || isset( $_GET['uxb_iframe'] ) ) {
		return;
	}
	echo '<li class="ta-cmm-li"><button type="button" id="ta-cmm-open" class="ta-cmm-trigger" aria-label="Mở menu điều hướng" aria-expanded="false" aria-controls="ta-cmm-drawer"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" aria-hidden="true"><path d="M3 6h18M3 12h18M3 18h18"/></svg></button></li>';
}
add_action( 'flatsome_header_elements', 'topasia_render_mobile_trigger_element' );


function topasia_render_custom_mobile_drawer() {
	if ( is_admin() || isset( $_GET['app'] ) || isset( $_GET['uxb_iframe'] ) ) {
		return;
	}
	echo '<div id="ta-cmm-backdrop" class="ta-cmm-backdrop" hidden></div>';
	echo '<aside id="ta-cmm-drawer" class="ta-cmm-drawer" aria-label="Menu di động Top Asia" aria-hidden="true" hidden>';
	echo topasia_render_mobile_nav_inner();
	echo '</aside>';
}
add_action( 'wp_footer', 'topasia_render_custom_mobile_drawer', 20 );


function topasia_mobile_sidebar_elements( $value ) {
	if ( is_admin() ) {
		return $value;
	}
	return array( 'nav' );
}
add_filter( 'theme_mod_mobile_sidebar', 'topasia_mobile_sidebar_elements' );
