<?php

class MSM_Sitemap_Builder_Cron {

	public static function setup() {
		add_action( 'msm_update_sitemap_for_year_month_date', array( __CLASS__, 'schedule_sitemap_update_for_year_month_date' ), 10, 2 );
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
}
