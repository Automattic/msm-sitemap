<?php
/**
 * Tests for SitemapIndexCollection Value Object.
 *
 * @package Automattic\MSM_Sitemap\Tests\Unit\Domain\ValueObjects
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Unit\Domain\ValueObjects;

use Automattic\MSM_Sitemap\Domain\ValueObjects\SitemapIndexCollection;
use Automattic\MSM_Sitemap\Domain\ValueObjects\SitemapIndexEntry;
use Automattic\MSM_Sitemap\Tests\Unit\TestCase;
use InvalidArgumentException;

/**
 * SitemapIndexCollection Value Object test case.
 *
 * Tests collection behavior including:
 * - Maximum entries limit (50,000 default, configurable)
 * - Add/remove operations
 * - Collection state (empty, full)
 * - Countable interface
 * - Entry validation
 */
class SitemapIndexCollectionTest extends TestCase {

	/**
	 * Test that empty collection can be created.
	 */
	public function test_can_create_empty_collection(): void {
		$collection = new SitemapIndexCollection();

		$this->assertCount( 0, $collection );
		$this->assertTrue( $collection->is_empty() );
		$this->assertFalse( $collection->is_full() );
	}

	/**
	 * Test that collection can be created with initial entries.
	 */
	public function test_can_create_collection_with_initial_entries(): void {
		$entries = array(
			new SitemapIndexEntry( 'https://example.com/sitemap1.xml' ),
			new SitemapIndexEntry( 'https://example.com/sitemap2.xml' ),
		);

		$collection = new SitemapIndexCollection( $entries );

		$this->assertCount( 2, $collection );
		$this->assertFalse( $collection->is_empty() );
	}

	// ===========================================
	// Maximum entries limit tests
	// ===========================================

	/**
	 * Test that default max entries is 50,000.
	 */
	public function test_default_max_entries_is_50000(): void {
		$this->assertSame( 50000, SitemapIndexCollection::DEFAULT_MAX_ENTRIES );
	}

	/**
	 * Test that custom max entries can be set below default.
	 */
	public function test_can_set_custom_max_entries_below_default(): void {
		$collection = new SitemapIndexCollection( array(), 100 );

		// Fill to capacity.
		for ( $i = 0; $i < 100; $i++ ) {
			$collection->add( new SitemapIndexEntry( "https://example.com/sitemap{$i}.xml" ) );
		}

		$this->assertTrue( $collection->is_full() );
		$this->assertCount( 100, $collection );
	}

	/**
	 * Test that max entries cannot exceed 50,000 (protocol limit).
	 */
	public function test_max_entries_cannot_exceed_protocol_limit(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Maximum entries cannot exceed the sitemap index protocol limit of 50000.' );

		new SitemapIndexCollection( array(), 50001 );
	}

	/**
	 * Test that creating collection with too many initial entries throws exception.
	 */
	public function test_rejects_initial_entries_exceeding_max(): void {
		$entries = array();
		for ( $i = 0; $i < 101; $i++ ) {
			$entries[] = new SitemapIndexEntry( "https://example.com/sitemap{$i}.xml" );
		}

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Cannot create sitemap index collection: exceeds maximum of 100 entries.' );

		new SitemapIndexCollection( $entries, 100 );
	}

	// ===========================================
	// Add operation tests
	// ===========================================

	/**
	 * Test that entries can be added.
	 */
	public function test_can_add_entry(): void {
		$collection = new SitemapIndexCollection();
		$entry      = new SitemapIndexEntry( 'https://example.com/sitemap.xml' );

		$collection->add( $entry );

		$this->assertCount( 1, $collection );
		$this->assertTrue( $collection->contains( $entry ) );
	}

	/**
	 * Test that adding to a full collection throws exception.
	 */
	public function test_add_to_full_collection_throws_exception(): void {
		$collection = new SitemapIndexCollection( array(), 2 );
		$collection->add( new SitemapIndexEntry( 'https://example.com/sitemap1.xml' ) );
		$collection->add( new SitemapIndexEntry( 'https://example.com/sitemap2.xml' ) );

		$this->assertTrue( $collection->is_full() );

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Cannot add entry: sitemap index already contains the maximum of 2 entries.' );

		$collection->add( new SitemapIndexEntry( 'https://example.com/sitemap3.xml' ) );
	}

	// ===========================================
	// Remove operation tests
	// ===========================================

