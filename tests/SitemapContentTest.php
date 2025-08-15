<?php
/**
 * SitemapContent Test
 *
 * @package Automattic\MSM_Sitemap\Tests
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests;

use Automattic\MSM_Sitemap\Domain\ValueObjects\SitemapContent;
use Automattic\MSM_Sitemap\Domain\ValueObjects\UrlEntry;
use InvalidArgumentException;

/**
 * Test SitemapContent value object.
 */
class SitemapContentTest extends TestCase {

	/**
	 * Test creating a valid sitemap content.
	 */
	public function test_create_valid_sitemap_content(): void {
		$entries = array(
			new UrlEntry( 'https://example.com/1' ),
			new UrlEntry( 'https://example.com/2' ),
		);

		$sitemap_content = new SitemapContent( $entries );

		$this->assertCount( 2, $sitemap_content );
		$this->assertFalse( $sitemap_content->is_empty() );
		$this->assertFalse( $sitemap_content->is_full() );
	}

	/**
	 * Test creating an empty sitemap content.
	 */
	public function test_create_empty_sitemap_content(): void {
		$sitemap_content = new SitemapContent();

		$this->assertCount( 0, $sitemap_content );
		$this->assertTrue( $sitemap_content->is_empty() );
		$this->assertFalse( $sitemap_content->is_full() );
	}

	/**
	 * Test adding a URL entry.
	 */
	public function test_add_url_entry(): void {
		$sitemap_content = new SitemapContent();
		$entry           = new UrlEntry( 'https://example.com/1' );

		$new_sitemap_content = $sitemap_content->add( $entry );

		$this->assertCount( 1, $new_sitemap_content );
		$this->assertTrue( $new_sitemap_content->contains( $entry ) );
		$this->assertNotSame( $sitemap_content, $new_sitemap_content ); // Immutable
	}

	/**
	 * Test removing a URL entry.
	 */
	public function test_remove_url_entry(): void {
		$entry1          = new UrlEntry( 'https://example.com/1' );
		$entry2          = new UrlEntry( 'https://example.com/2' );
		$sitemap_content = new SitemapContent( array( $entry1, $entry2 ) );

		$new_sitemap_content = $sitemap_content->remove( $entry1 );

		$this->assertCount( 1, $new_sitemap_content );
		$this->assertFalse( $new_sitemap_content->contains( $entry1 ) );
		$this->assertTrue( $new_sitemap_content->contains( $entry2 ) );
		$this->assertNotSame( $sitemap_content, $new_sitemap_content ); // Immutable
	}

	/**
	 * Test removing a non-existent URL entry.
	 */
	public function test_remove_nonexistent_url_entry(): void {
		$entry1          = new UrlEntry( 'https://example.com/1' );
		$entry2          = new UrlEntry( 'https://example.com/2' );
		$sitemap_content = new SitemapContent( array( $entry1 ) );

		$new_sitemap_content = $sitemap_content->remove( $entry2 );

		$this->assertCount( 1, $new_sitemap_content );
		$this->assertTrue( $new_sitemap_content->contains( $entry1 ) );
		$this->assertSame( $sitemap_content, $new_sitemap_content ); // No change
	}

	/**
	 * Test getting entries.
	 */
	public function test_get_entries(): void {
		$entries         = array(
			new UrlEntry( 'https://example.com/1' ),
			new UrlEntry( 'https://example.com/2' ),
		);
		$sitemap_content = new SitemapContent( $entries );

		$retrieved_entries = $sitemap_content->get_entries();

		$this->assertCount( 2, $retrieved_entries );
		$this->assertEquals( $entries, $retrieved_entries );
	}

	/**
	 * Test converting to array.
	 */
	public function test_to_array(): void {
		$entries         = array(
			new UrlEntry( 'https://example.com/1' ),
			new UrlEntry( 'https://example.com/2' ),
		);
		$sitemap_content = new SitemapContent( $entries );

		$array = $sitemap_content->to_array();

		$this->assertIsArray( $array );
		$this->assertCount( 2, $array );
		$this->assertEquals( 'https://example.com/1', $array[0]['loc'] );
		$this->assertEquals( 'https://example.com/2', $array[1]['loc'] );
	}

	/**
	 * Test equality comparison.
	 */
	public function test_equals_with_identical_sets(): void {
		$entries          = array(
			new UrlEntry( 'https://example.com/1' ),
			new UrlEntry( 'https://example.com/2' ),
		);
		$sitemap_content1 = new SitemapContent( $entries );
		$sitemap_content2 = new SitemapContent( $entries );

		$this->assertTrue( $sitemap_content1->equals( $sitemap_content2 ) );
	}

	/**
	 * Test equality comparison with different sets.
	 */
	public function test_equals_with_different_sets(): void {
		$entries1         = array( new UrlEntry( 'https://example.com/1' ) );
		$entries2         = array( new UrlEntry( 'https://example.com/2' ) );
		$sitemap_content1 = new SitemapContent( $entries1 );
		$sitemap_content2 = new SitemapContent( $entries2 );

		$this->assertFalse( $sitemap_content1->equals( $sitemap_content2 ) );
	}

