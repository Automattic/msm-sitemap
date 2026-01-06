<?php
/**
 * Tests for Site Value Object.
 *
 * @package Automattic\MSM_Sitemap\Tests\Unit\Domain\ValueObjects
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Unit\Domain\ValueObjects;

use Automattic\MSM_Sitemap\Domain\ValueObjects\Site;
use Automattic\MSM_Sitemap\Tests\Unit\TestCase;
use Brain\Monkey\Functions;

/**
 * Site Value Object test case.
 *
 * Tests WordPress integration through Brain Monkey function mocking.
 * All tests mock WordPress functions like get_option(), apply_filters(), home_url().
 */
class SiteTest extends TestCase {

	// ===========================================
	// is_public() tests
	// ===========================================

	/**
	 * Test is_public returns true when blog_public is '1'.
	 */
	public function test_is_public_returns_true_when_public(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( 'blog_public' )
			->andReturn( '1' );

		$this->assertTrue( Site::is_public() );
	}

	/**
	 * Test is_public returns false when blog_public is '0'.
	 */
	public function test_is_public_returns_false_when_not_public(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( 'blog_public' )
			->andReturn( '0' );

		$this->assertFalse( Site::is_public() );
	}

	/**
	 * Test is_public returns false when blog_public is empty string.
	 */
	public function test_is_public_returns_false_when_empty(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( 'blog_public' )
			->andReturn( '' );

		$this->assertFalse( Site::is_public() );
	}

	/**
	 * Test is_public returns false for numeric 1 (strict string comparison).
	 */
	public function test_is_public_uses_strict_string_comparison(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( 'blog_public' )
			->andReturn( 1 ); // Numeric, not string '1'.

		// Strict comparison means numeric 1 !== '1'.
		$this->assertFalse( Site::is_public() );
	}

	// ===========================================
	// are_sitemaps_enabled() tests
	// ===========================================

	/**
	 * Test are_sitemaps_enabled returns true when public.
	 */
	public function test_sitemaps_enabled_when_public(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( 'blog_public' )
			->andReturn( '1' );

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'msm_sitemap_is_enabled', true )
			->andReturn( true );

		$this->assertTrue( Site::are_sitemaps_enabled() );
	}

	/**
	 * Test are_sitemaps_enabled returns false when not public and not filtered.
	 */
	public function test_sitemaps_disabled_when_not_public(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( 'blog_public' )
			->andReturn( '0' );

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'msm_sitemap_is_enabled', false )
			->andReturn( false );

		$this->assertFalse( Site::are_sitemaps_enabled() );
	}

	/**
	 * Test are_sitemaps_enabled can be forced on via filter.
	 */
	public function test_sitemaps_can_be_enabled_via_filter(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( 'blog_public' )
			->andReturn( '0' ); // Site is not public.

		// But filter enables sitemaps anyway (e.g., staging environment).
		Functions\expect( 'apply_filters' )
			->once()
			->with( 'msm_sitemap_is_enabled', false )
			->andReturn( true );

		$this->assertTrue( Site::are_sitemaps_enabled() );
	}

	/**
	 * Test are_sitemaps_enabled can be forced off via filter.
	 */
	public function test_sitemaps_can_be_disabled_via_filter(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( 'blog_public' )
			->andReturn( '1' ); // Site is public.

		// But filter disables sitemaps anyway.
		Functions\expect( 'apply_filters' )
			->once()
			->with( 'msm_sitemap_is_enabled', true )
			->andReturn( false );

		$this->assertFalse( Site::are_sitemaps_enabled() );
	}

	// ===========================================
	// get_home_url() tests
	// ===========================================

	/**
	 * Test get_home_url returns home_url result.
	 */
	public function test_get_home_url_returns_home_url(): void {
		Functions\expect( 'home_url' )
			->once()
			->with( '' )
			->andReturn( 'https://example.com' );

		$this->assertSame( 'https://example.com', Site::get_home_url() );
	}

	/**
	 * Test get_home_url with path appends path.
	 */
	public function test_get_home_url_with_path(): void {
		Functions\expect( 'home_url' )
			->once()
			->with( '/sitemap.xml' )
			->andReturn( 'https://example.com/sitemap.xml' );

		$this->assertSame( 'https://example.com/sitemap.xml', Site::get_home_url( '/sitemap.xml' ) );
	}

	// ===========================================
	// is_indexed_by_year() tests
	// ===========================================

	/**
	 * Test is_indexed_by_year returns false by default.
	 */
	public function test_is_indexed_by_year_returns_false_by_default(): void {
		Functions\expect( 'apply_filters' )
			->once()
			->with( 'msm_sitemap_index_by_year', false )
			->andReturn( false );

		$this->assertFalse( Site::is_indexed_by_year() );
	}

	/**
	 * Test is_indexed_by_year can be enabled via filter.
	 */
	public function test_is_indexed_by_year_can_be_enabled(): void {
		Functions\expect( 'apply_filters' )
			->once()
			->with( 'msm_sitemap_index_by_year', false )
			->andReturn( true );

		$this->assertTrue( Site::is_indexed_by_year() );
	}

	// ===========================================
	// get_sitemap_index_url() tests
	// ===========================================

	/**
	 * Test get_sitemap_index_url returns default URL when not indexed by year.
	 */
	public function test_sitemap_index_url_default(): void {
		Functions\expect( 'apply_filters' )
			->once()
			->with( 'msm_sitemap_index_by_year', false )
			->andReturn( false );

		Functions\expect( 'home_url' )
			->once()
			->with( '/sitemap.xml' )
			->andReturn( 'https://example.com/sitemap.xml' );

		$this->assertSame( 'https://example.com/sitemap.xml', Site::get_sitemap_index_url() );
	}

	/**
	 * Test get_sitemap_index_url with year when indexed by year.
	 */
	public function test_sitemap_index_url_with_year(): void {
		Functions\expect( 'apply_filters' )
			->once()
			->with( 'msm_sitemap_index_by_year', false )
			->andReturn( true );

		Functions\expect( 'home_url' )
			->once()
			->with( '/sitemap-2024.xml' )
			->andReturn( 'https://example.com/sitemap-2024.xml' );

		$this->assertSame( 'https://example.com/sitemap-2024.xml', Site::get_sitemap_index_url( 2024 ) );
	}

	/**
	 * Test get_sitemap_index_url without year parameter falls back to default.
	 */
	public function test_sitemap_index_url_indexed_by_year_but_no_year_param(): void {
		Functions\expect( 'apply_filters' )
			->once()
			->with( 'msm_sitemap_index_by_year', false )
			->andReturn( true );

		// When indexed by year but no year provided, should return default.
		Functions\expect( 'home_url' )
			->once()
			->with( '/sitemap.xml' )
			->andReturn( 'https://example.com/sitemap.xml' );

		$this->assertSame( 'https://example.com/sitemap.xml', Site::get_sitemap_index_url() );
	}

	/**
	 * Test get_sitemap_index_url ignores year when not indexed by year.
	 */
	public function test_sitemap_index_url_ignores_year_when_not_indexed_by_year(): void {
		Functions\expect( 'apply_filters' )
			->once()
			->with( 'msm_sitemap_index_by_year', false )
			->andReturn( false );

		// Year is provided but indexing by year is disabled.
		Functions\expect( 'home_url' )
			->once()
			->with( '/sitemap.xml' )
			->andReturn( 'https://example.com/sitemap.xml' );

		$this->assertSame( 'https://example.com/sitemap.xml', Site::get_sitemap_index_url( 2024 ) );
	}
}
