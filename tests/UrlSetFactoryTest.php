<?php
/**
 * WP_Test_Sitemap_UrlSetFactory
 *
 * @package Automattic\MSM_Sitemap\Tests
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests;

use Automattic\MSM_Sitemap\Domain\ValueObjects\UrlEntry;
use Automattic\MSM_Sitemap\Domain\ValueObjects\UrlSet;
use Automattic\MSM_Sitemap\Infrastructure\Factories\UrlSetFactory;

/**
 * Unit Tests for UrlSetFactory.
 *
 * @author Gary Jones
 */
class UrlSetFactoryTest extends TestCase {

	/**
	 * Test creating a URL set from raw data.
	 */
	public function test_from_data(): void {
		$entries_data = array(
			array(
				'loc'        => 'https://example.com/post-1/',
				'lastmod'    => '2024-01-15T10:30:00+00:00',
				'changefreq' => 'weekly',
				'priority'   => 0.8,
			),
			array(
				'loc'        => 'https://example.com/post-2/',
				'lastmod'    => '2024-01-16T11:30:00+00:00',
				'changefreq' => 'monthly',
				'priority'   => 0.6,
			),
		);

		$url_set = UrlSetFactory::from_data( $entries_data );

		$this->assertInstanceOf( UrlSet::class, $url_set );
		$this->assertEquals( 2, $url_set->count() );

		$entries = $url_set->get_entries();
		$this->assertCount( 2, $entries );
		$this->assertEquals( 'https://example.com/post-1/', $entries[0]->loc() );
		$this->assertEquals( 'https://example.com/post-2/', $entries[1]->loc() );
	}

	/**
	 * Test creating a URL set from an array of URL entries.
	 */
	public function test_from_entries(): void {
		$url_entry1 = new UrlEntry( 'https://example.com/post-1/' );
		$url_entry2 = new UrlEntry( 'https://example.com/post-2/' );
		$entries    = array( $url_entry1, $url_entry2 );

		$url_set = UrlSetFactory::from_entries( $entries );

		$this->assertInstanceOf( UrlSet::class, $url_set );
		$this->assertEquals( 2, $url_set->count() );
		$this->assertTrue( $url_set->contains( $url_entry1 ) );
		$this->assertTrue( $url_set->contains( $url_entry2 ) );
	}

	/**
	 * Test creating a URL set from an array of URL entries with custom limit.
	 */
	public function test_from_entries_with_custom_limit(): void {
		$url_entry1 = new UrlEntry( 'https://example.com/post-1/' );
		$url_entry2 = new UrlEntry( 'https://example.com/post-2/' );
		$entries    = array( $url_entry1, $url_entry2 );

		$url_set = UrlSetFactory::from_entries( $entries, 5 );

		$this->assertInstanceOf( UrlSet::class, $url_set );
		$this->assertEquals( 2, $url_set->count() );
		$this->assertTrue( $url_set->contains( $url_entry1 ) );
		$this->assertTrue( $url_set->contains( $url_entry2 ) );
		$this->assertFalse( $url_set->is_full() );
	}

	/**
	 * Test creating an empty URL set.
	 */
	public function test_create_empty(): void {
		$url_set = UrlSetFactory::create_empty();

		$this->assertInstanceOf( UrlSet::class, $url_set );
		$this->assertEquals( 0, $url_set->count() );
		$this->assertTrue( $url_set->is_empty() );
	}

	/**
	 * Test creating an empty URL set with custom limit.
	 */
	public function test_create_empty_with_custom_limit(): void {
		$url_set = UrlSetFactory::create_empty( 10 );

		$this->assertInstanceOf( UrlSet::class, $url_set );
		$this->assertEquals( 0, $url_set->count() );
		$this->assertTrue( $url_set->is_empty() );
		$this->assertFalse( $url_set->is_full() );

		// Add entries up to the custom limit
		for ( $i = 0; $i < 10; $i++ ) {
			$url_set->add( new UrlEntry( "https://example.com/post-{$i}/" ) );
		}

		$this->assertTrue( $url_set->is_full() );
		$this->assertEquals( 10, $url_set->count() );
	}

