<?php
/**
 * Robots.txt integration tests for Metro Sitemap
 *
 * @package Metro_Sitemap/unit_tests
 */

namespace Automattic\MSM_Sitemap\Tests;

use Metro_Sitemap;

class RobotsTest extends TestCase {

	public function setUp(): void {
		parent::setUp();
		// Default to public blog.
		update_option( 'blog_public', 1 );
	}

	public function test_public_blog_year_indexing_off_outputs_single_sitemap() {
		Metro_Sitemap::$index_by_year = false;
		$output                       = Metro_Sitemap::robots_txt( '', '1' );
		$this->assertStringContainsString( 'Sitemap: ', $output );
		$this->assertStringContainsString( '/sitemap.xml', $output );
		// Should not contain year-based sitemaps.
		$this->assertStringNotContainsString( 'sitemap-', $output );
	}

	public function test_public_blog_year_indexing_on_outputs_sitemap_per_year() {
		Metro_Sitemap::$index_by_year = true;
		// Add posts for 3 years.
		$this->add_a_post_for_each_of_the_last_x_years( 3 );
		$this->build_sitemaps();
		$output = Metro_Sitemap::robots_txt( '', '1' );
		// Should contain at least 3 year-based sitemap lines.
		preg_match_all( '/Sitemap: .*\/sitemap-\d{4}\.xml/', $output, $matches );
		$this->assertTrue( count( $matches[0] ) >= 3, 'Expected at least 3 year-based sitemap lines.' );
		// Should not contain /sitemap.xml (non-year).
		$this->assertStringNotContainsString( '/sitemap.xml' . PHP_EOL, $output );
	}

	public function test_private_blog_outputs_no_sitemap() {
		update_option( 'blog_public', 0 );
		Metro_Sitemap::$index_by_year = false;
		$output                       = Metro_Sitemap::robots_txt( '', '0' );
		$this->assertStringNotContainsString( 'Sitemap: ', $output );
	}

	public function test_custom_home_url_is_used_in_sitemap_lines() {
		Metro_Sitemap::$index_by_year = false;
		$custom_url                   = 'https://custom.example.com';
		add_filter(
			'home_url',
			function( $url ) use ( $custom_url ) {
				return $custom_url;
			} 
		);
		$output       = Metro_Sitemap::robots_txt( '', '1' );
		$expected_url = $custom_url; // Only the domain, not /sitemap.xml
		$this->assertStringContainsString( $expected_url, $output );
		remove_all_filters( 'home_url' );
	}
} 
