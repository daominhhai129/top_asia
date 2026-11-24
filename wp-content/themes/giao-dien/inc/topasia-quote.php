<?php
/**
 * Top Asia — Quotation Page (/bao-gia/) Desktop & Mobile Optimizer.
 *
 * Parses the canonical quotation tables from page content so prices never drift,
 * keeps the full desktop comparison tables on viewports >= 768px, and renders
 * a dedicated mobile-first quotation app (sticky segment tabs, thickness filter
 * chips, zero-horizontal-scroll price cards, instant m2 estimator, and synced
 * B2B quote form + PDF download gate) for mobile phones <= 767px.
 *
 * @package TopAsia
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Determine whether current request renders the main quotation page (/bao-gia/).
 */
function topasia_is_quote_page() {
	if ( is_admin() || wp_doing_ajax() || is_customize_preview() ) {
		return false;
	}
	if ( isset( $_GET['app'] ) || isset( $_GET['uxb_iframe'] ) || isset( $_GET['ux_builder'] ) ) {
		return false;
	}
	return is_page( 'bao-gia' ) || is_page( 1723 );
}

/**
 * Add body class for targeted styling on /bao-gia/.
 */
add_filter( 'body_class', function ( $classes ) {
	if ( topasia_is_quote_page() ) {
		$classes[] = 'ta-quote-page';
	}
	return $classes;
} );

/**
 * Enqueue dedicated quotation page CSS and JS after theme styles.
 */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! topasia_is_quote_page() ) {
		return;
	}

	$dir = get_stylesheet_directory();
	$uri = get_stylesheet_directory_uri();
	$ver = function ( $rel ) use ( $dir ) {
		$path = $dir . $rel;
		return file_exists( $path ) ? (string) filemtime( $path ) : '1.0.0';
	};

	wp_enqueue_style(
		'ta-quote',
		$uri . '/assets/ta-quote.css',
		array( 'flatsome-style', 'topasia-menu' ),
		$ver( '/assets/ta-quote.css' )
	);

	wp_enqueue_script(
		'ta-quote',
		$uri . '/assets/ta-quote.js',
		array(),
		$ver( '/assets/ta-quote.js' ),
		true
	);

	if ( function_exists( 'taf_qd_submit_request' ) && function_exists( 'taf_qd_catalog' ) ) {
		wp_localize_script(
			'ta-quote',
			'TAFQuoteConfig',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'downloadUrl' => admin_url( 'admin-post.php' ),
				'nonce'       => wp_create_nonce( 'taf_qd_submit' ),
			)
		);
	}
}, 110 );

/**
 * Extract numeric VND amount from strings like "301.000đ" or "1.023.000 đ".
 */
function topasia_quote_parse_vnd( $text ) {
	$clean = preg_replace( '/[^\d]/', '', (string) $text );
	return '' !== $clean ? (int) $clean : 0;
}

/**
 * Classify a thickness label into mobile quick-filter buckets.
 */
function topasia_quote_thick_bucket( $thick_text ) {
	if ( preg_match_all( '/(\d+(?:[.,]\d+)?)/', (string) $thick_text, $m ) && ! empty( $m[1] ) ) {
		$vals = array_map( function ( $v ) {
			return (float) str_replace( ',', '.', $v );
		}, $m[1] );
		$max_val = max( $vals );
		if ( $max_val <= 2.75 ) {
			return 'thin';
		}
		if ( $max_val <= 5.5 ) {
			return 'mid';
		}
		return 'thick';
	}
	return 'mid';
}

/**
 * Check if a thickness is among the most requested specs for quick filtering.
 */
function topasia_quote_is_popular_thick( $thick_text ) {
	$t = mb_strtolower( trim( (string) $thick_text ), 'UTF-8' );
	$popular = array( '2,0mm', '3,0mm', '4,0mm', '4,7mm', '5,0mm', '6,0mm', '8,0mm', '10,0mm' );
	return in_array( $t, $popular, true );
}

/**
 * Append CSS classes to a DOMElement cleanly without duplicates.
 */
function topasia_quote_add_class( DOMElement $el, $extra ) {
	$existing = trim( (string) $el->getAttribute( 'class' ) );
	$parts    = '' !== $existing ? preg_split( '/\s+/', $existing ) : array();
	foreach ( preg_split( '/\s+/', trim( (string) $extra ) ) as $cls ) {
		if ( '' !== $cls && ! in_array( $cls, $parts, true ) ) {
			$parts[] = $cls;
		}
	}
	$el->setAttribute( 'class', implode( ' ', $parts ) );
}

