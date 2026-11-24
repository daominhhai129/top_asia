<?php
/**
 * Child theme footer override for Flatsome.
 *
 * Renders the complete, dynamic Top Asia footer directly from code so staging
 * and local environments stay in sync without relying on stale DB widgets.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'topasia_render_global_footer' ) ) {
	$footer_inc = get_stylesheet_directory() . '/inc/topasia-footer.php';
	if ( file_exists( $footer_inc ) ) {
		require_once $footer_inc;
	}
}

do_action( 'flatsome_before_footer' );
?>
<div class="footer-widgets footer footer-1">
	<?php
	if ( function_exists( 'topasia_render_global_footer' ) ) {
		topasia_render_global_footer();
	}
	?>
</div>

<?php do_action( 'flatsome_after_footer' ); ?>

<?php get_template_part( 'template-parts/footer/footer-absolute' ); ?>
