<?php
/*
Plugin Name: Metro Sitemap
Plugin URI: 
Description: Comprehensive sitemaps for your WordPress site. Joint collaboration between Metro.co.uk, MAKE and WordPress.com VIP.
Author: Artur Synowiec & Paul Kevan
Author URI: 
Version: 0.1
Stable tag: 0.1
License: Metro
*/

class Metro_Sitemap {

	function __construct() {
		define( 'MGS_INTERVAL_PER_GENERATION_EVENT', 60 ); // how far apart should full cron generation events be spaced
		define( 'MGS_INTERVAL_PER_YEAR_GENERATION', 10800 ); // 3 hrs minimum time span between each whole update run

		add_filter( 'cron_schedules', array( __CLASS__, 'sitemap_15_min_cron_interval' ) );

		// A cron schedule for creating/updating sitemap posts based on updated content since the last run
		add_action( 'init', array( __CLASS__, 'sitemap_init_cron' ) );
		add_action( 'redirect_canonical', array( __CLASS__, 'disable_canonical_redirects_for_sitemap_xml', 10, 2 ) );
		add_action( 'init', array( __CLASS__, 'add_google_sitemap_endpoint' ) );
		add_action( 'admin_menu', array( __CLASS__, 'google_sitemap_menu' ) );
		add_action( 'init', array( __CLASS__, 'create_post_type' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_redirect' ) );

	}

	/* 15 minutes cron interval for latest articles */
	function sitemap_15_min_cron_interval( $schedules ) {
		$schedules[ 'mgs-sitemap-15-min-cron-interval' ] = array(
			'interval' => 900,
			'display' => __( 'Every 15 minutes' ),
		);
		return $schedules;
	}

	function sitemap_init_cron() {
		if ( ! wp_next_scheduled( 'mgs_cron_update_sitemap' ) ) {
			wp_schedule_event( time(), 'mgs-sitemap-15-min-cron-interval', 'mgs_cron_update_sitemap' );
		}
		add_action( 'mgs_cron_update_sitemap', array( __CLASS__, 'update_sitemap_from_modified_posts' ) );

		add_action( 'mgs_cron_generate_sitemap_for_year', array( __CLASS__, 'generate_sitemap_for_year' ) );
		add_action( 'mgs_cron_generate_sitemap_for_year_month', array( __CLASS__, 'generate_sitemap_for_year_month' ) );
		add_action( 'mgs_cron_generate_sitemap_for_year_month_day', array( __CLASS__, 'generate_sitemap_for_year_month_day' ) );

		add_action( 'mgs_insert_sitemap_post', array( __CLASS__, 'queue_nginx_cache_invalidation', 10, 4 ) );
		add_action( 'mgs_delete_sitemap_post', array( __CLASS__, 'queue_nginx_cache_invalidation', 10, 4 ) );
		add_action( 'mgs_update_sitemap_post', array( __CLASS__, 'queue_nginx_cache_invalidation', 10, 4 ) );
	}

	function disable_canonical_redirects_for_sitemap_xml( $redirect_url, $requested_url ) { 
		if ( preg_match( '|sitemap\.xml|', $requested_url ) ) { 
			return $requested_url; 
		}
		return $redirect_url; 
	}

	function add_google_sitemap_endpoint() {
		define( 'WPCOM_SKIP_DEFAULT_SITEMAP', true );
		add_rewrite_tag( '%google-sitemap%', 'true' ); // allow 'google-sitemap=true' parameter
		add_rewrite_rule( '^sitemap.xml$','index.php?google-sitemap=true','top' );
	}


	function google_sitemap_menu() {
		add_options_page( 'Metro Sitemap Options', 'Create Sitemap', 'manage_options', 'metro-sitemap', array( __CLASS__, 'sitemap_options' ) );
	}

	function sitemap_options() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}

		$sitemap_create_in_progress = get_option( 'mgs_sitemap_create_in_progress' );
		$sitemap_update_last_run = get_option( 'mgs_sitemap_update_last_run' );
		$sitemap_update_next_run = $sitemap_update_last_run + 900;
		$modified_posts = self::get_last_modified_posts();
		$modified_posts_count = count( $modified_posts );
		$modified_posts_label = $modified_posts_count == 1 ? 'post' : 'posts';

		echo '<div class="wrap">';
		screen_icon();
		echo '<h2>Metro Sitemap</h2>';
		
		if ( isset( $_POST['action'] ) ) {
			$action = $_POST['action'];

			check_admin_referer( 'mgs-action' );

			switch ( $action ) {
				case 'Generate from all articles':
					self::generate_full_sitemap();
					update_option( 'mgs_sitemap_create_in_progress', true );

					if ( empty( $sitemap_create_in_progress ) ) {
						echo '<p>Creating sitemap...</p>';
					} else {
						echo '<p>Resuming sitemap creation</p>';
					}
				break;

				case 'Generate from latest articles':
					$last_modified = self::get_last_modified_posts();
					if ( count( $last_modified ) > 0 ) {
						echo '<p>Updating sitemap...</p>';
						self::update_sitemap_from_modified_posts();				
					} else {
						echo '<p>No posts updated lately.</p>';
					}
				break;

				case 'Halt Sitemap Generation':
					update_option( 'mgs_stop_processing', true );
					echo '<p>Stopping Sitemap generation</p>';
				break;

				case 'Reset Sitemap Data':
					// Do the same as when we finish then tell use to delete manuallyrather than remove all data
					delete_option( 'mgs_days_to_process' );
					delete_option( 'mgs_months_to_process' );
					delete_option( 'mgs_years_to_process' );
					update_option( 'mgs_stop_processing', true );
					delete_option( 'mgs_sitemap_create_in_progress' );
					echo '<p>If you want to remove the data you must do so manually</p>';
				break;

				default:
					echo '<p>Unknown action</p>';
				break;
			}

			echo '<form action="options-general.php">';
			echo ' <input type="hidden" name="page" value="metro-sitemap">';
			echo ' <input type="submit" value="Back">';
			echo '</form>';
		} else {
			$days_to_process = get_option( 'mgs_days_to_process' );

			?>
			<p><strong>Sitemap Create Status:</strong> <?php echo ( empty( $sitemap_create_in_progress ) ) ? ' Not Running</p>' : ' Running</p><p><b>Current position:</b>'; ?>
			<?php
			if ( $days_to_process ) {
				$years_to_process = get_option( 'mgs_years_to_process' );
				$months_to_process = get_option( 'mgs_months_to_process' );
				if ( ! $sitemap_create_in_progress ) {
					echo '<p><b>Restart position:</b>';
				}
				$current_day = count( $days_to_process ) - 1;
				$current_month = count( $months_to_process ) - 1;
				$current_year = count( $years_to_process ) - 1;
				printf( 'Day: %s Month: %s Year: %s</p>', $days_to_process[$current_day], $months_to_process[$current_month], $years_to_process[$current_year] );
				$years_to_process = ( $current_year == 0 ) ? array( 1 ) : $years_to_process;
				printf( '<p><b>Years to process:</b> %s </p>', implode( ',', $years_to_process ) );
			}
			?>
			<p><strong>Last updated:</strong> <?php echo human_time_diff( $sitemap_update_last_run ); ?> ago</p>
			<p><strong>Next update:</strong> <?php echo $modified_posts_count . ' ' . $modified_posts_label; ?> will be updated in <?php echo human_time_diff( $sitemap_update_next_run ); ?></p>
			<?php
			echo '<form action="'. menu_page_url( 'metro-google-sitemap', false ) .'" method="post" style="float: left;">';
			wp_nonce_field( 'mgs-action' );
			$disabled = ( $sitemap_create_in_progress ) ? ' disabled="disabled" ' : '';
			echo ' <input type="submit" name="action" value="Generate from all articles"' . $disabled . '>';
			echo ' <input type="submit" name="action" value="Generate from latest articles">';
			echo ' <input type="submit" name="action" value="Halt Sitemap Generation">';
			echo ' <input type="submit" name="action" value="Reset Sitemap Data">';
			echo '</form>';
		}
		echo '</div>';

	}

