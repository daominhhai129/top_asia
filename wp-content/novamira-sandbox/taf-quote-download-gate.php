<?php
/**
 * Top Asia homepage price-list downloads.
 * Stores download requests as private WordPress records.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function taf_qd_catalog() {
	return array(
		'asia-gold' => array(
			'label'         => 'Asia Gold',
			'path'          => '2026/11/Bao-gia-dong-Asia-Gold.pdf',
			'download_name' => 'Bao-gia-Asia-Gold.pdf',
		),
		'asia-lite' => array(
			'label'         => 'Asia Lite',
			'path'          => '2026/11/Bao-gia-dong-Asia-Lite.pdf',
			'download_name' => 'Bao-gia-Asia-Lite.pdf',
		),
		'asia-pearl' => array(
			'label'         => 'Asia Pearl',
			'path'          => '2026/11/Bao-gia-dong-Asia-Pearl.pdf',
			'download_name' => 'Bao-gia-Asia-Pearl.pdf',
		),
	);
}

function taf_qd_register_lead_type() {
	register_post_type(
		'taf_quote_lead',
		array(
			'labels' => array(
				'name'          => 'Yêu cầu tải báo giá',
				'singular_name' => 'Yêu cầu tải báo giá',
				'menu_name'     => 'Yêu cầu báo giá',
				'edit_item'     => 'Xem yêu cầu tải báo giá',
				'view_item'     => 'Xem yêu cầu tải báo giá',
				'search_items'  => 'Tìm yêu cầu tải báo giá',
				'not_found'     => 'Chưa có yêu cầu tải báo giá',
			),
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_admin_bar'   => false,
			'show_in_nav_menus'   => false,
			'show_in_rest'        => false,
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'supports'            => array( 'title' ),
			'capability_type'     => 'taf_quote_lead',
			'map_meta_cap'        => true,
			'capabilities'        => array(
				'edit_post'             => 'edit_taf_quote_lead',
				'read_post'             => 'read_taf_quote_lead',
				'delete_post'           => 'delete_taf_quote_lead',
				'edit_posts'            => 'manage_options',
				'edit_others_posts'     => 'manage_options',
				'publish_posts'         => 'manage_options',
				'read_private_posts'    => 'manage_options',
				'delete_posts'          => 'manage_options',
				'delete_private_posts'  => 'manage_options',
				'delete_published_posts'=> 'manage_options',
				'delete_others_posts'   => 'manage_options',
				'edit_private_posts'    => 'manage_options',
				'edit_published_posts'  => 'manage_options',
				'create_posts'          => 'manage_options',
			),
			'menu_icon' => 'dashicons-download',
		)
	);
}
add_action( 'init', 'taf_qd_register_lead_type' );

function taf_qd_lead_columns( $columns ) {
	return array(
		'cb'                 => isset( $columns['cb'] ) ? $columns['cb'] : '',
		'title'              => 'Yêu cầu',
		'taf_qd_phone'       => 'Số điện thoại',
		'taf_qd_product'     => 'Dòng sản phẩm',
		'taf_qd_downloaded'  => 'Tải xuống',
		'date'               => 'Ngày tạo',
	);
}
add_filter( 'manage_taf_quote_lead_posts_columns', 'taf_qd_lead_columns' );

function taf_qd_render_lead_column( $column, $post_id ) {
	if ( 'taf_qd_phone' === $column ) {
		echo esc_html( get_post_meta( $post_id, '_taf_qd_phone', true ) );
	} elseif ( 'taf_qd_product' === $column ) {
		echo esc_html( get_post_meta( $post_id, '_taf_qd_product_label', true ) );
	} elseif ( 'taf_qd_downloaded' === $column ) {
		$downloaded_at = get_post_meta( $post_id, '_taf_qd_downloaded_at', true );
		echo $downloaded_at ? esc_html( mysql2date( 'd/m/Y H:i', $downloaded_at ) ) : 'Chưa tải';
	}
}
add_action( 'manage_taf_quote_lead_posts_custom_column', 'taf_qd_render_lead_column', 10, 2 );

function taf_qd_resolve_pdf( $product ) {
	$catalog = taf_qd_catalog();
	if ( ! isset( $catalog[ $product ] ) ) {
		return false;
	}

	$uploads = wp_upload_dir();
	if ( empty( $uploads['basedir'] ) ) {
		return false;
	}

	$base = realpath( $uploads['basedir'] );
	$file = realpath( trailingslashit( $uploads['basedir'] ) . $catalog[ $product ]['path'] );
	if ( false === $base || false === $file || 0 !== strpos( $file, trailingslashit( $base ) ) || ! is_readable( $file ) ) {
		return false;
	}

	return array( $catalog[ $product ], $file );
}

function taf_qd_localize_home_script() {
	if ( ! is_front_page() || ! wp_script_is( 'taf-home', 'enqueued' ) ) {
		return;
	}

	wp_localize_script(
		'taf-home',
		'TAFQuoteConfig',
		array(
			'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
			'downloadUrl' => admin_url( 'admin-post.php' ),
			'nonce'       => wp_create_nonce( 'taf_qd_submit' ),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'taf_qd_localize_home_script', 30 );

function taf_qd_submit_request() {
	if ( 'POST' !== strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) ) {
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

	$phone_raw = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
	$phone     = preg_replace( '/D+/', '', $phone_raw );
	if ( 0 === strpos( $phone, '84' ) ) {
		$phone = '0' . substr( $phone, 2 );
	}
	if ( ! preg_match( '/^0d{9,10}$/', $phone ) ) {
		wp_send_json_error( array( 'message' => 'Vui lòng nhập số điện thoại hợp lệ.' ), 400 );
	}

	$product = isset( $_POST['product'] ) ? sanitize_key( wp_unslash( $_POST['product'] ) ) : '';
	$catalog = taf_qd_catalog();
	if ( ! isset( $catalog[ $product ] ) ) {
		wp_send_json_error( array( 'message' => 'Vui lòng chọn dòng sản phẩm có bảng giá tải xuống.' ), 400 );
	}

	$pdf = taf_qd_resolve_pdf( $product );
	if ( false === $pdf ) {
		wp_send_json_error( array( 'message' => 'Hiện chưa tìm thấy tệp báo giá. Vui lòng thử lại sau.' ), 404 );
	}

	$lead_id = wp_insert_post(
		array(
			'post_type'      => 'taf_quote_lead',
			'post_status'    => 'private',
			'post_title'     => 'Yêu cầu tải báo giá',
			'comment_status' => 'closed',
			'ping_status'    => 'closed',
		),
		true
	);
	if ( is_wp_error( $lead_id ) || ! $lead_id ) {
		wp_send_json_error( array( 'message' => 'Không thể lưu yêu cầu lúc này. Vui lòng thử lại.' ), 500 );
	}

	wp_update_post(
		array(
			'ID'         => (int) $lead_id,
			'post_title' => 'Yêu cầu tải báo giá #' . (int) $lead_id,
		)
	);
	update_post_meta( $lead_id, '_taf_qd_phone', $phone );
	update_post_meta( $lead_id, '_taf_qd_product', $product );
	update_post_meta( $lead_id, '_taf_qd_product_label', $catalog[ $product ]['label'] );
	update_post_meta( $lead_id, '_taf_qd_filename', $catalog[ $product ]['download_name'] );

	$token        = wp_generate_password( 48, false, false );
	$transient_id = 'taf_qd_download_' . hash( 'sha256', $token );
	$stored       = set_transient(
		$transient_id,
		array(
			'lead_id' => (int) $lead_id,
			'product' => $product,
		),
		10 * MINUTE_IN_SECONDS
	);
	if ( ! $stored ) {
		wp_send_json_error( array( 'message' => 'Không thể tạo liên kết tải lúc này. Vui lòng gửi lại yêu cầu.' ), 500 );
	}

	$download_url = add_query_arg(
		array(
			'action' => 'taf_qd_download',
			'token'  => $token,
		),
		admin_url( 'admin-post.php' )
	);

	wp_send_json_success( array( 'downloadUrl' => esc_url_raw( $download_url ) ) );
}
add_action( 'wp_ajax_taf_qd_submit', 'taf_qd_submit_request' );
add_action( 'wp_ajax_nopriv_taf_qd_submit', 'taf_qd_submit_request' );

function taf_qd_download_pdf() {
	$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
	if ( '' === $token ) {
		wp_die( 'Liên kết tải không hợp lệ. Vui lòng gửi lại yêu cầu.', 'Không thể tải báo giá', array( 'response' => 403 ) );
	}

	$transient_id = 'taf_qd_download_' . hash( 'sha256', $token );
	$grant        = get_transient( $transient_id );
	if ( ! is_array( $grant ) || empty( $grant['product'] ) || empty( $grant['lead_id'] ) ) {
		wp_die( 'Liên kết tải đã hết hạn hoặc đã được sử dụng. Vui lòng gửi lại số điện thoại.', 'Không thể tải báo giá', array( 'response' => 403 ) );
	}
	delete_transient( $transient_id );

	$pdf = taf_qd_resolve_pdf( sanitize_key( $grant['product'] ) );
	if ( false === $pdf ) {
		wp_die( 'Hiện chưa tìm thấy tệp báo giá. Vui lòng thử lại sau.', 'Không thể tải báo giá', array( 'response' => 404 ) );
	}

	$lead_id = (int) $grant['lead_id'];
	if ( 'taf_quote_lead' === get_post_type( $lead_id ) ) {
		update_post_meta( $lead_id, '_taf_qd_downloaded_at', current_time( 'mysql' ) );
	}

	while ( ob_get_level() > 0 ) {
		ob_end_clean();
	}
	nocache_headers();
	header( 'Content-Type: application/pdf' );
	header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $pdf[0]['download_name'] ) . '"' );
	header( 'Content-Length: ' . filesize( $pdf[1] ) );
	header( 'X-Content-Type-Options: nosniff' );
	readfile( $pdf[1] );
	exit;
}
add_action( 'admin_post_taf_qd_download', 'taf_qd_download_pdf' );
add_action( 'admin_post_nopriv_taf_qd_download', 'taf_qd_download_pdf' );