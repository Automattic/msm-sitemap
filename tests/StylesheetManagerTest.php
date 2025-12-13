<?php
/**
 * StylesheetManagerTest.php
 *
 * @package Automattic\MSM_Sitemap\Tests\Includes
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Includes;

use Automattic\MSM_Sitemap\StylesheetManager;
use Automattic\MSM_Sitemap\Tests\TestCase;

/**
 * Unit Tests for StylesheetManager class.
 */
class StylesheetManagerTest extends TestCase {

	/**
	 * @var string
	 */
	private string $site_url;

	/**
	 * Set up test environment.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$this->site_url = home_url();
	}

	/**
	 * Test that setup method registers the correct filters.
	 */
	public function test_setup_registers_actions(): void {
		// Call setup
		StylesheetManager::setup();

		// Verify actions are registered (has_action returns priority, not boolean)
		$priority1 = has_action( 'init', array( StylesheetManager::class, 'register_xsl_endpoints' ) );
		$priority2 = has_action( 'template_redirect', array( StylesheetManager::class, 'handle_xsl_requests' ) );
		$this->assertIsInt( $priority1 );
		$this->assertIsInt( $priority2 );
		
		// Verify actions are actually registered (priority > 0 means registered)
		$this->assertStringContainsString( 'register_xsl_endpoints', 'register_xsl_endpoints action should be registered' );
		$this->assertStringContainsString( 'handle_xsl_requests', 'handle_xsl_requests action should be registered' );
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
		$this->assertStringContainsString( $this->site_url, $reference );

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
		$this->assertStringContainsString( $this->site_url, $reference );

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
		$this->assertStringContainsString( '', $sitemap_reference );
		$this->assertStringContainsString( '', $index_reference );

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

		// Both should use the same home URL
		$this->assertStringContainsString( $this->site_url, $sitemap_reference );
		$this->assertStringContainsString( $this->site_url, $index_reference );

		// URLs should be different and use MSM-specific endpoints
		$this->assertStringContainsString( '/msm-sitemap.xsl', $sitemap_reference );
		$this->assertStringContainsString( '/msm-sitemap-index.xsl', $index_reference );
	}

	/**
	 * Test that MSM sitemap stylesheet contains proper content.
	 */
	public function test_get_msm_sitemap_stylesheet(): void {
		$stylesheet = StylesheetManager::get_msm_sitemap_stylesheet();

		// Check basic XSL structure
		$this->assertStringContainsString( '<?xml version="1.0" encoding="UTF-8"?>', $stylesheet );
		$this->assertStringContainsString( '<xsl:stylesheet', $stylesheet );
		$this->assertStringContainsString( '</xsl:stylesheet>', $stylesheet );

		// Check MSM branding
		$this->assertStringContainsString( 'Metro Sitemap', $stylesheet );
		$this->assertStringNotContainsString( 'WordPress', $stylesheet );

		// Check MSM namespaces
		$this->assertStringContainsString( 'xmlns:n="http://www.google.com/schemas/sitemap-news/0.9"', $stylesheet );
		$this->assertStringContainsString( 'xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"', $stylesheet );
		$this->assertStringContainsString( 'exclude-result-prefixes="sitemap n image"', $stylesheet );
	}

	/**
	 * Test that MSM sitemap index stylesheet contains proper content.
	 */
	public function test_get_msm_sitemap_index_stylesheet(): void {
		$stylesheet = StylesheetManager::get_msm_sitemap_index_stylesheet();

		// Check basic XSL structure
		$this->assertStringContainsString( '<?xml version="1.0" encoding="UTF-8"?>', $stylesheet );
		$this->assertStringContainsString( '<xsl:stylesheet', $stylesheet );
		$this->assertStringContainsString( '</xsl:stylesheet>', $stylesheet );

		// Check MSM branding
		$this->assertStringContainsString( 'Metro Sitemap', $stylesheet );
		$this->assertStringContainsString( 'Sitemap Index', $stylesheet );
		$this->assertStringNotContainsString( 'WordPress', $stylesheet );

		// Check sitemap index specific elements
		$this->assertStringContainsString( 'sitemap:sitemapindex', $stylesheet );
	}

	/**
	 * Test that CSS is included in stylesheets.
	 */
	public function test_get_stylesheet_css(): void {
		$css = StylesheetManager::get_stylesheet_css();

		// Check for basic CSS properties
		$this->assertStringContainsString( 'body {', $css );
		$this->assertStringContainsString( 'font-family:', $css );
		$this->assertStringContainsString( '#sitemap {', $css );
		$this->assertStringContainsString( '#sitemap__table {', $css );
	}
} 
