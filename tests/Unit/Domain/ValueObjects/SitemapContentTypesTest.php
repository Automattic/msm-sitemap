<?php
/**
 * Tests for SitemapContentTypes Value Object.
 *
 * @package Automattic\MSM_Sitemap\Tests\Unit\Domain\ValueObjects
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Unit\Domain\ValueObjects;

use Automattic\MSM_Sitemap\Domain\Contracts\ContentProviderInterface;
use Automattic\MSM_Sitemap\Domain\ValueObjects\SitemapContentTypes;
use Automattic\MSM_Sitemap\Domain\ValueObjects\UrlEntry;
use Automattic\MSM_Sitemap\Domain\ValueObjects\UrlSet;
use Automattic\MSM_Sitemap\Tests\Unit\TestCase;
use InvalidArgumentException;
use Mockery;

/**
 * SitemapContentTypes Value Object test case.
 *
 * Tests:
 * - Provider registration and lookup
 * - Duplicate registration prevention
 * - Unregistration
 * - Collection behavior
 */
class SitemapContentTypesTest extends TestCase {

	/**
	 * Create a mock ContentProviderInterface.
	 *
	 * @param string $content_type The content type.
	 * @param string $display_name The display name.
	 * @param string $description  The description.
	 * @return ContentProviderInterface
	 */
	private function create_mock_provider(
		string $content_type,
		string $display_name = 'Mock Provider',
		string $description = 'Mock description'
	): ContentProviderInterface {
		$mock = Mockery::mock( ContentProviderInterface::class );
		$mock->shouldReceive( 'get_content_type' )->andReturn( $content_type );
		$mock->shouldReceive( 'get_display_name' )->andReturn( $display_name );
		$mock->shouldReceive( 'get_description' )->andReturn( $description );
		$mock->shouldReceive( 'get_urls_for_date' )->andReturn( new UrlSet() );
		$mock->shouldReceive( 'enhance_url_entries' )->andReturnUsing(
			function ( array $entries ) {
				return $entries;
			}
		);
		return $mock;
	}

	// ===========================================
	// Creation tests
	// ===========================================

	/**
	 * Test that empty collection can be created.
	 */
	public function test_can_create_empty_collection(): void {
		$types = new SitemapContentTypes();

		$this->assertCount( 0, $types );
		$this->assertTrue( $types->is_empty() );
	}

	// ===========================================
	// Registration tests
	// ===========================================

	/**
	 * Test that provider can be registered.
	 */
	public function test_can_register_provider(): void {
		$types    = new SitemapContentTypes();
		$provider = $this->create_mock_provider( 'posts' );

		$types->register( $provider );

		$this->assertCount( 1, $types );
		$this->assertTrue( $types->is_registered( 'posts' ) );
	}

	/**
	 * Test that multiple providers can be registered.
	 */
	public function test_can_register_multiple_providers(): void {
		$types = new SitemapContentTypes();

		$types->register( $this->create_mock_provider( 'posts' ) );
		$types->register( $this->create_mock_provider( 'pages' ) );
		$types->register( $this->create_mock_provider( 'authors' ) );

		$this->assertCount( 3, $types );
		$this->assertTrue( $types->is_registered( 'posts' ) );
		$this->assertTrue( $types->is_registered( 'pages' ) );
		$this->assertTrue( $types->is_registered( 'authors' ) );
	}

	/**
	 * Test that duplicate registration throws exception.
	 */
	public function test_rejects_duplicate_registration(): void {
		$types = new SitemapContentTypes();
		$types->register( $this->create_mock_provider( 'posts' ) );

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Content provider for "posts" is already registered' );

		$types->register( $this->create_mock_provider( 'posts' ) );
	}

	// ===========================================
	// Unregistration tests
	// ===========================================

	/**
	 * Test that provider can be unregistered.
	 */
	public function test_can_unregister_provider(): void {
		$types = new SitemapContentTypes();
		$types->register( $this->create_mock_provider( 'posts' ) );

		$result = $types->unregister( 'posts' );

		$this->assertTrue( $result );
		$this->assertFalse( $types->is_registered( 'posts' ) );
		$this->assertCount( 0, $types );
	}

	/**
	 * Test that unregistering non-existent provider returns false.
	 */
	public function test_unregister_nonexistent_returns_false(): void {
		$types = new SitemapContentTypes();

		$result = $types->unregister( 'nonexistent' );

		$this->assertFalse( $result );
	}

	// ===========================================
	// Lookup tests
	// ===========================================

	/**
	 * Test that registered provider can be retrieved.
	 */
	public function test_can_get_registered_provider(): void {
		$types    = new SitemapContentTypes();
		$provider = $this->create_mock_provider( 'posts' );
		$types->register( $provider );

		$retrieved = $types->get( 'posts' );

		$this->assertSame( $provider, $retrieved );
	}

	/**
	 * Test that getting non-existent provider returns null.
	 */
	public function test_get_nonexistent_returns_null(): void {
		$types = new SitemapContentTypes();

		$this->assertNull( $types->get( 'nonexistent' ) );
	}

	/**
	 * Test that is_registered returns false for non-existent type.
	 */
	public function test_is_registered_returns_false_for_nonexistent(): void {
		$types = new SitemapContentTypes();

		$this->assertFalse( $types->is_registered( 'nonexistent' ) );
	}

	// ===========================================
	// get_all() tests
	// ===========================================

