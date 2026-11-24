<?php
/**
 * Refined header and floating contact bar polish, loaded by Novamira sandbox.
 */
if ( ! defined( 'ABSPATH' ) ) {
	return;
}
add_action( 'wp_enqueue_scripts', function () {
	$css = '
	.ocb-root .ocb-bar { gap: 8px !important; padding: 8px 12px !important; background: transparent !important; }
	.ocb-root .ocb-button { height: 48px !important; min-height: 48px !important; border-radius: 8px !important; box-shadow: 0 6px 18px rgba(11,29,58,.16) !important; }
	.ocb-root .ocb-call { background: #D92F35 !important; color: #fff !important; }
	.ocb-root .ocb-zalo, .ocb-root .ocb-messenger { background: #142B49 !important; color: #fff !important; }
	.ocb-root .ocb-messenger svg path:last-child { stroke: #fff !important; }
	@media (max-width: 1023px) {
		.mfp-bg.off-canvas.mfp-ready { background: rgba(11,29,58,.58) !important; backdrop-filter: blur(3px); -webkit-backdrop-filter: blur(3px); }
		.mfp-wrap.off-canvas:has(#main-menu) { box-shadow: 20px 0 64px rgba(11,29,58,.2); overscroll-behavior: contain; }
		body:has(.mfp-wrap.off-canvas.mfp-ready #main-menu) .ocb-root { opacity: 0 !important; visibility: hidden !important; pointer-events: none !important; transform: translateY(10px); transition: opacity .2s ease, transform .2s ease, visibility .2s ease; }
	}
	';
	wp_register_style( 'topasia-proid-inspired-header', false, array(), '1.1' );
	wp_enqueue_style( 'topasia-proid-inspired-header' );
	wp_add_inline_style( 'topasia-proid-inspired-header', $css );
}, 1000 );
