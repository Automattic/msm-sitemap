<?php
/**
 * WordPress Post Type Registration Service
 *
 * @package Automattic\MSM_Sitemap\Infrastructure\WordPress
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Infrastructure\WordPress;

use Automattic\MSM_Sitemap\Domain\Contracts\WordPressIntegrationInterface;

/**
 * Service responsible for registering custom post types for the MSM Sitemap plugin.
 */
class PostTypeRegistration implements WordPressIntegrationInterface {

	/**
	 * The sitemap custom post type name.
	 *
	 * @var string
	 */
	private const SITEMAP_CPT = 'msm_sitemap';

	/**
	 * Register WordPress hooks and filters for post type registration.
	 */
	public function register_hooks(): void {
		register_post_type(
			$this->get_post_type(),
			array(
				'labels'       => array(
					'name'          => __( 'Sitemaps', 'msm-sitemap' ),
					'singular_name' => __( 'Sitemap', 'msm-sitemap' ),
				),
				'public'       => false,
				'has_archive'  => false,
				'rewrite'      => false,
				'show_ui'      => true,  // TODO: should probably have some sort of custom UI
				'show_in_menu' => false, // Since we're manually adding a Sitemaps menu, no need to auto-add one through the CPT.
				'supports'     => array(
					'title',
				),
			)
		);
	}

	/**
	 * Get the sitemap post type name.
	 *
	 * @return string The sitemap post type name.
	 */
	public function get_post_type(): string {
		return self::SITEMAP_CPT;
	}
}
