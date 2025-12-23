<?php
/**
 * Tests for REST API Controllers
 *
 * @package Automattic\MSM_Sitemap\Tests\Infrastructure\REST
 */

declare(strict_types=1);

namespace Automattic\MSM_Sitemap\Tests\Infrastructure\REST;

use Automattic\MSM_Sitemap\Infrastructure\REST\Controllers\SitemapsController;
use Automattic\MSM_Sitemap\Infrastructure\REST\Controllers\StatsController;
use Automattic\MSM_Sitemap\Infrastructure\REST\Controllers\HealthController;
use Automattic\MSM_Sitemap\Infrastructure\REST\Controllers\ValidationController;
use Automattic\MSM_Sitemap\Infrastructure\REST\Controllers\GenerationController;
use Automattic\MSM_Sitemap\Infrastructure\REST\RESTSetup;
use Automattic\MSM_Sitemap\Application\Services\SitemapService;
use Automattic\MSM_Sitemap\Application\Services\SitemapStatsService;
use Automattic\MSM_Sitemap\Application\Services\SitemapValidationService;
use Automattic\MSM_Sitemap\Application\Services\MissingSitemapDetectionService;
use Automattic\MSM_Sitemap\Application\Services\IncrementalGenerationService;
use Automattic\MSM_Sitemap\Application\Services\CronManagementService;
use Automattic\MSM_Sitemap\Application\Services\SitemapGenerator;
use Automattic\MSM_Sitemap\Application\UseCases\GenerateSitemapUseCase;
use WP_REST_Request;
use WP_Test_REST_TestCase;

/**
 * Test class for REST API Controllers.
 */
class REST_API_ControllerTest extends WP_Test_REST_TestCase {

	/**
	 * Test that routes are registered correctly.
	 */
	public function test_register_routes(): void {
		// Trigger the rest_api_init action to properly register routes
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		do_action( 'rest_api_init' );

		// Verify that routes are registered by checking if they exist
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/msm-sitemap/v1/sitemaps', $routes );
		$this->assertArrayHasKey( '/msm-sitemap/v1/stats', $routes );
		$this->assertArrayHasKey( '/msm-sitemap/v1/health', $routes );
		$this->assertArrayHasKey( '/msm-sitemap/v1/missing', $routes );
		$this->assertArrayHasKey( '/msm-sitemap/v1/generate-missing', $routes );
	}

	/**
	 * Test permission callback for manage_options.
	 */
	public function test_check_manage_options_permission(): void {
		$controller = new HealthController();

		// Test without admin user
		$this->assertFalse( $controller->check_manage_options_permission() );

		// Create admin user and test
		$admin_user = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_user );

