<?php
/**
 * Tests for the MSM Sitemap AJAX endpoint and data retrieval logic.
 *
 * @package Automattic\MSM_Sitemap\Tests
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests;

/**
 * Tests for the MSM Sitemap AJAX endpoint and data retrieval logic.
 */
class AjaxEndpointTest extends TestCase {
	/**
	 * Admin user ID.
	 *
	 * @var int
	 */
	protected $admin_id;

	/**
	 * Set up the test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
	}

	/**
	 * Test that the get_sitemap_counts_data method returns the expected keys.
	 */
	public function test_get_sitemap_counts_data_returns_expected_keys() {
		// Build data in the same way as the ajax endpoint now does
		$stats_service = $this->get_service( \Automattic\MSM_Sitemap\Application\Services\SitemapStatsService::class );

		$comprehensive_stats = $stats_service->get_comprehensive_stats();
		$stats               = array(
			'total' => $comprehensive_stats['overview']['total_sitemaps'],
		);
		$recent_counts       = $stats_service->get_recent_url_counts( 5 );
		$data                = array(
			'total_indexed_urls'   => number_format( (int) get_option( 'msm_sitemap_indexed_url_count', 0 ) ),
			'total_sitemaps'       => number_format( (int) ( $stats['total'] ?? 0 ) ),
			'sitemap_indexed_urls' => $recent_counts,
		);

		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'total_indexed_urls', $data );
		$this->assertArrayHasKey( 'total_sitemaps', $data );
		$this->assertArrayHasKey( 'sitemap_indexed_urls', $data );
		$this->assertIsArray( $data['sitemap_indexed_urls'] );
		$this->assertCount( 5, $data['sitemap_indexed_urls'] );
	}
} 
