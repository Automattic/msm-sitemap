<?php
/**
 * WP_Test_Sitemap_SitemapIndexEntryFactory
 *
 * @package Metro_Sitemap/unit_tests
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Integration;

use Automattic\MSM_Sitemap\Domain\ValueObjects\SitemapIndexEntry;
use Automattic\MSM_Sitemap\Infrastructure\Factories\SitemapIndexEntryFactory;

/**
 * Unit Tests for SitemapIndexEntryFactory.
 *
 * @author Gary Jones
 */
class SitemapIndexEntryFactoryTest extends TestCase {

	/**
	 * Test creating from data.
	 */
	public function test_from_data(): void {
		$entry = SitemapIndexEntryFactory::from_data( 'https://example.com/sitemap.xml', '2024-01-15T00:00:00+00:00' );

		$this->assertInstanceOf( SitemapIndexEntry::class, $entry );
		$this->assertEquals( 'https://example.com/sitemap.xml', $entry->loc() );
		$this->assertEquals( '2024-01-15T00:00:00+00:00', $entry->lastmod() );
	}

	/**
	 * Test creating from data without lastmod.
	 */
	public function test_from_data_without_lastmod(): void {
		$entry = SitemapIndexEntryFactory::from_data( 'https://example.com/sitemap.xml' );

		$this->assertInstanceOf( SitemapIndexEntry::class, $entry );
		$this->assertEquals( 'https://example.com/sitemap.xml', $entry->loc() );
		$this->assertNull( $entry->lastmod() );
	}

	/**
	 * Test creating from post.
	 */
	public function test_from_post(): void {
		$sitemap_post_id = $this->create_dummy_post( '2024-01-15 10:30:00', 'publish', 'msm_sitemap' );
		$sitemap_post = get_post( $sitemap_post_id );

		$entry = SitemapIndexEntryFactory::from_post( $sitemap_post );

		$this->assertInstanceOf( SitemapIndexEntry::class, $entry );
		$this->assertEquals( get_permalink( $sitemap_post ), $entry->loc() );
		$this->assertEquals( get_post_modified_time( 'c', false, $sitemap_post ), $entry->lastmod() );
	}

	/**
	 * Test creating from posts.
	 */
	public function test_from_posts(): void {
		$sitemap_post1_id = $this->create_dummy_post( '2024-01-15 10:30:00', 'publish', 'msm_sitemap' );
		$sitemap_post2_id = $this->create_dummy_post( '2024-01-16 10:30:00', 'publish', 'msm_sitemap' );
		$sitemap_post1 = get_post( $sitemap_post1_id );
		$sitemap_post2 = get_post( $sitemap_post2_id );

		$entries = SitemapIndexEntryFactory::from_posts( array( $sitemap_post1, $sitemap_post2 ) );

		$this->assertCount( 2, $entries );
		$this->assertInstanceOf( SitemapIndexEntry::class, $entries[0] );
		$this->assertInstanceOf( SitemapIndexEntry::class, $entries[1] );
		$this->assertEquals( get_permalink( $sitemap_post1 ), $entries[0]->loc() );
		$this->assertEquals( get_permalink( $sitemap_post2 ), $entries[1]->loc() );
	}

	/**
	 * Test creating from post IDs.
	 */
	public function test_from_post_ids(): void {
		$sitemap_post1_id = $this->create_dummy_post( '2024-01-15 10:30:00', 'publish', 'msm_sitemap' );
		$sitemap_post2_id = $this->create_dummy_post( '2024-01-16 10:30:00', 'publish', 'msm_sitemap' );

		$entries = SitemapIndexEntryFactory::from_post_ids( array( $sitemap_post1_id, $sitemap_post2_id ) );

		$this->assertCount( 2, $entries );
		$this->assertInstanceOf( SitemapIndexEntry::class, $entries[0] );
		$this->assertInstanceOf( SitemapIndexEntry::class, $entries[1] );
		$this->assertEquals( get_permalink( $sitemap_post1_id ), $entries[0]->loc() );
		$this->assertEquals( get_permalink( $sitemap_post2_id ), $entries[1]->loc() );
	}

	/**
	 * Test creating from post IDs with invalid IDs.
	 */
	public function test_from_post_ids_with_invalid_ids(): void {
		$entries = SitemapIndexEntryFactory::from_post_ids( array( 99999, 99998 ) );

		$this->assertCount( 0, $entries );
	}

	/**
	 * Test creating from post IDs with non-sitemap posts.
	 */
	public function test_from_post_ids_with_non_sitemap_posts(): void {
		$regular_post_id = $this->create_dummy_post( '2024-01-15 10:30:00', 'publish', 'post' );

		$entries = SitemapIndexEntryFactory::from_post_ids( array( $regular_post_id ) );

		$this->assertCount( 0, $entries );
	}

	/**
	 * Test creating sitemap index entries from sitemap dates.
	 */
	public function test_from_sitemap_dates() {
		$sitemap_dates = array(
			'2024-01-15 00:00:00',
			'2024-01-16 00:00:00',
		);

		$entries = SitemapIndexEntryFactory::from_sitemap_dates( $sitemap_dates );

		$this->assertCount( 2, $entries );
		$this->assertInstanceOf( SitemapIndexEntry::class, $entries[0] );
		$this->assertInstanceOf( SitemapIndexEntry::class, $entries[1] );

		// Check first entry
		$this->assertStringContainsString( 'sitemap.xml', $entries[0]->loc() );
		$this->assertStringContainsString( 'yyyy=2024', $entries[0]->loc() );
		$this->assertStringContainsString( 'mm=01', $entries[0]->loc() );
		$this->assertStringContainsString( 'dd=15', $entries[0]->loc() );
		$this->assertEquals( '2024-01-15T00:00:00+00:00', $entries[0]->lastmod() );

		// Check second entry
		$this->assertStringContainsString( 'sitemap.xml', $entries[1]->loc() );
		$this->assertStringContainsString( 'yyyy=2024', $entries[1]->loc() );
		$this->assertStringContainsString( 'mm=01', $entries[1]->loc() );
		$this->assertStringContainsString( 'dd=16', $entries[1]->loc() );
		$this->assertEquals( '2024-01-16T00:00:00+00:00', $entries[1]->lastmod() );
	}

