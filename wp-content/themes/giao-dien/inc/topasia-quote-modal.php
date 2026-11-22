<?php
/**
 * Top Asia — Quote Download Modal & Lead Capture (Homepage & Quote Triggers).
 *
 * Opens a phone-capture modal when clicking "TẢI BÁO GIÁ NGAY" (#ta-open-quote,
 * [data-ta-quote-modal], or matching homepage hero download buttons), saves leads
 * to {$wpdb->prefix}topasia_quote_leads, emails staff, and delivers the quote files.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'topasia_quote_notify_emails' ) ) {
	/**
	 * Notification recipients when a visitor downloads a quote.
	 *
	 * @return string[]
	 */
	function topasia_quote_notify_emails() {
		return apply_filters(
			'topasia_quote_notify_emails',
			array(
				'vuquanghuybv2k@gmail.com',
			)
		);
	}
}

add_action(
	'init',
	function () {
		if ( get_option( 'topasia_quote_leads_db_version' ) === '1' ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = $wpdb->prefix . 'topasia_quote_leads';
		$charset = $wpdb->get_charset_collate();
		$sql     = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			phone varchar(30) NOT NULL,
			product varchar(60) NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY phone (phone),
			KEY created_at (created_at)
		) {$charset};";
		dbDelta( $sql );
		update_option( 'topasia_quote_leads_db_version', '1' );
	}
);

if ( ! function_exists( 'topasia_save_quote_lead' ) ) {
	/**
	 * AJAX handler for saving a quote download lead and notifying staff.
	 */
	function topasia_save_quote_lead() {
		check_ajax_referer( 'topasia_quote_lead_nonce', 'nonce' );

		$phone   = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$product = isset( $_POST['product'] ) ? sanitize_text_field( wp_unslash( $_POST['product'] ) ) : '';

		if ( ! preg_match( '/^[0-9+\s().-]{9,15}$/', $phone ) ) {
			wp_send_json_error( array( 'message' => 'Số điện thoại không hợp lệ.' ), 400 );
		}

		$allowed = array( 'Asia Gold', 'Asia Pearl', 'Asia Lite', 'Cả 3 dòng sản phẩm' );
		if ( ! in_array( $product, $allowed, true ) ) {
			wp_send_json_error( array( 'message' => 'Dòng sản phẩm không hợp lệ.' ), 400 );
		}

		global $wpdb;
		$table      = $wpdb->prefix . 'topasia_quote_leads';
		$created_at = current_time( 'mysql' );
		$ok         = $wpdb->insert(
			$table,
			array(
				'phone'      => $phone,
				'product'    => $product,
				'created_at' => $created_at,
			),
			array( '%s', '%s', '%s' )
		);

		if ( ! $ok ) {
			wp_send_json_error( array( 'message' => 'Lưu thông tin thất bại.' ), 500 );
		}

		$subject    = '[Top Asia] Khách đăng ký tải báo giá - ' . $product;
		$message    = "Có khách hàng đăng ký tải báo giá\n\n";
		$message   .= 'Số điện thoại: ' . $phone . "\n";
		$message   .= 'Dòng sản phẩm: ' . $product . "\n";
		$message   .= 'Thời gian: ' . $created_at . "\n\n";
		$message   .= 'Thông tin đã được lưu trong WordPress.';
		$recipients = array_unique( array_filter( array_map( 'sanitize_email', topasia_quote_notify_emails() ), 'is_email' ) );

		foreach ( $recipients as $recipient ) {
			wp_mail( $recipient, $subject, $message );
		}

		wp_send_json_success( array( 'message' => 'Đã lưu' ) );
	}
	add_action( 'wp_ajax_topasia_save_quote_lead', 'topasia_save_quote_lead' );
	add_action( 'wp_ajax_nopriv_topasia_save_quote_lead', 'topasia_save_quote_lead' );
}

