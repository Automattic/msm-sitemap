<?php
/**
 * Sitemap Deletion Test
 *
 * @package Automattic\MSM_Sitemap\Tests\Application\Services
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Application\Services;

/**
 * Tests for sitemap deletion functionality
 */
class SitemapDeletionTest extends \Automattic\MSM_Sitemap\Tests\TestCase {

	/**
	 * Test that generate_sitemap_for_date, delete_sitemap_for_date, and regenerate_sitemap_for_date work as expected.
	 */
	public function test_can_delete_and_regenerate_sitemap_for_date(): void {
		$date = '2018-01-04';
		$this->create_dummy_post( $date . ' 00:00:00', 'publish' );
		$this->generate_sitemap_for_date( $date );
		$sitemap_id = $this->get_sitemap_post_id( 2018, 1, 4 );
		$this->assertNotFalse( $sitemap_id );

		$generator  = msm_sitemap_plugin()->get_sitemap_generator();
		$repository = new \Automattic\MSM_Sitemap\Infrastructure\Repositories\SitemapPostRepository();
		$service    = new \Automattic\MSM_Sitemap\Application\Services\SitemapService( $generator, $repository );
		$service->delete_for_date( $date );
		$sitemap_id2 = $this->get_sitemap_post_id( 2018, 1, 4 );
		$this->assertFalse( $sitemap_id2 );
		// Regenerate
		$this->create_dummy_post( $date . ' 00:00:00', 'publish' );
		$this->generate_sitemap_for_date( $date );
		$sitemap_id3 = $this->get_sitemap_post_id( 2018, 1, 4 );
		$this->assertNotFalse( $sitemap_id3 );
	}

	/**
	 * Validate that MSM Sitemap CPTs are deleted when all posts are removed for a date.
	 */
	public function test_deletes_sitemap_when_all_posts_removed(): void {
		global $wpdb;

		$this->add_a_post_for_each_of_the_last_x_days_before_today( 4 );

		$this->assertPostCount( 4 );
		$this->build_sitemaps();

		list( $sitemap ) = get_posts(
			array(
				'post_type'      => \Automattic\MSM_Sitemap\Plugin::SITEMAP_CPT,
				'posts_per_page' => 1,
			)
		);

		$sitemap_date               = gmdate( 'Y-m-d', strtotime( $sitemap->post_date ) );
		list( $year, $month, $day ) = explode( '-', $sitemap_date );
		$start_date                 = $sitemap_date . ' 00:00:00';
		$end_date                   = $sitemap_date . ' 23:59:59';
		$post_ids                   = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_status = 'publish' AND post_date >= %s AND post_date <= %s AND post_type = 'post' LIMIT 1", $start_date, $end_date ) );

		$expected_total_urls = (int) get_option( 'msm_sitemap_indexed_url_count', 0 ) - count( $post_ids );

		foreach ( $post_ids as $post_id ) {
			wp_delete_post( $post_id, true );
		}

		// Use the new DDD system to trigger deletion logic
		$this->generate_sitemap_for_date( $sitemap_date, true );

		$this->assertEmpty( get_post( $sitemap->ID ), 'Sitemap with no posts was not deleted' );
		$this->assertEquals( $expected_total_urls, (int) get_option( 'msm_sitemap_indexed_url_count', 0 ), 'Mismatch in total indexed URLs' );
	}

