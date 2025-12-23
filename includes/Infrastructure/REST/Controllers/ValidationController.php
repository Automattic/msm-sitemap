<?php
/**
 * Validation REST Controller
 *
 * @package Automattic\MSM_Sitemap\Infrastructure\REST\Controllers
 */

declare(strict_types=1);

namespace Automattic\MSM_Sitemap\Infrastructure\REST\Controllers;

use Automattic\MSM_Sitemap\Application\Services\SitemapService;
use Automattic\MSM_Sitemap\Application\Services\SitemapValidationService;
use Automattic\MSM_Sitemap\Infrastructure\REST\Traits\RestControllerTrait;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * REST controller for sitemap validation.
 */
class ValidationController {

	use RestControllerTrait;

	/**
	 * The sitemap service.
	 *
	 * @var SitemapService
	 */
	private SitemapService $sitemap_service;

	/**
	 * The validation service.
	 *
	 * @var SitemapValidationService
	 */
	private SitemapValidationService $validation_service;

	/**
	 * Constructor.
	 *
	 * @param SitemapService           $sitemap_service    The sitemap service.
	 * @param SitemapValidationService $validation_service The validation service.
	 */
	public function __construct(
		SitemapService $sitemap_service,
		SitemapValidationService $validation_service
	) {
		$this->sitemap_service    = $sitemap_service;
		$this->validation_service = $validation_service;
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/validate',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'validate_sitemaps' ),
					'permission_callback' => array( $this, 'check_manage_options_permission' ),
					'args'                => $this->get_validate_params(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/validate/(?P<date>[0-9]{4}-[0-9]{2}-[0-9]{2})',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_validation_status' ),
					'permission_callback' => array( $this, 'check_manage_options_permission' ),
					'args'                => array(
						'date' => array(
							'validate_callback' => array( $this, 'validate_date_format' ),
						),
					),
				),
			)
		);
	}

	/**
	 * Get validation parameters.
	 *
	 * @return array Parameters for validation endpoint.
	 */
	private function get_validate_params(): array {
		return array(
			'date'         => array(
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => array( $this, 'validate_date_format' ),
			),
			'date_queries' => array(
				'required'          => false,
				'type'              => 'array',
				'validate_callback' => array( $this, 'validate_date_queries' ),
			),
		);
	}

	/**
	 * Validate sitemaps.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error The response object.
	 */
	public function validate_sitemaps( WP_REST_Request $request ) {
		$date         = $request->get_param( 'date' );
		$date_queries = $request->get_param( 'date_queries' );

		if ( ! empty( $date ) ) {
			$date_queries = array(
				array(
					'year'  => substr( $date, 0, 4 ),
					'month' => substr( $date, 5, 2 ),
					'day'   => substr( $date, 8, 2 ),
				),
			);
		}

		if ( empty( $date_queries ) ) {
			return new WP_Error(
				'rest_missing_param',
				__( 'Either date or date_queries parameter is required.', 'msm-sitemap' ),
				array( 'status' => 400 )
			);
		}

		$validation_result = $this->validation_service->validate_sitemaps( $date_queries );

		return rest_ensure_response( $validation_result );
	}

	/**
	 * Get validation status for a specific sitemap.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error The response object.
	 */
	public function get_validation_status( WP_REST_Request $request ) {
		$date = $request->get_param( 'date' );

		$sitemap_data = $this->sitemap_service->get_sitemap_data( $date );

		if ( null === $sitemap_data ) {
			return new WP_Error(
				'rest_not_found',
				__( 'Sitemap not found for the specified date.', 'msm-sitemap' ),
				array( 'status' => 404 )
			);
		}

		$validation_result = $this->validation_service->validate_sitemap_xml( $sitemap_data['xml_content'] );

		$validation_status = array(
			'date'      => $date,
			'valid'     => $validation_result['valid'],
			'errors'    => $validation_result['errors'],
			'warnings'  => $validation_result['warnings'],
			'url_count' => $sitemap_data['url_count'],
		);

		return rest_ensure_response( $validation_status );
	}
}
