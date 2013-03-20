<?php
/*
Plugin Name: Metro Google Sitemap
Plugin URI: 
Description: Metro Google Sitemap
Author: Artur Synowiec
Author URI: 
Version: 0.1
Stable tag: 0.1
License: Metro
*/
define( 'MGS_INTERVAL_PER_GENERATION_EVENT', 60 ); // how far apart should full cron generation events be spaced
define( 'MGS_INTERVAL_PER_YEAR_GENERATION', 10800 ); // 3 hrs minimum time span between each whole update run

// A cron schedule for creating/updating sitemap posts based on updated content since the last run
add_action( 'init', 'mgs_sitemap_init_cron' );

/* 15 minutes cron interval for latest articles */
function mgs_sitemap_15_min_cron_interval( $schedules ) {
	$schedules[ 'mgs-sitemap-15-min-cron-interval' ] = array(
		'interval' => 900,
		'display' => __( 'Every 15 minutes' )
	);
	return $schedules;
}

add_filter( 'cron_schedules', 'mgs_sitemap_15_min_cron_interval' );

function mgs_sitemap_init_cron() {
	if ( ! wp_next_scheduled( 'mgs_cron_update_sitemap' ) ) {
		wp_schedule_event( time(), 'mgs-sitemap-15-min-cron-interval', 'mgs_cron_update_sitemap' );
	}
	add_action( 'mgs_cron_update_sitemap', 'mgs_update_sitemap_from_modified_posts' );

	add_action( 'mgs_cron_generate_sitemap_for_year', 'mgs_generate_sitemap_for_year' );
	add_action( 'mgs_cron_generate_sitemap_for_year_month', 'mgs_generate_sitemap_for_year_month' );
	add_action( 'mgs_cron_generate_sitemap_for_year_month_day', 'mgs_generate_sitemap_for_year_month_day' );

	add_action( 'mgs_insert_sitemap_post', 'msg_queue_nginx_cache_invalidation', 10, 4 );
	add_action( 'mgs_delete_sitemap_post', 'msg_queue_nginx_cache_invalidation', 10, 4 );
	add_action( 'mgs_update_sitemap_post', 'msg_queue_nginx_cache_invalidation', 10, 4 );
}

function mgs_disable_canonical_redirects_for_sitemap_xml($redirect_url, $requested_url) { 
	if(preg_match('|sitemap\.xml|', $requested_url)) { 
		return $requested_url; 
	}
	return $redirect_url; 
}

add_action('redirect_canonical', 'mgs_disable_canonical_redirects_for_sitemap_xml', 10, 2);

function mgs_add_google_sitemap_endpoint() {
	define( 'WPCOM_SKIP_DEFAULT_SITEMAP', true );
	add_rewrite_tag('%google-sitemap%', 'true'); // allow 'google-sitemap=true' parameter
	add_rewrite_rule('^sitemap.xml$','index.php?google-sitemap=true','top');
}
add_action('init', 'mgs_add_google_sitemap_endpoint');


add_action('admin_menu', 'mgs_google_sitemap_menu');

function mgs_google_sitemap_menu() {
	add_options_page('Metro Google Sitemap Options', 'Create Google Sitemap', 'manage_options', 'metro-google-sitemap', 'mgs_google_sitemap_options');
}

