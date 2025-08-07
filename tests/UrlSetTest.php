<?php
/**
 * WP_Test_Sitemap_UrlSet
 *
 * @package Metro_Sitemap/unit_tests
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests;

use Automattic\MSM_Sitemap\Domain\ValueObjects\UrlEntry;
use Automattic\MSM_Sitemap\Domain\ValueObjects\UrlSet;

/**
 * Unit Tests for UrlSet value object.
 *
 * @author Gary Jones
 */
class UrlSetTest extends TestCase {

	/**
	 * Test creating a valid URL set.
	 */
	public function test_create_valid_url_set(): void {
		$url_entry = new UrlEntry( 'https://example.com/my-post/' );
		$url_set = new UrlSet( array( $url_entry ) );

		$this->assertEquals( 1, $url_set->count() );
		$this->assertFalse( $url_set->is_empty() );
		$this->assertFalse( $url_set->is_full() );
		$this->assertTrue( $url_set->contains( $url_entry ) );
	}

	/**
	 * Test creating an empty URL set.
	 */
	public function test_create_empty_url_set(): void {
		$url_set = new UrlSet();

		$this->assertEquals( 0, $url_set->count() );
		$this->assertTrue( $url_set->is_empty() );
		$this->assertFalse( $url_set->is_full() );
	}

	/**
	 * Test adding a URL entry to the set.
	 */
	public function test_add_url_entry(): void {
		$url_set = new UrlSet();
		$url_entry = new UrlEntry( 'https://example.com/my-post/' );

		$url_set->add( $url_entry );

		$this->assertEquals( 1, $url_set->count() );
		$this->assertTrue( $url_set->contains( $url_entry ) );
	}

	/**
	 * Test removing a URL entry from the set.
	 */
	public function test_remove_url_entry(): void {
		$url_entry = new UrlEntry( 'https://example.com/my-post/' );
		$url_set = new UrlSet( array( $url_entry ) );

		$result = $url_set->remove( $url_entry );

		$this->assertTrue( $result );
		$this->assertEquals( 0, $url_set->count() );
		$this->assertFalse( $url_set->contains( $url_entry ) );
	}

	/**
	 * Test removing a URL entry that doesn't exist.
	 */
	public function test_remove_nonexistent_url_entry(): void {
		$url_set = new UrlSet();
		$url_entry = new UrlEntry( 'https://example.com/my-post/' );

		$result = $url_set->remove( $url_entry );

		$this->assertFalse( $result );
		$this->assertEquals( 0, $url_set->count() );
	}

	/**
	 * Test getting all entries.
	 */
	public function test_get_entries(): void {
		$url_entry1 = new UrlEntry( 'https://example.com/post-1/' );
		$url_entry2 = new UrlEntry( 'https://example.com/post-2/' );
		$url_set = new UrlSet( array( $url_entry1, $url_entry2 ) );

		$entries = $url_set->get_entries();

		$this->assertCount( 2, $entries );
		$this->assertContains( $url_entry1, $entries );
		$this->assertContains( $url_entry2, $entries );
	}

	/**
	 * Test to array method.
	 */
	public function test_to_array(): void {
		$url_entry = new UrlEntry(
			'https://example.com/my-post/',
			'2024-01-15T10:30:00+00:00',
			'weekly',
			0.8
		);
		$url_set = new UrlSet( array( $url_entry ) );

		$array = $url_set->to_array();

		$this->assertCount( 1, $array );
		$this->assertEquals( 'https://example.com/my-post/', $array[0]['loc'] );
		$this->assertEquals( '2024-01-15T10:30:00+00:00', $array[0]['lastmod'] );
		$this->assertEquals( 'weekly', $array[0]['changefreq'] );
		$this->assertEquals( 0.8, $array[0]['priority'] );
	}



	/**
	 * Test equals method with identical sets.
	 */
	public function test_equals_with_identical_sets(): void {
		$url_entry1 = new UrlEntry( 'https://example.com/post-1/' );
		$url_entry2 = new UrlEntry( 'https://example.com/post-2/' );

		$url_set1 = new UrlSet( array( $url_entry1, $url_entry2 ) );
		$url_set2 = new UrlSet( array( $url_entry1, $url_entry2 ) );

		$this->assertTrue( $url_set1->equals( $url_set2 ) );
	}

	/**
	 * Test equals method with different sets.
	 */
	public function test_equals_with_different_sets(): void {
		$url_entry1 = new UrlEntry( 'https://example.com/post-1/' );
		$url_entry2 = new UrlEntry( 'https://example.com/post-2/' );
		$url_entry3 = new UrlEntry( 'https://example.com/post-3/' );

		$url_set1 = new UrlSet( array( $url_entry1, $url_entry2 ) );
		$url_set2 = new UrlSet( array( $url_entry1, $url_entry3 ) );

		$this->assertFalse( $url_set1->equals( $url_set2 ) );
	}

