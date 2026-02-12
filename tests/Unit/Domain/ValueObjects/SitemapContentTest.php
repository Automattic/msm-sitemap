<?php
/**
 * Tests for SitemapContent Value Object.
 *
 * @package Automattic\MSM_Sitemap\Tests\Unit\Domain\ValueObjects
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Unit\Domain\ValueObjects;

use Automattic\MSM_Sitemap\Domain\ValueObjects\SitemapContent;
use Automattic\MSM_Sitemap\Domain\ValueObjects\UrlEntry;
use Automattic\MSM_Sitemap\Tests\Generators\ValidUrlGenerator;
use Automattic\MSM_Sitemap\Tests\Unit\TestCase;
use Eris\Generators;
use Eris\TestTrait;
use InvalidArgumentException;

/**
 * SitemapContent Value Object test case.
 *
 * Tests:
 * - Immutability (add/remove return new instances)
 * - Entry limits enforcement
 * - Collection behavior
 * - contains() uses equality comparison (not strict identity)
 */
class SitemapContentTest extends TestCase {

	use TestTrait;

	/**
	 * Test that empty content can be created.
	 */
	public function test_can_create_empty_content(): void {
		$content = new SitemapContent();

		$this->assertCount( 0, $content );
		$this->assertTrue( $content->is_empty() );
		$this->assertFalse( $content->is_full() );
	}

	/**
	 * Test that content can be created with initial entries.
	 */
	public function test_can_create_content_with_initial_entries(): void {
		$entries = array(
			new UrlEntry( 'https://example.com/page1' ),
			new UrlEntry( 'https://example.com/page2' ),
		);

		$content = new SitemapContent( $entries );

		$this->assertCount( 2, $content );
		$this->assertFalse( $content->is_empty() );
	}

	// ===========================================
	// Maximum entries limit tests
	// ===========================================

	/**
	 * Test that default max entries is 50,000.
	 */
	public function test_default_max_entries_is_50000(): void {
		$this->assertSame( 50000, SitemapContent::DEFAULT_MAX_ENTRIES );
	}

	/**
	 * Test that max entries cannot exceed protocol limit.
	 */
	public function test_max_entries_cannot_exceed_protocol_limit(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Max entries (50001) cannot exceed the sitemap protocol limit (50000)' );

		new SitemapContent( array(), 50001 );
	}

	/**
	 * Test that entries exceeding max_entries are silently truncated.
	 *
	 * Unlike UrlSet, SitemapContent silently truncates instead of throwing.
	 */
	public function test_initial_entries_exceeding_max_are_truncated(): void {
		$entries = array();
		for ( $i = 0; $i < 10; $i++ ) {
			$entries[] = new UrlEntry( "https://example.com/page{$i}" );
		}

		// Create with max of 5 - should truncate to 5.
		$content = new SitemapContent( $entries, 5 );

		$this->assertCount( 5, $content );
	}

	// ===========================================
	// Immutability tests - add()
	// ===========================================

	/**
	 * Test that add returns a new instance.
	 */
	public function test_add_returns_new_instance(): void {
		$content  = new SitemapContent();
		$entry    = new UrlEntry( 'https://example.com/page' );
		$newContent = $content->add( $entry );

		$this->assertNotSame( $content, $newContent );
		$this->assertCount( 0, $content ); // Original unchanged.
		$this->assertCount( 1, $newContent );
	}

	/**
	 * Test that add to full content returns same instance.
	 *
	 * Unlike UrlSet which throws, SitemapContent returns the same instance.
	 */
	public function test_add_to_full_content_returns_same_instance(): void {
		$content = new SitemapContent( array(), 1 );
		$content = $content->add( new UrlEntry( 'https://example.com/page1' ) );

		$this->assertTrue( $content->is_full() );

		$newContent = $content->add( new UrlEntry( 'https://example.com/page2' ) );

		// Returns same instance when full.
		$this->assertSame( $content, $newContent );
		$this->assertCount( 1, $newContent );
	}

	// ===========================================
	// Immutability tests - remove()
	// ===========================================

	/**
	 * Test that remove returns a new instance when entry is found.
	 */
	public function test_remove_returns_new_instance_when_found(): void {
		$entry   = new UrlEntry( 'https://example.com/page' );
		$content = new SitemapContent( array( $entry ) );

		$newContent = $content->remove( $entry );

		$this->assertNotSame( $content, $newContent );
		$this->assertCount( 1, $content ); // Original unchanged.
		$this->assertCount( 0, $newContent );
	}

