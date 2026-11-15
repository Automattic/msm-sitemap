<?php
/**
 * Tests for sitemaps built from posts with a custom status.
 *
 * @package Automattic\MSM_Sitemap\Tests\Integration
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Integration;

use Automattic\MSM_Sitemap\Tests\Integration\Includes\CustomPostStatusTestTrait;
use Metro_Sitemap;

/**
 * The msm_sitemap_post_status filter chooses which posts go in the sitemap,
 * but the sitemap posts themselves must stay published, or the index and
 * daily sitemaps cannot find them.
 */
final class CustomPostStatusSitemapTest extends TestCase {

	use CustomPostStatusTestTrait;

	/**
	 * Use the custom post status for every test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->custom_post_status_set_up();
	}

	/**
	 * Remove the custom post status filter.
	 */
	public function tearDown(): void {
		$this->custom_post_status_tear_down();
		parent::tearDown();
	}

	/**
	 * Sitemaps for custom status posts are listed in the index and served.
	 */
	public function test_sitemap_for_custom_status_posts_is_published_and_served(): void {
		$post_id = $this->create_dummy_post( '2024-07-10 10:00:00', 'live' );

		Metro_Sitemap::generate_sitemap_for_date( '2024-07-10' );

		$sitemap_id = Metro_Sitemap::get_sitemap_post_id( 2024, 7, 10 );
		$this->assertNotFalse( $sitemap_id, 'The sitemap post should be found.' );
		$this->assertSame( 'publish', get_post_status( $sitemap_id ) );
		$this->assertSame( 1, Metro_Sitemap::count_sitemaps() );
		$this->assertStringContainsString( 'yyyy=2024&amp;mm=07&amp;dd=10', Metro_Sitemap::build_root_sitemap_xml() );
		$this->assertStringContainsString( get_permalink( $post_id ), (string) Metro_Sitemap::build_individual_sitemap_xml( 2024, 7, 10 ) );
	}

	/**
	 * Regenerating repairs a sitemap post saved with the custom status by earlier versions.
	 */
	public function test_regenerating_publishes_a_sitemap_saved_with_the_custom_status(): void {
		$this->create_dummy_post( '2024-07-10 10:00:00', 'live' );
		$sitemap_id = wp_insert_post(
			array(
				'post_name'   => '2024-07-10',
				'post_title'  => '2024-07-10',
				'post_type'   => Metro_Sitemap::SITEMAP_CPT,
				'post_status' => 'live',
				'post_date'   => '2024-07-10',
			)
		);

		Metro_Sitemap::generate_sitemap_for_date( '2024-07-10' );

		$this->assertSame( 'publish', get_post_status( $sitemap_id ) );
		$this->assertSame( $sitemap_id, Metro_Sitemap::get_sitemap_post_id( 2024, 7, 10 ) );
	}
}
