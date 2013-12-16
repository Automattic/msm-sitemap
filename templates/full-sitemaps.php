<?php

$req_year = ( isset( $_GET['yyyy'] ) ) ? intval( $_GET['yyyy'] ) : false;
$req_month = ( isset( $_GET['mm'] ) ) ? intval( $_GET['mm'] ) : false;
$req_day = ( isset( $_GET['dd'] ) ) ? intval( $_GET['dd'] ) : false;

header( 'Content-type: application/xml; charset=UTF-8' );

echo '<?xml version="1.0" encoding="utf-8"?>';

<<<<<<< HEAD
$build_xml = Metro_Sitemap::build_xml( array( 'year' => $req_year, 'month' => $req_month, 'day' => $req_day ) );
=======
	/** root sitemap */
	if ( false === $req_year && false === $req_month && false === $req_day ) {
		
		$this_year = date( 'Y' );
		$all_posts = get_posts( array( 'post_status' => 'publish', 'order' => 'ASC', 'posts_per_page' => 1, 'post_type' => Metro_Sitemap::get_supported_post_types() ) );
		$oldest_post = $all_posts[0];
		$start_year = substr( $oldest_post->post_date, 0, 4 );
		$years = range( $start_year, $this_year );	
>>>>>>> upstream/master

if ( $build_xml === false ) {
	wp_die( __( 'Sorry, no sitemap available here.', 'msm-sitemap' ) );
} else {
	echo $build_xml;
}

?>
