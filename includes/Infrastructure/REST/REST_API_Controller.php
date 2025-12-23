<?php
/**
 * REST API Controller for MSM Sitemap
 *
 * @package Automattic\MSM_Sitemap\Infrastructure\REST
 */

declare(strict_types=1);

namespace Automattic\MSM_Sitemap\Infrastructure\REST;

use Automattic\MSM_Sitemap\Application\Services\SitemapService;
use Automattic\MSM_Sitemap\Application\Services\SitemapStatsService;
use Automattic\MSM_Sitemap\Application\Services\SitemapValidationService;
use Automattic\MSM_Sitemap\Application\Services\SitemapExportService;
use Automattic\MSM_Sitemap\Application\Services\CronManagementService;
use Automattic\MSM_Sitemap\Application\Services\MissingSitemapDetectionService;
use Automattic\MSM_Sitemap\Application\Services\IncrementalGenerationService;

use Automattic\MSM_Sitemap\Application\Services\SitemapGenerator;
use Automattic\MSM_Sitemap\Application\DTOs\SitemapOperationResult;
use Automattic\MSM_Sitemap\Application\UseCases\GenerateSitemapUseCase;
use Automattic\MSM_Sitemap\Application\Commands\GenerateSitemapCommand;
use Automattic\MSM_Sitemap\Domain\Contracts\WordPressIntegrationInterface;
use Automattic\MSM_Sitemap\Infrastructure\Repositories\SitemapPostRepository;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * REST API Controller for MSM Sitemap endpoints.
 */
class REST_API_Controller implements WordPressIntegrationInterface {

	/**
	 * The sitemap service.
	 *
	 * @var SitemapService
	 */
	private SitemapService $sitemap_service;

	/**
	 * The stats service.
	 *
	 * @var SitemapStatsService
	 */
	private SitemapStatsService $stats_service;

	/**
	 * The validation service.
	 *
	 * @var SitemapValidationService
	 */
	private SitemapValidationService $validation_service;

	/**
	 * The export service.
	 *
	 * @var SitemapExportService
	 */
	private SitemapExportService $export_service;



	/**
	 * The cron management service.
	 *
	 * @var CronManagementService
	 */
	private CronManagementService $cron_management_service;

	/**
	 * The sitemap generator.
	 *
	 * @var SitemapGenerator
	 */
	private SitemapGenerator $sitemap_generator;

	/**
	 * The generate sitemap use case.
	 *
	 * @var GenerateSitemapUseCase
	 */
	private GenerateSitemapUseCase $generate_use_case;

	/**
	 * The missing sitemap detection service.
	 *
	 * @var MissingSitemapDetectionService
	 */
	private MissingSitemapDetectionService $missing_detection_service;

	/**
	 * The incremental generation service.
	 *
	 * @var IncrementalGenerationService
	 */
	private IncrementalGenerationService $incremental_generation_service;

	/**
	 * Constructor.
	 *
	 * @param SitemapService                 $sitemap_service                 The sitemap service.
	 * @param SitemapStatsService            $stats_service                   The stats service.
	 * @param SitemapValidationService       $validation_service              The validation service.
	 * @param SitemapExportService           $export_service                  The export service.
	 * @param CronManagementService          $cron_management_service         The cron management service.
	 * @param SitemapGenerator               $sitemap_generator               The sitemap generator.
	 * @param GenerateSitemapUseCase         $generate_use_case               The generate sitemap use case.
	 * @param MissingSitemapDetectionService $missing_detection_service       The missing sitemap detection service.
	 * @param IncrementalGenerationService   $incremental_generation_service  The incremental generation service.
	 */
	public function __construct(
		SitemapService $sitemap_service,
		SitemapStatsService $stats_service,
		SitemapValidationService $validation_service,
		SitemapExportService $export_service,
		CronManagementService $cron_management_service,
		SitemapGenerator $sitemap_generator,
		GenerateSitemapUseCase $generate_use_case,
		MissingSitemapDetectionService $missing_detection_service,
		IncrementalGenerationService $incremental_generation_service
	) {
		$this->sitemap_service                = $sitemap_service;
		$this->stats_service                  = $stats_service;
		$this->validation_service             = $validation_service;
		$this->export_service                 = $export_service;
		$this->cron_management_service        = $cron_management_service;
		$this->sitemap_generator              = $sitemap_generator;
		$this->generate_use_case              = $generate_use_case;
		$this->missing_detection_service      = $missing_detection_service;
		$this->incremental_generation_service = $incremental_generation_service;
	}

