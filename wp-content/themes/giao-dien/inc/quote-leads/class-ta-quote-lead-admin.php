<?php
/**
 * WordPress Admin UI for managing quote-download & contact phone leads.
 *
 * @package TopAsia
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TA_Quote_Lead_Admin {

	public const PAGE_SLUG = 'topasia-quote-leads';

	private static bool $booted = false;

	/**
	 * Register admin hooks once.
	 */
	public static function boot(): void {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_admin_actions' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'wp_ajax_ta_quote_update_customer', array( __CLASS__, 'ajax_update_customer' ) );
	}

	/**
	 * Register top-level "Khách báo giá" admin menu with new-lead badge.
	 */
	public static function register_menu(): void {
		$stats     = TA_Quote_Lead_Model::get_stats();
		$new_count = (int) ( $stats['new_customers'] ?? 0 );
		$label     = 'Khách báo giá';
		if ( $new_count > 0 ) {
			$label .= sprintf( ' <span class="awaiting-mod count-%d"><span class="pending-count">%s</span></span>', $new_count, number_format_i18n( $new_count ) );
		}

		add_menu_page(
			'Quản lý Khách để lại SĐT & Tải báo giá',
			$label,
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' ),
			'dashicons-phone',
			26
		);
	}

	/**
	 * Load WordPress Media uploader on the Settings tab and inline admin styles.
	 */
	public static function enqueue_assets( string $hook ): void {
		if ( 'toplevel_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}

		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'customers';
		if ( 'settings' === $tab ) {
			wp_enqueue_media();
		}
	}

	/**
	 * Handle CSV export, bulk updates, single deletions, and settings submissions before output.
	 */
	public static function handle_admin_actions(): void {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( self::PAGE_SLUG !== $page || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// 1. Export CSV (customers or logs).
		if ( isset( $_GET['ta_ql_export'] ) ) {
			check_admin_referer( 'ta_ql_export_csv' );
			$export_type = sanitize_key( wp_unslash( $_GET['ta_ql_export'] ) );
			self::stream_csv( $export_type );
		}

		// 2. Delete single customer.
		if ( isset( $_GET['ta_ql_delete_customer'] ) ) {
			$cid = absint( $_GET['ta_ql_delete_customer'] );
			check_admin_referer( 'ta_ql_delete_customer_' . $cid );
			TA_Quote_Lead_Model::delete_customer( $cid, true );
			self::redirect_with_notice( 'customers', 'deleted_customer' );
		}

		// 3. Delete single lead log row.
		if ( isset( $_GET['ta_ql_delete_lead'] ) ) {
			$lid = absint( $_GET['ta_ql_delete_lead'] );
			check_admin_referer( 'ta_ql_delete_lead_' . $lid );
			TA_Quote_Lead_Model::delete_lead( $lid );
			self::redirect_with_notice( 'logs', 'deleted_lead' );
		}

		// 4. POST actions (bulk status / bulk delete / save settings / fallback row save).
		if ( 'POST' !== strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? (string) $_SERVER['REQUEST_METHOD'] : '' ) ) {
			return;
		}

		$action = isset( $_POST['ta_ql_action'] ) ? sanitize_key( wp_unslash( $_POST['ta_ql_action'] ) ) : '';
		if ( 'save_settings' === $action ) {
			check_admin_referer( 'ta_ql_save_settings' );
			$catalog_input = array();
			if ( isset( $_POST['catalog_files'] ) && is_array( $_POST['catalog_files'] ) ) {
				foreach ( $_POST['catalog_files'] as $slug => $row ) {
					$slug_key                   = sanitize_key( (string) $slug );
					$catalog_input[ $slug_key ] = array(
						'attachment_id' => isset( $row['attachment_id'] ) ? absint( $row['attachment_id'] ) : 0,
						'rel_path'      => isset( $row['rel_path'] ) ? sanitize_text_field( wp_unslash( (string) $row['rel_path'] ) ) : '',
					);
				}
			}

			TA_Quote_Lead_Model::save_settings(
				array(
					'email_enabled' => ! empty( $_POST['email_enabled'] ),
					'notify_emails' => isset( $_POST['notify_emails'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['notify_emails'] ) ) : '',
					'capture_cf7'   => ! empty( $_POST['capture_cf7'] ),
					'catalog_files' => $catalog_input,
				)
			);
			self::redirect_with_notice( 'settings', 'saved_settings' );
		}

		if ( 'bulk_customers' === $action ) {
			check_admin_referer( 'ta_ql_bulk_customers' );
			$bulk_op = isset( $_POST['bulk_op'] ) ? sanitize_key( wp_unslash( $_POST['bulk_op'] ) ) : '';
			$ids     = isset( $_POST['customer_ids'] ) && is_array( $_POST['customer_ids'] ) ? array_map( 'absint', $_POST['customer_ids'] ) : array();
			if ( 'delete' === $bulk_op ) {
				foreach ( $ids as $cid ) {
					TA_Quote_Lead_Model::delete_customer( $cid, true );
				}
				self::redirect_with_notice( 'customers', 'bulk_deleted' );
			} elseif ( 0 === strpos( $bulk_op, 'status_' ) ) {
				$status = substr( $bulk_op, 7 );
				TA_Quote_Lead_Model::bulk_update_status( $ids, $status );
				self::redirect_with_notice( 'customers', 'bulk_updated' );
			}
		}
	}

	/**
	 * Inline AJAX handler to update customer status and note from the admin table.
	 */
	public static function ajax_update_customer(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Không đủ quyền.' ), 403 );
		}
		check_ajax_referer( 'ta_ql_inline_update', 'nonce' );

		$customer_id = isset( $_POST['customer_id'] ) ? absint( $_POST['customer_id'] ) : 0;
		$fields      = array();
		if ( isset( $_POST['status'] ) ) {
			$fields['status'] = sanitize_key( wp_unslash( (string) $_POST['status'] ) );
		}
		if ( isset( $_POST['note'] ) ) {
			$fields['note'] = sanitize_textarea_field( wp_unslash( (string) $_POST['note'] ) );
		}

		$ok = TA_Quote_Lead_Model::update_customer( $customer_id, $fields );
		if ( ! $ok ) {
			wp_send_json_error( array( 'message' => 'Không thể lưu thay đổi.' ), 400 );
		}

		$stats = TA_Quote_Lead_Model::get_stats();
		wp_send_json_success(
			array(
				'customer'     => TA_Quote_Lead_Model::get_customer( $customer_id ),
				'newCustomers' => (int) ( $stats['new_customers'] ?? 0 ),
			)
		);
	}

	/**
	 * Render the admin management screen.
	 */
	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		TA_Quote_Lead_Model::maybe_install();

		$tab   = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'customers';
		$tab   = in_array( $tab, array( 'customers', 'logs', 'settings' ), true ) ? $tab : 'customers';
		$stats = TA_Quote_Lead_Model::get_stats();

		self::render_styles();
		?>
		<div class="wrap ta-ql-wrap">
			<div class="ta-ql-header">
				<div>
					<h1>Quản lý Khách để lại SĐT &amp; Tải báo giá</h1>
					<p class="description">Lưu trữ tập trung toàn bộ số điện thoại khách hàng từ tính năng Tải báo giá trang chủ, trang /bao-gia/ và các form liên hệ trên website.</p>
				</div>
				<div class="ta-ql-header-actions">
					<?php if ( 'settings' !== $tab ) : ?>
						<?php
						$export_type = 'logs' === $tab ? 'logs' : 'customers';
						$export_url  = wp_nonce_url(
							add_query_arg(
								array(
									'page'         => self::PAGE_SLUG,
									'ta_ql_export' => $export_type,
									'search'       => isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['search'] ) ) : '',
									'status'       => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( (string) $_GET['status'] ) ) : '',
									'source'       => isset( $_GET['source'] ) ? sanitize_key( wp_unslash( (string) $_GET['source'] ) ) : '',
									'product'      => isset( $_GET['product'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['product'] ) ) : '',
								),
								admin_url( 'admin.php' )
							),
							'ta_ql_export_csv'
						);
						?>
						<a href="<?php echo esc_url( $export_url ); ?>" class="button button-primary">
							<span class="dashicons dashicons-download" style="vertical-align:middle;margin-top:-2px"></span>
							Xuất CSV (<?php echo 'logs' === $export_type ? 'Lịch sử' : 'Danh bạ SĐT'; ?>)
						</a>
					<?php endif; ?>
				</div>
			</div>

			<?php self::render_notice(); ?>

			<div class="ta-ql-kpis">
				<div class="ta-ql-kpi">
					<span class="ta-ql-kpi-lbl">Tổng SĐT liên hệ (duy nhất)</span>
					<strong class="ta-ql-kpi-val"><?php echo esc_html( number_format_i18n( (int) $stats['total_customers'] ) ); ?></strong>
					<small><?php echo esc_html( number_format_i18n( (int) $stats['downloaded_custs'] ) ); ?> khách đã tải PDF</small>
				</div>
				<div class="ta-ql-kpi ta-ql-kpi--alert">
					<span class="ta-ql-kpi-lbl">Chờ xử lý (Mới)</span>
					<strong class="ta-ql-kpi-val" data-ta-kpi-new><?php echo esc_html( number_format_i18n( (int) $stats['new_customers'] ) ); ?></strong>
					<small>Cần nhân viên gọi tư vấn</small>
				</div>
				<div class="ta-ql-kpi">
					<span class="ta-ql-kpi-lbl">Tổng lượt gửi yêu cầu</span>
					<strong class="ta-ql-kpi-val"><?php echo esc_html( number_format_i18n( (int) $stats['total_leads'] ) ); ?></strong>
					<small><?php echo esc_html( number_format_i18n( (int) $stats['downloaded_leads'] ) ); ?> lượt đã tải báo giá</small>
				</div>
				<div class="ta-ql-kpi">
					<span class="ta-ql-kpi-lbl">7 ngày gần nhất / Hôm nay</span>
					<strong class="ta-ql-kpi-val"><?php echo esc_html( number_format_i18n( (int) $stats['week_leads'] ) ); ?></strong>
					<small>Hôm nay: <?php echo esc_html( number_format_i18n( (int) $stats['today_leads'] ) ); ?> lượt</small>
				</div>
			</div>

			<nav class="nav-tab-wrapper wp-clearfix" style="margin-bottom:16px">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=customers' ) ); ?>" class="nav-tab <?php echo 'customers' === $tab ? 'nav-tab-active' : ''; ?>">
					Danh bạ SĐT liên hệ (<?php echo esc_html( number_format_i18n( (int) $stats['total_customers'] ) ); ?>)
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=logs' ) ); ?>" class="nav-tab <?php echo 'logs' === $tab ? 'nav-tab-active' : ''; ?>">
					Lịch sử gửi &amp; Tải PDF (<?php echo esc_html( number_format_i18n( (int) $stats['total_leads'] ) ); ?>)
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=settings' ) ); ?>" class="nav-tab <?php echo 'settings' === $tab ? 'nav-tab-active' : ''; ?>">
					Cài đặt PDF &amp; Email
				</a>
			</nav>

			<?php
			if ( 'customers' === $tab ) {
				self::render_customers_tab();
			} elseif ( 'logs' === $tab ) {
				self::render_logs_tab();
			} else {
				self::render_settings_tab();
			}
			?>
		</div>
		<?php
	}

	/**
	 * Render Tab 1: Deduplicated Customer Contacts with inline status & note updates.
	 */
	private static function render_customers_tab(): void {
		$search    = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['search'] ) ) : '';
		$status    = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( (string) $_GET['status'] ) ) : '';
		$source    = isset( $_GET['source'] ) ? sanitize_key( wp_unslash( (string) $_GET['source'] ) ) : '';
		$product   = isset( $_GET['product'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['product'] ) ) : '';
		$date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['date_from'] ) ) : '';
		$date_to   = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['date_to'] ) ) : '';
		$paged     = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$per_page  = 25;

		$result = TA_Quote_Lead_Model::get_customers(
			array(
				'search'    => $search,
				'status'    => $status,
				'source'    => $source,
				'product'   => $product,
				'date_from' => $date_from,
				'date_to'   => $date_to,
				'paged'     => $paged,
				'per_page'  => $per_page,
			)
		);

		$items         = $result['items'];
		$total         = (int) $result['total'];
		$statuses      = TA_Quote_Lead_Model::get_statuses();
		$source_labels = TA_Quote_Lead_Model::get_source_labels();
		$inline_nonce  = wp_create_nonce( 'ta_ql_inline_update' );
		?>
		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="ta-ql-filters">
			<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>">
			<input type="hidden" name="tab" value="customers">
			<input type="search" name="search" value="<?php echo esc_attr( $search ); ?>" placeholder="Tìm SĐT, tên, email, ghi chú...">
			<select name="status">
				<option value="">-- Tất cả trạng thái --</option>
				<?php foreach ( $statuses as $k => $lbl ) : ?>
					<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $status, $k ); ?>><?php echo esc_html( $lbl ); ?></option>
				<?php endforeach; ?>
			</select>
			<select name="product">
				<option value="">-- Dòng sản phẩm --</option>
				<?php foreach ( array( 'Asia Gold', 'Asia Pearl', 'Asia Lite', 'Cả 3 dòng' ) as $p ) : ?>
					<option value="<?php echo esc_attr( $p ); ?>" <?php selected( $product, $p ); ?>><?php echo esc_html( $p ); ?></option>
				<?php endforeach; ?>
			</select>
			<select name="source">
				<option value="">-- Tất cả nguồn --</option>
				<?php foreach ( $source_labels as $k => $lbl ) : ?>
					<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $source, $k ); ?>><?php echo esc_html( $lbl ); ?></option>
				<?php endforeach; ?>
			</select>
			<input type="date" name="date_from" value="<?php echo esc_attr( $date_from ); ?>" title="Từ ngày">
			<input type="date" name="date_to" value="<?php echo esc_attr( $date_to ); ?>" title="Đến ngày">
			<button type="submit" class="button">Lọc</button>
			<?php if ( $search || $status || $source || $product || $date_from || $date_to ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=customers' ) ); ?>" class="button">Xóa lọc</a>
			<?php endif; ?>
		</form>

		<form method="post" action="">
			<?php wp_nonce_field( 'ta_ql_bulk_customers' ); ?>
			<input type="hidden" name="ta_ql_action" value="bulk_customers">
			<div class="tablenav top">
				<div class="alignleft actions bulkactions">
					<select name="bulk_op">
						<option value="">Hành động hàng loạt</option>
						<?php foreach ( $statuses as $k => $lbl ) : ?>
							<option value="<?php echo esc_attr( 'status_' . $k ); ?>">Đổi trạng thái → <?php echo esc_html( $lbl ); ?></option>
						<?php endforeach; ?>
						<option value="delete">Xóa vĩnh viễn</option>
					</select>
					<button type="submit" class="button action" onclick="return this.form.bulk_op.value !== 'delete' || confirm('Xóa các khách hàng đã chọn và lịch sử của họ?');">Áp dụng</button>
				</div>
				<?php self::render_pagination( $total, $per_page, $paged ); ?>
			</div>

			<table class="wp-list-table widefat fixed striped ta-ql-table">
				<thead>
					<tr>
						<td class="manage-column column-cb check-column"><input type="checkbox" id="ta-ql-check-all"></td>
						<th style="width:170px">Số điện thoại</th>
						<th style="width:160px">Khách hàng</th>
						<th style="width:165px">Sản phẩm quan tâm</th>
						<th style="width:140px">Nguồn / Tương tác</th>
						<th style="width:145px">Trạng thái</th>
						<th>Ghi chú xử lý</th>
						<th style="width:130px">Thời gian</th>
						<th style="width:110px">Thao tác</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $items ) ) : ?>
						<tr><td colspan="9">Chưa có số điện thoại liên hệ nào phù hợp.</td></tr>
					<?php else : ?>
						<?php foreach ( $items as $row ) : ?>
							<?php
							$cid        = (int) $row['id'];
							$phone_norm = (string) ( $row['phone_norm'] ?: $row['phone'] );
							$zalo_phone = TA_Quote_Lead_Model::normalize_phone( $phone_norm );
							$cur_status = (string) ( $row['status'] ?? 'new' );
							$src_key    = (string) ( $row['last_source'] ?? 'home_quote_modal' );
							$src_label  = $source_labels[ $src_key ] ?? $src_key;
							$del_url    = wp_nonce_url(
								add_query_arg(
									array(
										'page'                  => self::PAGE_SLUG,
										'ta_ql_delete_customer' => $cid,
									),
									admin_url( 'admin.php' )
								),
								'ta_ql_delete_customer_' . $cid
							);
							$logs_url   = add_query_arg(
								array(
									'page'        => self::PAGE_SLUG,
									'tab'         => 'logs',
									'customer_id' => $cid,
								),
								admin_url( 'admin.php' )
							);
							?>
							<tr data-customer-row="<?php echo esc_attr( (string) $cid ); ?>">
								<th scope="row" class="check-column">
									<input type="checkbox" name="customer_ids[]" value="<?php echo esc_attr( (string) $cid ); ?>">
								</th>
								<td>
									<strong class="ta-ql-phone"><?php echo esc_html( TA_Quote_Lead_Model::format_phone_display( $phone_norm ) ); ?></strong>
									<div class="ta-ql-quick-contact">
										<a href="tel:<?php echo esc_attr( $zalo_phone ); ?>">Gọi</a>
										<a href="<?php echo esc_url( 'https://zalo.me/' . $zalo_phone ); ?>" target="_blank" rel="noopener noreferrer">Zalo</a>
									</div>
								</td>
								<td>
									<?php if ( ! empty( $row['full_name'] ) ) : ?>
										<div><strong><?php echo esc_html( (string) $row['full_name'] ); ?></strong></div>
									<?php else : ?>
										<span class="ta-ql-muted">Chưa có tên</span>
									<?php endif; ?>
									<?php if ( ! empty( $row['email'] ) ) : ?>
										<div class="ta-ql-small"><?php echo esc_html( (string) $row['email'] ); ?></div>
									<?php endif; ?>
									<?php if ( ! empty( $row['customer_type'] ) ) : ?>
										<div class="ta-ql-badge ta-ql-badge--soft"><?php echo esc_html( (string) $row['customer_type'] ); ?></div>
									<?php endif; ?>
								</td>
								<td>
									<?php
									$prods = array_filter( array_map( 'trim', explode( ',', (string) ( $row['products_list'] ?: $row['last_product'] ) ) ) );
									foreach ( $prods as $p ) {
										echo '<span class="ta-ql-badge">' . esc_html( $p ) . '</span> ';
									}
									?>
								</td>
								<td>
									<div class="ta-ql-small"><strong><?php echo esc_html( $src_label ); ?></strong></div>
									<div class="ta-ql-small ta-ql-muted">
										<?php echo esc_html( (string) (int) $row['total_requests'] ); ?> lượt gửi ·
										<?php echo esc_html( (string) (int) $row['total_downloads'] ); ?> tải PDF
									</div>
								</td>
								<td>
									<select class="ta-ql-status-select ta-ql-status--<?php echo esc_attr( $cur_status ); ?>" data-ta-status="<?php echo esc_attr( (string) $cid ); ?>">
										<?php foreach ( $statuses as $k => $lbl ) : ?>
											<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $cur_status, $k ); ?>><?php echo esc_html( $lbl ); ?></option>
										<?php endforeach; ?>
									</select>
								</td>
								<td>
									<div class="ta-ql-note-box">
										<textarea rows="2" class="ta-ql-note-input" data-ta-note="<?php echo esc_attr( (string) $cid ); ?>" placeholder="Nhập ghi chú tư vấn..."><?php echo esc_textarea( (string) ( $row['note'] ?? '' ) ); ?></textarea>
										<button type="button" class="button button-small" data-ta-save-row="<?php echo esc_attr( (string) $cid ); ?>">Lưu</button>
										<span class="ta-ql-save-msg" data-ta-msg="<?php echo esc_attr( (string) $cid ); ?>"></span>
									</div>
								</td>
								<td>
									<div class="ta-ql-small" title="Lần gần nhất"><strong><?php echo esc_html( mysql2date( 'd/m/Y H:i', (string) $row['last_seen_at'] ) ); ?></strong></div>
									<?php if ( $row['first_seen_at'] !== $row['last_seen_at'] ) : ?>
										<div class="ta-ql-small ta-ql-muted" title="Lần đầu">Đầu: <?php echo esc_html( mysql2date( 'd/m/Y', (string) $row['first_seen_at'] ) ); ?></div>
									<?php endif; ?>
								</td>
								<td>
									<a href="<?php echo esc_url( $logs_url ); ?>" class="button button-small">Lịch sử</a>
									<a href="<?php echo esc_url( $del_url ); ?>" class="button-link-delete" style="display:inline-block;margin-top:6px" onclick="return confirm('Xóa SĐT này và toàn bộ lịch sử liên quan?');">Xóa</a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</form>

		<script>
		(function(){
			var checkAll = document.getElementById('ta-ql-check-all');
			if (checkAll) {
				checkAll.addEventListener('change', function(){
					document.querySelectorAll('input[name="customer_ids[]"]').forEach(function(cb){ cb.checked = checkAll.checked; });
				});
			}
			var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			var nonce = <?php echo wp_json_encode( $inline_nonce ); ?>;

			function saveRow(cid) {
				var statusEl = document.querySelector('[data-ta-status="' + cid + '"]');
				var noteEl = document.querySelector('[data-ta-note="' + cid + '"]');
				var msgEl = document.querySelector('[data-ta-msg="' + cid + '"]');
				var fd = new FormData();
				fd.append('action', 'ta_quote_update_customer');
				fd.append('nonce', nonce);
				fd.append('customer_id', cid);
				if (statusEl) fd.append('status', statusEl.value);
				if (noteEl) fd.append('note', noteEl.value);

				if (msgEl) msgEl.textContent = 'Đang lưu...';
				fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd })
					.then(function(r){ return r.json(); })
					.then(function(res){
						if (res && res.success) {
							if (statusEl) {
								statusEl.className = 'ta-ql-status-select ta-ql-status--' + statusEl.value;
							}
							if (msgEl) {
								msgEl.textContent = 'Đã lưu';
								setTimeout(function(){ msgEl.textContent = ''; }, 2000);
							}
							var kpi = document.querySelector('[data-ta-kpi-new]');
							if (kpi && res.data && typeof res.data.newCustomers !== 'undefined') {
								kpi.textContent = res.data.newCustomers;
							}
						} else if (msgEl) {
							msgEl.textContent = 'Lỗi lưu!';
						}
					})
					.catch(function(){ if (msgEl) msgEl.textContent = 'Lỗi kết nối!'; });
			}

			document.querySelectorAll('[data-ta-status]').forEach(function(sel){
				sel.addEventListener('change', function(){ saveRow(sel.getAttribute('data-ta-status')); });
			});
			document.querySelectorAll('[data-ta-save-row]').forEach(function(btn){
				btn.addEventListener('click', function(){ saveRow(btn.getAttribute('data-ta-save-row')); });
			});
		})();
		</script>
		<?php
	}

	/**
	 * Render Tab 2: Detailed submission & PDF download event log.
	 */
	private static function render_logs_tab(): void {
		$search      = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['search'] ) ) : '';
		$source      = isset( $_GET['source'] ) ? sanitize_key( wp_unslash( (string) $_GET['source'] ) ) : '';
		$customer_id = isset( $_GET['customer_id'] ) ? absint( $_GET['customer_id'] ) : 0;
		$paged       = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$per_page    = 30;

		$result = TA_Quote_Lead_Model::get_leads(
			array(
				'search'      => $search,
				'source'      => $source,
				'customer_id' => $customer_id,
				'paged'       => $paged,
				'per_page'    => $per_page,
			)
		);

		$items         = $result['items'];
		$total         = (int) $result['total'];
		$source_labels = TA_Quote_Lead_Model::get_source_labels();
		?>
		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="ta-ql-filters">
			<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>">
			<input type="hidden" name="tab" value="logs">
			<?php if ( $customer_id ) : ?>
				<input type="hidden" name="customer_id" value="<?php echo esc_attr( (string) $customer_id ); ?>">
			<?php endif; ?>
			<input type="search" name="search" value="<?php echo esc_attr( $search ); ?>" placeholder="Tìm SĐT, tên, sản phẩm, URL...">
			<select name="source">
				<option value="">-- Tất cả nguồn --</option>
				<?php foreach ( $source_labels as $k => $lbl ) : ?>
					<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $source, $k ); ?>><?php echo esc_html( $lbl ); ?></option>
				<?php endforeach; ?>
			</select>
			<button type="submit" class="button">Lọc</button>
			<?php if ( $search || $source || $customer_id ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=logs' ) ); ?>" class="button">Xem tất cả</a>
			<?php endif; ?>
		</form>

		<?php self::render_pagination( $total, $per_page, $paged ); ?>

		<table class="wp-list-table widefat fixed striped ta-ql-table">
			<thead>
				<tr>
					<th style="width:60px">ID</th>
					<th style="width:140px">Số điện thoại</th>
					<th style="width:145px">Sản phẩm</th>
					<th style="width:170px">Nguồn &amp; Trang gửi</th>
					<th style="width:175px">Trạng thái tải PDF</th>
					<th>Chi tiết / Ghi chú</th>
					<th style="width:120px">IP</th>
					<th style="width:130px">Thời gian</th>
					<th style="width:70px">Xóa</th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $items ) ) : ?>
					<tr><td colspan="9">Chưa có dữ liệu lịch sử.</td></tr>
				<?php else : ?>
					<?php foreach ( $items as $row ) : ?>
						<?php
						$lid       = (int) $row['id'];
						$src_key   = (string) ( $row['source'] ?? 'home_quote_modal' );
						$src_label = $source_labels[ $src_key ] ?? $src_key;
						$del_url   = wp_nonce_url(
							add_query_arg(
								array(
									'page'              => self::PAGE_SLUG,
									'tab'               => 'logs',
									'ta_ql_delete_lead' => $lid,
								),
								admin_url( 'admin.php' )
							),
							'ta_ql_delete_lead_' . $lid
						);
						?>
						<tr>
							<td>#<?php echo esc_html( (string) $lid ); ?></td>
							<td><strong><?php echo esc_html( TA_Quote_Lead_Model::format_phone_display( (string) ( $row['phone_norm'] ?: $row['phone'] ) ) ); ?></strong></td>
							<td><span class="ta-ql-badge"><?php echo esc_html( (string) $row['product'] ); ?></span></td>
							<td>
								<div><?php echo esc_html( $src_label ); ?></div>
								<?php if ( ! empty( $row['page_url'] ) ) : ?>
									<div class="ta-ql-small ta-ql-muted" style="word-break:break-all"><?php echo esc_html( (string) wp_parse_url( (string) $row['page_url'], PHP_URL_PATH ) ); ?></div>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( ! empty( $row['downloaded_at'] ) ) : ?>
									<span class="ta-ql-badge ta-ql-badge--ok">Đã tải (<?php echo esc_html( (string) max( 1, (int) $row['download_count'] ) ); ?> lần)</span>
									<div class="ta-ql-small ta-ql-muted"><?php echo esc_html( mysql2date( 'd/m/Y H:i', (string) $row['downloaded_at'] ) ); ?></div>
									<?php if ( ! empty( $row['downloaded_lines'] ) ) : ?>
										<div class="ta-ql-small"><?php echo esc_html( (string) $row['downloaded_lines'] ); ?></div>
									<?php endif; ?>
								<?php else : ?>
									<span class="ta-ql-muted">Chưa tải / Form liên hệ</span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( ! empty( $row['full_name'] ) || ! empty( $row['email'] ) ) : ?>
									<div class="ta-ql-small"><strong><?php echo esc_html( trim( $row['full_name'] . ' ' . $row['email'] ) ); ?></strong></div>
								<?php endif; ?>
								<?php if ( ! empty( $row['note'] ) ) : ?>
									<div class="ta-ql-small"><?php echo esc_html( (string) $row['note'] ); ?></div>
								<?php endif; ?>
							</td>
							<td class="ta-ql-small ta-ql-muted"><?php echo esc_html( (string) ( $row['ip_address'] ?: '—' ) ); ?></td>
							<td class="ta-ql-small"><?php echo esc_html( mysql2date( 'd/m/Y H:i', (string) $row['created_at'] ) ); ?></td>
							<td><a href="<?php echo esc_url( $del_url ); ?>" class="button-link-delete" onclick="return confirm('Xóa dòng lịch sử này?');">Xóa</a></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render Tab 3: PDF file mapping & notification settings.
	 */
	private static function render_settings_tab(): void {
		$settings = TA_Quote_Lead_Model::get_settings();
		$catalog  = TA_Quote_Lead_Model::get_catalog();
		?>
		<form method="post" action="" class="ta-ql-settings-card">
			<?php wp_nonce_field( 'ta_ql_save_settings' ); ?>
			<input type="hidden" name="ta_ql_action" value="save_settings">

			<h2>1. Cấu hình tệp Báo giá PDF theo từng dòng sản phẩm</h2>
			<p class="description">Chọn trực tiếp file PDF từ Thư viện Media hoặc nhập đường dẫn tương đối trong <code>wp-content/uploads/</code>. Hệ thống vẫn tự động dò file dự phòng nếu chưa gán thủ công.</p>

			<table class="form-table">
				<?php foreach ( $catalog as $slug => $meta ) : ?>
					<?php
					$cfg      = $settings['catalog_files'][ $slug ] ?? array( 'attachment_id' => 0, 'rel_path' => '' );
					$resolved = TA_Quote_Lead_Model::resolve_pdf( $slug );
					?>
					<tr>
						<th scope="row"><?php echo esc_html( (string) $meta['label'] ); ?></th>
						<td>
							<div class="ta-ql-pdf-picker">
								<input type="hidden" name="catalog_files[<?php echo esc_attr( $slug ); ?>][attachment_id]" value="<?php echo esc_attr( (string) (int) $cfg['attachment_id'] ); ?>" data-ta-att-id="<?php echo esc_attr( $slug ); ?>">
								<input type="text" class="regular-text" name="catalog_files[<?php echo esc_attr( $slug ); ?>][rel_path]" value="<?php echo esc_attr( (string) $cfg['rel_path'] ); ?>" placeholder="VD: 2026/11/Bao-gia-dong-Asia-Gold-2.pdf" data-ta-rel-path="<?php echo esc_attr( $slug ); ?>">
								<button type="button" class="button" data-ta-pick-pdf="<?php echo esc_attr( $slug ); ?>">Chọn PDF từ Media</button>
							</div>
							<p class="description">
								<?php if ( false !== $resolved ) : ?>
									<span style="color:#15803d;font-weight:600">&#10003; Đang phục vụ file:</span> <code><?php echo esc_html( $resolved[2] ); ?></code>
								<?php else : ?>
									<span style="color:#b91c1c;font-weight:600">&#9888; Chưa tìm thấy file PDF trên máy chủ này.</span> (Số điện thoại khách hàng vẫn được lưu đầy đủ khi gửi form.)
								<?php endif; ?>
							</p>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>

			<hr>
			<h2>2. Thông báo Email &amp; Thu thập đồng bộ toàn website</h2>
			<table class="form-table">
				<tr>
					<th scope="row">Gửi email khi có khách mới</th>
					<td>
						<label>
							<input type="checkbox" name="email_enabled" value="1" <?php checked( ! empty( $settings['email_enabled'] ) ); ?>>
							Tự động gửi email thông báo khi khách hàng để lại SĐT tải báo giá
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ta-ql-emails">Email nhân viên nhận tin</label></th>
					<td>
						<textarea id="ta-ql-emails" name="notify_emails" rows="4" class="large-text code" placeholder="Mỗi email một dòng hoặc ngăn cách bằng dấu phẩy"><?php echo esc_textarea( implode( "\n", (array) $settings['notify_emails'] ) ); ?></textarea>
						<p class="description">Mỗi dòng một địa chỉ email. Hệ thống bỏ qua gửi email khi đang chạy trên localhost.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Đồng bộ Contact Form 7</th>
					<td>
						<label>
							<input type="checkbox" name="capture_cf7" value="1" <?php checked( ! empty( $settings['capture_cf7'] ) ); ?>>
							Tự động lưu thêm số điện thoại từ các form Contact Form 7 (Báo giá B2B, Tư vấn, Liên hệ) vào cùng danh bạ này
						</label>
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="submit" class="button button-primary">Lưu cấu hình</button>
			</p>
		</form>

		<script>
		(function(){
			if (typeof wp === 'undefined' || !wp.media) return;
			document.querySelectorAll('[data-ta-pick-pdf]').forEach(function(btn){
				btn.addEventListener('click', function(){
					var slug = btn.getAttribute('data-ta-pick-pdf');
					var frame = wp.media({
						title: 'Chọn file báo giá PDF',
						library: { type: 'application/pdf' },
						button: { text: 'Sử dụng file này' },
						multiple: false
					});
					frame.on('select', function(){
						var att = frame.state().get('selection').first().toJSON();
						var idEl = document.querySelector('[data-ta-att-id="' + slug + '"]');
						var pathEl = document.querySelector('[data-ta-rel-path="' + slug + '"]');
						if (idEl) idEl.value = att.id || 0;
						if (pathEl && att.url) {
							var idx = att.url.indexOf('/wp-content/uploads/');
							pathEl.value = idx >= 0 ? att.url.slice(idx + '/wp-content/uploads/'.length) : (att.filename || '');
						}
					});
					frame.open();
				});
			});
		})();
		</script>
		<?php
	}

	/**
	 * Stream UTF-8 BOM CSV file safely to Excel.
	 */
	private static function stream_csv( string $export_type ): void {
		$filters = array(
			'search'   => isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['search'] ) ) : '',
			'status'   => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( (string) $_GET['status'] ) ) : '',
			'source'   => isset( $_GET['source'] ) ? sanitize_key( wp_unslash( (string) $_GET['source'] ) ) : '',
			'product'  => isset( $_GET['product'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['product'] ) ) : '',
			'per_page' => 10000,
			'paged'    => 1,
		);

		$statuses      = TA_Quote_Lead_Model::get_statuses();
		$source_labels = TA_Quote_Lead_Model::get_source_labels();
		$filename      = 'logs' === $export_type
			? 'topasia-quote-logs-' . wp_date( 'Y-m-d' ) . '.csv'
			: 'topasia-quote-customers-' . wp_date( 'Y-m-d' ) . '.csv';

		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );

		$out = fopen( 'php://output', 'w' );
		if ( false === $out ) {
			exit;
		}
		fwrite( $out, "\xEF\xBB\xBF" );

		if ( 'logs' === $export_type ) {
			fputcsv( $out, array( 'ID', 'So dien thoai', 'Ho ten', 'Email', 'San pham', 'Nguon', 'Trang gui', 'Da tai PDF', 'So lan tai', 'IP', 'Thoi gian' ) );
			$res = TA_Quote_Lead_Model::get_leads( $filters );
			foreach ( $res['items'] as $r ) {
				fputcsv(
					$out,
					array(
						(int) $r['id'],
						(string) ( $r['phone_norm'] ?: $r['phone'] ),
						self::csv_safe( (string) ( $r['full_name'] ?? '' ) ),
						self::csv_safe( (string) ( $r['email'] ?? '' ) ),
						self::csv_safe( (string) ( $r['product'] ?? '' ) ),
						self::csv_safe( $source_labels[ $r['source'] ] ?? (string) $r['source'] ),
						self::csv_safe( (string) ( $r['page_url'] ?? '' ) ),
						! empty( $r['downloaded_at'] ) ? (string) $r['downloaded_at'] : 'Chua tai',
						(int) ( $r['download_count'] ?? 0 ),
						(string) ( $r['ip_address'] ?? '' ),
						(string) ( $r['created_at'] ?? '' ),
					)
				);
			}
		} else {
			fputcsv( $out, array( 'ID', 'So dien thoai', 'Ho ten', 'Email', 'Phan loai', 'San pham quan tam', 'Nguon', 'So lan gui', 'So lan tai PDF', 'Trang thai', 'Ghi chu', 'Lan dau', 'Lan gan nhat' ) );
			$res = TA_Quote_Lead_Model::get_customers( $filters );
			foreach ( $res['items'] as $r ) {
				fputcsv(
					$out,
					array(
						(int) $r['id'],
						(string) ( $r['phone_norm'] ?: $r['phone'] ),
						self::csv_safe( (string) ( $r['full_name'] ?? '' ) ),
						self::csv_safe( (string) ( $r['email'] ?? '' ) ),
						self::csv_safe( (string) ( $r['customer_type'] ?? '' ) ),
						self::csv_safe( (string) ( $r['products_list'] ?: $r['last_product'] ) ),
						self::csv_safe( $source_labels[ $r['last_source'] ] ?? (string) $r['last_source'] ),
						(int) ( $r['total_requests'] ?? 1 ),
						(int) ( $r['total_downloads'] ?? 0 ),
						$statuses[ $r['status'] ] ?? (string) $r['status'],
						self::csv_safe( (string) ( $r['note'] ?? '' ) ),
						(string) ( $r['first_seen_at'] ?? '' ),
						(string) ( $r['last_seen_at'] ?? '' ),
					)
				);
			}
		}

		fclose( $out );
		exit;
	}

	/**
	 * Neutralize spreadsheet formula injection characters in untrusted text fields.
	 */
	private static function csv_safe( string $value ): string {
		if ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
			return "'" . $value;
		}
		return $value;
	}

	/**
	 * Render WordPress pagination links.
	 */
	private static function render_pagination( int $total, int $per_page, int $paged ): void {
		$total_pages = (int) ceil( $total / max( 1, $per_page ) );
		if ( $total_pages <= 1 ) {
			return;
		}
		$links = paginate_links(
			array(
				'base'      => add_query_arg( 'paged', '%#%' ),
				'format'    => '',
				'prev_text' => '&laquo;',
				'next_text' => '&raquo;',
				'total'     => $total_pages,
				'current'   => $paged,
			)
		);
		if ( $links ) {
			echo '<div class="tablenav-pages"><span class="displaying-num">' . esc_html( number_format_i18n( $total ) ) . ' mục</span> ' . wp_kses_post( $links ) . '</div>';
		}
	}

	/**
	 * Redirect back to admin tab with a status notice code.
	 */
	private static function redirect_with_notice( string $tab, string $notice ): void {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => self::PAGE_SLUG,
					'tab'          => $tab,
					'ta_ql_notice' => $notice,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Print admin notices for completed actions.
	 */
	private static function render_notice(): void {
		$code = isset( $_GET['ta_ql_notice'] ) ? sanitize_key( wp_unslash( (string) $_GET['ta_ql_notice'] ) ) : '';
		$map  = array(
			'saved_settings'   => 'Đã lưu cấu hình tệp báo giá PDF & email thông báo.',
			'deleted_customer' => 'Đã xóa khách hàng và lịch sử liên quan.',
			'deleted_lead'     => 'Đã xóa dòng lịch sử.',
			'bulk_deleted'     => 'Đã xóa các khách hàng được chọn.',
			'bulk_updated'     => 'Đã cập nhật trạng thái cho các khách hàng được chọn.',
		);
		if ( isset( $map[ $code ] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $map[ $code ] ) . '</p></div>';
		}
	}

	/**
	 * Scoped admin CSS for KPI cards, filters, status pills, and inline editor.
	 */
	private static function render_styles(): void {
		?>
		<style>
			.ta-ql-header { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; flex-wrap:wrap; margin-bottom:16px; }
			.ta-ql-header h1 { margin-bottom:4px; }
			.ta-ql-kpis { display:grid; grid-template-columns:repeat(4, minmax(180px, 1fr)); gap:12px; margin:14px 0 18px; }
			@media (max-width:960px) { .ta-ql-kpis { grid-template-columns:repeat(2, 1fr); } }
			.ta-ql-kpi { background:#fff; border:1px solid #dcdcde; border-left:4px solid #142B49; border-radius:6px; padding:14px 16px; }
			.ta-ql-kpi--alert { border-left-color:#D92F35; }
			.ta-ql-kpi-lbl { display:block; font-size:12px; color:#646970; font-weight:600; text-transform:uppercase; letter-spacing:.03em; }
			.ta-ql-kpi-val { display:block; font-size:26px; line-height:1.2; color:#142B49; margin:4px 0; }
			.ta-ql-kpi--alert .ta-ql-kpi-val { color:#D92F35; }
			.ta-ql-kpi small { color:#646970; }
			.ta-ql-filters { display:flex; flex-wrap:wrap; gap:8px; align-items:center; margin-bottom:12px; background:#fff; padding:10px 12px; border:1px solid #dcdcde; border-radius:6px; }
			.ta-ql-phone { font-size:14px; color:#142B49; font-variant-numeric:tabular-nums; }
			.ta-ql-quick-contact { display:flex; gap:6px; margin-top:4px; }
			.ta-ql-quick-contact a { display:inline-block; padding:2px 8px; font-size:11px; font-weight:600; border-radius:4px; background:#f0f4f8; color:#142B49; text-decoration:none; }
			.ta-ql-quick-contact a:hover { background:#142B49; color:#fff; }
			.ta-ql-badge { display:inline-block; padding:2px 8px; margin:1px 2px 2px 0; border-radius:4px; font-size:11.5px; font-weight:600; background:#eef2f7; color:#142B49; }
			.ta-ql-badge--soft { background:#f6f7f7; color:#50575e; font-weight:500; }
			.ta-ql-badge--ok { background:#dcfce7; color:#166534; }
			.ta-ql-small { font-size:12px; line-height:1.4; }
			.ta-ql-muted { color:#646970; }
			.ta-ql-status-select { width:100%; font-weight:600; border-radius:4px; }
			.ta-ql-status--new { border-color:#D92F35; color:#b91c1c; background:#fef2f2; }
			.ta-ql-status--contacted { border-color:#2563eb; color:#1d4ed8; background:#eff6ff; }
			.ta-ql-status--quoted { border-color:#d97706; color:#b45309; background:#fffbeb; }
			.ta-ql-status--converted { border-color:#16a34a; color:#15803d; background:#f0fdf4; }
			.ta-ql-status--spam { border-color:#9ca3af; color:#4b5563; background:#f3f4f6; }
			.ta-ql-note-box { display:flex; flex-wrap:wrap; gap:4px; align-items:flex-end; }
			.ta-ql-note-input { width:100%; min-width:160px; font-size:12px; }
			.ta-ql-save-msg { font-size:11.5px; color:#15803d; font-weight:600; }
			.ta-ql-settings-card { background:#fff; border:1px solid #dcdcde; border-radius:6px; padding:18px 22px; max-width:960px; }
			.ta-ql-pdf-picker { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
		</style>
		<?php
	}
}