/**
 * Parse a single product-line container (#gia-asia-gold / pearl / lite).
 *
 * @return array|null
 */
function topasia_quote_extract_line_data( DOMXPath $xpath, DOMElement $container, array $meta ) {
	$sub_el  = $xpath->query( './/*[contains(concat(" ", normalize-space(@class), " "), " tap-cat-bar ")]//p', $container )->item( 0 );
	$link_el = $xpath->query( './/*[contains(concat(" ", normalize-space(@class), " "), " tap-cat-bar ")]//a', $container )->item( 0 );
	$h3_el   = $xpath->query( './/*[contains(concat(" ", normalize-space(@class), " "), " tap-cat-bar ")]//h3', $container )->item( 0 );
	$rows    = $xpath->query( './/table//tbody/tr', $container );

	if ( ! $rows || 0 === $rows->length ) {
		return null;
	}

	$headers = array();
	$th_nodes = $xpath->query( './/table//thead//th', $container );
	foreach ( $th_nodes as $th ) {
		$headers[] = mb_strtolower( trim( $th->textContent ), 'UTF-8' );
	}

	$has_width  = in_array( 'khổ rộng', $headers, true );
	$has_color  = in_array( 'màu sắc', $headers, true );
	$has_promo  = in_array( 'giá khuyến mãi', $headers, true );

	$items       = array();
	$min_price   = 0;

	foreach ( $rows as $tr ) {
		$cells = $xpath->query( './th|./td', $tr );
		if ( ! $cells || $cells->length < 3 ) {
			continue;
		}

		$thick = trim( preg_replace( '/\s+/', ' ', $cells->item( 0 )->textContent ) );
		$unit  = trim( preg_replace( '/\s+/', ' ', $cells->item( 1 )->textContent ) );

		$width      = '';
		$colors     = '';
		$ref_text   = '';
		$sale_text  = '';

		if ( $has_width && $has_color && $has_promo && $cells->length >= 6 ) {
			$width     = trim( preg_replace( '/\s+/', ' ', $cells->item( 2 )->textContent ) );
			$colors    = trim( preg_replace( '/\s+/', ' ', $cells->item( 3 )->textContent ) );
			$ref_text  = trim( preg_replace( '/\s+/', ' ', $cells->item( 4 )->textContent ) );
			$sale_text = trim( preg_replace( '/\s+/', ' ', $cells->item( 5 )->textContent ) );
		} else {
			$ref_text = trim( preg_replace( '/\s+/', ' ', $cells->item( 2 )->textContent ) );
		}

		$ref_num  = topasia_quote_parse_vnd( $ref_text );
		$sale_num = topasia_quote_parse_vnd( $sale_text );

		$effective_num = $sale_num > 0 ? $sale_num : $ref_num;
		if ( $effective_num > 0 && ( 0 === $min_price || $effective_num < $min_price ) ) {
			$min_price = $effective_num;
		}

		$discount_pct = 0;
		if ( $sale_num > 0 && $ref_num > $sale_num ) {
			$discount_pct = (int) round( ( ( $ref_num - $sale_num ) / $ref_num ) * 100 );
		}

		if ( '—' === $width || '-' === $width ) {
			$width = '';
		}
		if ( '—' === $colors || '-' === $colors ) {
			$colors = '';
		}

		$items[] = array(
			'thickness'    => $thick,
			'unit'         => $unit ? $unit : 'm²',
			'width'        => $width,
			'colors'       => $colors,
			'ref_text'     => $ref_text,
			'ref_num'      => $ref_num,
			'sale_text'    => $sale_text,
			'sale_num'     => $sale_num,
			'discount_pct' => $discount_pct,
			'bucket'       => topasia_quote_thick_bucket( $thick ),
			'popular'      => topasia_quote_is_popular_thick( $thick ),
		);
	}

	if ( empty( $items ) ) {
		return null;
	}

	$starting_display = $meta['fallback_price'];
	if ( $min_price > 0 ) {
		$starting_display = number_format( $min_price, 0, ',', '.' ) . 'đ/m²';
	}

	return array(
		'slug'         => $meta['slug'],
		'name'         => $meta['name'],
		'tier'         => $meta['tier'],
		'badge'        => $meta['badge'],
		'highlights'   => $meta['highlights'],
		'heading'      => $h3_el ? trim( $h3_el->textContent ) : $meta['name'],
		'subtitle'     => $sub_el ? trim( $sub_el->textContent ) : $meta['highlights'],
		'cat_url'      => $link_el ? trim( (string) $link_el->getAttribute( 'href' ) ) : $meta['fallback_url'],
		'min_price'    => $min_price,
		'from_label'   => 'Từ ' . $starting_display,
		'items'        => $items,
	);
}

