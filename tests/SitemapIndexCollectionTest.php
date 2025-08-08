<?php
/**
 * Sitemap Index Collection Test
 *
 * @package Automattic\MSM_Sitemap\Tests
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests;

use Automattic\MSM_Sitemap\Domain\ValueObjects\SitemapIndexCollection;
use Automattic\MSM_Sitemap\Domain\ValueObjects\SitemapIndexEntry;
use Automattic\MSM_Sitemap\Infrastructure\Factories\SitemapIndexEntryFactory;
use InvalidArgumentException;

/**
 * Test class for SitemapIndexCollection.
 */
class SitemapIndexCollectionTest extends TestCase {

	/**
	 * Test creating a valid sitemap index collection.
	 */
	public function test_create_valid_collection() {
		$entry1 = SitemapIndexEntryFactory::from_data( 'https://example.com/sitemap1.xml' );
		$entry2 = SitemapIndexEntryFactory::from_data( 'https://example.com/sitemap2.xml', '2023-01-01T00:00:00Z' );

		$collection = new SitemapIndexCollection( array( $entry1, $entry2 ) );

		$this->assertCount( 2, $collection );
		$this->assertFalse( $collection->is_empty() );
		$this->assertFalse( $collection->is_full() );
		$this->assertContains( $entry1, $collection->get_entries() );
		$this->assertContains( $entry2, $collection->get_entries() );
	}

	/**
	 * Test creating an empty collection.
	 */
	public function test_create_empty_collection() {
		$collection = new SitemapIndexCollection();

		$this->assertCount( 0, $collection );
		$this->assertTrue( $collection->is_empty() );
		$this->assertFalse( $collection->is_full() );
		$this->assertEmpty( $collection->get_entries() );
	}

	/**
	 * Test adding entries to collection.
	 */
	public function test_add_entries() {
		$collection = new SitemapIndexCollection();
		$entry1 = SitemapIndexEntryFactory::from_data( 'https://example.com/sitemap1.xml' );
		$entry2 = SitemapIndexEntryFactory::from_data( 'https://example.com/sitemap2.xml' );

		$collection->add( $entry1 );
		$this->assertCount( 1, $collection );
		$this->assertTrue( $collection->contains( $entry1 ) );

		$collection->add( $entry2 );
		$this->assertCount( 2, $collection );
		$this->assertTrue( $collection->contains( $entry2 ) );
	}

	/**
	 * Test removing entries from collection.
	 */
	public function test_remove_entries() {
		$entry1 = SitemapIndexEntryFactory::from_data( 'https://example.com/sitemap1.xml' );
		$entry2 = SitemapIndexEntryFactory::from_data( 'https://example.com/sitemap2.xml' );
		$collection = new SitemapIndexCollection( array( $entry1, $entry2 ) );

		$collection->remove( $entry1 );
		$this->assertCount( 1, $collection );
		$this->assertFalse( $collection->contains( $entry1 ) );
		$this->assertTrue( $collection->contains( $entry2 ) );

		$collection->remove( $entry2 );
		$this->assertCount( 0, $collection );
		$this->assertTrue( $collection->is_empty() );
	}

	/**
	 * Test collection validation with invalid entries.
	 */
	public function test_validation_with_invalid_entries() {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'All entries must be SitemapIndexEntry instances.' );

