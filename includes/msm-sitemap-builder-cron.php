<?php

class MSM_Sitemap_Builder_Cron {

	function setup() {
		// TODO: move admin_menu handler to main plugin and work with our jobs builder too
		add_action( 'admin_menu', array( __CLASS__, 'metro_sitemap_menu' ) );

		add_action( 'msm_update_sitemap_for_year_month_date', array( __CLASS__, 'schedule_sitemap_update_for_year_month_date' ), 10, 2 );

		add_action( 'msm_cron_generate_sitemap_for_year', array( __CLASS__, 'generate_sitemap_for_year' ) );
		add_action( 'msm_cron_generate_sitemap_for_year_month', array( __CLASS__, 'generate_sitemap_for_year_month' ) );
		add_action( 'msm_cron_generate_sitemap_for_year_month_day', array( __CLASS__, 'generate_sitemap_for_year_month_day' ) );
	}

	/**
	 * Register admin menu for sitemap
	 */
	public static function metro_sitemap_menu() {
		add_menu_page( __( 'Sitemaps', 'metro-sitemaps' ), __( 'Sitemaps', 'metro-sitemaps' ), 'manage_options', 'edit.php?post_type=' . Metro_Sitemap::SITEMAP_CPT, '', '', 31 );
		add_management_page( __( 'Sitemap Options', 'metro-sitemaps' ), __( 'Create Sitemaps', 'metro-sitemaps' ), 'manage_options', 'metro-sitemap', array( __CLASS__, 'sitemap_options' ) );
	}

	/**
	 * Render admin options page
	 */
	public static function sitemap_options() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'metro-sitemaps' ) );
		}

		$sitemap_create_in_progress = get_option( 'msm_sitemap_create_in_progress' );
		$sitemap_update_last_run = get_option( 'msm_sitemap_update_last_run' );
		$sitemap_update_next_run = $sitemap_update_last_run + 900;
		$modified_posts = Metro_Sitemap::get_last_modified_posts();
		$modified_posts_count = count( $modified_posts );
		$modified_posts_label = $modified_posts_count == 1 ? 'post' : 'posts';

		echo '<div class="wrap">';
		screen_icon();
		echo '<h2>' . _e( 'Metro Sitemap', 'metro-sitemaps' ) . '</h2>';
		
		if ( isset( $_POST['action'] ) ) {
			$action = $_POST['action'];

			check_admin_referer( 'msm-sitemap-action' );

			switch ( $action ) {
				case 'Generate from all articles':
					self::generate_full_sitemap();
					update_option( 'msm_sitemap_create_in_progress', true );

					if ( empty( $sitemap_create_in_progress ) ) {
						echo '<p>' . _e( 'Creating sitemap...', 'metro-sitemaps' ) . '</p>';
					} else {
						echo '<p>' . _e( 'Resuming sitemap creation', 'metro-sitemaps' ) . '</p>';
					}
				break;

				case 'Generate from latest articles':
					$last_modified = Metro_Sitemap::get_last_modified_posts();
					if ( count( $last_modified ) > 0 ) {
						echo '<p>' . _e( 'Updating sitemap...', 'metro-sitemaps' ) . '</p>';
						Metro_Sitemap::update_sitemap_from_modified_posts();				
					} else {
						echo '<p>' . _e( 'No posts updated lately.', 'metro-sitemaps' ) . '</p>';
					}
				break;

				case 'Halt Sitemap Generation':
					update_option( 'msm_stop_processing', true );
					echo '<p>' . _e( 'Stopping Sitemap generation', 'metro-sitemaps' ) . '</p>';
				break;

				case 'Reset Sitemap Data':
					// Do the same as when we finish then tell use to delete manuallyrather than remove all data
					self::reset_sitemap_data();
					echo '<p>' . _e( 'If you want to remove the data you must do so manually', 'metro-sitemaps' ) . '</p>';
				break;

				default:
					echo '<p>' . _e( 'Unknown action', 'metro-sitemaps' ) . '</p>';
				break;
			}

			echo '<form action="tools.php">';
			echo ' <input type="hidden" name="page" value="metro-sitemap">';
			echo ' <input type="submit" value="Back">';
			echo '</form>';
		} else {
			$days_to_process = get_option( 'msm_days_to_process' );

			?>
			<p><strong>Sitemap Create Status:</strong> <?php echo ( empty( $sitemap_create_in_progress ) ) ? ' Not Running</p>' : ' Running</p><p><b>Current position:</b>'; ?>
			<?php
			if ( $days_to_process ) {
				$years_to_process = get_option( 'msm_years_to_process' );
				$months_to_process = get_option( 'msm_months_to_process' );
				if ( ! $sitemap_create_in_progress ) {
					echo '<p><b>' . _e( 'Restart position:', 'metro-sitemaps' ) . '</b>';
				}
				$current_day = count( $days_to_process ) - 1;
				$current_month = count( $months_to_process ) - 1;
				$current_year = count( $years_to_process ) - 1;
				printf( 'Day: %s Month: %s Year: %s</p>', $days_to_process[$current_day], $months_to_process[$current_month], $years_to_process[$current_year] );
				$years_to_process = ( $current_year == 0 ) ? array( 1 ) : $years_to_process;
				printf( '<p><b>Years to process:</b> %s </p>', implode( ',', $years_to_process ) );
			}
			?>
			<p><strong><?php _e( 'Last updated:', 'metro-sitemaps' ); ?></strong> <?php echo human_time_diff( $sitemap_update_last_run ); ?> ago</p>
			<p><strong><?php _e( 'Next update:', 'metro-sitemaps' ); ?></strong> <?php echo $modified_posts_count . ' ' . $modified_posts_label; ?> will be updated in <?php echo human_time_diff( $sitemap_update_next_run ); ?></p>
			<?php
			echo '<form action="'. menu_page_url( 'metro-sitemap', false ) .'" method="post" style="float: left;">';
			wp_nonce_field( 'msm-sitemap-action' );
			$disabled = ( $sitemap_create_in_progress ) ? ' disabled="disabled" ' : '';
			echo ' <input type="submit" name="action" value="Generate from all articles"' . $disabled . '>';
			echo ' <input type="submit" name="action" value="Generate from latest articles">';
			echo ' <input type="submit" name="action" value="Halt Sitemap Generation">';
			echo ' <input type="submit" name="action" value="Reset Sitemap Data">';
			echo '</form>';
		}
		echo '</div>';

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

		$time = time();
		$next_year = $all_years_with_posts[count( $all_years_with_posts ) - 1];

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
		$next_month = $months[count( $months ) - 1];

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

		$next_element = count( $days ) - 1;
		$next_day = $days[$next_element];


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
		} else if ( $total_years ) {
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