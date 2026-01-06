<?php
/**
 * Tests for UrlSet Value Object.
 *
 * @package Automattic\MSM_Sitemap\Tests\Unit\Domain\ValueObjects
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Unit\Domain\ValueObjects;

use Automattic\MSM_Sitemap\Domain\ValueObjects\UrlEntry;
use Automattic\MSM_Sitemap\Domain\ValueObjects\UrlSet;
use Automattic\MSM_Sitemap\Tests\Unit\TestCase;
use InvalidArgumentException;

/**
 * UrlSet Value Object test case.
 *
 * Tests collection behavior including:
 * - Maximum entries limit (50,000 default, configurable)
 * - Add/remove operations
 * - Collection state (empty, full)
 * - Countable interface
 * - Entry validation
 */
class UrlSetTest extends TestCase {

	/**
	 * Test that empty UrlSet can be created.
	 */
	public function test_can_create_empty_urlset(): void {
		$urlset = new UrlSet();

		$this->assertCount( 0, $urlset );
		$this->assertTrue( $urlset->is_empty() );
		$this->assertFalse( $urlset->is_full() );
	}

	/**
	 * Test that UrlSet can be created with initial entries.
	 */
	public function test_can_create_urlset_with_initial_entries(): void {
		$entries = array(
			new UrlEntry( 'https://example.com/page1' ),
			new UrlEntry( 'https://example.com/page2' ),
		);

		$urlset = new UrlSet( $entries );

		$this->assertCount( 2, $urlset );
		$this->assertFalse( $urlset->is_empty() );
	}

	// ===========================================
	// Maximum entries limit tests
	// ===========================================

	/**
	 * Test that default max entries is 50,000.
	 */
	public function test_default_max_entries_is_50000(): void {
		$this->assertSame( 50000, UrlSet::DEFAULT_MAX_ENTRIES );
	}

	/**
	 * Test that custom max entries can be set below default.
	 */
	public function test_can_set_custom_max_entries_below_default(): void {
		$urlset = new UrlSet( array(), 100 );

		// Fill to capacity.
		for ( $i = 0; $i < 100; $i++ ) {
			$urlset->add( new UrlEntry( "https://example.com/page{$i}" ) );
		}

		$this->assertTrue( $urlset->is_full() );
		$this->assertCount( 100, $urlset );
	}

	/**
	 * Test that max entries cannot exceed 50,000 (protocol limit).
	 */
	public function test_max_entries_cannot_exceed_protocol_limit(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Maximum entries cannot exceed the sitemap protocol limit of 50000.' );

		new UrlSet( array(), 50001 );
	}

	/**
	 * Test that creating a UrlSet with too many initial entries throws exception.
	 */
	public function test_rejects_initial_entries_exceeding_max(): void {
		$entries = array();
		for ( $i = 0; $i < 101; $i++ ) {
			$entries[] = new UrlEntry( "https://example.com/page{$i}" );
		}

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Cannot create URL set: exceeds maximum of 100 entries.' );

		new UrlSet( $entries, 100 );
	}

	// ===========================================
	// Add operation tests
	// ===========================================

	/**
	 * Test that entries can be added.
	 */
	public function test_can_add_entry(): void {
		$urlset = new UrlSet();
		$entry  = new UrlEntry( 'https://example.com/page' );

		$urlset->add( $entry );

		$this->assertCount( 1, $urlset );
		$this->assertTrue( $urlset->contains( $entry ) );
	}

	/**
	 * Test that adding to a full UrlSet throws exception.
	 */
	public function test_add_to_full_urlset_throws_exception(): void {
		$urlset = new UrlSet( array(), 2 );
		$urlset->add( new UrlEntry( 'https://example.com/page1' ) );
		$urlset->add( new UrlEntry( 'https://example.com/page2' ) );

		$this->assertTrue( $urlset->is_full() );

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Cannot add entry: sitemap already contains the maximum of 2 entries.' );

		$urlset->add( new UrlEntry( 'https://example.com/page3' ) );
	}

