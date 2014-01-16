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
		add_action( 'admin_menu', array( __CLASS__, 'metro_sitemap_menu' ) );
		add_action( 'msm_cron_update_sitemap', array( __CLASS__, 'update_sitemap_from_modified_posts' ) );
	}

	/**
	 * Register admin menu for sitemap
	 */
	public static function metro_sitemap_menu() {
		add_management_page( __( 'Sitemap', 'metro-sitemaps' ), __( 'Sitemap', 'metro-sitemaps' ), 'manage_options', 'metro-sitemap', array( __CLASS__, 'render_sitemap_options_page' ) );
	}

	/**
	 * Render admin options page
	 */
	public static function render_sitemap_options_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'metro-sitemaps' ) );
		}

		// Array of possible user actions
		$actions = Array(
			'generate' => __( 'Generate from all articles', 'metro-sitemaps' ),
			'generate-from-latest' => __( 'Generate from latest articles', 'metro-sitemaps' ),
			'halt-generation' => __( 'Halt Sitemap Generation', 'metro-sitemaps' ),
			'reset-sitemap-data' => __( 'Reset Sitemap Data', 'metro-sitemaps' ),
		);

		// Start outputting html
		echo '<div class="wrap">';
		screen_icon();
		echo '<h2>' . __( 'Sitemap', 'metro-sitemaps' ) . '</h2>';

		if ( isset( $_POST['action'] ) && in_array($_POST['action'], $actions)  ) {
			check_admin_referer( 'msm-sitemap-action' );
			$message = Metro_Sitemap::do_sitemap_action( array_search( $_POST['action'], $actions ) );
			echo '<div class="updated settings-error" id="msm-sitemap-updated"><p>' . esc_html( $message ) . '</p></div>';
		}

		// All the settings we need to read to display the page
		$sitemap_create_in_progress = get_option( 'msm_sitemap_create_in_progress' );
		$sitemap_halt_in_progress = get_option( 'msm_stop_processing' ) && ! $sitemap_create_in_progress;
		$sitemap_update_last_run = get_option( 'msm_sitemap_update_last_run' );
		$sitemap_update_next_run = $sitemap_update_last_run + 900;
		$modified_posts = Metro_Sitemap::get_last_modified_posts();
		$modified_posts_count = count( $modified_posts );
		$modified_posts_label = $modified_posts_count == 1 ? 'post' : 'posts';
		$days_to_process = get_option( 'msm_days_to_process' );

		// Determine sitemap status text
		$sitemap_create_status = __( 'Not Running', 'metro-sitemaps' );
		if ( $sitemap_halt_in_progress ) {
			$sitemap_create_status = __( 'Halting', 'metro-sitemaps' );
		} else if ( $sitemap_create_in_progress ) {
			$sitemap_create_status = __( 'Running', 'metro-sitemaps' );
		}
		
		echo '<p><strong>' . __( 'Sitemap Create Status:', 'metro-sitemaps' ) . '</strong> ' . esc_html( $sitemap_create_status );
		if ( $days_to_process ) {
			$years_to_process = get_option( 'msm_years_to_process' );
			$months_to_process = get_option( 'msm_months_to_process' );
			echo '<p><b>' . ( $sitemap_create_in_progress ? __( 'Current position:', 'metro-sitemaps' ) : __( 'Restart position:', 'metro-sitemaps' ) ). ' </b>';
			$current_day = count( $days_to_process ) - 1;
			$current_month = count( $months_to_process ) - 1;
			$current_year = count( $years_to_process ) - 1;
			printf( __('Day: %s Month: %s Year: %s</p>'), $days_to_process[$current_day], $months_to_process[$current_month], $years_to_process[$current_year] );
			$years_to_process = ( $current_year == 0 ) ? array( 1 ) : $years_to_process;
			printf( __('<p><b>Years to process:</b> %s </p>'), implode( ',', $years_to_process ) );
		}

		?>
		<p><strong><?php _e( 'Last updated:', 'metro-sitemaps' ); ?></strong> <?php echo human_time_diff( $sitemap_update_last_run ); ?> ago</p>
		<p><strong><?php _e( 'Next update:', 'metro-sitemaps' ); ?></strong> <?php echo $modified_posts_count . ' ' . $modified_posts_label; ?> will be updated in <?php echo human_time_diff( $sitemap_update_next_run ); ?></p>

		<h3><?php _e('Stats', 'metro-sitemaps') ?></h3>
		<p><?php printf( __('Currently Metro Sitemap has built %s sitemaps and indexed %s URLs.', 'metro-sitemaps'),
			'<strong>' . number_format( Metro_Sitemap::count_sitemaps() ) . '</strong>', '<strong>' . number_format( Metro_Sitemap::get_total_indexed_url_count() ) . '</strong>' ); ?> </p>

		<form action="<?php echo menu_page_url( 'metro-sitemap', false ) ?>" method="post" style="float: left;">
			<?php wp_nonce_field( 'msm-sitemap-action' ); ?>
			<?php if ( $sitemap_create_in_progress ): ?>
			<input type="submit" name="action" class="button-secondary" value="<?php echo esc_attr( $actions['halt-generation'] ); ?>">
			<?php else: ?>
			<input type="submit" name="action" class="button-secondary" value="<?php echo esc_attr( $actions['generate'] ); ?>">
			<input type="submit" name="action" class="button-secondary" value="<?php echo esc_attr( $actions['generate-from-latest'] ); ?>">
			<input type="submit" name="action" class="button-secondary" value="<?php echo esc_attr( $actions['reset-sitemap-data'] ); ?>">
			<?php endif; ?>
		</form>
		</div>
		<?php
	}

	/**
	 * 
	 * @param str $action The name of the action to perform
	 * @return str Returns the text describing the action that was performed
	 */
	public static function do_sitemap_action($action) {
		switch ( $action ) {
			case 'generate':
				$sitemap_create_in_progress = get_option( 'msm_sitemap_create_in_progress' );
				MSM_Sitemap_Builder_Cron::generate_full_sitemap();
				update_option( 'msm_sitemap_create_in_progress', true );

				if ( empty( $sitemap_create_in_progress ) ) {
					return __( 'Starting sitemap generation...', 'metro-sitemaps' );
				} else {
					return __( 'Resuming sitemap creation', 'metro-sitemaps' );
				}
			break;

			case 'generate-from-latest':
				$last_modified = Metro_Sitemap::get_last_modified_posts();
				if ( count( $last_modified ) > 0 ) {
					Metro_Sitemap::update_sitemap_from_modified_posts();
					return __( 'Updating sitemap from latest articles...', 'metro-sitemaps' );			
				} else {
					return __( 'Cannot generate from latest articles: no posts updated lately.', 'metro-sitemaps' );
				}
			break;

			case 'halt-generation':
				// Can only halt generation if sitemap creation is already in process
				if ( get_option( 'msm_stop_processing' ) ) {
					return __( 'Cannot stop sitemap generation: sitemap generation is already being halted.', 'metro-sitemaps' );
				} else if ( get_option( 'msm_sitemap_create_in_progress' ) ) {
					update_option( 'msm_stop_processing', true );
					return __( 'Stopping Sitemap generation', 'metro-sitemaps' );
				} else {
					return __( 'Cannot stop sitemap generation: sitemap generation not in progress', 'metro-sitemaps' );
				}
				
			break;

			case 'reset-sitemap-data':
				// Do the same as when we finish then tell use to delete manuallyrather than remove all data
				MSM_Sitemap_Builder_Cron::reset_sitemap_data();
				return __( 'Sitemap data reset. If you want to remove the data you must do so manually', 'metro-sitemaps' );
			break;

			default:
				return __( 'Unknown action', 'metro-sitemaps' );
			break;
		}
	}
		
	/**
	 * Counts the number of sitemaps that have been generated.
	 * 
	 * @return int The number of sitemaps that have been generated
	 */
	public static function count_sitemaps() {
		$count = wp_count_posts(Metro_Sitemap::SITEMAP_CPT);
		return (int) $count->publish;
	}
	
	/**
	 * Gets the current number of URLs indexed by msm-sitemap accross all sitemaps.
	 * 
	 * @return int The number of total number URLs indexed
	 */
	public static function get_total_indexed_url_count() {
		$counts = (array) get_option( 'msm_sitemap_indexed_url_count', array() );
		return array_sum( $counts );
	}
	
	/**
	 * Gets the number of URLs indexed for the given sitemap.
	 * 
	 * @param array $sitemaps The sitemaps to retrieve counts for. If $sitemaps is not given, counts are retrieved for all sitemaps.
	 */
	public static function get_indexed_url_count( $sitemaps = null ) {
		$counts = (array) get_option( 'msm_sitemap_indexed_url_count', array() );
		$return_vals = array();

		if ( is_null( $sitemaps ) )
			return $counts;

		foreach ( $sitemaps as $sitemap ) {
			if ( in_array( $sitemap, $counts ) ) 
				$return_vals[$sitemap] = (int) $counts[$sitemap];
		}

		return $return_vals;
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

		$url_counts = (array) get_option( 'msm_sitemap_indexed_url_count', array() );
		if ( ! $post_count ) {
			// If no entries - delete the whole sitemap post
			if ( $sitemap_exists ) {
				wp_delete_post( $sitemap_id, true );
				do_action( 'msm_delete_sitemap_post', $sitemap_id, $year, $month, $day );
				unset( $url_counts[$sitemap_name] );
				update_option( 'msm_sitemap_indexed_url_count' , $url_counts );
			}
			return;
		}

		// Create XML
		// SimpleXML doesn't allow us to define namespaces using addAttribute, so we need to specify them in the construction instead.
		$xml = new SimpleXMLElement( '<?xml version="1.0" encoding="utf-8"?><urlset xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:n="http://www.google.com/schemas/sitemap-news/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1" xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd"></urlset>' );

		$url_count = 0;
		while ( $query->have_posts() ) {
			$query->the_post();

			$url = $xml->addChild( 'url' );
			$url->addChild( 'loc', get_permalink() );
			$url->addChild( 'lastmod', get_the_modified_date( 'Y-m-d' ) . 'T' . get_the_modified_date( 'H:i:s' ) . 'Z' );
			$url->addChild( 'changefreq', 'monthly' );
			$url->addChild( 'priority', '0.7' );

			++$url_count;
			// TODO: add images to sitemap via <image:image> tag
		}

				// Save the sitemap
		if ( $sitemap_exists ) {
			update_post_meta( $sitemap_id, 'msm_sitemap_xml', $xml->asXML() );
			do_action( 'msm_update_sitemap_post', $sitemap_id, $year, $month, $day );
		} else {
			/* Should no longer hit this */
			$sitemap_id = wp_insert_post( $sitemap_data );
			add_post_meta( $sitemap_id, 'msm_sitemap_xml', $xml->asXML() );
			do_action( 'msm_insert_sitemap_post', $sitemap_id, $year, $month, $day );
		}

				// Update indexed url counts
		$url_counts[$sitemap_name] = $url_count;
		update_option( 'msm_sitemap_indexed_url_count' , $url_counts );

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

	public static function get_supported_post_types() {
		return apply_filters( 'msm_sitemap_entry_post_type', 'post' );
	}
}

add_action( 'after_setup_theme', array( 'Metro_Sitemap', 'setup' ) );