/**
 * Render the dedicated mobile quotation workspace HTML (<= 767px only).
 */
function topasia_quote_render_mobile_app( array $lines, $has_pdf_gate ) {
	if ( empty( $lines ) ) {
		return '';
	}

	$customer_types = array(
		array(
			'label' => 'Chủ đầu tư / Nhà thầu',
			'form'  => 'Chủ đầu tư & Nhà thầu',
			'tag'   => 'Dự án · CO/CQ',
		),
		array(
			'label' => 'Đại lý / Phân phối',
			'form'  => 'Đại lý & Nhà phân phối',
			'tag'   => 'Giá cuộn · Chiết khấu',
		),
		array(
			'label' => 'Khách mua lẻ',
			'form'  => 'Nhu cầu cá nhân',
			'tag'   => 'Cắt theo kích thước',
		),
	);

	ob_start();
	?>
	<div id="bgq-mobile-app" class="bgq-mobile-only" aria-label="Bảng giá Polycarbonate tối ưu cho điện thoại">
		<!-- Bước 1 rút gọn: chọn đối tượng nhanh -->
		<div class="bgq-m-block">
			<div class="bgq-m-step-head">
				<span class="bgq-m-step-num">01</span>
				<div>
					<h2 class="bgq-m-step-title">Bạn thuộc nhóm khách hàng nào?</h2>
					<p class="bgq-m-step-sub">Chạm để đồng bộ sẵn vào phiếu báo giá bên dưới</p>
				</div>
			</div>
			<div class="bgq-m-role-grid" role="group" aria-label="Chọn đối tượng khách hàng">
				<?php foreach ( $customer_types as $ct ) : ?>
					<button
						type="button"
						class="bgq-m-role"
						data-bgq="kh"
						data-value="<?php echo esc_attr( $ct['form'] ); ?>"
						aria-pressed="false"
					>
						<span class="bgq-m-role-name"><?php echo esc_html( $ct['label'] ); ?></span>
						<span class="bgq-m-role-tag"><?php echo esc_html( $ct['tag'] ); ?></span>
					</button>
				<?php endforeach; ?>
			</div>
		</div>

		<!-- Thanh chuyển dòng sản phẩm (sticky dưới header khi cuộn trên mobile) -->
		<div class="bgq-m-tabs-sticky" data-bgq-sticky-tabs>
			<div class="bgq-m-tabs-head">
				<div class="bgq-m-step-head bgq-m-step-head--compact">
					<span class="bgq-m-step-num">02</span>
					<div>
						<h2 class="bgq-m-step-title">Chọn dòng &amp; Xem giá (đ/m²)</h2>
					</div>
				</div>
				<?php if ( $has_pdf_gate ) : ?>
					<button type="button" class="bgq-m-pdf-chip" data-ta-open-quote aria-haspopup="dialog" aria-controls="ta-quote-modal">
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12m0 0-4-4m4 4 4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
						<span>Tải PDF</span>
					</button>
				<?php endif; ?>
			</div>

			<div class="bgq-m-tablist" role="tablist" aria-label="Dòng sản phẩm Top Asia">
				<?php foreach ( $lines as $idx => $line ) : ?>
					<button
						type="button"
						role="tab"
						id="bgq-mtab-<?php echo esc_attr( $line['slug'] ); ?>"
						class="bgq-m-tab bgq-m-tab--<?php echo esc_attr( $line['tier'] ); ?><?php echo 0 === $idx ? ' is-active' : ''; ?>"
						aria-selected="<?php echo 0 === $idx ? 'true' : 'false'; ?>"
						aria-controls="bgq-mpanel-<?php echo esc_attr( $line['slug'] ); ?>"
						data-bgq-mtab="<?php echo esc_attr( $line['slug'] ); ?>"
						data-value="<?php echo esc_attr( $line['name'] ); ?>"
					>
						<span class="bgq-m-tab-tier"><?php echo esc_html( $line['badge'] ); ?></span>
						<strong class="bgq-m-tab-name"><?php echo esc_html( $line['name'] ); ?></strong>
						<span class="bgq-m-tab-price"><?php echo esc_html( $line['from_label'] ); ?></span>
					</button>
				<?php endforeach; ?>
			</div>
		</div>

		<!-- Nội dung từng dòng sản phẩm -->
		<?php foreach ( $lines as $idx => $line ) : ?>
			<?php
			$row_count = count( $line['items'] );
			$show_filters = $row_count > 8;
			?>
			<div
				id="bgq-mpanel-<?php echo esc_attr( $line['slug'] ); ?>"
				class="bgq-m-panel<?php echo 0 === $idx ? ' is-active' : ''; ?>"
				role="tabpanel"
				aria-labelledby="bgq-mtab-<?php echo esc_attr( $line['slug'] ); ?>"
				data-bgq-mpanel="<?php echo esc_attr( $line['slug'] ); ?>"
				<?php echo 0 === $idx ? '' : ' hidden'; ?>
			>
				<!-- Tóm tắt dòng sản phẩm -->
				<div class="bgq-m-summary bgq-m-summary--<?php echo esc_attr( $line['tier'] ); ?>">
					<div class="bgq-m-summary-top">
						<div>
							<span class="bgq-m-badge bgq-m-badge--<?php echo esc_attr( $line['tier'] ); ?>"><?php echo esc_html( $line['badge'] ); ?></span>
							<h3 class="bgq-m-summary-title"><?php echo esc_html( $line['heading'] ); ?></h3>
						</div>
						<span class="bgq-m-count"><?php echo esc_html( $row_count . ' quy cách' ); ?></span>
					</div>
					<p class="bgq-m-summary-desc"><?php echo esc_html( $line['subtitle'] ); ?></p>
					<div class="bgq-m-summary-foot">
						<span class="bgq-m-highlight"><?php echo esc_html( $line['highlights'] ); ?></span>
						<?php if ( ! empty( $line['cat_url'] ) ) : ?>
							<a class="bgq-m-catlink" href="<?php echo esc_url( $line['cat_url'] ); ?>">Xem sản phẩm →</a>
						<?php endif; ?>
					</div>
				</div>

				<!-- Bộ lọc nhanh độ dày (cho bảng có nhiều quy cách như Asia Gold) -->
				<?php if ( $show_filters ) : ?>
					<div class="bgq-m-filters" role="group" aria-label="Lọc nhanh theo độ dày">
						<button type="button" class="bgq-m-chip is-active" data-bgq-filter="all" data-line="<?php echo esc_attr( $line['slug'] ); ?>" aria-pressed="true">Tất cả (<?php echo (int) $row_count; ?>)</button>
						<button type="button" class="bgq-m-chip" data-bgq-filter="popular" data-line="<?php echo esc_attr( $line['slug'] ); ?>" aria-pressed="false">Phổ biến</button>
						<button type="button" class="bgq-m-chip" data-bgq-filter="thin" data-line="<?php echo esc_attr( $line['slug'] ); ?>" aria-pressed="false">Mỏng ≤ 2,7mm</button>
						<button type="button" class="bgq-m-chip" data-bgq-filter="mid" data-line="<?php echo esc_attr( $line['slug'] ); ?>" aria-pressed="false">Trung 3–5mm</button>
						<button type="button" class="bgq-m-chip" data-bgq-filter="thick" data-line="<?php echo esc_attr( $line['slug'] ); ?>" aria-pressed="false">Dày ≥ 6mm</button>
					</div>
				<?php endif; ?>

				<!-- Danh sách thẻ giá tối ưu mobile (0 cuộn ngang) -->
				<div class="bgq-m-list" data-bgq-list="<?php echo esc_attr( $line['slug'] ); ?>">
					<?php foreach ( $line['items'] as $item ) : ?>
						<?php
						$has_sale      = $item['sale_num'] > 0;
						$active_price  = $has_sale ? $item['sale_num'] : $item['ref_num'];
						$is_contact    = 0 === $active_price;
						$buckets       = array( $item['bucket'] );
						if ( $item['popular'] ) {
							$buckets[] = 'popular';
						}
						?>
						<article
							class="bgq-m-card<?php echo $item['popular'] ? ' is-popular' : ''; ?>"
							data-bgq-card
							data-line="<?php echo esc_attr( $line['name'] ); ?>"
							data-thick="<?php echo esc_attr( $item['thickness'] ); ?>"
							data-price="<?php echo (int) $active_price; ?>"
							data-buckets="<?php echo esc_attr( implode( ' ', $buckets ) ); ?>"
						>
							<div class="bgq-m-card-main">
								<div class="bgq-m-card-left">
									<div class="bgq-m-thick-row">
										<strong class="bgq-m-thick"><?php echo esc_html( $item['thickness'] ); ?></strong>
										<?php if ( $item['discount_pct'] > 0 ) : ?>
											<span class="bgq-m-off">-<?php echo (int) $item['discount_pct']; ?>%</span>
										<?php endif; ?>
									</div>
									<?php if ( '' !== $item['width'] ) : ?>
										<div class="bgq-m-spec"><span>Khổ:</span> <?php echo esc_html( $item['width'] ); ?></div>
									<?php endif; ?>
								</div>

								<div class="bgq-m-card-right">
									<?php if ( $is_contact ) : ?>
										<span class="bgq-m-price-contact">Liên hệ báo giá</span>
									<?php elseif ( $has_sale ) : ?>
										<div class="bgq-m-price-sale"><?php echo esc_html( $item['sale_text'] ); ?><small>/<?php echo esc_html( $item['unit'] ); ?></small></div>
										<div class="bgq-m-price-ref">Niêm yết: <s><?php echo esc_html( $item['ref_text'] ); ?></s></div>
									<?php else : ?>
										<div class="bgq-m-price-std"><?php echo esc_html( $item['ref_text'] ); ?><small>/<?php echo esc_html( $item['unit'] ); ?></small></div>
										<?php if ( false !== stripos( $item['sale_text'], 'Liên hệ' ) ) : ?>
											<div class="bgq-m-price-note">KM: Liên hệ nhà máy</div>
										<?php endif; ?>
									<?php endif; ?>
								</div>
							</div>

							<?php if ( '' !== $item['colors'] ) : ?>
								<div class="bgq-m-colors">
									<strong>Màu sắc:</strong> <?php echo esc_html( $item['colors'] ); ?>
								</div>
							<?php endif; ?>

							<div class="bgq-m-card-foot">
								<button
									type="button"
									class="bgq-m-calc-trigger"
									data-bgq-pick-calc
									data-line="<?php echo esc_attr( $line['name'] ); ?>"
									data-thick="<?php echo esc_attr( $item['thickness'] ); ?>"
									data-price="<?php echo (int) $active_price; ?>"
								>
									<?php echo $active_price > 0 ? 'Ước tính m²' : 'Ghi chú độ dày'; ?>
								</button>
								<button
									type="button"
									class="bgq-m-rowbtn"
									data-bgq="row"
									data-value="<?php echo esc_attr( $line['name'] ); ?>"
									data-thick="<?php echo esc_attr( $item['thickness'] ); ?>"
									data-price="<?php echo (int) $active_price; ?>"
								>
									<span>Chọn nhận báo giá</span>
									<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
								</button>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endforeach; ?>

		<!-- Dự toán nhanh chi phí vật tư theo m2 trên điện thoại -->
		<section class="bgq-m-calc" id="bgq-m-calc" aria-labelledby="bgq-m-calc-title">
			<div class="bgq-m-calc-head">
				<div>
					<span class="bgq-m-calc-kicker">Tiện ích dự toán nhanh</span>
					<h3 id="bgq-m-calc-title">Ước tính chi phí theo diện tích (m²)</h3>
				</div>
			</div>
			<div class="bgq-m-calc-body">
				<div class="bgq-m-calc-field">
					<label for="bgq-calc-spec">Quy cách đã chọn</label>
					<select id="bgq-calc-spec" class="bgq-m-calc-select">
						<?php foreach ( $lines as $line ) : ?>
							<optgroup label="<?php echo esc_attr( $line['name'] ); ?>">
								<?php foreach ( $line['items'] as $item ) : ?>
									<?php
									$p = $item['sale_num'] > 0 ? $item['sale_num'] : $item['ref_num'];
									$p_txt = $p > 0 ? number_format( $p, 0, ',', '.' ) . 'đ/m²' : 'Liên hệ';
									?>
									<option
										value="<?php echo esc_attr( $line['name'] . '|' . $item['thickness'] ); ?>"
										data-line="<?php echo esc_attr( $line['name'] ); ?>"
										data-thick="<?php echo esc_attr( $item['thickness'] ); ?>"
										data-price="<?php echo (int) $p; ?>"
									>
										<?php echo esc_html( $line['name'] . ' · ' . $item['thickness'] . ' (' . $p_txt . ')' ); ?>
									</option>
								<?php endforeach; ?>
							</optgroup>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="bgq-m-calc-row">
					<div class="bgq-m-calc-field">
						<label for="bgq-calc-area">Diện tích dự kiến (m²)</label>
						<input id="bgq-calc-area" type="number" inputmode="decimal" min="1" step="1" value="100" placeholder="VD: 100">
					</div>
					<label class="bgq-m-calc-uv">
						<input id="bgq-calc-uv" type="checkbox">
						<span>Phủ UV (+20.000đ/m²)</span>
					</label>
				</div>

				<div class="bgq-m-calc-result" aria-live="polite">
					<div>
						<span class="bgq-m-calc-res-label">Tổng tạm tính (chưa VAT &amp; ship)</span>
						<strong class="bgq-m-calc-res-total" id="bgq-calc-total">—</strong>
					</div>
					<button type="button" class="bgq-m-calc-apply" id="bgq-calc-apply">Gửi báo giá số lượng này →</button>
				</div>
			</div>
		</section>
	</div>
	<?php
	return trim( ob_get_clean() );
}