	function get_post_year_range() {
		global $wpdb;

		$oldest_post_date_gmt = $wpdb->get_var( "SELECT post_date FROM $wpdb->posts WHERE post_status = 'publish' ORDER BY post_date ASC LIMIT 1" );
		$oldest_post_year = date( 'Y', strtotime( $oldest_post_date_gmt ) );
		$current_year = date( 'Y' );

		return range( $oldest_post_year, $current_year );
	}

	function check_year_has_posts() {

		$all_years = self::get_post_year_range();

		$years_with_posts = array();
		foreach ( $all_years as $year ) {
			if ( self::date_range_has_posts( self::get_date_stamp( $year, 1, 1 ), self::get_date_stamp( $year, 12, 31 ) ) ) {
				$years_with_posts[] = $year;
			}
		}
		return $years_with_posts;

	}

	function get_date_stamp( $year, $month, $day ) {
		return sprintf( '%s-%s-%s', $year, str_pad( $month, 2, '0', STR_PAD_LEFT ), str_pad( $day, 2, '0', STR_PAD_LEFT ) );
	}

	function date_range_has_posts( $start_date, $end_date ) {
		global $wpdb;

		$start_date .= ' 00:00:00';
		$end_date .= ' 23:59:59';

		// TODO: need to incorporate post_type filter from get_last_modified_posts
		return $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_status = 'publish' AND post_date >= %s AND post_date <= %s LIMIT 1", $start_date, $end_date ) );
	}

