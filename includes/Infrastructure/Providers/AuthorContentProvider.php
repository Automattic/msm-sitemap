<?php
/**
 * Author Content Provider
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
use Automattic\MSM_Sitemap\Infrastructure\Repositories\AuthorRepository;
use InvalidArgumentException;
use WP_User;

/**
 * Author Content Provider
 *
 * Provides URLs for author archive pages.
 */
class AuthorContentProvider implements PaginatedContentProviderInterface {

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

		$offset  = ( $page - 1 ) * $per_page;
		$authors = $this->author_repository->get_authors_with_posts( $offset, $per_page );

		if ( empty( $authors ) ) {
			return UrlSetFactory::create_empty();
		}

		$url_entries = array();
		foreach ( $authors as $author ) {
			$url_entry = $this->create_url_entry_from_author( $author );
			if ( $url_entry ) {
				$url_entries[] = $url_entry;
			}
		}

		return UrlSetFactory::from_entries( $url_entries );
	}

	/**
	 * Create a URL entry from an author.
	 *
	 * @param WP_User $author The user object.
	 * @return UrlEntry|null The URL entry or null if author should be skipped.
	 */
	private function create_url_entry_from_author( WP_User $author ): ?UrlEntry {
		/**
		 * Filter whether to skip an author from sitemap.
		 *
		 * @since 2.0.0
		 *
		 * @param bool $skip    Whether to skip the author. Default false.
		 * @param int  $user_id The user ID.
		 */
		if ( apply_filters( 'msm_sitemap_skip_author', false, $author->ID ) ) {
			return null;
		}

		$url = $this->author_repository->get_author_url( $author );
		if ( ! $url ) {
			return null;
		}

		/**
		 * Filter the changefreq for author URLs.
		 *
		 * @since 2.0.0
		 *
		 * @param string  $changefreq The changefreq value. Default 'weekly'.
		 * @param WP_User $author     The user object.
		 */
		$changefreq = apply_filters( 'msm_sitemap_author_changefreq', 'weekly', $author );

		/**
		 * Filter the priority for author URLs.
		 *
		 * @since 2.0.0
		 *
		 * @param float   $priority The priority value. Default 0.5.
		 * @param WP_User $author   The user object.
		 */
		$priority = apply_filters( 'msm_sitemap_author_priority', 0.5, $author );

		try {
			return UrlEntryFactory::from_data( $url, null, $changefreq, $priority );
		} catch ( InvalidArgumentException $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'MSM Sitemap: Invalid URL entry for author ' . $author->ID . ': ' . $e->getMessage() );
			return null;
		}
	}

	/**
	 * Get the content type this provider handles.
	 *
	 * @return string The content type identifier.
	 */
	public function get_content_type(): string {
		return 'author';
	}

	/**
	 * Get the display name for this content provider.
	 *
	 * @return string The localised display name.
	 */
	public function get_display_name(): string {
		return __( 'Authors', 'msm-sitemap' );
	}

	/**
	 * Get the description for this content provider.
	 *
	 * @return string The localised description.
	 */
	public function get_description(): string {
		return __( 'Include author archive pages in sitemaps', 'msm-sitemap' );
	}

	/**
	 * Get the total number of authors this provider can return.
	 *
	 * @return int Total author count.
	 */
	public function get_total_count(): int {
		return $this->author_repository->get_author_count();
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
		return 'author';
	}

	/**
	 * Check if this provider is enabled.
	 *
	 * @return bool True if enabled, false otherwise.
	 */
	public function is_enabled(): bool {
		return '1' === $this->settings_service->get_setting( 'include_authors', '0' );
	}
}
