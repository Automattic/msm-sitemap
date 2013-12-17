<?php
/*
Plugin Name: Metro Sitemap
Description: Comprehensive sitemaps for your WordPress site. Joint collaboration between Metro.co.uk, MAKE, Alley Interactive, and WordPress.com VIP.
Author: Artur Synowiec, Paul Kevan, and others
Version: 0.1
Stable tag: 0.1
License: GPLv2
*/

if ( defined( 'WP_CLI' ) && true === WP_CLI )
	require dirname( __FILE__ ) . '/includes/wp-cli.php';

class Metro_Sitemap {

	const DEFAULT_POSTS_PER_SITEMAP_PAGE = 200;

	const SITEMAP_CPT = 'msm_sitemap';

	/**
	 * Register actions for our hook
	 */
	public static function setup() {
		define( 'MSM_INTERVAL_PER_GENERATION_EVENT', 60 ); // how far apart should full cron generation events be spaced

		add_filter( 'cron_schedules', array( __CLASS__, 'sitemap_15_min_cron_interval' ) );

		// A cron schedule for creating/updating sitemap posts based on updated content since the last run
		add_action( 'init', array( __CLASS__, 'sitemap_init' ) );
		add_action( 'admin_init', array( __CLASS__, 'sitemap_init_cron' ) );
		add_action( 'redirect_canonical', array( __CLASS__, 'disable_canonical_redirects_for_sitemap_xml' ), 10, 2 );
		add_action( 'init', array( __CLASS__, 'create_post_type' ) );
		add_filter( 'template_include', array( __CLASS__, 'load_sitemap_template' ) );

		// By default, we use wp-cron to help generate the full sitemap.
		// However, this will let us override it, if necessary, like on WP.com
		if ( true === apply_filters( 'msm_sitemap_use_cron_builder', true ) ) {
			require dirname( __FILE__ ) . '/includes/msm-sitemap-builder-cron.php';
			MSM_Sitemap_Builder_Cron::setup();
		}
	}

	/**
	 * Register 15 minute cron interval for latest articles
	 * @param array[] $schedules
	 * @return array[] modified schedules
	 */
	public static function sitemap_15_min_cron_interval( $schedules ) {
		$schedules[ 'ms-sitemap-15-min-cron-interval' ] = array(
			'interval' => 900,
			'display' => __( 'Every 15 minutes', 'metro-sitemaps' ),
		);
		return $schedules;
	}

	/**
	 * Register endpoint for sitemap and other hooks
	 */
	public static function sitemap_init() {
		define( 'WPCOM_SKIP_DEFAULT_SITEMAP', true );
		add_rewrite_tag( '%sitemap%', 'true' ); // allow 'sitemap=true' parameter
		add_rewrite_rule( '^sitemap.xml$','index.php?sitemap=true','top' );

		add_filter( 'robots_txt', array( __CLASS__, 'robots_txt' ), 10, 2 );

		add_action( 'msm_cron_update_sitemap', array( __CLASS__, 'update_sitemap_from_modified_posts' ) );

		add_action( 'msm_cron_generate_sitemap_for_year', array( __CLASS__, 'generate_sitemap_for_year' ) );
		add_action( 'msm_cron_generate_sitemap_for_year_month', array( __CLASS__, 'generate_sitemap_for_year_month' ) );
		add_action( 'msm_cron_generate_sitemap_for_year_month_day', array( __CLASS__, 'generate_sitemap_for_year_month_day' ) );
	}

	/**
	 * Add entry to the bottom of robots.txt
	 */
	public static function robots_txt( $output, $public ) {

		// Make sure the site isn't private
		if ( '1' == $public ) {
			$output .= '# Sitemap archive' . PHP_EOL;
			$output .= 'Sitemap: ' . home_url( '/sitemap.xml' ) . PHP_EOL . PHP_EOL;
		}
		return $output;

	}

	/**
	 * Add cron jobs required to generate these sitemaps
	 */
	public static function sitemap_init_cron() {
		if ( ! wp_next_scheduled( 'msm_cron_update_sitemap' ) ) {
			wp_schedule_event( time(), 'ms-sitemap-15-min-cron-interval', 'msm_cron_update_sitemap' );
		}
	}

	/**
	 * Disable canonical redirects for the sitemap file
	 * @see http://codex.wordpress.org/Function_Reference/redirect_canonical
	 * @param string $redirect_url
	 * @param string $requested_url
	 * @return string URL to redirect
	 */
	public static function disable_canonical_redirects_for_sitemap_xml( $redirect_url, $requested_url ) {
		if ( preg_match( '|sitemap\.xml|', $requested_url ) ) { 
			return $requested_url; 
		}
		return $redirect_url; 
	}

