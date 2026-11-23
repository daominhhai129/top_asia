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

function topasia_menu_config() {
	return array(
		array( 'label' => 'Trang chủ', 'url' => '/', 'type' => 'link', 'desktop' => false ),

		array(
			'label'    => 'Giới thiệu',
			'type'     => 'dropdown',
			'children' => array(
				array( 'label' => 'Về Top Asia', 'url' => '/gioi-thieu/', 'desc' => 'Câu chuyện thương hiệu và định hướng', 'icon' => 'info' ),
				array( 'label' => 'Nhà máy & Năng lực sản xuất', 'url' => '/nha-may-san-xuat-tam-polycarbonate-top-asia/', 'desc' => 'Khẳng định năng lực nhà sản xuất B2B', 'icon' => 'factory' ),
			),
		),

		array(
			'label'    => 'Sản phẩm',
			'type'     => 'mega',
			'mobile'   => 'view',
			'children' => array(
				array(
					'label'  => 'Tấm Polycarbonate đặc cường lực',
					'url'    => '/tat-ca-san-pham/tam-nhua-kinh-polycarbonate-cuong-luc/',
					'mdesc'  => 'Ba dòng sản phẩm theo phân khúc',
					'brands' => array(
						array( 'name' => 'ASIA GOLD', 'seg' => 'Phân khúc tiêu chuẩn', 'mseg' => 'Tiêu chuẩn', 'url' => '/danh-muc-san-pham/tam-nhua-polycarbonate-cuong-luc-asia-gold/', 'accent' => true ),
						array( 'name' => 'ASIA PEARL', 'seg' => 'Phân khúc chuyên nghiệp', 'mseg' => 'Chuyên nghiệp', 'url' => '/danh-muc-san-pham/tam-nhua-polycarbonate-cuong-luc-asia-pearl/' ),
						array( 'name' => 'ASIA LITE', 'seg' => 'Phân khúc cao cấp', 'mseg' => 'Cao cấp', 'url' => '/danh-muc-san-pham/tam-nhua-polycarbonate-cuong-luc-asia-lite/' ),
					),
				),
				array( 'label' => 'Tấm Polycarbonate đặc sần', 'url' => '/tat-ca-san-pham/polycarbonate-dac-san/' ),
				array(
					'label' => 'Phụ kiện chuyên dụng',
					'url'   => '/tat-ca-san-pham/phu-kien-chuyen-dung/',
					'desc'  => 'Nẹp · Canopy · Phụ kiện thi công đồng bộ',
					'mdesc' => 'Nẹp · Canopy · Phụ kiện thi công',
					// Cấp 3 — bám đúng product_cat đang có trên site (130 / 131 / 132).
					'subs'  => array(
						array( 'label' => 'Phụ kiện thi công', 'url' => '/danh-muc-san-pham/phu-kien-thi-cong/', 'seg' => 'Nẹp nhôm · Gioăng EPDM · Ke chống bão · Vít', 'mseg' => 'Nẹp nhôm · Gioăng · Ke chống bão' ),
						array( 'label' => 'Phụ kiện Mái che Canopy', 'url' => '/danh-muc-san-pham/phu-kien-mai-che-canopy/', 'seg' => 'Khung B60–B150 · Nẹp nhôm · Gioăng', 'mseg' => 'Khung B60–B150 · Nẹp · Gioăng' ),
						array( 'label' => 'Mái che Canopy', 'url' => '/danh-muc-san-pham/mai-che-canopy/', 'seg' => 'Bộ mái che hoàn thiện' ),
					),
				),
			),
			// Guide card (spec §7.4).
			'card'     => array(
				'title' => 'Chưa biết chọn loại tấm nào?',
				'text'  => 'Xem Cẩm nang Tấm lợp lấy sáng để tìm sản phẩm phù hợp',
				'cta'   => 'Tìm sản phẩm phù hợp',
				'url'   => '/tam-lop-lay-sang-polycarbonate/',
			),
		),

		array(
			'label'    => 'Dự án',
			// Mục cha là link thật -> bấm vào "DỰ ÁN" đi tới /du-an/.
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
			'type'        => 'dropdown',
			'mobile'      => 'view',
			// NOTE: anchors on /ho-tro-ky-thuat/ missing -> first two point to the hub (mirror PC).
			'children'    => array(
				array( 'label' => 'Hướng dẫn thi công & khẩu độ', 'url' => '/ho-tro-ky-thuat/', 'desc' => 'Thông số, lưu ý và quy trình thực hành', 'icon' => 'guide', 'micon' => 'roof', 'mtone' => 'featured' ),
				array( 'label' => 'Tiêu chuẩn, bảo hành & xử lý sự cố', 'url' => '/ho-tro-ky-thuat/', 'desc' => 'Tiêu chuẩn chất lượng, bảo hành, khắc phục lỗi', 'mdesc' => 'Chất lượng, bảo hành, khắc phục lỗi', 'icon' => 'shield', 'micon' => 'diamond' ),
				array( 'label' => 'Tài liệu & Tải về', 'url' => '/tai-ve/', 'desc' => 'Catalogue, bảng màu, datasheet, hồ sơ năng lực', 'icon' => 'download', 'micon' => 'doc', 'mtone' => 'accent' ),
			),
			// Support CTA shown only inside this group on mobile (spec §11.4).
			'support_cta' => array( 'label' => 'Gửi yêu cầu hỗ trợ', 'url' => '/lien-he/' ),
		),

		array(
			'label'    => 'Hợp tác',
			'type'     => 'dropdown',
			'children' => array(
				array( 'label' => 'Chủ đầu tư & Nhà thầu', 'url' => '/chu-dau-tu-nha-thau/', 'icon' => 'building' ),
				array( 'label' => 'Đại lý & Nhà phân phối', 'url' => '/tuyen-dai-ly/', 'icon' => 'handshake' ),
			),
		),

		array(
			'label'    => 'Tin tức',
			// Mục cha là link thật -> bấm vào "TIN TỨC" đi tới /tin-tuc/.
			'url'      => '/tin-tuc/',
			'type'     => 'dropdown',
			'children' => array(
				array( 'label' => 'Tin tức / Báo giá', 'url' => topasia_menu_cat_url( 'tin-tuc-bao-gia' ), 'desc' => 'Cập nhật giá và thông tin thị trường', 'icon' => 'info' ),
				array( 'label' => 'Kiến thức kỹ thuật', 'url' => topasia_menu_cat_url( 'kien-thuc-ky-thuat' ), 'desc' => 'Thông số, thi công, tiêu chuẩn', 'icon' => 'guide' ),
				array( 'label' => 'Top Asia cập nhật', 'url' => topasia_menu_cat_url( 'top-asia-cap-nhat' ), 'desc' => 'Hoạt động và sự kiện doanh nghiệp', 'icon' => 'doc' ),
			),
		),

		array( 'label' => 'Liên hệ', 'url' => '/lien-he/', 'type' => 'link' ),

		array( 'label' => 'Báo giá', 'url' => '/bao-gia/', 'type' => 'cta' ),
	);
}


