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
		$container = \Automattic\MSM_Sitemap\Infrastructure\DI\msm_sitemap_container();
		$settings_service = $container->get( \Automattic\MSM_Sitemap\Application\Services\SettingsService::class );
		$settings_service->update_settings( array(
			'include_images' => false,
			'featured_images' => false,
			'content_images' => true,
			'max_images_per_sitemap' => 500,
		) );

		// Create new repository instance to test saved values
		$repository = new ImageRepository();
		
		$this->assertFalse( $repository->should_include_images() );
		$this->assertFalse( $repository->should_include_featured_images() );
		$this->assertTrue( $repository->should_include_content_images() );
		$this->assertEquals( 500, $repository->get_max_images_per_sitemap() );
		$this->assertEquals( array( 'content' ), $repository->get_included_image_types() );

		// Clean up
		delete_option( 'msm_sitemap' );
	}

	/**
	 * Test that filters can override saved settings.
	 */
	public function test_filters_override_saved_settings(): void {
		// Set saved values
		$container = \Automattic\MSM_Sitemap\Infrastructure\DI\msm_sitemap_container();
		$settings_service = $container->get( \Automattic\MSM_Sitemap\Application\Services\SettingsService::class );
		$settings_service->update_settings( array(
			'include_images' => false,
			'max_images_per_sitemap' => 500,
		) );

		// Add filters to override
		add_filter( 'msm_sitemap_include_images', '__return_true' );
		add_filter( 'msm_sitemap_max_images_per_sitemap', function() { return 2000; } );
		add_filter( 'msm_sitemap_image_types', function() { return array( 'content' ); } );

		$repository = new ImageRepository();
		
		$this->assertTrue( $repository->should_include_images() );
		$this->assertEquals( 2000, $repository->get_max_images_per_sitemap() );
		$this->assertEquals( array( 'content' ), $repository->get_included_image_types() );

		// Clean up
		remove_filter( 'msm_sitemap_include_images', '__return_true' );
		remove_all_filters( 'msm_sitemap_max_images_per_sitemap' );
		remove_all_filters( 'msm_sitemap_image_types' );
		delete_option( 'msm_sitemap' );
	}

	/**
	 * Test that max images per sitemap returns saved values.
	 */
	public function test_max_images_per_sitemap_values(): void {
		$container = \Automattic\MSM_Sitemap\Infrastructure\DI\msm_sitemap_container();
		$settings_service = $container->get( \Automattic\MSM_Sitemap\Application\Services\SettingsService::class );
		
		// Test default value
		$settings_service->delete_setting( 'max_images_per_sitemap' );
		$repository = new ImageRepository();
		$this->assertEquals( 1000, $repository->get_max_images_per_sitemap() );

		// Test custom values
		$settings_service->update_setting( 'max_images_per_sitemap', 500 );
		$repository = new ImageRepository();
		$this->assertEquals( 500, $repository->get_max_images_per_sitemap() );

		// Test edge cases (repository doesn't clamp, ActionHandlers does)
		// For edge cases, set directly in database to bypass validation
		$current_settings = get_option( 'msm_sitemap', array() );
		$current_settings['max_images_per_sitemap'] = 0;
		update_option( 'msm_sitemap', $current_settings );
		$repository = new ImageRepository();
		$this->assertEquals( 0, $repository->get_max_images_per_sitemap() );

		$current_settings['max_images_per_sitemap'] = 15000;
		update_option( 'msm_sitemap', $current_settings );
		$repository = new ImageRepository();
		$this->assertEquals( 15000, $repository->get_max_images_per_sitemap() );

		// Clean up
		$settings_service->delete_setting( 'max_images_per_sitemap' );
	}

	/**
	 * Test that image types are based on individual settings.
	 */
	public function test_image_types_based_on_settings(): void {
		$container = \Automattic\MSM_Sitemap\Infrastructure\DI\msm_sitemap_container();
		$settings_service = $container->get( \Automattic\MSM_Sitemap\Application\Services\SettingsService::class );
		
		// Clean up any existing options first
		$settings_service->delete_setting( 'featured_images' );
		$settings_service->delete_setting( 'content_images' );

		// Test default values (both enabled)
		$repository = new ImageRepository();
		$this->assertEquals( array( 'featured', 'content' ), $repository->get_included_image_types() );

		// Test with only featured images enabled
		$settings_service->update_setting( 'featured_images', '1' );
		$settings_service->update_setting( 'content_images', '0' );
		$repository = new ImageRepository();
		$this->assertEquals( array( 'featured' ), $repository->get_included_image_types() );

		// Test with only content images enabled
		$settings_service->update_setting( 'featured_images', '0' );
		$settings_service->update_setting( 'content_images', '1' );
		$repository = new ImageRepository();
		$this->assertEquals( array( 'content' ), $repository->get_included_image_types() );

		// Test with neither enabled
		$settings_service->update_setting( 'featured_images', '0' );
		$settings_service->update_setting( 'content_images', '0' );
		$repository = new ImageRepository();
		$this->assertEquals( array(), $repository->get_included_image_types() );

		// Clean up
		$settings_service->delete_setting( 'featured_images' );
		$settings_service->delete_setting( 'content_images' );
	}
}
