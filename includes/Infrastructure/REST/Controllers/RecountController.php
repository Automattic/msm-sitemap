<?php
/**
 * Recount REST Controller
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
 * REST controller for URL recount operations.
 */
class RecountController {

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
			'/recount',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'recount_urls' ),
					'permission_callback' => array( $this, 'check_manage_options_permission' ),
					'args'                => $this->get_recount_params(),
				),
			)
		);
	}

	/**
	 * Get recount parameters.
	 *
	 * @return array Parameters for recount endpoint.
	 */
	private function get_recount_params(): array {
		return array(
			'date'         => array(
				'type'        => 'string',
				'format'      => 'date',
				'description' => 'Date in YYYY-MM-DD format',
			),
			'date_queries' => array(
				'type'  => 'array',
				'items' => array(
					'type'       => 'object',
					'properties' => array(
						'year'  => array( 'type' => 'integer' ),
						'month' => array( 'type' => 'integer' ),
						'day'   => array( 'type' => 'integer' ),
					),
				),
			),
			'full'         => array(
				'type'        => 'boolean',
				'default'     => false,
				'description' => 'Perform full recount',
			),
		);
	}

	/**
	 * Recount URLs for sitemaps.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response The response object.
	 */
	public function recount_urls( WP_REST_Request $request ): WP_REST_Response {
		$full_recount = $request->get_param( 'full' ) ?? false;

		$result = $this->sitemap_service->recount_urls( $full_recount );

		$response_data = array(
			'success' => $result->is_success(),
			'count'   => $result->get_count(),
			'message' => $result->get_message(),
		);

		if ( ! $result->is_success() ) {
			$response_data['error_code']     = $result->get_error_code();
			$response_data['recount_errors'] = $result->get_recount_errors();
		}

		$this->clear_sitemap_caches();

		$response = rest_ensure_response( $response_data );
		$response->set_status( $result->is_success() ? 200 : 400 );

		return $response;
	}
}
