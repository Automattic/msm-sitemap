<?php
/**
 * Sitemap builder cron handler.
 *
 * @package Automattic\MSM_Sitemap
 */

declare(strict_types=1);

// namespace Automattic\MSM_Sitemap;

use Automattic\MSM_Sitemap\Cron_Service;

/**
 * Sitemap builder cron handler.
 */
class MSM_Sitemap_Builder_Cron {

	/**
	 * Setup the cron functionality
	 */
	public static function setup() {
		add_action( 'msm_update_sitemap_for_year_month_date', array( __CLASS__, 'schedule_sitemap_update_for_year_month_date' ), 10, 2 );

		add_action( 'msm_cron_generate_sitemap_for_year', array( __CLASS__, 'generate_sitemap_for_year' ) );
		add_action( 'msm_cron_generate_sitemap_for_year_month', array( __CLASS__, 'generate_sitemap_for_year_month' ) );
		add_action( 'msm_cron_generate_sitemap_for_year_month_day', array( __CLASS__, 'generate_sitemap_for_year_month_day' ) );

		if ( is_admin() ) {
			add_filter( 'msm_sitemap_create_status', array( __CLASS__, 'sitemap_create_status' ) );
		}
	}

	/**
	 * Adds the "Halting" sitemap create status as this status is specific to the
	 * builder cron.
	 *
	 * Hooked into the msm_sitemap_create_status filter.
	 *
	 * @param string $status The status text to show the user on the admin page.
	 * @return string The status text.
	 */
	public static function sitemap_create_status( $status ) {
		if ( (bool) get_option( 'msm_stop_processing' ) === true && (bool) get_option( 'msm_sitemap_create_in_progress' ) === true ) {
			return __( 'Halting', 'msm-sitemap' );
		}
			
		return $status;
	}

	/**
	 * Generates full sitemaps for the site.
	 *
	 * Called by ActionHandlers::handle_generate_full()
	 */
	public static function action_generate() {
		$sitemap_create_in_progress = (bool) get_option( 'msm_sitemap_create_in_progress' );
		self::generate_full_sitemap();

		if ( false !== get_option( 'msm_sitemap_create_in_progress', false ) ) {
			update_option( 'msm_sitemap_create_in_progress', true );
		} else {
			add_option( 'msm_sitemap_create_in_progress', true, '', 'no' );
		}
	}

	/**
	 * Checks if we can generate sitemaps from the latest posts.
	 *
	 * @return bool
	 */
	public static function can_generate_from_latest() {
		return count( Metro_Sitemap::get_last_modified_posts() ) > 0;
	}

	/**
	 * Generates sitemaps from the latest posts.
	 *
	 * Called by ActionHandlers::handle_generate_from_latest()
	 */
	public static function generate_from_latest() {
		if ( self::can_generate_from_latest() ) {
			Metro_Sitemap::update_sitemap_from_modified_posts();
		}
	}

	/**
	 * Reset sitemap options
	 */
	public static function reset_sitemap_data() {
		// Remove the stats meta information
		delete_post_meta_by_key( 'msm_indexed_url_count' );

		// Remove the XML sitemap data
		delete_post_meta_by_key( 'msm_sitemap_xml' );

		// Delete state options
		delete_option( 'msm_days_to_process' );
		delete_option( 'msm_months_to_process' );
		delete_option( 'msm_years_to_process' );
		delete_option( 'msm_stop_processing' );
		delete_option( 'msm_sitemap_create_in_progress' );

		// Delete stats options
		delete_option( 'msm_sitemap_indexed_url_count' );
	}



	public static function schedule_sitemap_update_for_year_month_date( $date, $time ) {
		list( $year, $month, $day ) = $date;

		wp_schedule_single_event(
			$time,
			'msm_cron_generate_sitemap_for_year_month_day',
			array(
				array(
					'year'  => (int) $year,
					'month' => (int) $month,
					'day'   => (int) $day,
				),
			)
		);
	}

	/*
	 * We want to generate the entire sitemap catalogue async to avoid running into timeout and memory issues.
	 *
	 * Here's how it all works:
	 *
	 * -- Get year range for content
	 * -- Store these years in options table
	 * -- Cascade through all months and days in reverse order i.e. newest first
	 * -- Generate cron event for each individual day and when finished queue up the cron for the next one
	 * -- Add each post from that day to the custom post
	 *
	 */

	/**
	 * Generate full sitemap
	 */
	public static function generate_full_sitemap() {
		// Check if cron is enabled before processing
		if ( ! Cron_Service::is_cron_enabled() ) {
			return;
		}

		global $wpdb;

		$is_partial_or_running = get_option( 'msm_years_to_process' );

		if ( empty( $is_partial_or_running ) ) {
			$all_years_with_posts = Metro_Sitemap::check_year_has_posts();
			update_option( 'msm_years_to_process', $all_years_with_posts );
		} else {
			$all_years_with_posts = $is_partial_or_running;
		}

		if ( 0 == count( $all_years_with_posts ) ) {
			return; // Cannot generate sitemaps if there are no posts
		}

		$time      = time();
		$next_year = end( $all_years_with_posts );

		wp_schedule_single_event(
			$time,
			'msm_cron_generate_sitemap_for_year',
			array(
				array(
					'year' => (int) $next_year,
				),
			)
		);
	}

