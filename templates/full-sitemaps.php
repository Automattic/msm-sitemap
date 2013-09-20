<?php

	$req_year = ( isset( $_GET['yyyy'] ) ) ? intval( $_GET['yyyy'] ) : '';
	$req_month = ( isset( $_GET['mm'] ) ) ? intval( $_GET['mm'] ) : '';
	$req_day = ( isset( $_GET['dd'] ) ) ? intval( $_GET['dd'] ) : '';

	$this_year = date( 'Y' );
	$all_posts = get_posts( array( 'post_status' => 'publish', 'order' => 'ASC', 'posts_per_page' => 1 ) );
	$oldest_post = $all_posts[0];
	$start_year = substr( $oldest_post->post_date, 0, 4 );
	$years = range( $start_year, $this_year );	

	header( 'Content-type: application/xml; charset=UTF-8' );

	echo '<?xml version="1.0" encoding="utf-8"?>';

	/** root sitemap */
	if ( ( $req_year == 0 ) && ( $req_month == 0 ) && ( $req_day == 0 ) ) {
		echo '<sitemapindex xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
		foreach ( $years as $year ) {
			$query = new WP_Query( array( 'year' => $year, 'post_type' => Metro_Sitemap::$sitemap_cpt ) );
			if ( $query->have_posts() ) {
				echo '<sitemap>';
				echo '<loc>'. home_url( '/sitemap.xml?yyyy=' . $year ) . '</loc>';
				echo '</sitemap>';
			}
		}
		echo '</sitemapindex>';
	/** show sitemap by day */
	} else if ( $req_year > 0 && $req_month > 0 && $req_day > 0 ) {
		$sitemap_args = array(
			'year' => $req_year,
			'monthnum' => $req_month,
			'day' => $req_day,
			'orderby' => 'ID',
			'order' => 'ASC',
			'posts_per_page' => 1,
			'post_type' => Metro_Sitemap::$sitemap_cpt,
		);
		$sitemap_query = new WP_Query( $sitemap_args );
		if ( $sitemap_query->have_posts() ) {
		   while ( $sitemap_query->have_posts() ) : 
		   		$sitemap_query->the_post();
				$sitemap_content = get_post_meta( get_the_ID(), 'msm_sitemap_xml', true );
		   		echo $sitemap_content;
		   endwhile;
		} else {
		   echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"/>';
		}
	/** sitemap by year */
	} else if ( $req_year > 0 ){
		echo '<sitemapindex xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
			$months = 12;
			if ( $req_year == date( 'Y' ) ) $months = date( 'm' );
			for ( $m = 1; $m <= $months; $m++ ) {
				if ( strlen( $m ) === 1 ) $m = '0' . $m;
				$days = 31;
				if ( $m == 2) $days = date( 'L', strtotime( $req_year . '-01-01' ) ) ? 29 : 28;  // leap year
				if ( $m == 4 || $m == 6 || $m == 9 || $m == 11) $days = 30;
				if ( $m == date( 'm' ) ) $days = date( 'd' );
				for ( $d = 1; $d <= $days; $d++ ) {
					$sitemap_args = array(
						'year' => $req_year,
						'monthnum' => $m,
						'day' => $d,
						'post_type' => 'DESC',
						'post_type' => Metro_Sitemap::$sitemap_cpt,
					);
					$query = new WP_Query( $sitemap_args );
					if ( $query->have_posts() ) {
						if ( strlen( $d ) === 1 ) $d = '0' . $d;
						echo '<sitemap>';
						echo '<loc>' . home_url( '/sitemap.xml?yyyy=' . $req_year . '&amp;mm=' . $m . '&amp;dd=' . $d ) . '</loc>';
						echo '</sitemap>';						
					}
				}
			}
		echo '</sitemapindex>';
	} else {
		echo '<sitemapindex/>';
	}

?>
