<?php
/**
 * GlobalPostTest
 *
 * @package Automattic\MSM_Sitemap\Tests\Integration
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Integration;

use Automattic\MSM_Sitemap\Infrastructure\Factories\UrlEntryFactory;

/**
 * Tests that the global post is set up while sitemap filters run, as it was in 1.x.
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

		$locs = array_map(
			static fn ( $entry ) => $entry->loc(),
			UrlEntryFactory::from_posts( array( $keep, $sponsor ) )
		);

		$this->assertSame( array( get_permalink( $keep ) ), $locs );
	}

	/**
	 * Per-post filters see the post they are for.
	 */
	public function test_filters_see_matching_post(): void {
		$first  = $this->create_dummy_post( '2023-05-06 10:00:00' );
		$second = $this->create_dummy_post( '2023-05-06 11:00:00' );

		$seen = array();
		add_filter(
			'msm_sitemap_changefreq',
			static function ( $changefreq, $post ) use ( &$seen ) {
				$seen[ $post->ID ] = get_the_ID();
				return $changefreq;
			},
			10,
			2
		);

		UrlEntryFactory::from_posts( array( $first, $second ) );

		$this->assertSame(
			array(
				$first  => $first,
				$second => $second,
			),
			$seen
		);
	}

	/**
	 * The global post is put back the way it was found.
	 */
	public function test_global_post_is_restored(): void {
		$post_id         = $this->create_dummy_post( '2023-05-07 10:00:00' );
		$original        = get_post( $this->create_dummy_post( '2020-01-01 10:00:00' ) );
		$GLOBALS['post'] = $original; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		UrlEntryFactory::from_post( $post_id );

		$this->assertSame( $original, $GLOBALS['post'] );
	}
}
