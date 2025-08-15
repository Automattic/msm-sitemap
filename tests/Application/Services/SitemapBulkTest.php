<?php
/**
 * Sitemap Bulk Operations Test
 *
 * @package Automattic\MSM_Sitemap\Tests\Application\Services
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Application\Services;

use WP_Post;

/**
 * Tests for bulk sitemap operations
 */
class SitemapBulkTest extends \Automattic\MSM_Sitemap\Tests\TestCase {

	/**
	 * Multi-day bulk creation and XML structure/content validation.
	 */
	public function test_creates_multiple_sitemaps_with_valid_xml_structure(): void {
		$this->add_a_post_for_each_of_the_last_x_days_before_today( 4 );
		$this->assertPostCount( 4 );
		$this->build_sitemaps();

		$this->assertSitemapCount( 4 );

		$sitemaps = get_posts(
			array(
				'post_type'      => 'msm_sitemap',
				'fields'         => 'ids',
				'posts_per_page' => -1,
			)
		);

		$created_posts_ids = get_posts(
			array(
				'post_type'      => 'post',
				'fields'         => 'ids',
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'DEC',
			)
		);

		foreach ( $sitemaps as $i => $map_id ) {
			$xml = get_post_meta( $map_id, 'msm_sitemap_xml', true );
			// Get the corresponding created post ID (assuming same order)
			$post_id = $created_posts_ids[ $i ];
			$this->assertStringContainsString( 'p=' . $post_id, $xml );

			$xml_struct = simplexml_load_string( $xml );
			$this->assertNotEmpty( $xml_struct->url );
			$this->assertNotEmpty( $xml_struct->url->loc );
			$this->assertNotEmpty( $xml_struct->url->lastmod );
			$this->assertNotEmpty( $xml_struct->url->changefreq );
			$this->assertNotEmpty( $xml_struct->url->priority );
			$this->assertStringContainsString( 'p=' . $post_id, (string) $xml_struct->url->loc );
		}
	}

	/**
	 * Validate that get_sitemap_post_id function returns the expected Sitemap.
	 */
	public function test_get_sitemap_post_id_returns_expected_sitemap(): void {
		$this->add_a_post_for_each_of_the_last_x_days_before_today( 4 );

		$this->assertPostCount( 4 );
		$this->build_sitemaps();

		$date          = strtotime( '-1 day' );
		$sitemap_year  = gmdate( 'Y', $date );
		$sitemap_month = gmdate( 'm', $date );
		$sitemap_day   = gmdate( 'd', $date );
		$sitemap_ymd   = sprintf( '%s-%s-%s', $sitemap_year, $sitemap_month, $sitemap_day );

		$sitemap_post_id = $this->get_sitemap_post_id( $sitemap_year, $sitemap_month, $sitemap_day );
		$sitemap_post    = get_post( $sitemap_post_id );

		$this->assertInstanceOf( WP_Post::class, $sitemap_post, 'get_sitemap_post_id returned non-WP_Post value' );
		$this->assertEquals( $sitemap_ymd, $sitemap_post->post_title );
	}
}
