<?php
/**
 * Post Content Provider Test
 *
 * @package Automattic\MSM_Sitemap\Tests
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests;

use Automattic\MSM_Sitemap\Domain\ValueObjects\SitemapContentType;
use Automattic\MSM_Sitemap\Domain\ValueObjects\UrlSet;
use Automattic\MSM_Sitemap\Infrastructure\Providers\PostContentProvider;

/**
 * Test PostContentProvider.
 */
class PostContentProviderTest extends TestCase {

	/**
	 * Post content provider instance.
	 *
	 * @var PostContentProvider
	 */
	private PostContentProvider $provider;

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->provider = new PostContentProvider();
	}

	/**
	 * Test get_content_type returns posts.
	 */
	public function test_get_content_type_returns_posts(): void {
		$content_type = $this->provider->get_content_type();
		$this->assertIsString( $content_type );
		$this->assertEquals( 'posts', $content_type );
	}

	/**
	 * Test get_display_name returns posts.
	 */
	public function test_get_display_name_returns_posts(): void {
		$this->assertEquals( 'Posts', $this->provider->get_display_name() );
	}

	/**
	 * Test get_description returns description.
	 */
	public function test_get_description_returns_description(): void {
		$this->assertEquals( 'Include published posts in sitemaps', $this->provider->get_description() );
	}

	/**
	 * Test get_urls_for_date with invalid date returns empty set.
	 */
	public function test_get_urls_for_date_with_invalid_date_returns_empty_set(): void {
		$url_set = $this->provider->get_urls_for_date( 'invalid-date' );
		$this->assertInstanceOf( UrlSet::class, $url_set );
		$this->assertTrue( $url_set->is_empty() );
	}

	/**
	 * Test get_urls_for_date with valid date but no posts returns empty set.
	 */
	public function test_get_urls_for_date_with_valid_date_no_posts_returns_empty_set(): void {
		$url_set = $this->provider->get_urls_for_date( '2024-01-15 00:00:00' );
		$this->assertInstanceOf( UrlSet::class, $url_set );
		$this->assertTrue( $url_set->is_empty() );
	}

	/**
	 * Test get_urls_for_date with valid date and posts returns url set.
	 */
	public function test_get_urls_for_date_with_valid_date_and_posts_returns_url_set(): void {
		// Create a test post
		$post_id = $this->create_dummy_post( '2024-01-15' );

		$url_set = $this->provider->get_urls_for_date( '2024-01-15 00:00:00' );
		$this->assertInstanceOf( UrlSet::class, $url_set );
		$this->assertFalse( $url_set->is_empty() );
		$this->assertEquals( 1, $url_set->count() );

		// Clean up
		wp_delete_post( $post_id, true );
	}

	/**
	 * Test get_urls_for_date respects post status filter.
	 */
	public function test_get_urls_for_date_respects_post_status_filter(): void {
		// Create a test post
		$post_id = $this->create_dummy_post( '2024-01-15' );

		// Add filter to change post status
		add_filter( 'msm_sitemap_post_status', function() {
			return 'draft';
		} );

		$url_set = $this->provider->get_urls_for_date( '2024-01-15 00:00:00' );
		$this->assertTrue( $url_set->is_empty() );

		// Clean up
		remove_all_filters( 'msm_sitemap_post_status' );
		wp_delete_post( $post_id, true );
	}

	/**
	 * Test get_urls_for_date respects post type filter.
	 */
	public function test_get_urls_for_date_respects_post_type_filter(): void {
		// Create a test post
		$post_id = $this->create_dummy_post( '2024-01-15' );

		// Add filter to change post types
		add_filter( 'msm_sitemap_entry_post_type', function() {
			return array( 'page' );
		} );

		$url_set = $this->provider->get_urls_for_date( '2024-01-15 00:00:00' );
		$this->assertTrue( $url_set->is_empty() );

		// Clean up
		remove_all_filters( 'msm_sitemap_entry_post_type' );
		wp_delete_post( $post_id, true );
	}
}
