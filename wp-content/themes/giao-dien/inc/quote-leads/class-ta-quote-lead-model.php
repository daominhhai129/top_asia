<?php
/**
 * Backend data model and repository for Top Asia quote-download & contact phone leads.
 *
 * Maintains two normalized custom tables:
 *  - {$wpdb->prefix}topasia_quote_customers : deduplicated contacts keyed by normalized phone.
 *  - {$wpdb->prefix}topasia_quote_leads     : immutable log of every quote/contact submission.
 *
 * @package TopAsia
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TA_Quote_Lead_Model {

	public const DB_VERSION        = '2.0.0';
	public const OPTION_DB_VERSION = 'topasia_quote_model_db_version';
	public const OPTION_MIGRATED   = 'topasia_quote_model_migrated_v2';
	public const OPTION_SETTINGS   = 'topasia_quote_gate_settings';

	/**
	 * Cached settings within the current request.
	 *
	 * @var array<string, mixed>|null
	 */
	private static ?array $settings_cache = null;

	/**
	 * Table storing unique customer phone contacts.
	 */
	public static function customers_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'topasia_quote_customers';
	}

	/**
	 * Table storing individual quote/contact submission events.
	 */
	public static function leads_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'topasia_quote_leads';
	}

	/**
	 * Supported CRM workflow statuses for contact records.
	 *
	 * @return array<string, string>
	 */
	public static function get_statuses(): array {
		return array(
			'new'       => 'Mới',
			'contacted' => 'Đã liên hệ',
			'quoted'    => 'Đã gửi báo giá',
			'converted' => 'Đã chốt',
			'spam'      => 'Không hợp lệ',
		);
	}

	/**
	 * Human-readable labels for capture sources.
	 *
	 * @return array<string, string>
	 */
	public static function get_source_labels(): array {
		return array(
			'home_quote_modal'   => 'Trang chủ (Tải báo giá)',
			'bao_gia_pdf_modal'  => 'Trang /bao-gia/ (Tải PDF)',
			'cf7_quote'          => 'Form Báo giá (CF7)',
			'cf7_contact'        => 'Form Tư vấn / Liên hệ (CF7)',
			'legacy_quote_modal' => 'Dữ liệu lịch sử',
			'manual'             => 'Nhập thủ công',
		);
	}

	/**
	 * Default product catalog definition.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_default_catalog(): array {
		return array(
			'asia-gold'  => array(
				'label'         => 'Asia Gold',
				'paths'         => array(
					'2026/11/Bao-gia-dong-Asia-Gold-2.pdf',
					'2026/11/Bao-gia-dong-Asia-Gold-1.pdf',
					'2026/11/Bao-gia-dong-Asia-Gold.pdf',
				),
				'download_name' => 'Bao-gia-Asia-Gold.pdf',
				'search_terms'  => array( 'Asia Gold', 'Asia-Gold' ),
			),
			'asia-pearl' => array(
				'label'         => 'Asia Pearl',
				'paths'         => array(
					'2026/11/Bao-gia-dong-Asia-Pearl-2.pdf',
					'2026/11/Bao-gia-dong-Asia-Pearl-1.pdf',
					'2026/11/Bao-gia-dong-Asia-Pearl.pdf',
				),
				'download_name' => 'Bao-gia-Asia-Pearl.pdf',
				'search_terms'  => array( 'Asia Pearl', 'Asia-Pearl' ),
			),
			'asia-lite'  => array(
				'label'         => 'Asia Lite',
				'paths'         => array(
					'2026/11/Bao-gia-dong-Asia-Lite-2.pdf',
					'2026/11/Bao-gia-dong-Asia-Lite-1.pdf',
					'2026/11/Bao-gia-dong-Asia-Lite.pdf',
				),
				'download_name' => 'Bao-gia-Asia-Lite.pdf',
				'search_terms'  => array( 'Asia Lite', 'Asia-Lite' ),
			),
		);
	}

	/**
	 * Return plugin/module settings merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_settings(): array {
		if ( null !== self::$settings_cache ) {
			return self::$settings_cache;
		}

		$admin_email = sanitize_email( (string) get_option( 'admin_email', '' ) );
		$defaults    = array(
			'email_enabled' => true,
			'notify_emails' => $admin_email ? array( $admin_email ) : array(),
			'capture_cf7'   => true,
			'catalog_files' => array(
				'asia-gold'  => array( 'attachment_id' => 0, 'rel_path' => '' ),
				'asia-pearl' => array( 'attachment_id' => 0, 'rel_path' => '' ),
				'asia-lite'  => array( 'attachment_id' => 0, 'rel_path' => '' ),
			),
		);

		$saved = get_option( self::OPTION_SETTINGS, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		$merged                  = wp_parse_args( $saved, $defaults );
		$merged['email_enabled'] = ! empty( $merged['email_enabled'] );
		$merged['capture_cf7']   = ! empty( $merged['capture_cf7'] );

		$emails = array();
		if ( isset( $merged['notify_emails'] ) && is_array( $merged['notify_emails'] ) ) {
			foreach ( $merged['notify_emails'] as $email ) {
				$clean = sanitize_email( (string) $email );
				if ( $clean && is_email( $clean ) ) {
					$emails[] = $clean;
				}
			}
		}
		$merged['notify_emails'] = array_values( array_unique( $emails ) );

		$catalog_files = array();
		$raw_files     = isset( $merged['catalog_files'] ) && is_array( $merged['catalog_files'] ) ? $merged['catalog_files'] : array();
		foreach ( self::get_default_catalog() as $slug => $meta ) {
			$row                    = isset( $raw_files[ $slug ] ) && is_array( $raw_files[ $slug ] ) ? $raw_files[ $slug ] : array();
			$catalog_files[ $slug ] = array(
				'attachment_id' => isset( $row['attachment_id'] ) ? absint( $row['attachment_id'] ) : 0,
				'rel_path'      => isset( $row['rel_path'] ) ? ltrim( str_replace( '\\', '/', sanitize_text_field( (string) $row['rel_path'] ) ), '/' ) : '',
			);
		}
		$merged['catalog_files'] = $catalog_files;

		self::$settings_cache = $merged;
		return $merged;
	}

	/**
	 * Persist settings cleanly.
	 *
	 * @param array<string, mixed> $input Raw settings input.
	 * @return array<string, mixed>
	 */
	public static function save_settings( array $input ): array {
		$emails = array();
		$raw    = $input['notify_emails'] ?? array();
		if ( is_string( $raw ) ) {
			$raw = preg_split( '/[\r\n,;]+/', $raw ) ?: array();
		}
		if ( is_array( $raw ) ) {
			foreach ( $raw as $candidate ) {
				$clean = sanitize_email( trim( (string) $candidate ) );
				if ( $clean && is_email( $clean ) ) {
					$emails[] = $clean;
				}
			}
		}

		$catalog_files = array();
		$raw_files     = isset( $input['catalog_files'] ) && is_array( $input['catalog_files'] ) ? $input['catalog_files'] : array();
		foreach ( self::get_default_catalog() as $slug => $meta ) {
			$row                    = isset( $raw_files[ $slug ] ) && is_array( $raw_files[ $slug ] ) ? $raw_files[ $slug ] : array();
			$rel_path               = isset( $row['rel_path'] ) ? ltrim( str_replace( '\\', '/', sanitize_text_field( (string) $row['rel_path'] ) ), '/' ) : '';
			$rel_path               = str_replace( '../', '', $rel_path );
			$catalog_files[ $slug ] = array(
				'attachment_id' => isset( $row['attachment_id'] ) ? absint( $row['attachment_id'] ) : 0,
				'rel_path'      => $rel_path,
			);
		}

		$clean = array(
			'email_enabled' => ! empty( $input['email_enabled'] ),
			'notify_emails' => array_values( array_unique( $emails ) ),
			'capture_cf7'   => ! empty( $input['capture_cf7'] ),
			'catalog_files' => $catalog_files,
		);

		update_option( self::OPTION_SETTINGS, $clean, false );
		self::$settings_cache = $clean;
		return $clean;
	}

	/**
	 * Return product catalog enriched with configured paths.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_catalog(): array {
		$catalog  = self::get_default_catalog();
		$settings = self::get_settings();
		$files    = $settings['catalog_files'] ?? array();

		foreach ( $catalog as $slug => &$meta ) {
			$paths = isset( $meta['paths'] ) ? (array) $meta['paths'] : array();
			if ( ! empty( $files[ $slug ]['rel_path'] ) ) {
				array_unshift( $paths, (string) $files[ $slug ]['rel_path'] );
			}
			$meta['attachment_id'] = ! empty( $files[ $slug ]['attachment_id'] ) ? (int) $files[ $slug ]['attachment_id'] : 0;
			$meta['paths']         = array_values( array_unique( array_filter( $paths ) ) );
		}
		unset( $meta );

		return $catalog;
	}

	/**
	 * Resolve a safe, readable PDF file inside wp-content/uploads for a product slug.
	 *
	 * @param string $product Product slug.
	 * @return array{0: array<string, mixed>, 1: string, 2: string}|false [catalog_meta, abs_path, uploads_rel_path]
	 */
	public static function resolve_pdf( string $product ) {
		$catalog = self::get_catalog();
		if ( ! isset( $catalog[ $product ] ) ) {
			return false;
		}

		$uploads = wp_upload_dir();
		if ( empty( $uploads['basedir'] ) ) {
			return false;
		}

		$base      = realpath( $uploads['basedir'] );
		$base_norm = false !== $base ? trailingslashit( wp_normalize_path( $base ) ) : '';
		if ( '' === $base_norm ) {
			return false;
		}

		$meta       = $catalog[ $product ];
		$candidates = array();

		if ( ! empty( $meta['attachment_id'] ) ) {
			$attached = get_attached_file( (int) $meta['attachment_id'] );
			if ( is_string( $attached ) && '' !== $attached ) {
				$candidates[] = $attached;
			}
		}

		foreach ( (array) ( $meta['paths'] ?? array() ) as $rel_path ) {
			$candidates[] = trailingslashit( $uploads['basedir'] ) . ltrim( (string) $rel_path, '/' );
		}

		// Fallback: inspect PDF attachments in the Media Library matching the product line.
		$search_terms = isset( $meta['search_terms'] ) ? (array) $meta['search_terms'] : array( $meta['label'] );
		foreach ( $search_terms as $term ) {
			$att_ids = get_posts(
				array(
					'post_type'      => 'attachment',
					'post_mime_type' => 'application/pdf',
					'post_status'    => 'inherit',
					'posts_per_page' => 3,
					'orderby'        => 'ID',
					'order'          => 'DESC',
					'fields'         => 'ids',
					'no_found_rows'  => true,
					's'              => $term,
				)
			);
			foreach ( $att_ids as $att_id ) {
				$attached = get_attached_file( (int) $att_id );
				if ( is_string( $attached ) && '' !== $attached ) {
					$candidates[] = $attached;
				}
			}
		}

		foreach ( array_unique( $candidates ) as $candidate ) {
			$real      = realpath( (string) $candidate );
			$real_norm = false !== $real ? wp_normalize_path( $real ) : '';
			if ( '' !== $real_norm && 0 === strpos( $real_norm, $base_norm ) && is_readable( $real ) ) {
				$rel = ltrim( substr( $real_norm, strlen( $base_norm ) ), '/' );
				return array( $meta, $real, $rel );
			}
		}

		return false;
	}

	/**
	 * Ensure DB schema is up to date and legacy records are backfilled.
	 */
	public static function maybe_install(): void {
		if ( get_option( self::OPTION_DB_VERSION ) !== self::DB_VERSION ) {
			self::install();
		} elseif ( ! get_option( self::OPTION_MIGRATED ) ) {
			self::migrate_legacy_records();
		}
	}

	/**
	 * Create or upgrade the two backend tables using dbDelta().
	 */
	public static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset         = $wpdb->get_charset_collate();
		$customers_table = self::customers_table();
		$leads_table     = self::leads_table();

		$sql_customers = "CREATE TABLE {$customers_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			phone varchar(30) NOT NULL,
			phone_norm varchar(20) NOT NULL DEFAULT '',
			full_name varchar(120) NOT NULL DEFAULT '',
			email varchar(190) NOT NULL DEFAULT '',
			customer_type varchar(100) NOT NULL DEFAULT '',
			product varchar(50) NOT NULL DEFAULT '',
			last_product varchar(100) NOT NULL DEFAULT '',
			products_list text NULL,
			last_source varchar(80) NOT NULL DEFAULT 'home_quote_modal',
			last_page_url text NULL,
			total_requests int(10) unsigned NOT NULL DEFAULT 1,
			total_downloads int(10) unsigned NOT NULL DEFAULT 0,
			status varchar(30) NOT NULL DEFAULT 'new',
			note text NULL,
			first_seen_at datetime NOT NULL,
			last_seen_at datetime NOT NULL,
			last_downloaded_at datetime NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY idx_phone_norm (phone_norm),
			KEY idx_status (status),
			KEY idx_last_seen (last_seen_at)
		) {$charset};";

		$sql_leads = "CREATE TABLE {$leads_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			customer_id bigint(20) unsigned NOT NULL DEFAULT 0,
			phone varchar(30) NOT NULL,
			phone_norm varchar(20) NOT NULL DEFAULT '',
			full_name varchar(120) NOT NULL DEFAULT '',
			email varchar(190) NOT NULL DEFAULT '',
			product varchar(100) NOT NULL DEFAULT '',
			product_slug varchar(60) NOT NULL DEFAULT '',
			source varchar(80) NOT NULL DEFAULT 'home_quote_modal',
			file_url text NOT NULL,
			page_url text NULL,
			referrer_url text NULL,
			ip_address varchar(45) NOT NULL DEFAULT '',
			user_agent varchar(255) NOT NULL DEFAULT '',
			status varchar(30) NOT NULL DEFAULT 'new',
			note text NULL,
			downloaded_at datetime NULL,
			download_count int(10) unsigned NOT NULL DEFAULT 0,
			downloaded_lines text NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY idx_customer (customer_id),
			KEY phone (phone),
			KEY idx_phone_norm (phone_norm),
			KEY idx_source (source),
			KEY created_at (created_at)
		) {$charset};";

		dbDelta( $sql_customers );
		dbDelta( $sql_leads );

		update_option( self::OPTION_DB_VERSION, self::DB_VERSION, false );
		self::migrate_legacy_records();
	}

	/**
	 * Backfill existing rows in topasia_quote_leads and legacy taf_quote_lead posts into the customer model.
	 */
	public static function migrate_legacy_records(): void {
		global $wpdb;

		$leads_table     = self::leads_table();
		$customers_table = self::customers_table();

		// 1. Migrate any legacy taf_quote_lead CPT records if present.
		$cpt_ids = get_posts(
			array(
				'post_type'      => 'taf_quote_lead',
				'post_status'    => array( 'private', 'publish', 'draft' ),
				'posts_per_page' => 500,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array(
					array(
						'key'     => '_taf_qd_migrated',
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		foreach ( $cpt_ids as $post_id ) {
			$post_id     = (int) $post_id;
			$raw_phone   = (string) get_post_meta( $post_id, '_taf_qd_phone', true );
			$norm_phone  = self::normalize_phone( $raw_phone );
			$prod_slug   = sanitize_key( (string) get_post_meta( $post_id, '_taf_qd_product', true ) );
			$prod_label  = (string) get_post_meta( $post_id, '_taf_qd_product_label', true );
			$dl_at       = (string) get_post_meta( $post_id, '_taf_qd_downloaded_at', true );
			$filename    = (string) get_post_meta( $post_id, '_taf_qd_filename', true );
			$post_obj    = get_post( $post_id );
			$created_at  = ( $post_obj && ! empty( $post_obj->post_date ) ) ? $post_obj->post_date : current_time( 'mysql' );

			if ( '' !== $norm_phone ) {
				$wpdb->insert(
					$leads_table,
					array(
						'customer_id'      => 0,
						'phone'            => $raw_phone ? $raw_phone : $norm_phone,
						'phone_norm'       => $norm_phone,
						'product'          => $prod_label ? $prod_label : $prod_slug,
						'product_slug'     => $prod_slug,
						'source'           => 'home_quote_modal',
						'file_url'         => $filename,
						'downloaded_at'    => $dl_at ? $dl_at : null,
						'download_count'   => $dl_at ? 1 : 0,
						'downloaded_lines' => $dl_at && $prod_slug ? $prod_slug : '',
						'created_at'       => $created_at,
					)
				);
			}
			update_post_meta( $post_id, '_taf_qd_migrated', '1' );
		}

		// 2. Link unlinked rows in topasia_quote_leads into topasia_quote_customers.
		$unlinked = $wpdb->get_results(
			"SELECT * FROM {$leads_table} WHERE customer_id = 0 OR phone_norm = '' ORDER BY created_at ASC, id ASC",
			ARRAY_A
		);

		foreach ( (array) $unlinked as $row ) {
			$lead_id    = (int) $row['id'];
			$raw_phone  = (string) ( $row['phone'] ?? '' );
			$norm_phone = self::normalize_phone( $raw_phone );
			if ( '' === $norm_phone ) {
				$norm_phone = substr( preg_replace( '/\D+/', '', $raw_phone ), 0, 20 );
			}
			if ( '' === $norm_phone ) {
				continue;
			}

			$source = ! empty( $row['source'] ) ? (string) $row['source'] : 'legacy_quote_modal';
			$prod   = trim( (string) ( $row['product'] ?? '' ) );
			$ts     = ! empty( $row['created_at'] ) && '0000-00-00 00:00:00' !== $row['created_at']
				? (string) $row['created_at']
				: current_time( 'mysql' );

			$customer_id = self::upsert_customer_from_row(
				array(
					'phone'        => $raw_phone,
					'phone_norm'   => $norm_phone,
					'full_name'    => (string) ( $row['full_name'] ?? '' ),
					'email'        => (string) ( $row['email'] ?? '' ),
					'product'      => $prod,
					'source'       => $source,
					'page_url'     => (string) ( $row['page_url'] ?? '' ),
					'downloaded'   => ! empty( $row['downloaded_at'] ) ? 1 : (int) ( $row['download_count'] ?? 0 ),
					'downloaded_at'=> $row['downloaded_at'] ?? null,
					'timestamp'    => $ts,
				)
			);

			$wpdb->update(
				$leads_table,
				array(
					'customer_id' => $customer_id,
					'phone_norm'  => $norm_phone,
					'source'      => $source,
				),
				array( 'id' => $lead_id ),
				array( '%d', '%s', '%s' ),
				array( '%d' )
			);
		}

		// Normalize legacy empty timestamps in topasia_quote_customers if any.
		$wpdb->query(
			"UPDATE {$customers_table} SET first_seen_at = created_at WHERE first_seen_at = '0000-00-00 00:00:00' OR first_seen_at IS NULL"
		);
		$wpdb->query(
			"UPDATE {$customers_table} SET last_seen_at = created_at WHERE last_seen_at = '0000-00-00 00:00:00' OR last_seen_at IS NULL"
		);

		update_option( self::OPTION_MIGRATED, '1', false );
	}

	/**
	 * Normalize a phone string to canonical Vietnamese digits starting with 0.
	 */
	public static function normalize_phone( string $raw ): string {
		$digits = preg_replace( '/\D+/', '', $raw );
		if ( ! is_string( $digits ) || '' === $digits ) {
			return '';
		}
		if ( 0 === strpos( $digits, '84' ) && strlen( $digits ) >= 11 ) {
			$digits = '0' . substr( $digits, 2 );
		}
		return substr( $digits, 0, 20 );
	}

	/**
	 * Check whether a normalized phone string is a valid Vietnamese phone number (10-11 digits starting with 0).
	 */
	public static function is_valid_vn_phone( string $norm ): bool {
		return 1 === preg_match( '/^0\d{9,10}$/', $norm );
	}

	/**
	 * Format a phone number with subtle readability spaces if 10 digits (e.g. 0912 345 678).
	 */
	public static function format_phone_display( string $phone ): string {
		$norm = self::normalize_phone( $phone );
		if ( 10 === strlen( $norm ) ) {
			return substr( $norm, 0, 4 ) . ' ' . substr( $norm, 4, 3 ) . ' ' . substr( $norm, 7, 3 );
		}
		if ( 11 === strlen( $norm ) ) {
			return substr( $norm, 0, 4 ) . ' ' . substr( $norm, 4, 3 ) . ' ' . substr( $norm, 7, 4 );
		}
		return $phone;
	}

	/**
	 * Record a new quote/contact submission and upsert its deduplicated customer contact record.
	 *
	 * @param array<string, mixed> $data Submission payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function record_lead( array $data ) {
		global $wpdb;

		self::maybe_install();

		$raw_phone  = sanitize_text_field( (string) ( $data['phone'] ?? '' ) );
		$norm_phone = self::normalize_phone( $raw_phone );

		if ( ! self::is_valid_vn_phone( $norm_phone ) ) {
			return new WP_Error(
				'invalid_phone',
				'Vui lòng nhập số điện thoại hợp lệ (10–11 chữ số, bắt đầu bằng 0).'
			);
		}

		$product_label = sanitize_text_field( (string) ( $data['product'] ?? '' ) );
		$product_slug  = sanitize_key( (string) ( $data['product_slug'] ?? '' ) );
		if ( '' === $product_label && '' !== $product_slug ) {
			$catalog       = self::get_catalog();
			$product_label = isset( $catalog[ $product_slug ]['label'] ) ? (string) $catalog[ $product_slug ]['label'] : $product_slug;
		}
		if ( '' === $product_label ) {
			$product_label = 'Báo giá chung';
		}

		$source       = sanitize_key( (string) ( $data['source'] ?? 'home_quote_modal' ) );
		if ( '' === $source ) {
			$source = 'home_quote_modal';
		}

		$full_name     = sanitize_text_field( (string) ( $data['full_name'] ?? '' ) );
		$email         = sanitize_email( (string) ( $data['email'] ?? '' ) );
		$customer_type = sanitize_text_field( (string) ( $data['customer_type'] ?? '' ) );
		$file_url      = esc_url_raw( (string) ( $data['file_url'] ?? '' ) );
		$page_url      = esc_url_raw( ! empty( $data['page_url'] ) ? (string) $data['page_url'] : (string) wp_get_referer() );
		$referrer_url  = esc_url_raw( (string) ( $data['referrer_url'] ?? ( $_SERVER['HTTP_REFERER'] ?? '' ) ) );
		$ip_address    = self::resolve_client_ip();
		$user_agent    = substr( sanitize_text_field( (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ), 0, 255 );
		$note          = sanitize_textarea_field( (string) ( $data['note'] ?? '' ) );
		$now           = current_time( 'mysql' );

		$existing_customer = self::get_customer_by_phone( $norm_phone );
		$is_new_customer   = null === $existing_customer;

		$customer_id = self::upsert_customer_from_row(
			array(
				'phone'         => $norm_phone,
				'phone_norm'    => $norm_phone,
				'full_name'     => $full_name,
				'email'         => $email,
				'customer_type' => $customer_type,
				'product'       => $product_label,
				'source'        => $source,
				'page_url'      => $page_url,
				'note'          => $note,
				'timestamp'     => $now,
			)
		);

		if ( ! $customer_id ) {
			return new WP_Error( 'db_error', 'Không thể lưu thông tin liên hệ lúc này. Vui lòng thử lại.' );
		}

		$leads_table = self::leads_table();
		$inserted    = $wpdb->insert(
			$leads_table,
			array(
				'customer_id'  => $customer_id,
				'phone'        => $norm_phone,
				'phone_norm'   => $norm_phone,
				'full_name'    => substr( $full_name, 0, 120 ),
				'email'        => substr( $email, 0, 190 ),
				'product'      => substr( $product_label, 0, 100 ),
				'product_slug' => substr( $product_slug, 0, 60 ),
				'source'       => substr( $source, 0, 80 ),
				'file_url'     => $file_url,
				'page_url'     => $page_url,
				'referrer_url' => $referrer_url,
				'ip_address'   => $ip_address,
				'user_agent'   => $user_agent,
				'status'       => 'new',
				'note'         => $note,
				'created_at'   => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return new WP_Error( 'db_error', 'Không thể ghi nhận yêu cầu lúc này. Vui lòng thử lại.' );
		}

		$lead_id = (int) $wpdb->insert_id;

		$result = array(
			'lead_id'         => $lead_id,
			'customer_id'     => $customer_id,
			'phone'           => $norm_phone,
			'product'         => $product_label,
			'product_slug'    => $product_slug,
			'source'          => $source,
			'is_new_customer' => $is_new_customer,
			'created_at'      => $now,
		);

		self::maybe_send_notification( $result, $data );

		/**
		 * Fires after a quote/contact lead phone number has been recorded.
		 *
		 * @param array<string, mixed> $result Saved record metadata.
		 * @param array<string, mixed> $data   Original sanitized payload.
		 */
		do_action( 'topasia_quote_lead_recorded', $result, $data );

		return $result;
	}

	/**
	 * Insert or update a deduplicated customer record in topasia_quote_customers.
	 *
	 * @param array<string, mixed> $row Normalized row attributes.
	 */
	private static function upsert_customer_from_row( array $row ): int {
		global $wpdb;

		$table      = self::customers_table();
		$norm_phone = (string) $row['phone_norm'];
		$ts         = ! empty( $row['timestamp'] ) ? (string) $row['timestamp'] : current_time( 'mysql' );
		$product    = trim( (string) ( $row['product'] ?? '' ) );
		$existing   = self::get_customer_by_phone( $norm_phone );

		if ( null === $existing ) {
			$products = $product ? array( $product ) : array();
			$wpdb->insert(
				$table,
				array(
					'phone'              => substr( (string) ( $row['phone'] ?: $norm_phone ), 0, 30 ),
					'phone_norm'         => $norm_phone,
					'full_name'          => substr( (string) ( $row['full_name'] ?? '' ), 0, 120 ),
					'email'              => substr( (string) ( $row['email'] ?? '' ), 0, 190 ),
					'customer_type'      => substr( (string) ( $row['customer_type'] ?? '' ), 0, 100 ),
					'product'            => substr( $product, 0, 50 ),
					'last_product'       => substr( $product, 0, 100 ),
					'products_list'      => implode( ', ', $products ),
					'last_source'        => substr( (string) ( $row['source'] ?? 'home_quote_modal' ), 0, 80 ),
					'last_page_url'      => (string) ( $row['page_url'] ?? '' ),
					'total_requests'     => 1,
					'total_downloads'    => max( 0, (int) ( $row['downloaded'] ?? 0 ) ),
					'status'             => 'new',
					'note'               => (string) ( $row['note'] ?? '' ),
					'first_seen_at'      => $ts,
					'last_seen_at'       => $ts,
					'last_downloaded_at' => ! empty( $row['downloaded_at'] ) ? (string) $row['downloaded_at'] : null,
					'created_at'         => $ts,
				)
			);
			return (int) $wpdb->insert_id;
		}

		$existing_products = array_filter( array_map( 'trim', explode( ',', (string) ( $existing['products_list'] ?? '' ) ) ) );
		if ( '' !== $product && ! in_array( $product, $existing_products, true ) ) {
			$existing_products[] = $product;
		}

		$update = array(
			'phone'           => substr( (string) ( $row['phone'] ?: $norm_phone ), 0, 30 ),
			'total_requests'  => (int) $existing['total_requests'] + 1,
			'total_downloads' => (int) $existing['total_downloads'] + max( 0, (int) ( $row['downloaded'] ?? 0 ) ),
			'products_list'   => implode( ', ', $existing_products ),
			'last_source'     => substr( (string) ( $row['source'] ?: $existing['last_source'] ), 0, 80 ),
		);

		if ( '' !== $product ) {
			$update['product']      = substr( $product, 0, 50 );
			$update['last_product'] = substr( $product, 0, 100 );
		}
		if ( ! empty( $row['full_name'] ) ) {
			$update['full_name'] = substr( (string) $row['full_name'], 0, 120 );
		}
		if ( ! empty( $row['email'] ) ) {
			$update['email'] = substr( (string) $row['email'], 0, 190 );
		}
		if ( ! empty( $row['customer_type'] ) ) {
			$update['customer_type'] = substr( (string) $row['customer_type'], 0, 100 );
		}
		if ( ! empty( $row['page_url'] ) ) {
			$update['last_page_url'] = (string) $row['page_url'];
		}
		if ( ! empty( $row['note'] ) ) {
			$prev_note      = trim( (string) ( $existing['note'] ?? '' ) );
			$new_note       = trim( (string) $row['note'] );
			$update['note'] = $prev_note && false === strpos( $prev_note, $new_note )
				? $prev_note . "\n" . $new_note
				: ( $prev_note ? $prev_note : $new_note );
		}

		if ( empty( $existing['first_seen_at'] ) || $ts < (string) $existing['first_seen_at'] ) {
			$update['first_seen_at'] = $ts;
		}
		if ( empty( $existing['last_seen_at'] ) || $ts >= (string) $existing['last_seen_at'] ) {
			$update['last_seen_at'] = $ts;
		}
		if ( ! empty( $row['downloaded_at'] ) && ( empty( $existing['last_downloaded_at'] ) || (string) $row['downloaded_at'] >= (string) $existing['last_downloaded_at'] ) ) {
			$update['last_downloaded_at'] = (string) $row['downloaded_at'];
		}

		$wpdb->update(
			$table,
			$update,
			array( 'id' => (int) $existing['id'] )
		);

		return (int) $existing['id'];
	}

	/**
	 * Record a PDF download event on both the submission log and parent customer contact.
	 */
	public static function mark_downloaded( int $lead_id, string $product_slug, string $file_url = '' ): void {
		global $wpdb;

		if ( $lead_id <= 0 ) {
			return;
		}

		$leads_table     = self::leads_table();
		$customers_table = self::customers_table();
		$lead            = $wpdb->get_row(
			$wpdb->prepare( "SELECT id, customer_id, downloaded_lines, download_count, file_url FROM {$leads_table} WHERE id = %d LIMIT 1", $lead_id ),
			ARRAY_A
		);

		if ( ! $lead ) {
			return;
		}

		$now   = current_time( 'mysql' );
		$lines = array_filter( array_map( 'trim', explode( ',', (string) ( $lead['downloaded_lines'] ?? '' ) ) ) );
		if ( '' !== $product_slug && ! in_array( $product_slug, $lines, true ) ) {
			$lines[] = $product_slug;
		}

		$update_lead = array(
			'downloaded_at'    => $now,
			'download_count'   => (int) $lead['download_count'] + 1,
			'downloaded_lines' => implode( ',', $lines ),
		);
		if ( '' !== $file_url ) {
			$update_lead['file_url'] = esc_url_raw( $file_url );
		}

		$wpdb->update( $leads_table, $update_lead, array( 'id' => $lead_id ) );

		$customer_id = (int) $lead['customer_id'];
		if ( $customer_id > 0 ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$customers_table} SET total_downloads = total_downloads + 1, last_downloaded_at = %s WHERE id = %d",
					$now,
					$customer_id
				)
			);
		}
	}

	/**
	 * Fetch a customer record by ID.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function get_customer( int $id ): ?array {
		global $wpdb;
		$table = self::customers_table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Fetch a customer record by normalized phone.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function get_customer_by_phone( string $phone_norm ): ?array {
		global $wpdb;
		if ( '' === $phone_norm ) {
			return null;
		}
		$table = self::customers_table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE phone_norm = %s LIMIT 1", $phone_norm ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Query deduplicated customers with search, filters, sorting, and pagination.
	 *
	 * @param array<string, mixed> $args Filter arguments.
	 * @return array{items: array<int, array<string, mixed>>, total: int}
	 */
	public static function get_customers( array $args = array() ): array {
		global $wpdb;
		self::maybe_install();

		$table    = self::customers_table();
		$where    = array( '1=1' );
		$params   = array();
		$per_page = isset( $args['per_page'] ) ? max( 1, min( 10000, (int) $args['per_page'] ) ) : 25;
		$paged    = isset( $args['paged'] ) ? max( 1, (int) $args['paged'] ) : 1;
		$offset   = ( $paged - 1 ) * $per_page;

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$params[] = sanitize_key( (string) $args['status'] );
		}
		if ( ! empty( $args['source'] ) ) {
			$where[]  = 'last_source = %s';
			$params[] = sanitize_key( (string) $args['source'] );
		}
		if ( ! empty( $args['product'] ) ) {
			$where[]  = 'products_list LIKE %s';
			$params[] = '%' . $wpdb->esc_like( sanitize_text_field( (string) $args['product'] ) ) . '%';
		}
		if ( ! empty( $args['date_from'] ) ) {
			$where[]  = 'last_seen_at >= %s';
			$params[] = sanitize_text_field( (string) $args['date_from'] ) . ' 00:00:00';
		}
		if ( ! empty( $args['date_to'] ) ) {
			$where[]  = 'last_seen_at <= %s';
			$params[] = sanitize_text_field( (string) $args['date_to'] ) . ' 23:59:59';
		}
		if ( ! empty( $args['search'] ) ) {
			$s        = '%' . $wpdb->esc_like( sanitize_text_field( (string) $args['search'] ) ) . '%';
			$where[]  = '(phone LIKE %s OR phone_norm LIKE %s OR full_name LIKE %s OR email LIKE %s OR note LIKE %s)';
			$params[] = $s;
			$params[] = $s;
			$params[] = $s;
			$params[] = $s;
			$params[] = $s;
		}

		$allowed_orderby = array( 'id', 'last_seen_at', 'first_seen_at', 'total_requests', 'total_downloads', 'status' );
		$orderby         = isset( $args['orderby'] ) && in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'last_seen_at';
		$order           = ( isset( $args['order'] ) && 'ASC' === strtoupper( (string) $args['order'] ) ) ? 'ASC' : 'DESC';

		$where_sql = implode( ' AND ', $where );
		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		$data_sql  = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} {$order}, id DESC LIMIT %d OFFSET %d";

		if ( $params ) {
			$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) );
			$items = $wpdb->get_results( $wpdb->prepare( $data_sql, array_merge( $params, array( $per_page, $offset ) ) ), ARRAY_A );
		} else {
			$total = (int) $wpdb->get_var( $count_sql );
			$items = $wpdb->get_results( $wpdb->prepare( $data_sql, $per_page, $offset ), ARRAY_A );
		}

		return array(
			'items' => is_array( $items ) ? $items : array(),
			'total' => $total,
		);
	}

	/**
	 * Query individual submission log rows.
	 *
	 * @param array<string, mixed> $args Filter arguments.
	 * @return array{items: array<int, array<string, mixed>>, total: int}
	 */
	public static function get_leads( array $args = array() ): array {
		global $wpdb;
		self::maybe_install();

		$table    = self::leads_table();
		$where    = array( '1=1' );
		$params   = array();
		$per_page = isset( $args['per_page'] ) ? max( 1, min( 10000, (int) $args['per_page'] ) ) : 30;
		$paged    = isset( $args['paged'] ) ? max( 1, (int) $args['paged'] ) : 1;
		$offset   = ( $paged - 1 ) * $per_page;

		if ( ! empty( $args['customer_id'] ) ) {
			$where[]  = 'customer_id = %d';
			$params[] = (int) $args['customer_id'];
		}
		if ( ! empty( $args['source'] ) ) {
			$where[]  = 'source = %s';
			$params[] = sanitize_key( (string) $args['source'] );
		}
		if ( ! empty( $args['product'] ) ) {
			$where[]  = 'product LIKE %s';
			$params[] = '%' . $wpdb->esc_like( sanitize_text_field( (string) $args['product'] ) ) . '%';
		}
		if ( ! empty( $args['date_from'] ) ) {
			$where[]  = 'created_at >= %s';
			$params[] = sanitize_text_field( (string) $args['date_from'] ) . ' 00:00:00';
		}
		if ( ! empty( $args['date_to'] ) ) {
			$where[]  = 'created_at <= %s';
			$params[] = sanitize_text_field( (string) $args['date_to'] ) . ' 23:59:59';
		}
		if ( ! empty( $args['search'] ) ) {
			$s        = '%' . $wpdb->esc_like( sanitize_text_field( (string) $args['search'] ) ) . '%';
			$where[]  = '(phone LIKE %s OR phone_norm LIKE %s OR full_name LIKE %s OR email LIKE %s OR product LIKE %s OR page_url LIKE %s)';
			$params[] = $s;
			$params[] = $s;
			$params[] = $s;
			$params[] = $s;
			$params[] = $s;
			$params[] = $s;
		}

		$where_sql = implode( ' AND ', $where );
		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		$data_sql  = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d";

		if ( $params ) {
			$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) );
			$items = $wpdb->get_results( $wpdb->prepare( $data_sql, array_merge( $params, array( $per_page, $offset ) ) ), ARRAY_A );
		} else {
			$total = (int) $wpdb->get_var( $count_sql );
			$items = $wpdb->get_results( $wpdb->prepare( $data_sql, $per_page, $offset ), ARRAY_A );
		}

		return array(
			'items' => is_array( $items ) ? $items : array(),
			'total' => $total,
		);
	}

	/**
	 * Aggregate KPIs for the admin dashboard and REST API.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_stats(): array {
		global $wpdb;
		self::maybe_install();

		$customers = self::customers_table();
		$leads     = self::leads_table();
		$today     = wp_date( 'Y-m-d 00:00:00' );
		$week_ago  = wp_date( 'Y-m-d 00:00:00', current_time( 'timestamp' ) - ( 6 * DAY_IN_SECONDS ) );

		$status_rows = $wpdb->get_results( "SELECT status, COUNT(*) AS cnt FROM {$customers} GROUP BY status", OBJECT_K );
		$by_status   = array();
		foreach ( self::get_statuses() as $key => $label ) {
			$by_status[ $key ] = isset( $status_rows[ $key ] ) ? (int) $status_rows[ $key ]->cnt : 0;
		}

		return array(
			'total_customers'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$customers}" ),
			'new_customers'      => $by_status['new'] ?? 0,
			'downloaded_custs'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$customers} WHERE total_downloads > 0" ),
			'total_leads'        => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$leads}" ),
			'today_leads'        => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$leads} WHERE created_at >= %s", $today ) ),
			'week_leads'         => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$leads} WHERE created_at >= %s", $week_ago ) ),
			'downloaded_leads'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$leads} WHERE download_count > 0" ),
			'by_status'          => $by_status,
		);
	}

	/**
	 * Update editable CRM fields on a customer record.
	 *
	 * @param int                  $customer_id Customer primary key.
	 * @param array<string, mixed> $fields      Fields to update.
	 */
	public static function update_customer( int $customer_id, array $fields ): bool {
		global $wpdb;

		if ( $customer_id <= 0 ) {
			return false;
		}

		$update   = array();
		$statuses = self::get_statuses();

		if ( isset( $fields['status'] ) ) {
			$st = sanitize_key( (string) $fields['status'] );
			if ( isset( $statuses[ $st ] ) ) {
				$update['status'] = $st;
			}
		}
		if ( array_key_exists( 'note', $fields ) ) {
			$update['note'] = sanitize_textarea_field( (string) $fields['note'] );
		}
		if ( array_key_exists( 'full_name', $fields ) ) {
			$update['full_name'] = substr( sanitize_text_field( (string) $fields['full_name'] ), 0, 120 );
		}
		if ( array_key_exists( 'email', $fields ) ) {
			$update['email'] = substr( sanitize_email( (string) $fields['email'] ), 0, 190 );
		}
		if ( array_key_exists( 'customer_type', $fields ) ) {
			$update['customer_type'] = substr( sanitize_text_field( (string) $fields['customer_type'] ), 0, 100 );
		}

		if ( empty( $update ) ) {
			return false;
		}

		$res = $wpdb->update(
			self::customers_table(),
			$update,
			array( 'id' => $customer_id )
		);

		return false !== $res;
	}

	/**
	 * Bulk-update workflow status on multiple customers.
	 *
	 * @param array<int, int> $ids    Customer IDs.
	 * @param string          $status Target status key.
	 */
	public static function bulk_update_status( array $ids, string $status ): int {
		global $wpdb;

		$statuses = self::get_statuses();
		$status   = sanitize_key( $status );
		if ( ! isset( $statuses[ $status ] ) ) {
			return 0;
		}

		$clean_ids = array_values( array_filter( array_map( 'absint', $ids ) ) );
		if ( empty( $clean_ids ) ) {
			return 0;
		}

		$placeholders = implode( ',', array_fill( 0, count( $clean_ids ), '%d' ) );
		$table        = self::customers_table();
		$params       = array_merge( array( $status ), $clean_ids );

		return (int) $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status = %s WHERE id IN ({$placeholders})", $params ) );
	}

	/**
	 * Delete a customer contact (and optionally its associated submission log rows).
	 */
	public static function delete_customer( int $customer_id, bool $delete_logs = true ): bool {
		global $wpdb;
		if ( $customer_id <= 0 ) {
			return false;
		}
		if ( $delete_logs ) {
			$wpdb->delete( self::leads_table(), array( 'customer_id' => $customer_id ), array( '%d' ) );
		}
		return false !== $wpdb->delete( self::customers_table(), array( 'id' => $customer_id ), array( '%d' ) );
	}

	/**
	 * Delete a single submission log row.
	 */
	public static function delete_lead( int $lead_id ): bool {
		global $wpdb;
		if ( $lead_id <= 0 ) {
			return false;
		}
		return false !== $wpdb->delete( self::leads_table(), array( 'id' => $lead_id ), array( '%d' ) );
	}

	/**
	 * Send notification email to configured staff recipients when enabled.
	 *
	 * @param array<string, mixed> $result Saved record metadata.
	 * @param array<string, mixed> $data   Original submission payload.
	 */
	private static function maybe_send_notification( array $result, array $data ): void {
		$settings = self::get_settings();
		if ( empty( $settings['email_enabled'] ) || empty( $settings['notify_emails'] ) ) {
			return;
		}

		// Skip outbound mail during automated localhost requests unless explicitly filtered.
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( in_array( $host, array( 'localhost', '127.0.0.1' ), true ) && ! apply_filters( 'topasia_quote_send_mail_on_local', false ) ) {
			return;
		}

		$source_labels = self::get_source_labels();
		$source_label  = $source_labels[ $result['source'] ] ?? $result['source'];
		$subject       = sprintf( '[Top Asia] SĐT nhận báo giá mới: %s (%s)', $result['phone'], $result['product'] );

		$lines = array(
			'Website Top Asia vừa ghi nhận một khách hàng để lại số điện thoại:',
			'',
			'Số điện thoại: ' . $result['phone'],
			'Dòng sản phẩm: ' . $result['product'],
			'Nguồn gửi: ' . $source_label,
			'Khách mới: ' . ( ! empty( $result['is_new_customer'] ) ? 'Có (Lần đầu liên hệ)' : 'Khách đã từng gửi trước đó' ),
		);
		if ( ! empty( $data['full_name'] ) ) {
			$lines[] = 'Họ tên: ' . sanitize_text_field( (string) $data['full_name'] );
		}
		if ( ! empty( $data['email'] ) ) {
			$lines[] = 'Email: ' . sanitize_email( (string) $data['email'] );
		}
		if ( ! empty( $data['page_url'] ) ) {
			$lines[] = 'Trang gửi: ' . esc_url_raw( (string) $data['page_url'] );
		}
		$lines[] = 'Thời gian: ' . $result['created_at'];
		$lines[] = '';
		$lines[] = 'Xem danh sách quản lý: ' . admin_url( 'admin.php?page=topasia-quote-leads' );

		$body = implode( "\n", $lines );
		foreach ( (array) $settings['notify_emails'] as $recipient ) {
			if ( is_email( $recipient ) ) {
				wp_mail( $recipient, $subject, $body );
			}
		}
	}

	/**
	 * Resolve client IP address safely without trusting spoofable headers unconditionally.
	 */
	public static function resolve_client_ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? trim( (string) wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return $ip;
		}
		return '';
	}
}
