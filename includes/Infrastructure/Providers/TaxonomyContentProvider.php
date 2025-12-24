<?php
/**
 * Taxonomy Content Provider
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
use Automattic\MSM_Sitemap\Infrastructure\Repositories\TaxonomyRepository;
use InvalidArgumentException;
use WP_Taxonomy;
use WP_Term;

/**
 * Taxonomy Content Provider
 *
 * Provides URLs for taxonomy term archives (categories, tags, custom taxonomies).
 * Each taxonomy type has its own provider instance.
 */
class TaxonomyContentProvider implements PaginatedContentProviderInterface {

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
	 * The taxonomy slug.
	 *
	 * @var string
	 */
	private string $taxonomy;

	/**
	 * The taxonomy object.
	 *
	 * @var WP_Taxonomy|null
	 */
	private ?WP_Taxonomy $taxonomy_object = null;

	/**
	 * Constructor.
	 *
	 * @param TaxonomyRepository $taxonomy_repository The taxonomy repository.
	 * @param SettingsService    $settings_service    The settings service.
	 * @param string             $taxonomy            The taxonomy slug.
	 */
	public function __construct(
		TaxonomyRepository $taxonomy_repository,
		SettingsService $settings_service,
		string $taxonomy
	) {
		$this->taxonomy_repository = $taxonomy_repository;
		$this->settings_service    = $settings_service;
		$this->taxonomy            = $taxonomy;
	}

	/**
	 * Get the taxonomy object.
	 *
	 * @return WP_Taxonomy|null The taxonomy object or null if not found.
	 */
	private function get_taxonomy_object(): ?WP_Taxonomy {
		if ( null === $this->taxonomy_object ) {
			$taxonomy_object = get_taxonomy( $this->taxonomy );
			if ( $taxonomy_object instanceof WP_Taxonomy ) {
				$this->taxonomy_object = $taxonomy_object;
			}
		}
		return $this->taxonomy_object;
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
		$terms  = $this->taxonomy_repository->get_terms( $this->taxonomy, $offset, $per_page );

		if ( empty( $terms ) ) {
			return UrlSetFactory::create_empty();
		}

		$url_entries = array();
		foreach ( $terms as $term ) {
			$url_entry = $this->create_url_entry_from_term( $term );
			if ( $url_entry ) {
				$url_entries[] = $url_entry;
			}
		}

		return UrlSetFactory::from_entries( $url_entries );
	}

	/**
	 * Create a URL entry from a term.
	 *
	 * @param WP_Term $term The term object.
	 * @return UrlEntry|null The URL entry or null if term should be skipped.
	 */
	private function create_url_entry_from_term( WP_Term $term ): ?UrlEntry {
		/**
		 * Filter whether to skip a term from sitemap.
		 *
		 * @since 2.0.0
		 *
		 * @param bool   $skip     Whether to skip the term. Default false.
		 * @param int    $term_id  The term ID.
		 * @param string $taxonomy The taxonomy.
		 */
		if ( apply_filters( 'msm_sitemap_skip_term', false, $term->term_id, $term->taxonomy ) ) {
			return null;
		}

		$url = $this->taxonomy_repository->get_term_url( $term );
		if ( ! $url ) {
			return null;
		}

		/**
		 * Filter the changefreq for taxonomy term URLs.
		 *
		 * @since 2.0.0
		 *
		 * @param string  $changefreq The changefreq value. Default 'weekly'.
		 * @param WP_Term $term       The term object.
		 */
		$changefreq = apply_filters( 'msm_sitemap_taxonomy_changefreq', 'weekly', $term );

		/**
		 * Filter the priority for taxonomy term URLs.
		 *
		 * @since 2.0.0
		 *
		 * @param float   $priority The priority value. Default 0.5.
		 * @param WP_Term $term     The term object.
		 */
		$priority = apply_filters( 'msm_sitemap_taxonomy_priority', 0.5, $term );

		try {
			return UrlEntryFactory::from_data( $url, null, $changefreq, $priority );
		} catch ( InvalidArgumentException $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'MSM Sitemap: Invalid URL entry for term ' . $term->term_id . ': ' . $e->getMessage() );
			return null;
		}
	}

	/**
	 * Get the content type this provider handles.
	 *
	 * @return string The content type identifier.
	 */
	public function get_content_type(): string {
		return 'taxonomy-' . $this->taxonomy;
	}

	/**
	 * Get the display name for this content provider.
	 *
	 * @return string The localised display name.
	 */
	public function get_display_name(): string {
		$taxonomy_object = $this->get_taxonomy_object();
		if ( $taxonomy_object ) {
			return $taxonomy_object->labels->name ?? ucfirst( $this->taxonomy );
		}
		return ucfirst( $this->taxonomy );
	}

	/**
	 * Get the description for this content provider.
	 *
	 * @return string The localised description.
	 */
	public function get_description(): string {
		return sprintf(
			/* translators: %s: taxonomy name */
			__( 'Include %s archive pages in sitemaps', 'msm-sitemap' ),
			$this->get_display_name()
		);
	}

	/**
	 * Get the total number of terms this provider can return.
	 *
	 * @return int Total term count.
	 */
	public function get_total_count(): int {
		return $this->taxonomy_repository->get_term_count( $this->taxonomy );
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
		return 'taxonomy-' . $this->taxonomy;
	}

	/**
	 * Check if this provider is enabled.
	 *
	 * @return bool True if enabled, false otherwise.
	 */
	public function is_enabled(): bool {
		// Check if taxonomies are enabled globally.
		$include_taxonomies = $this->settings_service->get_setting( 'include_taxonomies', '0' );
		if ( '1' !== $include_taxonomies ) {
			return false;
		}

		// Check if this specific taxonomy is enabled.
		$enabled_taxonomies = $this->settings_service->get_setting(
			'enabled_taxonomies',
			array( 'category', 'post_tag' )
		);

		if ( ! is_array( $enabled_taxonomies ) ) {
			$enabled_taxonomies = array( 'category', 'post_tag' );
		}

		return in_array( $this->taxonomy, $enabled_taxonomies, true );
	}

	/**
	 * Get the taxonomy slug.
	 *
	 * @return string The taxonomy slug.
	 */
	public function get_taxonomy(): string {
		return $this->taxonomy;
	}
}