		new SitemapIndexCollection( array( 'invalid_entry' ) );
	}

	/**
	 * Test collection validation when exceeding max entries.
	 */
	public function test_validation_when_exceeding_max_entries() {
		$entries = array();
		for ( $i = 0; $i < 50001; $i++ ) {
			$entries[] = SitemapIndexEntryFactory::from_data( "https://example.com/sitemap{$i}.xml" );
		}

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Cannot create sitemap index collection: exceeds maximum of 50000 entries.' );

		new SitemapIndexCollection( $entries );
	}

	/**
	 * Test adding entry when collection is full.
	 */
	public function test_add_entry_when_full() {
		$entries = array();
		for ( $i = 0; $i < 50000; $i++ ) {
			$entries[] = SitemapIndexEntryFactory::from_data( "https://example.com/sitemap{$i}.xml" );
		}
		$collection = new SitemapIndexCollection( $entries );

		$new_entry = SitemapIndexEntryFactory::from_data( 'https://example.com/sitemap50000.xml' );

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Cannot add entry: sitemap index already contains the maximum of 50000 entries.' );

		$collection->add( $new_entry );
	}

	/**
	 * Test collection with custom max entries.
	 */
	public function test_collection_with_custom_max_entries() {
		$collection = new SitemapIndexCollection( array(), 10 );

		$this->assertFalse( $collection->is_full() );

		// Add 10 entries
		for ( $i = 0; $i < 10; $i++ ) {
			$entry = SitemapIndexEntryFactory::from_data( "https://example.com/sitemap{$i}.xml" );
			$collection->add( $entry );
		}

		$this->assertTrue( $collection->is_full() );
		$this->assertCount( 10, $collection );
	}

	/**
	 * Test validation when max entries exceeds protocol limit.
	 */
	public function test_validation_when_max_entries_exceeds_protocol_limit() {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Maximum entries cannot exceed the sitemap index protocol limit of 50000.' );

		new SitemapIndexCollection( array(), 50001 );
	}

	/**
	 * Test collection equality.
	 */
	public function test_collection_equality() {
		$entry1 = SitemapIndexEntryFactory::from_data( 'https://example.com/sitemap1.xml' );
		$entry2 = SitemapIndexEntryFactory::from_data( 'https://example.com/sitemap2.xml' );

		$collection1 = new SitemapIndexCollection( array( $entry1, $entry2 ) );
		$collection2 = new SitemapIndexCollection( array( $entry1, $entry2 ) );
		$collection3 = new SitemapIndexCollection( array( $entry1 ) );

		$this->assertTrue( $collection1->equals( $collection2 ) );
		$this->assertTrue( $collection2->equals( $collection1 ) );
		$this->assertFalse( $collection1->equals( $collection3 ) );
		$this->assertFalse( $collection3->equals( $collection1 ) );
	}

	/**
	 * Test collection to array conversion.
	 */
	public function test_to_array() {
		$entry1 = SitemapIndexEntryFactory::from_data( 'https://example.com/sitemap1.xml' );
		$entry2 = SitemapIndexEntryFactory::from_data( 'https://example.com/sitemap2.xml' );
		$collection = new SitemapIndexCollection( array( $entry1, $entry2 ) );

		$array = $collection->to_array();

		$this->assertIsArray( $array );
		$this->assertCount( 2, $array );
		$this->assertContains( $entry1, $array );
		$this->assertContains( $entry2, $array );
	}

	/**
	 * Test collection contains method.
	 */
	public function test_contains() {
		$entry1 = SitemapIndexEntryFactory::from_data( 'https://example.com/sitemap1.xml' );
		$entry2 = SitemapIndexEntryFactory::from_data( 'https://example.com/sitemap2.xml' );
		$entry3 = SitemapIndexEntryFactory::from_data( 'https://example.com/sitemap3.xml' );

		$collection = new SitemapIndexCollection( array( $entry1, $entry2 ) );

		$this->assertTrue( $collection->contains( $entry1 ) );
		$this->assertTrue( $collection->contains( $entry2 ) );
		$this->assertFalse( $collection->contains( $entry3 ) );
	}

	/**
	 * Test countable interface.
	 */
	public function test_countable_interface() {
		$entry1 = SitemapIndexEntryFactory::from_data( 'https://example.com/sitemap1.xml' );
		$entry2 = SitemapIndexEntryFactory::from_data( 'https://example.com/sitemap2.xml' );
		$collection = new SitemapIndexCollection( array( $entry1, $entry2 ) );

		$this->assertEquals( 2, count( $collection ) );
		$this->assertEquals( 2, $collection->count() );
	}
}
