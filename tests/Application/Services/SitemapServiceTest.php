<?php
/**
 * SitemapService Test
 *
 * @package Automattic\MSM_Sitemap\Tests\Application\Services
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Application\Services;

use Automattic\MSM_Sitemap\Application\Services\SitemapService;
use Automattic\MSM_Sitemap\Application\Services\SitemapGenerator;
use Automattic\MSM_Sitemap\Infrastructure\Repositories\SitemapPostRepository;

/**
 * Test SitemapService (legacy wrapper service)
 */
class SitemapServiceTest extends \Automattic\MSM_Sitemap\Tests\TestCase {

	/**
	 * Test that halt checking works in generate_for_date_queries
	 */
	public function test_stops_generation_when_halt_signal_detected(): void {
		// Set up halt signal
		update_option( 'msm_sitemap_stop_generation', true );

		$generator  = new SitemapGenerator();
		$repository = new SitemapPostRepository();
		$service    = new SitemapService( $generator, $repository );

		$date_queries = array(
			array(
				'year'  => 2024,
				'month' => 1,
				'day'   => 1,
			),
		);

		$result = $service->generate_for_date_queries( $date_queries );

		$this->assertFalse( $result->is_success() );
		$this->assertEquals( 'stopped', $result->get_error_code() );
		$this->assertStringContainsString( 'Sitemap generation was stopped', $result->get_message() );

		// Clean up
		delete_option( 'msm_sitemap_stop_generation' );
	}