	/**
	 * Generate sitemap for a given year
	 *
	 * @param mixed[] $args
	 */
	public static function generate_sitemap_for_year( $args ) {
		// Check if cron is enabled before processing
		if ( ! Cron_Service::is_cron_enabled() ) {
			return;
		}

		$is_partial_or_running = get_option( 'msm_months_to_process' );

		$year      = $args['year'];
		$max_month = 12;
		if ( $year == date( 'Y' ) ) {
			$max_month = date( 'n' );
		}

		if ( empty( $is_partial_or_running ) ) {
			$months = range( 1, $max_month );
			update_option( 'msm_months_to_process', $months );
		} else {
			$months = $is_partial_or_running;
		}

		$time       = time();
		$next_month = end( $months );

		wp_schedule_single_event(
			$time,
			'msm_cron_generate_sitemap_for_year_month',
			array(
				array(
					'year'  => (int) $year,
					'month' => (int) $next_month,
				),
			)
		);
	}

	/**
	 * Generate sitemap for a given month in a given year
	 *
	 * @param mixed[] $args
	 */
	public static function generate_sitemap_for_year_month( $args ) {
		// Check if cron is enabled before processing
		if ( ! Cron_Service::is_cron_enabled() ) {
			return;
		}

		$is_partial_or_running = get_option( 'msm_days_to_process' );

		$year  = $args['year'];
		$month = $args['month'];

		// cal_days_in_month doesn't exist on WP.com so set it to a possible max. Will skip invalid dates as no posts will be found
		if ( ! function_exists( 'cal_days_in_month' ) ) {
			$max_days = 31;
		} else {
			$max_days = cal_days_in_month( CAL_GREGORIAN, (int) $month, (int) $year );
		}

		if ( date( 'Y' ) == $year && $month == date( 'n' ) ) {
			$max_days = date( 'j' );
		}

		if ( empty( $is_partial_or_running ) ) {
			$days = range( 1, $max_days );
			update_option( 'msm_days_to_process', $days );
		} else {
			$days = $is_partial_or_running;
		}

		$next_day = end( $days );

		$time = time();

		wp_schedule_single_event(
			$time,
			'msm_cron_generate_sitemap_for_year_month_day',
			array(
				array(
					'year'  => (int) $year,
					'month' => (int) $month,
					'day'   => (int) $next_day,
				),
			)
		);
	}

	/**
	 * Generate sitemap for a given year, month, day
	 *
	 * @param mixed[] $args
	 */
	public static function generate_sitemap_for_year_month_day( $args ) {
		// Check if cron is enabled before processing
		if ( ! Cron_Service::is_cron_enabled() ) {
			return;
		}

		$year  = $args['year'];
		$month = $args['month'];
		$day   = $args['day'];

		$date_stamp = Metro_Sitemap::get_date_stamp( $year, $month, $day );
		if ( Metro_Sitemap::date_range_has_posts( $date_stamp, $date_stamp ) ) {
			Metro_Sitemap::generate_sitemap_for_date( $date_stamp );
		} else {
			Metro_Sitemap::delete_sitemap_for_date( $date_stamp );
		}

		self::find_next_day_to_process( $year, $month, $day );
	}

	/**
	 * Find the next day with posts to process
	 *
	 * @param int $year
	 * @param int $month
	 * @param int $day
	 * @return void, just updates options.
	 */
	public static function find_next_day_to_process( $year, $month, $day ) {
		$halt = (bool) get_option( 'msm_stop_processing' ) === true;
		if ( $halt || ! Metro_Sitemap::is_blog_public() ) {
			// Allow user to bail out of the current process, doesn't remove where the job got up to
			// or If the blog became private while sitemaps were enabled, stop here.
			delete_option( 'msm_stop_processing' );
			delete_option( 'msm_sitemap_create_in_progress' );
			return;
		}

		update_option( 'msm_sitemap_create_in_progress', true );

		$days_being_processed   = (array) get_option( 'msm_days_to_process', array() );
		$months_being_processed = (array) get_option( 'msm_months_to_process', array() );
		$years_being_processed  = (array) get_option( 'msm_years_to_process', array() );

		$total_days   = count( $days_being_processed );
		$total_months = count( $months_being_processed );
		$total_years  = count( $years_being_processed );

		if ( $total_days && $day > 1 ) {
			// Day has finished
			unset( $days_being_processed[ $total_days - 1 ] );
			update_option( 'msm_days_to_process', $days_being_processed );
			self::generate_sitemap_for_year_month(
				array(
					'year'  => $year,
					'month' => $month,
				) 
			);
		} elseif ( $total_months and $month > 1 ) {
			// Month has finished
			unset( $months_being_processed[ $total_months - 1 ] );
			delete_option( 'msm_days_to_process' );
			update_option( 'msm_months_to_process', $months_being_processed );
			self::generate_sitemap_for_year( array( 'year' => $year ) );
		} elseif ( $total_years > 1 ) {
			// Year has finished
			unset( $years_being_processed[ $total_years - 1 ] );
			delete_option( 'msm_days_to_process' );
			delete_option( 'msm_months_to_process' );
			update_option( 'msm_years_to_process', $years_being_processed );
			self::generate_full_sitemap();
		} else {
			// We've finished - remove all options
			delete_option( 'msm_days_to_process' );
			delete_option( 'msm_months_to_process' );
			delete_option( 'msm_years_to_process' );
			delete_option( 'msm_sitemap_create_in_progress' );
		}
	}
}