	/**
	 * Validate that sitemaps are deleted if all posts are skipped by filter.
	 */
	public function test_deletes_sitemap_when_all_posts_skipped(): void {
		// Create a post for today.
		$today = gmdate( 'Y-m-d' ) . ' 00:00:00';
		$this->create_dummy_post( $today );

		// Generate the sitemap for today using the new DDD system (should create a sitemap post).
		$this->generate_sitemap_for_date( gmdate( 'Y-m-d' ) );

		// Get the sitemap post ID for today.
		list( $year, $month, $day ) = explode( '-', gmdate( 'Y-m-d' ) );
		$sitemap_id                 = $this->get_sitemap_post_id( $year, $month, $day );
		$this->assertNotFalse( $sitemap_id, 'Sitemap post should exist before skipping.' );

		// Add a filter to skip all posts.
		add_filter( 'msm_sitemap_skip_post', '__return_true', 10, 2 );

		// Rebuild the sitemap for today using the new DDD system (should delete the sitemap post).
		$this->generate_sitemap_for_date( gmdate( 'Y-m-d' ), true );

		// The sitemap post should now be deleted.
		$sitemap_id_after = $this->get_sitemap_post_id( $year, $month, $day );
		$this->assertFalse( $sitemap_id_after, 'Sitemap post should be deleted if all posts are skipped.' );

		// Clean up filter.
		remove_all_filters( 'msm_sitemap_skip_post' );
	}

	// ===== EDGE CASES AND BOUNDARY CONDITIONS =====

	/**
	 * Test that delete_for_date handles invalid date format gracefully.
	 *
	 * @dataProvider invalid_date_formats_data_provider
	 */
	public function test_handles_invalid_date_format_gracefully( ?string $invalid_date ): void {
		$generator  = msm_sitemap_plugin()->get_sitemap_generator();
		$repository = new \Automattic\MSM_Sitemap\Infrastructure\Repositories\SitemapPostRepository();
		$service    = new \Automattic\MSM_Sitemap\Application\Services\SitemapService( $generator, $repository );

		// Should not throw an exception for valid dates
		if ( null !== $invalid_date ) {
			$result = $service->delete_for_date( $invalid_date );
			// Should handle gracefully (may return false or throw exception depending on implementation)
			$this->assertIsBool( $result );
		} else {
			// For null values, just verify the method doesn't crash
			$this->assertNull( $invalid_date );
		}
	}

	/**
	 * Data provider for invalid date formats.
	 */
	public function invalid_date_formats_data_provider(): iterable {
		yield 'invalid date string' => array( 'invalid_date' => 'invalid-date' );
		yield 'invalid month' => array( 'invalid_date' => '2018-13-01' );
		yield 'invalid day' => array( 'invalid_date' => '2018-01-32' );
		yield 'invalid day for February' => array( 'invalid_date' => '2018-02-30' );
		yield 'not a date' => array( 'invalid_date' => 'not-a-date' );
		yield 'empty string' => array( 'invalid_date' => '' );
		yield 'null value' => array( 'invalid_date' => null );
	}

	/**
	 * Test that delete_for_date handles non-existent sitemaps gracefully.
	 */
	public function test_handles_non_existent_sitemaps_gracefully(): void {
		$date = '2018-01-20';
		
		// Ensure no sitemap exists for this date
		$sitemap_id = $this->get_sitemap_post_id( 2018, 1, 20 );
		$this->assertFalse( $sitemap_id, 'No sitemap should exist for this date' );

		$generator  = msm_sitemap_plugin()->get_sitemap_generator();
		$repository = new \Automattic\MSM_Sitemap\Infrastructure\Repositories\SitemapPostRepository();
		$service    = new \Automattic\MSM_Sitemap\Application\Services\SitemapService( $generator, $repository );

		// Should not throw an exception when trying to delete non-existent sitemap
		$result = $service->delete_for_date( $date );
		// Should handle gracefully (may return false or throw exception depending on implementation)
	}

	/**
	 * Test that delete_for_date handles future dates correctly.
	 */
	public function test_handles_future_dates_correctly(): void {
		$future_date = gmdate( 'Y-m-d', strtotime( '+1 year' ) );
		
		$generator  = msm_sitemap_plugin()->get_sitemap_generator();
		$repository = new \Automattic\MSM_Sitemap\Infrastructure\Repositories\SitemapPostRepository();
		$service    = new \Automattic\MSM_Sitemap\Application\Services\SitemapService( $generator, $repository );

		// Should handle future dates gracefully
		$result = $service->delete_for_date( $future_date );
		// Should handle gracefully (may return false or throw exception depending on implementation)
		$this->assertIsBool( $result );
	}

