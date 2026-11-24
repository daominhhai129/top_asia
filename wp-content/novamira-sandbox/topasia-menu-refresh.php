<?php
/**
 * Top Asia navigation refresh, loaded by Novamira sandbox.
 * Rollback: remove this file from wp-content/novamira-sandbox/.
 */
if ( ! defined( 'ABSPATH' ) || function_exists( 'topasia_menu_inject' ) ) { return; }

function topasia_refresh_items() {
    $items = topasia_menu_config();
    foreach ( $items as &$item ) {
        if ( 'Sản phẩm' === $item['label'] ) {
            array_unshift( $item['children'], array( 'label' => 'Tất cả sản phẩm', 'url' => '/tat-ca-san-pham/' ) );
        }
        if ( 'Dự án' === $item['label'] || 'Tin tức' === $item['label'] ) {
            foreach ( $item['children'] as &$child ) {
                $terms = array( '/cong-trinh-thuc-te/' => 125, '/tin-tuc-bao-gia/' => 148, '/kien-thuc-ky-thuat/' => 124, '/top-asia-cap-nhat/' => 147 );
                if ( isset( $terms[ $child['url'] ] ) ) {
                    $link = get_term_link( $terms[ $child['url'] ], 'category' );
                    if ( ! is_wp_error( $link ) ) { $child['url'] = $link; }
                }
            }
            unset( $child );
        }
        if ( 'Hỗ trợ kỹ thuật' === $item['label'] ) {
            $item['children'] = array(
                array( 'label' => 'Hỗ trợ kỹ thuật', 'url' => '/ho-tro-ky-thuat/', 'desc' => 'Hướng dẫn thi công, tiêu chuẩn và bảo hành', 'icon' => 'guide' ),
                array( 'label' => 'Tài liệu & Tải về', 'url' => '/tai-ve/', 'desc' => 'Catalogue, bảng màu và hồ sơ năng lực', 'icon' => 'download' ),
            );
        }
    }
    unset( $item );
    return $items;
}

function topasia_refresh_desktop() {
	static $inst = 0; $inst++; $out = '<nav class="ta-nav" aria-label="Điều hướng chính"><ul class="ta-nav-list">';
	foreach ( topasia_refresh_items() as $i => $item ) {
		$type = $item['type']; $active = topasia_menu_item_active( $item ); $cls = $active ? ' is-active' : '';
		if ( $type === 'link' || $type === 'cta' ) {
			$out .= '<li class="ta-item'.$cls.'"><a class="ta-link'.( $type === 'cta' ? ' ta-cta' : '' ).'" href="'.esc_url( topasia_menu_url( $item['url'] ) ).'"'.( topasia_menu_is_current($item['url']) ? ' aria-current="page"' : '' ).' data-ta-menu="'.esc_attr($item['label']).'"'.( $type === 'cta' ? ' data-ta-cta="1"' : '' ).'>'.esc_html($item['label']).'</a></li>'; continue;
		}
		$id = 'ta-refresh-dd-'.$inst.'-'.$i;
		$out .= '<li class="ta-item ta-has-dd'.( $type === 'mega' ? ' ta-has-mega' : '' ).$cls.'">';
		if ( ! empty($item['url']) ) {
			$pcur = topasia_menu_is_current($item['url']) && ! topasia_menu_parent_url_duplicated($item);
			$out .= '<a class="ta-link ta-dd-toggle" href="'.esc_url(topasia_menu_url($item['url'])).'" aria-haspopup="true" aria-expanded="false" aria-controls="'.$id.'"'.($pcur ? ' aria-current="page"' : '').' data-ta-menu="'.esc_attr($item['label']).'"><span>'.esc_html($item['label']).'</span>'.topasia_menu_svg('chevron','ta-chev',14,'1.7').'</a>';
		} else {
			$out .= '<button type="button" class="ta-link ta-dd-toggle" aria-expanded="false" aria-controls="'.$id.'" data-ta-menu="'.esc_attr($item['label']).'"><span>'.esc_html($item['label']).'</span>'.topasia_menu_svg('chevron','ta-chev',14,'1.7').'</button>';
		}
		$out .= $type === 'mega' ? topasia_render_mega_panel($item,$id) : topasia_render_dropdown_panel($item,$id);
		$out .= '</li>';
	}
	return $out.'</ul></nav>';
}