	/**
	 * Test creating a URL set from posts.
	 */
	public function test_from_posts(): void {
		// Create test posts
		$post_id_1 = $this->create_dummy_post( '2024-01-15 10:30:00', 'publish' );
		$post_id_2 = $this->create_dummy_post( '2024-01-15 11:30:00', 'publish' );

		$url_set = UrlSetFactory::from_posts( array( $post_id_1, $post_id_2 ) );

		$this->assertInstanceOf( UrlSet::class, $url_set );
		$this->assertEquals( 2, $url_set->count() );

		$entries = $url_set->get_entries();
		$this->assertCount( 2, $entries );
		$this->assertContains( get_permalink( $post_id_1 ), array_map( fn( $entry ) => $entry->loc(), $entries ) );
		$this->assertContains( get_permalink( $post_id_2 ), array_map( fn( $entry ) => $entry->loc(), $entries ) );
	}

	/**
	 * Test creating a URL set from posts with some invalid post IDs.
	 */
	public function test_from_posts_with_invalid_ids(): void {
		// Create one valid post
		$post_id = $this->create_dummy_post( '2024-01-15 10:30:00', 'publish' );

		// Include an invalid post ID
		$url_set = UrlSetFactory::from_posts( array( $post_id, 99999 ) );

		$this->assertInstanceOf( UrlSet::class, $url_set );
		$this->assertEquals( 1, $url_set->count() );

		$entries = $url_set->get_entries();
		$this->assertCount( 1, $entries );
		$this->assertEquals( get_permalink( $post_id ), $entries[0]->loc() );
	}

	/**
	 * Test creating a URL set from posts with filter exclusions.
	 */
	public function test_from_posts_with_filter_exclusions(): void {
		// Create test post
		$post_id = $this->create_dummy_post( '2024-01-15 10:30:00', 'publish' );

		// Add filter to skip posts
		add_filter( 'msm_sitemap_skip_post', '__return_true' );

		$url_set = UrlSetFactory::from_posts( array( $post_id ) );

		$this->assertInstanceOf( UrlSet::class, $url_set );
		$this->assertEquals( 0, $url_set->count() );
		$this->assertTrue( $url_set->is_empty() );

		// Clean up
		remove_filter( 'msm_sitemap_skip_post', '__return_true' );
	}

	/**
	 * Test creating a URL set from posts with custom filters.
	 */
	public function test_from_posts_with_custom_filters(): void {
		// Create test post
		$post_id = $this->create_dummy_post( '2024-01-15 10:30:00', 'publish' );

		// Add custom filters
		add_filter(
			'msm_sitemap_changefreq',
			function ( $changefreq, $post ) {
				return 'daily';
			},
			10,
			2 
		);

		add_filter(
			'msm_sitemap_priority',
			function ( $priority, $post ) {
				return 0.9;
			},
			10,
			2 
		);

		$url_set = UrlSetFactory::from_posts( array( $post_id ) );

		$this->assertInstanceOf( UrlSet::class, $url_set );
		$this->assertEquals( 1, $url_set->count() );

		$entries = $url_set->get_entries();
		$this->assertCount( 1, $entries );
		$this->assertEquals( 'daily', $entries[0]->changefreq() );
		$this->assertEquals( 0.9, $entries[0]->priority() );

		// Clean up
		remove_all_filters( 'msm_sitemap_changefreq' );
		remove_all_filters( 'msm_sitemap_priority' );
	}

	/**
	 * Test creating a URL set from empty posts array.
	 */
	public function test_from_posts_empty(): void {
		$url_set = UrlSetFactory::from_posts( array() );

		$this->assertInstanceOf( UrlSet::class, $url_set );
		$this->assertEquals( 0, $url_set->count() );
		$this->assertTrue( $url_set->is_empty() );
	}

	/**
	 * Test creating a URL set from data with missing optional fields.
	 */
	public function test_from_data_with_missing_optional_fields(): void {
		$entries_data = array(
			array(
				'loc' => 'https://example.com/simple-post/',
			),
		);

		$url_set = UrlSetFactory::from_data( $entries_data );

		$this->assertInstanceOf( UrlSet::class, $url_set );
		$this->assertEquals( 1, $url_set->count() );

		$entries = $url_set->get_entries();
		$this->assertCount( 1, $entries );
		$this->assertEquals( 'https://example.com/simple-post/', $entries[0]->loc() );
		$this->assertNull( $entries[0]->lastmod() );
		$this->assertNull( $entries[0]->changefreq() );
		$this->assertNull( $entries[0]->priority() );
	}
}