	// ===========================================
	// Remove operation tests
	// ===========================================

	/**
	 * Test that entries can be removed.
	 */
	public function test_can_remove_entry(): void {
		$entry  = new UrlEntry( 'https://example.com/page' );
		$urlset = new UrlSet( array( $entry ) );

		$result = $urlset->remove( $entry );

		$this->assertTrue( $result );
		$this->assertCount( 0, $urlset );
		$this->assertFalse( $urlset->contains( $entry ) );
	}

	/**
	 * Test that removing non-existent entry returns false.
	 */
	public function test_remove_nonexistent_entry_returns_false(): void {
		$entry1 = new UrlEntry( 'https://example.com/page1' );
		$entry2 = new UrlEntry( 'https://example.com/page2' );
		$urlset = new UrlSet( array( $entry1 ) );

		$result = $urlset->remove( $entry2 );

		$this->assertFalse( $result );
		$this->assertCount( 1, $urlset );
	}

	/**
	 * Test that array is re-indexed after removal.
	 */
	public function test_array_is_reindexed_after_removal(): void {
		$entry1 = new UrlEntry( 'https://example.com/page1' );
		$entry2 = new UrlEntry( 'https://example.com/page2' );
		$entry3 = new UrlEntry( 'https://example.com/page3' );
		$urlset = new UrlSet( array( $entry1, $entry2, $entry3 ) );

		$urlset->remove( $entry2 );

		$entries = $urlset->get_entries();
		$this->assertSame( array( 0, 1 ), array_keys( $entries ) );
	}

	// ===========================================
	// Collection state tests
	// ===========================================

	/**
	 * Test that is_empty returns true for empty set.
	 */
	public function test_is_empty_returns_true_for_empty_set(): void {
		$urlset = new UrlSet();

		$this->assertTrue( $urlset->is_empty() );
	}

	/**
	 * Test that is_empty returns false for non-empty set.
	 */
	public function test_is_empty_returns_false_for_non_empty_set(): void {
		$urlset = new UrlSet( array( new UrlEntry( 'https://example.com/page' ) ) );

		$this->assertFalse( $urlset->is_empty() );
	}

	/**
	 * Test that is_full returns false when not at capacity.
	 */
	public function test_is_full_returns_false_when_not_at_capacity(): void {
		$urlset = new UrlSet( array(), 100 );
		$urlset->add( new UrlEntry( 'https://example.com/page' ) );

		$this->assertFalse( $urlset->is_full() );
	}

	/**
	 * Test that is_full returns true when at capacity.
	 */
	public function test_is_full_returns_true_at_capacity(): void {
		$urlset = new UrlSet( array(), 1 );
		$urlset->add( new UrlEntry( 'https://example.com/page' ) );

		$this->assertTrue( $urlset->is_full() );
	}

	// ===========================================
	// Contains tests
	// ===========================================

	/**
	 * Test that contains returns true for existing entry.
	 */
	public function test_contains_returns_true_for_existing_entry(): void {
		$entry  = new UrlEntry( 'https://example.com/page' );
		$urlset = new UrlSet( array( $entry ) );

		$this->assertTrue( $urlset->contains( $entry ) );
	}

	/**
	 * Test that contains returns false for non-existing entry.
	 */
	public function test_contains_returns_false_for_non_existing_entry(): void {
		$entry1 = new UrlEntry( 'https://example.com/page1' );
		$entry2 = new UrlEntry( 'https://example.com/page2' );
		$urlset = new UrlSet( array( $entry1 ) );

		$this->assertFalse( $urlset->contains( $entry2 ) );
	}

	/**
	 * Test that contains uses strict comparison (same instance).
	 */
	public function test_contains_uses_strict_comparison(): void {
		$entry1 = new UrlEntry( 'https://example.com/page' );
		$entry2 = new UrlEntry( 'https://example.com/page' ); // Same URL, different instance.
		$urlset = new UrlSet( array( $entry1 ) );

		// Different instances with same data are not considered the same.
		$this->assertFalse( $urlset->contains( $entry2 ) );
	}