	/**
	 * Test that get_all returns all registered providers.
	 */
	public function test_get_all_returns_all_providers(): void {
		$types     = new SitemapContentTypes();
		$provider1 = $this->create_mock_provider( 'posts' );
		$provider2 = $this->create_mock_provider( 'pages' );

		$types->register( $provider1 );
		$types->register( $provider2 );

		$all = $types->get_all();

		$this->assertCount( 2, $all );
		$this->assertContains( $provider1, $all );
		$this->assertContains( $provider2, $all );
	}

	/**
	 * Test that get_all returns array values (re-indexed).
	 */
	public function test_get_all_returns_reindexed_array(): void {
		$types = new SitemapContentTypes();
		$types->register( $this->create_mock_provider( 'posts' ) );
		$types->register( $this->create_mock_provider( 'pages' ) );

		$all = $types->get_all();

		$this->assertSame( array( 0, 1 ), array_keys( $all ) );
	}

	// ===========================================
	// get_all_types() tests
	// ===========================================

	/**
	 * Test that get_all_types returns all registered type names.
	 */
	public function test_get_all_types_returns_type_names(): void {
		$types = new SitemapContentTypes();
		$types->register( $this->create_mock_provider( 'posts' ) );
		$types->register( $this->create_mock_provider( 'pages' ) );
		$types->register( $this->create_mock_provider( 'authors' ) );

		$allTypes = $types->get_all_types();

		$this->assertCount( 3, $allTypes );
		$this->assertContains( 'posts', $allTypes );
		$this->assertContains( 'pages', $allTypes );
		$this->assertContains( 'authors', $allTypes );
	}

	// ===========================================
	// clear() tests
	// ===========================================

	/**
	 * Test that clear removes all providers.
	 */
	public function test_clear_removes_all_providers(): void {
		$types = new SitemapContentTypes();
		$types->register( $this->create_mock_provider( 'posts' ) );
		$types->register( $this->create_mock_provider( 'pages' ) );

		$types->clear();

		$this->assertCount( 0, $types );
		$this->assertTrue( $types->is_empty() );
		$this->assertFalse( $types->is_registered( 'posts' ) );
		$this->assertFalse( $types->is_registered( 'pages' ) );
	}

	// ===========================================
	// is_empty() tests
	// ===========================================

	/**
	 * Test that is_empty returns true for empty collection.
	 */
	public function test_is_empty_returns_true_for_empty(): void {
		$types = new SitemapContentTypes();

		$this->assertTrue( $types->is_empty() );
	}

	/**
	 * Test that is_empty returns false for non-empty collection.
	 */
	public function test_is_empty_returns_false_for_non_empty(): void {
		$types = new SitemapContentTypes();
		$types->register( $this->create_mock_provider( 'posts' ) );

		$this->assertFalse( $types->is_empty() );
	}

	// ===========================================
	// Countable interface tests
	// ===========================================

	/**
	 * Test that count returns correct number.
	 */
	public function test_count_returns_correct_number(): void {
		$types = new SitemapContentTypes();
		$types->register( $this->create_mock_provider( 'posts' ) );
		$types->register( $this->create_mock_provider( 'pages' ) );

		$this->assertCount( 2, $types );
		$this->assertSame( 2, $types->count() );
	}

	// ===========================================
	// Equality tests
	// ===========================================

	/**
	 * Test equals returns true for same providers.
	 */
	public function test_equals_returns_true_for_same_providers(): void {
		$provider1 = $this->create_mock_provider( 'posts', 'Posts', 'Post provider' );
		$provider2 = $this->create_mock_provider( 'pages', 'Pages', 'Page provider' );

		$types1 = new SitemapContentTypes();
		$types1->register( $provider1 );
		$types1->register( $provider2 );

		$types2 = new SitemapContentTypes();
		$types2->register( $provider1 );
		$types2->register( $provider2 );

		$this->assertTrue( $types1->equals( $types2 ) );
	}

	/**
	 * Test equals returns false for different counts.
	 */
	public function test_equals_returns_false_for_different_counts(): void {
		$provider = $this->create_mock_provider( 'posts' );

		$types1 = new SitemapContentTypes();
		$types1->register( $provider );

		$types2 = new SitemapContentTypes();

		$this->assertFalse( $types1->equals( $types2 ) );
	}

	/**
	 * Test equals returns false for different content types.
	 */
	public function test_equals_returns_false_for_different_types(): void {
		$types1 = new SitemapContentTypes();
		$types1->register( $this->create_mock_provider( 'posts' ) );

		$types2 = new SitemapContentTypes();
		$types2->register( $this->create_mock_provider( 'pages' ) );

		$this->assertFalse( $types1->equals( $types2 ) );
	}

	/**
	 * Test equals compares provider properties.
	 */
	public function test_equals_compares_provider_properties(): void {
		$types1 = new SitemapContentTypes();
		$types1->register( $this->create_mock_provider( 'posts', 'Posts', 'Description A' ) );

		$types2 = new SitemapContentTypes();
		$types2->register( $this->create_mock_provider( 'posts', 'Posts', 'Description B' ) );

		// Different descriptions should make them unequal.
		$this->assertFalse( $types1->equals( $types2 ) );
	}

	/**
	 * Test equals returns true for empty collections.
	 */
	public function test_equals_returns_true_for_empty_collections(): void {
		$types1 = new SitemapContentTypes();
		$types2 = new SitemapContentTypes();

		$this->assertTrue( $types1->equals( $types2 ) );
	}
}