	/**
	 * Test that remove returns same instance when entry not found.
	 */
	public function test_remove_returns_same_instance_when_not_found(): void {
		$entry1  = new UrlEntry( 'https://example.com/page1' );
		$entry2  = new UrlEntry( 'https://example.com/page2' );
		$content = new SitemapContent( array( $entry1 ) );

		$newContent = $content->remove( $entry2 );

		// Returns same instance when not found.
		$this->assertSame( $content, $newContent );
	}

	/**
	 * Test that remove uses equality comparison, not identity.
	 *
	 * This is a key difference from UrlSet - SitemapContent uses equals() method.
	 */
	public function test_remove_uses_equality_comparison(): void {
		$entry1  = new UrlEntry( 'https://example.com/page', '2024-01-15', 'weekly' );
		$entry2  = new UrlEntry( 'https://example.com/page', '2024-01-15', 'weekly' ); // Same values, different instance.
		$content = new SitemapContent( array( $entry1 ) );

		$newContent = $content->remove( $entry2 );

		// Should find and remove because it uses equals() comparison.
		$this->assertNotSame( $content, $newContent );
		$this->assertCount( 0, $newContent );
	}

	// ===========================================
	// contains() tests
	// ===========================================

	/**
	 * Test contains uses equality comparison.
	 */
	public function test_contains_uses_equality_comparison(): void {
		$entry1  = new UrlEntry( 'https://example.com/page', '2024-01-15' );
		$entry2  = new UrlEntry( 'https://example.com/page', '2024-01-15' ); // Same values, different instance.
		$content = new SitemapContent( array( $entry1 ) );

		// Uses equals(), so different instance with same values should be found.
		$this->assertTrue( $content->contains( $entry2 ) );
	}

	/**
	 * Test contains returns false for different entry.
	 */
	public function test_contains_returns_false_for_different_entry(): void {
		$entry1  = new UrlEntry( 'https://example.com/page1' );
		$entry2  = new UrlEntry( 'https://example.com/page2' );
		$content = new SitemapContent( array( $entry1 ) );

		$this->assertFalse( $content->contains( $entry2 ) );
	}

	// ===========================================
	// Collection state tests
	// ===========================================

	/**
	 * Test that is_empty returns true for empty content.
	 */
	public function test_is_empty_returns_true_for_empty_content(): void {
		$content = new SitemapContent();

		$this->assertTrue( $content->is_empty() );
	}

	/**
	 * Test that is_empty returns false for non-empty content.
	 */
	public function test_is_empty_returns_false_for_non_empty_content(): void {
		$content = new SitemapContent( array( new UrlEntry( 'https://example.com/page' ) ) );

		$this->assertFalse( $content->is_empty() );
	}

	/**
	 * Test that is_full returns true when at capacity.
	 */
	public function test_is_full_returns_true_at_capacity(): void {
		$content = new SitemapContent( array(), 1 );
		$content = $content->add( new UrlEntry( 'https://example.com/page' ) );

		$this->assertTrue( $content->is_full() );
	}

	/**
	 * Test that is_full returns false when not at capacity.
	 */
	public function test_is_full_returns_false_when_not_at_capacity(): void {
		$content = new SitemapContent( array(), 100 );
		$content = $content->add( new UrlEntry( 'https://example.com/page' ) );

		$this->assertFalse( $content->is_full() );
	}

	// ===========================================
	// Validation tests
	// ===========================================

	/**
	 * Test that non-UrlEntry objects throw exception.
	 */
	public function test_rejects_non_urlentry_in_initial_entries(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'All entries must be UrlEntry objects, got string' );

		// @phpstan-ignore-next-line
		new SitemapContent( array( 'not-an-entry' ) );
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
		$content = new SitemapContent( $entries );

		$result = $content->to_array();

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
	 * Test equals returns true for identical content.
	 */
	public function test_equals_returns_true_for_identical_content(): void {
		$entry1 = new UrlEntry( 'https://example.com/page1' );
		$entry2 = new UrlEntry( 'https://example.com/page2' );

		$content1 = new SitemapContent( array( $entry1, $entry2 ) );
		$content2 = new SitemapContent( array( $entry1, $entry2 ) );

		$this->assertTrue( $content1->equals( $content2 ) );
	}

	/**
	 * Test equals returns false for different counts.
	 */
	public function test_equals_returns_false_for_different_counts(): void {
		$entry = new UrlEntry( 'https://example.com/page' );

		$content1 = new SitemapContent( array( $entry ) );
		$content2 = new SitemapContent();

		$this->assertFalse( $content1->equals( $content2 ) );
	}

