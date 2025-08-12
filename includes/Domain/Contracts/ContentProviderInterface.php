<?php
/**
 * Content Provider Interface
 *
 * @package Automattic\MSM_Sitemap\Domain\Contracts
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Domain\Contracts;

use Automattic\MSM_Sitemap\Domain\ValueObjects\UrlSet;

/**
 * Content Provider Interface
 *
 * Defines the contract for content providers that can supply URLs
 * for sitemap generation. Each content type (posts, images, taxonomies, users)
 * will have its own implementation.
 */
interface ContentProviderInterface {

	/**
	 * Get URLs for a specific date.
	 *
	 * @param string $date MySQL DATETIME format (e.g., '2024-01-15 00:00:00').
	 * @return UrlSet Collection of URL entries for the date.
	 */
	public function get_urls_for_date( string $date ): UrlSet;

	/**
	 * Get the content type this provider handles.
	 *
	 * @return string The content type.
	 */
	public function get_content_type(): string;

	/**
	 * Get the display name for this content provider.
	 *
	 * @return string The display name.
	 */
	public function get_display_name(): string;

	/**
	 * Get the description for this content provider.
	 *
	 * @return string The description.
	 */
	public function get_description(): string;
}
