<?php
/**
 * Quản lý Admin Bar, nạp tài nguyên Frontend và trang quản trị trong WP Admin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TA_QIR_Admin {

	const PAGE_SLUG = 'ta-quick-image-replace';

	public static function init() {
		add_action( 'admin_bar_menu', array( __CLASS__, 'add_admin_bar_toggle' ), 999 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_frontend_assets' ) );
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_page' ) );
		add_action( 'admin_post_ta_qir_save_settings', array( __CLASS__, 'handle_save_settings' ) );
		add_action( 'admin_post_ta_qir_revert_one', array( __CLASS__, 'handle_revert_one' ) );
		add_action( 'admin_post_ta_qir_revert_all', array( __CLASS__, 'handle_revert_all' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( TA_QIR_PLUGIN_FILE ), array( __CLASS__, 'add_plugin_action_links' ) );
	}

	/**
	 * Thêm liên kết nhanh ở trang Plugins.
	 *
	 * @param array $links Danh sách link.
	 * @return array
	 */
	public static function add_plugin_action_links( $links ) {
		$url = admin_url( 'tools.php?page=' . self::PAGE_SLUG );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">Quản lý &amp; Cài đặt</a>' );
		return $links;
	}

	/**
	 * Thêm nút Bật/Tắt Thay ảnh nhanh trên thanh Admin Bar ở frontend.
	 *
	 * @param WP_Admin_Bar $wp_admin_bar Admin bar.
	 * @return void
	 */
	public static function add_admin_bar_toggle( $wp_admin_bar ) {
		if ( is_admin() || ! TA_QIR_Storage::current_user_can_replace() ) {
			return;
		}

		$settings   = TA_QIR_Storage::get_settings();
		$is_always  = ( 'always' === $settings['default_mode'] );
		$state_text = $is_always ? 'Bật' : 'Tắt';

		$wp_admin_bar->add_node(
			array(
				'id'    => 'ta-qir-toggle',
				'title' => '<span class="ab-icon dashicons dashicons-images-alt2" style="margin-top:2px;"></span><span class="ab-label">Thay ảnh nhanh: <strong id="ta-qir-ab-state">' . esc_html( $state_text ) . '</strong></span>',
				'href'  => '#ta-qir-toggle',
				'meta'  => array(
					'title' => 'Bật hoặc tắt chế độ rê chuột & Ctrl+V thay ảnh trực tiếp',
					'class' => 'ta-qir-ab-node',
				),
			)
		);
	}

	/**
	 * Nạp Media Library và script giao diện hover/paste ở frontend cho admin.
	 *
	 * @return void
	 */
	public static function enqueue_frontend_assets() {
		if ( is_admin() || ! TA_QIR_Storage::current_user_can_replace() ) {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_style(
			'ta-qir-frontend',
			TA_QIR_PLUGIN_URL . 'assets/frontend-editor.css',
			array( 'dashicons' ),
			TA_QIR_VERSION
		);

		wp_enqueue_script(
			'ta-qir-frontend',
			TA_QIR_PLUGIN_URL . 'assets/frontend-editor.js',
			array( 'jquery', 'media-editor' ),
			TA_QIR_VERSION,
			true
		);

		$settings      = TA_QIR_Storage::get_settings();
		$page_path     = TA_QIR_Storage::get_current_page_path();
		$active_map    = TA_QIR_Storage::get_effective_mappings_for_page( $page_path );
		$client_map    = array();

		foreach ( $active_map as $item ) {
			$new_key                = TA_QIR_Storage::normalize_image_url( $item['new_url'] );
			$client_map[ $new_key ] = array(
				'id'           => $item['id'],
				'originalUrl'  => $item['original_url'],
				'originalKey'  => $item['original_key'],
				'newUrl'       => $item['new_url'],
				'scope'        => $item['scope'],
				'attachmentId' => (int) $item['attachment_id'],
			);
		}

		wp_localize_script(
			'ta-qir-frontend',
			'taQirConfig',
			array(
				'restUrl'     => esc_url_raw( rest_url( TA_QIR_REST_API::NAMESPACE_SLUG ) ),
				'nonce'       => wp_create_nonce( 'wp_rest' ),
				'pagePath'    => $page_path,
				'homeUrl'     => home_url( '/' ),
				'defaultMode' => $settings['default_mode'],
				'supportBg'   => (bool) $settings['support_bg'],
				'minSize'     => (int) $settings['min_size'],
				'mappings'    => $client_map,
			)
		);
	}

	/**
	 * Đăng ký trang quản lý dưới menu Công cụ.
	 *
	 * @return void
	 */
	public static function register_admin_page() {
		add_management_page(
			'Thay ảnh nhanh',
			'Thay ảnh nhanh',
			'upload_files',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_admin_page' )
		);
	}

	/**
	 * Xử lý lưu cài đặt.
	 *
	 * @return void
	 */
	public static function handle_save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Không có quyền truy cập.' );
		}
		check_admin_referer( 'ta_qir_save_settings' );

		TA_QIR_Storage::save_settings(
			array(
				'default_mode'  => isset( $_POST['default_mode'] ) ? sanitize_key( wp_unslash( $_POST['default_mode'] ) ) : 'toggle',
				'support_bg'    => ! empty( $_POST['support_bg'] ),
				'min_size'      => isset( $_POST['min_size'] ) ? absint( $_POST['min_size'] ) : 24,
				'allowed_roles' => isset( $_POST['allowed_roles'] ) ? (array) wp_unslash( $_POST['allowed_roles'] ) : array( 'administrator' ),
			)
		);

		wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_SLUG, 'ta_msg' => 'saved' ), admin_url( 'tools.php' ) ) );
		exit;
	}

	/**
	 * Xử lý khôi phục 1 ảnh từ trang quản trị.
	 *
	 * @return void
	 */
	public static function handle_revert_one() {
		if ( ! TA_QIR_Storage::current_user_can_replace() ) {
			wp_die( 'Không có quyền truy cập.' );
		}
		$id = isset( $_GET['id'] ) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : '';
		check_admin_referer( 'ta_qir_revert_' . $id );

		TA_QIR_Storage::delete_mapping( $id );

		wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_SLUG, 'ta_msg' => 'reverted' ), admin_url( 'tools.php' ) ) );
		exit;
	}

	/**
	 * Xử lý khôi phục toàn bộ ảnh.
	 *
	 * @return void
	 */
	public static function handle_revert_all() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Không có quyền truy cập.' );
		}
		check_admin_referer( 'ta_qir_revert_all' );

		TA_QIR_Storage::delete_all_mappings();

		wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_SLUG, 'ta_msg' => 'reverted_all' ), admin_url( 'tools.php' ) ) );
		exit;
	}

	/**
	 * Render trang quản lý & cài đặt.
	 *
	 * @return void
	 */
	public static function render_admin_page() {
		if ( ! TA_QIR_Storage::current_user_can_replace() ) {
			wp_die( 'Bạn không có quyền truy cập trang này.' );
		}

		$mappings = TA_QIR_Storage::get_all_mappings();
		$settings = TA_QIR_Storage::get_settings();
		$msg      = isset( $_GET['ta_msg'] ) ? sanitize_key( wp_unslash( $_GET['ta_msg'] ) ) : '';
		?>
		<div class="wrap">
			<h1>Thay ảnh nhanh (TA Quick Image Replace)</h1>
			<p>Ra ngoài trang chủ hoặc bất kỳ trang nào trên website, bật nút <strong>Thay ảnh nhanh</strong> trên thanh Admin Bar để rê chuột thay ảnh bằng Media Library, dán URL hoặc <strong>Ctrl+V</strong> trực tiếp.</p>

			<?php if ( 'saved' === $msg ) : ?>
				<div class="notice notice-success is-dismissible"><p>Đã lưu cài đặt.</p></div>
			<?php elseif ( 'reverted' === $msg ) : ?>
				<div class="notice notice-success is-dismissible"><p>Đã khôi phục ảnh về bản gốc.</p></div>
			<?php elseif ( 'reverted_all' === $msg ) : ?>
				<div class="notice notice-success is-dismissible"><p>Đã khôi phục toàn bộ ảnh về bản gốc.</p></div>
			<?php endif; ?>

			<h2 style="margin-top:24px;">Danh sách ảnh đang được thay thế (<?php echo count( $mappings ); ?>)</h2>

			<?php if ( ! empty( $mappings ) && current_user_can( 'manage_options' ) ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:12px;" onsubmit="return confirm('Khôi phục toàn bộ ảnh về bản gốc?');">
					<input type="hidden" name="action" value="ta_qir_revert_all">
					<?php wp_nonce_field( 'ta_qir_revert_all' ); ?>
					<button type="submit" class="button button-secondary">Khôi phục tất cả về ảnh gốc</button>
				</form>
			<?php endif; ?>

			<table class="widefat striped">
				<thead>
					<tr>
						<th style="width:90px;">Ảnh mới</th>
						<th>Ảnh gốc</th>
						<th>Ảnh thay thế</th>
						<th style="width:140px;">Phạm vi</th>
						<th style="width:160px;">Cập nhật</th>
						<th style="width:110px;">Thao tác</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $mappings ) ) : ?>
						<tr>
							<td colspan="6">Chưa có ảnh nào được thay thế.</td>
						</tr>
					<?php else : ?>
						<?php foreach ( array_reverse( $mappings ) as $item ) : ?>
							<?php
							$user       = ! empty( $item['updated_by'] ) ? get_userdata( (int) $item['updated_by'] ) : null;
							$user_label = $user ? $user->display_name : '—';
							$revert_url = wp_nonce_url(
								add_query_arg(
									array(
										'action' => 'ta_qir_revert_one',
										'id'     => $item['id'],
									),
									admin_url( 'admin-post.php' )
								),
								'ta_qir_revert_' . $item['id']
							);
							?>
							<tr>
								<td>
									<img src="<?php echo esc_url( $item['new_url'] ); ?>" alt="" style="width:72px;height:48px;object-fit:cover;border-radius:4px;border:1px solid #dcdcde;">
								</td>
								<td style="word-break:break-all;">
									<code><?php echo esc_html( $item['original_url'] ); ?></code>
								</td>
								<td style="word-break:break-all;">
									<a href="<?php echo esc_url( $item['new_url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $item['new_url'] ); ?></a>
									<?php if ( ! empty( $item['attachment_id'] ) ) : ?>
										<br><small>Media ID: #<?php echo (int) $item['attachment_id']; ?></small>
									<?php endif; ?>
								</td>
								<td>
									<?php if ( 'page' === $item['scope'] ) : ?>
										<span class="dashicons dashicons-admin-page"></span> Chỉ trang:<br><code><?php echo esc_html( $item['page_path'] ); ?></code>
									<?php else : ?>
										<span class="dashicons dashicons-admin-site"></span> Toàn website
									<?php endif; ?>
								</td>
								<td>
									<?php echo esc_html( $item['updated_at'] ); ?><br>
									<small>Bởi: <?php echo esc_html( $user_label ); ?></small>
								</td>
								<td>
									<a href="<?php echo esc_url( $revert_url ); ?>" class="button button-small" onclick="return confirm('Khôi phục ảnh này về bản gốc?');">Khôi phục</a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<?php if ( current_user_can( 'manage_options' ) ) : ?>
				<h2 style="margin-top:32px;">Cài đặt</h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="ta_qir_save_settings">
					<?php wp_nonce_field( 'ta_qir_save_settings' ); ?>
					<table class="form-table">
						<tr>
							<th scope="row">Chế độ mặc định ở Frontend</th>
							<td>
								<label style="display:block;margin-bottom:6px;">
									<input type="radio" name="default_mode" value="toggle" <?php checked( $settings['default_mode'], 'toggle' ); ?>>
									<strong>Tắt mặc định</strong> — Bật/tắt bằng nút trên thanh Admin Bar (khuyên dùng để không vướng khi duyệt web)
								</label>
								<label style="display:block;">
									<input type="radio" name="default_mode" value="always" <?php checked( $settings['default_mode'], 'always' ); ?>>
									<strong>Luôn bật sẵn</strong> khi quản trị viên đăng nhập
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row">Hỗ trợ ảnh nền CSS</th>
							<td>
								<label>
									<input type="checkbox" name="support_bg" value="1" <?php checked( $settings['support_bg'] ); ?>>
									Cho phép thay cả ảnh nền (<code>background-image</code>) của banner/section
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row">Kích thước ảnh tối thiểu (px)</th>
							<td>
								<input type="number" name="min_size" value="<?php echo esc_attr( $settings['min_size'] ); ?>" min="0" max="500" class="small-text"> px
								<p class="description">Bỏ qua các icon nhỏ hơn kích thước này (ảnh bị lỗi/vỡ luôn được phép thay bất kể kích thước).</p>
							</td>
						</tr>
						<tr>
							<th scope="row">Vai trò được phép dùng</th>
							<td>
								<label style="margin-right:16px;">
									<input type="checkbox" checked disabled> Administrator (luôn bật)
								</label>
								<label>
									<input type="checkbox" name="allowed_roles[]" value="editor" <?php checked( in_array( 'editor', $settings['allowed_roles'], true ) ); ?>>
									Editor (Biên tập viên)
								</label>
							</td>
						</tr>
					</table>
					<?php submit_button( 'Lưu cài đặt' ); ?>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}
}