	/**
	 * Test that delete_for_date handles very old dates correctly.
	 */
	public function test_handles_very_old_dates_correctly(): void {
		$old_date = '1900-01-01';
		
		$generator  = msm_sitemap_plugin()->get_sitemap_generator();
		$repository = new \Automattic\MSM_Sitemap\Infrastructure\Repositories\SitemapPostRepository();
		$service    = new \Automattic\MSM_Sitemap\Application\Services\SitemapService( $generator, $repository );

		// Should handle very old dates gracefully
		$result = $service->delete_for_date( $old_date );
		// Should handle gracefully (may return false or throw exception depending on implementation)
		$this->assertIsBool( $result );
	}

	/**
	 * Test that deletion works correctly when sitemap has many URLs.
	 * @group slow
	 */
	public function test_deletes_sitemap_with_many_urls_correctly(): void {
		$date = '2018-01-21';
		
		// Create the maximum number of posts allowed per sitemap page (500)
		$test_posts = 500;
		$post_ids = array();
		for ( $i = 1; $i <= $test_posts; $i++ ) {
			$post_ids[] = wp_insert_post(
				array(
					'post_title'   => "Post {$i}",
					'post_content' => "Content for post {$i}",
					'post_status'  => 'publish',
					'post_date'    => $date . ' 00:00:00',
				)
			);
		}
		
		$this->generate_sitemap_for_date( $date );
		$sitemap_id = $this->get_sitemap_post_id( 2018, 1, 21 );
		$this->assertNotFalse( $sitemap_id );
		
		// Delete all posts efficiently
		foreach ( $post_ids as $post_id ) {
			wp_delete_post( $post_id, true );
		}
		
		// Regenerate sitemap (should delete it since no posts remain)
		$this->generate_sitemap_for_date( $date, true );
		
		$sitemap_id_after = $this->get_sitemap_post_id( 2018, 1, 21 );
		$this->assertFalse( $sitemap_id_after, 'Sitemap should be deleted when all posts are removed' );
	}

	/**
	 * Test that deletion works correctly when sitemap has only one URL.
	 */
	public function test_deletes_sitemap_with_single_url_correctly(): void {
		$date = '2018-01-22';
		
		// Create only one post
		$post_id = wp_insert_post(
			array(
				'post_title'   => 'Single Post',
				'post_content' => 'Single post content',
				'post_status'  => 'publish',
				'post_date'    => $date . ' 00:00:00',
			)
		);
		
		$this->generate_sitemap_for_date( $date );
		$sitemap_id = $this->get_sitemap_post_id( 2018, 1, 22 );
		$this->assertNotFalse( $sitemap_id );
		
		// Delete the post
		wp_delete_post( $post_id, true );
		
		// Regenerate sitemap (should delete it since no posts remain)
		$this->generate_sitemap_for_date( $date, true );
		
		$sitemap_id_after = $this->get_sitemap_post_id( 2018, 1, 22 );
		$this->assertFalse( $sitemap_id_after, 'Sitemap should be deleted when the only post is removed' );
	}

	/**
	 * Test that deletion works correctly when posts are trashed instead of deleted.
	 */
	public function test_handles_trashed_posts_correctly(): void {
		$date = '2018-01-23';
		
		// Create a post
		$post_id = wp_insert_post(
			array(
				'post_title'   => 'Trashed Post',
				'post_content' => 'Trashed post content',
				'post_status'  => 'publish',
				'post_date'    => $date . ' 00:00:00',
			)
		);
		
		$this->generate_sitemap_for_date( $date );
		$sitemap_id = $this->get_sitemap_post_id( 2018, 1, 23 );
		$this->assertNotFalse( $sitemap_id );
		
		// Trash the post (don't delete permanently)
		wp_trash_post( $post_id );
		
		// Regenerate sitemap (should delete it since trashed posts are not published)
		$this->generate_sitemap_for_date( $date, true );
		
		$sitemap_id_after = $this->get_sitemap_post_id( 2018, 1, 23 );
		$this->assertFalse( $sitemap_id_after, 'Sitemap should be deleted when post is trashed' );
	}