/**
 * Transform /bao-gia/ content to cleanly separate Desktop tables and Mobile view
 * while sharing one canonical contact form (#form-bao-gia), FAQ, and documents.
 */
add_filter( 'the_content', function ( $content ) {
	if ( ! topasia_is_quote_page() || ! is_main_query() || ! in_the_loop() ) {
		return $content;
	}
	if ( false === strpos( $content, 'tap-bgq' ) || false === strpos( $content, 'id="bang-gia"' ) ) {
		return $content;
	}

	// Remove legacy inline script inside .tap-bgq (replaced cleanly by assets/ta-quote.js).
	$content = preg_replace(
		'/<script\b[^>]*>(?:(?!<\/script>)[\s\S])*?quote_customer_type_select(?:(?!<\/script>)[\s\S])*?<\/script>/i',
		'',
		$content
	);

	// Protect remaining shortcodes and any remaining script tags from libxml HTML normalization.
	$placeholders = array();
	$content      = preg_replace_callback(
		'/(<script\b[^>]*>[\s\S]*?<\/script>|\[contact-form-7[^\]]*\])/i',
		function ( $m ) use ( &$placeholders ) {
			$key                  = '<!--TA_BGQ_PH_' . count( $placeholders ) . '-->';
			$placeholders[ $key ] = $m[0];
			return $key;
		},
		$content
	);

	$encoded = mb_encode_numericentity( $content, array( 0x80, 0x10FFFF, 0, ~0 ), 'UTF-8' );
	$prev    = libxml_use_internal_errors( true );
	$dom     = new DOMDocument( '1.0', 'UTF-8' );
	$loaded  = $dom->loadHTML( '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body><div id="bgq-root-wrap">' . $encoded . '</div></body></html>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
	libxml_clear_errors();
	libxml_use_internal_errors( $prev );

	if ( ! $loaded ) {
		return strtr( $content, $placeholders );
	}

	$xpath = new DOMXPath( $dom );
	$root  = $xpath->query( '//div[contains(concat(" ", normalize-space(@class), " "), " tap-bgq ")]' )->item( 0 );
	if ( ! ( $root instanceof DOMElement ) ) {
		return strtr( $content, $placeholders );
	}

	// Mark hero, Desktop Step 1, and Desktop Step 2.
	$hero = $xpath->query( './/section[contains(concat(" ", normalize-space(@class), " "), " tap-hero ")]', $root )->item( 0 );
	if ( $hero instanceof DOMElement ) {
		topasia_quote_add_class( $hero, 'bgq-hero' );
	}

	$sections = $xpath->query( './section', $root );
	foreach ( $sections as $sec ) {
		if ( ! ( $sec instanceof DOMElement ) ) {
			continue;
		}
		$sec_id = (string) $sec->getAttribute( 'id' );
		if ( 'bang-gia' === $sec_id || 'form-bao-gia' === $sec_id ) {
			continue;
		}
		$has_kh   = $xpath->query( './/*[contains(concat(" ", normalize-space(@class), " "), " bgq-kh ")]', $sec )->length > 0;
		$has_line = $xpath->query( './/*[contains(concat(" ", normalize-space(@class), " "), " bgq-line-btn ")]', $sec )->length > 0;
		if ( $has_kh || $has_line ) {
			topasia_quote_add_class( $sec, 'bgq-desktop-only' );
		}
	}

	// Parse live pricing blocks inside #bang-gia.
	$bang_gia = $dom->getElementById( 'bang-gia' );
	if ( ! ( $bang_gia instanceof DOMElement ) ) {
		return strtr( $content, $placeholders );
	}

	$line_meta = array(
		'gia-asia-gold'  => array(
			'slug'           => 'asia-gold',
			'name'           => 'Asia Gold',
			'tier'           => 'gold',
			'badge'          => 'Phổ thông',
			'highlights'     => '1,5–10mm · Bảo hành 10 năm',
			'fallback_price' => '178.000đ/m²',
			'fallback_url'   => '/danh-muc-san-pham/tam-nhua-polycarbonate-cuong-luc-asia-gold/',
		),
		'gia-asia-pearl' => array(
			'slug'           => 'asia-pearl',
			'name'           => 'Asia Pearl',
			'tier'           => 'pearl',
			'badge'          => 'Chuyên nghiệp',
			'highlights'     => '3–18mm · Nhựa SABIC (Ả Rập)',
			'fallback_price' => '417.000đ/m²',
			'fallback_url'   => '/danh-muc-san-pham/tam-nhua-polycarbonate-cuong-luc-asia-pearl/',
		),
		'gia-asia-lite'  => array(
			'slug'           => 'asia-lite',
			'name'           => 'Asia Lite',
			'tier'           => 'lite',
			'badge'          => 'Cao cấp',
			'highlights'     => '3–18mm · Makrolon (Đức) / Lotte (Hàn)',
			'fallback_price' => '480.000đ/m²',
			'fallback_url'   => '/danh-muc-san-pham/tam-nhua-polycarbonate-cuong-luc-asia-lite/',
		),
	);

	$parsed_lines = array();
	$desktop_wrap_targets = array();

	$bang_wrap = $xpath->query( './div[contains(concat(" ", normalize-space(@class), " "), " tap-wrap ")]', $bang_gia )->item( 0 );
	if ( $bang_wrap instanceof DOMElement ) {
		foreach ( $bang_wrap->childNodes as $child ) {
			if ( ! ( $child instanceof DOMElement ) ) {
				continue;
			}
			$cid = (string) $child->getAttribute( 'id' );
			$ccls = (string) $child->getAttribute( 'class' );
			if ( false !== strpos( $ccls, 'tap-heading' ) || isset( $line_meta[ $cid ] ) ) {
				topasia_quote_add_class( $child, 'bgq-desktop-only' );
				$desktop_wrap_targets[] = $child;
			} elseif ( false !== strpos( $ccls, 'tap-card' ) ) {
				topasia_quote_add_class( $child, 'bgq-conditions-card' );
			}
		}
	}

	foreach ( $line_meta as $id => $meta ) {
		$block = $dom->getElementById( $id );
		if ( $block instanceof DOMElement ) {
			$extracted = topasia_quote_extract_line_data( $xpath, $block, $meta );
			if ( $extracted ) {
				$parsed_lines[] = $extracted;
			}
		}
	}

	if ( ! empty( $parsed_lines ) && $bang_wrap instanceof DOMElement ) {
		$has_pdf_gate = function_exists( 'taf_qd_submit_request' ) && function_exists( 'taf_qd_catalog' );
		$mobile_html  = topasia_quote_render_mobile_app( $parsed_lines, $has_pdf_gate );

		if ( '' !== $mobile_html ) {
			$frag_doc = new DOMDocument( '1.0', 'UTF-8' );
			$frag_enc = mb_encode_numericentity( $mobile_html, array( 0x80, 0x10FFFF, 0, ~0 ), 'UTF-8' );
			$prev2    = libxml_use_internal_errors( true );
			$ok_frag  = $frag_doc->loadHTML( '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>' . $frag_enc . '</body></html>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
			libxml_clear_errors();
			libxml_use_internal_errors( $prev2 );

			if ( $ok_frag ) {
				$frag_body = $frag_doc->getElementsByTagName( 'body' )->item( 0 );
				if ( $frag_body ) {
					$first_child = $bang_wrap->firstChild;
					foreach ( $frag_body->childNodes as $node ) {
						$imported = $dom->importNode( $node, true );
						if ( $first_child ) {
							$bang_wrap->insertBefore( $imported, $first_child );
						} else {
							$bang_wrap->appendChild( $imported );
						}
					}
				}
			}
		}
	}

	// Enhance hero actions with a clean mobile PDF trigger when download gate is loaded.
	if ( $hero instanceof DOMElement && function_exists( 'taf_qd_submit_request' ) ) {
		$actions = $xpath->query( './/*[contains(concat(" ", normalize-space(@class), " "), " tap-hero-actions ")]', $hero )->item( 0 );
		if ( $actions instanceof DOMElement ) {
			$pdf_btn = $dom->createElement( 'button' );
			$pdf_btn->setAttribute( 'type', 'button' );
			$pdf_btn->setAttribute( 'class', 'tap-btn tap-btn--outline bgq-hero-pdf bgq-mobile-only' );
			$pdf_btn->setAttribute( 'data-ta-open-quote', '1' );
			$pdf_btn->setAttribute( 'aria-haspopup', 'dialog' );
			$pdf_btn->setAttribute( 'aria-controls', 'ta-quote-modal' );
			$pdf_btn->appendChild( $dom->createTextNode( 'Tải bảng giá PDF' ) );
			$actions->appendChild( $pdf_btn );
		}
	}

	// Clean class on #form-bao-gia section so customizer ".bgq-form" rules target the inner card only.
	$form_sec = $dom->getElementById( 'form-bao-gia' );
	if ( $form_sec instanceof DOMElement ) {
		topasia_quote_add_class( $form_sec, 'bgq-form-section' );
	}

	$root_wrap = $dom->getElementById( 'bgq-root-wrap' );
	if ( ! ( $root_wrap instanceof DOMElement ) ) {
		return strtr( $content, $placeholders );
	}

	$out = '';
	foreach ( $root_wrap->childNodes as $node ) {
		$out .= $dom->saveHTML( $node );
	}

	return strtr( $out, $placeholders );
}, 9 );

/**
 * Render the phone-gated PDF download modal on /bao-gia/ (reusing taf_qd_submit).
 */
add_action( 'wp_footer', function () {
	if ( ! topasia_is_quote_page() || is_front_page() ) {
		return;
	}
	if ( ! function_exists( 'taf_qd_submit_request' ) || ! function_exists( 'taf_qd_catalog' ) ) {
		return;
	}
	?>
	<div id="ta-quote-modal" class="ta-modal bgq-pdf-modal" role="dialog" aria-modal="true" aria-labelledby="ta-quote-modal-title" aria-hidden="true" inert>
		<div class="ta-modal__backdrop" data-ta-modal-close></div>
		<div class="ta-modal__dialog">
			<button type="button" class="ta-modal__close" data-ta-modal-close aria-label="Đóng cửa sổ tải báo giá">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
			</button>

			<div class="ta-qmodal__form-view" data-ta-qview="form">
				<span class="ta-eyebrow">Bảng giá nhà máy Top Asia</span>
				<h2 id="ta-quote-modal-title" class="ta-qmodal__title">Tải bảng báo giá PDF</h2>
				<p class="ta-qmodal__desc">Chọn dòng sản phẩm và nhập số điện thoại để tải ngay file báo giá niêm yết mới nhất.</p>

				<form id="ta-quote-download-form" class="ta-qmodal__form" novalidate>
					<fieldset class="ta-qmodal__lines">
						<legend class="ta-qmodal__label">Chọn dòng bảng giá</legend>
						<div class="ta-qmodal__pills">
							<label class="ta-qpill">
								<input type="radio" name="product" value="asia-gold" checked>
								<span>Asia Gold</span>
							</label>
							<label class="ta-qpill">
								<input type="radio" name="product" value="asia-pearl">
								<span>Asia Pearl</span>
							</label>
							<label class="ta-qpill">
								<input type="radio" name="product" value="asia-lite">
								<span>Asia Lite</span>
							</label>
						</div>
					</fieldset>

					<div class="ta-qmodal__field">
						<label for="ta-qd-phone" class="ta-qmodal__label">Số điện thoại nhận bảng giá <span aria-hidden="true">*</span></label>
						<input id="ta-qd-phone" type="tel" name="phone" inputmode="tel" autocomplete="tel" placeholder="VD: 0912 345 678" required>
					</div>

					<input type="text" name="taf_qd_company" tabindex="-1" autocomplete="off" class="ta-qmodal__hp" aria-hidden="true">

					<p class="ta-qmodal__error" role="alert" hidden></p>

					<button type="submit" class="tx-btn tx-btn--accent tx-btn--lg ta-qmodal__submit">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12m0 0-4-4m4 4 4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
						<span>Xác nhận &amp; Tải bảng giá</span>
					</button>
				</form>
			</div>

			<div class="ta-qmodal__success-view" data-ta-qview="success" hidden>
				<div class="ta-qmodal__badge" aria-hidden="true">
					<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
				</div>
				<h2 class="ta-qmodal__title">Đã mở khóa bảng báo giá!</h2>
				<p class="ta-qmodal__desc">Tệp báo giá bạn chọn đang tự động tải về. Bạn cũng có thể bấm chọn tải thêm bảng giá các dòng khác:</p>
				<div class="ta-qmodal__downloads" data-ta-qdownloads></div>
				<button type="button" class="tx-btn tx-btn--ghost ta-qmodal__done" data-ta-modal-close>Đóng cửa sổ</button>
			</div>
		</div>
	</div>
	<?php
}, 35 );