function mgs_google_sitemap_options() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( __('You do not have sufficient permissions to access this page.') );
	}

	$sitemap_create_last_run = get_option( 'mgs_sitemap_create_last_run' );
	$sitemap_create_last_run_today = $sitemap_create_last_run && date( 'Y-m-d' ) == date( 'Y-m-d', $sitemap_create_last_run );
	$sitemap_update_last_run = get_option( 'mgs_sitemap_update_last_run' );
	$sitemap_update_next_run = $sitemap_update_last_run + 900;
	$modified_posts = mgs_get_last_modified_posts();
	$modified_posts_count = count( $modified_posts );
	$modified_posts_label = $modified_posts_count == 1 ? 'post' : 'posts';

	echo '<div class="wrap">';
	screen_icon();
	echo '<h2>Metro Google sitemap</h2>';
	
	if ( isset( $_POST['action'] ) ) {

		if ($_POST['action'] == 'generate-latest-google-sitemap') {
			check_admin_referer( 'generate-latest-google-sitemap' );
		
			$last_modified = mgs_get_last_modified_posts();
			if(count($last_modified) > 0) {
				echo "<p>Updating sitemap...</p>";
				mgs_update_sitemap_from_modified_posts();				
			} else {
				echo "<p>No posts updated lately.</p>";
			}
		} else if($_POST['action'] == 'generate-google-sitemap') {
			check_admin_referer( 'generate-google-sitemap' );
			$year = (int)$_POST['year'];

			// Only allow running it after 'MGS_INTERVAL_PER_YEAR_GENERATION' time span; arbitrary protection since create is a pretty intensive process
			if(time() > $sitemap_create_last_run + MGS_INTERVAL_PER_YEAR_GENERATION) {
				mgs_generate_full_sitemap($year);
				echo '<p>Creating sitemap...</p>';
			} else {
				echo '<p>Sorry, you can run it again in '.human_time_diff($sitemap_create_last_run + MGS_INTERVAL_PER_YEAR_GENERATION).'</p>';
			}

		}

		echo '<form action="options-general.php">';
		echo ' <input type="hidden" name="page" value="metro-google-sitemap">';
		echo ' <input type="submit" value="Back">';
		echo '</form>';
	} else {
		?>
		<p><strong>Last created:</strong> <?php echo human_time_diff( $sitemap_create_last_run ); ?> ago</p>
		<p><strong>Last updated:</strong> <?php echo human_time_diff( $sitemap_update_last_run ); ?> ago</p>
		<p><strong>Next update:</strong> <?php echo $modified_posts_count . ' ' . $modified_posts_label; ?> will be updated in <?php echo human_time_diff( $sitemap_update_next_run ); ?></p>
		<?php
		echo '<form action="'. menu_page_url( 'metro-google-sitemap', false ) .'" method="post" style="float: left;">';
		echo ' <input type="hidden" name="action" value="generate-google-sitemap">';
		wp_nonce_field( 'generate-google-sitemap' );
		
		$post_year_range = mgs_get_post_year_range();
		$post_year_range = array_reverse($post_year_range);
		
		echo ' <select name="year">';
		foreach ( $post_year_range as $year ) {
			echo ' <option value="'.$year.'">'.$year.'</option>';
		}
		echo ' </select>';
		
		echo ' <input type="submit" value="Generate from all articles">';
		echo '</form>';

		echo '<form action="'. menu_page_url( 'metro-google-sitemap', false ) .'" method="post">';
		echo ' <input type="hidden" name="action" value="generate-latest-google-sitemap">';
		wp_nonce_field( 'generate-latest-google-sitemap' );
		echo ' <input type="submit" value="Generate from latest articles">';
		echo '</form>';
	}
	echo '</div>';

}

function mgs_get_post_year_range() {
	global $wpdb;

	$oldest_post_date_gmt = $wpdb->get_var( "SELECT post_date FROM $wpdb->posts WHERE post_status = 'publish' ORDER BY post_date ASC LIMIT 1" );
	$oldest_post_year = date( 'Y', strtotime( $oldest_post_date_gmt ) );
	$current_year = date( 'Y' );

	return range( $oldest_post_year, $current_year );
}

function mgs_get_date_stamp( $year, $month, $day ) {
	return sprintf( '%s-%s-%s', $year, str_pad( $month, 2, '0', STR_PAD_LEFT ), str_pad( $day, 2, '0', STR_PAD_LEFT ) );
}

function mgs_date_range_has_posts( $start_date, $end_date ) {
	global $wpdb;

	$start_date .= ' 00:00:00';
	$end_date .= ' 23:59:59';

	// TODO: need to incorporate post_type filter from mgs_get_last_modified_posts
	return $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_status = 'publish' AND post_date >= %s AND post_date <= %s LIMIT 1", $start_date, $end_date ) );
}

/*
 * We want to generate the entire sitemap catalogue async to avoid running into timeouta and memory issues.
 *
 * Here's how it all works:
 *
 * -- Get year range for content
 * -- Create cron event for each year: mgs_generate_sitemap_for_year
 * -- mgs_generate_sitemap_for_year creates cron events for each month: mgs_generate_sitemap_for_year_month
 * -- mgs_generate_sitemap_for_year_month creates cron events for each day: mgs_generate_sitemap_for_year_month_day
 * -- mgs_generate_sitemap_for_year_month_day gets recent posts and adds sitemap via mgs_create_sitemap
 *
 * We could alternatively do a cascading approach, where one year queues the next but this would require more code :)
 * 
 * CHANGED TO RUN ONE YEAR AT A TIME DUE TO HEAVY LOAD - each all articles update can be done within 3 hours span
 *  
 */