	/**
	 * Test equals is order-dependent.
	 *
	 * Unlike UrlSet.equals() which is order-independent, SitemapContent.equals()
	 * compares entries at each index.
	 */
	public function test_equals_is_order_dependent(): void {
		$entry1 = new UrlEntry( 'https://example.com/page1' );
		$entry2 = new UrlEntry( 'https://example.com/page2' );

		$content1 = new SitemapContent( array( $entry1, $entry2 ) );
		$content2 = new SitemapContent( array( $entry2, $entry1 ) ); // Different order.

		// Order matters in SitemapContent.equals().
		$this->assertFalse( $content1->equals( $content2 ) );
	}

	/**
	 * Test equals returns true for empty contents.
	 */
	public function test_equals_returns_true_for_empty_contents(): void {
		$content1 = new SitemapContent();
		$content2 = new SitemapContent();

		$this->assertTrue( $content1->equals( $content2 ) );
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
		$content = new SitemapContent( $entries );

		$this->assertCount( 3, $content );
		$this->assertSame( 3, $content->count() );
	}

	// ===========================================
	// Property-based tests (Eris)
	// ===========================================

	/**
	 * Property: add() returns a new instance (immutability).
	 *
	 * Adding an entry to SitemapContent must always return a different
	 * object, leaving the original unchanged. This guarantees immutability.
	 */
	public function test_property_add_returns_new_instance(): void {
		$this
			->forAll(
				new ValidUrlGenerator()
			)
			->then( function ( string $url ): void {
				$content    = new SitemapContent();
				$entry      = new UrlEntry( $url );
				$newContent = $content->add( $entry );

				$this->assertNotSame(
					$content,
					$newContent,
					'add() must return a new SitemapContent instance'
				);
				$this->assertCount( 0, $content, 'Original must remain empty' );
				$this->assertCount( 1, $newContent, 'New instance must have 1 entry' );
			} );
	}

	/**
	 * Property: add() increments count by exactly 1 (when not full).
	 *
	 * For any non-full SitemapContent, adding one entry should increase
	 * the count by exactly one.
	 */
	public function test_property_add_increments_count_by_one(): void {
		$this
			->forAll(
				Generators::choose( 0, 9 ),
				new ValidUrlGenerator()
			)
			->then( function ( int $initial_count, string $new_url ): void {
				// Build initial content with $initial_count entries.
				$entries = array();
				for ( $i = 0; $i < $initial_count; $i++ ) {
					$entries[] = new UrlEntry( "https://example.com/page{$i}" );
				}
				$content = new SitemapContent( $entries );
				$this->assertCount( $initial_count, $content );

				$entry      = new UrlEntry( $new_url );
				$newContent = $content->add( $entry );

				$this->assertSame(
					$initial_count + 1,
					$newContent->count(),
					sprintf(
						'Count should increment from %d to %d after add()',
						$initial_count,
						$initial_count + 1
					)
				);
			} );
	}

	/**
	 * Property: the original SitemapContent is unchanged after add().
	 *
	 * Since SitemapContent is immutable, the count of the original
	 * instance must remain the same after calling add().
	 */
	public function test_property_original_unchanged_after_add(): void {
		$this
			->forAll(
				Generators::choose( 0, 5 ),
				new ValidUrlGenerator()
			)
			->then( function ( int $initial_count, string $new_url ): void {
				$entries = array();
				for ( $i = 0; $i < $initial_count; $i++ ) {
					$entries[] = new UrlEntry( "https://example.com/page{$i}" );
				}
				$content       = new SitemapContent( $entries );
				$original_count = $content->count();

				$content->add( new UrlEntry( $new_url ) );

				$this->assertSame(
					$original_count,
					$content->count(),
					'Original SitemapContent must not be modified by add()'
				);
			} );
	}

	/**
	 * Property: reflexive equality holds for any SitemapContent.
	 *
	 * Every SitemapContent must be equal to itself.
	 */
	public function test_property_reflexive_equality(): void {
		$this
			->forAll(
				Generators::choose( 0, 5 )
			)
			->then( function ( int $count ): void {
				$entries = array();
				for ( $i = 0; $i < $count; $i++ ) {
					$entries[] = new UrlEntry( "https://example.com/page{$i}" );
				}
				$content = new SitemapContent( $entries );

				$this->assertTrue(
					$content->equals( $content ),
					'SitemapContent must be equal to itself'
				);
			} );
	}
}