	/**
	 * Test creating sitemap index entries from sitemap dates with index by year enabled.
	 */
	public function test_from_sitemap_dates_with_index_by_year() {
		// Enable index by year for this test
		add_filter( 'msm_sitemap_index_by_year', '__return_true' );

		$sitemap_dates = array(
			'2024-01-15 00:00:00',
			'2024-01-16 00:00:00',
		);

		$entries = SitemapIndexEntryFactory::from_sitemap_dates( $sitemap_dates );

		$this->assertCount( 2, $entries );
		$this->assertInstanceOf( SitemapIndexEntry::class, $entries[0] );
		$this->assertInstanceOf( SitemapIndexEntry::class, $entries[1] );

		// Check first entry - should use sitemap-2024.xml format
		$this->assertStringContainsString( 'sitemap-2024.xml', $entries[0]->loc() );
		$this->assertStringContainsString( 'mm=01', $entries[0]->loc() );
		$this->assertStringContainsString( 'dd=15', $entries[0]->loc() );
		$this->assertStringNotContainsString( 'yyyy=', $entries[0]->loc() );
		$this->assertEquals( '2024-01-15T00:00:00+00:00', $entries[0]->lastmod() );

		// Check second entry
		$this->assertStringContainsString( 'sitemap-2024.xml', $entries[1]->loc() );
		$this->assertStringContainsString( 'mm=01', $entries[1]->loc() );
		$this->assertStringContainsString( 'dd=16', $entries[1]->loc() );
		$this->assertStringNotContainsString( 'yyyy=', $entries[1]->loc() );
		$this->assertEquals( '2024-01-16T00:00:00+00:00', $entries[1]->lastmod() );

		// Remove the filter
		remove_filter( 'msm_sitemap_index_by_year', '__return_true' );
	}

	/**
	 * Test creating sitemap index entries from empty sitemap dates array.
	 */
	public function test_from_sitemap_dates_empty() {
		$entries = SitemapIndexEntryFactory::from_sitemap_dates( array() );

		$this->assertCount( 0, $entries );
		$this->assertIsArray( $entries );
	}

	/**
	 * Test that sitemap index lastmod respects site timezone.
	 *
	 * When the site is in a non-UTC timezone (e.g., America/New_York),
	 * the lastmod should include the correct timezone offset.
	 */
	public function test_from_sitemap_dates_respects_timezone(): void {
		// Set timezone to New York (UTC-5 / UTC-4 during DST).
		update_option( 'timezone_string', 'America/New_York' );
		wp_cache_flush();

		$sitemap_dates = array(
			'2024-01-15 00:00:00', // Winter: UTC-5.
			'2024-07-15 00:00:00', // Summer: UTC-4 (DST).
		);

		$entries = SitemapIndexEntryFactory::from_sitemap_dates( $sitemap_dates );

		$this->assertCount( 2, $entries );

		// Winter date should have -05:00 offset (EST).
		$this->assertEquals( '2024-01-15T00:00:00-05:00', $entries[0]->lastmod() );

		// Summer date should have -04:00 offset (EDT).
		$this->assertEquals( '2024-07-15T00:00:00-04:00', $entries[1]->lastmod() );

		// Reset timezone.
		update_option( 'timezone_string', 'UTC' );
		wp_cache_flush();
	}

	/**
	 * Test that sitemap index lastmod respects UTC timezone.
	 *
	 * When the site is in UTC, the lastmod should have +00:00 offset.
	 */
	public function test_from_sitemap_dates_with_utc_timezone(): void {
		// Ensure timezone is UTC.
		update_option( 'timezone_string', 'UTC' );
		wp_cache_flush();

		$sitemap_dates = array(
			'2024-01-15 00:00:00',
		);

		$entries = SitemapIndexEntryFactory::from_sitemap_dates( $sitemap_dates );

		$this->assertCount( 1, $entries );
		$this->assertEquals( '2024-01-15T00:00:00+00:00', $entries[0]->lastmod() );
	}

	/**
	 * Test that sitemap index lastmod respects Australian timezone.
	 *
	 * Australia/Sydney is ahead of UTC, so this tests positive offsets.
	 */
	public function test_from_sitemap_dates_with_sydney_timezone(): void {
		// Set timezone to Sydney (UTC+10 / UTC+11 during DST).
		update_option( 'timezone_string', 'Australia/Sydney' );
		wp_cache_flush();

		$sitemap_dates = array(
			'2024-01-15 00:00:00', // Summer in Australia: UTC+11 (AEDT).
			'2024-07-15 00:00:00', // Winter in Australia: UTC+10 (AEST).
		);

		$entries = SitemapIndexEntryFactory::from_sitemap_dates( $sitemap_dates );

		$this->assertCount( 2, $entries );

		// January (summer in Sydney) should have +11:00 offset (AEDT).
		$this->assertEquals( '2024-01-15T00:00:00+11:00', $entries[0]->lastmod() );

		// July (winter in Sydney) should have +10:00 offset (AEST).
		$this->assertEquals( '2024-07-15T00:00:00+10:00', $entries[1]->lastmod() );

		// Reset timezone.
		update_option( 'timezone_string', 'UTC' );
		wp_cache_flush();
	}
}
