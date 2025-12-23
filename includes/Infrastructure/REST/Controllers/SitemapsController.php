<?php
/**
 * Sitemaps REST Controller
 *
 * @package Automattic\MSM_Sitemap\Infrastructure\REST\Controllers
 */

declare(strict_types=1);

namespace Automattic\MSM_Sitemap\Infrastructure\REST\Controllers;

use Automattic\MSM_Sitemap\Application\Services\SitemapService;
use Automattic\MSM_Sitemap\Application\UseCases\GenerateSitemapUseCase;
use Automattic\MSM_Sitemap\Application\Commands\GenerateSitemapCommand;
use Automattic\MSM_Sitemap\Application\DTOs\SitemapOperationResult;
use Automattic\MSM_Sitemap\Infrastructure\REST\Traits\RestControllerTrait;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * REST controller for sitemap CRUD operations.
 */
class SitemapsController {

	use RestControllerTrait;

	/**
	 * The sitemap service.
	 *
	 * @var SitemapService
	 */
	private SitemapService $sitemap_service;

	/**
	 * The generate sitemap use case.
	 *
	 * @var GenerateSitemapUseCase
	 */
	private GenerateSitemapUseCase $generate_use_case;

	/**
	 * Constructor.
	 *
	 * @param SitemapService         $sitemap_service   The sitemap service.
	 * @param GenerateSitemapUseCase $generate_use_case The generate sitemap use case.
	 */
	public function __construct(
		SitemapService $sitemap_service,
		GenerateSitemapUseCase $generate_use_case
	) {
		$this->sitemap_service   = $sitemap_service;
		$this->generate_use_case = $generate_use_case;
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/sitemaps',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_sitemaps' ),
					'permission_callback' => array( $this, 'check_manage_options_permission' ),
					'args'                => $this->get_collection_params(),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_sitemap' ),
					'permission_callback' => array( $this, 'check_manage_options_permission' ),
					'args'                => $this->get_create_params(),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete_sitemaps' ),
					'permission_callback' => array( $this, 'check_manage_options_permission' ),
					'args'                => $this->get_delete_params(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/sitemaps/(?P<date>[0-9]{4}-[0-9]{2}-[0-9]{2})',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_sitemap' ),
					'permission_callback' => '__return_true',
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
	 * Get collection parameters.
	 *
	 * @return array Parameters for collection endpoint.
	 */
	private function get_collection_params(): array {
		return array(
			'page'      => array(
				'default'           => 1,
				'sanitize_callback' => 'absint',
				'validate_callback' => function ( $param ) {
					return $param > 0;
				},
			),
			'per_page'  => array(
				'default'           => 10,
				'sanitize_callback' => 'absint',
				'validate_callback' => function ( $param ) {
					return $param > 0 && $param <= 100;
				},
			),
			'date_from' => array(
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => array( $this, 'validate_date_format' ),
			),
			'date_to'   => array(
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => array( $this, 'validate_date_format' ),
			),
			'year'      => array(
				'sanitize_callback' => 'absint',
				'validate_callback' => function ( $param ) {
					return $param >= 1900 && $param <= 2100;
				},
			),
			'month'     => array(
				'sanitize_callback' => 'absint',
				'validate_callback' => function ( $param ) {
					return $param >= 1 && $param <= 12;
				},
			),
		);
	}

