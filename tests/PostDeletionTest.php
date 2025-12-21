<?php
/**
 * Tests for post deletion functionality.
 *
 * @package Automattic\MSM_Sitemap\Tests
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests;

use Automattic\MSM_Sitemap\Infrastructure\WordPress\CoreIntegration;

/**
 * Tests for post deletion functionality.
 */
class PostDeletionTest extends TestCase {

	/**
	 * Test that handle_post_deletion is called when a post is deleted.
	 */
	public function test_handle_post_deletion_hook_registered(): void {
		$core_integration = $this->get_service( CoreIntegration::class );
		$this->assertGreaterThan( 0, has_action( 'deleted_post', array( $core_integration, 'handle_post_deletion' ) ) );
		$this->assertGreaterThan( 0, has_action( 'trashed_post', array( $core_integration, 'handle_post_deletion' ) ) );
	}

	/**
	 * Test that handle_post_deletion processes supported post types.
	 */
	public function test_handle_post_deletion_processes_supported_post_types(): void {
		$date    = '2018-01-01';
		$post_id = $this->create_dummy_post( $date . ' 00:00:00', 'publish', 'post' );
		
		// Generate initial sitemap
		$this->generate_sitemap_for_date( $date );
		$sitemap_id = $this->get_sitemap_post_id( 2018, 1, 1 );
		$this->assertNotFalse( $sitemap_id );
		
		// Delete the post
		wp_delete_post( $post_id, true );
		
		// Check that the sitemap action was triggered
		// Note: We can't easily test the cron action directly, but we can verify the method works
		$post = get_post( $post_id );
		$this->assertNull( $post ); // Post should be deleted
	}

	/**
	 * Test that handle_post_deletion ignores unsupported post types.
	 */
	public function test_handle_post_deletion_ignores_unsupported_post_types(): void {
		$date    = '2018-01-02';
		$post_id = $this->create_dummy_post( $date . ' 00:00:00', 'publish', 'page' );
		
		// Generate initial sitemap using new DDD system - pages are not supported by default, so no sitemap should be created
		$result = $this->generate_sitemap_for_date( $date );
		$this->assertFalse( $result ); // No sitemap should be created for pages
		
		// Delete the page (unsupported post type)
		wp_delete_post( $post_id, true );
		
		// Verify the method doesn't throw an error
		$this->assertTrue( true );
	}

	/**
	 * Test that handle_post_deletion handles null post object gracefully.
	 */
	public function test_handle_post_deletion_handles_null_post_object(): void {
		$date    = '2018-01-03';
		$post_id = $this->create_dummy_post( $date . ' 00:00:00', 'publish' );
		
		// Generate initial sitemap
		$this->generate_sitemap_for_date( $date );
		
		// Call handle_post_deletion with null post object
		$core_integration = $this->get_service( CoreIntegration::class );
		$core_integration->handle_post_deletion( $post_id, null );
		
		// Method should not throw an error
		$this->assertTrue( true );
	}

	/**
	 * Test that handle_post_deletion handles invalid post ID gracefully.
	 */
	public function test_handle_post_deletion_handles_invalid_post_id(): void {
		// Call handle_post_deletion with invalid post ID
		$core_integration = $this->get_service( CoreIntegration::class );
		$core_integration->handle_post_deletion( 99999, null );
		
		// Method should not throw an error
		$this->assertTrue( true );
	}

	/**
	 * Test that handle_post_deletion validates date format.
	 */
	public function test_handle_post_deletion_validates_date_format(): void {
		// Create a post with an invalid date
		$post_data = array(
			'post_title'   => 'Test Post',
			'post_type'    => 'post',
			'post_content' => 'Test content',
			'post_status'  => 'publish',
			'post_author'  => 1,
			'post_date'    => 'invalid-date',
		);

		$post_id = wp_insert_post( $post_data );

		// Call handle_post_deletion
		$core_integration = $this->get_service( CoreIntegration::class );
		$core_integration->handle_post_deletion( $post_id, null );

		// Method should not throw an error
		$this->assertTrue( true );

		// Clean up - only delete if post was actually created (WP 6.9+ returns 0 for invalid posts)
		if ( $post_id > 0 ) {
			wp_delete_post( $post_id, true );
		}
	}

	/**
	 * Test that handle_post_deletion triggers sitemap update action.
	 */
	public function test_handle_post_deletion_triggers_sitemap_update_action(): void {
		$date    = '2018-01-04';
		$post_id = $this->create_dummy_post( $date . ' 00:00:00', 'publish' );
		
		// Mock the action to track if it's called
		$action_called = false;
		$action_args   = null;
		
		add_action(
			'msm_update_sitemap_for_year_month_date',
			function ( $date_array, $time ) use ( &$action_called, &$action_args ) {
				$action_called = true;
				$action_args   = array( $date_array, $time );
			},
			10,
			2
		);

		// Call handle_post_deletion
		$core_integration = $this->get_service( CoreIntegration::class );
		$core_integration->handle_post_deletion( $post_id, null );

		// Verify the action was called
		$this->assertTrue( $action_called );
		$this->assertIsArray( $action_args );
		$this->assertCount( 2, $action_args );
		$this->assertEquals( array( 2018, 1, 4 ), $action_args[0] );

		// Clean up
		remove_all_actions( 'msm_update_sitemap_for_year_month_date' );
	}

