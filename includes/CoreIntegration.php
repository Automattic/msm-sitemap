<?php
/**
 * CoreIntegration.php
 *
 * @package Automattic\MSM_Sitemap
 */

declare(strict_types=1);

namespace Automattic\MSM_Sitemap;

/**
 * Handles WordPress core sitemap system integration.
 *
 * This class manages the integration with WordPress core sitemaps,
 * primarily for stylesheet support while preventing interference with MSM functionality.
 *
 * @package Automattic\MSM_Sitemap
 */
class CoreIntegration {

	/**
	 * Initialize core sitemap integration.
	 */
	public static function setup(): void {
		// Enable WordPress core sitemaps for stylesheet support, but prevent them from interfering with MSM
		add_action( 'wp_sitemaps_init', array( __CLASS__, 'disable_core_providers' ), 999 );
		add_filter( 'wp_sitemaps_robots', '__return_empty_string' ); // Prevent core sitemaps from being added to robots.txt
	}

	/**
	 * Disable core sitemap providers to prevent interference with MSM.
	 *
	 * Replaces all core providers with empty implementations to prevent
	 * core sitemaps from working while keeping the stylesheet endpoints available.
	 *
	 * @param \WP_Sitemaps $wp_sitemaps WordPress core sitemaps instance.
	 */
	public static function disable_core_providers( \WP_Sitemaps $wp_sitemaps ): void {
		// Remove all core providers to prevent them from interfering with MSM
		$core_providers = array( 'posts', 'taxonomies', 'users' );
		
		foreach ( $core_providers as $provider_name ) {
			$wp_sitemaps->registry->add_provider(
				$provider_name,
				new class() extends \WP_Sitemaps_Provider {
					/**
					 * Get URL list for the provider.
					 *
					 * @param int    $page_num Page of results.
					 * @param string $subtype  Subtype to query.
					 * @return array Array of URL data.
					 */
					public function get_url_list( $page_num, $subtype = '' ): array {
						return array(); // Return empty array to prevent core sitemaps from working
					}
				
					/**
					 * Get the maximum number of pages available for the provider.
					 *
					 * @param string $subtype Subtype to query.
					 * @return int Number of pages.
					 */
					public function get_max_num_pages( $subtype = '' ): int {
						return 0; // No pages
					}
				} 
			);
		}
	}
} 
