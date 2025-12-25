<?php
/**
 * Page Content Provider
 *
 * @package Automattic\MSM_Sitemap\Infrastructure\Providers
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Infrastructure\Providers;

use Automattic\MSM_Sitemap\Application\Services\SettingsService;
use Automattic\MSM_Sitemap\Domain\Contracts\PaginatedContentProviderInterface;
use Automattic\MSM_Sitemap\Domain\ValueObjects\UrlEntry;
use Automattic\MSM_Sitemap\Domain\ValueObjects\UrlSet;
use Automattic\MSM_Sitemap\Infrastructure\Factories\UrlEntryFactory;
use Automattic\MSM_Sitemap\Infrastructure\Factories\UrlSetFactory;
use Automattic\MSM_Sitemap\Infrastructure\Repositories\PageRepository;
use InvalidArgumentException;
use WP_Post;

/**
 * Page Content Provider
 *
 * Provides URLs for published pages.
 */
class PageContentProvider implements PaginatedContentProviderInterface {

	/**
	 * The page repository.
	 *
	 * @var PageRepository
	 */
	private PageRepository $page_repository;

	/**
	 * The settings service.
	 *
	 * @var SettingsService
	 */
	private SettingsService $settings_service;

	/**
	 * Constructor.
	 *
	 * @param PageRepository  $page_repository  The page repository.
	 * @param SettingsService $settings_service The settings service.
	 */
	public function __construct(
		PageRepository $page_repository,
		SettingsService $settings_service
	) {
		$this->page_repository  = $page_repository;
		$this->settings_service = $settings_service;
	}

	/**
	 * Get URLs for a specific page.
	 *
	 * @param int $page     Page number (1-indexed).
	 * @param int $per_page Number of URLs per page.
	 * @return UrlSet Collection of URL entries for the page.
	 */
	public function get_urls( int $page = 1, int $per_page = self::DEFAULT_PER_PAGE ): UrlSet {
		if ( ! $this->is_enabled() ) {
			return UrlSetFactory::create_empty();
		}

		$offset = ( $page - 1 ) * $per_page;
		$pages  = $this->page_repository->get_pages( $offset, $per_page );

		if ( empty( $pages ) ) {
			return UrlSetFactory::create_empty();
		}

		$url_entries = array();
		foreach ( $pages as $page_post ) {
			$url_entry = $this->create_url_entry_from_page( $page_post );
			if ( $url_entry ) {
				$url_entries[] = $url_entry;
			}
		}

		return UrlSetFactory::from_entries( $url_entries );
	}

	/**
	 * Create a URL entry from a page.
	 *
	 * @param WP_Post $page The post object.
	 * @return UrlEntry|null The URL entry or null if page should be skipped.
	 */
	private function create_url_entry_from_page( WP_Post $page ): ?UrlEntry {
		/**
		 * Filter whether to skip a page from sitemap.
		 *
		 * @since 2.0.0
		 *
		 * @param bool $skip    Whether to skip the page. Default false.
		 * @param int  $post_id The post ID.
		 */
		if ( apply_filters( 'msm_sitemap_skip_page', false, $page->ID ) ) {
			return null;
		}

		$url = $this->page_repository->get_page_url( $page );
		if ( ! $url ) {
			return null;
		}

		$lastmod = $this->page_repository->get_page_lastmod( $page );

		/**
		 * Filter the changefreq for page URLs.
		 *
		 * @since 2.0.0
		 *
		 * @param string  $changefreq The changefreq value. Default 'weekly'.
		 * @param WP_Post $page       The post object.
		 */
		$changefreq = apply_filters( 'msm_sitemap_page_changefreq', 'weekly', $page );

		/**
		 * Filter the priority for page URLs.
		 *
		 * @since 2.0.0
		 *
		 * @param float   $priority The priority value. Default 0.6.
		 * @param WP_Post $page     The post object.
		 */
		$priority = apply_filters( 'msm_sitemap_page_priority', 0.6, $page );

		try {
			return UrlEntryFactory::from_data( $url, $lastmod, $changefreq, $priority );
		} catch ( InvalidArgumentException $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'MSM Sitemap: Invalid URL entry for page ' . $page->ID . ': ' . $e->getMessage() );
			return null;
		}
	}

	/**
	 * Get the content type this provider handles.
	 *
	 * @return string The content type identifier.
	 */
	public function get_content_type(): string {
		return 'page';
	}

	/**
	 * Get the display name for this content provider.
	 *
	 * @return string The localised display name.
	 */
	public function get_display_name(): string {
		return __( 'Pages', 'msm-sitemap' );
	}

	/**
	 * Get the description for this content provider.
	 *
	 * @return string The localised description.
	 */
	public function get_description(): string {
		return __( 'Include static pages in sitemaps', 'msm-sitemap' );
	}

	/**
	 * Get the total number of pages this provider can return.
	 *
	 * @return int Total page count.
	 */
	public function get_total_count(): int {
		return $this->page_repository->get_page_count();
	}

	/**
	 * Get the number of pages for this sitemap.
	 *
	 * @param int $per_page Number of URLs per page.
	 * @return int Number of pages.
	 */
	public function get_page_count( int $per_page = self::DEFAULT_PER_PAGE ): int {
		$total = $this->get_total_count();
		if ( 0 === $total ) {
			return 0;
		}
		return (int) ceil( $total / $per_page );
	}

	/**
	 * Get the sitemap slug for URL generation.
	 *
	 * @return string The sitemap slug.
	 */
	public function get_sitemap_slug(): string {
		return 'page';
	}

	/**
	 * Check if this provider is enabled.
	 *
	 * @return bool True if enabled, false otherwise.
	 */
	public function is_enabled(): bool {
		return '1' === $this->settings_service->get_setting( 'include_pages', '0' );
	}
}
