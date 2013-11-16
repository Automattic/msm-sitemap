<?php

add_action( 'msm_insert_sitemap_post', 'msm_sitemap_queue_nginx_cache_invalidation', 10, 4 );
add_action( 'msm_delete_sitemap_post', 'msm_sitemap_queue_nginx_cache_invalidation', 10, 4 );
add_action( 'msm_update_sitemap_post', 'msm_sitemap_queue_nginx_cache_invalidation', 10, 4 );

/**
 * Queue action to invalidate nginx cache if on WPCOM
 * @param int $sitemap_id
 * @param string $year
 * @param string $month
 * @param string $day
 */
function msm_sitemap_queue_nginx_cache_invalidation( $sitemap_id, $year, $month, $day ) {
	if ( ! function_exists( 'queue_async_job' ) )
		return;

	$sitemap_url = home_url( '/sitemap.xml' );

	$sitemap_urls = array(
		add_query_arg( array( 'yyyy' => $year ), $sitemap_url ),
		add_query_arg( array( 'yyyy' => $year, 'mm' => $month ), $sitemap_url ),
		add_query_arg( array( 'yyyy' => $year, 'mm' => $month, 'dd' => $day ), $sitemap_url ),
	);

	queue_async_job( array( 'output_cache' => array( 'url' => $sitemap_urls ) ), 'wpcom_invalidate_output_cache_job', -16 );
}