	/**
	 * Get create parameters.
	 *
	 * @return array Parameters for create endpoint.
	 */
	private function get_create_params(): array {
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
			'force'        => array(
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
			),
			'all'          => array(
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
			),
		);
	}

	/**
	 * Get delete parameters.
	 *
	 * @return array Parameters for delete endpoint.
	 */
	private function get_delete_params(): array {
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
	 * Get sitemaps collection.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error The response object.
	 */
	public function get_sitemaps( WP_REST_Request $request ) {
		$cache_key = 'msm_sitemap_rest_sitemaps_' . md5( serialize( $request->get_params() ) );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return rest_ensure_response( $cached );
		}

		$date_queries = array();

		if ( $request->get_param( 'year' ) ) {
			$query = array( 'year' => $request->get_param( 'year' ) );
			if ( $request->get_param( 'month' ) ) {
				$query['month'] = $request->get_param( 'month' );
			}
			$date_queries[] = $query;
		}

		$sitemap_data = $this->sitemap_service->get_sitemap_list_data( $date_queries );

		$page     = $request->get_param( 'page' );
		$per_page = $request->get_param( 'per_page' );
		$offset   = ( $page - 1 ) * $per_page;

		$total_sitemaps = count( $sitemap_data );
		$sitemaps       = array_slice( $sitemap_data, $offset, $per_page );

		$response_data = array(
			'sitemaps'    => $sitemaps,
			'total'       => $total_sitemaps,
			'page'        => $page,
			'per_page'    => $per_page,
			'total_pages' => ceil( $total_sitemaps / $per_page ),
		);

		set_transient( $cache_key, $response_data, 5 * MINUTE_IN_SECONDS );

		return rest_ensure_response( $response_data );
	}

	/**
	 * Create sitemap(s).
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error The response object.
	 */
	public function create_sitemap( WP_REST_Request $request ) {
		$date         = $request->get_param( 'date' );
		$date_queries = $request->get_param( 'date_queries' );
		$force        = $request->get_param( 'force' );
		$all          = $request->get_param( 'all' );

		$command = new GenerateSitemapCommand( $date, $date_queries ? $date_queries : array(), $force, $all );
		$result  = $this->generate_use_case->execute( $command );

		$response_data = array(
			'success' => $result->is_success(),
			'count'   => $result->get_count(),
			'message' => $result->get_message(),
		);

		$status_code = 200;

		if ( $result->is_success() ) {
			$status_code = $result->get_count() > 0 ? 201 : 200;
		} elseif ( 'sitemap_exists' === $result->get_error_code() ) {
			$status_code = 409;
		} elseif ( 'no_content' === $result->get_error_code() ) {
			$status_code = 422;
		} else {
			$status_code = 400;
		}

		if ( ! $result->is_success() ) {
			$response_data['error_code'] = $result->get_error_code();
		}

		$this->clear_sitemap_caches();

		$response = rest_ensure_response( $response_data );
		$response->set_status( $status_code );

		return $response;
	}

	/**
	 * Delete sitemap(s).
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error The response object.
	 */
	public function delete_sitemaps( WP_REST_Request $request ) {
		$date         = $request->get_param( 'date' );
		$date_queries = $request->get_param( 'date_queries' );

		if ( $date ) {
			$success = $this->sitemap_service->delete_for_date( $date );
			$result  = array(
				'success'       => $success,
				'deleted_count' => $success ? 1 : 0,
			);
		} elseif ( $date_queries ) {
			$operation_result = $this->sitemap_service->delete_for_date_queries( $date_queries );

			if ( $operation_result instanceof SitemapOperationResult ) {
				$result = array(
					'success'       => $operation_result->is_success(),
					'deleted_count' => $operation_result->get_count(),
					'message'       => $operation_result->get_message(),
				);

				if ( ! $result['success'] ) {
					$result['error_code'] = $operation_result->get_error_code();
				}
			} else {
				$result = $operation_result;
			}
		} else {
			return new WP_Error(
				'rest_missing_param',
				__( 'Either date or date_queries parameter is required.', 'msm-sitemap' ),
				array( 'status' => 400 )
			);
		}

		$status_code = 200;

		if ( isset( $result['success'] ) && $result['success'] ) {
			$status_code = ( isset( $result['deleted_count'] ) && $result['deleted_count'] > 0 ) ? 204 : 200;
		} else {
			$status_code = 400;
		}

		$this->clear_sitemap_caches();

		$response = rest_ensure_response( $result );
		$response->set_status( $status_code );

		return $response;
	}

	/**
	 * Get individual sitemap.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error The response object.
	 */
	public function get_sitemap( WP_REST_Request $request ) {
		$date = $request->get_param( 'date' );

		$cache_key = 'msm_sitemap_rest_sitemap_' . $date;
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return rest_ensure_response( $cached );
		}

		$sitemap_data = $this->sitemap_service->get_sitemap_data( $date );

		if ( null === $sitemap_data ) {
			return new WP_Error(
				'rest_not_found',
				__( 'Sitemap not found for the specified date.', 'msm-sitemap' ),
				array( 'status' => 404 )
			);
		}

		set_transient( $cache_key, $sitemap_data, HOUR_IN_SECONDS );

		return rest_ensure_response( $sitemap_data );
	}
}
