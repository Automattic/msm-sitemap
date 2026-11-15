<?php
/**
 * Tests for the database cost of generating a sitemap.
 *
 * @package Metro_Sitemap/unit_tests
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Integration;

use Metro_Sitemap;

/**
 * Tests for the database cost of generating a sitemap.
 */
class GenerateSitemapCostTest extends TestCase {

	/**
	 * A date with no posts does not create and then delete a sitemap post.
	 */
	public function test_empty_date_does_not_create_sitemap_post(): void {
		$inserted = 0;
		$this->add_test_filter(
			'wp_insert_post',
			static function ( $post_id, $post ) use ( &$inserted ) {
				if ( Metro_Sitemap::SITEMAP_CPT === $post->post_type ) {
					++$inserted;
				}
			},
			10,
			2
		);
		$deleted = did_action( 'msm_delete_sitemap_post' );

		Metro_Sitemap::generate_sitemap_for_date( '2023-04-03' );

		$this->assertSame( 0, $inserted );
		$this->assertSame( $deleted, did_action( 'msm_delete_sitemap_post' ) );
	}

	/**
	 * A date whose posts are all gone deletes its existing sitemap.
	 */
	public function test_empty_date_deletes_existing_sitemap(): void {
		$post_id = $this->create_dummy_post( '2023-04-04 10:00:00' );
		Metro_Sitemap::generate_sitemap_for_date( '2023-04-04' );
		$this->assertNotFalse( Metro_Sitemap::get_sitemap_post_id( 2023, 4, 4 ) );

		wp_delete_post( $post_id, true );
		Metro_Sitemap::generate_sitemap_for_date( '2023-04-04' );

		$this->assertFalse( Metro_Sitemap::get_sitemap_post_id( 2023, 4, 4 ) );
	}
}
