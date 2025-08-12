<?php
/**
 * Interface for sitemap repositories.
 *
 * @package Automattic\MSM_Sitemap\Domain\Contracts
 */

namespace Automattic\MSM_Sitemap\Domain\Contracts;

/**
 * Interface for sitemap repositories.
 */
interface SitemapRepositoryInterface {
	/**
	 * Save a sitemap for a specific date.
	 *
	 * @param string $date The sitemap date (YYYY-MM-DD format).
	 * @param string $xml_content The XML content of the sitemap.
	 * @param int $url_count The number of URLs in the sitemap.
	 * @return bool True on success, false on failure.
	 */
	public function save( string $date, string $xml_content, int $url_count ): bool;

	/**
	 * Find a sitemap by date.
	 *
	 * @param string $date The sitemap date (YYYY-MM-DD format).
	 * @return int|null The post ID if found, null otherwise.
	 */
	public function find_by_date( string $date ): ?int;

	/**
	 * Delete a sitemap for a specific date.
	 *
	 * @param string $date The sitemap date (YYYY-MM-DD format).
	 * @return bool True on success, false on failure.
	 */
	public function delete_by_date( string $date ): bool;

	/**
	 * Delete sitemaps for specific date queries.
	 *
	 * @param array $date_queries Array of date queries with year, month, day keys.
	 * @return int Number of sitemaps deleted.
	 */
	public function delete_for_date_queries( array $date_queries ): int;

	/**
	 * Delete all sitemaps.
	 *
	 * @return int Number of sitemaps deleted.
	 */
	public function delete_all(): int;

	/**
	 * Get all sitemap dates.
	 *
	 * @return array Array of sitemap dates in YYYY-MM-DD format.
	 */
	public function get_all_sitemap_dates(): array;
}