function topasia_menu_logo_url() {
	return home_url( '/wp-content/uploads/2026/06/logo.jpg' );
}




function topasia_menu_url( $url ) {
	if ( preg_match( '#^https?://#i', $url ) ) {
		return $url;
	}
	return home_url( $url );
}


function topasia_menu_norm_path( $url ) {
	$path = wp_parse_url( topasia_menu_url( $url ), PHP_URL_PATH );
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
	$item      = topasia_menu_norm_path( $url );
	$cur       = topasia_menu_current_path();
	$home_path = topasia_menu_norm_path( home_url( '/' ) );
	if ( $home_path === $item ) {
		return $home_path === $cur;
	}
	// Exact match, or current page is a descendant of a section URL.
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
	$out   = '<nav class="ta-nav" aria-label="Điều hướng chính"><ul class="ta-nav-list">';
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

		// Simple link / CTA.
		if ( 'link' === $type || 'cta' === $type ) {
			$cta   = ( 'cta' === $type ) ? ' ta-cta' : '';
			$aria  = $active ? ' aria-current="page"' : '';
			$out  .= '<li class="ta-item' . $acls . '"><a class="ta-link' . $cta . '" href="' . esc_url( topasia_menu_url( $item['url'] ) ) . '"' . $aria . ' data-ta-menu="' . esc_attr( $item['label'] ) . '"' . ( 'cta' === $type ? ' data-ta-cta="1"' : '' ) . '>' . esc_html( $dlabel ) . '</a></li>';
			continue;
		}

		// Dropdown / Mega.
		$panel_id = 'ta-dd-' . $inst . '-' . $i;
		$out     .= '<li class="ta-item ta-has-dd' . ( 'mega' === $type ? ' ta-has-mega' : '' ) . $acls . '">';

		if ( ! empty( $item['url'] ) ) {
			// Mục cha là link thật (Dự án, Tin tức).
			// Nếu đã có mục con trỏ tới cùng URL thì để mục con giữ aria-current,
			// tránh 2 lần aria-current="page" trong một nav.
			$pcur = topasia_menu_is_current( $item['url'] ) && ! topasia_menu_parent_url_duplicated( $item );
			$out .= '<a class="ta-link ta-dd-toggle" href="' . esc_url( topasia_menu_url( $item['url'] ) ) . '" aria-haspopup="true" aria-expanded="false" aria-controls="' . $panel_id . '"' . ( $pcur ? ' aria-current="page"' : '' ) . ' data-ta-menu="' . esc_attr( $item['label'] ) . '">';
			$out .= '<span>' . esc_html( $dlabel ) . '</span>';
			$out .= topasia_menu_svg( 'chevron', 'ta-chev', 14, '1.7' );
			$out .= '</a>';
		} else {
			// Mục cha chỉ mở panel (Giới thiệu, Sản phẩm, Hợp tác, Hỗ trợ kỹ thuật).
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

	$out .= '</ul></nav>';
	return $out;
}


function topasia_render_dropdown_panel( $item, $panel_id ) {
	$out  = '<div class="ta-dd-panel" id="' . $panel_id . '" role="region" aria-label="' . esc_attr( $item['label'] ) . '">';
	$out .= '<div class="ta-dd-inner"><p class="ta-dd-title">' . esc_html( $item['label'] ) . '</p><ul class="ta-dd-list">';
	foreach ( $item['children'] as $child ) {
		$cur   = topasia_menu_is_current( $child['url'] );
		$aria  = $cur ? ' aria-current="page"' : '';
		$ico   = ! empty( $child['icon'] ) ? '<span class="ta-dd-ic">' . topasia_menu_icon( $child['icon'] ) . '</span>' : '';
		$desc  = ! empty( $child['desc'] ) ? '<span class="ta-dd-desc">' . esc_html( $child['desc'] ) . '</span>' : '';
		$out  .= '<li><a class="ta-dd-link' . ( $cur ? ' is-active' : '' ) . '" href="' . esc_url( topasia_menu_url( $child['url'] ) ) . '"' . $aria . ' data-ta-menu="' . esc_attr( $child['label'] ) . '">' . $ico . '<span class="ta-dd-txt"><span class="ta-dd-label">' . esc_html( $child['label'] ) . '</span>' . $desc . '</span></a></li>';
	}
	$out .= '</ul></div></div>';
	return $out;
}


function topasia_render_mega_panel( $item, $panel_id ) {
	$out  = '<div class="ta-dd-panel ta-mega-panel" id="' . $panel_id . '" role="region" aria-label="' . esc_attr( $item['label'] ) . '">';
	$out .= '<div class="ta-mega-inner"><p class="ta-dd-title">' . esc_html( $item['label'] ) . '</p><div class="ta-mega-cats">';

	foreach ( $item['children'] as $child ) {
		$cur  = topasia_menu_is_current( $child['url'] );
		$out .= '<div class="ta-mega-cat">';
		// Không gắn mũi tên "›" ở đây: cấp 3 (brands/subs) luôn hiện sẵn bên dưới,
		// mũi tên chỉ khiến người dùng tưởng có flyout phải bấm mới mở.
		$out .= '<a class="ta-mega-cat-head' . ( $cur ? ' is-active' : '' ) . '" href="' . esc_url( topasia_menu_url( $child['url'] ) ) . '"' . ( $cur ? ' aria-current="page"' : '' ) . ' data-ta-menu="' . esc_attr( $child['label'] ) . '"><span>' . esc_html( $child['label'] ) . '</span>';
		$out .= '</a>';
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
	$out .= '</div>'; // .ta-mega-cats

	if ( ! empty( $item['card'] ) ) {
		$c    = $item['card'];
		$out .= '<aside class="ta-guide"><span class="ta-guide-ic">' . topasia_menu_icon( 'compass' ) . '</span><div class="ta-guide-body"><p class="ta-guide-title">' . esc_html( $c['title'] ) . '</p><p class="ta-guide-text">' . esc_html( $c['text'] ) . '</p><a class="ta-guide-cta" href="' . esc_url( topasia_menu_url( $c['url'] ) ) . '" data-ta-guide="1" data-ta-menu="' . esc_attr( $c['cta'] ) . '">' . esc_html( $c['cta'] ) . '</a></div></aside>';
	}

	$out .= '</div></div>';
	return $out;
}


function topasia_render_mobile_nav() {
	static $inst = 0;
	$inst++;
	$items = topasia_menu_config();
	$views = array();
	$list  = '';
	$i     = 0;

	foreach ( $items as $item ) {
		$i++;
		$type   = isset( $item['type'] ) ? $item['type'] : 'link';
		$active = topasia_menu_item_active( $item );

		// CTA (Báo giá) is rendered as full-width button in the footer instead.
		if ( 'cta' === $type ) {
			continue;
		}

		if ( 'link' === $type ) {
			$aria  = topasia_menu_is_current( $item['url'] ) ? ' aria-current="page"' : '';
			$list .= '<li class="ta-mitem"><a class="ta-mlink' . ( $active ? ' is-active' : '' ) . '" href="' . esc_url( topasia_menu_url( $item['url'] ) ) . '"' . $aria . ' data-ta-menu="' . esc_attr( $item['label'] ) . '">' . esc_html( $item['label'] ) . '</a></li>';
			continue;
		}

		$mobile = isset( $item['mobile'] ) ? $item['mobile'] : 'accordion';

		// Drill-down row -> separate sub-screen (Sản phẩm, Hỗ trợ kỹ thuật).
		if ( 'view' === $mobile ) {
			$vkey    = 'v' . $inst . '-' . $i;
			$list   .= '<li class="ta-mitem ta-mdrill' . ( $active ? ' is-active' : '' ) . '"><button type="button" class="ta-mtoggle ta-mtoggle--drill" data-ta-view="' . esc_attr( $vkey ) . '" data-ta-menu="' . esc_attr( $item['label'] ) . '"><span>' . esc_html( $item['label'] ) . '</span>' . topasia_menu_svg( 'chevright', 'ta-mchev ta-mchev--drill', 18, '1.8' ) . '</button></li>';
			$views[] = topasia_render_mobile_view( $item, $vkey );
			continue;
		}

		// Inline accordion (Giới thiệu, Hợp tác, Dự án, Tin tức).
		$panel_id = 'ta-macc-' . $inst . '-' . $i;
		$list    .= '<li class="ta-mitem ta-macc' . ( $active ? ' is-active' : '' ) . '">';
		$list    .= '<button type="button" class="ta-mtoggle" aria-expanded="false" aria-controls="' . $panel_id . '" data-ta-menu="' . esc_attr( $item['label'] ) . '"><span>' . esc_html( $item['label'] ) . '</span>' . topasia_menu_svg( 'chevron', 'ta-mchev', 18, '1.8' ) . '</button>';
		$list    .= '<div class="ta-mpanel" id="' . $panel_id . '" hidden>';

		// Link tới trang tổng của mục cha (nếu có 'url' và chưa có mục con nào
		// trỏ tới đúng URL đó -> tránh lặp 2 dòng giống hệt nhau).
		if ( ! empty( $item['url'] ) && ! topasia_menu_parent_url_duplicated( $item ) ) {
			$pcur  = topasia_menu_is_current( $item['url'] );
			$list .= '<a class="ta-msub' . ( $pcur ? ' is-active' : '' ) . '" href="' . esc_url( topasia_menu_url( $item['url'] ) ) . '"' . ( $pcur ? ' aria-current="page"' : '' ) . ' data-ta-menu="' . esc_attr( $item['label'] ) . ' - xem tat ca"><span class="ta-dd-ic">' . topasia_menu_icon( 'arrow' ) . '</span><span class="ta-dd-txt"><span class="ta-dd-label">Xem tất cả</span></span></a>';
		}

		foreach ( $item['children'] as $child ) {
			$cur   = topasia_menu_is_current( $child['url'] );
			$ico   = ! empty( $child['icon'] ) ? '<span class="ta-dd-ic">' . topasia_menu_icon( $child['icon'] ) . '</span>' : '';
			$desc  = ! empty( $child['desc'] ) ? '<span class="ta-dd-desc">' . esc_html( $child['desc'] ) . '</span>' : '';
			$list .= '<a class="ta-msub' . ( $cur ? ' is-active' : '' ) . '" href="' . esc_url( topasia_menu_url( $child['url'] ) ) . '"' . ( $cur ? ' aria-current="page"' : '' ) . ' data-ta-menu="' . esc_attr( $child['label'] ) . '">' . $ico . '<span class="ta-dd-txt"><span class="ta-dd-label">' . esc_html( $child['label'] ) . '</span>' . $desc . '</span></a>';
		}
		$list .= '</div></li>';
	}

	$out  = '<nav class="ta-mnav" aria-label="Điều hướng chính">';

	// Drawer header: logo + close (mockup screen 01).
	$out .= '<div class="ta-mhead">';
	$out .= '<a class="ta-mlogo" href="' . esc_url( home_url( '/' ) ) . '" data-ta-menu="Logo" aria-label="Top Asia — Trang chủ"><img src="' . esc_url( topasia_menu_logo_url() ) . '" alt="Top Asia" width="107" height="40" loading="eager"></a>';
	$out .= '<button type="button" class="ta-mclose" data-ta-close aria-label="Đóng menu">' . topasia_menu_svg( 'close', 'ta-mclose-ic', 16, '1.9' ) . '</button>';
	$out .= '</div>';

	// Views container: main screen + drill-down screens.
	$out .= '<div class="ta-mviews">';
	$out .= '<section class="ta-mview ta-mview--main is-current" data-ta-mview="main" aria-label="Menu chính">';
	$out .= '<ul class="ta-mnav-list">' . $list . '</ul>';
	$out .= '<div class="ta-mfoot">' . topasia_mobile_quote_btn() . '<p class="ta-mhotline">Hotline: <a href="tel:0896699996">0896 699 996</a></p></div>';
	$out .= '</section>';
	$out .= implode( '', $views );
	$out .= '</div></nav>';
	return $out;
}


function topasia_mobile_quote_btn() {
	return '<a class="ta-btn ta-btn--quote" href="' . esc_url( topasia_menu_url( '/bao-gia/' ) ) . '" data-ta-cta="1" data-ta-menu="NHẬN BÁO GIÁ">Nhận báo giá</a>';
}


function topasia_render_mobile_view( $item, $vkey ) {
	$type = isset( $item['type'] ) ? $item['type'] : 'dropdown';

	$out  = '<section class="ta-mview" data-ta-mview="' . esc_attr( $vkey ) . '" aria-label="' . esc_attr( $item['label'] ) . '" hidden>';
	$out .= '<div class="ta-msubbar"><button type="button" class="ta-mback" data-ta-back aria-label="Quay lại menu chính">' . topasia_menu_svg( 'back', 'ta-mback-ic', 20, '2' ) . '</button><span class="ta-msubtitle">' . esc_html( $item['label'] ) . '</span></div>';

	if ( 'mega' === $type ) {
		// Sản phẩm (mockup screen 02): category card + brand chips + rows + guide card.
		foreach ( $item['children'] as $child ) {
			$cur   = topasia_menu_is_current( $child['url'] );
			$desc  = ! empty( $child['mdesc'] ) ? $child['mdesc'] : ( ! empty( $child['desc'] ) ? $child['desc'] : '' );
			$card  = ! empty( $child['brands'] );
			$out  .= '<a class="ta-mcat ' . ( $card ? 'ta-mcat--card' : 'ta-mcat--row' ) . ( $cur ? ' is-active' : '' ) . '" href="' . esc_url( topasia_menu_url( $child['url'] ) ) . '"' . ( $cur ? ' aria-current="page"' : '' ) . ' data-ta-menu="' . esc_attr( $child['label'] ) . '">';
			$out  .= '<span class="ta-mcat-txt"><span class="ta-mcat-label">' . esc_html( $child['label'] ) . '</span>' . ( $desc ? '<span class="ta-mcat-desc">' . esc_html( $desc ) . '</span>' : '' ) . '</span>';
			$out  .= '<span class="ta-mega-arrow" aria-hidden="true">›</span></a>';
			if ( ! empty( $child['subs'] ) ) {
				$out .= '<ul class="ta-msubs">';
				foreach ( $child['subs'] as $s ) {
					$scur = topasia_menu_is_current( $s['url'] );
					$sseg = ! empty( $s['mseg'] ) ? $s['mseg'] : ( ! empty( $s['seg'] ) ? $s['seg'] : '' );
					$out .= '<li><a class="ta-msub-item' . ( $scur ? ' is-active' : '' ) . '" href="' . esc_url( topasia_menu_url( $s['url'] ) ) . '"' . ( $scur ? ' aria-current="page"' : '' ) . ' data-ta-menu="' . esc_attr( $s['label'] ) . '"><span class="ta-msub-name">' . esc_html( $s['label'] ) . '</span>' . ( $sseg ? '<span class="ta-msub-seg">' . esc_html( $sseg ) . '</span>' : '' ) . '</a></li>';
				}
				$out .= '</ul>';
			}
			if ( $card ) {
				$out .= '<div class="ta-mbrands">';
				foreach ( $child['brands'] as $b ) {
					$bcur = topasia_menu_is_current( $b['url'] );
					$acc  = ! empty( $b['accent'] ) ? ' ta-brand--accent' : '';
					$seg  = ! empty( $b['mseg'] ) ? $b['mseg'] : $b['seg'];
					$out .= '<a class="ta-brand' . $acc . ( $bcur ? ' is-active' : '' ) . '" href="' . esc_url( topasia_menu_url( $b['url'] ) ) . '"' . ( $bcur ? ' aria-current="page"' : '' ) . ' data-ta-menu="' . esc_attr( $b['name'] ) . '"><span class="ta-brand-name">' . esc_html( $b['name'] ) . '</span><span class="ta-brand-seg">' . esc_html( $seg ) . '</span></a>';
				}
				$out .= '</div>';
			}
		}
		if ( ! empty( $item['card'] ) ) {
			$c    = $item['card'];
			$out .= '<aside class="ta-guide ta-guide--m"><span class="ta-guide-ic">' . topasia_menu_icon( 'compass' ) . '</span><div class="ta-guide-body"><p class="ta-guide-title">' . esc_html( $c['title'] ) . '</p><p class="ta-guide-text">' . esc_html( $c['text'] ) . '</p><a class="ta-guide-cta" href="' . esc_url( topasia_menu_url( $c['url'] ) ) . '" data-ta-guide="1" data-ta-menu="' . esc_attr( $c['cta'] ) . '">' . esc_html( $c['cta'] ) . '</a></div></aside>';
		}
		$out .= '<div class="ta-mviewfoot">' . topasia_mobile_quote_btn() . '</div>';
	} else {
		// Hỗ trợ kỹ thuật (mockup screen 03): icon cards + support CTA + quote CTA.
		foreach ( $item['children'] as $child ) {
			$cur  = topasia_menu_is_current( $child['url'] );
			$desc = ! empty( $child['mdesc'] ) ? $child['mdesc'] : ( ! empty( $child['desc'] ) ? $child['desc'] : '' );
			$ikey = ! empty( $child['micon'] ) ? $child['micon'] : ( ! empty( $child['icon'] ) ? $child['icon'] : '' );
			$tone = ! empty( $child['mtone'] ) ? ' ta-mcard--' . sanitize_html_class( $child['mtone'] ) : '';
			$out .= '<a class="ta-mcard' . $tone . ( $cur ? ' is-active' : '' ) . '" href="' . esc_url( topasia_menu_url( $child['url'] ) ) . '"' . ( $cur ? ' aria-current="page"' : '' ) . ' data-ta-menu="' . esc_attr( $child['label'] ) . '">';
			$out .= $ikey ? '<span class="ta-mcard-ic">' . topasia_menu_icon( $ikey ) . '</span>' : '';
			$out .= '<span class="ta-mcard-txt"><span class="ta-mcard-label">' . esc_html( $child['label'] ) . '</span>' . ( $desc ? '<span class="ta-mcard-desc">' . esc_html( $desc ) . '</span>' : '' ) . '</span>';
			$out .= '<span class="ta-mega-arrow" aria-hidden="true">›</span></a>';
		}
		$foot = '';
		if ( ! empty( $item['support_cta'] ) ) {
			$sc    = $item['support_cta'];
			$foot .= '<a class="ta-btn ta-btn--support" href="' . esc_url( topasia_menu_url( $sc['url'] ) ) . '" data-ta-support="1" data-ta-menu="' . esc_attr( $sc['label'] ) . '">' . esc_html( $sc['label'] ) . '</a>';
		}
		$out .= '<div class="ta-mviewfoot">' . $foot . topasia_mobile_quote_btn() . '</div>';
	}

	$out .= '</section>';
	return $out;
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
add_filter( 'pre_wp_nav_menu', 'topasia_menu_inject', 10, 2 );


function topasia_menu_assets() {
	$dir = get_stylesheet_directory();
	$uri = get_stylesheet_directory_uri();
	$css = $dir . '/assets/topasia-menu-r5.css';
	$js  = $dir . '/assets/topasia-menu-r2.js';
	$fnt = $dir . '/assets/topasia-fonts.css';
	wp_enqueue_style( 'topasia-fonts', $uri . '/assets/topasia-fonts.css', array(), file_exists( $fnt ) ? filemtime( $fnt ) : '1' );
	wp_enqueue_style( 'topasia-menu', $uri . '/assets/topasia-menu-r5.css', array(), file_exists( $css ) ? filemtime( $css ) : '1' );
	wp_enqueue_script( 'topasia-menu', $uri . '/assets/topasia-menu-r2.js', array(), file_exists( $js ) ? filemtime( $js ) : '1', true );
}
add_action( 'wp_enqueue_scripts', 'topasia_menu_assets', 20 );


function topasia_header_main_right_elements( $value ) {
	if ( is_admin() || ! is_array( $value ) ) {
		return $value;
	}
	return array_values( array_diff( $value, array( 'search', 'cart' ) ) );
}
add_filter( 'theme_mod_header_elements_right', 'topasia_header_main_right_elements' );

function topasia_header_top_right_elements( $value ) {
	if ( is_admin() ) {
		return $value;
	}
	$value = is_array( $value ) ? $value : array();
	foreach ( array( 'search', 'cart' ) as $element ) {
		if ( ! in_array( $element, $value, true ) ) {
			$value[] = $element;
		}
	}
	return $value;
}
add_filter( 'theme_mod_topbar_elements_right', 'topasia_header_top_right_elements' );



// Re-register pre_wp_nav_menu at priority 999 so topasia_render_desktop_nav() takes precedence cleanly.
remove_filter( 'pre_wp_nav_menu', 'topasia_menu_inject', 10 );
add_filter( 'pre_wp_nav_menu', 'topasia_menu_inject', 999, 2 );

// Remove search icon from Header Main Left so it does not overlap .ta-nav.
if ( ! function_exists( 'topasia_header_main_left_elements' ) ) {
    function topasia_header_main_left_elements( $value ) {
        if ( is_admin() || ! is_array( $value ) ) {
            return $value;
        }
        return array_values( array_diff( $value, array( 'search' ) ) );
    }
}
add_filter( 'theme_mod_header_elements_left', 'topasia_header_main_left_elements' );