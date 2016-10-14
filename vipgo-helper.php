<?php

// On VIP Go we're blocking using cron as it creates a mess of cron for large datasets
add_filter('msm_sitemap_use_cron_builder', '__return_false', 9999);

// Add the update cron, since this is necessary for ongoing updates
add_action( 'msm_update_sitemap_for_year_month_date', 'vipgo_schedule_sitemap_update_for_year_month_date', 10, 2 );

function vipgo_schedule_sitemap_update_for_year_month_date( $date, $time ) {
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
