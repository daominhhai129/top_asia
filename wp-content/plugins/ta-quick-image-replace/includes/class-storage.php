<?php
/**
 * Quản lý lưu trữ bảng ánh xạ ảnh và cài đặt plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TA_QIR_Storage {

	const OPTION_MAPPINGS = 'ta_qir_mappings';
	const OPTION_SETTINGS = 'ta_qir_settings';

	/**
	 * Cache nội bộ trong request.
	 *
	 * @var array|null
	 */
	private static $mappings_cache = null;

	/**
	 * Cache cài đặt trong request.
	 *
	 * @var array|null
	 */
	private static $settings_cache = null;

	public static function init() {
		// Dự phòng cho các hook khởi tạo sau này nếu cần.
	}

	/**
	 * Giá trị cài đặt mặc định.
	 *
	 * @return array
	 */
	public static function get_default_settings() {
		return array(
			'default_mode'  => 'toggle', // 'toggle' (Admin Bar) | 'always'
			'support_bg'    => true,
			'min_size'      => 24,
			'allowed_roles' => array( 'administrator', 'editor' ),
		);
	}

	/**
	 * Lấy cài đặt hiện tại.
	 *
	 * @return array
	 */
	public static function get_settings() {
		if ( null !== self::$settings_cache ) {
			return self::$settings_cache;
		}
		$saved    = get_option( self::OPTION_SETTINGS, array() );
		$defaults = self::get_default_settings();
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		$merged = wp_parse_args( $saved, $defaults );
		$merged['support_bg'] = (bool) $merged['support_bg'];
		$merged['min_size']   = max( 0, (int) $merged['min_size'] );
		if ( ! in_array( $merged['default_mode'], array( 'toggle', 'always' ), true ) ) {
			$merged['default_mode'] = 'toggle';
		}
		if ( ! is_array( $merged['allowed_roles'] ) || empty( $merged['allowed_roles'] ) ) {
			$merged['allowed_roles'] = array( 'administrator' );
		}
		self::$settings_cache = $merged;
		return self::$settings_cache;
	}

	/**
	 * Cập nhật cài đặt.
	 *
	 * @param array $settings Dữ liệu cài đặt mới.
	 * @return array
	 */
	public static function save_settings( $settings ) {
		$defaults = self::get_default_settings();
		$clean    = array(
			'default_mode'  => ( isset( $settings['default_mode'] ) && 'always' === $settings['default_mode'] ) ? 'always' : 'toggle',
			'support_bg'    => ! empty( $settings['support_bg'] ),
			'min_size'      => isset( $settings['min_size'] ) ? max( 0, min( 500, (int) $settings['min_size'] ) ) : $defaults['min_size'],
			'allowed_roles' => array( 'administrator' ),
		);

		if ( ! empty( $settings['allowed_roles'] ) && is_array( $settings['allowed_roles'] ) ) {
			$roles = array_map( 'sanitize_key', $settings['allowed_roles'] );
			$roles = array_unique( array_merge( array( 'administrator' ), $roles ) );
			$clean['allowed_roles'] = array_values( $roles );
		}

		update_option( self::OPTION_SETTINGS, $clean, false );
		self::$settings_cache = $clean;
		return $clean;
	}

	/**
	 * Kiểm tra người dùng hiện tại có quyền thay ảnh không.
	 *
	 * @return bool
	 */
	public static function current_user_can_replace() {
		if ( ! is_user_logged_in() || ! current_user_can( 'upload_files' ) ) {
			return false;
		}
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		$user     = wp_get_current_user();
		$settings = self::get_settings();
		$allowed  = $settings['allowed_roles'];
		foreach ( (array) $user->roles as $role ) {
			if ( in_array( $role, $allowed, true ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Lấy toàn bộ bảng ánh xạ ảnh.
	 *
	 * @return array
	 */
	public static function get_all_mappings() {
		if ( null !== self::$mappings_cache ) {
			return self::$mappings_cache;
		}
		$data = get_option( self::OPTION_MAPPINGS, array() );
		if ( ! is_array( $data ) ) {
			$data = array();
		}
		self::$mappings_cache = $data;
		return self::$mappings_cache;
	}

	/**
	 * Chuẩn hóa đường dẫn trang hiện tại (ví dụ: '/', '/san-pham/').
	 *
	 * @param string $path Đường dẫn hoặc URL.
	 * @return string
	 */
	public static function normalize_page_path( $path ) {
		$path = trim( (string) $path );
		if ( '' === $path ) {
			return '/';
		}
		if ( preg_match( '#^https?://#i', $path ) ) {
			$parsed = wp_parse_url( $path );
			$path   = isset( $parsed['path'] ) ? $parsed['path'] : '/';
		}
		$path = '/' . ltrim( $path, '/' );
		if ( '/' !== $path ) {
			$path = trailingslashit( $path );
		}
		return $path;
	}

	/**
	 * Lấy đường dẫn trang hiện tại từ REQUEST_URI.
	 *
	 * @return string
	 */
	public static function get_current_page_path() {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
		$path = strtok( $uri, '?' );
		return self::normalize_page_path( $path ? $path : '/' );
	}

	/**
	 * Chuẩn hóa URL ảnh thành khóa so khớp ổn định (bỏ qua http/https nếu cùng domain).
	 *
	 * @param string $url URL ảnh gốc.
	 * @return string
	 */
	public static function normalize_image_url( $url ) {
		$url = trim( html_entity_decode( (string) $url, ENT_QUOTES, 'UTF-8' ) );
		if ( '' === $url || 0 === stripos( $url, 'data:' ) || 0 === stripos( $url, 'blob:' ) ) {
			return '';
		}

		// URL dạng //domain.com/path
		if ( 0 === strpos( $url, '//' ) ) {
			$scheme = is_ssl() ? 'https:' : 'http:';
			$url    = $scheme . $url;
		} elseif ( 0 === strpos( $url, '/' ) ) {
			$url = home_url( $url );
		} elseif ( ! preg_match( '#^https?://#i', $url ) ) {
			$url = home_url( '/' . ltrim( $url, './' ) );
		}

		$parsed    = wp_parse_url( $url );
		$home_host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		$host      = isset( $parsed['host'] ) ? strtolower( $parsed['host'] ) : '';
		$path      = isset( $parsed['path'] ) ? $parsed['path'] : '';
		$query     = isset( $parsed['query'] ) && '' !== $parsed['query'] ? '?' . $parsed['query'] : '';

		if ( '' === $path ) {
			return '';
		}

		if ( $host === $home_host || '' === $host ) {
			return $path . $query;
		}

		return '//' . $host . $path . $query;
	}

	/**
	 * Kiểm tra URL mới có hợp lệ và an toàn không.
	 *
	 * @param string $url URL mới.
	 * @return string|false URL đã làm sạch hoặc false nếu không hợp lệ.
	 */
	public static function sanitize_image_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return false;
		}
		if ( 0 === strpos( $url, '/' ) && 0 !== strpos( $url, '//' ) ) {
			$url = home_url( $url );
		}
		$clean = esc_url_raw( $url, array( 'http', 'https' ) );
		if ( '' === $clean ) {
			return false;
		}
		return $clean;
	}

	/**
	 * Lưu hoặc cập nhật một ánh xạ ảnh.
	 *
	 * @param array $args Tham số ánh xạ.
	 * @return array|WP_Error
	 */
	public static function save_mapping( $args ) {
		$raw_original  = isset( $args['original_url'] ) ? trim( (string) $args['original_url'] ) : '';
		$original_key  = self::normalize_image_url( $raw_original );
		$attachment_id = isset( $args['attachment_id'] ) ? absint( $args['attachment_id'] ) : 0;
		$new_url       = isset( $args['new_url'] ) ? self::sanitize_image_url( $args['new_url'] ) : false;
		$scope         = ( isset( $args['scope'] ) && 'page' === $args['scope'] ) ? 'page' : 'global';
		$page_path     = ( 'page' === $scope ) ? self::normalize_page_path( isset( $args['page_path'] ) ? $args['page_path'] : '/' ) : '';

		if ( '' === $original_key ) {
			return new WP_Error( 'invalid_original_url', 'Không xác định được đường dẫn ảnh gốc.', array( 'status' => 400 ) );
		}

		if ( $attachment_id > 0 ) {
			if ( ! wp_attachment_is_image( $attachment_id ) ) {
				return new WP_Error( 'invalid_attachment', 'Tệp được chọn trong Media Library không phải là ảnh hợp lệ.', array( 'status' => 400 ) );
			}
			$att_url = wp_get_attachment_url( $attachment_id );
			if ( $att_url ) {
				$new_url = $att_url;
			}
		}

		if ( ! $new_url ) {
			return new WP_Error( 'invalid_new_url', 'URL ảnh mới không hợp lệ (chỉ hỗ trợ http/https).', array( 'status' => 400 ) );
		}

		$scope_key = ( 'page' === $scope ) ? 'page:' . $page_path : 'global';
		$id        = md5( $scope_key . '|' . $original_key );

		$mappings        = self::get_all_mappings();
		$mappings[ $id ] = array(
			'id'            => $id,
			'original_key'  => $original_key,
			'original_url'  => $raw_original,
			'new_url'       => $new_url,
			'attachment_id' => $attachment_id,
			'scope'         => $scope,
			'page_path'     => $page_path,
			'updated_at'    => current_time( 'mysql' ),
			'updated_by'    => get_current_user_id(),
		);

		update_option( self::OPTION_MAPPINGS, $mappings, true );
		self::$mappings_cache = $mappings;
		self::purge_caches();

		return $mappings[ $id ];
	}

	/**
	 * Khôi phục (xóa) một ánh xạ theo ID hoặc theo original_url + page_path.
	 *
	 * @param string $id ID ánh xạ.
	 * @param string $original_url URL gốc (tùy chọn).
	 * @param string $page_path Đường dẫn trang (tùy chọn).
	 * @return bool
	 */
	public static function delete_mapping( $id = '', $original_url = '', $page_path = '' ) {
		$mappings = self::get_all_mappings();
		$changed  = false;

		if ( '' !== $id && isset( $mappings[ $id ] ) ) {
			unset( $mappings[ $id ] );
			$changed = true;
		} elseif ( '' !== $original_url ) {
			$key       = self::normalize_image_url( $original_url );
			$norm_page = self::normalize_page_path( $page_path );

			// Ưu tiên xóa bản ghi theo trang trước, nếu không có thì xóa bản ghi global.
			$page_id   = md5( 'page:' . $norm_page . '|' . $key );
			$global_id = md5( 'global|' . $key );

			if ( isset( $mappings[ $page_id ] ) ) {
				unset( $mappings[ $page_id ] );
				$changed = true;
			} elseif ( isset( $mappings[ $global_id ] ) ) {
				unset( $mappings[ $global_id ] );
				$changed = true;
			}
		}

		if ( $changed ) {
			update_option( self::OPTION_MAPPINGS, $mappings, true );
			self::$mappings_cache = $mappings;
			self::purge_caches();
		}

		return $changed;
	}

	/**
	 * Xóa toàn bộ ánh xạ ảnh.
	 *
	 * @return void
	 */
	public static function delete_all_mappings() {
		update_option( self::OPTION_MAPPINGS, array(), true );
		self::$mappings_cache = array();
		self::purge_caches();
	}

	/**
	 * Lấy danh sách ánh xạ áp dụng cho một trang (key = original_key, ưu tiên scope='page').
	 *
	 * @param string $page_path Đường dẫn trang đã chuẩn hóa.
	 * @return array<string, array>
	 */
	public static function get_effective_mappings_for_page( $page_path ) {
		$all       = self::get_all_mappings();
		$page_path = self::normalize_page_path( $page_path );
		$result    = array();

		if ( empty( $all ) ) {
			return $result;
		}

		// Nạp global trước.
		foreach ( $all as $item ) {
			if ( isset( $item['scope'], $item['original_key'] ) && 'global' === $item['scope'] ) {
				$result[ $item['original_key'] ] = $item;
			}
		}

		// Ghi đè bằng scope='page' nếu khớp trang hiện tại.
		foreach ( $all as $item ) {
			if ( isset( $item['scope'], $item['page_path'], $item['original_key'] ) && 'page' === $item['scope'] && $item['page_path'] === $page_path ) {
				$result[ $item['original_key'] ] = $item;
			}
		}

		return $result;
	}

	/**
	 * Xóa cache trang phổ biến sau khi thay ảnh.
	 *
	 * @return void
	 */
	public static function purge_caches() {
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
		}
		if ( has_action( 'litespeed_purge_all' ) ) {
			do_action( 'litespeed_purge_all' );
		}
		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all();
		}
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
		}
		if ( function_exists( 'wpfc_clear_all_cache' ) ) {
			wpfc_clear_all_cache( true );
		}
		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			sg_cachepress_purge_cache();
		}
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}
	}
}
