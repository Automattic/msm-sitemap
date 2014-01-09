<?php

class MSM_Sitemap_Builder_Cron {

	function setup() {
		// TODO: move admin_menu handler to main plugin and work with our jobs builder too
		add_action( 'msm_update_sitemap_for_year_month_date', array( __CLASS__, 'schedule_sitemap_update_for_year_month_date' ), 10, 2 );

		add_action( 'msm_cron_generate_sitemap_for_year', array( __CLASS__, 'generate_sitemap_for_year' ) );
		add_action( 'msm_cron_generate_sitemap_for_year_month', array( __CLASS__, 'generate_sitemap_for_year_month' ) );
		add_action( 'msm_cron_generate_sitemap_for_year_month_day', array( __CLASS__, 'generate_sitemap_for_year_month_day' ) );
	}

	/**
	 * Reset sitemap options
	 */
	public static function reset_sitemap_data() {
		delete_option( 'msm_days_to_process' );
		delete_option( 'msm_months_to_process' );
		delete_option( 'msm_years_to_process' );
		update_option( 'msm_stop_processing', true );
		delete_option( 'msm_sitemap_create_in_progress' );
                delete_option( 'msm_sitemap_indexed_url_count' );
	}

	function schedule_sitemap_update_for_year_month_date( $date, $time ) {
		list( $year, $month, $day ) = $date;
                
		wp_schedule_single_event(
			$time, 
			'msm_cron_generate_sitemap_for_year_month_day',
			array(
				array(
					'year' => $year,
					'month' => $month,
					'day' => $day,
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
		global $wpdb;

		$is_partial_or_running = get_option( 'msm_years_to_process' );

		if ( empty( $is_partial_or_running ) ) {
			$all_years_with_posts = Metro_Sitemap::check_year_has_posts();
			update_option( 'msm_years_to_process', $all_years_with_posts );
		} else {
			$all_years_with_posts = $is_partial_or_running;
		}
                
                if ( 0 == count($all_years_with_posts) )
                    return; // Cannot generate sitemaps if there are no posts
                
		$time = time();
		$next_year = end($all_years_with_posts);

		wp_schedule_single_event(
			$time, 
			'msm_cron_generate_sitemap_for_year', 
			array(
				array(
					'year' => $next_year,
				),
			)
		);
	}

	/**
	 * Generate sitemap for a given year
	 * @param mixed[] $args
	 */
	public static function generate_sitemap_for_year( $args ) {

		$is_partial_or_running = get_option( 'msm_months_to_process' );

		$year = $args['year'];
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

		$time = time();
		$next_month = end($months);
                
		wp_schedule_single_event(
			$time,
			'msm_cron_generate_sitemap_for_year_month',
			array(
				array(
					'year' => $year,
					'month' => $next_month,
				),
			)
		);
	}

	/**
	 * Generate sitemap for a given month in a given year
	 * @param mixed[] $args
	 */
	public static function generate_sitemap_for_year_month( $args ) {

		$is_partial_or_running = get_option( 'msm_days_to_process' );

		$year = $args['year'];
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
                
		$next_day = end($days);

		$time = time();
                
		wp_schedule_single_event(
			$time,
			'msm_cron_generate_sitemap_for_year_month_day',
			array(
				array(
					'year' => $year,
					'month' => $month,
					'day' => $next_day,
				),
			)
		);
		
	}

	/**
	 * Generate sitemap for a given year, month, day
	 * @param mixed[] $args
	 */
	public static function generate_sitemap_for_year_month_day( $args ) {
		$year = $args['year'];
		$month = $args['month'];
		$day = $args['day'];
                
		$date_stamp = Metro_Sitemap::get_date_stamp( $year, $month, $day );
		if ( Metro_Sitemap::date_range_has_posts( $date_stamp, $date_stamp ) ) {
			Metro_Sitemap::generate_sitemap_for_date( $date_stamp );
		}
                
		self::find_next_day_to_process( $year, $month, $day );
	}
	
	/**
	 * Find the next day with posts to process
	 * @param int $year
	 * @param int $month
	 * @param int $day
	 * @return void, just updates options.
	 */
	public static function find_next_day_to_process( $year, $month, $day ) {

		$halt = get_option( 'msm_stop_processing' );
		if ( $halt ) {
			// Allow user to bail out of the current process, doesn't remove where the job got up to
			delete_option( 'msm_stop_processing' );
			delete_option( 'msm_sitemap_create_in_progress' );
			return;
		}

		update_option( 'msm_sitemap_create_in_progress', true );

		$days_being_processed = get_option( 'msm_days_to_process' );
		$months_being_processed = get_option( 'msm_months_to_process' );
		$years_being_processed = get_option( 'msm_years_to_process' );

		$total_days = count( $days_being_processed );
		$total_months = count( $months_being_processed );
		$total_years = count( $years_being_processed );
                
		if ( $total_days && $day > 1 ) {
			// Day has finished
			unset( $days_being_processed[$total_days - 1] );
			update_option( 'msm_days_to_process', $days_being_processed );
			self::generate_sitemap_for_year_month( array( 'year' => $year, 'month' => $month ) );
		} else if ( $total_months and $month > 1 ) {
			// Month has finished
			unset( $months_being_processed[ $total_months - 1] );
			delete_option( 'msm_days_to_process' );
			update_option( 'msm_months_to_process', $months_being_processed );
			self::generate_sitemap_for_year( array( 'year' => $year ) );
		} else if ( $total_years > 1 ) {
			// Year has finished
			unset( $years_being_processed[ $total_years - 1] );
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