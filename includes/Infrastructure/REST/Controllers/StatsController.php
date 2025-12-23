<?php
/**
 * Stats REST Controller
 *
 * @package Automattic\MSM_Sitemap\Infrastructure\REST\Controllers
 */

declare(strict_types=1);

namespace Automattic\MSM_Sitemap\Infrastructure\REST\Controllers;

use Automattic\MSM_Sitemap\Application\Services\SitemapStatsService;
use Automattic\MSM_Sitemap\Infrastructure\REST\Traits\RestControllerTrait;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST controller for sitemap statistics.
 */
class StatsController {

	use RestControllerTrait;

	/**
	 * The stats service.
	 *
	 * @var SitemapStatsService
	 */
	private SitemapStatsService $stats_service;

	/**
	 * Constructor.
	 *
	 * @param SitemapStatsService $stats_service The stats service.
	 */
	public function __construct( SitemapStatsService $stats_service ) {
		$this->stats_service = $stats_service;
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/stats',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_stats' ),
					'permission_callback' => array( $this, 'check_manage_options_permission' ),
					'args'                => $this->get_stats_params(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/recent-urls',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_recent_urls' ),
					'permission_callback' => array( $this, 'check_manage_options_permission' ),
					'args'                => $this->get_recent_urls_params(),
				),
			)
		);
	}

	/**
	 * Get stats parameters.
	 *
	 * @return array Parameters for stats endpoint.
	 */
	private function get_stats_params(): array {
		return array(
			'date_range' => array(
				'default'           => 'all',
				'sanitize_callback' => 'sanitize_text_field',
				'enum'              => array( 'all', '7', '30', '90', '180', '365', 'custom' ),
			),
			'start_date' => array(
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => array( $this, 'validate_date_format' ),
			),
			'end_date'   => array(
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => array( $this, 'validate_date_format' ),
			),
		);
	}

	/**
	 * Get recent URLs parameters.
	 *
	 * @return array Parameters for recent URLs endpoint.
	 */
	private function get_recent_urls_params(): array {
		return array(
			'days' => array(
				'type'        => 'integer',
				'default'     => 7,
				'minimum'     => 1,
				'maximum'     => 365,
				'description' => 'Number of days to show',
			),
		);
	}

	/**
	 * Get statistics.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response The response object.
	 */
	public function get_stats( WP_REST_Request $request ): WP_REST_Response {
		$cache_key = 'msm_sitemap_rest_stats_' . md5( serialize( $request->get_params() ) );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return rest_ensure_response( $cached );
		}

		$date_range = $request->get_param( 'date_range' );
		$start_date = $request->get_param( 'start_date' );
		$end_date   = $request->get_param( 'end_date' );

		$stats = $this->stats_service->get_comprehensive_stats( $date_range, $start_date, $end_date );

		set_transient( $cache_key, $stats, 15 * MINUTE_IN_SECONDS );

		return rest_ensure_response( $stats );
	}

	/**
	 * Get recent URL counts.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response The response object.
	 */
	public function get_recent_urls( WP_REST_Request $request ): WP_REST_Response {
		$days = $request->get_param( 'days' ) ?? 7;

		$recent_counts = $this->stats_service->get_recent_url_counts( $days );

		return rest_ensure_response( $recent_counts );
	}
}
