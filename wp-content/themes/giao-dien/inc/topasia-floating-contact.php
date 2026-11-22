<?php
/**
 * Top Asia — Floating Contact Buttons (Phone & Zalo: 0896 699 996).
 *
 * Desktop: bottom-right floating stack (Call + Zalo) positioned cleanly above #top-link.
 * Mobile: sticky 2-column bottom action bar matching Top Asia Design System.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'topasia_render_floating_contact' ) ) {
	/**
	 * Render Top Asia floating Phone & Zalo contact buttons in wp_footer.
	 */
	function topasia_render_floating_contact() {
		if ( is_admin() || wp_doing_ajax() || is_customize_preview() ) {
			return;
		}
		if ( isset( $_GET['app'] ) || isset( $_GET['uxb_iframe'] ) || isset( $_GET['ux_builder'] ) ) {
			return;
		}
		if ( defined( 'OCB_VERSION' ) || class_exists( 'Ocean_Contact_Buttons' ) ) {
			return;
		}

		$phone_raw     = '0896699996';
		$phone_display = '0896 699 996';
		$zalo_url      = 'https://zalo.me/' . $phone_raw;
		?>
		<div class="ta-float-contact" role="region" aria-label="Liên hệ nhanh Top Asia">
			<a class="ta-fc-btn ta-fc-call" href="tel:<?php echo esc_attr( $phone_raw ); ?>" data-ta-contact="phone" aria-label="Gọi hotline <?php echo esc_attr( $phone_display ); ?>">
				<span class="ta-fc-ic" aria-hidden="true">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
				</span>
				<span class="ta-fc-txt">
					<strong class="ta-fc-label">Gọi ngay</strong>
					<small class="ta-fc-sub"><?php echo esc_html( $phone_display ); ?></small>
				</span>
			</a>

			<a class="ta-fc-btn ta-fc-zalo" href="<?php echo esc_url( $zalo_url ); ?>" target="_blank" rel="noopener noreferrer" data-ta-contact="zalo" aria-label="Chat Zalo <?php echo esc_attr( $phone_display ); ?>">
				<span class="ta-fc-ic ta-fc-zalo-badge" aria-hidden="true">Zalo</span>
				<span class="ta-fc-txt">
					<strong class="ta-fc-label">Chat Zalo</strong>
					<small class="ta-fc-sub"><?php echo esc_html( $phone_display ); ?></small>
				</span>
			</a>
		</div>

		<style>
		.ta-float-contact,
		.ta-float-contact * {
			box-sizing: border-box;
			font-family: 'Google Sans', Arial, sans-serif;
		}
		.ta-float-contact {
			position: fixed;
			z-index: 990;
			right: 20px;
			bottom: 84px;
			display: flex;
			flex-direction: column;
			gap: 10px;
		}
		.ta-fc-btn {
			position: relative;
			display: inline-flex;
			align-items: center;
			gap: 10px;
			min-height: 48px;
			padding: 8px 16px 8px 12px;
			border-radius: 10px;
			color: #FFFFFF !important;
			text-decoration: none !important;
			box-shadow: 0 10px 24px rgba(11, 29, 58, .22);
			transition: transform .18s ease, background-color .18s ease, box-shadow .18s ease;
		}
		.ta-fc-btn:hover {
			transform: translateY(-2px);
			box-shadow: 0 14px 28px rgba(11, 29, 58, .28);
			color: #FFFFFF !important;
		}
		.ta-fc-btn:focus-visible {
			outline: 2px solid #FFFFFF;
			outline-offset: 2px;
		}
		.ta-fc-call {
			background: #E31E24;
		}
		.ta-fc-call:hover {
			background: #C51A1F;
		}
		.ta-fc-call::before {
			content: "";
			position: absolute;
			inset: 0;
			border-radius: inherit;
			border: 2px solid rgba(227, 30, 36, .45);
			animation: ta-fc-pulse 2.2s ease-out infinite;
			pointer-events: none;
		}
		@keyframes ta-fc-pulse {
			0% { transform: scale(1); opacity: .75; }
			75% { transform: scale(1.06, 1.16); opacity: 0; }
			100% { transform: scale(1.06, 1.16); opacity: 0; }
		}
		.ta-fc-zalo {
			background: #0B1D3A;
			border: 1px solid rgba(255, 255, 255, .16);
		}
		.ta-fc-zalo:hover {
			background: #12294F;
		}
		.ta-fc-ic {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			width: 32px;
			height: 32px;
			border-radius: 8px;
			background: rgba(255, 255, 255, .16);
			flex: 0 0 auto;
		}
		.ta-fc-zalo-badge {
			font-size: 11px;
			font-weight: 800;
			letter-spacing: -.01em;
			background: #FFFFFF;
			color: #0B1D3A;
		}
		.ta-fc-txt {
			display: flex;
			flex-direction: column;
			line-height: 1.15;
		}
		.ta-fc-label {
			font-size: 13px;
			font-weight: 800;
			letter-spacing: .02em;
			color: #FFFFFF;
		}
		.ta-fc-sub {
			font-size: 11.5px;
			font-weight: 600;
			color: rgba(255, 255, 255, .88);
			font-variant-numeric: tabular-nums;
			margin-top: 2px;
		}

		@media (min-width: 768px) {
			#top-link.back-to-top {
				bottom: 20px !important;
				right: 20px !important;
			}
		}

		@media (max-width: 767px) {
			body {
				padding-bottom: calc(68px + env(safe-area-inset-bottom));
			}
			.ta-float-contact {
				left: 0;
				right: 0;
				bottom: 0;
				flex-direction: row;
				display: grid;
				grid-template-columns: 1fr 1fr;
				gap: 8px;
				padding: 8px 12px calc(8px + env(safe-area-inset-bottom));
				background: rgba(255, 255, 255, .97);
				backdrop-filter: blur(8px);
				border-top: 1px solid #E4E9F0;
				box-shadow: 0 -6px 20px rgba(11, 29, 58, .10);
			}
			.ta-fc-btn {
				width: 100%;
				min-height: 48px;
				padding: 6px 10px;
				border-radius: 8px;
				justify-content: center;
				box-shadow: none;
			}
			.ta-fc-call::before { display: none; }
			.ta-fc-ic {
				width: 28px;
				height: 28px;
				border-radius: 6px;
			}
			.ta-fc-label { font-size: 12.5px; }
			.ta-fc-sub { font-size: 11px; }
			#top-link.back-to-top {
				bottom: calc(74px + env(safe-area-inset-bottom)) !important;
			}
			.grecaptcha-badge {
				bottom: calc(74px + env(safe-area-inset-bottom)) !important;
				z-index: 980 !important;
			}
			body.page-id-1723 .bgq-sticky {
				bottom: calc(72px + env(safe-area-inset-bottom)) !important;
			}
		}

		@media (prefers-reduced-motion: reduce) {
			.ta-fc-call::before { animation: none; display: none; }
			.ta-fc-btn { transition: none; }
		}

		@media print {
			.ta-float-contact { display: none !important; }
		}
		</style>

		<script>
		(function () {
			document.addEventListener('click', function (e) {
				var btn = e.target.closest('.ta-fc-btn[data-ta-contact]');
				if (!btn) {
					return;
				}
				if (window.dataLayer && typeof window.dataLayer.push === 'function') {
					window.dataLayer.push({
						event: 'contact_button_click',
						contact_channel: btn.getAttribute('data-ta-contact'),
						contact_value: '0896699996'
					});
				}
			});
		})();
		</script>
		<?php
	}
}
add_action( 'wp_footer', 'topasia_render_floating_contact', 40 );