function mgs_generate_full_sitemap($year) {
	global $wpdb;

	$time = time();
	update_option( 'mgs_sitemap_create_last_run', $time );

	$time += MGS_INTERVAL_PER_GENERATION_EVENT;
	if (  mgs_date_range_has_posts( mgs_get_date_stamp( $year, 1, 1 ), mgs_get_date_stamp( $year, 12, 31 ) ) ) {
		wp_schedule_single_event( $time, 'mgs_cron_generate_sitemap_for_year', array(
			array(
				'year' => $year,
			)
		) );
	}
	
}


function mgs_generate_sitemap_for_year( $args ) {
	$year = $args['year'];
	$months = range( 1, 12 );
	$time = time();

	foreach ( $months as $month ) {
		$month_start =  mgs_get_date_stamp( $year, $month, 1 );
		if ( ! mgs_date_range_has_posts( $month_start, mgs_get_date_stamp( $year, $month, date( 't', strtotime( $month_start ) ) ) ) )
			continue;

		$time += MGS_INTERVAL_PER_GENERATION_EVENT;
		wp_schedule_single_event( $time, 'mgs_cron_generate_sitemap_for_year_month', array(
			array(
				'year' => $year,
				'month' => $month,
			)
		) );
	}
}

function mgs_generate_sitemap_for_year_month( $args ) {
	$year = $args['year'];
	$month = $args['month'];
	$days = range( 1, 31 );
	$time = time();

	foreach ( $days as $day ) {
		$date = mgs_get_date_stamp( $year, $month, $day );
		$is_date = strtotime( $date );
		
		if ( ! $is_date )
			continue;

		if ( ! mgs_date_range_has_posts( mgs_get_date_stamp( $year, $month, $day ), mgs_get_date_stamp( $year, $month, $day ) ) )
			continue;

		$time += MGS_INTERVAL_PER_GENERATION_EVENT;
		mgs_schedule_sitemap_for_year_month_day( $time, $year, $month, $day );
	}
}

function mgs_schedule_sitemap_for_year_month_day( $time, $year, $month, $day ) {
	wp_schedule_single_event( $time, 'mgs_cron_generate_sitemap_for_year_month_day', array(
			array(
				'year' => $year,
				'month' => $month,
				'day' => $day,
			)
		) );
}

function mgs_generate_sitemap_for_year_month_day( $args ) {
	$year = $args['year'];
	$month = $args['month'];
	$day = $args['day'];

	$date = mgs_get_date_stamp( $year, $month, $day );

	mgs_generate_sitemap_for_date( $date );
}

