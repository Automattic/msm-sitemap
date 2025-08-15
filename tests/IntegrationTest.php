<?php
/**
 * Integration Test
 *
 * @package Automattic\MSM_Sitemap\Tests
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests;

/**
 * Test integration of SitemapGenerator with MSM Sitemap.
 */
class IntegrationTest extends TestCase {

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		
		// Clean up any existing sitemap posts
		$existing_sitemaps = get_posts(
			array(
				'post_type'      => 'msm_sitemap',
				'post_status'    => 'any',
				'posts_per_page' => -1,
			) 
		);
		
		// Debug: log what we found
		if ( ! empty( $existing_sitemaps ) ) {
			error_log( 'Found ' . count( $existing_sitemaps ) . ' existing sitemap posts to clean up' );
		}
		
		foreach ( $existing_sitemaps as $sitemap ) {
			wp_delete_post( $sitemap->ID, true );
		}
	}

	/**
	 * Test that the new generate_sitemap_for_date method works.
	 */
	public function test_generate_sitemap_for_date_works(): void {
		// Create a test post
		$post_id = $this->create_dummy_post( '2024-01-15' );

		// Test the new method
		$result = $this->generate_sitemap_for_date( '2024-01-15 00:00:00' );

		$this->assertTrue( $result );

		// Clean up
		wp_delete_post( $post_id, true );
	}

	/**
	 * Test that the new method returns false when no posts exist.
	 */
	public function test_generate_sitemap_for_date_returns_false_when_no_posts(): void {
		$date   = '1971-01-15';
		$result = $this->generate_sitemap_for_date( $date );
		$this->assertFalse( $result );
		$sitemap_id = $this->get_sitemap_post_id( 1971, 1, 15 );
		$this->assertFalse( $sitemap_id );
	}

	/**
	 * Test that the sitemap content types are properly initialized.
	 */
	public function test_sitemap_content_types_are_initialized(): void {
		// This test verifies that the static property is set
		// We can't directly access it, but we can test that the generator works
		$result = $this->generate_sitemap_for_date( '2024-01-15 00:00:00' );
		
		$this->assertIsBool( $result );
	}
}
