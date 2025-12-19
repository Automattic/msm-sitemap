<?php

use Automattic\MSM_Sitemap\Site;

if ( ! Site::is_public() ) {
	wp_die(
		__( 'Sorry, this site is not public so sitemaps are not available.', 'msm-sitemap' ),
		__( 'Sitemap Not Available', 'msm-sitemap' ),
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

if ( $build_xml === false ) {
	wp_die(
		__( 'Sorry, no sitemap available here.', 'msm-sitemap' ),
		__( 'Sitemap Not Available', 'msm-sitemap' ),
		array( 'response' => 404 )
	);
}

// Explicitly set 200 OK status to override any previously set status.
//
// WordPress core sitemaps (introduced in WP 5.5) use the same 'sitemap' query var.
// When core's sitemap handler runs and finds no matching core sitemap, it sets a
// 404 status. Even though MSM Sitemap outputs valid XML, the 404 status persists
// because we only set headers for Content-Type, not the HTTP status.
//
// This ensures search engines receive the correct 200 status with our valid sitemap.
//
// @see https://core.trac.wordpress.org/ticket/51136
// @see https://github.com/Automattic/msm-sitemap/pull/168
status_header( 200 );

header( 'Content-type: application/xml; charset=UTF-8' );
echo $build_xml;
