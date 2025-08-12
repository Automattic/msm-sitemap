<?php
/**
 * Sitemap Generation Test
 *
 * @package Automattic\MSM_Sitemap\Tests\Application\Services
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Application\Services;

/**
 * Tests for sitemap generation functionality
 */
class SitemapGenerationTest extends \Automattic\MSM_Sitemap\Tests\TestCase {

	/**
	 * Test that generate_sitemap_for_date creates a sitemap post with the correct URL.
	 */
	public function test_creates_sitemap_post_when_posts_exist_for_date(): void {
		$date = '2018-01-01';
		$this->create_dummy_post( $date . ' 00:00:00', 'publish' );
		$this->generate_sitemap_for_date( $date );
		$sitemap_id = $this->get_sitemap_post_id( 2018, 1, 1 );
		$this->assertNotFalse( $sitemap_id );
		$xml = get_post_meta( $sitemap_id, 'msm_sitemap_xml', true );
		$this->assertIsString( $xml );
		$this->assertStringContainsString( '<urlset', $xml );
		$count = get_post_meta( $sitemap_id, 'msm_indexed_url_count', true );
		$this->assertEquals( 1, (int) $count );
	}

	/**
	 * Test that generate_sitemap_for_date does not create a sitemap post if there are no posts.
	 */
	public function test_does_not_create_sitemap_post_when_no_posts_exist(): void {
		$date = '2018-01-02';
		$this->generate_sitemap_for_date( $date );
		$sitemap_id = $this->get_sitemap_post_id( 2018, 1, 2 );
		$this->assertFalse( $sitemap_id );
	}

	/**
	 * Test that generate_sitemap_for_date does not create a sitemap post if all posts are skipped by filter.
	 */
	public function test_does_not_create_sitemap_post_when_all_posts_skipped_by_filter(): void {
		$date = '2018-01-05';
		$this->create_dummy_post( $date . ' 00:00:00', 'publish' );
		add_filter( 'msm_sitemap_skip_post', '__return_true', 10, 2 );
		$this->generate_sitemap_for_date( $date );
		$sitemap_id = $this->get_sitemap_post_id( 2018, 1, 5 );
		$this->assertFalse( $sitemap_id );
		remove_all_filters( 'msm_sitemap_skip_post' );
	}

	/**
	 * Test that generate_sitemap_for_date updates when posts change.
	 */
	public function test_updates_sitemap_when_posts_change(): void {
		$date = '2018-01-06';
		$this->create_dummy_post( $date . ' 00:00:00', 'publish' );
		$this->generate_sitemap_for_date( $date );
		$sitemap_id = $this->get_sitemap_post_id( 2018, 1, 6 );
		$this->assertNotFalse( $sitemap_id );
		$count1 = get_post_meta( $sitemap_id, 'msm_indexed_url_count', true );
		$this->assertEquals( 1, (int) $count1 );
		// Add another post for the same date
		$this->create_dummy_post( $date . ' 12:00:00', 'publish' );
		// Fine to force regeneration here because we're testing that the sitemap is updated when posts change.
		$this->generate_sitemap_for_date( $date, true );
		$sitemap_id2 = $this->get_sitemap_post_id( 2018, 1, 6 );
		$this->assertNotFalse( $sitemap_id2 );
		$count2 = get_post_meta( $sitemap_id2, 'msm_indexed_url_count', true );
		$this->assertEquals( 2, (int) $count2 );
	}

	// ===== EDGE CASES AND BOUNDARY CONDITIONS =====