	/**
	 * Return range of years for posts in the database
	 * @return int[] valid years
	 */
	public static function get_post_year_range() {
		global $wpdb;

		$oldest_post_date_gmt = $wpdb->get_var( "SELECT post_date FROM $wpdb->posts WHERE post_status = 'publish' ORDER BY post_date ASC LIMIT 1" );
		$oldest_post_year = date( 'Y', strtotime( $oldest_post_date_gmt ) );
		$current_year = date( 'Y' );

		return range( $oldest_post_year, $current_year );
	}

	/**
	 * Get every year that has valid posts in a range
	 * @return int[] years with posts
	 */
	public static function check_year_has_posts() {

		$all_years = self::get_post_year_range();

		$years_with_posts = array();
		foreach ( $all_years as $year ) {
			if ( self::date_range_has_posts( self::get_date_stamp( $year, 1, 1 ), self::get_date_stamp( $year, 12, 31 ) ) ) {
				$years_with_posts[] = $year;
			}
		}
		return $years_with_posts;

	}

	/**
	 * Get properly formatted data stamp from year, month, and day
	 * @param int $year
	 * @param int $month
	 * @param int $day
	 * @return string formatted stamp
	 */
	public static function get_date_stamp( $year, $month, $day ) {
		return sprintf( '%s-%s-%s', $year, str_pad( $month, 2, '0', STR_PAD_LEFT ), str_pad( $day, 2, '0', STR_PAD_LEFT ) );
	}

	/**
	 * Does a current date range have posts?
	 * @param string $start_date
	 * @param string $end_date
	 * @return int|false
	 */
	public static function date_range_has_posts( $start_date, $end_date ) {
		global $wpdb;

		$start_date .= ' 00:00:00';
		$end_date .= ' 23:59:59';

		// TODO: need to incorporate post_type filter from get_last_modified_posts
		return $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_status = 'publish' AND post_date >= %s AND post_date <= %s LIMIT 1", $start_date, $end_date ) );
	}