function mgs_generate_sitemap_for_date( $sitemap_date ) {
	global $wpdb;

	$sitemap_time = strtotime( $sitemap_date );
	list( $year, $month, $day ) = explode( '-', $sitemap_date );

	$sitemap_name = $sitemap_date;
	$sitemap_exists = false;

	$sitemap_id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type = %s AND post_name = %s LIMIT 1", 'mgs_sitemap', $sitemap_name ) );

	if ( $sitemap_id )
		$sitemap_exists = true;

	$query_args = array(
		'year' => $year,
		'monthnum' => $month,
		'day' => $day,
		'order' => 'DESC',
		'post_status' => 'publish',
		'post_type' => apply_filters( 'mgs_sitemap_entry_post_type', 'post' ),
		'posts_per_page' => apply_filters( 'mgs_sitemap_entry_posts_per_page', 200 ),
		'no_found_rows' => true,
	);

	$query = new WP_Query( $query_args );
	$post_count = $query->post_count;

	if ( ! $post_count ) {
		// If no entries - delete the whole sitemap post
		if ( $sitemap_exists ) {
			wp_delete_post( $sitemap_id, true );
			do_action( 'mgs_delete_sitemap_post', $sitemap_id, $year, $month, $day );
		}
		return;
	}

	// Create XML
	$xml = '<urlset xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:n="http://www.google.com/schemas/sitemap-news/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1" xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">';
	
	while ( $query->have_posts() ) {
		$query->the_post();

		$url = '<url>';
		$loc = '<loc>'.get_permalink().'</loc>';
		$lastmod = '<lastmod>'.get_the_modified_date('Y-m-d').'T'.get_the_modified_date('H:i:s').'Z</lastmod>';
		$url .= $loc;
		$url .= $lastmod;
		$content = get_the_content();
		$images_xml = '';
		/** Include inline images (parse content using DOM parser) */
		/*  // commented out due to resize errors 
		$dom = new DOMDocument();
		$dom->loadHTML($content);
		$nodes = $dom->getElementsByTagName('img');
		foreach ($nodes as $img) {
		  	$images_xml .= "<image:image><image:loc>".str_replace('&', '&amp;', $img->getAttribute('src'))."</image:loc></image:image>";
		}
		$url .= $images_xml;
		*/
		$url .= '<changefreq>monthly</changefreq>';
		$url .= '<priority>0.7</priority>';
		$url .= '</url>';
		$xml .= $url;
	}
	
	$xml .= '</urlset>';
	
	if( $sitemap_exists ) {
		update_post_meta( $sitemap_id, 'mgs_sitemap_xml', $xml );
		do_action( 'mgs_update_sitemap_post', $sitemap_id, $year, $month, $day );
	} else {
		$sitemap_data = array(
			'post_name' => $sitemap_name,
			'post_title' => $sitemap_name,
			'post_type' => 'mgs_sitemap',
			'post_status' => 'publish',
			'post_date' => $sitemap_date,
		);
		$sitemap_id = wp_insert_post( $sitemap_data );
		add_post_meta( $sitemap_id, 'mgs_sitemap_xml', $xml );
		do_action( 'mgs_insert_sitemap_post', $sitemap_id, $year, $month, $day );
	}
	wp_reset_postdata();
}

add_action( 'init', 'mgs_create_post_type' );


function mgs_create_post_type() {
	register_post_type(
		'mgs_sitemap',
		array(
			'labels' => array(
				'name' => __( 'Google Sitemap' ),
				'singular_name' => __( 'Google Sitemap' )
			),
			'public' => false,
			'has_archive' => true,
			'supports' => array(
				'title',
			),
			'show_ui' => true, // debugging, so we can see the sitemaps that are generated
		)
	);
}

function mgs_get_last_modified_posts() {
	global $wpdb;

	$sitemap_last_run = get_option( 'mgs_sitemap_update_last_run', false );
	
	$date = date( 'Y-m-d H:i:s', ( time() - 3600 ) ); // posts changed within the last hour

	if( $sitemap_last_run ) {
		$date = date( 'Y-m-d H:i:s', $sitemap_last_run );
	}
	$post_types = apply_filters( 'mgs_sitemap_entry_post_type', 'post' );
	$post_types_in = sprintf( "'%s'", implode( "','", (array) $post_types ) );

	$modified_posts = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_date FROM $wpdb->posts WHERE post_type IN ( $post_types_in ) AND post_modified >= %s ORDER BY post_date LIMIT 1000", $date ) );
	return $modified_posts;
}

function mgs_get_post_dates( $posts ) {
	$dates = array();
	foreach ( $posts as $post ) {
	    $dates[] = date( 'Y-m-d', strtotime( $post->post_date ) );
	}
	$dates = array_unique( $dates );

	return $dates;
}

function mgs_update_sitemap_from_modified_posts() {
	$time = time();
	$last_modified_posts = mgs_get_last_modified_posts();
	$dates = mgs_get_post_dates( $last_modified_posts );

	foreach ( $dates as $date ) {
		list( $year, $month, $day ) = explode( '-', $date );
		$time += MGS_INTERVAL_PER_GENERATION_EVENT;
		mgs_schedule_sitemap_for_year_month_day( $time, $year, $month, $day );
	}
	update_option( 'mgs_sitemap_update_last_run', time() );
}

function msg_queue_nginx_cache_invalidation( $sitemap_id, $year, $month, $day ) {
	$metro_uk_sitemap_urls = array(
		"http://metro.co.uk/sitemap.xml?yyyy=$year",
		"http://metro.co.uk/sitemap.xml?yyyy=$year&mm=$month",
		"http://metro.co.uk/sitemap.xml?yyyy=$year&mm=$month&dd=$day",
	);
	queue_async_job( array( 'output_cache' => array( 'url' => $metro_uk_sitemap_urls ) ), 'wpcom_invalidate_output_cache_job', -16 );
}
