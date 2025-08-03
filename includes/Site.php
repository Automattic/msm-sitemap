<?php
/**
 * Site information and configuration handler.
 *
 * @package Automattic\MSM_Sitemap
 */

declare(strict_types=1);

namespace Automattic\MSM_Sitemap;

/**
 * Site class for handling site-related information and configuration.
 *
 * Provides a clean interface for accessing site settings and configuration
 * that are relevant to sitemap generation and site visibility.
 */
class Site {

	/**
	 * Check if the site is public (search engines can index).
	 *
	 * Determines whether the site is configured to allow search engines
	 * to index the content. This is based on the 'blog_public' option.
	 *
	 * @return bool True if the site is public, false otherwise.
	 */
	public static function is_public(): bool {
		return ( '1' === get_option( 'blog_public' ) );
	}

	/**
	 * Get the home URL for the site.
	 *
	 * Returns the home URL of the site, which is typically used for
	 * generating sitemap URLs and other site-related links.
	 *
	 * @param string $path Optional. Path relative to the home URL.
	 * @return string The home URL with optional path appended.
	 */
	public static function get_home_url( string $path = '' ): string {
		return home_url( $path );
	}
} 
