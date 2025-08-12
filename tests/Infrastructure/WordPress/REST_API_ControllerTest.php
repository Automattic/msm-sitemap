<?php
/**
 * Tests for REST API Controller
 *
 * @package Automattic\MSM_Sitemap\Tests\Infrastructure\WordPress
 */

declare(strict_types=1);

namespace Automattic\MSM_Sitemap\Tests\Infrastructure\WordPress;

use Automattic\MSM_Sitemap\Infrastructure\WordPress\REST_API_Controller;
use Automattic\MSM_Sitemap\Application\Services\SitemapService;
use Automattic\MSM_Sitemap\Application\Services\SitemapStatsService;
use Automattic\MSM_Sitemap\Application\Services\SitemapValidationService;
use Automattic\MSM_Sitemap\Application\Services\SitemapExportService;
use WP_REST_Request;
use WP_Test_REST_TestCase;

/**
 * Test class for REST API Controller.
 */
class REST_API_ControllerTest extends WP_Test_REST_TestCase {

	/**
	 * The REST API controller instance.
	 *
	 * @var REST_API_Controller
	 */
	private REST_API_Controller $controller;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();

		// Create mock services
		$sitemap_service = $this->createMock( SitemapService::class );
		$stats_service = $this->createMock( SitemapStatsService::class );
		$validation_service = $this->createMock( SitemapValidationService::class );
		$export_service = $this->createMock( SitemapExportService::class );

		$this->controller = new REST_API_Controller(
			$sitemap_service,
			$stats_service,
			$validation_service,
			$export_service
		);
	}

	/**
	 * Test that the controller registers routes correctly.
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
	}

	/**
	 * Test permission callback for manage_options.
	 */
	public function test_check_manage_options_permission(): void {
		// Test without admin user
		$this->assertFalse( $this->controller->check_manage_options_permission() );

		// Create admin user and test
		$admin_user = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_user );
		
		$this->assertTrue( $this->controller->check_manage_options_permission() );
	}

	/**
	 * Test date format validation.
	 */
	public function test_validate_date_format(): void {
		// Valid dates
		$this->assertTrue( $this->controller->validate_date_format( '2024-01-15' ) );
		$this->assertTrue( $this->controller->validate_date_format( '2024-12-31' ) );

		// Invalid dates
		$this->assertFalse( $this->controller->validate_date_format( '2024-1-15' ) );
		$this->assertFalse( $this->controller->validate_date_format( '2024-01-5' ) );
		$this->assertFalse( $this->controller->validate_date_format( '2024/01/15' ) );
		$this->assertFalse( $this->controller->validate_date_format( 'invalid' ) );
	}

	/**
	 * Test date queries validation.
	 */
	public function test_validate_date_queries(): void {
		// Valid date queries
		$valid_queries = array(
			array(
				'year' => 2024,
			),
			array(
				'year' => 2024,
				'month' => 1,
			),
			array(
				'year' => 2024,
				'month' => 12,
				'day' => 31,
			),
		);
		
		foreach ( $valid_queries as $query ) {
			$this->assertTrue( $this->controller->validate_date_queries( array( $query ) ) );
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
				'year' => 2024,
				'month' => 0,
			), // Month too low
			array(
				'year' => 2024,
				'month' => 13,
			), // Month too high
			array(
				'year' => 2024,
				'day' => 0,
			), // Day too low
			array(
				'year' => 2024,
				'day' => 32,
			), // Day too high
		);
		
		foreach ( $invalid_queries as $query ) {
			$this->assertFalse( $this->controller->validate_date_queries( array( $query ) ) );
		}
	}

	/**
	 * Test health endpoint.
	 */
	public function test_get_health(): void {
		$request = new WP_REST_Request( 'GET', '/msm-sitemap/v1/health' );
		$response = $this->controller->get_health( $request );

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
					'date' => '2024-01-15',
					'url_count' => 10,
					'xml_content' => '<xml>test</xml>',
				)
			);

		$controller = new REST_API_Controller(
			$sitemap_service,
			$this->createMock( SitemapStatsService::class ),
			$this->createMock( SitemapValidationService::class ),
			$this->createMock( SitemapExportService::class )
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

		$response = $this->controller->get_sitemap( $request );

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

		$controller = new REST_API_Controller(
			$sitemap_service,
			$this->createMock( SitemapStatsService::class ),
			$this->createMock( SitemapValidationService::class ),
			$this->createMock( SitemapExportService::class )
		);

		$response = $controller->get_sitemap( $request );

		$this->assertWPError( $response );
		$this->assertEquals( 'rest_not_found', $response->get_error_code() );
		$this->assertEquals( 404, $response->get_error_data()['status'] );
	}
}
