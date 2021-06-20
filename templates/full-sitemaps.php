<?php
/**
 * Sitemap template
 *
 * @package Metro_Sitemap
 *
 * phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
 * phpcs:disable WordPress.Security.NonceVerification.Recommended
 */

if ( ! Metro_Sitemap::is_blog_public() ) {
	wp_die(
		esc_html__( 'Sorry, this site is not public so sitemaps are not available.', 'msm-sitemap' ),
		esc_html__( 'Sitemap Not Available', 'msm-sitemap' ),
		array( 'response' => 404 )
	);
}

$req_year = get_query_var( 'sitemap-year' );
if ( empty( $req_year ) ) {
	$req_year = ( isset( $_GET['yyyy'] ) ) ? intval( $_GET['yyyy'] ) : false;
}

$req_month = ( isset( $_GET['mm'] ) ) ? intval( $_GET['mm'] ) : false;
$req_day   = ( isset( $_GET['dd'] ) ) ? intval( $_GET['dd'] ) : false;

$build_xml = Metro_Sitemap::build_xml(
	array(
		'year'  => $req_year,
		'month' => $req_month,
		'day'   => $req_day,
	)
);

if ( false === $build_xml ) {
	wp_die(
		esc_html__( 'Sorry, no sitemap available here.', 'msm-sitemap' ),
		esc_html__( 'Sitemap Not Available', 'msm-sitemap' ),
		array( 'response' => 404 )
	);
}
header( 'Content-type: application/xml; charset=UTF-8' );
echo $build_xml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
