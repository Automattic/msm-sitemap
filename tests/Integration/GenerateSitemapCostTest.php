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
	 * Count the queries run while generating the sitemap for a date, from a cold cache.
	 *
	 * @param string $date Date in Y-m-d.
	 * @return int
	 */
	private function count_generation_queries( string $date ): int {
		global $wpdb;

		wp_cache_flush();
		$before = $wpdb->num_queries;
		Metro_Sitemap::generate_sitemap_for_date( $date );

		return $wpdb->num_queries - $before;
	}

	/**
	 * Query count does not grow with the number of posts in the sitemap.
	 */
	public function test_queries_do_not_grow_per_post(): void {
		$this->set_permalink_structure( '/%category%/%postname%/' );

		for ( $i = 0; $i < 2; $i++ ) {
			$this->create_dummy_post( '2023-04-01 10:00:00' );
		}
		for ( $i = 0; $i < 12; $i++ ) {
			$this->create_dummy_post( '2023-04-02 10:00:00' );
		}

		$small = $this->count_generation_queries( '2023-04-01' );
		$large = $this->count_generation_queries( '2023-04-02' );

		$this->assertLessThan( 5, $large - $small, 'Ten more posts should not mean ten more queries.' );
	}

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
