<?php
/**
 * Phone-gated PDF download controller, CF7 lead capture bridge, and REST API endpoints.
 *
 * @package TopAsia
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TA_Quote_Download_Gate {

	private static bool $booted = false;

	/**
	 * Register WordPress hooks once.
	 */
	public static function boot(): void {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		add_action( 'init', array( 'TA_Quote_Lead_Model', 'maybe_install' ), 5 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'localize_home_script' ), 120 );

		add_action( 'wp_ajax_taf_qd_submit', array( __CLASS__, 'handle_submit' ) );
		add_action( 'wp_ajax_nopriv_taf_qd_submit', array( __CLASS__, 'handle_submit' ) );

		add_action( 'admin_post_taf_qd_download', array( __CLASS__, 'handle_download' ) );
		add_action( 'admin_post_nopriv_taf_qd_download', array( __CLASS__, 'handle_download' ) );

		add_action( 'wpcf7_before_send_mail', array( __CLASS__, 'capture_cf7_submission' ), 20, 1 );
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
	}

	/**
	 * Localize AJAX & download endpoints for the homepage quote modal.
	 */
	public static function localize_home_script(): void {
		if ( ! is_front_page() ) {
			return;
		}

		$config = array(
			'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
			'downloadUrl' => admin_url( 'admin-post.php' ),
			'nonce'       => wp_create_nonce( 'taf_qd_submit' ),
			'source'      => 'home_quote_modal',
		);

		foreach ( array( 'ta-home', 'taf-home' ) as $handle ) {
			if ( wp_script_is( $handle, 'enqueued' ) ) {
				wp_localize_script( $handle, 'TAFQuoteConfig', $config );
			}
		}
	}

	/**
	 * Handle AJAX phone submission from homepage or /bao-gia/ quote download modal.
	 */
	public static function handle_submit(): void {
		if ( 'POST' !== strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? (string) $_SERVER['REQUEST_METHOD'] : '' ) ) {
			wp_send_json_error( array( 'message' => 'Vui lòng gửi lại yêu cầu.' ), 405 );
		}

		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'taf_qd_submit' ) ) {
			wp_send_json_error( array( 'message' => 'Phiên tải đã hết hạn. Vui lòng tải lại trang.' ), 403 );
		}

		$honeypot = isset( $_POST['taf_qd_company'] ) ? sanitize_text_field( wp_unslash( $_POST['taf_qd_company'] ) ) : '';
		if ( '' !== $honeypot ) {
			wp_send_json_error( array( 'message' => 'Không thể xử lý yêu cầu này.' ), 400 );
		}

		if ( self::is_rate_limited() ) {
			wp_send_json_error( array( 'message' => 'Bạn gửi yêu cầu quá nhanh. Vui lòng thử lại sau ít phút.' ), 429 );
		}

		$phone_raw = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$phone     = TA_Quote_Lead_Model::normalize_phone( $phone_raw );
		if ( ! TA_Quote_Lead_Model::is_valid_vn_phone( $phone ) ) {
			wp_send_json_error( array( 'message' => 'Vui lòng nhập số điện thoại hợp lệ (10–11 chữ số, bắt đầu bằng 0).' ), 400 );
		}

		$catalog = TA_Quote_Lead_Model::get_catalog();
		$product = isset( $_POST['product'] ) ? sanitize_key( wp_unslash( $_POST['product'] ) ) : 'asia-gold';
		if ( ! isset( $catalog[ $product ] ) ) {
			$product = 'asia-gold';
		}

		$page_url = isset( $_POST['page_url'] ) ? esc_url_raw( wp_unslash( $_POST['page_url'] ) ) : '';
		if ( '' === $page_url ) {
			$page_url = (string) wp_get_referer();
		}

		$source = isset( $_POST['source'] ) ? sanitize_key( wp_unslash( $_POST['source'] ) ) : '';
		if ( '' === $source ) {
			$source = ( false !== strpos( $page_url, 'bao-gia' ) ) ? 'bao_gia_pdf_modal' : 'home_quote_modal';
		}

		// Always record the contact phone lead before attempting PDF resolution so no lead is ever lost.
		$saved = TA_Quote_Lead_Model::record_lead(
			array(
				'phone'        => $phone,
				'product'      => $catalog[ $product ]['label'],
				'product_slug' => $product,
				'source'       => $source,
				'page_url'     => $page_url,
			)
		);

		if ( is_wp_error( $saved ) ) {
			wp_send_json_error( array( 'message' => $saved->get_error_message() ), 500 );
		}

		$lead_id     = (int) $saved['lead_id'];
		$customer_id = (int) $saved['customer_id'];

		$pdf = TA_Quote_Lead_Model::resolve_pdf( $product );
		if ( false === $pdf ) {
			wp_send_json_error(
				array(
					'message'    => 'Đã lưu số điện thoại của bạn. Tệp PDF báo giá hiện đang được cập nhật trên máy chủ — nhân viên Top Asia sẽ gửi trực tiếp qua Zalo/SĐT cho bạn.',
					'leadSaved'  => true,
					'leadId'     => $lead_id,
					'customerId' => $customer_id,
				),
				404
			);
		}

		$token        = wp_generate_password( 48, false, false );
		$transient_id = 'taf_qd_download_' . hash( 'sha256', $token );
		$stored       = set_transient(
			$transient_id,
			array(
				'lead_id'          => $lead_id,
				'customer_id'      => $customer_id,
				'product'          => $product,
				'allowed_products' => array_keys( $catalog ),
			),
			15 * MINUTE_IN_SECONDS
		);

		if ( ! $stored ) {
			wp_send_json_error( array( 'message' => 'Không thể tạo liên kết tải lúc này. Vui lòng gửi lại yêu cầu.' ), 500 );
		}

		$download_url = add_query_arg(
			array(
				'action' => 'taf_qd_download',
				'token'  => $token,
				'line'   => $product,
			),
			admin_url( 'admin-post.php' )
		);

		$downloads = array();
		foreach ( $catalog as $key => $meta ) {
			if ( false !== TA_Quote_Lead_Model::resolve_pdf( $key ) ) {
				$downloads[] = array(
					'key'   => $key,
					'label' => $meta['label'],
					'url'   => esc_url_raw(
						add_query_arg(
							array(
								'action' => 'taf_qd_download',
								'token'  => $token,
								'line'   => $key,
							),
							admin_url( 'admin-post.php' )
						)
					),
				);
			}
		}

		wp_send_json_success(
			array(
				'leadId'      => $lead_id,
				'customerId'  => $customer_id,
				'downloadUrl' => esc_url_raw( $download_url ),
				'downloads'   => $downloads,
			)
		);
	}

	/**
	 * Stream the authorized PDF file and record the download timestamp on the lead & customer model.
	 */
	public static function handle_download(): void {
		$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
		if ( '' === $token ) {
			wp_die( 'Liên kết tải không hợp lệ. Vui lòng gửi lại yêu cầu.', 'Không thể tải báo giá', array( 'response' => 403 ) );
		}

		$transient_id = 'taf_qd_download_' . hash( 'sha256', $token );
		$grant        = get_transient( $transient_id );
		if ( ! is_array( $grant ) || empty( $grant['product'] ) || empty( $grant['lead_id'] ) ) {
			wp_die( 'Liên kết tải đã hết hạn hoặc không hợp lệ. Vui lòng nhập lại số điện thoại.', 'Không thể tải báo giá', array( 'response' => 403 ) );
		}

		$line    = isset( $_GET['line'] ) ? sanitize_key( wp_unslash( $_GET['line'] ) ) : sanitize_key( (string) $grant['product'] );
		$allowed = isset( $grant['allowed_products'] ) && is_array( $grant['allowed_products'] ) ? $grant['allowed_products'] : array( $grant['product'] );
		if ( ! in_array( $line, $allowed, true ) ) {
			$line = sanitize_key( (string) $grant['product'] );
		}

		$pdf = TA_Quote_Lead_Model::resolve_pdf( $line );
		if ( false === $pdf ) {
			wp_die( 'Hiện chưa tìm thấy tệp báo giá. Vui lòng thử lại sau.', 'Không thể tải báo giá', array( 'response' => 404 ) );
		}

		$uploads  = wp_upload_dir();
		$file_url = ! empty( $uploads['baseurl'] ) && ! empty( $pdf[2] ) ? trailingslashit( $uploads['baseurl'] ) . ltrim( $pdf[2], '/' ) : '';
		TA_Quote_Lead_Model::mark_downloaded( (int) $grant['lead_id'], $line, $file_url );

		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}
		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( (string) $pdf[0]['download_name'] ) . '"' );
		header( 'Content-Length: ' . (string) filesize( $pdf[1] ) );
		header( 'X-Content-Type-Options: nosniff' );
		readfile( $pdf[1] );
		exit;
	}

	/**
	 * Capture valid phone leads submitted via Contact Form 7 forms into the unified backend model.
	 *
	 * @param mixed $contact_form WPCF7_ContactForm instance.
	 */
	public static function capture_cf7_submission( $contact_form ): void {
		$settings = TA_Quote_Lead_Model::get_settings();
		if ( empty( $settings['capture_cf7'] ) ) {
			return;
		}
		if ( ! class_exists( 'WPCF7_Submission' ) ) {
			return;
		}

		$submission = WPCF7_Submission::get_instance();
		if ( ! $submission ) {
			return;
		}

		$posted = $submission->get_posted_data();
		if ( ! is_array( $posted ) || empty( $posted ) ) {
			return;
		}

		$phone = self::extract_first_field( $posted, array( 'your-tel', 'phone', 'your-phone', 'sdt' ), '/^(?:tel|phone)[_-]?\d*$/i' );
		$norm  = TA_Quote_Lead_Model::normalize_phone( $phone );
		if ( ! TA_Quote_Lead_Model::is_valid_vn_phone( $norm ) ) {
			return;
		}

		$form_id    = ( is_object( $contact_form ) && method_exists( $contact_form, 'id' ) ) ? (int) $contact_form->id() : 0;
		$form_title = ( is_object( $contact_form ) && method_exists( $contact_form, 'title' ) ) ? (string) $contact_form->title() : '';
		$full_name  = self::extract_first_field( $posted, array( 'your-name', 'fullname', 'name' ) );
		$email      = self::extract_first_field( $posted, array( 'your-email', 'email' ) );
		$product    = self::extract_first_field( $posted, array( 'product-line', 'select-766', 'product' ) );
		$cust_type  = self::extract_first_field( $posted, array( 'customer-type' ) );
		$message    = self::extract_first_field( $posted, array( 'your-message', 'note', 'your-subject' ) );
		$page_url   = self::extract_first_field( $posted, array( 'page-url' ) );
		if ( '' === $page_url && method_exists( $submission, 'get_meta' ) ) {
			$page_url = (string) $submission->get_meta( 'url' );
		}

		$is_quote_form = ( false !== stripos( $form_title, 'gia' ) || false !== strpos( $page_url, 'bao-gia' ) || '' !== $product );
		$source        = $is_quote_form ? 'cf7_quote' : 'cf7_contact';

		$note_parts = array();
		if ( '' !== $form_title ) {
			$note_parts[] = 'Form: ' . $form_title . ( $form_id ? ' (#' . $form_id . ')' : '' );
		}
		if ( '' !== $message ) {
			$note_parts[] = $message;
		}

		TA_Quote_Lead_Model::record_lead(
			array(
				'phone'         => $norm,
				'full_name'     => $full_name,
				'email'         => $email,
				'customer_type' => $cust_type,
				'product'       => '' !== $product ? $product : ( $form_title ? $form_title : 'Liên hệ tư vấn' ),
				'product_slug'  => sanitize_key( $product ),
				'source'        => $source,
				'page_url'      => $page_url,
				'note'          => implode( ' — ', $note_parts ),
			)
		);
	}

	/**
	 * Register admin-protected REST routes for the quote-lead backend model.
	 */
	public static function register_rest_routes(): void {
		$admin_perm = static function (): bool {
			return current_user_can( 'manage_options' );
		};

		register_rest_route(
			'topasia/v1',
			'/quote-customers',
			array(
				'methods'             => 'GET',
				'permission_callback' => $admin_perm,
				'callback'            => static function ( WP_REST_Request $request ): WP_REST_Response {
					$result = TA_Quote_Lead_Model::get_customers(
						array(
							'status'   => $request->get_param( 'status' ),
							'source'   => $request->get_param( 'source' ),
							'product'  => $request->get_param( 'product' ),
							'search'   => $request->get_param( 'search' ),
							'paged'    => $request->get_param( 'page' ) ?: 1,
							'per_page' => $request->get_param( 'per_page' ) ?: 25,
						)
					);
					return new WP_REST_Response( $result, 200 );
				},
			)
		);

		register_rest_route(
			'topasia/v1',
			'/quote-customers/(?P<id>\d+)',
			array(
				'methods'             => 'POST,PATCH',
				'permission_callback' => $admin_perm,
				'callback'            => static function ( WP_REST_Request $request ): WP_REST_Response {
					$id = (int) $request->get_param( 'id' );
					$ok = TA_Quote_Lead_Model::update_customer( $id, (array) $request->get_json_params() );
					if ( ! $ok ) {
						return new WP_REST_Response( array( 'message' => 'Không thể cập nhật bản ghi.' ), 400 );
					}
					return new WP_REST_Response( array( 'customer' => TA_Quote_Lead_Model::get_customer( $id ) ), 200 );
				},
			)
		);

		register_rest_route(
			'topasia/v1',
			'/quote-leads',
			array(
				'methods'             => 'GET',
				'permission_callback' => $admin_perm,
				'callback'            => static function ( WP_REST_Request $request ): WP_REST_Response {
					$result = TA_Quote_Lead_Model::get_leads(
						array(
							'customer_id' => $request->get_param( 'customer_id' ),
							'source'      => $request->get_param( 'source' ),
							'search'      => $request->get_param( 'search' ),
							'paged'       => $request->get_param( 'page' ) ?: 1,
							'per_page'    => $request->get_param( 'per_page' ) ?: 30,
						)
					);
					return new WP_REST_Response( $result, 200 );
				},
			)
		);

		register_rest_route(
			'topasia/v1',
			'/quote-leads/stats',
			array(
				'methods'             => 'GET',
				'permission_callback' => $admin_perm,
				'callback'            => static function (): WP_REST_Response {
					return new WP_REST_Response( TA_Quote_Lead_Model::get_stats(), 200 );
				},
			)
		);
	}

	/**
	 * Lightweight per-IP rate limiter (25 requests / 10 minutes) to prevent automated spam floods.
	 */
	private static function is_rate_limited(): bool {
		$ip = TA_Quote_Lead_Model::resolve_client_ip();
		if ( '' === $ip ) {
			return false;
		}
		$key   = 'taf_qd_rl_' . md5( $ip );
		$count = (int) get_transient( $key );
		if ( $count >= 25 ) {
			return true;
		}
		set_transient( $key, $count + 1, 10 * MINUTE_IN_SECONDS );
		return false;
	}

	/**
	 * Extract a scalar/array CF7 posted field matching exact keys or an optional regex key pattern.
	 *
	 * @param array<string, mixed> $posted       Posted CF7 array.
	 * @param array<int, string>   $exact_keys   Candidate field names.
	 * @param string               $regex_key    Optional regex pattern for dynamic field names (e.g. tel-321).
	 */
	private static function extract_first_field( array $posted, array $exact_keys, string $regex_key = '' ): string {
		$to_str = static function ( $val ): string {
			if ( is_array( $val ) ) {
				$flat = array_filter( array_map( 'strval', $val ) );
				return trim( implode( ', ', $flat ) );
			}
			return trim( (string) $val );
		};

		foreach ( $exact_keys as $k ) {
			if ( isset( $posted[ $k ] ) ) {
				$v = $to_str( $posted[ $k ] );
				if ( '' !== $v ) {
					return $v;
				}
			}
		}

		if ( '' !== $regex_key ) {
			foreach ( $posted as $k => $val ) {
				if ( preg_match( $regex_key, (string) $k ) ) {
					$v = $to_str( $val );
					if ( '' !== $v ) {
						return $v;
					}
				}
			}
		}

		return '';
	}
}
