<?php
/**
 * Page Repository Interface
 *
 * @package Automattic\MSM_Sitemap\Domain\Contracts
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Domain\Contracts;

use WP_Post;

/**
 * Interface for page data access for sitemap generation.
 */
interface PageRepositoryInterface {

	/**
	 * Get published pages.
	 *
	 * @param int $offset Number of pages to skip.
	 * @param int $limit  Maximum pages to return.
	 * @return array<WP_Post> Array of post objects.
	 */
	public function get_pages( int $offset = 0, int $limit = 2000 ): array;

	/**
	 * Get total count of published pages.
	 *
	 * @return int Total number of pages.
	 */
	public function get_page_count(): int;

	/**
	 * Get the URL for a page.
	 *
	 * @param WP_Post $page The post object.
	 * @return string|null The page URL or null if not available.
	 */
	public function get_page_url( WP_Post $page ): ?string;

	/**
	 * Check if a page should be excluded from sitemaps (noindex).
	 *
	 * @param int $post_id The post ID.
	 * @return bool True if the page should be excluded, false otherwise.
	 */
	public function is_page_noindex( int $post_id ): bool;

	/**
	 * Get the last modified date for a page.
	 *
	 * @param WP_Post $page The post object.
	 * @return string|null The last modified date in W3C format or null.
	 */
	public function get_page_lastmod( WP_Post $page ): ?string;
}
