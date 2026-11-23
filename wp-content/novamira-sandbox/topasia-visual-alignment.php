<?php
/**
 * Top Asia page visual alignment.
 */
if ( ! defined( 'ABSPATH' ) ) { return; }

add_action( 'wp_enqueue_scripts', function () {
	$css = '
	/* Comparison table */
	.yith-woocompare-table th,
	.yith-woocompare-table .product_title,
	#yith-woocompare table th { color: #0B1D3A; }
	.yith-woocompare-table tr:nth-child(even) th,
	.yith-woocompare-table tr:nth-child(even) td,
	#yith-woocompare table tr:nth-child(even) th,
	#yith-woocompare table tr:nth-child(even) td { background: #F7F8FA; }
	.yith-woocompare-table a.button,
	#yith-woocompare table a.button { background: #0B1D3A; border-color: #0B1D3A; color: #fff; }
	.yith-woocompare-table a.button:hover,
	#yith-woocompare table a.button:hover { background: #132A52; border-color: #132A52; }
	.yith-woocompare-table a.compare,
	#yith-woocompare table a.compare { background: #E31E24; border-color: #E31E24; color: #fff; }
	.yith-woocompare-table a.remove,
	#yith-woocompare table a.remove { color: #E31E24; }
	';
	wp_register_style( 'topasia-visual-alignment', false, array(), '1.0' );
	wp_enqueue_style( 'topasia-visual-alignment' );
	wp_add_inline_style( 'topasia-visual-alignment', $css );
}, 99 );