	/**
	 * Test that entries can be removed.
	 */
	public function test_can_remove_entry(): void {
		$entry      = new SitemapIndexEntry( 'https://example.com/sitemap.xml' );
		$collection = new SitemapIndexCollection( array( $entry ) );

		$collection->remove( $entry );

		$this->assertCount( 0, $collection );
		$this->assertFalse( $collection->contains( $entry ) );
	}

	/**
	 * Test that removing non-existent entry does nothing (no return value).
	 */
	public function test_remove_nonexistent_entry_does_nothing(): void {
		$entry1     = new SitemapIndexEntry( 'https://example.com/sitemap1.xml' );
		$entry2     = new SitemapIndexEntry( 'https://example.com/sitemap2.xml' );
		$collection = new SitemapIndexCollection( array( $entry1 ) );

		// Remove a different entry - no exception, just silently does nothing.
		$collection->remove( $entry2 );

		$this->assertCount( 1, $collection );
	}

	/**
	 * Test that array is re-indexed after removal.
	 */
	public function test_array_is_reindexed_after_removal(): void {
		$entry1     = new SitemapIndexEntry( 'https://example.com/sitemap1.xml' );
		$entry2     = new SitemapIndexEntry( 'https://example.com/sitemap2.xml' );
		$entry3     = new SitemapIndexEntry( 'https://example.com/sitemap3.xml' );
		$collection = new SitemapIndexCollection( array( $entry1, $entry2, $entry3 ) );

		$collection->remove( $entry2 );

		$entries = $collection->get_entries();
		$this->assertSame( array( 0, 1 ), array_keys( $entries ) );
	}

	// ===========================================
	// Collection state tests
	// ===========================================

	/**
	 * Test that is_empty returns true for empty collection.
	 */
	public function test_is_empty_returns_true_for_empty_collection(): void {
		$collection = new SitemapIndexCollection();

		$this->assertTrue( $collection->is_empty() );
	}

	/**
	 * Test that is_empty returns false for non-empty collection.
	 */
	public function test_is_empty_returns_false_for_non_empty_collection(): void {
		$collection = new SitemapIndexCollection(
			array( new SitemapIndexEntry( 'https://example.com/sitemap.xml' ) )
		);

		$this->assertFalse( $collection->is_empty() );
	}

	/**
	 * Test that is_full returns false when not at capacity.
	 */
	public function test_is_full_returns_false_when_not_at_capacity(): void {
		$collection = new SitemapIndexCollection( array(), 100 );
		$collection->add( new SitemapIndexEntry( 'https://example.com/sitemap.xml' ) );

		$this->assertFalse( $collection->is_full() );
	}

	/**
	 * Test that is_full returns true when at capacity.
	 */
	public function test_is_full_returns_true_at_capacity(): void {
		$collection = new SitemapIndexCollection( array(), 1 );
		$collection->add( new SitemapIndexEntry( 'https://example.com/sitemap.xml' ) );

		$this->assertTrue( $collection->is_full() );
	}

	// ===========================================
	// Contains tests
	// ===========================================

	/**
	 * Test that contains returns true for existing entry.
	 */
	public function test_contains_returns_true_for_existing_entry(): void {
		$entry      = new SitemapIndexEntry( 'https://example.com/sitemap.xml' );
		$collection = new SitemapIndexCollection( array( $entry ) );

		$this->assertTrue( $collection->contains( $entry ) );
	}

	/**
	 * Test that contains returns false for non-existing entry.
	 */
	public function test_contains_returns_false_for_non_existing_entry(): void {
		$entry1     = new SitemapIndexEntry( 'https://example.com/sitemap1.xml' );
		$entry2     = new SitemapIndexEntry( 'https://example.com/sitemap2.xml' );
		$collection = new SitemapIndexCollection( array( $entry1 ) );

		$this->assertFalse( $collection->contains( $entry2 ) );
	}

	/**
	 * Test that contains uses strict comparison (same instance).
	 */
	public function test_contains_uses_strict_comparison(): void {
		$entry1     = new SitemapIndexEntry( 'https://example.com/sitemap.xml' );
		$entry2     = new SitemapIndexEntry( 'https://example.com/sitemap.xml' ); // Same URL, different instance.
		$collection = new SitemapIndexCollection( array( $entry1 ) );

		// Different instances with same data are not considered the same.
		$this->assertFalse( $collection->contains( $entry2 ) );
	}

	// ===========================================
	// Validation tests
	// ===========================================