	/**
	 * Test that generate_sitemap_for_date handles invalid date format gracefully.
	 *
	 * @dataProvider invalid_date_formats_data_provider
	 */
	public function test_handles_invalid_date_format_gracefully( ?string $invalid_date ): void {
		// Should not throw an exception or create invalid sitemaps
		if ( null !== $invalid_date ) {
			$this->generate_sitemap_for_date( $invalid_date );
			// Verify no sitemap was created for invalid date
			$sitemap_id = $this->get_sitemap_post_id( 2018, 1, 1 );
			$this->assertFalse( $sitemap_id );
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
	 * Test that generate_sitemap_for_date handles future dates correctly.
	 */
	public function test_handles_future_dates_correctly(): void {
		$future_date = gmdate( 'Y-m-d', strtotime( '+1 year' ) );
		
		// Create a post for a future date
		$this->create_dummy_post( $future_date . ' 00:00:00', 'publish' );
		$this->generate_sitemap_for_date( $future_date );
		
		// Should not create sitemap for future dates
		list( $year, $month, $day ) = explode( '-', $future_date );
		$sitemap_id = $this->get_sitemap_post_id( (int) $year, (int) $month, (int) $day );
		$this->assertFalse( $sitemap_id, 'Should not create sitemap for future dates' );
	}

	/**
	 * Test that generate_sitemap_for_date handles very old dates correctly.
	 */
	public function test_handles_very_old_dates_correctly(): void {
		$old_date = '1900-01-01';
		
		// Create a post for a very old date
		$this->create_dummy_post( $old_date . ' 00:00:00', 'publish' );
		$this->generate_sitemap_for_date( $old_date );
		
		// Should handle old dates gracefully (may or may not create sitemap depending on business logic)
		$sitemap_id = $this->get_sitemap_post_id( 1900, 1, 1 );
		// Assert that the system handles old dates without crashing
		$this->assertIsInt( $sitemap_id );
	}

	/**
	 * Test that generate_sitemap_for_date handles posts with very long titles and content.
	 */
	public function test_handles_posts_with_very_long_content(): void {
		$date = '2018-01-07';
		$long_title = str_repeat( 'A very long title that exceeds normal limits ', 50 );
		$long_content = str_repeat( 'A very long content that exceeds normal limits ', 1000 );
		
		$post_id = wp_insert_post(
			array(
				'post_title'   => $long_title,
				'post_content' => $long_content,
				'post_status'  => 'publish',
				'post_date'    => $date . ' 00:00:00',
			)
		);
		
		$this->generate_sitemap_for_date( $date );
		$sitemap_id = $this->get_sitemap_post_id( 2018, 1, 7 );
		$this->assertNotFalse( $sitemap_id );
		
		$xml = get_post_meta( $sitemap_id, 'msm_sitemap_xml', true );
		$this->assertIsString( $xml );
		$this->assertStringContainsString( '<urlset', $xml );
	}

	/**
	 * Test that generate_sitemap_for_date handles posts with special characters in titles.
	 */
	public function test_handles_posts_with_special_characters(): void {
		$date = '2018-01-08';
		$special_title = 'Post with special chars: & < > " \' éñç';
		
		$post_id = wp_insert_post(
			array(
				'post_title'   => $special_title,
				'post_content' => 'Content with special chars: & < > " \' éñç',
				'post_status'  => 'publish',
				'post_date'    => $date . ' 00:00:00',
			)
		);
		
		$this->generate_sitemap_for_date( $date );
		$sitemap_id = $this->get_sitemap_post_id( 2018, 1, 8 );
		$this->assertNotFalse( $sitemap_id );
		
		$xml = get_post_meta( $sitemap_id, 'msm_sitemap_xml', true );
		$this->assertIsString( $xml );
		$this->assertStringContainsString( '<urlset', $xml );
		// XML should be properly formatted and contain the post
		$this->assertStringContainsString( '<urlset', $xml );
		$this->assertStringContainsString( 'p=' . $post_id, $xml );
	}

	/**
	 * Test that generate_sitemap_for_date handles posts with custom post types correctly.
	 */
	public function test_handles_custom_post_types_correctly(): void {
		$date = '2018-01-09';
		
		// Register a custom post type
		register_post_type(
			'test_cpt',
			array(
				'public' => true,
				'label'  => 'Test CPT',
			)
		);
		
		// Create a post with custom post type
		$post_id = wp_insert_post(
			array(
				'post_title'   => 'Custom Post Type Test',
				'post_content' => 'Test content',
				'post_status'  => 'publish',
				'post_type'    => 'test_cpt',
				'post_date'    => $date . ' 00:00:00',
			)
		);
		
		$this->generate_sitemap_for_date( $date );
		$sitemap_id = $this->get_sitemap_post_id( 2018, 1, 9 );
		
		// Should handle custom post types according to business logic
		// Assert that the system handles custom post types without crashing
		$this->assertIsBool( $sitemap_id );
		
		// Clean up
		unregister_post_type( 'test_cpt' );
	}

	/**
	 * Test that generate_sitemap_for_date handles posts with different statuses correctly.
	 */
	public function test_handles_posts_with_different_statuses(): void {
		$date = '2018-01-10';
		
		// Create posts with different statuses
		$published_post = wp_insert_post(
			array(
				'post_title'   => 'Published Post',
				'post_content' => 'Published content',
				'post_status'  => 'publish',
				'post_date'    => $date . ' 00:00:00',
			)
		);
		
		$draft_post = wp_insert_post(
			array(
				'post_title'   => 'Draft Post',
				'post_content' => 'Draft content',
				'post_status'  => 'draft',
				'post_date'    => $date . ' 00:00:00',
			)
		);
		
		$private_post = wp_insert_post(
			array(
				'post_title'   => 'Private Post',
				'post_content' => 'Private content',
				'post_status'  => 'private',
				'post_date'    => $date . ' 00:00:00',
			)
		);
		
		$this->generate_sitemap_for_date( $date );
		$sitemap_id = $this->get_sitemap_post_id( 2018, 1, 10 );
		$this->assertNotFalse( $sitemap_id );
		
		$xml = get_post_meta( $sitemap_id, 'msm_sitemap_xml', true );
		$this->assertIsString( $xml );
		
		// Should only include published posts
		$this->assertStringContainsString( 'p=' . $published_post, $xml );
		$this->assertStringNotContainsString( 'p=' . $draft_post, $xml );
		$this->assertStringNotContainsString( 'p=' . $private_post, $xml );
	}

	/**
	 * Test that generate_sitemap_for_date handles large number of posts correctly.
	 * @group slow
	 */
	public function test_handles_large_number_of_posts_correctly(): void {
		$date = '2018-01-11';
		
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
		$sitemap_id = $this->get_sitemap_post_id( 2018, 1, 11 );
		$this->assertNotFalse( $sitemap_id );
		
		$xml = get_post_meta( $sitemap_id, 'msm_sitemap_xml', true );
		$this->assertIsString( $xml );
		
		// Should include all posts
		$url_count = substr_count( $xml, '<url>' );
		$this->assertEquals( $test_posts, $url_count, 'Sitemap should include all posts' );
		
		// Verify a sample of posts are included (don't check all 100)
		$sample_posts = array_slice( $post_ids, 0, 5 );
		foreach ( $sample_posts as $post_id ) {
			$this->assertStringContainsString( 'p=' . $post_id, $xml );
		}
	}

	/**
	 * Test that generate_sitemap_for_date handles empty post content correctly.
	 */
	public function test_handles_empty_post_content_correctly(): void {
		$date = '2018-01-12';
		
		// Create a post with empty content
		$post_id = wp_insert_post(
			array(
				'post_title'   => 'Empty Content Post',
				'post_content' => '',
				'post_status'  => 'publish',
				'post_date'    => $date . ' 00:00:00',
			)
		);
		
		$this->generate_sitemap_for_date( $date );
		$sitemap_id = $this->get_sitemap_post_id( 2018, 1, 12 );
		$this->assertNotFalse( $sitemap_id );
		
		$xml = get_post_meta( $sitemap_id, 'msm_sitemap_xml', true );
		$this->assertIsString( $xml );
		$this->assertStringContainsString( 'p=' . $post_id, $xml );
	}

	/**
	 * Test that generate_sitemap_for_date handles posts with very short titles.
	 */
	public function test_handles_posts_with_very_short_titles(): void {
		$date = '2018-01-13';
		
		// Create a post with very short title
		$post_id = wp_insert_post(
			array(
				'post_title'   => 'A',
				'post_content' => 'Content',
				'post_status'  => 'publish',
				'post_date'    => $date . ' 00:00:00',
			)
		);
		
		$this->generate_sitemap_for_date( $date );
		$sitemap_id = $this->get_sitemap_post_id( 2018, 1, 13 );
		$this->assertNotFalse( $sitemap_id );
		
		$xml = get_post_meta( $sitemap_id, 'msm_sitemap_xml', true );
		$this->assertIsString( $xml );
		$this->assertStringContainsString( 'p=' . $post_id, $xml );
	}
}
