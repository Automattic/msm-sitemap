<?php
/**
 * Health REST Controller
 *
 * @package Automattic\MSM_Sitemap\Infrastructure\REST\Controllers
 */

declare(strict_types=1);

namespace Automattic\MSM_Sitemap\Infrastructure\REST\Controllers;

use Automattic\MSM_Sitemap\Infrastructure\REST\Traits\RestControllerTrait;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST controller for health check endpoint.
 */
class HealthController {

	use RestControllerTrait;

	/**
	 * Register REST API routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/health',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_health' ),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	/**
	 * Get health status.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response The response object.
	 */
	public function get_health( WP_REST_Request $request ): WP_REST_Response {
		$health_data = array(
			'status'    => 'healthy',
			'timestamp' => current_time( 'mysql' ),
			'version'   => '1.5.2',
			'features'  => array(
				'sitemap_generation' => true,
				'statistics'         => true,
				'validation'         => true,
				'export'             => true,
				'cron'               => true,
			),
		);

		return rest_ensure_response( $health_data );
	}
}