	/**
	 * Test that non-SitemapIndexEntry objects in initial array throws exception.
	 */
	public function test_rejects_non_sitemapindexentry_in_initial_entries(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'All entries must be SitemapIndexEntry instances.' );

		// @phpstan-ignore-next-line
		new SitemapIndexCollection( array( 'not-an-entry' ) );
	}

	// ===========================================
	// Countable interface tests
	// ===========================================

	/**
	 * Test that count returns correct number.
	 */
	public function test_count_returns_correct_number(): void {
		$entries = array(
			new SitemapIndexEntry( 'https://example.com/sitemap1.xml' ),
			new SitemapIndexEntry( 'https://example.com/sitemap2.xml' ),
			new SitemapIndexEntry( 'https://example.com/sitemap3.xml' ),
		);
		$collection = new SitemapIndexCollection( $entries );

		$this->assertCount( 3, $collection );
		$this->assertSame( 3, $collection->count() );
	}

	// ===========================================
	// to_array tests
	// ===========================================

	/**
	 * Test that to_array returns array of entries (not arrays of entry data).
	 *
	 * Note: SitemapIndexCollection.to_array() returns the entries array directly,
	 * not converted to arrays like UrlSet.to_array().
	 */
	public function test_to_array_returns_entries(): void {
		$entry1 = new SitemapIndexEntry( 'https://example.com/sitemap1.xml' );
		$entry2 = new SitemapIndexEntry( 'https://example.com/sitemap2.xml' );
		$collection = new SitemapIndexCollection( array( $entry1, $entry2 ) );

		$result = $collection->to_array();

		$this->assertCount( 2, $result );
		$this->assertSame( $entry1, $result[0] );
		$this->assertSame( $entry2, $result[1] );
	}

	// ===========================================
	// Equality tests
	// ===========================================

	/**
	 * Test that equals returns true for collections with same entries.
	 */
	public function test_equals_returns_true_for_same_entries(): void {
		$entry1 = new SitemapIndexEntry( 'https://example.com/sitemap1.xml' );
		$entry2 = new SitemapIndexEntry( 'https://example.com/sitemap2.xml' );

		$collection1 = new SitemapIndexCollection( array( $entry1, $entry2 ) );
		$collection2 = new SitemapIndexCollection( array( $entry1, $entry2 ) );

		$this->assertTrue( $collection1->equals( $collection2 ) );
	}

	/**
	 * Test that equals returns true regardless of order.
	 */
	public function test_equals_is_order_independent(): void {
		$entry1 = new SitemapIndexEntry( 'https://example.com/sitemap1.xml' );
		$entry2 = new SitemapIndexEntry( 'https://example.com/sitemap2.xml' );

		$collection1 = new SitemapIndexCollection( array( $entry1, $entry2 ) );
		$collection2 = new SitemapIndexCollection( array( $entry2, $entry1 ) );

		$this->assertTrue( $collection1->equals( $collection2 ) );
	}

	/**
	 * Test that equals returns false for different counts.
	 */
	public function test_equals_returns_false_for_different_counts(): void {
		$entry1 = new SitemapIndexEntry( 'https://example.com/sitemap1.xml' );
		$entry2 = new SitemapIndexEntry( 'https://example.com/sitemap2.xml' );

		$collection1 = new SitemapIndexCollection( array( $entry1 ) );
		$collection2 = new SitemapIndexCollection( array( $entry1, $entry2 ) );

		$this->assertFalse( $collection1->equals( $collection2 ) );
	}

	/**
	 * Test that equals returns false for different entries.
	 */
	public function test_equals_returns_false_for_different_entries(): void {
		$entry1 = new SitemapIndexEntry( 'https://example.com/sitemap1.xml' );
		$entry2 = new SitemapIndexEntry( 'https://example.com/sitemap2.xml' );
		$entry3 = new SitemapIndexEntry( 'https://example.com/sitemap3.xml' );

		$collection1 = new SitemapIndexCollection( array( $entry1, $entry2 ) );
		$collection2 = new SitemapIndexCollection( array( $entry1, $entry3 ) );

		$this->assertFalse( $collection1->equals( $collection2 ) );
	}

	/**
	 * Test that two empty collections are equal.
	 */
	public function test_empty_collections_are_equal(): void {
		$collection1 = new SitemapIndexCollection();
		$collection2 = new SitemapIndexCollection();

		$this->assertTrue( $collection1->equals( $collection2 ) );
	}
}