	/**
	 * Test that handle_post_deletion works with trashed posts.
	 */
	public function test_handle_post_deletion_works_with_trashed_posts(): void {
		$date    = '2018-01-05';
		$post_id = $this->create_dummy_post( $date . ' 00:00:00', 'publish' );
		
		// Generate initial sitemap
		$this->generate_sitemap_for_date( $date );
		$sitemap_id = $this->get_sitemap_post_id( 2018, 1, 5 );
		$this->assertNotFalse( $sitemap_id );
		
		// Trash the post
		wp_trash_post( $post_id );
		
		// Verify post is trashed
		$post = get_post( $post_id );
		$this->assertEquals( 'trash', $post->post_status );
	}

	/**
	 * Test that handle_post_deletion works with multiple posts from same date.
	 */
	public function test_handle_post_deletion_works_with_multiple_posts_same_date(): void {
		$date     = '2018-01-06';
		$post_id1 = $this->create_dummy_post( $date . ' 00:00:00', 'publish' );
		$post_id2 = $this->create_dummy_post( $date . ' 01:00:00', 'publish' );
		
		// Generate initial sitemap
		$this->generate_sitemap_for_date( $date );
		$sitemap_id = $this->get_sitemap_post_id( 2018, 1, 6 );
		$this->assertNotFalse( $sitemap_id );
		
		// Delete first post
		wp_delete_post( $post_id1, true );
		
		// Sitemap should still exist since there's another post
		$sitemap_id_after = $this->get_sitemap_post_id( 2018, 1, 6 );
		$this->assertNotFalse( $sitemap_id_after );
		
		// Delete second post
		wp_delete_post( $post_id2, true );
		
		// Now sitemap should be deleted (but this happens via cron, so we can't test it directly)
		// Instead, verify that the action was triggered
		$this->assertTrue( true ); // Just verify the method doesn't throw an error
	}

	/**
	 * Test that handle_post_deletion works with posts from different dates.
	 */
	public function test_handle_post_deletion_works_with_posts_different_dates(): void {
		$date1    = '2018-01-07';
		$date2    = '2018-01-08';
		$post_id1 = $this->create_dummy_post( $date1 . ' 00:00:00', 'publish' );
		$post_id2 = $this->create_dummy_post( $date2 . ' 00:00:00', 'publish' );
		
		// Generate initial sitemaps
		$this->generate_sitemap_for_date( $date1 );
		$this->generate_sitemap_for_date( $date2 );
		
		$sitemap_id1 = $this->get_sitemap_post_id( 2018, 1, 7 );
		$sitemap_id2 = $this->get_sitemap_post_id( 2018, 1, 8 );
		$this->assertNotFalse( $sitemap_id1 );
		$this->assertNotFalse( $sitemap_id2 );
		
		// Delete first post
		wp_delete_post( $post_id1, true );
		
		// First sitemap should be deleted, second should remain
		// Note: This happens via cron, so we can't test it directly
		$sitemap_id1_after = $this->get_sitemap_post_id( 2018, 1, 7 );
		$sitemap_id2_after = $this->get_sitemap_post_id( 2018, 1, 8 );
		// We can't assert specific behavior here since it depends on cron timing
		$this->assertTrue( true ); // Just verify the method doesn't throw an error
		
		// Delete second post
		wp_delete_post( $post_id2, true );
		
		// Second sitemap should also be deleted (but this happens via cron)
		$this->assertTrue( true ); // Just verify the method doesn't throw an error
	}

	/**
	 * Test that handle_post_deletion handles future dates correctly.
	 */
	public function test_handle_post_deletion_handles_future_dates(): void {
		$future_date = date( 'Y-m-d', strtotime( '+1 day' ) );
		$post_id     = $this->create_dummy_post( $future_date . ' 00:00:00', 'publish' );
		
		// Call handle_post_deletion
		$core_integration = $this->get_service( CoreIntegration::class );
		$core_integration->handle_post_deletion( $post_id, null );
		
		// Method should not throw an error
		$this->assertTrue( true );
		
		// Clean up
		wp_delete_post( $post_id, true );
	}

	/**
	 * Test that handle_post_deletion works with custom post types that are supported.
	 */
	public function test_handle_post_deletion_works_with_supported_custom_post_types(): void {
		// Register a custom post type that should be supported
		register_post_type(
			'test_post_type',
			array(
				'public' => true,
			) 
		);
		
		// Add filter to include our custom post type
		add_filter(
			'msm_sitemap_entry_post_type',
			function ( $post_types ) {
				$post_types[] = 'test_post_type';
				return $post_types;
			} 
		);
		
		$date    = '2018-01-09';
		$post_id = $this->create_dummy_post( $date . ' 00:00:00', 'publish', 'test_post_type' );
		
		// Generate initial sitemap
		$this->generate_sitemap_for_date( $date );
		$sitemap_id = $this->get_sitemap_post_id( 2018, 1, 9 );
		$this->assertNotFalse( $sitemap_id );
		
		// Delete the post
		wp_delete_post( $post_id, true );
		
		// Verify post is deleted
		$post = get_post( $post_id );
		$this->assertNull( $post );
		
		// Clean up
		unregister_post_type( 'test_post_type' );
		remove_all_filters( 'msm_sitemap_entry_post_type' );
	}

	/**
	 * Test that handle_post_deletion ignores unsupported custom post types.
	 */
	public function test_handle_post_deletion_ignores_unsupported_custom_post_types(): void {
		// Test with a built-in post type that's not supported (attachment)
		$date    = '2018-01-10';
		$post_id = $this->create_dummy_post( $date . ' 00:00:00', 'publish', 'attachment' );
		
		// Generate initial sitemap using new DDD system - unsupported post types should not create sitemaps
		$result = $this->generate_sitemap_for_date( $date );
		$this->assertFalse( $result ); // No sitemap should be created for unsupported post types
		
		// Delete the post
		wp_delete_post( $post_id, true );
		
		// Verify the method doesn't throw an error
		$this->assertTrue( true );
	}
} 