	/*
	 * We want to generate the entire sitemap catalogue async to avoid running into timeouta and memory issues.
	 *
	 * Here's how it all works:
	 *
	 * -- Get year range for content
	 * -- Create cron event for each year: generate_sitemap_for_year
	 * -- generate_sitemap_for_year creates cron events for each month: generate_sitemap_for_year_month
	 * -- generate_sitemap_for_year_month creates cron events for each day: generate_sitemap_for_year_month_day
	 * -- generate_sitemap_for_year_month_day gets recent posts and adds sitemap via create_sitemap
	 *
	 * We could alternatively do a cascading approach, where one year queues the next but this would require more code :)
	 * 
	 * CHANGED TO RUN ONE YEAR AT A TIME DUE TO HEAVY LOAD - each all articles update can be done within 3 hours span
	 *  
	 */
	function generate_full_sitemap() {
		global $wpdb;

		$is_partial_or_running = get_option( 'mgs_years_to_process' );

		if ( empty( $is_partial_or_running ) ) {
			$all_years_with_posts = self::check_year_has_posts();
			update_option( 'mgs_years_to_process', $all_years_with_posts );
		} else {
			$all_years_with_posts = $is_partial_or_running;
		}

		$time = time();
		$next_year = $all_years_with_posts[count( $all_years_with_posts ) - 1];

	//	update_option( 'mgs_sitemap_create_last_run', $time );

		wp_schedule_single_event(
			$time, 
			array( __CLASS__, 'mgs_cron_generate_sitemap_for_year' ), 
			array(
				array(
					'year' => $next_year,
				),
			)
		);
		
	}


	function generate_sitemap_for_year( $args ) {
		
		$is_partial_or_running = get_option( 'mgs_months_to_process' );

		$year = $args['year'];
		$max_month = 12;
		if ( $year == date( 'Y' ) ) {
			$max_month = date( 'n' );
		}

		if ( empty( $is_partial_or_running ) ) {
			$months = range( 1, $max_month );
			update_option( 'mgs_months_to_process', $months );
		} else {
			$months = $is_partial_or_running;
		}


		$time = time();
		$next_month = $months[count( $months ) - 1];

		wp_schedule_single_event(
			$time,
			'mgs_cron_generate_sitemap_for_year_month',
			array(
				array(
					'year' => $year,
					'month' => $next_month,
				),
			)
		);
	}

	function generate_sitemap_for_year_month( $args ) {


		$is_partial_or_running = get_option( 'mgs_days_to_process' );

		$year = $args['year'];
		$month = $args['month'];

		$max_days = cal_days_in_month( CAL_GREGORIAN, $month, $year );

		if ( $month == date( 'n' ) ) {
			$max_days = date( 'j' );
		}

		if ( empty( $is_partial_or_running ) ) {
			$days = range( 1, $max_days );
			update_option( 'mgs_days_to_process', $days );
		} else {
			$days = $is_partial_or_running;
		}

		$next_element = count( $days ) - 1;
		$next_day = $days[$next_element];


		$time = time();

		wp_schedule_single_event(
			$time,
			'mgs_cron_generate_sitemap_for_year_month_day',
			array(
				array(
					'year' => $year,
					'month' => $month,
					'day' => $next_day,
				),
			)
		);
		
	}

	function generate_sitemap_for_year_month_day( $args ) {
		$year = $args['year'];
		$month = $args['month'];
		$day = $args['day'];

		if ( self::date_range_has_posts( self::get_date_stamp( $year, $month, $day ), self::get_date_stamp( $year, $month, $day ) ) ) {
			$date = self::get_date_stamp( $year, $month, $day );
			self::generate_sitemap_for_date( $date );
		}

		self::find_next_day_to_process( $year, $month, $day );
	}