	// ===========================================
	// Validation tests
	// ===========================================

	/**
	 * Test that non-UrlEntry objects in initial array throws exception.
	 */
	public function test_rejects_non_urlentry_in_initial_entries(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'All entries must be UrlEntry instances.' );

		// @phpstan-ignore-next-line
		new UrlSet( array( 'not-a-url-entry' ) );
	}

	// ===========================================
	// Countable interface tests
	// ===========================================

	/**
	 * Test that count returns correct number.
	 */
	public function test_count_returns_correct_number(): void {
		$entries = array(
			new UrlEntry( 'https://example.com/page1' ),
			new UrlEntry( 'https://example.com/page2' ),
			new UrlEntry( 'https://example.com/page3' ),
		);
		$urlset  = new UrlSet( $entries );

		$this->assertCount( 3, $urlset );
		$this->assertSame( 3, $urlset->count() );
	}

	// ===========================================
	// to_array tests
	// ===========================================

	/**
	 * Test that to_array returns array of entry arrays.
	 */
	public function test_to_array_returns_entry_arrays(): void {
		$entries = array(
			new UrlEntry( 'https://example.com/page1' ),
			new UrlEntry( 'https://example.com/page2', '2024-01-15' ),
		);
		$urlset  = new UrlSet( $entries );

		$result = $urlset->to_array();

		$this->assertCount( 2, $result );
		$this->assertSame( array( 'loc' => 'https://example.com/page1' ), $result[0] );
		$this->assertSame(
			array(
				'loc'     => 'https://example.com/page2',
				'lastmod' => '2024-01-15',
			),
			$result[1]
		);
	}

	// ===========================================
	// Equality tests
	// ===========================================

	/**
	 * Test that equals returns true for sets with same entries.
	 */
	public function test_equals_returns_true_for_same_entries(): void {
		$entry1 = new UrlEntry( 'https://example.com/page1' );
		$entry2 = new UrlEntry( 'https://example.com/page2' );

		$urlset1 = new UrlSet( array( $entry1, $entry2 ) );
		$urlset2 = new UrlSet( array( $entry1, $entry2 ) );

		$this->assertTrue( $urlset1->equals( $urlset2 ) );
	}

	/**
	 * Test that equals returns true regardless of order.
	 */
	public function test_equals_is_order_independent(): void {
		$entry1 = new UrlEntry( 'https://example.com/page1' );
		$entry2 = new UrlEntry( 'https://example.com/page2' );

		$urlset1 = new UrlSet( array( $entry1, $entry2 ) );
		$urlset2 = new UrlSet( array( $entry2, $entry1 ) );

		$this->assertTrue( $urlset1->equals( $urlset2 ) );
	}

	/**
	 * Test that equals returns false for different counts.
	 */
	public function test_equals_returns_false_for_different_counts(): void {
		$entry1 = new UrlEntry( 'https://example.com/page1' );
		$entry2 = new UrlEntry( 'https://example.com/page2' );

		$urlset1 = new UrlSet( array( $entry1 ) );
		$urlset2 = new UrlSet( array( $entry1, $entry2 ) );

		$this->assertFalse( $urlset1->equals( $urlset2 ) );
	}

	/**
	 * Test that equals returns false for different entries.
	 */
	public function test_equals_returns_false_for_different_entries(): void {
		$entry1 = new UrlEntry( 'https://example.com/page1' );
		$entry2 = new UrlEntry( 'https://example.com/page2' );
		$entry3 = new UrlEntry( 'https://example.com/page3' );

		$urlset1 = new UrlSet( array( $entry1, $entry2 ) );
		$urlset2 = new UrlSet( array( $entry1, $entry3 ) );

		$this->assertFalse( $urlset1->equals( $urlset2 ) );
	}

	/**
	 * Test that two empty sets are equal.
	 */
	public function test_empty_sets_are_equal(): void {
		$urlset1 = new UrlSet();
		$urlset2 = new UrlSet();

		$this->assertTrue( $urlset1->equals( $urlset2 ) );
	}
}