	/**
	 * Generate sitemap for a date; this is where XML is rendered.
	 * @param string $sitemap_date
	 */
	public static function generate_sitemap_for_date( $sitemap_date ) {
		global $wpdb;

		$sitemap_time = strtotime( $sitemap_date );
		list( $year, $month, $day ) = explode( '-', $sitemap_date );

		$sitemap_name = $sitemap_date;
		$sitemap_exists = false;

		$sitemap_data = array(
			'post_name' => $sitemap_name,
			'post_title' => $sitemap_name,
			'post_type' => self::SITEMAP_CPT,
			'post_status' => 'publish',
			'post_date' => $sitemap_date,
		);

		$sitemap_id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type = %s AND post_name = %s LIMIT 1", self::SITEMAP_CPT, $sitemap_name ) );

		if ( $sitemap_id ) {
			$sitemap_exists = true;
		} else {
			$sitemap_id = wp_insert_post( $sitemap_data );
			$sitemap_exists = true;
		}

		$query_args = array(
			'year' => $year,
			'monthnum' => $month,
			'day' => $day,
			'order' => 'DESC',
			'post_status' => 'publish',
			'post_type' => self::get_supported_post_types(),	
			'posts_per_page' => apply_filters( 'msm_sitemap_entry_posts_per_page', self::DEFAULT_POSTS_PER_SITEMAP_PAGE ),
			'no_found_rows' => true,
		);

		$query = new WP_Query( $query_args );
		$post_count = $query->post_count;

		if ( ! $post_count ) {
			// If no entries - delete the whole sitemap post
			if ( $sitemap_exists ) {
				wp_delete_post( $sitemap_id, true );
				do_action( 'msm_delete_sitemap_post', $sitemap_id, $year, $month, $day );
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
			update_post_meta( $sitemap_id, 'msm_sitemap_xml', $xml );
			do_action( 'msm_update_sitemap_post', $sitemap_id, $year, $month, $day );
		} else {
			/* Should no longer hit this */
			$sitemap_id = wp_insert_post( $sitemap_data );
			add_post_meta( $sitemap_id, 'msm_sitemap_xml', $xml );
			do_action( 'msm_insert_sitemap_post', $sitemap_id, $year, $month, $day );
		}
		wp_reset_postdata();
	}

	/**
	 * Register our CPT
	 */
	public static function create_post_type() {
		register_post_type(
			self::SITEMAP_CPT,
			array(
				'labels'       => array(
					'name'          => __( 'Sitemaps' ),
					'singular_name' => __( 'Sitemap' ),
				),
				'public'       => false,
				'has_archive'  => false,
				'rewrite'      => false,
				'show_ui'      => true,  // TODO: should probably have some sort of custom UI
				'show_in_menu' => false, // Since we're manually adding a Sitemaps menu, no need to auto-add one through the CPT.
				'supports'     => array(
					'title',
				),
			)
		);
	}

	/**
	 * Get posts modified within the last hour
	 * @return object[] modified posts
	 */
	public static function get_last_modified_posts() {
		global $wpdb;

		$sitemap_last_run = get_option( 'msm_sitemap_update_last_run', false );
		
		$date = date( 'Y-m-d H:i:s', ( time() - 3600 ) ); // posts changed within the last hour

		if ( $sitemap_last_run ) {
			$date = date( 'Y-m-d H:i:s', $sitemap_last_run );
		}
		$post_types = apply_filters( 'msm_sitemap_entry_post_type', 'post' );
		$post_types_in = sprintf( "'%s'", implode( "','", (array) $post_types ) );

		$modified_posts = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_date FROM $wpdb->posts WHERE post_type IN ( $post_types_in ) AND post_modified >= %s ORDER BY post_date LIMIT 1000", $date ) );
		return $modified_posts;
	}

	/**
	 * Get dates for an array of posts
	 * @param object[] $posts
	 * @return string[] unique dates of each post.
	 */
	public static function get_post_dates( $posts ) {
		$dates = array();
		foreach ( $posts as $post ) {
		    $dates[] = date( 'Y-m-d', strtotime( $post->post_date ) );
		}
		$dates = array_unique( $dates );

		return $dates;
	}

	/**
	 * Update the sitemap with changes from recently modified posts
	 */
	public static function update_sitemap_from_modified_posts() {
		$time = time();
		$last_modified_posts = self::get_last_modified_posts();
		$dates = self::get_post_dates( $last_modified_posts );

		foreach ( $dates as $date ) {
			list( $year, $month, $day ) = explode( '-', $date );

			$time += MSM_INTERVAL_PER_GENERATION_EVENT;

			do_action( 'msm_update_sitemap_for_year_month_date', array( $year, $month, $day ), $time );
		}
		update_option( 'msm_sitemap_update_last_run', time() );
	}

	/**
	 * Trigger rendering of the actual sitemap
	 */
	public static function load_sitemap_template( $template ) {
		if ( get_query_var( 'sitemap' ) === 'true' ) {
			$template = dirname( __FILE__ ) . '/templates/full-sitemaps.php';
		}
		return $template;
	}

	public static function build_xml( $request = array() ) {

		$year = $request['year'];
		$month = $request['month'];
		$day = $request['day'];

		$output = '';

		if ( false === $year && false === $month && false === $day ) {

			/* Output years with posts */
			$years = self::check_year_has_posts();

			foreach ( $years as $year ) {
				$output .= '<sitemap>';
				$output .= '<loc>'. home_url( '/sitemap.xml?yyyy=' . $year ) . '</loc>';
				$output .= '</sitemap>';
			}
		} else if ( $year > 0 && $month > 0 && $day > 0 ) {
			// Get XML for an individual day. Stored as full xml
			$sitemap_args = array(
				'year' => $year,
				'monthnum' => $month,
				'day' => $day,
				'orderby' => 'ID',
				'order' => 'ASC',
				'posts_per_page' => 1,
				'fields' => 'ids',
				'post_type' => self::SITEMAP_CPT,
				'no_found_rows' => true,
				'update_term_cache' => false,
			);
			$sitemap_query = new WP_Query( $sitemap_args );
			if ( $sitemap_query->have_posts() ) {
				while ( $sitemap_query->have_posts() ) : 
			   		$sitemap_query->the_post();
					$sitemap_content = get_post_meta( get_the_ID(), 'msm_sitemap_xml', true );
			   		$output .= $sitemap_content;
				endwhile;
			} else {
				/* There are no posts for this day */
				return '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"/>';
			}
		} else if ( $year > 0 ) {
			/* Print out whole year as links - there shouldn't be any days without posts against them */
			$all_sitemap_items = get_posts(
				array(
					'year' => $year,
					'post_type' => self::SITEMAP_CPT,
					'no_found_rows' => true,
					'update_meta_cache' => false,
					'update_term_cache' => false,
					'suppress_filters' => false,
				)
			);

			foreach ( $all_sitemap_items as $item ) {

				$title = get_the_title( $item );
				$date_part = explode( '-', $title );
				$m = $date_part[1];
				$d = $date_part[2];

				$output .= '<sitemap>';
				$output .= '<loc>' . home_url( '/sitemap.xml?yyyy=' . $year . '&amp;mm=' . $m . '&amp;dd=' . $d ) . '</loc>';
				$output .= '</sitemap>';
			}
		} else {
			/* Invalid options sent */
			return false;
		}

		$output = '<sitemapindex xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . $output . '</sitemapindex>';

		return $output;
	}

	public static function find_valid_days( $year ) {
		$days = 31;
		if ( $m == 2 ) {
			$days = date( 'L', strtotime( $year . '-01-01' ) ) ? 29 : 28;  // leap year
		} elseif ( $m == 4 || $m == 6 || $m == 9 || $m == 11 ) {
			$days = 30;
		}

		if ( $m == date( 'm' ) ) {
			$days = date( 'd' );
		}

		return $days;
	}

	public static function get_supported_post_types() {
		return apply_filters( 'msm_sitemap_entry_post_type', 'post' );
	}
}

add_action( 'after_setup_theme', array( 'Metro_Sitemap', 'setup' ) );
