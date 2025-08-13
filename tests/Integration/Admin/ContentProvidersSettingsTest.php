<?php
/**
 * Content Providers Settings Test
 *
 * @package Automattic\MSM_Sitemap\Tests\Admin
 */

declare(strict_types=1);

namespace Automattic\MSM_Sitemap\Tests;

use Automattic\MSM_Sitemap\Infrastructure\Repositories\ImageRepository;

/**
 * Unit Tests for Content Provider Settings.
 */
class ContentProvidersSettingsTest extends TestCase {

	/**
	 * Test that image provider settings are saved and retrieved correctly.
	 */
	public function test_image_provider_settings_save_and_retrieve(): void {
		// Clean up any existing options first
		delete_option( 'msm_sitemap_images_provider_enabled' );
		delete_option( 'msm_sitemap_include_featured_images' );
		delete_option( 'msm_sitemap_include_content_images' );
		delete_option( 'msm_sitemap_max_images_per_sitemap' );

		// Test default values
		$repository = new ImageRepository();
		
		$this->assertTrue( $repository->should_include_images() );
		$this->assertTrue( $repository->should_include_featured_images() );
		$this->assertTrue( $repository->should_include_content_images() );
		$this->assertEquals( 1000, $repository->get_max_images_per_sitemap() );
		$this->assertEquals( array( 'featured', 'content' ), $repository->get_included_image_types() );

		// Test saving custom values
		update_option( 'msm_sitemap_images_provider_enabled', '0' );
		update_option( 'msm_sitemap_include_featured_images', '0' );
		update_option( 'msm_sitemap_include_content_images', '1' );
		update_option( 'msm_sitemap_max_images_per_sitemap', 500 );

		// Create new repository instance to test saved values
		$repository = new ImageRepository();
		
		$this->assertFalse( $repository->should_include_images() );
		$this->assertFalse( $repository->should_include_featured_images() );
		$this->assertTrue( $repository->should_include_content_images() );
		$this->assertEquals( 500, $repository->get_max_images_per_sitemap() );
		$this->assertEquals( array( 'content' ), $repository->get_included_image_types() );

		// Clean up
		delete_option( 'msm_sitemap_images_provider_enabled' );
		delete_option( 'msm_sitemap_include_featured_images' );
		delete_option( 'msm_sitemap_include_content_images' );
		delete_option( 'msm_sitemap_max_images_per_sitemap' );
	}

	/**
	 * Test that filters can override saved settings.
	 */
	public function test_filters_override_saved_settings(): void {
		// Set saved values
		update_option( 'msm_sitemap_images_provider_enabled', false );
		update_option( 'msm_sitemap_max_images_per_sitemap', 500 );
		update_option( 'msm_sitemap_image_types', array( 'featured' ) );

		// Add filters to override
		add_filter( 'msm_sitemap_images_provider_enabled', '__return_true' );
		add_filter( 'msm_sitemap_max_images_per_sitemap', function() { return 2000; } );
		add_filter( 'msm_sitemap_image_types', function() { return array( 'content' ); } );

		$repository = new ImageRepository();
		
		$this->assertTrue( $repository->should_include_images() );
		$this->assertEquals( 2000, $repository->get_max_images_per_sitemap() );
		$this->assertEquals( array( 'content' ), $repository->get_included_image_types() );

		// Clean up
		remove_filter( 'msm_sitemap_images_provider_enabled', '__return_true' );
		remove_all_filters( 'msm_sitemap_max_images_per_sitemap' );
		remove_all_filters( 'msm_sitemap_image_types' );
		delete_option( 'msm_sitemap_images_provider_enabled' );
		delete_option( 'msm_sitemap_max_images_per_sitemap' );
		delete_option( 'msm_sitemap_image_types' );
	}

	/**
	 * Test that max images per sitemap returns saved values.
	 */
	public function test_max_images_per_sitemap_values(): void {
		// Test default value
		delete_option( 'msm_sitemap_max_images_per_sitemap' );
		$repository = new ImageRepository();
		$this->assertEquals( 1000, $repository->get_max_images_per_sitemap() );

		// Test custom values
		update_option( 'msm_sitemap_max_images_per_sitemap', 500 );
		$repository = new ImageRepository();
		$this->assertEquals( 500, $repository->get_max_images_per_sitemap() );

		// Test edge cases (repository doesn't clamp, ActionHandlers does)
		update_option( 'msm_sitemap_max_images_per_sitemap', 0 );
		$repository = new ImageRepository();
		$this->assertEquals( 0, $repository->get_max_images_per_sitemap() );

		update_option( 'msm_sitemap_max_images_per_sitemap', 15000 );
		$repository = new ImageRepository();
		$this->assertEquals( 15000, $repository->get_max_images_per_sitemap() );

		// Clean up
		delete_option( 'msm_sitemap_max_images_per_sitemap' );
	}

	/**
	 * Test that image types are based on individual settings.
	 */
	public function test_image_types_based_on_settings(): void {
		// Clean up any existing options first
		delete_option( 'msm_sitemap_include_featured_images' );
		delete_option( 'msm_sitemap_include_content_images' );

		// Test default values (both enabled)
		$repository = new ImageRepository();
		$this->assertEquals( array( 'featured', 'content' ), $repository->get_included_image_types() );

		// Test with only featured images enabled
		update_option( 'msm_sitemap_include_featured_images', '1' );
		update_option( 'msm_sitemap_include_content_images', '0' );
		$repository = new ImageRepository();
		$this->assertEquals( array( 'featured' ), $repository->get_included_image_types() );

		// Test with only content images enabled
		update_option( 'msm_sitemap_include_featured_images', '0' );
		update_option( 'msm_sitemap_include_content_images', '1' );
		$repository = new ImageRepository();
		$this->assertEquals( array( 'content' ), $repository->get_included_image_types() );

		// Test with neither enabled
		update_option( 'msm_sitemap_include_featured_images', '0' );
		update_option( 'msm_sitemap_include_content_images', '0' );
		$repository = new ImageRepository();
		$this->assertEquals( array(), $repository->get_included_image_types() );

		// Clean up
		delete_option( 'msm_sitemap_include_featured_images' );
		delete_option( 'msm_sitemap_include_content_images' );
	}
}
