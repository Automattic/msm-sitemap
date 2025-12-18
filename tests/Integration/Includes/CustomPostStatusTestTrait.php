<?php
/**
 * Trait for custom post status helpers in MSM Sitemap tests.
 *
 * @package Automattic\MSM_Sitemap\Tests\Integration\Includes
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Integration\Includes;

/**
 * Trait for custom post status helpers in MSM Sitemap tests.
 */
trait CustomPostStatusTestTrait {
	/**
	 * Set up the custom post status.
	 */
	public function custom_post_status_set_up(): void {
		register_post_status(
			$this->custom_post_status(),
			array(
				'public' => true,
			)
		);
		$this->add_test_filter( 'msm_sitemap_post_status', array( $this, 'custom_post_status' ) );
	}

	/**
	 * Tear down the custom post status.
	 */
	public function custom_post_status_tear_down(): void {
		remove_filter( 'msm_sitemap_post_status', array( $this, 'custom_post_status' ) );
	}

	/**
	 * Get the custom post status.
	 */
	public function custom_post_status(): string {
		return 'live';
	}
} 