	/**
	 * Register WordPress hooks and filters for REST API functionality.
	 */
	public function register_hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register all REST API routes.
	 */
	public function register_routes(): void {
		// Sitemap management endpoints
		register_rest_route(
			'msm-sitemap/v1',
			'/sitemaps',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_sitemaps' ),
					'permission_callback' => array( $this, 'check_manage_options_permission' ),
					'args'                => $this->get_sitemaps_collection_params(),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_sitemap' ),
					'permission_callback' => array( $this, 'check_manage_options_permission' ),
					'args'                => $this->get_create_sitemap_params(),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete_sitemaps' ),
					'permission_callback' => array( $this, 'check_manage_options_permission' ),
					'args'                => $this->get_delete_sitemaps_params(),
				),
			)
		);

		// Individual sitemap endpoint
		register_rest_route(
			'msm-sitemap/v1',
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

		// Stats endpoints
		register_rest_route(
			'msm-sitemap/v1',
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

		// Health check endpoint
		register_rest_route(
			'msm-sitemap/v1',
			'/health',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_health' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		// Validation endpoints
		register_rest_route(
			'msm-sitemap/v1',
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
			'msm-sitemap/v1',
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

		// Export endpoint
		register_rest_route(
			'msm-sitemap/v1',
			'/export',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'export_sitemaps' ),
					'permission_callback' => array( $this, 'check_manage_options_permission' ),
					'args'                => $this->get_export_params(),
				),
			)
		);

		// Cron management endpoints
		register_rest_route(
			'msm-sitemap/v1',
			'/cron/status',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_cron_status' ),
					'permission_callback' => array( $this, 'check_manage_options_permission' ),
				),
			)
		);

		register_rest_route(
			'msm-sitemap/v1',
			'/cron/enable',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'enable_cron' ),
					'permission_callback' => array( $this, 'check_manage_options_permission' ),
				),
			)
		);

		register_rest_route(
			'msm-sitemap/v1',
			'/cron/disable',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'disable_cron' ),
					'permission_callback' => array( $this, 'check_manage_options_permission' ),
				),
			)
		);

		register_rest_route(
			'msm-sitemap/v1',
			'/cron/reset',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'reset_cron' ),
					'permission_callback' => array( $this, 'check_manage_options_permission' ),
				),
			)
		);

		// Missing sitemap detection endpoint
		register_rest_route(
			'msm-sitemap/v1',
			'/missing',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_missing_sitemaps' ),
					'permission_callback' => array( $this, 'check_manage_options_permission' ),
				),
			)
		);

		// Missing sitemap generation endpoint
		register_rest_route(
			'msm-sitemap/v1',
			'/generate-missing',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'generate_missing_sitemaps' ),
					'permission_callback' => array( $this, 'check_manage_options_permission' ),
					'args'                => array(
						'background' => array(
							'default'           => false,
							'sanitize_callback' => 'rest_sanitize_boolean',
							'description'       => 'Whether to schedule background generation (true) or run directly (false)',
						),
					),
				),
			)
		);

		// Background generation progress endpoint
		register_rest_route(
			'msm-sitemap/v1',
			'/background-progress',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_background_generation_progress' ),
					'permission_callback' => array( $this, 'check_manage_options_permission' ),
				),
			)
		);

		// URL recount endpoint
		register_rest_route(
			'msm-sitemap/v1',
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

		// Recent URL counts endpoint
		register_rest_route(
			'msm-sitemap/v1',
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

		// Full sitemap generation endpoint
		register_rest_route(
			'msm-sitemap/v1',
			'/generate-full',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'generate_full_sitemaps' ),
					'permission_callback' => array( $this, 'check_manage_options_permission' ),
				),
			)
		);

		// Halt generation endpoint
		register_rest_route(
			'msm-sitemap/v1',
			'/halt-generation',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'halt_generation' ),
					'permission_callback' => array( $this, 'check_manage_options_permission' ),
				),
			)
		);

		// Cron frequency endpoint
		register_rest_route(
			'msm-sitemap/v1',
			'/cron/frequency',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'update_cron_frequency' ),
					'permission_callback' => array( $this, 'check_manage_options_permission' ),
					'args'                => $this->get_cron_frequency_params(),
				),
			)
		);

		// Reset all data endpoint
		register_rest_route(
			'msm-sitemap/v1',
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
	 * Check if user has manage_options capability.
	 *
	 * @return bool True if user has permission, false otherwise.
	 */
	public function check_manage_options_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Validate date format.
	 *
	 * @param string $date The date to validate.
	 * @return bool True if valid, false otherwise.
	 */
	public function validate_date_format( string $date ): bool {
		// Allow empty strings for optional parameters
		if ( '' === $date ) {
			return true;
		}
		return (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date );
	}

	/**
	 * Get sitemaps collection parameters.
	 *
	 * @return array Parameters for sitemaps collection.
	 */
	protected function get_sitemaps_collection_params(): array {
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
	 * Get create sitemap parameters.
	 *
	 * @return array Parameters for creating sitemaps.
	 */
	protected function get_create_sitemap_params(): array {
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
	 * Get delete sitemaps parameters.
	 *
	 * @return array Parameters for deleting sitemaps.
	 */
	protected function get_delete_sitemaps_params(): array {
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
	 * Get stats parameters.
	 *
	 * @return array Parameters for stats endpoint.
	 */
	protected function get_stats_params(): array {
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
	 * Get validation parameters.
	 *
	 * @return array Parameters for validation endpoint.
	 */
	protected function get_validate_params(): array {
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
	 * Get export parameters.
	 *
	 * @return array Parameters for export endpoint.
	 */
	protected function get_export_params(): array {
		return array(
			'format'    => array(
				'default'           => 'json',
				'sanitize_callback' => 'sanitize_text_field',
				'enum'              => array( 'json', 'csv', 'xml' ),
			),
			'date_from' => array(
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => array( $this, 'validate_date_format' ),
			),
			'date_to'   => array(
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => array( $this, 'validate_date_format' ),
			),
		);
	}

	/**
	 * Get recount parameters.
	 *
	 * @return array Parameters for recount endpoint.
	 */
	protected function get_recount_params(): array {
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
	 * Get recent URLs parameters.
	 *
	 * @return array Parameters for recent URLs endpoint.
	 */
	protected function get_recent_urls_params(): array {
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
	 * Get cron frequency parameters.
	 *
	 * @return array Parameters for cron frequency endpoint.
	 */
	protected function get_cron_frequency_params(): array {
		return array(
			'frequency' => array(
				'type'        => 'string',
				'enum'        => CronManagementService::get_valid_frequencies(),
				'required'    => true,
				'description' => 'Cron frequency',
			),
		);
	}

	/**
	 * Validate date queries array.
	 *
	 * @param array $date_queries The date queries to validate.
	 * @return bool True if valid, false otherwise.
	 */
	public function validate_date_queries( array $date_queries ): bool {
		foreach ( $date_queries as $query ) {
			if ( ! is_array( $query ) ) {
				return false;
			}
			
			// Check for required keys
			if ( ! isset( $query['year'] ) ) {
				return false;
			}
			
			// Validate year
			if ( ! is_numeric( $query['year'] ) || $query['year'] < 1900 || $query['year'] > 2100 ) {
				return false;
			}
			
			// Validate month if present
			if ( isset( $query['month'] ) ) {
				if ( ! is_numeric( $query['month'] ) || $query['month'] < 1 || $query['month'] > 12 ) {
					return false;
				}
			}
			
			// Validate day if present
			if ( isset( $query['day'] ) ) {
				if ( ! is_numeric( $query['day'] ) || $query['day'] < 1 || $query['day'] > 31 ) {
					return false;
				}
			}
		}
		
		return true;
	}

	/**
	 * Get sitemaps collection.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error The response object.
	 */
	public function get_sitemaps( WP_REST_Request $request ) {
		// Check cache first
		$cache_key = 'msm_sitemap_rest_sitemaps_' . md5( serialize( $request->get_params() ) );
		$cached    = get_transient( $cache_key );
		
		if ( false !== $cached ) {
			return rest_ensure_response( $cached );
		}

		// Build date queries from parameters
		$date_queries = array();
		
		if ( $request->get_param( 'year' ) ) {
			$query = array( 'year' => $request->get_param( 'year' ) );
			if ( $request->get_param( 'month' ) ) {
				$query['month'] = $request->get_param( 'month' );
			}
			$date_queries[] = $query;
		}

		// Get sitemap data
		$sitemap_data = $this->sitemap_service->get_sitemap_list_data( $date_queries );
		
		// Apply pagination
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

		// Cache for 5 minutes
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

		// Use the new use case for all sitemap generation
		$command = new GenerateSitemapCommand( $date, $date_queries ? $date_queries : array(), $force, $all );
		$result  = $this->generate_use_case->execute( $command );

		// Convert SitemapOperationResult to array for JSON serialization
		$response_data = array(
			'success' => $result->is_success(),
			'count'   => $result->get_count(),
			'message' => $result->get_message(),
		);

		// Determine appropriate HTTP status code
		$status_code = 200; // Default
		
		if ( $result->is_success() ) {
			if ( $result->get_count() > 0 ) {
				$status_code = 201; // Created - resource was successfully created
			} else {
				$status_code = 200; // OK - operation succeeded but no new resources created
			}
		} elseif ( 'sitemap_exists' === $result->get_error_code() ) {
			$status_code = 409; // Conflict - resource already exists
		} elseif ( 'no_content' === $result->get_error_code() ) {
			$status_code = 422; // Unprocessable Entity - valid request but cannot be processed
		} else {
			$status_code = 400; // Bad Request - general error
		}

		if ( ! $result->is_success() ) {
			$response_data['error_code'] = $result->get_error_code();
		}

		// Clear relevant caches
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
			
			// Convert SitemapOperationResult to array for JSON serialization
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

		// Determine appropriate HTTP status code
		$status_code = 200; // Default
		
		if ( isset( $result['success'] ) && $result['success'] ) {
			if ( isset( $result['deleted_count'] ) && $result['deleted_count'] > 0 ) {
				$status_code = 204; // No Content - resource was successfully deleted
			} else {
				$status_code = 200; // OK - operation succeeded but no resources were deleted
			}
		} else {
			$status_code = 400; // Bad Request - general error
		}

		// Clear relevant caches
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
		
		// Check cache first
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

		// Cache for 1 hour
		set_transient( $cache_key, $sitemap_data, HOUR_IN_SECONDS );

		return rest_ensure_response( $sitemap_data );
	}

	/**
	 * Get statistics.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error The response object.
	 */
	public function get_stats( WP_REST_Request $request ) {
		// Check cache first
		$cache_key = 'msm_sitemap_rest_stats_' . md5( serialize( $request->get_params() ) );
		$cached    = get_transient( $cache_key );
		
		if ( false !== $cached ) {
			return rest_ensure_response( $cached );
		}

		$date_range = $request->get_param( 'date_range' );
		$start_date = $request->get_param( 'start_date' );
		$end_date   = $request->get_param( 'end_date' );

		$stats = $this->stats_service->get_comprehensive_stats( $date_range, $start_date, $end_date );

		// Cache for 15 minutes
		set_transient( $cache_key, $stats, 15 * MINUTE_IN_SECONDS );

		return rest_ensure_response( $stats );
	}

	/**
	 * Get health status.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response The response object.
	 */
	public function get_health( WP_REST_Request $request ) {
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

	/**
	 * Clear sitemap-related caches.
	 */
	private function clear_sitemap_caches(): void {
		// Clear sitemap list caches
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_msm_sitemap_rest_sitemaps_%'" );
		
		// Clear individual sitemap caches
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_msm_sitemap_rest_sitemap_%'" );
		
		// Clear stats caches
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_msm_sitemap_rest_stats_%'" );
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

		// Build date queries if specific date provided
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

		// Get sitemap data for the specific date
		$sitemap_data = $this->sitemap_service->get_sitemap_data( $date );

		if ( null === $sitemap_data ) {
			return new WP_Error(
				'rest_not_found',
				__( 'Sitemap not found for the specified date.', 'msm-sitemap' ),
				array( 'status' => 404 )
			);
		}

		// Validate the sitemap XML content
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

	/**
	 * Export sitemap data.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error The response object.
	 */
	public function export_sitemaps( WP_REST_Request $request ) {
		$format    = $request->get_param( 'format' );
		$date_from = $request->get_param( 'date_from' );
		$date_to   = $request->get_param( 'date_to' );

		// Build date queries if date range provided
		$date_queries = null;
		if ( ! empty( $date_from ) && ! empty( $date_to ) ) {
			$date_queries = array(
				array(
					'year'  => (int) substr( $date_from, 0, 4 ),
					'month' => (int) substr( $date_from, 5, 2 ),
					'day'   => (int) substr( $date_from, 8, 2 ),
				),
				array(
					'year'  => (int) substr( $date_to, 0, 4 ),
					'month' => (int) substr( $date_to, 5, 2 ),
					'day'   => (int) substr( $date_to, 8, 2 ),
				),
			);
		}

		// For REST API, we'll return the sitemap data directly rather than writing to files
		$sitemaps = $this->export_service->get_sitemaps_for_export( $date_queries );

		if ( empty( $sitemaps ) ) {
			return new WP_Error(
				'rest_not_found',
				__( 'No sitemaps found to export.', 'msm-sitemap' ),
				array( 'status' => 404 )
			);
		}

		// Format the response based on the requested format
		switch ( $format ) {
			case 'json':
				$response_data = $sitemaps;
				$content_type  = 'application/json';
				break;
			case 'xml':
				// Combine all sitemaps into a single XML response
				$combined_xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
				$combined_xml .= '<sitemaps>' . "\n";
				foreach ( $sitemaps as $sitemap ) {
					$combined_xml .= '<sitemap>' . "\n";
					$combined_xml .= '<date>' . esc_html( $sitemap['date'] ) . '</date>' . "\n";
					$combined_xml .= '<filename>' . esc_html( $sitemap['filename'] ) . '</filename>' . "\n";
					$combined_xml .= '<content><![CDATA[' . $sitemap['xml_content'] . ']]></content>' . "\n";
					$combined_xml .= '</sitemap>' . "\n";
				}
				$combined_xml .= '</sitemaps>';
				$response_data = $combined_xml;
				$content_type  = 'application/xml';
				break;
			case 'csv':
				// Convert to CSV format
				$csv_data   = array();
				$csv_data[] = array( 'Date', 'Filename', 'URL Count' );
				foreach ( $sitemaps as $sitemap ) {
					// Count URLs in XML content
					$url_count = 0;
					if ( ! empty( $sitemap['xml_content'] ) ) {
						$dom = new \DOMDocument();
						@$dom->loadXML( $sitemap['xml_content'] );
						$urls      = $dom->getElementsByTagName( 'url' );
						$url_count = $urls->length;
					}
					$csv_data[] = array( $sitemap['date'], $sitemap['filename'], $url_count );
				}
				$response_data = $this->array_to_csv( $csv_data );
				$content_type  = 'text/csv';
				break;
			default:
				return new WP_Error(
					'rest_bad_request',
					__( 'Invalid export format specified.', 'msm-sitemap' ),
					array( 'status' => 400 )
				);
		}

		$response = rest_ensure_response( $response_data );
		$response->header( 'Content-Type', $content_type );

		return $response;
	}

	/**
	 * Convert array to CSV string.
	 *
	 * @param array $data Array of arrays to convert to CSV.
	 * @return string CSV formatted string.
	 */
	private function array_to_csv( array $data ): string {
		$output = fopen( 'php://temp', 'r+' );
		foreach ( $data as $row ) {
			fputcsv( $output, $row, ',', '"', '\\' );
		}
		rewind( $output );
		$csv = stream_get_contents( $output );
		fclose( $output );
		return $csv;
	}

	/**
	 * Get cron status.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response The response object.
	 */
	public function get_cron_status( WP_REST_Request $request ) {
		$status_data = $this->cron_management_service->get_cron_status();
		return rest_ensure_response( $status_data );
	}

	/**
	 * Enable cron jobs.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response The response object.
	 */
	public function enable_cron( WP_REST_Request $request ) {
		$result = $this->cron_management_service->enable_cron();
		
		$status_code = $result['success'] ? 200 : 409;
		if ( isset( $result['error_code'] ) && 'blog_not_public' === $result['error_code'] ) {
			$status_code = 403;
		}

		$response = rest_ensure_response( $result );
		$response->set_status( $status_code );
		return $response;
	}

	/**
	 * Disable cron jobs.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response The response object.
	 */
	public function disable_cron( WP_REST_Request $request ) {
		$result = $this->cron_management_service->disable_cron();
		
		$status_code = $result['success'] ? 200 : 409;

		$response = rest_ensure_response( $result );
		$response->set_status( $status_code );
		return $response;
	}

	/**
	 * Reset cron state.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response The response object.
	 */
	public function reset_cron( WP_REST_Request $request ) {
		$result = $this->cron_management_service->reset_cron();

		$status_code = $result['success'] ? 200 : 400;

		$response = rest_ensure_response( $result );
		$response->set_status( $status_code );
		return $response;
	}

	/**
	 * Get missing sitemaps status.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response The response object.
	 */
	public function get_missing_sitemaps( WP_REST_Request $request ) {
		$missing_data = $this->missing_detection_service->get_missing_sitemaps();
		$summary      = $this->missing_detection_service->get_missing_content_summary();

		// Determine button text based on cron status
		$cron_status = $this->cron_management_service->get_cron_status();
		$button_text = $cron_status['enabled']
			? __( 'Generate Missing Sitemaps', 'msm-sitemap' )
			: __( 'Generate Missing Sitemaps (Direct)', 'msm-sitemap' );

		$response_data = array(
			'missing_dates_count'     => $missing_data['missing_dates_count'],
			'recently_modified_count' => $missing_data['recently_modified_count'],
			'summary'                 => $summary,
			'button_text'             => $button_text,
		);

		return rest_ensure_response( $response_data );
	}

	/**
	 * Generate missing sitemaps.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response The response object.
	 */
	public function generate_missing_sitemaps( WP_REST_Request $request ) {
		$background = $request->get_param( 'background' );

		if ( $background ) {
			$result = $this->incremental_generation_service->schedule();
		} else {
			$result = $this->incremental_generation_service->generate();
		}

		$response_data = array(
			'success' => $result['success'],
			'message' => $result['message'] ?? '',
			'method'  => $result['method'] ?? '',
		);

		if ( isset( $result['generated_count'] ) ) {
			$response_data['generated_count'] = $result['generated_count'];
		}

		if ( isset( $result['scheduled_count'] ) ) {
			$response_data['scheduled_count'] = $result['scheduled_count'];
		}

		$status_code = $result['success'] ? 200 : 400;

		$response = rest_ensure_response( $response_data );
		$response->set_status( $status_code );
		return $response;
	}

	/**
	 * Get background generation progress.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response The response object.
	 */
	public function get_background_generation_progress( WP_REST_Request $request ) {
		$progress = $this->incremental_generation_service->get_progress();

		return rest_ensure_response( $progress );
	}

	/**
	 * Recount URLs for sitemaps.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error The response object.
	 */
	public function recount_urls( WP_REST_Request $request ) {
		$full_recount = $request->get_param( 'full' ) ?? false;

		$result = $this->sitemap_service->recount_urls( $full_recount );

		// Convert SitemapRecountResult to array for JSON serialization
		$response_data = array(
			'success' => $result->is_success(),
			'count'   => $result->get_count(),
			'message' => $result->get_message(),
		);

		if ( ! $result->is_success() ) {
			$response_data['error_code']     = $result->get_error_code();
			$response_data['recount_errors'] = $result->get_recount_errors();
		}

		// Clear relevant caches
		$this->clear_sitemap_caches();

		$response = rest_ensure_response( $response_data );
		$response->set_status( $result->is_success() ? 200 : 400 );
		
		return $response;
	}

	/**
	 * Get recent URL counts.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error The response object.
	 */
	public function get_recent_urls( WP_REST_Request $request ) {
		$days = $request->get_param( 'days' ) ?? 7;

		$recent_counts = $this->stats_service->get_recent_url_counts( $days );

		return rest_ensure_response( $recent_counts );
	}



	/**
	 * Update cron frequency.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response The response object.
	 */
	public function update_cron_frequency( WP_REST_Request $request ) {
		$frequency = $request->get_param( 'frequency' );
		$result    = $this->cron_management_service->update_frequency( $frequency );
		
		$status_code = $result['success'] ? 200 : 400;

		$response = rest_ensure_response( $result );
		$response->set_status( $status_code );
		return $response;
	}

	/**
	 * Reset all data.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response The response object.
	 */
	public function reset_all_data( WP_REST_Request $request ) {
		// Create service to handle the reset
		$repository = new SitemapPostRepository();
		$service    = new SitemapService( $this->sitemap_generator, $repository );
		
		$service->reset_all_data();

		$response_data = array(
			'success' => true,
			'message' => __( 'Sitemap data reset. All sitemap posts, metadata, and processing options have been cleared.', 'msm-sitemap' ),
		);

		return rest_ensure_response( $response_data );
	}
}