	/**
	 * Test equality comparison with different sizes.
	 */
	public function test_equals_with_different_sizes(): void {
		$entries1         = array( new UrlEntry( 'https://example.com/1' ) );
		$entries2         = array(
			new UrlEntry( 'https://example.com/1' ),
			new UrlEntry( 'https://example.com/2' ),
		);
		$sitemap_content1 = new SitemapContent( $entries1 );
		$sitemap_content2 = new SitemapContent( $entries2 );

		$this->assertFalse( $sitemap_content1->equals( $sitemap_content2 ) );
	}

	/**
	 * Test equality comparison with different order.
	 */
	public function test_equals_with_different_order(): void {
		$entries1         = array(
			new UrlEntry( 'https://example.com/1' ),
			new UrlEntry( 'https://example.com/2' ),
		);
		$entries2         = array(
			new UrlEntry( 'https://example.com/2' ),
			new UrlEntry( 'https://example.com/1' ),
		);
		$sitemap_content1 = new SitemapContent( $entries1 );
		$sitemap_content2 = new SitemapContent( $entries2 );

		$this->assertFalse( $sitemap_content1->equals( $sitemap_content2 ) );
	}

	/**
	 * Test validation with invalid entry type.
	 */
	public function test_validation_with_invalid_entry_type(): void {
		$invalid_entries = array( 'not_a_url_entry' );

		$this->expectException( InvalidArgumentException::class );
		new SitemapContent( $invalid_entries );
	}

	/**
	 * Test validation with too many entries.
	 */
	public function test_validation_with_too_many_entries(): void {
		$entries = array();
		for ( $i = 0; $i < SitemapContent::DEFAULT_MAX_ENTRIES + 1; $i++ ) {
			$entries[] = new UrlEntry( "https://example.com/{$i}" );
		}

		$sitemap_content = new SitemapContent( $entries );

		$this->assertTrue( $sitemap_content->is_full() );
		$this->assertCount( SitemapContent::DEFAULT_MAX_ENTRIES, $sitemap_content );
	}

	/**
	 * Test validation with custom max entries.
	 */
	public function test_validation_with_custom_max_entries(): void {
		$entries         = array(
			new UrlEntry( 'https://example.com/1' ),
			new UrlEntry( 'https://example.com/2' ),
		);
		$sitemap_content = new SitemapContent( $entries, 3 );

		$this->assertCount( 2, $sitemap_content );
		$this->assertFalse( $sitemap_content->is_full() );
	}

	/**
	 * Test validation when max entries exceeds protocol limit.
	 */
	public function test_validation_when_max_entries_exceeds_protocol_limit(): void {
		$this->expectException( InvalidArgumentException::class );
		new SitemapContent( array(), SitemapContent::DEFAULT_MAX_ENTRIES + 1 );
	}

	/**
	 * Test adding entry when full.
	 */
	public function test_add_entry_when_full(): void {
		$entries = array();
		for ( $i = 0; $i < SitemapContent::DEFAULT_MAX_ENTRIES; $i++ ) {
			$entries[] = new UrlEntry( "https://example.com/{$i}" );
		}
		$sitemap_content = new SitemapContent( $entries );

		$new_entry           = new UrlEntry( 'https://example.com/overflow' );
		$new_sitemap_content = $sitemap_content->add( $new_entry );

		$this->assertSame( $sitemap_content, $new_sitemap_content ); // No change
		$this->assertCount( SitemapContent::DEFAULT_MAX_ENTRIES, $new_sitemap_content );
	}

	/**
	 * Test adding entry when full with custom limit.
	 */
	public function test_add_entry_when_full_with_custom_limit(): void {
		$entries         = array(
			new UrlEntry( 'https://example.com/1' ),
			new UrlEntry( 'https://example.com/2' ),
		);
		$sitemap_content = new SitemapContent( $entries, 2 );

		$new_entry           = new UrlEntry( 'https://example.com/3' );
		$new_sitemap_content = $sitemap_content->add( $new_entry );

		$this->assertSame( $sitemap_content, $new_sitemap_content ); // No change
		$this->assertCount( 2, $new_sitemap_content );
	}

	/**
	 * Test count method.
	 */
	public function test_count(): void {
		$entries         = array(
			new UrlEntry( 'https://example.com/1' ),
			new UrlEntry( 'https://example.com/2' ),
		);
		$sitemap_content = new SitemapContent( $entries );

		$this->assertEquals( 2, $sitemap_content->count() );
	}

	/**
	 * Test is full method.
	 */
	public function test_is_full(): void {
		$entries = array();
		for ( $i = 0; $i < SitemapContent::DEFAULT_MAX_ENTRIES; $i++ ) {
			$entries[] = new UrlEntry( "https://example.com/{$i}" );
		}
		$sitemap_content = new SitemapContent( $entries );

		$this->assertTrue( $sitemap_content->is_full() );
		$this->assertCount( SitemapContent::DEFAULT_MAX_ENTRIES, $sitemap_content );
	}
}
