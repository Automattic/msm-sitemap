<?php
/**
 * Tests for Site Value Object.
 *
 * @package Automattic\MSM_Sitemap\Tests\Domain\ValueObjects
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Domain\ValueObjects;

use Automattic\MSM_Sitemap\Domain\ValueObjects\Site;
use Automattic\MSM_Sitemap\Tests\TestCase;

/**
 * Test class for Site Value Object.
 */
class SiteTest extends TestCase {

	/**
	 * Test is_public returns true when blog_public is '1'.
	 */
	public function test_is_public_returns_true_when_blog_public(): void {
		update_option( 'blog_public', '1' );

		$this->assertTrue( Site::is_public() );
	}

	/**
	 * Test is_public returns false when blog_public is '0'.
	 */
	public function test_is_public_returns_false_when_not_blog_public(): void {
		update_option( 'blog_public', '0' );

		$this->assertFalse( Site::is_public() );
	}

	/**
	 * Test are_sitemaps_enabled returns true when blog is public.
	 */
	public function test_are_sitemaps_enabled_returns_true_when_public(): void {
		update_option( 'blog_public', '1' );

		$this->assertTrue( Site::are_sitemaps_enabled() );
	}

	/**
	 * Test are_sitemaps_enabled returns false when blog is not public.
	 */
	public function test_are_sitemaps_enabled_returns_false_when_not_public(): void {
		update_option( 'blog_public', '0' );

		$this->assertFalse( Site::are_sitemaps_enabled() );
	}

	/**
	 * Test msm_sitemap_is_enabled filter can enable sitemaps on non-public site.
	 */
	public function test_filter_can_enable_sitemaps_on_non_public_site(): void {
		update_option( 'blog_public', '0' );

		// Add filter to enable sitemaps
		add_filter( 'msm_sitemap_is_enabled', '__return_true' );

		$this->assertTrue( Site::are_sitemaps_enabled() );

		// Clean up
		remove_filter( 'msm_sitemap_is_enabled', '__return_true' );
	}

	/**
	 * Test msm_sitemap_is_enabled filter can disable sitemaps on public site.
	 */
	public function test_filter_can_disable_sitemaps_on_public_site(): void {
		update_option( 'blog_public', '1' );

		// Add filter to disable sitemaps
		add_filter( 'msm_sitemap_is_enabled', '__return_false' );

		$this->assertFalse( Site::are_sitemaps_enabled() );

		// Clean up
		remove_filter( 'msm_sitemap_is_enabled', '__return_false' );
	}

	/**
	 * Test msm_sitemap_is_enabled filter receives correct default value.
	 */
	public function test_filter_receives_correct_default_value(): void {
		update_option( 'blog_public', '1' );

		$received_value = null;
		$filter_callback = function ( $is_enabled ) use ( &$received_value ) {
			$received_value = $is_enabled;
			return $is_enabled;
		};

		add_filter( 'msm_sitemap_is_enabled', $filter_callback );
		Site::are_sitemaps_enabled();
		remove_filter( 'msm_sitemap_is_enabled', $filter_callback );

		$this->assertTrue( $received_value );

		// Test with non-public site
		update_option( 'blog_public', '0' );

		add_filter( 'msm_sitemap_is_enabled', $filter_callback );
		Site::are_sitemaps_enabled();
		remove_filter( 'msm_sitemap_is_enabled', $filter_callback );

		$this->assertFalse( $received_value );
	}
}