	/**
	 * Test that halt checking works in recount_urls_full
	 */
	public function test_stops_url_recount_when_halt_signal_detected(): void {
		// Create a test sitemap first
		$post_id = wp_insert_post(
			array(
				'post_title'  => '2024-01-01',
				'post_name'   => '2024-01-01',
				'post_type'   => 'msm_sitemap',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $post_id, 'msm_sitemap_xml', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>https://example.com/</loc></url></urlset>' );

		// Set up halt signal
		update_option( 'msm_sitemap_stop_generation', true );

		$generator  = new SitemapGenerator();
		$repository = new SitemapPostRepository();
		$service    = new SitemapService( $generator, $repository );

		$result = $service->recount_urls( true ); // Use full recount

		$this->assertFalse( $result->is_success() );
		$this->assertEquals( 'stopped', $result->get_error_code() );
		$this->assertStringContainsString( 'URL recount was stopped', $result->get_message() );

		// Clean up
		delete_option( 'msm_sitemap_stop_generation' );
		wp_delete_post( $post_id, true );
	}

	/**
	 * Test that halt checking works in validate_sitemaps
	 */
	public function test_stops_validation_when_halt_signal_detected(): void {
		// Create a test sitemap first
		$post_id = wp_insert_post(
			array(
				'post_title'  => '2024-01-01',
				'post_name'   => '2024-01-01',
				'post_type'   => 'msm_sitemap',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $post_id, 'msm_sitemap_xml', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>https://example.com/</loc></url></urlset>' );

		// Set up halt signal
		update_option( 'msm_stop_processing', true );

		$generator  = new SitemapGenerator();
		$repository = new SitemapPostRepository();
		$service    = new SitemapService( $generator, $repository );

		$validation_service = new \Automattic\MSM_Sitemap\Application\Services\SitemapValidationService( $repository );
		$result             = $validation_service->validate_sitemaps();

		$this->assertFalse( $result->is_success() );
		$this->assertEquals( 'stopped', $result->get_error_code() );
		$this->assertStringContainsString( 'Sitemap validation was stopped', $result->get_message() );

		// Clean up
		delete_option( 'msm_stop_processing' );
		wp_delete_post( $post_id, true );
	}

	/**
	 * Test that export_sitemaps works correctly
	 */
	public function test_exports_sitemaps_successfully(): void {
		// Create a test sitemap first
		$post_id = wp_insert_post(
			array(
				'post_title'  => '2024-01-01',
				'post_name'   => '2024-01-01',
				'post_type'   => 'msm_sitemap',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $post_id, 'msm_sitemap_xml', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>https://example.com/</loc></url></urlset>' );

		$generator  = new SitemapGenerator();
		$repository = new SitemapPostRepository();
		$service    = new SitemapService( $generator, $repository );

		// Create a temporary directory for testing
		$temp_dir = sys_get_temp_dir() . '/msm-sitemap-test-' . uniqid();

		$export_service = new \Automattic\MSM_Sitemap\Application\Services\SitemapExportService( $repository, new \Automattic\MSM_Sitemap\Application\Services\SitemapQueryService() );
		$result         = $export_service->export_sitemaps( $temp_dir );

		$this->assertTrue( $result['success'] );
		$this->assertEquals( 1, $result['count'] );
		$this->assertStringContainsString( 'Exported 1 sitemap', $result['message'] );
		$this->assertEmpty( $result['errors'] );
		$this->assertFileExists( $temp_dir . '/2024-01-01.xml' );

		// Clean up
		wp_delete_post( $post_id, true );
	}

	/**
	 * Test that export_sitemaps stops when halted
	 */
	public function test_stops_export_when_halt_signal_detected(): void {
		// Create a test sitemap first
		$post_id = wp_insert_post(
			array(
				'post_title'  => '2024-01-01',
				'post_name'   => '2024-01-01',
				'post_type'   => 'msm_sitemap',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $post_id, 'msm_sitemap_xml', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>https://example.com/</loc></url></urlset>' );

		// Set up halt signal
		update_option( 'msm_stop_processing', true );

		$generator  = new SitemapGenerator();
		$repository = new SitemapPostRepository();
		$service    = new SitemapService( $generator, $repository );

		$temp_dir = sys_get_temp_dir() . '/msm-sitemap-test-' . uniqid();

		$export_service = new \Automattic\MSM_Sitemap\Application\Services\SitemapExportService( $repository, new \Automattic\MSM_Sitemap\Application\Services\SitemapQueryService() );
		$result         = $export_service->export_sitemaps( $temp_dir );

		$this->assertFalse( $result['success'] );
		$this->assertEquals( 0, $result['count'] );
		$this->assertStringContainsString( 'stopped by user request', $result['message'] );

		// Clean up
		delete_option( 'msm_stop_processing' );
		wp_delete_post( $post_id, true );
	}

	/**
	 * Test that format_xml_for_export works correctly
	 */
	public function test_formats_xml_for_export_with_and_without_pretty_printing(): void {
		$generator  = new SitemapGenerator();
		$repository = new SitemapPostRepository();
		$service    = new SitemapService( $generator, $repository );

		$xml_content = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>https://example.com/</loc></url></urlset>';

		// Test without pretty printing
		$export_service = new \Automattic\MSM_Sitemap\Application\Services\SitemapExportService( $repository, new \Automattic\MSM_Sitemap\Application\Services\SitemapQueryService() );
		$result         = $export_service->format_xml_for_export( $xml_content, false );
		$this->assertEquals( $xml_content, $result );

		// Test with pretty printing
		$result = $export_service->format_xml_for_export( $xml_content, true );
		$this->assertStringContainsString( '<?xml version="1.0"?>', $result );
		$this->assertStringContainsString( '<urlset', $result );
		$this->assertStringContainsString( '</urlset>', $result );
	}

	/**
	 * Test that get_sitemaps_for_export works correctly
	 */
	public function test_retrieves_sitemaps_for_export(): void {
		// Create a test sitemap first
		$post_id = wp_insert_post(
			array(
				'post_title'  => '2024-01-01',
				'post_name'   => '2024-01-01',
				'post_type'   => 'msm_sitemap',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $post_id, 'msm_sitemap_xml', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>https://example.com/</loc></url></urlset>' );

		$generator  = new SitemapGenerator();
		$repository = new SitemapPostRepository();
		$service    = new SitemapService( $generator, $repository );

		$export_service = new \Automattic\MSM_Sitemap\Application\Services\SitemapExportService( $repository, new \Automattic\MSM_Sitemap\Application\Services\SitemapQueryService() );
		$sitemaps       = $export_service->get_sitemaps_for_export();

		$this->assertNotEmpty( $sitemaps );
		$this->assertCount( 1, $sitemaps );
		$this->assertEquals( '2024-01-01', $sitemaps[0]['filename'] );
		$this->assertStringContainsString( '<urlset', $sitemaps[0]['xml_content'] );

		// Clean up
		wp_delete_post( $post_id, true );
	}

	// ===== EDGE CASES AND BOUNDARY CONDITIONS =====

	/**
	 * Test that generate_for_date_queries handles empty array gracefully.
	 */
	public function test_handles_empty_date_queries_gracefully(): void {
		$generator  = new SitemapGenerator();
		$repository = new SitemapPostRepository();
		$service    = new SitemapService( $generator, $repository );

		$result = $service->generate_for_date_queries( array() );

		// Should handle empty array gracefully
		$this->assertIsObject( $result );
		$this->assertTrue( method_exists( $result, 'is_success' ) );
	}

	/**
	 * Test that generate_for_date_queries handles invalid date queries gracefully.
	 *
	 * @dataProvider invalid_date_queries_data_provider
	 */
	public function test_handles_invalid_date_queries_gracefully( array $invalid_query, bool $expects_exception ): void {
		$generator  = new SitemapGenerator();
		$repository = new SitemapPostRepository();
		$service    = new SitemapService( $generator, $repository );

		if ( $expects_exception ) {
			// Should throw exception for invalid dates (correct behavior)
			$this->expectException( \InvalidArgumentException::class );
			$result = $service->generate_for_date_queries( array( $invalid_query ) );
		} else {
			// Should handle gracefully without throwing exception
			$result = $service->generate_for_date_queries( array( $invalid_query ) );
			// Verify the service handles the invalid input gracefully
			$this->assertIsObject( $result );
		}
	}

	/**
	 * Data provider for invalid date queries.
	 */
	public function invalid_date_queries_data_provider(): iterable {
		yield 'invalid month' => array(
			'invalid_query'     => array(
				'year'  => 2024,
				'month' => 13, // Invalid month
			),
			'expects_exception' => false, // Now handled gracefully by skipping invalid months
		);
		yield 'invalid day' => array(
			'invalid_query'     => array(
				'year'  => 2024,
				'month' => 1,
				'day'   => 32, // Invalid day
			),
			'expects_exception' => false,
		);
		yield 'invalid day for February' => array(
			'invalid_query'     => array(
				'year'  => 2024,
				'month' => 2,
				'day'   => 30, // Invalid day for February
			),
			'expects_exception' => false,
		);
	}

	/**
	 * Test that recount_urls handles no sitemaps gracefully.
	 */
	public function test_handles_recount_with_no_sitemaps_gracefully(): void {
		$generator  = new SitemapGenerator();
		$repository = new SitemapPostRepository();
		$service    = new SitemapService( $generator, $repository );

		// Ensure no sitemaps exist
		$sitemaps = get_posts(
			array(
				'post_type'      => 'msm_sitemap',
				'posts_per_page' => -1,
			)
		);
		foreach ( $sitemaps as $sitemap ) {
			wp_delete_post( $sitemap->ID, true );
		}

		$result = $service->recount_urls( true );

		// Should handle gracefully
		$this->assertTrue( $result->is_success() );
		$this->assertEquals( 0, $result->get_count() );
	}

	/**
	 * Test that export_sitemaps handles invalid directory gracefully.
	 */
	public function test_handles_invalid_export_directory_gracefully(): void {
		$generator  = new SitemapGenerator();
		$repository = new SitemapPostRepository();
		$service    = new SitemapService( $generator, $repository );

		$invalid_dir = '';

		$export_service = new \Automattic\MSM_Sitemap\Application\Services\SitemapExportService( $repository, new \Automattic\MSM_Sitemap\Application\Services\SitemapQueryService() );
		$result         = $export_service->export_sitemaps( $invalid_dir );

		// Should handle gracefully
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'success', $result );
	}



	/**
	 * Test that format_xml_for_export handles invalid XML gracefully.
	 */
	public function test_handles_invalid_xml_for_export_gracefully(): void {
		$generator  = new SitemapGenerator();
		$repository = new SitemapPostRepository();
		$service    = new SitemapService( $generator, $repository );

		$invalid_xml_content = 'Invalid XML content <url><loc>unclosed';

		$export_service = new \Automattic\MSM_Sitemap\Application\Services\SitemapExportService( $repository, new \Automattic\MSM_Sitemap\Application\Services\SitemapQueryService() );
		$result         = $export_service->format_xml_for_export( $invalid_xml_content, true );

		// Should handle gracefully (may return original content or throw exception)
		$this->assertIsString( $result );
	}

	/**
	 * Test that get_sitemaps_for_export handles corrupted sitemap data gracefully.
	 */
	public function test_handles_corrupted_sitemap_data_gracefully(): void {
		// Create a test sitemap with corrupted data
		$post_id = wp_insert_post(
			array(
				'post_title'  => '2024-01-01',
				'post_name'   => '2024-01-01',
				'post_type'   => 'msm_sitemap',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $post_id, 'msm_sitemap_xml', 'Corrupted XML data' );

		$generator  = new SitemapGenerator();
		$repository = new SitemapPostRepository();
		$service    = new SitemapService( $generator, $repository );

		$export_service = new \Automattic\MSM_Sitemap\Application\Services\SitemapExportService( $repository, new \Automattic\MSM_Sitemap\Application\Services\SitemapQueryService() );
		$sitemaps       = $export_service->get_sitemaps_for_export();

		// Should handle gracefully
		$this->assertNotEmpty( $sitemaps );
		$this->assertCount( 1, $sitemaps );
		$this->assertEquals( 'Corrupted XML data', $sitemaps[0]['xml_content'] );

		// Clean up
		wp_delete_post( $post_id, true );
	}

	/**
	 * Test that service handles null repository gracefully.
	 */
	public function test_handles_null_repository_gracefully(): void {
		$generator  = new SitemapGenerator();
		$repository = null;

		// Should throw exception for null repository (correct behavior)
		$this->expectException( \TypeError::class );
		$service = new SitemapService( $generator, $repository );
	}

	/**
	 * Test that service handles null generator gracefully.
	 */
	public function test_handles_null_generator_gracefully(): void {
		$generator  = null;
		$repository = new SitemapPostRepository();

		// Should throw exception for null generator (correct behavior)
		$this->expectException( \TypeError::class );
		$service = new SitemapService( $generator, $repository );
	}

	/**
	 * Test that service handles extremely large date queries gracefully.
	 * @group slow
	 */
	public function test_handles_extremely_large_date_queries_gracefully(): void {
		$generator  = new SitemapGenerator();
		$repository = new SitemapPostRepository();
		$service    = new SitemapService( $generator, $repository );

		// Create a very large array of date queries
		$large_queries = array();
		for ( $i = 0; $i < 10000; $i++ ) {
			$large_queries[] = array(
				'year'  => 2024,
				'month' => 1,
				'day'   => 1,
			);
		}

		$result = $service->generate_for_date_queries( $large_queries );

		// Should handle gracefully (may timeout, return error, or process successfully)
		$this->assertIsObject( $result );
	}

	/**
	 * Test that service handles memory exhaustion gracefully.
	 * @group slow
	 */
	public function test_handles_memory_exhaustion_gracefully(): void {
		$generator  = new SitemapGenerator();
		$repository = new SitemapPostRepository();
		$service    = new SitemapService( $generator, $repository );

		// Create many sitemaps to potentially exhaust memory
		for ( $i = 0; $i < 1000; $i++ ) {
			$post_id = wp_insert_post(
				array(
					'post_title'  => "Sitemap {$i}",
					'post_name'   => "sitemap-{$i}",
					'post_type'   => 'msm_sitemap',
					'post_status' => 'publish',
				)
			);
			update_post_meta( $post_id, 'msm_sitemap_xml', str_repeat( '<url><loc>https://example.com/</loc></url>', 1000 ) );
		}

		$export_service = new \Automattic\MSM_Sitemap\Application\Services\SitemapExportService( $repository, new \Automattic\MSM_Sitemap\Application\Services\SitemapQueryService() );
		$sitemaps       = $export_service->get_sitemaps_for_export();

		// Should handle gracefully (may return subset, error, or process all)
		$this->assertIsArray( $sitemaps );

		// Clean up
		$sitemaps_to_clean = get_posts(
			array(
				'post_type'      => 'msm_sitemap',
				'posts_per_page' => -1,
			)
		);
		foreach ( $sitemaps_to_clean as $sitemap ) {
			wp_delete_post( $sitemap->ID, true );
		}
	}
}
