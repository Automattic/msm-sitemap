<?php
/**
 * Paginated Content Provider Interface
 *
 * Interface for content providers that generate non-date-based sitemaps.
 * Used for taxonomies, pages, users, and other content that is organised
 * as a single paginated sitemap rather than by publish date.
 *
 * @package Automattic\MSM_Sitemap\Domain\Contracts
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Domain\Contracts;

use Automattic\MSM_Sitemap\Domain\ValueObjects\UrlSet;

/**
 * Paginated Content Provider Interface
 *
 * Defines the contract for content providers that generate paginated sitemaps
 * for non-date-based content (taxonomies, pages, users).
 *
 * Unlike DateBasedContentProviderInterface which organises content by publish date,
 * this interface produces a single sitemap per content type with pagination support.
 *
 * @see DateBasedContentProviderInterface For date-based content (posts).
 */
interface PaginatedContentProviderInterface {

	/**
	 * Default number of URLs per sitemap page.
	 *
	 * WordPress core uses 2000 as the default.
	 */
	public const DEFAULT_PER_PAGE = 2000;

	/**
	 * Get URLs for a specific page.
	 *
	 * @param int $page     Page number (1-indexed).
	 * @param int $per_page Number of URLs per page.
	 * @return UrlSet Collection of URL entries for the page.
	 */
	public function get_urls( int $page = 1, int $per_page = self::DEFAULT_PER_PAGE ): UrlSet;

	/**
	 * Get the content type this provider handles.
	 *
	 * @return string The content type identifier (e.g., 'taxonomy-category', 'page', 'author').
	 */
	public function get_content_type(): string;

	/**
	 * Get the display name for this content provider.
	 *
	 * @return string The localised display name.
	 */
	public function get_display_name(): string;

	/**
	 * Get the description for this content provider.
	 *
	 * @return string The localised description.
	 */
	public function get_description(): string;

	/**
	 * Get the total number of items this provider can return.
	 *
	 * @return int Total item count.
	 */
	public function get_total_count(): int;

	/**
	 * Get the number of pages for this sitemap.
	 *
	 * @param int $per_page Number of URLs per page.
	 * @return int Number of pages.
	 */
	public function get_page_count( int $per_page = self::DEFAULT_PER_PAGE ): int;

	/**
	 * Get the sitemap slug for URL generation.
	 *
	 * Used to construct URLs like /sitemap-{slug}.xml or /sitemap-{slug}-2.xml
	 *
	 * @return string The sitemap slug (e.g., 'taxonomy-category', 'page', 'author').
	 */
	public function get_sitemap_slug(): string;

	/**
	 * Check if this provider is enabled.
	 *
	 * @return bool True if enabled, false otherwise.
	 */
	public function is_enabled(): bool;
}
