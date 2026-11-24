<?php
/**
 * Bộ thay thế ảnh phía Server (HTML Output Buffer) cho mọi ảnh trên trang.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TA_QIR_Replacer {

	/**
	 * Danh sách ánh xạ đang áp dụng cho request hiện tại.
	 *
	 * @var array<string, array>
	 */
	private static $active_mappings = array();

	/**
	 * Cache srcset theo attachment_id để tránh gọi lặp.
	 *
	 * @var array<int, string>
	 */
	private static $srcset_cache = array();

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_start_buffer' ), 999 );
	}

	/**
	 * Chỉ bật output buffer ở frontend khi trang hiện tại có ít nhất 1 ảnh cần thay.
	 *
	 * @return void
	 */
	public static function maybe_start_buffer() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || is_feed() ) {
			return;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}

		$page_path             = TA_QIR_Storage::get_current_page_path();
		self::$active_mappings = TA_QIR_Storage::get_effective_mappings_for_page( $page_path );

		if ( empty( self::$active_mappings ) ) {
			return;
		}

		ob_start( array( __CLASS__, 'filter_html' ) );
	}

	/**
	 * Lọc HTML đầu ra để thay thế src, srcset, lazy-load và background-image.
	 *
	 * @param string $html HTML của trang.
	 * @return string
	 */
	public static function filter_html( $html ) {
		if ( ! is_string( $html ) || '' === $html || empty( self::$active_mappings ) ) {
			return $html;
		}

		// Chỉ xử lý phản hồi HTML thực sự.
		if ( false === stripos( $html, '<html' ) && false === stripos( $html, '<img' ) && false === stripos( $html, 'background' ) ) {
			return $html;
		}

		if ( false !== stripos( $html, '<img' ) ) {
			$html = preg_replace_callback( '/<img\b[^>]*>/i', array( __CLASS__, 'replace_img_tag' ), $html );
		}

		$settings = TA_QIR_Storage::get_settings();
		if ( ! empty( $settings['support_bg'] ) && false !== stripos( $html, 'url(' ) ) {
			// Thay trong inline style="..."
			$html = preg_replace_callback(
				'/\bstyle\s*=\s*(["\'])(.*?)\1/is',
				array( __CLASS__, 'replace_inline_style' ),
				$html
			);
			// Thay trong thẻ <style>...</style>
			$html = preg_replace_callback(
				'/<style\b[^>]*>(.*?)<\/style>/is',
				array( __CLASS__, 'replace_style_block' ),
				$html
			);
		}

		return $html;
	}

	/**
	 * Tìm ánh xạ khớp với một URL ảnh.
	 *
	 * @param string $url URL cần kiểm tra.
	 * @return array|null
	 */
	private static function find_mapping( $url ) {
		$key = TA_QIR_Storage::normalize_image_url( $url );
		if ( '' !== $key && isset( self::$active_mappings[ $key ] ) ) {
			return self::$active_mappings[ $key ];
		}
		return null;
	}

	/**
	 * Lấy srcset cho attachment_id (có cache).
	 *
	 * @param int $attachment_id ID ảnh trong Media Library.
	 * @return string
	 */
	private static function get_srcset( $attachment_id ) {
		$attachment_id = (int) $attachment_id;
		if ( $attachment_id <= 0 ) {
			return '';
		}
		if ( ! isset( self::$srcset_cache[ $attachment_id ] ) ) {
			$srcset                               = wp_get_attachment_image_srcset( $attachment_id, 'full' );
			self::$srcset_cache[ $attachment_id ] = is_string( $srcset ) ? $srcset : '';
		}
		return self::$srcset_cache[ $attachment_id ];
	}

	/**
	 * Thay thế thuộc tính bên trong thẻ <img>.
	 *
	 * @param array $matches Regex match.
	 * @return string
	 */
	private static function replace_img_tag( $matches ) {
		$tag       = $matches[0];
		$url_attrs = array( 'src', 'data-src', 'data-lazy-src', 'data-original' );
		$mapping   = null;

		foreach ( $url_attrs as $attr ) {
			if ( preg_match( '/\b' . preg_quote( $attr, '/' ) . '\s*=\s*(["\'])(.*?)\1/i', $tag, $m ) ) {
				$found = self::find_mapping( $m[2] );
				if ( $found ) {
					$mapping = $found;
					break;
				}
			}
		}

		if ( ! $mapping ) {
			return $tag;
		}

		$new_url_esc = esc_url( $mapping['new_url'] );

		// Thay tất cả thuộc tính URL có mặt trên thẻ <img>.
		foreach ( $url_attrs as $attr ) {
			$tag = preg_replace(
				'/(\b' . preg_quote( $attr, '/' ) . '\s*=\s*)(["\'])(.*?)\2/i',
				'$1$2' . $new_url_esc . '$2',
				$tag
			);
		}

		// Đồng bộ hoặc xóa bỏ srcset / data-srcset cũ để trình duyệt không tải nhầm ảnh cũ.
		$new_srcset = self::get_srcset( $mapping['attachment_id'] );
		foreach ( array( 'srcset', 'data-srcset', 'data-lazy-srcset' ) as $srcset_attr ) {
			if ( preg_match( '/\b' . preg_quote( $srcset_attr, '/' ) . '\s*=\s*(["\'])(.*?)\1/i', $tag ) ) {
				if ( '' !== $new_srcset ) {
					$tag = preg_replace(
						'/(\b' . preg_quote( $srcset_attr, '/' ) . '\s*=\s*)(["\'])(.*?)\2/i',
						'$1$2' . esc_attr( $new_srcset ) . '$2',
						$tag
					);
				} else {
					$tag = preg_replace(
						'/\s+\b' . preg_quote( $srcset_attr, '/' ) . '\s*=\s*(["\'])(.*?)\1/i',
						'',
						$tag
					);
				}
			}
		}

		return $tag;
	}

	/**
	 * Thay thế url(...) trong inline style="...".
	 *
	 * @param array $matches Regex match.
	 * @return string
	 */
	private static function replace_inline_style( $matches ) {
		$quote = $matches[1];
		$style = $matches[2];
		if ( false === stripos( $style, 'url(' ) ) {
			return $matches[0];
		}
		$updated = self::replace_css_urls( $style );
		return 'style=' . $quote . $updated . $quote;
	}

	/**
	 * Thay thế url(...) trong thẻ <style>...</style>.
	 *
	 * @param array $matches Regex match.
	 * @return string
	 */
	private static function replace_style_block( $matches ) {
		$css = $matches[1];
		if ( false === stripos( $css, 'url(' ) ) {
			return $matches[0];
		}
		$updated = self::replace_css_urls( $css );
		return str_replace( $css, $updated, $matches[0] );
	}

	/**
	 * Thay các url(...) khớp trong chuỗi CSS.
	 *
	 * @param string $css Chuỗi CSS.
	 * @return string
	 */
	private static function replace_css_urls( $css ) {
		return preg_replace_callback(
			'/url\(\s*(["\']?)([^)"\']+)\1\s*\)/i',
			function ( $m ) {
				$mapping = self::find_mapping( $m[2] );
				if ( ! $mapping ) {
					return $m[0];
				}
				$quote = $m[1] ? $m[1] : '"';
				return 'url(' . $quote . esc_url( $mapping['new_url'] ) . $quote . ')';
			},
			$css
		);
	}
}
