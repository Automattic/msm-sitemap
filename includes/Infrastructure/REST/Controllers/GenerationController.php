<?php
/**
 * Generation REST Controller
 *
 * @package Automattic\MSM_Sitemap\Infrastructure\REST\Controllers
 */

declare(strict_types=1);

namespace Automattic\MSM_Sitemap\Infrastructure\REST\Controllers;

use Automattic\MSM_Sitemap\Application\Services\CronManagementService;
use Automattic\MSM_Sitemap\Application\Services\MissingSitemapDetectionService;
use Automattic\MSM_Sitemap\Application\Services\IncrementalGenerationService;
use Automattic\MSM_Sitemap\Application\Services\SettingsService;
use Automattic\MSM_Sitemap\Application\Services\SitemapGenerator;
use Automattic\MSM_Sitemap\Infrastructure\REST\Traits\RestControllerTrait;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST controller for sitemap generation operations.
 */
class GenerationController {

	use RestControllerTrait;

	/**
	 * The cron management service.
	 *
	 * @var CronManagementService
	 */
	private CronManagementService $cron_management_service;

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
	 * The sitemap generator.
	 *
	 * @var SitemapGenerator
	 */
	private SitemapGenerator $sitemap_generator;

	/**
	 * The settings service.
	 *
	 * @var SettingsService
	 */
	private SettingsService $settings_service;

	/**
	 * Constructor.
	 *
	 * @param CronManagementService          $cron_management_service        The cron management service.
	 * @param MissingSitemapDetectionService $missing_detection_service      The missing sitemap detection service.
	 * @param IncrementalGenerationService   $incremental_generation_service The incremental generation service.
	 * @param SitemapGenerator               $sitemap_generator              The sitemap generator.
	 * @param SettingsService                $settings_service               The settings service.
	 */
	public function __construct(
		CronManagementService $cron_management_service,
		MissingSitemapDetectionService $missing_detection_service,
		IncrementalGenerationService $incremental_generation_service,
		SitemapGenerator $sitemap_generator,
		SettingsService $settings_service
	) {
		$this->cron_management_service        = $cron_management_service;
		$this->missing_detection_service      = $missing_detection_service;
		$this->incremental_generation_service = $incremental_generation_service;
		$this->sitemap_generator              = $sitemap_generator;
		$this->settings_service               = $settings_service;
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/missing',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_missing_sitemaps' ),
					'permission_callback' => array( $this, 'check_manage_options_permission' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
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

		register_rest_route(
			$this->namespace,
			'/background-progress',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_background_generation_progress' ),
					'permission_callback' => array( $this, 'check_manage_options_permission' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/generate-full',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'generate_full_sitemaps' ),
					'permission_callback' => array( $this, 'check_manage_options_permission' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/halt-generation',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'halt_generation' ),
					'permission_callback' => array( $this, 'check_manage_options_permission' ),
				),
			)
		);
	}

	/**
	 * Get missing sitemaps status.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response The response object.
	 */
	public function get_missing_sitemaps( WP_REST_Request $request ): WP_REST_Response {
		$missing_data     = $this->missing_detection_service->get_missing_sitemaps();
		$settings_changed = $this->settings_service->has_content_settings_changed();

		// If settings have changed, override the summary to show settings warning
		if ( $settings_changed ) {
			$summary = array(
				'has_missing'      => true,
				'settings_changed' => true,
				'message'          => __( 'Content settings changed', 'msm-sitemap' ),
				'counts'           => array(),
			);
		} else {
			$summary                     = $this->missing_detection_service->get_missing_content_summary();
			$summary['settings_changed'] = false;
		}

		$cron_status = $this->cron_management_service->get_cron_status();
		$button_text = $cron_status['enabled']
			? __( 'Generate Missing Sitemaps', 'msm-sitemap' )
			: __( 'Generate Missing Sitemaps (Direct)', 'msm-sitemap' );

		$response_data = array(
			'missing_dates_count'     => $missing_data['missing_dates_count'],
			'recently_modified_count' => $missing_data['recently_modified_count'],
			'summary'                 => $summary,
			'button_text'             => $button_text,
			'settings_changed'        => $settings_changed,
			'cron_enabled'            => $cron_status['enabled'],
		);

		return rest_ensure_response( $response_data );
	}

	/**
	 * Generate missing sitemaps.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response The response object.
	 */
	public function generate_missing_sitemaps( WP_REST_Request $request ): WP_REST_Response {
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
	public function get_background_generation_progress( WP_REST_Request $request ): WP_REST_Response {
		$progress = $this->incremental_generation_service->get_progress();

		// Include percentage complete in the response
		$response_data                     = $progress->toArray();
		$response_data['percent_complete'] = $progress->percentComplete();

		return rest_ensure_response( $response_data );
	}

	/**
	 * Generate full sitemaps.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response The response object.
	 */
	public function generate_full_sitemaps( WP_REST_Request $request ): WP_REST_Response {
		$result = $this->sitemap_generator->generate_all_sitemaps();

		$this->clear_sitemap_caches();

		return rest_ensure_response( $result );
	}

	/**
	 * Halt sitemap generation.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response The response object.
	 */
	public function halt_generation( WP_REST_Request $request ): WP_REST_Response {
		$this->sitemap_generator->halt_generation();

		$response_data = array(
			'success' => true,
			'message' => __( 'Sitemap generation halted.', 'msm-sitemap' ),
		);

		return rest_ensure_response( $response_data );
	}
}
