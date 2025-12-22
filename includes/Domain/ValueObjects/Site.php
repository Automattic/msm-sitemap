<?php
/**
 * Site information and configuration handler.
 *
 * @package Automattic\MSM_Sitemap\Domain\ValueObjects
 */

declare(strict_types=1);

namespace Automattic\MSM_Sitemap\Domain\ValueObjects;

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
	 * Check if MSM Sitemaps are enabled.
	 *
	 * By default, sitemaps are enabled when the site is public. This method
	 * provides a filter to allow enabling sitemaps on non-public sites
	 * (e.g., staging environments) without affecting the broader blog_public
	 * setting that may impact other SEO-related features.
	 *
	 * @since 2.0.0
	 *
	 * @return bool True if sitemaps are enabled, false otherwise.
	 */
	public static function are_sitemaps_enabled(): bool {
		$is_public = self::is_public();

		/**
		 * Filters whether MSM Sitemaps are enabled.
		 *
		 * Allows enabling sitemaps on non-public sites (e.g., staging environments)
		 * without affecting the broader blog_public setting.
		 *
		 * @since 2.0.0
		 *
		 * @param bool $is_enabled Whether sitemaps are enabled. Default is based on blog_public.
		 */
		return (bool) apply_filters( 'msm_sitemap_is_enabled', $is_public );
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

	/**
	 * Check if sitemaps are indexed by year.
	 *
	 * @return bool True if indexed by year, false otherwise.
	 */
	public static function is_indexed_by_year(): bool {
		return apply_filters( 'msm_sitemap_index_by_year', false );
	}

	/**
	 * Get the sitemap index URL based on configuration.
	 *
	 * @param int|null $year Optional year for year-based indexing.
	 * @return string The complete sitemap index URL.
	 */
	public static function get_sitemap_index_url( ?int $year = null ): string {
		if ( self::is_indexed_by_year() && $year ) {
			return home_url( "/sitemap-{$year}.xml" );
		}
		return home_url( '/sitemap.xml' );
	}
}
