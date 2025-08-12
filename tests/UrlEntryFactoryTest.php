<?php
/**
 * WP_Test_Sitemap_UrlEntryFactory
 *
 * @package Automattic\MSM_Sitemap\Tests
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests;

use Automattic\MSM_Sitemap\Domain\ValueObjects\UrlEntry;
use Automattic\MSM_Sitemap\Infrastructure\Factories\UrlEntryFactory;

/**
 * Unit Tests for UrlEntryFactory.
 *
 * @author Gary Jones
 */
class UrlEntryFactoryTest extends TestCase {

	/**
	 * Test creating a URL entry from raw data.
	 */
	public function test_from_data(): void {
		$url_entry = UrlEntryFactory::from_data(
			'https://example.com/my-post/',
			'2024-01-15T10:30:00+00:00',
			'weekly',
			0.8
		);

		$this->assertInstanceOf( UrlEntry::class, $url_entry );
		$this->assertEquals( 'https://example.com/my-post/', $url_entry->loc() );
		$this->assertEquals( '2024-01-15T10:30:00+00:00', $url_entry->lastmod() );
		$this->assertEquals( 'weekly', $url_entry->changefreq() );
		$this->assertEquals( 0.8, $url_entry->priority() );
	}

	/**
	 * Test creating URL entries from an array of post IDs.
	 */
	public function test_from_posts(): void {
		// Create test posts
		$post_id_1 = $this->create_dummy_post( '2024-01-15 10:30:00', 'publish' );
		$post_id_2 = $this->create_dummy_post( '2024-01-15 11:30:00', 'publish' );

		$url_entries = UrlEntryFactory::from_posts( array( $post_id_1, $post_id_2 ) );

		$this->assertCount( 2, $url_entries );
		$this->assertContainsOnlyInstancesOf( UrlEntry::class, $url_entries );

		// Verify the entries have the expected URLs
		$urls = array_map( fn( $entry ) => $entry->loc(), $url_entries );
		$this->assertContains( get_permalink( $post_id_1 ), $urls );
		$this->assertContains( get_permalink( $post_id_2 ), $urls );
	}

	/**
	 * Test creating URL entries from posts with some invalid post IDs.
	 */
	public function test_from_posts_with_invalid_ids(): void {
		// Create one valid post
		$post_id = $this->create_dummy_post( '2024-01-15 10:30:00', 'publish' );

		// Include an invalid post ID
		$url_entries = UrlEntryFactory::from_posts( array( $post_id, 99999 ) );

		$this->assertCount( 1, $url_entries );
		$this->assertInstanceOf( UrlEntry::class, $url_entries[0] );
		$this->assertEquals( get_permalink( $post_id ), $url_entries[0]->loc() );
	}

	/**
	 * Test creating URL entries from posts with filter exclusions.
	 */
	public function test_from_posts_with_filter_exclusions(): void {
		// Create test post
		$post_id = $this->create_dummy_post( '2024-01-15 10:30:00', 'publish' );

		// Add filter to skip posts
		add_filter( 'msm_sitemap_skip_post', '__return_true' );

		$url_entries = UrlEntryFactory::from_posts( array( $post_id ) );

		$this->assertCount( 0, $url_entries );

		// Clean up
		remove_filter( 'msm_sitemap_skip_post', '__return_true' );
	}

	/**
	 * Test creating URL entries with custom changefreq and priority filters.
	 */
	public function test_from_posts_with_custom_filters(): void {
		// Create test post
		$post_id = $this->create_dummy_post( '2024-01-15 10:30:00', 'publish' );

		// Add custom filters
		add_filter( 'msm_sitemap_changefreq', function( $changefreq, $post ) {
			return 'daily';
		}, 10, 2 );

		add_filter( 'msm_sitemap_priority', function( $priority, $post ) {
			return 0.9;
		}, 10, 2 );

		$url_entries = UrlEntryFactory::from_posts( array( $post_id ) );

		$this->assertCount( 1, $url_entries );
		$this->assertEquals( 'daily', $url_entries[0]->changefreq() );
		$this->assertEquals( 0.9, $url_entries[0]->priority() );

		// Clean up
		remove_all_filters( 'msm_sitemap_changefreq' );
		remove_all_filters( 'msm_sitemap_priority' );
	}

	/**
	 * Test creating a URL entry from a single post.
	 */
	public function test_from_post(): void {
		// Create test post
		$post_id = $this->create_dummy_post( '2024-01-15 10:30:00', 'publish' );

		$url_entry = UrlEntryFactory::from_post( $post_id );

		$this->assertInstanceOf( UrlEntry::class, $url_entry );
		$this->assertEquals( get_permalink( $post_id ), $url_entry->loc() );
		$this->assertEquals( 'monthly', $url_entry->changefreq() );
		$this->assertEquals( 0.7, $url_entry->priority() );
	}

	/**
	 * Test creating a URL entry from an invalid post ID.
	 */
	public function test_from_post_with_invalid_id(): void {
		$url_entry = UrlEntryFactory::from_post( 99999 );

		$this->assertNull( $url_entry );
	}

	/**
	 * Test creating a URL entry from a post that should be skipped.
	 */
	public function test_from_post_with_skip_filter(): void {
		// Create test post
		$post_id = $this->create_dummy_post( '2024-01-15 10:30:00', 'publish' );

		// Add filter to skip this specific post
		add_filter( 'msm_sitemap_skip_post', function( $skip, $post_id_to_check ) use ( $post_id ) {
			return $post_id_to_check === $post_id;
		}, 10, 2 );

		$url_entry = UrlEntryFactory::from_post( $post_id );

		$this->assertNull( $url_entry );

		// Clean up
		remove_all_filters( 'msm_sitemap_skip_post' );
	}
}