	/**
	 * Test equals method with different sizes.
	 */
	public function test_equals_with_different_sizes(): void {
		$url_entry1 = new UrlEntry( 'https://example.com/post-1/' );
		$url_entry2 = new UrlEntry( 'https://example.com/post-2/' );

		$url_set1 = new UrlSet( array( $url_entry1 ) );
		$url_set2 = new UrlSet( array( $url_entry1, $url_entry2 ) );

		$this->assertFalse( $url_set1->equals( $url_set2 ) );
	}

	/**
	 * Test equals method with different order.
	 */
	public function test_equals_with_different_order(): void {
		$url_entry1 = new UrlEntry( 'https://example.com/post-1/' );
		$url_entry2 = new UrlEntry( 'https://example.com/post-2/' );

		$url_set1 = new UrlSet( array( $url_entry1, $url_entry2 ) );
		$url_set2 = new UrlSet( array( $url_entry2, $url_entry1 ) );

		$this->assertTrue( $url_set1->equals( $url_set2 ) );
	}

	/**
	 * Test validation with invalid entry type.
	 */
	public function test_validation_with_invalid_entry_type(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'All entries must be UrlEntry instances.' );

		new UrlSet( array( 'not-a-url-entry' ) );
	}

	/**
	 * Test validation with too many entries.
	 */
	public function test_validation_with_too_many_entries(): void {
		$entries = array();
		for ( $i = 0; $i < 50001; $i++ ) {
			$entries[] = new UrlEntry( "https://example.com/post-{$i}/" );
		}

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Cannot create URL set: exceeds maximum of 50000 entries.' );

		new UrlSet( $entries );
	}

	/**
	 * Test validation with custom max entries limit.
	 */
	public function test_validation_with_custom_max_entries(): void {
		$entries = array();
		for ( $i = 0; $i < 6; $i++ ) {
			$entries[] = new UrlEntry( "https://example.com/post-{$i}/" );
		}

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Cannot create URL set: exceeds maximum of 5 entries.' );

		new UrlSet( $entries, 5 );
	}

	/**
	 * Test validation when max entries exceeds protocol limit.
	 */
	public function test_validation_when_max_entries_exceeds_protocol_limit(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Maximum entries cannot exceed the sitemap protocol limit of 50000.' );

		$url_entry = new UrlEntry( 'https://example.com/my-post/' );
		new UrlSet( array( $url_entry ), 50001 );
	}

	/**
	 * Test adding entry when set is full.
	 */
	public function test_add_entry_when_full(): void {
		$entries = array();
		for ( $i = 0; $i < 50000; $i++ ) {
			$entries[] = new UrlEntry( "https://example.com/post-{$i}/" );
		}
		$url_set = new UrlSet( $entries );

		$new_entry = new UrlEntry( 'https://example.com/new-post/' );

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Cannot add entry: sitemap already contains the maximum of 50000 entries.' );

		$url_set->add( $new_entry );
	}

	/**
	 * Test adding entry when set is full with custom limit.
	 */
	public function test_add_entry_when_full_with_custom_limit(): void {
		$entries = array();
		for ( $i = 0; $i < 3; $i++ ) {
			$entries[] = new UrlEntry( "https://example.com/post-{$i}/" );
		}
		$url_set = new UrlSet( $entries, 3 );

		$new_entry = new UrlEntry( 'https://example.com/new-post/' );

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Cannot add entry: sitemap already contains the maximum of 3 entries.' );

		$url_set->add( $new_entry );
	}

	/**
	 * Test count method.
	 */
	public function test_count(): void {
		$url_set = new UrlSet();
		$this->assertEquals( 0, count( $url_set ) );

		$url_entry = new UrlEntry( 'https://example.com/my-post/' );
		$url_set->add( $url_entry );
		$this->assertEquals( 1, count( $url_set ) );
	}

	/**
	 * Test is full method.
	 */
	public function test_is_full(): void {
		$url_set = new UrlSet();
		$this->assertFalse( $url_set->is_full() );

		// Add entries up to the limit
		for ( $i = 0; $i < 50000; $i++ ) {
			$url_set->add( new UrlEntry( "https://example.com/post-{$i}/" ) );
		}

		$this->assertTrue( $url_set->is_full() );
		$this->assertEquals( 50000, $url_set->count() );
	}

	/**
	 * Test is full method with custom limit.
	 */
	public function test_is_full_with_custom_limit(): void {
		$url_set = new UrlSet( array(), 5 );
		$this->assertFalse( $url_set->is_full() );

		// Add entries up to the custom limit
		for ( $i = 0; $i < 5; $i++ ) {
			$url_set->add( new UrlEntry( "https://example.com/post-{$i}/" ) );
		}

		$this->assertTrue( $url_set->is_full() );
		$this->assertEquals( 5, $url_set->count() );
	}
}