		$this->assertTrue( $controller->check_manage_options_permission() );
	}

	/**
	 * Test date format validation.
	 */
	public function test_validate_date_format(): void {
		$controller = new HealthController();

		// Valid dates
		$this->assertTrue( $controller->validate_date_format( '2024-01-15' ) );
		$this->assertTrue( $controller->validate_date_format( '2024-12-31' ) );

		// Invalid dates
		$this->assertFalse( $controller->validate_date_format( '2024-1-15' ) );
		$this->assertFalse( $controller->validate_date_format( '2024-01-5' ) );
		$this->assertFalse( $controller->validate_date_format( '2024/01/15' ) );
		$this->assertFalse( $controller->validate_date_format( 'invalid' ) );
	}

	/**
	 * Test date queries validation.
	 */
	public function test_validate_date_queries(): void {
		$controller = new HealthController();

		// Valid date queries
		$valid_queries = array(
			array(
				'year' => 2024,
			),
			array(
				'year'  => 2024,
				'month' => 1,
			),
			array(
				'year'  => 2024,
				'month' => 12,
				'day'   => 31,
			),
		);

		foreach ( $valid_queries as $query ) {
			$this->assertTrue( $controller->validate_date_queries( array( $query ) ) );
		}

		// Invalid date queries
		$invalid_queries = array(
			array(
				'month' => 1,
			), // Missing year
			array(
				'year' => 1800,
			), // Year too low
			array(
				'year' => 2200,
			), // Year too high
			array(
				'year'  => 2024,
				'month' => 0,
			), // Month too low
			array(
				'year'  => 2024,
				'month' => 13,
			), // Month too high
			array(
				'year' => 2024,
				'day'  => 0,
			), // Day too low
			array(
				'year' => 2024,
				'day'  => 32,
			), // Day too high
		);

		foreach ( $invalid_queries as $query ) {
			$this->assertFalse( $controller->validate_date_queries( array( $query ) ) );
		}
	}

	/**
	 * Test health endpoint.
	 */
	public function test_get_health(): void {
		$controller = new HealthController();
		$request    = new WP_REST_Request( 'GET', '/msm-sitemap/v1/health' );
		$response   = $controller->get_health( $request );

		$this->assertNotWPError( $response );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'status', $data );
		$this->assertArrayHasKey( 'timestamp', $data );
		$this->assertArrayHasKey( 'version', $data );
		$this->assertArrayHasKey( 'features', $data );
		$this->assertEquals( 'healthy', $data['status'] );
		$this->assertEquals( '1.5.2', $data['version'] );
	}

	/**
	 * Test individual sitemap endpoint with valid date.
	 */
	public function test_get_sitemap_with_valid_date(): void {
		$request = new WP_REST_Request( 'GET', '/msm-sitemap/v1/sitemaps/2024-01-15' );
		$request->set_param( 'date', '2024-01-15' );

		// Mock the sitemap service to return data
		$sitemap_service = $this->createMock( SitemapService::class );
		$sitemap_service->method( 'get_sitemap_data' )
			->with( '2024-01-15' )
			->willReturn(
				array(
					'date'        => '2024-01-15',
					'url_count'   => 10,
					'xml_content' => '<xml>test</xml>',
				)
			);

		$controller = new SitemapsController(
			$sitemap_service,
			$this->createMock( GenerateSitemapUseCase::class )
		);

		$response = $controller->get_sitemap( $request );

		$this->assertNotWPError( $response );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( '2024-01-15', $data['date'] );
		$this->assertEquals( 10, $data['url_count'] );
	}

	/**
	 * Test individual sitemap endpoint with invalid date.
	 * Note: In a real REST API call, validation would happen before this method is called.
	 * This test shows what happens when an invalid date somehow reaches the method.
	 */
	public function test_get_sitemap_with_invalid_date(): void {
		$request = new WP_REST_Request( 'GET', '/msm-sitemap/v1/sitemaps/invalid-date' );
		$request->set_param( 'date', 'invalid-date' );

		$sitemap_service = $this->createMock( SitemapService::class );
		$sitemap_service->method( 'get_sitemap_data' )
			->willReturn( null );

		$controller = new SitemapsController(
			$sitemap_service,
			$this->createMock( GenerateSitemapUseCase::class )
		);

		$response = $controller->get_sitemap( $request );

		// Since the method doesn't validate the date format (that's done by REST API),
		// it will try to get sitemap data and return not found
		$this->assertWPError( $response );
		$this->assertEquals( 'rest_not_found', $response->get_error_code() );
	}

	/**
	 * Test individual sitemap endpoint with non-existent date.
	 */
	public function test_get_sitemap_with_nonexistent_date(): void {
		$request = new WP_REST_Request( 'GET', '/msm-sitemap/v1/sitemaps/2024-01-15' );
		$request->set_param( 'date', '2024-01-15' );

		// Mock the sitemap service to return null (not found)
		$sitemap_service = $this->createMock( SitemapService::class );
		$sitemap_service->method( 'get_sitemap_data' )
			->with( '2024-01-15' )
			->willReturn( null );

		$controller = new SitemapsController(
			$sitemap_service,
			$this->createMock( GenerateSitemapUseCase::class )
		);

		$response = $controller->get_sitemap( $request );

		$this->assertWPError( $response );
		$this->assertEquals( 'rest_not_found', $response->get_error_code() );
		$this->assertEquals( 404, $response->get_error_data()['status'] );
	}

	/**
	 * Test get missing sitemaps endpoint.
	 */
	public function test_get_missing_sitemaps(): void {
		$request = new WP_REST_Request( 'GET', '/msm-sitemap/v1/missing' );

		// Mock the detection service
		$missing_detection_service = $this->createMock( MissingSitemapDetectionService::class );
		$missing_detection_service->method( 'get_missing_sitemaps' )
			->willReturn(
				array(
					'missing_dates_count'     => 3,
					'recently_modified_count' => 1,
				)
			);
		$missing_detection_service->method( 'get_missing_content_summary' )
			->willReturn(
				array(
					'has_missing' => true,
					'message'     => '3 missing sitemaps detected.',
				)
			);

		// Mock the cron management service
		$cron_management_service = $this->createMock( CronManagementService::class );
		$cron_management_service->method( 'get_cron_status' )
			->willReturn( array( 'enabled' => true ) );

		$controller = new GenerationController(
			$cron_management_service,
			$missing_detection_service,
			$this->createMock( IncrementalGenerationService::class ),
			$this->createMock( SitemapGenerator::class )
		);

		$response = $controller->get_missing_sitemaps( $request );

		$this->assertNotWPError( $response );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'missing_dates_count', $data );
		$this->assertArrayHasKey( 'recently_modified_count', $data );
		$this->assertArrayHasKey( 'summary', $data );
		$this->assertArrayHasKey( 'button_text', $data );
		$this->assertEquals( 3, $data['missing_dates_count'] );
		$this->assertEquals( 1, $data['recently_modified_count'] );
		$this->assertTrue( $data['summary']['has_missing'] );
	}

	/**
	 * Test get missing sitemaps with no missing sitemaps.
	 */
	public function test_get_missing_sitemaps_none_missing(): void {
		$request = new WP_REST_Request( 'GET', '/msm-sitemap/v1/missing' );

		// Mock the detection service with no missing sitemaps
		$missing_detection_service = $this->createMock( MissingSitemapDetectionService::class );
		$missing_detection_service->method( 'get_missing_sitemaps' )
			->willReturn(
				array(
					'missing_dates_count'     => 0,
					'recently_modified_count' => 0,
				)
			);
		$missing_detection_service->method( 'get_missing_content_summary' )
			->willReturn(
				array(
					'has_missing' => false,
					'message'     => 'No missing sitemaps detected.',
				)
			);

		// Mock the cron management service
		$cron_management_service = $this->createMock( CronManagementService::class );
		$cron_management_service->method( 'get_cron_status' )
			->willReturn( array( 'enabled' => true ) );

		$controller = new GenerationController(
			$cron_management_service,
			$missing_detection_service,
			$this->createMock( IncrementalGenerationService::class ),
			$this->createMock( SitemapGenerator::class )
		);

		$response = $controller->get_missing_sitemaps( $request );

		$this->assertNotWPError( $response );
		$data = $response->get_data();
		$this->assertEquals( 0, $data['missing_dates_count'] );
		$this->assertFalse( $data['summary']['has_missing'] );
	}

	/**
	 * Test generate missing sitemaps endpoint success.
	 */
	public function test_generate_missing_sitemaps_success(): void {
		$request = new WP_REST_Request( 'POST', '/msm-sitemap/v1/generate-missing' );

		// Mock the generation service
		$incremental_generation_service = $this->createMock( IncrementalGenerationService::class );
		$incremental_generation_service->method( 'generate' )
			->willReturn(
				array(
					'success'         => true,
					'message'         => 'Scheduled generation of 3 missing sitemaps.',
					'method'          => 'cron',
					'generated_count' => 3,
				)
			);

		$controller = new GenerationController(
			$this->createMock( CronManagementService::class ),
			$this->createMock( MissingSitemapDetectionService::class ),
			$incremental_generation_service,
			$this->createMock( SitemapGenerator::class )
		);

		$response = $controller->generate_missing_sitemaps( $request );

		$this->assertNotWPError( $response );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertEquals( 'Scheduled generation of 3 missing sitemaps.', $data['message'] );
		$this->assertEquals( 'cron', $data['method'] );
		$this->assertEquals( 3, $data['generated_count'] );
	}

	/**
	 * Test generate missing sitemaps endpoint failure.
	 */
	public function test_generate_missing_sitemaps_failure(): void {
		$request = new WP_REST_Request( 'POST', '/msm-sitemap/v1/generate-missing' );

		// Mock the generation service with failure
		$incremental_generation_service = $this->createMock( IncrementalGenerationService::class );
		$incremental_generation_service->method( 'generate' )
			->willReturn(
				array(
					'success' => false,
					'message' => 'No missing sitemaps to generate.',
				)
			);

		$controller = new GenerationController(
			$this->createMock( CronManagementService::class ),
			$this->createMock( MissingSitemapDetectionService::class ),
			$incremental_generation_service,
			$this->createMock( SitemapGenerator::class )
		);

		$response = $controller->generate_missing_sitemaps( $request );

		$this->assertNotWPError( $response );
		$this->assertEquals( 400, $response->get_status() );

		$data = $response->get_data();
		$this->assertFalse( $data['success'] );
		$this->assertEquals( 'No missing sitemaps to generate.', $data['message'] );
	}
}
