<?php
/**
 * XslRequestHandlerTest.php
 *
 * @package Automattic\MSM_Sitemap\Tests
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests;

use Automattic\MSM_Sitemap\Infrastructure\HTTP\XslRequestHandler;

/**
 * Tests for XslRequestHandler class.
 */
class XslRequestHandlerTest extends TestCase {

	/**
	 * The XSL request handler instance.
	 *
	 * @var XslRequestHandler
	 */
	private XslRequestHandler $handler;

	/**
	 * Set up the test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->handler = new XslRequestHandler();
	}

	/**
	 * Test that register_hooks adds the correct actions.
	 */
	public function test_register_hooks_adds_actions(): void {
		$this->handler->register_hooks();

		$priority1 = has_action( 'init', array( $this->handler, 'register_xsl_endpoints' ) );
		$priority2 = has_action( 'template_redirect', array( $this->handler, 'handle_xsl_requests' ) );

		$this->assertIsInt( $priority1 );
		$this->assertIsInt( $priority2 );
	}

	/**
	 * Test that query vars are added correctly.
	 */
	public function test_add_query_vars(): void {
		$existing_vars = array( 'foo', 'bar' );
		$result        = $this->handler->add_query_vars( $existing_vars );

		$this->assertContains( 'msm-sitemap-stylesheet', $result );
		$this->assertContains( 'foo', $result );
		$this->assertContains( 'bar', $result );
	}

	/**
	 * Test that sitemap stylesheet contains proper content.
	 */
	public function test_get_sitemap_stylesheet(): void {
		$stylesheet = $this->handler->get_sitemap_stylesheet();

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
	 * Test that sitemap index stylesheet contains proper content.
	 */
	public function test_get_sitemap_index_stylesheet(): void {
		$stylesheet = $this->handler->get_sitemap_index_stylesheet();

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
		$css = $this->handler->get_stylesheet_css();

		// Check for basic CSS properties
		$this->assertStringContainsString( 'body {', $css );
		$this->assertStringContainsString( 'font-family:', $css );
		$this->assertStringContainsString( '#sitemap {', $css );
		$this->assertStringContainsString( '#sitemap__table {', $css );
	}

	/**
	 * Test that stylesheet content filter is applied.
	 */
	public function test_stylesheet_content_filter_is_applied(): void {
		$custom_content = 'Custom XSL content';

		add_filter(
			'msm_sitemaps_stylesheet_content',
			function () use ( $custom_content ) {
				return $custom_content;
			}
		);

		$stylesheet = $this->handler->get_sitemap_stylesheet();

		$this->assertEquals( $custom_content, $stylesheet );

		remove_all_filters( 'msm_sitemaps_stylesheet_content' );
	}

	/**
	 * Test that index stylesheet content filter is applied.
	 */
	public function test_index_stylesheet_content_filter_is_applied(): void {
		$custom_content = 'Custom index XSL content';

		add_filter(
			'msm_sitemaps_stylesheet_index_content',
			function () use ( $custom_content ) {
				return $custom_content;
			}
		);

		$stylesheet = $this->handler->get_sitemap_index_stylesheet();

		$this->assertEquals( $custom_content, $stylesheet );

		remove_all_filters( 'msm_sitemaps_stylesheet_index_content' );
	}

	/**
	 * Test that CSS filter is applied.
	 */
	public function test_css_filter_is_applied(): void {
		$additional_css = 'h1 { color: red; }';

		add_filter(
			'msm_sitemaps_stylesheet_css',
			function ( $css ) use ( $additional_css ) {
				return $css . $additional_css;
			}
		);

		$css = $this->handler->get_stylesheet_css();

		$this->assertStringContainsString( $additional_css, $css );

		remove_all_filters( 'msm_sitemaps_stylesheet_css' );
	}
}
