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
	 * Test that setup method registers the correct filters.
	 */
	public function test_setup_registers_filters(): void {
		// Remove any existing filters to start fresh
		remove_filter( 'wp_sitemaps_stylesheet_content', array( StylesheetManager::class, 'modify_core_stylesheet' ) );
		remove_filter( 'wp_sitemaps_stylesheet_index_content', array( StylesheetManager::class, 'modify_core_index_stylesheet' ) );

		// Call setup
		StylesheetManager::setup();

		// Verify filters are registered (has_filter returns priority, not boolean)
		$priority1 = has_filter( 'wp_sitemaps_stylesheet_content', array( StylesheetManager::class, 'modify_core_stylesheet' ) );
		$priority2 = has_filter( 'wp_sitemaps_stylesheet_index_content', array( StylesheetManager::class, 'modify_core_index_stylesheet' ) );
		$this->assertIsInt( $priority1 );
		$this->assertIsInt( $priority2 );
		$this->assertGreaterThan( 0, $priority1 );
		$this->assertGreaterThan( 0, $priority2 );

		// Clean up
		remove_filter( 'wp_sitemaps_stylesheet_content', array( StylesheetManager::class, 'modify_core_stylesheet' ) );
		remove_filter( 'wp_sitemaps_stylesheet_index_content', array( StylesheetManager::class, 'modify_core_index_stylesheet' ) );
	}

	/**
	 * Test that sitemap stylesheet reference returns correct URL.
	 */
	public function test_get_sitemap_stylesheet_reference(): void {
		$reference = StylesheetManager::get_sitemap_stylesheet_reference();

		$this->assertStringContainsString( '<?xml-stylesheet', $reference );
		$this->assertStringContainsString( 'type="text/xsl"', $reference );
		$this->assertStringContainsString( 'href="', $reference );
		$this->assertStringContainsString( '/wp-sitemap.xsl', $reference );
		$this->assertStringContainsString( home_url(), $reference );
	}

	/**
	 * Test that index stylesheet reference returns correct URL.
	 */
	public function test_get_index_stylesheet_reference(): void {
		$reference = StylesheetManager::get_index_stylesheet_reference();

		$this->assertStringContainsString( '<?xml-stylesheet', $reference );
		$this->assertStringContainsString( 'type="text/xsl"', $reference );
		$this->assertStringContainsString( 'href="', $reference );
		$this->assertStringContainsString( '/wp-sitemap-index.xsl', $reference );
		$this->assertStringContainsString( home_url(), $reference );
	}

	/**
	 * Test that core stylesheet modification applies MSM branding.
	 */
	public function test_modify_core_stylesheet_applies_msm_branding(): void {
		$original_content = 'This XML Sitemap is generated by WordPress to make your content more visible for search engines.';
		$modified_content = StylesheetManager::modify_core_stylesheet( $original_content );

		$this->assertStringContainsString( 'Metro Sitemap', $modified_content );
		$this->assertStringNotContainsString( 'WordPress', $modified_content );
	}

	/**
	 * Test that core stylesheet modification adds MSM namespaces.
	 */
	public function test_modify_core_stylesheet_adds_msm_namespaces(): void {
		$original_content = 'xmlns:sitemap="http://www.sitemaps.org/schemas/sitemap/0.9" exclude-result-prefixes="sitemap"';
		$modified_content = StylesheetManager::modify_core_stylesheet( $original_content );

		// Check that MSM namespaces are added
		$this->assertStringContainsString( 'xmlns:n="http://www.google.com/schemas/sitemap-news/0.9"', $modified_content );
		$this->assertStringContainsString( 'xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"', $modified_content );

		// Check that exclude-result-prefixes is updated
		$this->assertStringContainsString( 'exclude-result-prefixes="sitemap n image"', $modified_content );
	}

	/**
	 * Test that core index stylesheet modification applies MSM branding.
	 */
	public function test_modify_core_index_stylesheet_applies_msm_branding(): void {
		$original_content = 'This XML Sitemap is generated by WordPress to make your content more visible for search engines.';
		$modified_content = StylesheetManager::modify_core_index_stylesheet( $original_content );

		$this->assertStringContainsString( 'Metro Sitemap', $modified_content );
		$this->assertStringContainsString( 'Sitemap Index', $modified_content );
		$this->assertStringNotContainsString( 'WordPress', $modified_content );
	}

	/**
	 * Test that stylesheet modification preserves other content.
	 */
	public function test_modify_core_stylesheet_preserves_other_content(): void {
		$original_content = '<?xml version="1.0"?><xsl:stylesheet>This XML Sitemap is generated by WordPress to make your content more visible for search engines.</xsl:stylesheet>';
		$modified_content = StylesheetManager::modify_core_stylesheet( $original_content );

		// Check that XML structure is preserved
		$this->assertStringContainsString( '<?xml version="1.0"?>', $modified_content );
		$this->assertStringContainsString( '<xsl:stylesheet>', $modified_content );
		$this->assertStringContainsString( '</xsl:stylesheet>', $modified_content );

		// Check that branding is changed
		$this->assertStringContainsString( 'Metro Sitemap', $modified_content );
		$this->assertStringNotContainsString( 'WordPress', $modified_content );
	}

	/**
	 * Test that MSM-specific filters are applied.
	 */
	public function test_msm_specific_filters_are_applied(): void {
		$original_content = 'Test content';
		$custom_content   = 'Custom MSM content';

		// Add a custom filter
		add_filter(
			'msm_sitemaps_stylesheet_content',
			function () use ( $custom_content ) {
				return $custom_content;
			} 
		);

		$modified_content = StylesheetManager::modify_core_stylesheet( $original_content );

		$this->assertEquals( $custom_content, $modified_content );

		// Clean up
		remove_all_filters( 'msm_sitemaps_stylesheet_content' );
	}

	/**
	 * Test that MSM index-specific filters are applied.
	 */
	public function test_msm_index_specific_filters_are_applied(): void {
		$original_content = 'Test content';
		$custom_content   = 'Custom MSM index content';

		// Add a custom filter
		add_filter(
			'msm_sitemaps_stylesheet_index_content',
			function () use ( $custom_content ) {
				return $custom_content;
			} 
		);

		$modified_content = StylesheetManager::modify_core_index_stylesheet( $original_content );

		$this->assertEquals( $custom_content, $modified_content );

		// Clean up
		remove_all_filters( 'msm_sitemaps_stylesheet_index_content' );
	}

	/**
	 * Test that stylesheet references use correct home URL.
	 */
	public function test_stylesheet_references_use_correct_home_url(): void {
		$sitemap_reference = StylesheetManager::get_sitemap_stylesheet_reference();
		$index_reference   = StylesheetManager::get_index_stylesheet_reference();

		// Both should use the same home URL
		$this->assertStringContainsString( home_url(), $sitemap_reference );
		$this->assertStringContainsString( home_url(), $index_reference );

		// URLs should be different
		$this->assertStringContainsString( '/wp-sitemap.xsl', $sitemap_reference );
		$this->assertStringContainsString( '/wp-sitemap-index.xsl', $index_reference );
	}
} 