add_action(
	'admin_menu',
	function () {
		if ( isset( $GLOBALS['admin_page_hooks']['topasia-quote-leads'] ) ) {
			return;
		}
		add_menu_page(
			'Khách tải báo giá',
			'Khách tải báo giá',
			'manage_options',
			'topasia-quote-leads',
			'topasia_quote_leads_page',
			'dashicons-list-view',
			26
		);
	},
	20
);

if ( ! function_exists( 'topasia_quote_leads_page' ) ) {
	/**
	 * Admin page displaying saved quote leads with CSV export.
	 */
	function topasia_quote_leads_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		global $wpdb;
		$table = $wpdb->prefix . 'topasia_quote_leads';

		if ( isset( $_GET['export_topasia_leads'] ) && check_admin_referer( 'topasia_export_leads' ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results( "SELECT phone, product, created_at FROM {$table} ORDER BY id DESC", ARRAY_A );
			header( 'Content-Type: text/csv; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename=topasia-leads.csv' );
			$out = fopen( 'php://output', 'w' );
			if ( $out ) {
				fwrite( $out, "\xEF\xBB\xBF" );
				fputcsv( $out, array( 'Phone', 'Product', 'Time' ) );
				foreach ( (array) $rows as $r ) {
					fputcsv( $out, $r );
				}
				fclose( $out );
			}
			exit;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT id, phone, product, created_at FROM {$table} ORDER BY id DESC LIMIT 1000" );
		echo '<div class="wrap"><h1>Khách tải báo giá</h1>';
		echo '<a class="button button-primary" href="' . esc_url( wp_nonce_url( admin_url( 'admin.php?page=topasia-quote-leads&export_topasia_leads=1' ), 'topasia_export_leads' ) ) . '">Xuất CSV</a>';
		echo '<table class="widefat striped" style="margin-top:15px"><thead><tr><th>ID</th><th>Số điện thoại</th><th>Sản phẩm</th><th>Thời gian</th></tr></thead><tbody>';
		if ( ! empty( $rows ) ) {
			foreach ( $rows as $r ) {
				echo '<tr><td>' . esc_html( $r->id ) . '</td><td>' . esc_html( $r->phone ) . '</td><td>' . esc_html( $r->product ) . '</td><td>' . esc_html( $r->created_at ) . '</td></tr>';
			}
		} else {
			echo '<tr><td colspan="4">Chưa có dữ liệu.</td></tr>';
		}
		echo '</tbody></table></div>';
	}
}

add_action(
	'wp_footer',
	function () {
		if ( is_admin() || wp_doing_ajax() || is_customize_preview() ) {
			return;
		}
		if ( isset( $_GET['app'] ) || isset( $_GET['uxb_iframe'] ) || isset( $_GET['ux_builder'] ) ) {
			return;
		}

		$files = apply_filters(
			'topasia_quote_files',
			array(
				'Asia Gold'  => home_url( '/wp-content/uploads/2026/11/Bao-gia-dong-Asia-Gold-2.pdf' ),
				'Asia Pearl' => home_url( '/wp-content/uploads/2026/11/Bao-gia-dong-Asia-Pearl-2.pdf' ),
				'Asia Lite'  => home_url( '/wp-content/uploads/2026/11/Bao-gia-dong-Asia-Lite-2.pdf' ),
			)
		);

		$quote_config = array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'topasia_quote_lead_nonce' ),
			'files'   => array(
				'Asia Gold'           => array( array( 'label' => 'Tải báo giá Asia Gold (PDF)', 'url' => $files['Asia Gold'] ) ),
				'Asia Pearl'          => array( array( 'label' => 'Tải báo giá Asia Pearl (PDF)', 'url' => $files['Asia Pearl'] ) ),
				'Asia Lite'           => array( array( 'label' => 'Tải báo giá Asia Lite (PDF)', 'url' => $files['Asia Lite'] ) ),
				'Cả 3 dòng sản phẩm' => array(
					array( 'label' => 'Tải báo giá Asia Gold (PDF)', 'url' => $files['Asia Gold'] ),
					array( 'label' => 'Tải báo giá Asia Pearl (PDF)', 'url' => $files['Asia Pearl'] ),
					array( 'label' => 'Tải báo giá Asia Lite (PDF)', 'url' => $files['Asia Lite'] ),
				),
			),
		);
		?>
		<div id="ta-quote-modal" role="dialog" aria-modal="true" aria-labelledby="ta-quote-modal-title" aria-hidden="true">
			<div class="ta-modal-overlay"></div>
			<div class="ta-modal-box">
				<button type="button" class="ta-modal-close" aria-label="Đóng">&times;</button>
				<div class="ta-modal-icon" aria-hidden="true">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12m-5-5 5 5 5-5M5 21h14"/></svg>
				</div>
				<h2 id="ta-quote-modal-title">Tải báo giá sản phẩm</h2>
				<p>Vui lòng nhập số điện thoại để nhận và tải bảng báo giá mới nhất từ nhà máy Top Asia.</p>
				<form id="ta-quote-form" novalidate>
					<label class="screen-reader-text" for="ta-customer-phone">Số điện thoại</label>
					<input type="tel" id="ta-customer-phone" placeholder="Nhập số điện thoại (VD: 0912 345 678)" maxlength="15" inputmode="tel" autocomplete="tel" required>
					<label class="screen-reader-text" for="ta-product-line">Dòng sản phẩm</label>
					<select id="ta-product-line" required>
						<option value="">Chọn dòng sản phẩm</option>
						<option value="Asia Gold">Asia Gold (Phân khúc phổ thông)</option>
						<option value="Asia Pearl">Asia Pearl (Phân khúc chuyên nghiệp)</option>
						<option value="Asia Lite">Asia Lite (Phân khúc cao cấp)</option>
						<option value="Cả 3 dòng sản phẩm">Cả 3 dòng sản phẩm</option>
					</select>
					<button type="submit" id="ta-quote-submit">Tải báo giá ngay</button>
					<div id="ta-quote-message" role="status" aria-live="polite"></div>
					<div id="ta-quote-success-links" hidden></div>
				</form>
			</div>
		</div>

		<style>
		#ta-quote-modal,
		#ta-quote-modal * {
			box-sizing: border-box;
			font-family: 'Google Sans', Arial, sans-serif;
		}
		#ta-quote-modal {
			display: none;
			position: fixed;
			z-index: 999999;
			inset: 0;
			align-items: center;
			justify-content: center;
			padding: 18px;
		}
		#ta-quote-modal.ta-modal-active { display: flex; }
		#ta-quote-modal .ta-modal-overlay {
			position: absolute;
			inset: 0;
			background: rgba(11, 29, 58, .72);
			backdrop-filter: blur(3px);
		}
		#ta-quote-modal .ta-modal-box {
			position: relative;
			z-index: 2;
			width: 100%;
			max-width: 440px;
			max-height: calc(100dvh - 32px);
			overflow-y: auto;
			padding: 32px 26px 26px;
			border-radius: 13px;
			border-top: 4px solid #E31E24;
			color: #0B1D3A;
			background: #FFFFFF;
			text-align: center;
			box-shadow: 0 20px 60px rgba(11, 29, 58, .28);
		}
		#ta-quote-modal .ta-modal-close {
			position: absolute;
			top: 10px;
			right: 12px;
			width: 40px;
			height: 40px;
			padding: 0;
			border: 0;
			border-radius: 8px;
			color: #6B7280;
			background: #F7F8FA;
			font-size: 24px;
			line-height: 1;
			cursor: pointer;
			transition: background .15s ease, color .15s ease;
		}
		#ta-quote-modal .ta-modal-close:hover {
			background: #FDEEEF;
			color: #E31E24;
		}
		#ta-quote-modal .ta-modal-icon {
			display: flex;
			width: 48px;
			height: 48px;
			align-items: center;
			justify-content: center;
			margin: 0 auto 14px;
			border-radius: 10px;
			color: #FFFFFF;
			background: #E31E24;
		}
		#ta-quote-modal .ta-modal-box h2 {
			margin: 0 0 8px;
			color: #0B1D3A;
			font-size: 22px;
			font-weight: 800;
			letter-spacing: -.02em;
			text-transform: uppercase;
		}
		#ta-quote-modal .ta-modal-box p {
			margin: 0 0 16px;
			color: #6B7280;
			font-size: 14px;
			line-height: 1.55;
		}
		#ta-quote-modal input,
		#ta-quote-modal select {
			display: block;
			width: 100%;
			height: 48px;
			margin: 10px 0;
			padding: 0 14px;
			border: 1px solid #E4E9F0 !important;
			border-radius: 8px !important;
			color: #0B1D3A;
			background: #FFFFFF;
			font-size: 15px;
			box-shadow: none !important;
		}
		#ta-quote-modal input:focus,
		#ta-quote-modal select:focus {
			border-color: #0B1D3A !important;
			outline: none;
			box-shadow: 0 0 0 2px rgba(11, 29, 58, .12) !important;
		}
		#ta-quote-modal #ta-quote-submit {
			width: 100%;
			min-height: 48px;
			margin-top: 10px;
			border: 0;
			border-radius: 8px;
			color: #FFFFFF;
			background: #E31E24;
			font-size: 14px;
			font-weight: 800;
			letter-spacing: .035em;
			text-transform: uppercase;
			cursor: pointer;
			transition: background .18s ease;
		}
		#ta-quote-modal #ta-quote-submit:hover { background: #C51A1F; }
		#ta-quote-modal #ta-quote-submit:disabled { opacity: .65; cursor: wait; }
		#ta-quote-message {
			margin-top: 12px;
			color: #E31E24;
			font-size: 13px;
			font-weight: 600;
			line-height: 1.5;
		}
		#ta-quote-message.is-success { color: #0F766E; }
		#ta-quote-success-links {
			display: grid;
			gap: 8px;
			margin-top: 12px;
		}
		#ta-quote-success-links[hidden] { display: none; }
		#ta-quote-success-links a {
			display: flex;
			align-items: center;
			justify-content: center;
			min-height: 44px;
			padding: 10px 14px;
			border-radius: 8px;
			background: #0B1D3A;
			color: #FFFFFF !important;
			font-size: 13px;
			font-weight: 700;
			text-decoration: none;
		}
		#ta-quote-success-links a:hover { background: #E31E24; }
		@media (max-width: 600px) {
			#ta-quote-modal { padding: 12px; }
			#ta-quote-modal .ta-modal-box { padding: 28px 18px 20px; }
		}
		</style>

		<script>
		window.TopAsiaQuoteLead = window.TopAsiaQuoteLead || <?php echo wp_json_encode( $quote_config ); ?>;
		(function () {
			if (window.topAsiaQuoteModalInitialized) {
				return;
			}
			window.topAsiaQuoteModalInitialized = true;

			var modal = document.getElementById('ta-quote-modal');
			if (!modal) {
				return;
			}
			var phoneInput = document.getElementById('ta-customer-phone');
			var lineSelect = document.getElementById('ta-product-line');
			var submitBtn = document.getElementById('ta-quote-submit');
			var messageEl = document.getElementById('ta-quote-message');
			var linksEl = document.getElementById('ta-quote-success-links');
			var formEl = document.getElementById('ta-quote-form');
			var lastTrigger = null;

			function isQuoteTrigger(el) {
				if (!el) {
					return null;
				}
				var trigger = el.closest('#ta-open-quote, [data-ta-quote-modal], a, button');
				if (!trigger || modal.contains(trigger)) {
					return null;
				}
				if (trigger.id === 'ta-open-quote' || trigger.hasAttribute('data-ta-quote-modal')) {
					return trigger;
				}
				var txt = (trigger.textContent || '').replace(/\s+/g, ' ').trim().toUpperCase();
				if (txt.indexOf('TẢI BÁO GIÁ') !== -1) {
					return trigger;
				}
				return null;
			}

			function openModal(trigger) {
				lastTrigger = trigger || document.activeElement;
				var preset = trigger && trigger.getAttribute ? trigger.getAttribute('data-ta-quote-product') : '';
				if (preset && lineSelect) {
					lineSelect.value = preset;
				}
				modal.classList.add('ta-modal-active');
				modal.setAttribute('aria-hidden', 'false');
				setTimeout(function () {
					if (phoneInput) {
						phoneInput.focus();
					}
				}, 60);
			}

			function closeModal() {
				modal.classList.remove('ta-modal-active');
				modal.setAttribute('aria-hidden', 'true');
				if (lastTrigger && typeof lastTrigger.focus === 'function') {
					lastTrigger.focus();
				}
			}

			document.addEventListener('click', function (e) {
				var trigger = isQuoteTrigger(e.target);
				if (!trigger) {
					return;
				}
				e.preventDefault();
				openModal(trigger);
			});

			modal.querySelector('.ta-modal-close').addEventListener('click', closeModal);
			modal.querySelector('.ta-modal-overlay').addEventListener('click', closeModal);
			document.addEventListener('keydown', function (e) {
				if (e.key === 'Escape' && modal.classList.contains('ta-modal-active')) {
					closeModal();
				}
			});

			if (formEl) {
				formEl.addEventListener('submit', async function (e) {
					e.preventDefault();
					var phone = phoneInput ? phoneInput.value.trim() : '';
					var product = lineSelect ? lineSelect.value : '';

					messageEl.className = '';
					linksEl.hidden = true;
					linksEl.innerHTML = '';

					if (!/^[0-9+\s().-]{9,15}$/.test(phone)) {
						messageEl.textContent = 'Vui lòng nhập số điện thoại hợp lệ.';
						if (phoneInput) {
							phoneInput.focus();
						}
						return;
					}
					if (!product) {
						messageEl.textContent = 'Vui lòng chọn dòng sản phẩm.';
						if (lineSelect) {
							lineSelect.focus();
						}
						return;
					}
					if (!window.TopAsiaQuoteLead || !window.TopAsiaQuoteLead.ajaxUrl) {
						messageEl.textContent = 'Hệ thống chưa kết nối. Vui lòng thử lại sau.';
						return;
					}

					submitBtn.disabled = true;
					messageEl.textContent = 'Đang lưu thông tin...';

					var data = new FormData();
					data.append('action', 'topasia_save_quote_lead');
					data.append('nonce', window.TopAsiaQuoteLead.nonce);
					data.append('phone', phone);
					data.append('product', product);

					try {
						var response = await fetch(window.TopAsiaQuoteLead.ajaxUrl, {
							method: 'POST',
							credentials: 'same-origin',
							body: data
						});
						var result = await response.json();
						if (!response.ok || !result.success) {
							messageEl.textContent = (result.data && result.data.message) ? result.data.message : 'Không lưu được thông tin. Vui lòng thử lại.';
							submitBtn.disabled = false;
							return;
						}

						messageEl.className = 'is-success';
						messageEl.textContent = 'Đã lưu thông tin. Báo giá của bạn đã sẵn sàng bên dưới:';

						var list = (window.TopAsiaQuoteLead.files && window.TopAsiaQuoteLead.files[product]) || [];
						list.forEach(function (item, idx) {
							var a = document.createElement('a');
							a.href = item.url;
							a.target = '_blank';
							a.rel = 'noopener';
							a.download = '';
							a.textContent = item.label;
							linksEl.appendChild(a);

							setTimeout(function () {
								var dl = document.createElement('a');
								dl.href = item.url;
								dl.target = '_blank';
								dl.rel = 'noopener';
								dl.download = '';
								document.body.appendChild(dl);
								dl.click();
								dl.remove();
							}, idx * 650);
						});
						linksEl.hidden = false;
					} catch (err) {
						messageEl.textContent = 'Lỗi kết nối. Vui lòng thử lại.';
					} finally {
						submitBtn.disabled = false;
					}
				});
			}
		})();
		</script>
		<?php
	},
	30
);
