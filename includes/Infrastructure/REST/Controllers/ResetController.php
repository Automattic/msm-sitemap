<?php
/**
 * Reset REST Controller
 *
 * @package Automattic\MSM_Sitemap\Infrastructure\REST\Controllers
 */

declare(strict_types=1);

namespace Automattic\MSM_Sitemap\Infrastructure\REST\Controllers;

use Automattic\MSM_Sitemap\Application\Services\SitemapService;
use Automattic\MSM_Sitemap\Infrastructure\REST\Traits\RestControllerTrait;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST controller for reset operations.
 */
class ResetController {

	use RestControllerTrait;

	/**
	 * The sitemap service.
	 *
	 * @var SitemapService
	 */
	private SitemapService $sitemap_service;

	/**
	 * Constructor.
	 *
	 * @param SitemapService $sitemap_service The sitemap service.
	 */
	public function __construct( SitemapService $sitemap_service ) {
		$this->sitemap_service = $sitemap_service;
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/reset',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'reset_all_data' ),
					'permission_callback' => array( $this, 'check_manage_options_permission' ),
				),
			)
		);
	}

	/**
	 * Reset all data.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response The response object.
	 */
	public function reset_all_data( WP_REST_Request $request ): WP_REST_Response {
		$this->sitemap_service->reset_all_data();

		$response_data = array(
			'success' => true,
			'message' => __( 'Sitemap data reset. All sitemap posts, metadata, and processing options have been cleared.', 'msm-sitemap' ),
		);

		return rest_ensure_response( $response_data );
	}
}
