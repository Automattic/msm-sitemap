<?php
/**
 * Sitemap Content Types Test
 *
 * @package Automattic\MSM_Sitemap\Tests
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests;

use Automattic\MSM_Sitemap\Domain\Services\ContentProvider;
use Automattic\MSM_Sitemap\Domain\ValueObjects\SitemapContentTypes;
use Automattic\MSM_Sitemap\Infrastructure\Providers\PostContentProvider;
use InvalidArgumentException;

/**
 * Test SitemapContentTypes collection.
 */
class SitemapContentTypesTest extends TestCase {

	/**
	 * Sitemap content types instance.
	 *
	 * @var SitemapContentTypes
	 */
	private SitemapContentTypes $content_types;

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->content_types = new SitemapContentTypes();
	}

	/**
	 * Test register adds content provider.
	 */
	public function test_register_adds_content_provider(): void {
		$provider = new PostContentProvider();
		$this->content_types->register( $provider );

		$this->assertTrue( $this->content_types->is_registered( 'posts' ) );
		$this->assertEquals( $provider, $this->content_types->get( 'posts' ) );
		$this->assertEquals( 1, $this->content_types->count() );
	}

	/**
	 * Test register throws exception for duplicate.
	 */
	public function test_register_throws_exception_for_duplicate(): void {
		$provider1 = new PostContentProvider();
		$provider2 = new PostContentProvider();

		$this->content_types->register( $provider1 );

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Content provider for "posts" is already registered' );

		$this->content_types->register( $provider2 );
	}

	/**
	 * Test unregister removes content provider.
	 */
	public function test_unregister_removes_content_provider(): void {
		$provider = new PostContentProvider();
		$this->content_types->register( $provider );

		$this->assertTrue( $this->content_types->unregister( 'posts' ) );
		$this->assertFalse( $this->content_types->is_registered( 'posts' ) );
		$this->assertNull( $this->content_types->get( 'posts' ) );
	}

	/**
	 * Test unregister returns false for non-existent type.
	 */
	public function test_unregister_returns_false_for_non_existent_type(): void {
		$this->assertFalse( $this->content_types->unregister( 'non_existent' ) );
	}

	/**
	 * Test get returns registered content provider.
	 */
	public function test_get_returns_registered_content_provider(): void {
		$provider = new PostContentProvider();
		$this->content_types->register( $provider );

		$retrieved = $this->content_types->get( 'posts' );
		$this->assertEquals( $provider, $retrieved );
	}

	/**
	 * Test get returns null for non-existent type.
	 */
	public function test_get_returns_null_for_non_existent_type(): void {
		$this->assertNull( $this->content_types->get( 'non_existent' ) );
	}

	/**
	 * Test get_all returns all registered content providers.
	 */
	public function test_get_all_returns_all_registered_content_providers(): void {
		$provider1 = new PostContentProvider();
		$provider2 = new PostContentProvider(); // This will fail to register due to duplicate

		$this->content_types->register( $provider1 );

		$all_providers = $this->content_types->get_all();
		$this->assertCount( 1, $all_providers );
		$this->assertEquals( $provider1, $all_providers[0] );
	}

	/**
	 * Test get_all_types returns all registered content types.
	 */
	public function test_get_all_types_returns_all_registered_content_types(): void {
		$provider = new PostContentProvider();
		$this->content_types->register( $provider );

		$all_types = $this->content_types->get_all_types();
		$this->assertCount( 1, $all_types );
		$this->assertContains( 'posts', $all_types );
	}

	/**
	 * Test count returns correct number.
	 */
	public function test_count_returns_correct_number(): void {
		$this->assertEquals( 0, $this->content_types->count() );

		$provider = new PostContentProvider();
		$this->content_types->register( $provider );
		$this->assertEquals( 1, $this->content_types->count() );
	}

	/**
	 * Test is_empty returns correct value.
	 */
	public function test_is_empty_returns_correct_value(): void {
		$this->assertTrue( $this->content_types->is_empty() );

		$provider = new PostContentProvider();
		$this->content_types->register( $provider );
		$this->assertFalse( $this->content_types->is_empty() );
	}

	/**
	 * Test clear removes all content providers.
	 */
	public function test_clear_removes_all_content_providers(): void {
		$provider = new PostContentProvider();
		$this->content_types->register( $provider );
		$this->assertFalse( $this->content_types->is_empty() );

		$this->content_types->clear();
		$this->assertTrue( $this->content_types->is_empty() );
		$this->assertEquals( 0, $this->content_types->count() );
	}

	/**
	 * Test equals compares collections correctly.
	 */
	public function test_equals_compares_collections_correctly(): void {
		$provider1 = new PostContentProvider();
		$provider2 = new PostContentProvider();

		$this->content_types->register( $provider1 );

		$other_content_types = new SitemapContentTypes();
		$other_content_types->register( $provider2 );

		$this->assertTrue( $this->content_types->equals( $other_content_types ) );
	}
}
