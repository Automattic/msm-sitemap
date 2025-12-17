<?php
/**
 * Sitemap Index Collection Factory Test
 *
 * @package Automattic\MSM_Sitemap\Tests
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Integration;

use Automattic\MSM_Sitemap\Domain\ValueObjects\SitemapIndexCollection;
use Automattic\MSM_Sitemap\Domain\ValueObjects\SitemapIndexEntry;
use Automattic\MSM_Sitemap\Infrastructure\Factories\SitemapIndexCollectionFactory;
use InvalidArgumentException;
use Automattic\MSM_Sitemap\Infrastructure\Factories\SitemapIndexEntryFactory;

/**
 * Test class for SitemapIndexCollectionFactory.
 */
class SitemapIndexCollectionFactoryTest extends TestCase {

	/**
	 * Test creating collection from entries.
	 */
	public function test_from_entries() {
		$entry1 = SitemapIndexEntryFactory::from_data( 'https://example.com/sitemap1.xml' );
		$entry2 = SitemapIndexEntryFactory::from_data( 'https://example.com/sitemap2.xml', '2023-01-01T00:00:00Z' );

		$collection = SitemapIndexCollectionFactory::from_entries( array( $entry1, $entry2 ) );

		$this->assertInstanceOf( SitemapIndexCollection::class, $collection );
		$this->assertCount( 2, $collection );
		$this->assertContains( $entry1, $collection->get_entries() );
		$this->assertContains( $entry2, $collection->get_entries() );
	}

	/**
	 * Test creating collection from entries with custom max entries.
	 */
	public function test_from_entries_with_custom_max_entries() {
		$entry1 = SitemapIndexEntryFactory::from_data( 'https://example.com/sitemap1.xml' );
		$entry2 = SitemapIndexEntryFactory::from_data( 'https://example.com/sitemap2.xml' );

		$collection = SitemapIndexCollectionFactory::from_entries( array( $entry1, $entry2 ), 10 );

		$this->assertInstanceOf( SitemapIndexCollection::class, $collection );
		$this->assertCount( 2, $collection );
		$this->assertFalse( $collection->is_full() );
	}

	/**
	 * Test creating empty collection.
	 */
	public function test_create_empty() {
		$collection = SitemapIndexCollectionFactory::create_empty();

		$this->assertInstanceOf( SitemapIndexCollection::class, $collection );
		$this->assertCount( 0, $collection );
		$this->assertTrue( $collection->is_empty() );
	}

	/**
	 * Test creating empty collection with custom max entries.
	 */
	public function test_create_empty_with_custom_max_entries() {
		$collection = SitemapIndexCollectionFactory::create_empty( 5 );

		$this->assertInstanceOf( SitemapIndexCollection::class, $collection );
		$this->assertCount( 0, $collection );
		$this->assertTrue( $collection->is_empty() );
		$this->assertFalse( $collection->is_full() );
	}

	/**
	 * Test merging multiple collections.
	 */
	public function test_merge() {
		$entry1 = SitemapIndexEntryFactory::from_data( 'https://example.com/sitemap1.xml' );
		$entry2 = SitemapIndexEntryFactory::from_data( 'https://example.com/sitemap2.xml' );
		$entry3 = SitemapIndexEntryFactory::from_data( 'https://example.com/sitemap3.xml' );

		$collection1 = SitemapIndexCollectionFactory::from_entries( array( $entry1, $entry2 ) );
		$collection2 = SitemapIndexCollectionFactory::from_entries( array( $entry3 ) );

		$merged_collection = SitemapIndexCollectionFactory::merge( array( $collection1, $collection2 ) );

		$this->assertInstanceOf( SitemapIndexCollection::class, $merged_collection );
		$this->assertCount( 3, $merged_collection );
		$this->assertContains( $entry1, $merged_collection->get_entries() );
		$this->assertContains( $entry2, $merged_collection->get_entries() );
		$this->assertContains( $entry3, $merged_collection->get_entries() );
	}

	/**
	 * Test merging multiple collections with custom max entries.
	 */
	public function test_merge_with_custom_max_entries() {
		$entry1 = SitemapIndexEntryFactory::from_data( 'https://example.com/sitemap1.xml' );
		$entry2 = SitemapIndexEntryFactory::from_data( 'https://example.com/sitemap2.xml' );

		$collection1 = SitemapIndexCollectionFactory::from_entries( array( $entry1 ) );
		$collection2 = SitemapIndexCollectionFactory::from_entries( array( $entry2 ) );

		$merged_collection = SitemapIndexCollectionFactory::merge( array( $collection1, $collection2 ), 5 );

		$this->assertInstanceOf( SitemapIndexCollection::class, $merged_collection );
		$this->assertCount( 2, $merged_collection );
		$this->assertFalse( $merged_collection->is_full() );
	}

	/**
	 * Test merging with invalid collection type.
	 */
	public function test_merge_with_invalid_collection_type() {
		$entry1 = SitemapIndexEntryFactory::from_data( 'https://example.com/sitemap1.xml' );
		$collection1 = SitemapIndexCollectionFactory::from_entries( array( $entry1 ) );

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'All collections must be SitemapIndexCollection instances.' );

		SitemapIndexCollectionFactory::merge( array( $collection1, 'invalid_collection' ) );
	}

	/**
	 * Test merging empty collections.
	 */
	public function test_merge_empty_collections() {
		$collection1 = SitemapIndexCollectionFactory::create_empty();
		$collection2 = SitemapIndexCollectionFactory::create_empty();

		$merged_collection = SitemapIndexCollectionFactory::merge( array( $collection1, $collection2 ) );

		$this->assertInstanceOf( SitemapIndexCollection::class, $merged_collection );
		$this->assertCount( 0, $merged_collection );
		$this->assertTrue( $merged_collection->is_empty() );
	}

	/**
	 * Test merging single collection.
	 */
	public function test_merge_single_collection() {
		$entry1 = SitemapIndexEntryFactory::from_data( 'https://example.com/sitemap1.xml' );
		$collection1 = SitemapIndexCollectionFactory::from_entries( array( $entry1 ) );

		$merged_collection = SitemapIndexCollectionFactory::merge( array( $collection1 ) );

		$this->assertInstanceOf( SitemapIndexCollection::class, $merged_collection );
		$this->assertCount( 1, $merged_collection );
		$this->assertContains( $entry1, $merged_collection->get_entries() );
	}
}
