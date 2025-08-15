<?php
/**
 * Tests for sitemap index generation
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests;

use Automattic\MSM_Sitemap\Infrastructure\REST\SitemapEndpointHandler;
use Automattic\MSM_Sitemap\Infrastructure\Repositories\SitemapPostRepository;

/**
 * Test sitemap index generation.
 */
class SitemapIndexTest extends TestCase {

	/**
	 * Test that sitemap index XML generation works with existing sitemaps.
	 */
	public function test_sitemap_index_generation_with_existing_sitemaps(): void {
		// First create some posts for today
		$this->add_a_post_for_today();
		$this->add_a_post_for_today();
		
		// Verify posts were created
		$this->assertPostCount( 2, 'Should have created 2 posts' );

		// Use the existing test infrastructure to create posts and sitemap
		$this->build_sitemaps();

		// Get repository to verify sitemap exists
		$repository = new SitemapPostRepository();
		$dates      = $repository->get_all_sitemap_dates();
		
		$this->assertNotEmpty( $dates, 'Should have at least one sitemap date after build_sitemaps()' );

		$sitemap_endpoint_handler = $this->get_service( SitemapEndpointHandler::class );
		$index_xml                = $sitemap_endpoint_handler->get_sitemap_index_xml( false );

		$this->assertNotFalse( $index_xml, 'Index XML should be generated successfully' );
		$this->assertStringContainsString( '<sitemapindex', $index_xml, 'Should contain sitemapindex element' );
		$this->assertStringContainsString( '<sitemap>', $index_xml, 'Should contain at least one sitemap entry' );

		// Verify XSL stylesheet is included
		$this->assertStringContainsString( '<?xml-stylesheet', $index_xml, 'Should include XSL stylesheet reference' );
	}

	/**
	 * Test sitemap index generation with no sitemaps.
	 */
	public function test_sitemap_index_generation_with_no_sitemaps(): void {
		// Clean up any existing sitemaps
		$repository = new SitemapPostRepository();
		$repository->delete_all();

		$sitemap_endpoint_handler = $this->get_service( SitemapEndpointHandler::class );
		$index_xml                = $sitemap_endpoint_handler->get_sitemap_index_xml( false );

		$this->assertFalse( $index_xml, 'Index XML should return false when no sitemaps exist' );
	}

	/**
	 * Test sitemap index generation with year filtering.
	 */
	public function test_sitemap_index_generation_with_year_filter(): void {
		// Create posts for different years and dates
		$this->create_dummy_post( '2023-06-15' );
		$this->create_dummy_post( '2024-07-20' );
		
		// Build sitemaps from those posts
		$this->build_sitemaps();

		$sitemap_endpoint_handler = $this->get_service( SitemapEndpointHandler::class );

		// Test 2024 only
		$index_xml_2024 = $sitemap_endpoint_handler->get_sitemap_index_xml( 2024 );
		$this->assertNotFalse( $index_xml_2024, 'Should generate index for 2024' );
		$this->assertStringContainsString( '2024', $index_xml_2024, 'Should contain 2024 entries' );
		$this->assertStringNotContainsString( '2023', $index_xml_2024, 'Should not contain 2023 entries' );

		// Test 2023 only
		$index_xml_2023 = $sitemap_endpoint_handler->get_sitemap_index_xml( 2023 );
		$this->assertNotFalse( $index_xml_2023, 'Should generate index for 2023' );
		$this->assertStringContainsString( '2023', $index_xml_2023, 'Should contain 2023 entries' );
		$this->assertStringNotContainsString( '2024', $index_xml_2023, 'Should not contain 2024 entries' );
	}
}
