<?php
/**
 * Refined staging header and contact bar.
 */
if ( ! defined( 'ABSPATH' ) ) { return; }
add_action( 'wp_enqueue_scripts', function () {
	$css = '
	.top-bar { display: none !important; }
	.header-wrapper { background: #fff; }
	.header-main { background: #fff; box-shadow: 0 4px 18px rgba(11,29,58,.07); }
	.header-wrapper .logo img[src*="/themes/flatsome/assets/img/logo.png"] {
		content: url("https://topasia.vn/staging/wp-content/uploads/2026/06/top_asia_logo_full_rectangle_transparent_3000px.png");
		width: 170px;
		height: 52px;
		object-fit: contain;
	}
	.header-wrapper .logo { min-width: 170px; }
	@media (max-width: 849px) {
		.header-wrapper .logo img[src*="/themes/flatsome/assets/img/logo.png"] { width: 150px; height: 46px; }
		.header-wrapper .logo { min-width: 150px; }
	}

	.ocb-root .ocb-bar { gap: 8px !important; padding: 8px 12px !important; background: transparent !important; }
	.ocb-root .ocb-button { height: 48px !important; min-height: 48px !important; border-radius: 8px !important; box-shadow: 0 6px 18px rgba(11,29,58,.16) !important; }
	.ocb-root .ocb-call { background: #E31E24 !important; color: #fff !important; }
	.ocb-root .ocb-zalo, .ocb-root .ocb-messenger { background: #0B1D3A !important; color: #fff !important; }
	.ocb-root .ocb-messenger svg path:last-child { stroke: #fff !important; }
	.ta-mnav { background: #f7f8fb !important; }
	@media (max-width: 1199px) {
		.mfp-bg.off-canvas.mfp-ready { background: rgba(11,29,58,.58) !important; backdrop-filter: blur(3px); -webkit-backdrop-filter: blur(3px); }
		.mfp-wrap.off-canvas:has(#main-menu) { box-shadow: 20px 0 64px rgba(11,29,58,.2); overscroll-behavior: contain; }
		body:has(.mfp-wrap.off-canvas.mfp-ready #main-menu) .ocb-root { opacity: 0 !important; visibility: hidden !important; pointer-events: none !important; transform: translateY(10px); transition: opacity .2s ease, transform .2s ease, visibility .2s ease; }
		#main-menu.mobile-sidebar, #main-menu .sidebar-menu, .ta-mnav { background: #f7f8fb !important; }
		#main-menu .searchform .search-field { height: 46px !important; border-color: #dce3eb !important; border-radius: 12px 0 0 12px !important; font: 400 16px \"Google Sans\", Arial, sans-serif !important; }
		#main-menu .searchform .button, #main-menu .searchform button { background: #e31e24 !important; border-radius: 0 12px 12px 0 !important; }
		.ta-mnav { min-height: calc(100dvh - 80px); animation: ta-menu-sheet-in .36s cubic-bezier(.2,.8,.2,1) both; }
		.ta-mhead { min-height: 76px; padding: max(12px,env(safe-area-inset-top)) 18px 12px 20px; background: #fff; border-bottom: 1px solid #e8edf3; box-shadow: 0 5px 18px rgba(11,29,58,.05); }
		.ta-mlogo img { height: 42px; }
		.ta-mclose { width: 48px; height: 48px; border: 1px solid #f4d5d7; background: #fff5f5; color: #c81920; transition: transform .25s ease, background .2s ease; }
		.ta-mclose:hover, .ta-mclose:focus-visible { transform: rotate(90deg); background: #e31e24; color: #fff; }
		.ta-mviews { padding: 0 18px; }
		.ta-mview { padding: 8px 0 24px; }
		.ta-mview--main { padding-top: 10px; }
		.ta-mnav-list { padding: 0 !important; }
		.ta-mnav-list::before { content: \"KHÁM PHÁ TOP ASIA\"; display: block; padding: 12px 4px 8px; color: #68778a; font: 700 11px/1.4 \"Google Sans\",Arial,sans-serif; letter-spacing: 1.1px; }
		.ta-mitem { margin: 7px 0; overflow: hidden; border: 1px solid #e7ecf2; border-bottom: 1px solid #e7ecf2 !important; border-radius: 14px; background: #fff; box-shadow: 0 3px 12px rgba(11,29,58,.04); transition: transform .2s ease, border-color .2s ease, box-shadow .2s ease; }
		.ta-mitem:active { transform: scale(.99); }
		.ta-mlink, .ta-mtoggle { min-height: 62px; padding: 17px 16px; border-radius: 13px; color: #0b1d3a; font: 600 18px/1.3 \"Google Sans\",Arial,sans-serif !important; }
		.ta-mlink:hover, .ta-mtoggle:hover { background: #f8f9fc; }
		.ta-mlink:focus-visible, .ta-mtoggle:focus-visible, .ta-mback:focus-visible, .ta-mclose:focus-visible, .ta-btn:focus-visible { outline: 3px solid rgba(227,30,36,.32); outline-offset: 2px; }
	';
	wp_register_style( 'topasia-proid-inspired-header', false, array(), '1.0' );
	wp_enqueue_style( 'topasia-proid-inspired-header' );
	wp_add_inline_style( 'topasia-proid-inspired-header', $css );
}, 1000 );