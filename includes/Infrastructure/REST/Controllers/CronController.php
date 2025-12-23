<?php
/**
 * Cron REST Controller
 *
 * @package Automattic\MSM_Sitemap\Infrastructure\REST\Controllers
 */

declare(strict_types=1);

namespace Automattic\MSM_Sitemap\Infrastructure\REST\Controllers;

use Automattic\MSM_Sitemap\Application\Services\CronManagementService;
use Automattic\MSM_Sitemap\Infrastructure\REST\Traits\RestControllerTrait;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST controller for cron management.
 */
class CronController {

	use RestControllerTrait;

	/**
	 * The cron management service.
	 *
	 * @var CronManagementService
	 */
	private CronManagementService $cron_management_service;

	/**
	 * Constructor.
	 *
	 * @param CronManagementService $cron_management_service The cron management service.
	 */
	public function __construct( CronManagementService $cron_management_service ) {
		$this->cron_management_service = $cron_management_service;
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
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
			$this->namespace,
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
			$this->namespace,
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
			$this->namespace,
			'/cron/reset',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'reset_cron' ),
					'permission_callback' => array( $this, 'check_manage_options_permission' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/cron/frequency',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'update_cron_frequency' ),
					'permission_callback' => array( $this, 'check_manage_options_permission' ),
					'args'                => $this->get_frequency_params(),
				),
			)
		);
	}

	/**
	 * Get frequency parameters.
	 *
	 * @return array Parameters for frequency endpoint.
	 */
	private function get_frequency_params(): array {
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
	 * Get cron status.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response The response object.
	 */
	public function get_cron_status( WP_REST_Request $request ): WP_REST_Response {
		$status_data = $this->cron_management_service->get_cron_status();
		return rest_ensure_response( $status_data );
	}

	/**
	 * Enable cron jobs.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response The response object.
	 */
	public function enable_cron( WP_REST_Request $request ): WP_REST_Response {
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
	public function disable_cron( WP_REST_Request $request ): WP_REST_Response {
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
	public function reset_cron( WP_REST_Request $request ): WP_REST_Response {
		$result = $this->cron_management_service->reset_cron();

		$status_code = $result['success'] ? 200 : 400;

		$response = rest_ensure_response( $result );
		$response->set_status( $status_code );
		return $response;
	}

	/**
	 * Update cron frequency.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response The response object.
	 */
	public function update_cron_frequency( WP_REST_Request $request ): WP_REST_Response {
		$frequency = $request->get_param( 'frequency' );
		$result    = $this->cron_management_service->update_frequency( $frequency );

		$status_code = $result['success'] ? 200 : 400;

		$response = rest_ensure_response( $result );
		$response->set_status( $status_code );
		return $response;
	}
}
