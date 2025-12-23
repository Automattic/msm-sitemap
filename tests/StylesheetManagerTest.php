<?php
/**
 * StylesheetManagerTest.php
 *
 * @package Automattic\MSM_Sitemap\Tests\Includes
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Includes;

use Automattic\MSM_Sitemap\Infrastructure\WordPress\StylesheetManager;
use Automattic\MSM_Sitemap\Tests\TestCase;

/**
 * Tests for StylesheetManager class.
 */
class StylesheetManagerTest extends TestCase {

	/**
	 * Get the site URL dynamically from WordPress.
	 *
	 * @return string
	 */
	private function get_site_url(): string {
		return home_url();
	}

	/**
	 * Test that sitemap stylesheet reference returns correct URL.
	 */
	public function test_get_sitemap_stylesheet_reference(): void {
		$reference = StylesheetManager::get_sitemap_stylesheet_reference();

		$this->assertStringContainsString( '<?xml-stylesheet', $reference );
		$this->assertStringContainsString( 'type="text/xsl"', $reference );
		$this->assertStringContainsString( 'href="', $reference );
		$this->assertStringContainsString( '/msm-sitemap.xsl', $reference );
		$this->assertStringContainsString( $this->get_site_url(), $reference );
	}

	/**
	 * Test that index stylesheet reference returns correct URL.
	 */
	public function test_get_index_stylesheet_reference(): void {
		$reference = StylesheetManager::get_index_stylesheet_reference();

		$this->assertStringContainsString( '<?xml-stylesheet', $reference );
		$this->assertStringContainsString( 'type="text/xsl"', $reference );
		$this->assertStringContainsString( 'href="', $reference );
		$this->assertStringContainsString( '/msm-sitemap-index.xsl', $reference );
		$this->assertStringContainsString( $this->get_site_url(), $reference );
	}

	/**
	 * Test that XSL reference can be disabled via filter.
	 */
	public function test_xsl_reference_can_be_disabled(): void {
		// Add filter to disable XSL references
		add_filter( 'msm_sitemap_include_xsl_reference', '__return_false' );

		$sitemap_reference = StylesheetManager::get_sitemap_stylesheet_reference();
		$index_reference   = StylesheetManager::get_index_stylesheet_reference();

		// Both should return empty strings when disabled
		$this->assertSame( '', $sitemap_reference );
		$this->assertSame( '', $index_reference );

		// Clean up
		remove_filter( 'msm_sitemap_include_xsl_reference', '__return_false' );
	}

	/**
	 * Test that XSL reference is enabled by default.
	 */
	public function test_xsl_reference_is_enabled_by_default(): void {
		// Ensure no filters are applied
		remove_all_filters( 'msm_sitemap_include_xsl_reference' );

		$sitemap_reference = StylesheetManager::get_sitemap_stylesheet_reference();
		$index_reference   = StylesheetManager::get_index_stylesheet_reference();

		// Both should contain XSL references when enabled
		$this->assertStringContainsString( '<?xml-stylesheet', $sitemap_reference );
		$this->assertStringContainsString( '<?xml-stylesheet', $index_reference );
		$this->assertStringContainsString( '/msm-sitemap.xsl', $sitemap_reference );
		$this->assertStringContainsString( '/msm-sitemap-index.xsl', $index_reference );
	}

	/**
	 * Test that stylesheet references use correct home URL.
	 */
	public function test_stylesheet_references_use_correct_home_url(): void {
		$sitemap_reference = StylesheetManager::get_sitemap_stylesheet_reference();
		$index_reference   = StylesheetManager::get_index_stylesheet_reference();
		$site_url          = $this->get_site_url();

		// Both should use the same home URL
		$this->assertStringContainsString( $site_url, $sitemap_reference );
		$this->assertStringContainsString( $site_url, $index_reference );

		// URLs should be different and use MSM-specific endpoints
		$this->assertStringContainsString( '/msm-sitemap.xsl', $sitemap_reference );
		$this->assertStringContainsString( '/msm-sitemap-index.xsl', $index_reference );
	}
}