function topasia_refresh_mobile() {
	static $inst = 0; $inst++; $views = array(); $list = '';
	foreach ( topasia_refresh_items() as $i => $item ) {
		$type = $item['type']; $active = topasia_menu_item_active($item);
		if ( $type === 'cta' ) continue;
		if ( $type === 'link' ) {
			$list .= '<li class="ta-mitem"><a class="ta-mlink'.($active?' is-active':'').'" href="'.esc_url(topasia_menu_url($item['url'])).'"'.(topasia_menu_is_current($item['url'])?' aria-current="page"':'').' data-ta-menu="'.esc_attr($item['label']).'">'.esc_html($item['label']).'</a></li>'; continue;
		}
		if ( $type === 'mega' ) {
			$key = 'refresh-'.$inst.'-'.$i;
			$list .= '<li class="ta-mitem ta-mdrill'.($active?' is-active':'').'"><button type="button" class="ta-mtoggle ta-mtoggle--drill" data-ta-view="'.$key.'" data-ta-menu="'.esc_attr($item['label']).'"><span>'.esc_html($item['label']).'</span>'.topasia_menu_svg('chevright','ta-mchev ta-mchev--drill',18,'1.8').'</button></li>';
			$views[] = topasia_render_mobile_view($item,$key); continue;
		}
		$id = 'ta-refresh-macc-'.$inst.'-'.$i;
		$list .= '<li class="ta-mitem ta-macc'.($active?' is-active':'').'"><button type="button" class="ta-mtoggle" aria-expanded="false" aria-controls="'.$id.'" data-ta-menu="'.esc_attr($item['label']).'"><span>'.esc_html($item['label']).'</span>'.topasia_menu_svg('chevron','ta-mchev',18,'1.8').'</button><div class="ta-mpanel" id="'.$id.'" hidden>';
		if ( ! empty($item['url']) && ! topasia_menu_parent_url_duplicated($item) ) {
			$list .= '<a class="ta-msub" href="'.esc_url(topasia_menu_url($item['url'])).'" data-ta-menu="'.esc_attr($item['label']).' - xem tat ca"><span class="ta-dd-ic">'.topasia_menu_icon('arrow').'</span><span class="ta-dd-txt"><span class="ta-dd-label">Xem tất cả</span></span></a>';
		}
		foreach ( $item['children'] as $child ) {
			$cur = topasia_menu_is_current($child['url']); $ico = ! empty($child['icon']) ? '<span class="ta-dd-ic">'.topasia_menu_icon($child['icon']).'</span>' : '';
			$list .= '<a class="ta-msub'.($cur?' is-active':'').'" href="'.esc_url(topasia_menu_url($child['url'])).'"'.($cur?' aria-current="page"':'').' data-ta-menu="'.esc_attr($child['label']).'">'.$ico.'<span class="ta-dd-txt"><span class="ta-dd-label">'.esc_html($child['label']).'</span>'.(!empty($child['desc'])?'<span class="ta-dd-desc">'.esc_html($child['desc']).'</span>':'').'</span></a>';
		}
		$list .= '</div></li>';
	}
	$out = '<nav class="ta-mnav" aria-label="Điều hướng chính"><div class="ta-mhead"><a class="ta-mlogo" href="'.esc_url(home_url('/')).'" data-ta-menu="Logo" aria-label="Top Asia — Trang chủ"><img src="'.esc_url(topasia_menu_logo_url()).'" alt="Top Asia" width="107" height="40" loading="eager"></a><button type="button" class="ta-mclose" data-ta-close aria-label="Đóng menu">'.topasia_menu_svg('close','ta-mclose-ic',16,'1.9').'</button></div><div class="ta-mviews"><section class="ta-mview ta-mview--main is-current" data-ta-mview="main" aria-label="Menu chính"><ul class="ta-mnav-list">'.$list.'</ul><div class="ta-mfoot">'.topasia_mobile_quote_btn().'<p class="ta-mhotline">Hotline: <a href="tel:0896699996">0896 699 996</a></p></div></section>'.implode('',$views).'</div></nav>';
	return $out;
}
function topasia_refresh_menu_inject( $output, $args ) {
	if ( function_exists( 'topasia_menu_inject' ) || ! function_exists( 'topasia_render_mobile_view' ) ) {
		return $output;
	}
	if ( ! is_object($args) || empty($args->theme_location) ) return $output;
	if ( $args->theme_location === 'primary' ) return topasia_refresh_desktop();
	if ( $args->theme_location === 'primary_mobile' ) return topasia_refresh_mobile();
	return $output;
}
add_filter( 'pre_wp_nav_menu', 'topasia_refresh_menu_inject', 20, 2 );