	function generate_sitemap_for_date( $sitemap_date ) {
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
				do_action( array( __CLASS__, 'mgs_delete_sitemap_post' ), $sitemap_id, $year, $month, $day );
			}
			return;
		}

		// Create XML
		$xml = '<urlset xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:n="http://www.google.com/schemas/sitemap-news/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1" xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">';
		
		while ( $query->have_posts() ) {
			$query->the_post();

			$url = '<url>';
			$loc = '<loc>'.get_permalink().'</loc>';
			$lastmod = '<lastmod>' . get_the_modified_date( 'Y-m-d' ) . 'T' . get_the_modified_date( 'H:i:s' ) . 'Z</lastmod>';
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
		
		if ( $sitemap_exists ) {
			update_post_meta( $sitemap_id, 'mgs_sitemap_xml', $xml );
			do_action( array( __CLASS__, 'mgs_update_sitemap_post' ), $sitemap_id, $year, $month, $day );
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
			do_action( array( __CLASS__, 'mgs_insert_sitemap_post' ), $sitemap_id, $year, $month, $day );
		}
		wp_reset_postdata();
	}

	function find_next_day_to_process( $year, $month, $day ) {

		$halt = get_option( 'mgs_stop_processing' );
		if ( $halt ) {
			// Allow user to bail out of the current process, doesn't remove where the job got up to
			delete_option( 'mgs_stop_processing' );
			delete_option( 'mgs_sitemap_create_in_progress' );
			return;
		}

		update_option( 'mgs_sitemap_create_in_progress', true );

		$days_being_processed = get_option( 'mgs_days_to_process' );
		$months_being_processed = get_option( 'mgs_months_to_process' );
		$years_being_processed = get_option( 'mgs_years_to_process' );

		$total_days = count( $days_being_processed );
		$total_months = count( $months_being_processed );
		$total_years = count( $years_being_processed );

		if ( $total_days && $day > 1 ) {
			// Day has finished
			unset( $days_being_processed[$total_days - 1] );
			update_option( 'mgs_days_to_process', $days_being_processed );
			self::generate_sitemap_for_year_month( array( 'year' => $year, 'month' => $month ) );
		} else if ( $total_months and $month > 1 ) {
			// Month has finished
			unset( $months_being_processed[ $total_months - 1] );
			delete_option( 'mgs_days_to_process' );
			update_option( 'mgs_months_to_process', $months_being_processed );
			self::generate_sitemap_for_year( array( 'year' => $year ) );
		} else if ( $total_years ) {
			// Year has finished
			unset( $years_being_processed[ $total_years - 1] );
			delete_option( 'mgs_days_to_process' );
			delete_option( 'mgs_months_to_process' );
			update_option( 'mgs_years_to_process', $years_being_processed );
			self::generate_full_sitemap();
		} else {
			// We've finished - remove all options
			delete_option( 'mgs_days_to_process' );
			delete_option( 'mgs_months_to_process' );
			delete_option( 'mgs_years_to_process' );
			delete_option( 'mgs_sitemap_create_in_progress' );
		}

	}



	function create_post_type() {
		register_post_type(
			'mgs_sitemap',
			array(
				'labels' => array(
					'name' => __( 'Sitemaps' ),
					'singular_name' => __( 'Sitemap' ),
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

	function get_last_modified_posts() {
		global $wpdb;

		$sitemap_last_run = get_option( 'mgs_sitemap_update_last_run', false );
		
		$date = date( 'Y-m-d H:i:s', ( time() - 3600 ) ); // posts changed within the last hour

		if ( $sitemap_last_run ) {
			$date = date( 'Y-m-d H:i:s', $sitemap_last_run );
		}
		$post_types = apply_filters( 'mgs_sitemap_entry_post_type', 'post' );
		$post_types_in = sprintf( "'%s'", implode( "','", (array) $post_types ) );

		$modified_posts = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_date FROM $wpdb->posts WHERE post_type IN ( $post_types_in ) AND post_modified >= %s ORDER BY post_date LIMIT 1000", $date ) );
		return $modified_posts;
	}

	function get_post_dates( $posts ) {
		$dates = array();
		foreach ( $posts as $post ) {
		    $dates[] = date( 'Y-m-d', strtotime( $post->post_date ) );
		}
		$dates = array_unique( $dates );

		return $dates;
	}

	function update_sitemap_from_modified_posts() {
		$time = time();
		$last_modified_posts = self::get_last_modified_posts();
		$dates = self::get_post_dates( $last_modified_posts );

		foreach ( $dates as $date ) {
			list( $year, $month, $day ) = explode( '-', $date );

			wp_schedule_single_event(
				$time, 
				'mgs_cron_generate_sitemap_for_year_month_day',
				array(
					array(
						'year' => $year,
						'month' => $month,
						'day' => $day,
					),
				)
			);
		}
		update_option( 'mgs_sitemap_update_last_run', time() );
	}

	function queue_nginx_cache_invalidation( $sitemap_id, $year, $month, $day ) {
		if ( ! function_exists( 'queue_async_job' ) )
			return;

		$site_url = home_url();

		$sitemap_urls = array(
			$site_url . "/sitemap.xml?yyyy=$year",
			$site_url . "/sitemap.xml?yyyy=$year&mm=$month",
			$site_url . "/sitemap.xml?yyyy=$year&mm=$month&dd=$day",
		);
		if ( function_exists( 'queue_async_job' ) ) {
			queue_async_job( array( 'output_cache' => array( 'url' => $metro_uk_sitemap_urls ) ), 'wpcom_invalidate_output_cache_job', -16 );
		}
	}

	function handle_redirect() {

		if ( get_query_var( 'google-sitemap' ) === 'true' ) {
			include( plugin_dir_path( __FILE__ ) . 'templates/full-sitemaps.php' );
			exit;
		}
		return;
	}


}

$metro_sitemap_class = new Metro_Sitemap();








