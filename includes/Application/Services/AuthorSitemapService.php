<?php
/**
 * Author Sitemap Service
 *
 * @package Automattic\MSM_Sitemap\Application\Services
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Application\Services;

use Automattic\MSM_Sitemap\Domain\ValueObjects\SitemapContent;
use Automattic\MSM_Sitemap\Infrastructure\Formatters\SitemapXmlFormatter;
use Automattic\MSM_Sitemap\Infrastructure\Providers\AuthorContentProvider;
use Automattic\MSM_Sitemap\Infrastructure\Repositories\AuthorRepository;

/**
 * Author Sitemap Service
 *
 * Orchestrates author sitemap generation with object caching.
 */
class AuthorSitemapService {

	/**
	 * Cache group for author sitemaps.
	 */
	public const CACHE_GROUP = 'msm_author_sitemaps';

	/**
	 * Default cache TTL in seconds (1 hour).
	 */
	public const DEFAULT_CACHE_TTL = 3600;

	/**
	 * The author repository.
	 *
	 * @var AuthorRepository
	 */
	private AuthorRepository $author_repository;

	/**
	 * The settings service.
	 *
	 * @var SettingsService
	 */
	private SettingsService $settings_service;

	/**
	 * The content provider instance.
	 *
	 * @var AuthorContentProvider|null
	 */
	private ?AuthorContentProvider $provider = null;

	/**
	 * Constructor.
	 *
	 * @param AuthorRepository $author_repository The author repository.
	 * @param SettingsService  $settings_service  The settings service.
	 */
	public function __construct(
		AuthorRepository $author_repository,
		SettingsService $settings_service
	) {
		$this->author_repository = $author_repository;
		$this->settings_service  = $settings_service;
	}

	/**
	 * Check if author sitemaps are enabled.
	 *
	 * @return bool True if enabled, false otherwise.
	 */
	public function is_enabled(): bool {
		return '1' === $this->settings_service->get_setting( 'include_authors', '0' );
	}

	/**
	 * Get the content provider.
	 *
	 * @return AuthorContentProvider The provider instance.
	 */
	public function get_provider(): AuthorContentProvider {
		if ( null === $this->provider ) {
			$this->provider = new AuthorContentProvider(
				$this->author_repository,
				$this->settings_service
			);
		}

		return $this->provider;
	}

	/**
	 * Generate sitemap XML for authors.
	 *
	 * Uses object cache to avoid regenerating on every request.
	 *
	 * @param int $page The page number (1-indexed).
	 * @return string|null The sitemap XML or null if not found/empty.
	 */
	public function generate_sitemap_xml( int $page = 1 ): ?string {
		$provider = $this->get_provider();
		if ( ! $provider->is_enabled() ) {
			return null;
		}

		// Try to get from cache first
		$cache_key = $this->get_cache_key( $page );
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

		// Cache the result with TTL from settings (stored in minutes, converted to seconds)
		$ttl_minutes = $this->settings_service->get_setting( 'author_cache_ttl', SettingsService::DEFAULT_CACHE_TTL_MINUTES );
		$ttl_seconds = $ttl_minutes * 60;

		/**
		 * Filter the cache TTL for author sitemaps.
		 *
		 * @since 2.0.0
		 *
		 * @param int $ttl Cache TTL in seconds.
		 */
		$ttl = apply_filters( 'msm_sitemap_author_cache_ttl', $ttl_seconds );
		wp_cache_set( $cache_key, $xml, self::CACHE_GROUP, $ttl );

		return $xml;
	}

	/**
	 * Get cache key for an author sitemap page.
	 *
	 * @param int $page The page number.
	 * @return string The cache key.
	 */
	private function get_cache_key( int $page ): string {
		return "author_sitemap_{$page}";
	}

	/**
	 * Invalidate all author sitemap cache.
	 */
	public function invalidate_cache(): void {
		$provider   = $this->get_provider();
		$page_count = $provider->get_page_count();

		for ( $page = 1; $page <= max( 1, $page_count ); $page++ ) {
			$cache_key = $this->get_cache_key( $page );
			wp_cache_delete( $cache_key, self::CACHE_GROUP );
		}
	}

	/**
	 * Get sitemap index entries for authors.
	 *
	 * @return array<array{page: int, url: string, lastmod: string}> Array of index entries.
	 */
	public function get_sitemap_index_entries(): array {
		if ( ! $this->is_enabled() ) {
			return array();
		}

		$entries    = array();
		$provider   = $this->get_provider();
		$page_count = $provider->get_page_count();

		if ( 0 === $page_count ) {
			return array();
		}

		for ( $page = 1; $page <= $page_count; $page++ ) {
			$entries[] = array(
				'page'    => $page,
				'url'     => $this->get_author_sitemap_url( $page ),
				'lastmod' => gmdate( 'c' ),
			);
		}

		return $entries;
	}

	/**
	 * Get the URL for an author sitemap.
	 *
	 * @param int $page The page number (1-indexed).
	 * @return string The sitemap URL.
	 */
	public function get_author_sitemap_url( int $page = 1 ): string {
		$suffix = $page > 1 ? "-{$page}" : '';
		return home_url( "/sitemap-author{$suffix}.xml" );
	}
}
