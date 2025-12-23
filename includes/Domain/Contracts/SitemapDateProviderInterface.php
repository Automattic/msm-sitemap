<?php
/**
 * Sitemap Date Provider Interface
 *
 * @package Automattic\MSM_Sitemap\Domain\Contracts
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Domain\Contracts;

/**
 * Sitemap Date Provider Interface
 *
 * Defines the contract for services that provide dates requiring sitemap generation.
 * Different implementations detect dates through different strategies:
 * - Missing sitemaps: dates with posts but no sitemap
 * - Stale sitemaps: dates where sitemap is older than latest post modification
 * - All dates: all dates that have posts (for full regeneration)
 */
interface SitemapDateProviderInterface {

	/**
	 * Get dates that require sitemap generation.
	 *
	 * @return array<string> Array of dates in YYYY-MM-DD format.
	 */
	public function get_dates(): array;

	/**
	 * Get the provider type identifier.
	 *
	 * @return string The provider type (e.g., 'missing', 'stale', 'all').
	 */
	public function get_type(): string;

	/**
	 * Get a human-readable description of what this provider detects.
	 *
	 * @return string The description.
	 */
	public function get_description(): string;
}
