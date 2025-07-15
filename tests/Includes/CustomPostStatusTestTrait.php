<?php
/**
 * Trait for custom post status helpers in MSM Sitemap tests.
 */

namespace Automattic\MSM_Sitemap\Tests\Includes;

trait CustomPostStatusTestTrait {
	public function custom_post_status_set_up(): void {
		register_post_status(
			$this->custom_post_status(),
			array(
				'public' => true,
			)
		);
		$this->add_test_filter( 'msm_sitemap_post_status', array( $this, 'custom_post_status' ) );
	}

	public function custom_post_status_tear_down(): void {
		remove_filter( 'msm_sitemap_post_status', array( $this, 'custom_post_status' ) );
	}

	public function custom_post_status(): string {
		return 'live';
	}
} 
