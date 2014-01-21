<?php

	if ( ! Metro_Sitemap::is_blog_public() ) {
		wp_die( 
			__( 'Sorry, this site is not public so sitemaps are not available.', 'msm-sitemap' ),
			__( 'Sitemap Not Available', 'msm-sitemap' ),
			array ( 'response' => 404 )
		);
	}

	$req_year = ( isset( $_GET['yyyy'] ) ) ? intval( $_GET['yyyy'] ) : false;
	$req_month = ( isset( $_GET['mm'] ) ) ? intval( $_GET['mm'] ) : false;
	$req_day = ( isset( $_GET['dd'] ) ) ? intval( $_GET['dd'] ) : false;

	header( 'Content-type: application/xml; charset=UTF-8' );

	$xml_prefix = '<?xml version="1.0" encoding="utf-8"?>';

	/** root sitemap */
	if ( false === $req_year && false === $req_month && false === $req_day ) {
		global $wpdb;
		// Direct query because we just want dates of the sitemap entries and this is much faster than WP_Query
		$sitemaps = $wpdb->get_col( $wpdb->prepare( "SELECT post_date FROM $wpdb->posts WHERE post_type = %s ORDER BY post_date DESC LIMIT 10000", Metro_Sitemap::SITEMAP_CPT ) );	

		$xml = new SimpleXMLElement( $xml_prefix . '<sitemapindex xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></sitemapindex>' );
		foreach ( $sitemaps as $sitemap_date ) {
			$sitemap_time = strtotime( $sitemap_date );
			$sitemap_url = add_query_arg( array(
				'yyyy' => date( 'Y', $sitemap_time ),
				'mm' => date( 'm', $sitemap_time ),
				'dd' => date( 'd', $sitemap_time ),	
			), home_url( '/sitemap.xml' ) ); 

			$sitemap = $xml->addChild( 'sitemap' );
			$sitemap->loc = $sitemap_url; // manually set the child instead of addChild to prevent "unterminated entity reference" warnings due to encoded ampersands http://stackoverflow.com/a/555039/169478
		}
		echo $xml->asXML();
	/** show sitemap by day */
	} else if ( $req_year > 0 && $req_month > 0 && $req_day > 0 ) {
		$sitemap_args = array(
			'year' => $req_year,
			'monthnum' => $req_month,
			'day' => $req_day,
			'orderby' => 'ID',
			'order' => 'ASC',
			'posts_per_page' => 1,
			'post_type' => Metro_Sitemap::SITEMAP_CPT,
			'no_found_rows' => true,
			'update_term_cache' => false,
		);
		$sitemap_query = new WP_Query( $sitemap_args );
		if ( $sitemap_query->have_posts() ) {
		   while ( $sitemap_query->have_posts() ) : 
		   		$sitemap_query->the_post();
				$sitemap_content = get_post_meta( get_the_ID(), 'msm_sitemap_xml', true );
		   		echo $sitemap_content;
		   endwhile;
		} else {
		   $xml = new SimpleXMLElement( $xml_prefix . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"/>' );
		   echo $xml->asXML();
		}
	} else {
		wp_die( __( 'Sorry, no sitemap available here.', 'msm-sitemap' ) );
	}

?>
