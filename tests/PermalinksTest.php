<?php
/**
 * Tests for Automattic\MSM_Sitemap\Permalinks.
 */

declare(strict_types=1);

namespace Automattic\MSM_Sitemap\Tests\Includes;

use Automattic\MSM_Sitemap\Infrastructure\WordPress\Permalinks;
use Automattic\MSM_Sitemap\Tests\TestCase;

/**
 * Tests for Permalinks class.
 */
class PermalinksTest extends TestCase {

	/**
	 * Get the site URL dynamically from WordPress.
	 *
	 * @return string
	 */
	private function get_site_url(): string {
		return home_url();
	}

	/**
	 * Helper to create a WP_Post-like object.
	 */
	private function make_post( string $post_type, string $post_name ): \WP_Post {
		$post_array = array(
			'ID'        => rand( 1, 10000 ),
			'post_type' => $post_type,
			'post_name' => $post_name,
		);
		return new \WP_Post( (object) $post_array );
	}

	/**
	 * Test daily sitemap permalinks (YYYY-MM-DD).
	 */
	public function test_daily_sitemap_permalink(): void {
		$post = $this->make_post( 'msm_sitemap', '2024-07-15' );
		// Simulate index by year = false
		add_filter( 'msm_sitemap_index_by_year', '__return_false' );
		$permalink = Permalinks::filter_post_type_link( 'http://irrelevant', $post );
		$this->assertSame( $this->get_site_url() . '/sitemap.xml?yyyy=2024&mm=07&dd=15', $permalink );
		remove_filter( 'msm_sitemap_index_by_year', '__return_false' );
	}

	/**
	 * Test year-based sitemap permalinks (YYYY) when index by year is true.
	 */
	public function test_year_sitemap_permalink(): void {
		$post = $this->make_post( 'msm_sitemap', '2023' );
		add_filter( 'msm_sitemap_index_by_year', '__return_true' );
		$permalink = Permalinks::filter_post_type_link( 'http://irrelevant', $post );
		$this->assertSame( $this->get_site_url() . '/sitemap-2023.xml', $permalink );
		remove_filter( 'msm_sitemap_index_by_year', '__return_true' );
	}

	/**
	 * Test fallback for invalid slugs.
	 */
	public function test_invalid_slug_fallback(): void {
		$post      = $this->make_post( 'msm_sitemap', 'not-a-date' );
		$permalink = Permalinks::filter_post_type_link( 'http://fallback', $post );
		$this->assertSame( 'http://fallback', $permalink );
	}

	/**
	 * Test non-msm_sitemap post types are not affected.
	 */
	public function test_non_sitemap_post_type(): void {
		$post      = $this->make_post( 'post', '2024-07-15' );
		$permalink = Permalinks::filter_post_type_link( 'http://original', $post );
		$this->assertSame( 'http://original', $permalink );
	}
} 
