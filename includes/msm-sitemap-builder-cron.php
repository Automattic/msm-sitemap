<?php

class MSM_Sitemap_Builder_Cron {

	public static function setup() {
		add_action( 'msm_update_sitemap_for_year_month_date', array( __CLASS__, 'schedule_sitemap_update_for_year_month_date' ), 10, 2 );
		add_action( 'msm_cron_generate_sitemap_for_year_month_day', array( __CLASS__, 'generate_sitemap_for_year_month_day' ) );
	}

	public static function schedule_sitemap_update_for_year_month_date( $date, $time ) {
		list( $year, $month, $day ) = $date;

		wp_schedule_single_event(
			$time, 
			'msm_cron_generate_sitemap_for_year_month_day',
			array(
				array(
					'year' => (int) $year,
					'month' => (int) $month,
					'day' => (int) $day,
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
		} else {
			Metro_Sitemap::delete_sitemap_for_date( $date_stamp );
		}
	}
}