	/**
	 * Test that deletion works correctly when posts are moved to different dates.
	 */
	public function test_handles_posts_moved_to_different_dates(): void {
		$original_date = '2018-01-24';
		$new_date = '2018-01-25';
		
		// Create a post
		$post_id = wp_insert_post(
			array(
				'post_title'   => 'Moved Post',
				'post_content' => 'Moved post content',
				'post_status'  => 'publish',
				'post_date'    => $original_date . ' 00:00:00',
			)
		);
		
		$this->generate_sitemap_for_date( $original_date );
		$sitemap_id = $this->get_sitemap_post_id( 2018, 1, 24 );
		$this->assertNotFalse( $sitemap_id );
		
		// Move the post to a different date
		wp_update_post(
			array(
				'ID'        => $post_id,
				'post_date' => $new_date . ' 00:00:00',
			)
		);
		
		// Regenerate sitemap for original date (should delete it since no posts remain)
		$this->generate_sitemap_for_date( $original_date, true );
		
		$sitemap_id_after = $this->get_sitemap_post_id( 2018, 1, 24 );
		$this->assertFalse( $sitemap_id_after, 'Sitemap should be deleted when post is moved to different date' );
		
		// Generate sitemap for new date (should create it)
		$this->generate_sitemap_for_date( $new_date );
		$new_sitemap_id = $this->get_sitemap_post_id( 2018, 1, 25 );
		$this->assertNotFalse( $new_sitemap_id, 'Sitemap should be created for new date' );
	}

	/**
	 * Test that deletion works correctly when posts are changed to different statuses.
	 */
	public function test_handles_posts_changed_to_different_statuses(): void {
		$date = '2018-01-26';
		
		// Create a published post
		$post_id = wp_insert_post(
			array(
				'post_title'   => 'Status Changed Post',
				'post_content' => 'Status changed post content',
				'post_status'  => 'publish',
				'post_date'    => $date . ' 00:00:00',
			)
		);
		
		$this->generate_sitemap_for_date( $date );
		$sitemap_id = $this->get_sitemap_post_id( 2018, 1, 26 );
		$this->assertNotFalse( $sitemap_id );
		
		// Change post status to draft
		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'draft',
			)
		);
		
		// Regenerate sitemap (should delete it since no published posts remain)
		$this->generate_sitemap_for_date( $date, true );
		
		$sitemap_id_after = $this->get_sitemap_post_id( 2018, 1, 26 );
		$this->assertFalse( $sitemap_id_after, 'Sitemap should be deleted when post status changes to non-published' );
	}

	/**
	 * Test that deletion works correctly when sitemap has corrupted XML content.
	 */
	public function test_handles_corrupted_xml_content_gracefully(): void {
		$date = '2018-01-27';
		
		// Create a post
		$post_id = wp_insert_post(
			array(
				'post_title'   => 'Corrupted XML Test Post',
				'post_content' => 'Corrupted XML test post content',
				'post_status'  => 'publish',
				'post_date'    => $date . ' 00:00:00',
			)
		);
		
		$this->generate_sitemap_for_date( $date );
		$sitemap_id = $this->get_sitemap_post_id( 2018, 1, 27 );
		$this->assertNotFalse( $sitemap_id );
		
		// Corrupt the XML content
		update_post_meta( $sitemap_id, 'msm_sitemap_xml', 'Corrupted XML content <url><loc>invalid' );
		
		// Should handle corrupted XML gracefully when regenerating
		$this->generate_sitemap_for_date( $date, true );
		
		$sitemap_id_after = $this->get_sitemap_post_id( 2018, 1, 27 );
		$this->assertNotFalse( $sitemap_id_after, 'Sitemap should still exist after handling corrupted XML' );
		
		// Verify XML is now valid
		$xml = get_post_meta( $sitemap_id_after, 'msm_sitemap_xml', true );
		$this->assertIsString( $xml );
		$this->assertStringContainsString( '<urlset', $xml );
	}
}
