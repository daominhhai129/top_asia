<?php
/**
 * Render the selected Top Asia demo page at the site front page.
 * Scoped to demo page ID 5784; leaves all other routes on their current templates.
 */
if ( ! defined( 'ABSPATH' ) ) { return; }

add_filter( 'template_include', function ( $template ) {
	$front_page_id = (int) get_option( 'page_on_front' );
	if ( ! is_front_page() || 5784 !== $front_page_id ) {
		return $template;
	}

	$page_template = get_page_template_slug( $front_page_id );
	if ( ! $page_template || 'default' === $page_template ) {
		return $template;
	}

	$resolved_template = locate_template( array( $page_template ), false, false );
	return $resolved_template ? $resolved_template : $template;
}, 99 );

