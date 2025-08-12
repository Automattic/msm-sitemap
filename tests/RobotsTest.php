<?php
/**
 * Robots.txt integration tests for MSM Sitemap
 *
 * @package Automattic\MSM_Sitemap\Tests
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests;

use Automattic\MSM_Sitemap\Infrastructure\WordPress\CoreIntegration;

/**
 * Robots.txt integration tests for MSM Sitemap.
 */
class RobotsTest extends TestCase {

	/**
	 * Set up the test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		// Default to public blog.
		update_option( 'blog_public', 1 );
	}

	/**
	 * Test that a public blog with year indexing off outputs a single sitemap.
	 */
	public function test_public_blog_year_indexing_off_outputs_single_sitemap() {
		remove_filter( 'msm_sitemap_index_by_year', '__return_true' );
		add_filter( 'msm_sitemap_index_by_year', '__return_false' );
		$output = CoreIntegration::robots_txt( '', '1' );
		$this->assertStringContainsString( 'Sitemap: ', $output );
		$this->assertStringContainsString( '/sitemap.xml', $output );
		// Should not contain year-based sitemaps.
		$this->assertStringNotContainsString( 'sitemap-', $output );
	}

	/**
	 * Test that a public blog with year indexing on outputs a sitemap per year.
	 */
	public function test_public_blog_year_indexing_on_outputs_sitemap_per_year() {
		remove_filter( 'msm_sitemap_index_by_year', '__return_false' );
		add_filter( 'msm_sitemap_index_by_year', '__return_true' );
		// Add posts for 3 years.
		$this->add_a_post_for_each_of_the_last_x_years( 3 );
		$this->build_sitemaps();
		$output = CoreIntegration::robots_txt( '', '1' );
		// Should contain at least 3 year-based sitemap lines.
		preg_match_all( '/Sitemap: .*\/sitemap-\d{4}\.xml/', $output, $matches );
		$this->assertTrue( count( $matches[0] ) >= 3, 'Expected at least 3 year-based sitemap lines.' );
		// Should not contain /sitemap.xml (non-year).
		$this->assertStringNotContainsString( '/sitemap.xml' . PHP_EOL, $output );
	}

	/**
	 * Test that a private blog outputs no sitemap.
	 */
	public function test_private_blog_outputs_no_sitemap() {
		update_option( 'blog_public', 0 );
		remove_filter( 'msm_sitemap_index_by_year', '__return_true' );
		add_filter( 'msm_sitemap_index_by_year', '__return_false' );
		$output = CoreIntegration::robots_txt( '', '0' );
		$this->assertStringNotContainsString( 'Sitemap: ', $output );
	}

	/**
	 * Test that a custom home URL is used in sitemap lines.
	 */
	public function test_custom_home_url_is_used_in_sitemap_lines() {
		remove_filter( 'msm_sitemap_index_by_year', '__return_true' );
		add_filter( 'msm_sitemap_index_by_year', '__return_false' );
		$custom_url                   = 'https://custom.example.com';
		add_filter(
			'home_url',
			function () use ( $custom_url ) {
				return $custom_url;
			} 
		);
		$output       = CoreIntegration::robots_txt( '', '1' );
		$expected_url = $custom_url; // Only the domain, not /sitemap.xml
		$this->assertStringContainsString( $expected_url, $output );
		remove_all_filters( 'home_url' );
	}
} 
