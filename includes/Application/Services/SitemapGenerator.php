<?php
/**
 * Sitemap Generator Service
 *
 * @package Automattic\MSM_Sitemap\Application\Services
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Application\Services;

use Automattic\MSM_Sitemap\Domain\Contracts\ContentProviderInterface;
use Automattic\MSM_Sitemap\Domain\ValueObjects\SitemapContent;
use Automattic\MSM_Sitemap\Domain\ValueObjects\SitemapContentTypes;

/**
 * Sitemap Generator Service
 *
 * Demonstrates how the ContentProviderInterface pattern enables comprehensive sitemaps.
 * This service will eventually handle all content types (posts, images, taxonomies, users).
 */
class SitemapGenerator {

	/**
	 * Content types collection.
	 *
	 * @var SitemapContentTypes
	 */
	private SitemapContentTypes $content_types;

	/**
	 * Constructor.
	 *
	 * @param SitemapContentTypes|null $content_types Content types collection.
	 */
	public function __construct( ?SitemapContentTypes $content_types = null ) {
		$this->content_types = $content_types ?? new SitemapContentTypes();
	}

	/**
	 * Generate a comprehensive sitemap for a specific date.
	 *
	 * @param string $date MySQL DATETIME format (e.g., '2024-01-15 00:00:00').
	 * @return SitemapContent Combined URL set from all registered content providers.
	 */
		public function generate_sitemap_for_date( string $date ): SitemapContent {
		$combined_entries = array();
		$providers = $this->content_types->get_all();

		foreach ( $providers as $provider ) {
			$provider_url_set = $provider->get_urls_for_date( $date );

			// Merge the provider's URLs into the combined set
			foreach ( $provider_url_set->get_entries() as $url_entry ) {
				$combined_entries[] = $url_entry;
			}
		}

		return new SitemapContent( $combined_entries );
	}

	/**
	 * Get all registered content providers.
	 *
	 * @return array<ContentProviderInterface> Array of registered content providers.
	 */
	public function get_providers(): array {
		return $this->content_types->get_all();
	}

	/**
	 * Get all content providers with their status.
	 *
	 * @return array<string, array<string, mixed>> Array of provider information.
	 */
	public function get_provider_status(): array {
		$status = array();

		foreach ( $this->content_types->get_all() as $provider ) {
			$status[ $provider->get_content_type() ] = array(
				'display_name' => $provider->get_display_name(),
				'description'  => $provider->get_description(),
			);
		}

		return $status;
	}

	/**
	 * Add a content provider.
	 *
	 * @param ContentProviderInterface $provider The content provider to add.
	 */
	public function add_provider( ContentProviderInterface $provider ): void {
		$this->content_types->register( $provider );
	}

	/**
	 * Remove a content provider by content type.
	 *
	 * @param string $content_type The content type to remove.
	 */
	public function remove_provider_by_type( string $content_type ): void {
		$this->content_types->unregister( $content_type );
	}
}
