<?php
/**
 * Taxonomy Sitemap Service
 *
 * @package Automattic\MSM_Sitemap\Application\Services
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Application\Services;

use Automattic\MSM_Sitemap\Domain\Contracts\PaginatedContentProviderInterface;
use Automattic\MSM_Sitemap\Domain\ValueObjects\SitemapContent;
use Automattic\MSM_Sitemap\Infrastructure\Formatters\SitemapXmlFormatter;
use Automattic\MSM_Sitemap\Infrastructure\Providers\TaxonomyContentProvider;
use Automattic\MSM_Sitemap\Infrastructure\Repositories\TaxonomyRepository;
use WP_Taxonomy;

/**
 * Taxonomy Sitemap Service
 *
 * Orchestrates taxonomy sitemap generation and manages enabled taxonomies.
 */
class TaxonomySitemapService {

	/**
	 * Cache group for taxonomy sitemaps.
	 */
	public const CACHE_GROUP = 'msm_taxonomy_sitemaps';

	/**
	 * Default cache TTL in seconds (1 hour).
	 */
	public const DEFAULT_CACHE_TTL = 3600;

	/**
	 * The taxonomy repository.
	 *
	 * @var TaxonomyRepository
	 */
	private TaxonomyRepository $taxonomy_repository;

	/**
	 * The settings service.
	 *
	 * @var SettingsService
	 */
	private SettingsService $settings_service;

	/**
	 * Cache of taxonomy content providers.
	 *
	 * @var array<string, TaxonomyContentProvider>
	 */
	private array $providers = array();

	/**
	 * Constructor.
	 *
	 * @param TaxonomyRepository $taxonomy_repository The taxonomy repository.
	 * @param SettingsService    $settings_service    The settings service.
	 */
	public function __construct(
		TaxonomyRepository $taxonomy_repository,
		SettingsService $settings_service
	) {
		$this->taxonomy_repository = $taxonomy_repository;
		$this->settings_service    = $settings_service;
	}

	/**
	 * Check if taxonomy sitemaps are enabled.
	 *
	 * @return bool True if enabled, false otherwise.
	 */
	public function is_enabled(): bool {
		return '1' === $this->settings_service->get_setting( 'include_taxonomies', '0' );
	}

	/**
	 * Get all public taxonomies available for sitemap inclusion.
	 *
	 * @return array<string, WP_Taxonomy> Array of taxonomy objects.
	 */
	public function get_available_taxonomies(): array {
		return $this->taxonomy_repository->get_public_taxonomies();
	}

	/**
	 * Get enabled taxonomies for sitemap inclusion.
	 *
	 * @return array<string, WP_Taxonomy> Array of enabled taxonomy objects.
	 */
	public function get_enabled_taxonomies(): array {
		if ( ! $this->is_enabled() ) {
			return array();
		}

		$all_taxonomies     = $this->get_available_taxonomies();
		$enabled_taxonomies = $this->settings_service->get_setting(
			'enabled_taxonomies',
			array( 'category', 'post_tag' )
		);

		if ( ! is_array( $enabled_taxonomies ) ) {
			$enabled_taxonomies = array( 'category', 'post_tag' );
		}

		return array_filter(
			$all_taxonomies,
			function ( WP_Taxonomy $taxonomy ) use ( $enabled_taxonomies ): bool {
				return in_array( $taxonomy->name, $enabled_taxonomies, true );
			}
		);
	}

	/**
	 * Get a content provider for a specific taxonomy.
	 *
	 * @param string $taxonomy The taxonomy slug.
	 * @return TaxonomyContentProvider|null The provider or null if taxonomy is invalid.
	 */
	public function get_provider( string $taxonomy ): ?TaxonomyContentProvider {
		if ( ! $this->taxonomy_repository->taxonomy_exists( $taxonomy ) ) {
			return null;
		}

		if ( ! isset( $this->providers[ $taxonomy ] ) ) {
			$this->providers[ $taxonomy ] = new TaxonomyContentProvider(
				$this->taxonomy_repository,
				$this->settings_service,
				$taxonomy
			);
		}

		return $this->providers[ $taxonomy ];
	}

	/**
	 * Generate sitemap XML for a taxonomy.
	 *
	 * Uses object cache to avoid regenerating on every request.
	 *
	 * @param string $taxonomy The taxonomy slug.
	 * @param int    $page     The page number (1-indexed).
	 * @return string|null The sitemap XML or null if not found/empty.
	 */
	public function generate_sitemap_xml( string $taxonomy, int $page = 1 ): ?string {
		$provider = $this->get_provider( $taxonomy );
		if ( ! $provider || ! $provider->is_enabled() ) {
			return null;
		}

		// Try to get from cache first
		$cache_key = $this->get_cache_key( $taxonomy, $page );
		$cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false !== $cached ) {
			return $cached;
		}

