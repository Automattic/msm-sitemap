<?php
/**
 * GlobalPostTest
 *
 * @package Metro_Sitemap/unit_tests
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Integration;

use Metro_Sitemap;

/**
 * Tests that the global post is set up while sitemap filters run, as it was up to 1.5.3.
 */
class GlobalPostTest extends TestCase {

	/**
	 * A msm_sitemap_skip_post callback can use get_post() to decide whether to skip.
	 */
	public function test_skip_post_callback_can_use_global_post(): void {
		$keep    = $this->create_dummy_post( '2023-05-05 10:00:00' );
		$sponsor = $this->create_dummy_post( '2023-05-05 11:00:00' );
		update_post_meta( $sponsor, 'sponsored', '1' );

		add_filter(
			'msm_sitemap_skip_post',
			static function ( $skip ) {
				$post = get_post();
				return $skip || ( $post && '1' === get_post_meta( $post->ID, 'sponsored', true ) );
			}
		);

		Metro_Sitemap::generate_sitemap_for_date( '2023-05-05' );
		$xml = get_post_meta( Metro_Sitemap::get_sitemap_post_id( 2023, 5, 5 ), 'msm_sitemap_xml', true );

		$this->assertStringContainsString( get_permalink( $keep ), $xml );
		$this->assertStringNotContainsString( get_permalink( $sponsor ), $xml );
	}

	/**
	 * A msm_sitemap_entry callback sees the post the entry is for.
	 */
	public function test_entry_callback_sees_matching_post(): void {
		$first  = $this->create_dummy_post( '2023-05-06 10:00:00' );
		$second = $this->create_dummy_post( '2023-05-06 11:00:00' );

		$seen = array();
		add_filter(
			'msm_sitemap_entry',
			static function ( $url ) use ( &$seen ) {
				$seen[ (string) $url->loc ] = get_the_ID();
				return $url;
			}
		);

		Metro_Sitemap::generate_sitemap_for_date( '2023-05-06' );

		$this->assertEquals(
			array(
				get_permalink( $first )  => $first,
				get_permalink( $second ) => $second,
			),
			$seen
		);
	}

	/**
	 * Generation puts the global post back the way it found it.
	 */
	public function test_global_post_is_restored(): void {
		$this->create_dummy_post( '2023-05-07 10:00:00' );
		$original        = get_post( $this->create_dummy_post( '2020-01-01 10:00:00' ) );
		$GLOBALS['post'] = $original; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		Metro_Sitemap::generate_sitemap_for_date( '2023-05-07' );

		$this->assertSame( $original, $GLOBALS['post'] );
	}
}
