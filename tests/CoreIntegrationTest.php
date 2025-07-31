<?php
/**
 * CoreIntegrationTest.php
 *
 * @package Automattic\MSM_Sitemap\Tests\Includes
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Includes;

use Automattic\MSM_Sitemap\CoreIntegration;
use Automattic\MSM_Sitemap\Tests\TestCase;
use WP_Sitemaps;
use WP_Sitemaps_Provider;

/**
 * Unit Tests for CoreIntegration class.
 */
class CoreIntegrationTest extends TestCase {

	/**
	 * Test that setup method registers the correct hooks.
	 */
	public function test_setup_registers_hooks(): void {
		// Remove any existing hooks to start fresh
		remove_action( 'wp_sitemaps_init', array( CoreIntegration::class, 'disable_core_providers' ), 999 );
		remove_filter( 'wp_sitemaps_robots', '__return_empty_string' );

		// Call setup
		CoreIntegration::setup();

		// Verify hooks are registered (has_action/has_filter return priority, not boolean)
		$action_priority = has_action( 'wp_sitemaps_init', array( CoreIntegration::class, 'disable_core_providers' ) );
		$filter_priority = has_filter( 'wp_sitemaps_robots', '__return_empty_string' );
		$this->assertIsInt( $action_priority );
		$this->assertIsInt( $filter_priority );
		$this->assertGreaterThan( 0, $action_priority );
		$this->assertGreaterThan( 0, $filter_priority );

		// Verify priority is correct (has_action returns priority, not boolean)
		$priority = has_action( 'wp_sitemaps_init', array( CoreIntegration::class, 'disable_core_providers' ) );
		$this->assertIsInt( $priority );
		$this->assertGreaterThan( 0, $priority );
	}

	/**
	 * Test that core providers are disabled correctly.
	 */
	public function test_disable_core_providers(): void {
		// Mock WP_Sitemaps instance
		$wp_sitemaps = $this->createMock( WP_Sitemaps::class );
		$registry    = $this->createMock( \WP_Sitemaps_Registry::class );

		// Set up expectations
		$wp_sitemaps->registry = $registry;
		$registry->expects( $this->exactly( 3 ) )
			->method( 'add_provider' )
			->withConsecutive(
				array( 'posts', $this->isInstanceOf( WP_Sitemaps_Provider::class ) ),
				array( 'taxonomies', $this->isInstanceOf( WP_Sitemaps_Provider::class ) ),
				array( 'users', $this->isInstanceOf( WP_Sitemaps_Provider::class ) )
			);

		// Call the method
		CoreIntegration::disable_core_providers( $wp_sitemaps );
	}

	/**
	 * Test that disabled providers return empty results.
	 */
	public function test_disabled_providers_return_empty_results(): void {
		// Create a mock WP_Sitemaps instance
		$wp_sitemaps = $this->createMock( WP_Sitemaps::class );
		$registry    = $this->createMock( \WP_Sitemaps_Registry::class );

		// Capture the provider instances
		$captured_providers = array();
		$registry->method( 'add_provider' )
			->willReturnCallback(
				function ( $name, $provider ) use ( &$captured_providers ) {
					$captured_providers[ $name ] = $provider;
					return true;
				} 
			);

		$wp_sitemaps->registry = $registry;

		// Call the method
		CoreIntegration::disable_core_providers( $wp_sitemaps );

		// Test each provider returns empty results
		foreach ( $captured_providers as $name => $provider ) {
			$this->assertInstanceOf( WP_Sitemaps_Provider::class, $provider );
			$this->assertEmpty( $provider->get_url_list( 1 ) );
			$this->assertEquals( 0, $provider->get_max_num_pages() );
		}
	}

	/**
	 * Test that robots.txt filter prevents core sitemaps from being added.
	 */
	public function test_robots_filter_prevents_core_sitemaps(): void {
		// Add the filter
		add_filter( 'wp_sitemaps_robots', '__return_empty_string' );

		// Test that the filter returns empty string
		$result = apply_filters( 'wp_sitemaps_robots', 'Sitemap: https://example.com/wp-sitemap.xml' );
		$this->assertEquals( '', $result );

		// Clean up
		remove_filter( 'wp_sitemaps_robots', '__return_empty_string' );
	}

	/**
	 * Test that core sitemaps are enabled for stylesheet support.
	 */
	public function test_core_sitemaps_enabled_for_stylesheets(): void {
		// Verify that core sitemaps are enabled (not disabled by MSM)
		$this->assertTrue( apply_filters( 'wp_sitemaps_enabled', true ) );
		$this->assertNotFalse( apply_filters( 'wp_sitemaps_enabled', true ) );
	}

	/**
	 * Test integration with WordPress core sitemap system.
	 */
	public function test_integration_with_wordpress_core(): void {
		// Verify that the wp_sitemaps_get_server function is available
		// (this indicates core sitemaps are loaded)
		$this->assertTrue( function_exists( 'wp_sitemaps_get_server' ) );

		// Verify that core sitemap classes are available
		$this->assertTrue( class_exists( 'WP_Sitemaps' ) );
		$this->assertTrue( class_exists( 'WP_Sitemaps_Provider' ) );
		$this->assertTrue( class_exists( 'WP_Sitemaps_Registry' ) );
	}
} 
