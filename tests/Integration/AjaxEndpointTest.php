<?php
/**
 * Tests for the Metro Sitemap AJAX endpoint and data retrieval logic.
 *
 * @package Metro_Sitemap/unit_tests
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Integration;

use Metro_Sitemap;

/**
 * Tests for the Metro Sitemap AJAX endpoint and data retrieval logic.
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
		$data = Metro_Sitemap::get_sitemap_counts_data( 5 );
		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'total_indexed_urls', $data );
		$this->assertArrayHasKey( 'total_sitemaps', $data );
		$this->assertArrayHasKey( 'sitemap_indexed_urls', $data );
		$this->assertIsArray( $data['sitemap_indexed_urls'] );
		$this->assertCount( 5, $data['sitemap_indexed_urls'] );
	}
} 
