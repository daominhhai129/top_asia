<?php
/**
 * REST API cho plugin TA Quick Image Replace.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TA_QIR_REST_API {

	const NAMESPACE_SLUG = 'ta-img-replace/v1';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Đăng ký các endpoint REST.
	 *
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			self::NAMESPACE_SLUG,
			'/replace',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'handle_replace' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_SLUG,
			'/upload-paste',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'handle_upload_paste' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_SLUG,
			'/revert',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'handle_revert' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);
	}

	/**
	 * Kiểm tra quyền thao tác.
	 *
	 * @return bool|WP_Error
	 */
	public static function check_permission() {
		if ( ! TA_QIR_Storage::current_user_can_replace() ) {
			return new WP_Error( 'rest_forbidden', 'Bạn không có quyền thay đổi hình ảnh.', array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * Lưu thay thế ảnh bằng Media Library ID hoặc URL trực tiếp.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_replace( WP_REST_Request $request ) {
		$original_url  = (string) $request->get_param( 'original_url' );
		$new_url       = (string) $request->get_param( 'new_url' );
		$attachment_id = absint( $request->get_param( 'attachment_id' ) );
		$scope         = ( 'page' === $request->get_param( 'scope' ) ) ? 'page' : 'global';
		$page_path     = (string) $request->get_param( 'page_path' );

		$saved = TA_QIR_Storage::save_mapping(
			array(
				'original_url'  => $original_url,
				'new_url'       => $new_url,
				'attachment_id' => $attachment_id,
				'scope'         => $scope,
				'page_path'     => $page_path,
			)
		);

		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		$srcset = '';
		if ( $saved['attachment_id'] > 0 ) {
			$computed = wp_get_attachment_image_srcset( $saved['attachment_id'], 'full' );
			if ( is_string( $computed ) ) {
				$srcset = $computed;
			}
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'mapping' => $saved,
				'srcset'  => $srcset,
			)
		);
	}

	/**
	 * Nhận file ảnh từ Ctrl+V hoặc Kéo-thả, lưu vào Media Library rồi thay thế luôn.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_upload_paste( WP_REST_Request $request ) {
		$files = $request->get_file_params();
		if ( empty( $files['file'] ) || ! is_array( $files['file'] ) ) {
			return new WP_Error( 'missing_file', 'Không tìm thấy dữ liệu ảnh được dán hoặc kéo vào.', array( 'status' => 400 ) );
		}

		$file = $files['file'];
		if ( ! empty( $file['error'] ) && UPLOAD_ERR_OK !== (int) $file['error'] ) {
			return new WP_Error( 'upload_error', 'Lỗi khi tải ảnh lên (mã lỗi: ' . (int) $file['error'] . ').', array( 'status' => 400 ) );
		}

		$original_url = (string) $request->get_param( 'original_url' );
		$alt_text     = sanitize_text_field( (string) $request->get_param( 'alt_text' ) );
		$scope        = ( 'page' === $request->get_param( 'scope' ) ) ? 'page' : 'global';
		$page_path    = (string) $request->get_param( 'page_path' );

		if ( '' === trim( $original_url ) ) {
			return new WP_Error( 'missing_original_url', 'Thiếu đường dẫn ảnh gốc cần thay thế.', array( 'status' => 400 ) );
		}

		// Kiểm tra định dạng ảnh thực sự trước khi đưa vào Media Library.
		$allowed_mimes = array(
			'jpg|jpeg|jpe' => 'image/jpeg',
			'png'          => 'image/png',
			'gif'          => 'image/gif',
			'webp'         => 'image/webp',
			'avif'         => 'image/avif',
		);

		$tmp_path = isset( $file['tmp_name'] ) ? $file['tmp_name'] : '';
		if ( '' === $tmp_path || ! file_exists( $tmp_path ) ) {
			return new WP_Error( 'invalid_tmp_file', 'Không đọc được tệp ảnh tạm.', array( 'status' => 400 ) );
		}

		$img_info = @getimagesize( $tmp_path );
		if ( false === $img_info && ( ! function_exists( 'mime_content_type' ) || 'image/avif' !== @mime_content_type( $tmp_path ) ) ) {
			return new WP_Error( 'not_an_image', 'Dữ liệu được dán không phải là tệp ảnh hợp lệ.', array( 'status' => 400 ) );
		}

		// Tạo tên file gọn gàng, có nghĩa.
		$orig_name = isset( $file['name'] ) ? (string) $file['name'] : 'pasted-image.png';
		$ext       = strtolower( pathinfo( $orig_name, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif' ), true ) ) {
			$mime = isset( $img_info['mime'] ) ? $img_info['mime'] : ( isset( $file['type'] ) ? $file['type'] : 'image/png' );
			$map  = array(
				'image/jpeg' => 'jpg',
				'image/png'  => 'png',
				'image/gif'  => 'gif',
				'image/webp' => 'webp',
				'image/avif' => 'avif',
			);
			$ext  = isset( $map[ $mime ] ) ? $map[ $mime ] : 'png';
		}

		$slug_base = sanitize_title( $alt_text );
		if ( '' === $slug_base ) {
			$slug_base = 'quick-image';
		}
		$slug_base             = substr( $slug_base, 0, 50 );
		$_FILES['ta_qir_file'] = array(
			'name'     => $slug_base . '-' . gmdate( 'Ymd-His' ) . '.' . $ext,
			'type'     => isset( $file['type'] ) ? $file['type'] : 'image/' . ( 'jpg' === $ext ? 'jpeg' : $ext ),
			'tmp_name' => $tmp_path,
			'error'    => 0,
			'size'     => isset( $file['size'] ) ? (int) $file['size'] : filesize( $tmp_path ),
		);

		$checked = wp_check_filetype_and_ext( $_FILES['ta_qir_file']['tmp_name'], $_FILES['ta_qir_file']['name'], $allowed_mimes );
		if ( empty( $checked['type'] ) || empty( $checked['ext'] ) ) {
			return new WP_Error( 'unsupported_image_type', 'Chỉ hỗ trợ ảnh JPG, PNG, WebP, GIF hoặc AVIF.', array( 'status' => 400 ) );
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$attachment_id = media_handle_upload(
			'ta_qir_file',
			0,
			array(
				'post_title' => $alt_text ? $alt_text : $slug_base,
			)
		);

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		if ( '' !== $alt_text ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt_text );
		}

		$new_url = wp_get_attachment_url( $attachment_id );
		$saved   = TA_QIR_Storage::save_mapping(
			array(
				'original_url'  => $original_url,
				'new_url'       => $new_url,
				'attachment_id' => $attachment_id,
				'scope'         => $scope,
				'page_path'     => $page_path,
			)
		);

		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		$srcset   = '';
		$computed = wp_get_attachment_image_srcset( $attachment_id, 'full' );
		if ( is_string( $computed ) ) {
			$srcset = $computed;
		}

		return rest_ensure_response(
			array(
				'success'       => true,
				'attachment_id' => $attachment_id,
				'new_url'       => $new_url,
				'srcset'        => $srcset,
				'mapping'       => $saved,
			)
		);
	}

	/**
	 * Khôi phục ảnh về bản gốc.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_revert( WP_REST_Request $request ) {
		$id           = sanitize_text_field( (string) $request->get_param( 'id' ) );
		$original_url = (string) $request->get_param( 'original_url' );
		$page_path    = (string) $request->get_param( 'page_path' );

		$deleted = TA_QIR_Storage::delete_mapping( $id, $original_url, $page_path );
		if ( ! $deleted ) {
			return new WP_Error( 'not_found', 'Không tìm thấy bản ghi thay thế để khôi phục.', array( 'status' => 404 ) );
		}

		return rest_ensure_response(
			array(
				'success'      => true,
				'reverted'     => true,
				'original_url' => $original_url,
			)
		);
	}
}