		// Generate fresh XML
		$url_set = $provider->get_urls( $page );
		if ( $url_set->is_empty() ) {
			return null;
		}

		// Convert UrlSet to SitemapContent for the formatter
		$sitemap_content = new SitemapContent( $url_set->get_entries() );

		$formatter = new SitemapXmlFormatter();
		$xml       = $formatter->format( $sitemap_content );

		// Cache the result with TTL
		/**
		 * Filter the cache TTL for taxonomy sitemaps.
		 *
		 * @since 2.0.0
		 *
		 * @param int    $ttl      Cache TTL in seconds. Default 3600 (1 hour).
		 * @param string $taxonomy The taxonomy slug.
		 */
		$ttl = apply_filters( 'msm_sitemap_taxonomy_cache_ttl', self::DEFAULT_CACHE_TTL, $taxonomy );
		wp_cache_set( $cache_key, $xml, self::CACHE_GROUP, $ttl );

		return $xml;
	}

	/**
	 * Get cache key for a taxonomy sitemap.
	 *
	 * @param string $taxonomy The taxonomy slug.
	 * @param int    $page     The page number.
	 * @return string The cache key.
	 */
	private function get_cache_key( string $taxonomy, int $page ): string {
		return "taxonomy_sitemap_{$taxonomy}_{$page}";
	}

	/**
	 * Invalidate cache for a specific taxonomy.
	 *
	 * @param string $taxonomy The taxonomy slug.
	 */
	public function invalidate_taxonomy_cache( string $taxonomy ): void {
		$provider = $this->get_provider( $taxonomy );
		if ( ! $provider ) {
			return;
		}

		$page_count = $provider->get_page_count();
		for ( $page = 1; $page <= max( 1, $page_count ); $page++ ) {
			$cache_key = $this->get_cache_key( $taxonomy, $page );
			wp_cache_delete( $cache_key, self::CACHE_GROUP );
		}
	}

	/**
	 * Invalidate cache for all enabled taxonomies.
	 */
	public function invalidate_all_cache(): void {
		$taxonomies = $this->get_enabled_taxonomies();
		foreach ( $taxonomies as $taxonomy ) {
			$this->invalidate_taxonomy_cache( $taxonomy->name );
		}
	}

	/**
	 * Get sitemap index entries for all enabled taxonomies.
	 *
	 * Returns an array of entries to be included in the main sitemap index.
	 *
	 * @return array<array{taxonomy: string, page: int, url: string, lastmod: string|null}> Array of index entries.
	 */
	public function get_sitemap_index_entries(): array {
		if ( ! $this->is_enabled() ) {
			return array();
		}

		$entries            = array();
		$enabled_taxonomies = $this->get_enabled_taxonomies();

		foreach ( $enabled_taxonomies as $taxonomy ) {
			$provider   = $this->get_provider( $taxonomy->name );
			$page_count = $provider ? $provider->get_page_count() : 0;

			if ( 0 === $page_count ) {
				continue;
			}

			for ( $page = 1; $page <= $page_count; $page++ ) {
				$entries[] = array(
					'taxonomy' => $taxonomy->name,
					'page'     => $page,
					'url'      => $this->get_taxonomy_sitemap_url( $taxonomy->name, $page ),
					'lastmod'  => gmdate( 'c' ), // Current timestamp as fallback.
				);
			}
		}

		return $entries;
	}

	/**
	 * Get the URL for a taxonomy sitemap.
	 *
	 * @param string $taxonomy The taxonomy slug.
	 * @param int    $page     The page number (1-indexed).
	 * @return string The sitemap URL.
	 */
	public function get_taxonomy_sitemap_url( string $taxonomy, int $page = 1 ): string {
		$suffix = $page > 1 ? "-{$page}" : '';
		return home_url( "/sitemap-taxonomy-{$taxonomy}{$suffix}.xml" );
	}

	/**
	 * Parse a taxonomy sitemap URL to extract taxonomy and page.
	 *
	 * @param string $request_uri The request URI.
	 * @return array{taxonomy: string, page: int}|null Parsed data or null if not a taxonomy sitemap.
	 */
	public function parse_taxonomy_sitemap_url( string $request_uri ): ?array {
		// Match /sitemap-taxonomy-{slug}.xml or /sitemap-taxonomy-{slug}-{page}.xml
		if ( preg_match( '/\/sitemap-taxonomy-([a-z0-9_-]+)(?:-(\d+))?\.xml$/i', $request_uri, $matches ) ) {
			return array(
				'taxonomy' => $matches[1],
				'page'     => isset( $matches[2] ) ? (int) $matches[2] : 1,
			);
		}

		return null;
	}
}
