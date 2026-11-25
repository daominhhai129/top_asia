<?php
/**
 * Top Asia Quote Leads Backend Module loader & backwards-compatible helpers.
 *
 * @package TopAsia
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TOPASIA_QUOTE_LEADS_LOADED', true );

require_once __DIR__ . '/quote-leads/class-ta-quote-lead-model.php';
require_once __DIR__ . '/quote-leads/class-ta-quote-download-gate.php';
require_once __DIR__ . '/quote-leads/class-ta-quote-lead-admin.php';

TA_Quote_Download_Gate::boot();
if ( is_admin() ) {
	TA_Quote_Lead_Admin::boot();
}

if ( ! function_exists( 'taf_qd_catalog' ) ) {
	function taf_qd_catalog() {
		return TA_Quote_Lead_Model::get_catalog();
	}
}

if ( ! function_exists( 'taf_qd_resolve_pdf' ) ) {
	function taf_qd_resolve_pdf( $product ) {
		return TA_Quote_Lead_Model::resolve_pdf( (string) $product );
	}
}

if ( ! function_exists( 'taf_qd_localize_home_script' ) ) {
	function taf_qd_localize_home_script() {
		TA_Quote_Download_Gate::localize_home_script();
	}
}

if ( ! function_exists( 'taf_qd_submit_request' ) ) {
	function taf_qd_submit_request() {
		TA_Quote_Download_Gate::handle_submit();
	}
}

if ( ! function_exists( 'taf_qd_download_pdf' ) ) {
	function taf_qd_download_pdf() {
		TA_Quote_Download_Gate::handle_download();
	}
}
