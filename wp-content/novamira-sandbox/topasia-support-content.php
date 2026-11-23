<?php
/**
 * Styling for the technical support page content.
 */
if ( ! defined( 'ABSPATH' ) ) { return; }

add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_page( 670 ) ) { return; }
	$css = '
	.ta-support-content { color: #0B1D3A; }
	.ta-support-content .ta-support-eyebrow { color: #E31E24; font-size: 13px; font-weight: 700; letter-spacing: .08em; }
	.ta-support-content h2 { color: #0B1D3A; font-weight: 800; margin-bottom: 14px; }
	.ta-support-content h3 { color: #0B1D3A; font-weight: 700; font-size: 18px; }
	.ta-support-card, .ta-support-resource { height: 100%; padding: 26px; background: #fff; border: 1px solid #F7F8FA; border-radius: 13px; box-shadow: 0 8px 24px rgba(11,29,58,.06); }
	.ta-support-card { border-top: 3px solid #E31E24; }
	.ta-support-card p, .ta-support-resource p { color: #6B7280; line-height: 1.75; }
	.ta-support-content .button.primary { background: #E31E24; border-color: #E31E24; border-radius: 8px; }
	.ta-support-content .button.is-outline { color: #0B1D3A; border-color: #0B1D3A; border-radius: 8px; }
	@media (max-width: 549px) { .ta-support-card, .ta-support-resource { padding: 20px; } }
	';
	wp_register_style( 'topasia-support-content', false, array(), '1.0' );
	wp_enqueue_style( 'topasia-support-content' );
	wp_add_inline_style( 'topasia-support-content', $css );
}, 99 );
