<?php
/**
 * Taxonomy Sitemap Cache Invalidator
 *
 * @package Automattic\MSM_Sitemap\Infrastructure\WordPress
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Infrastructure\WordPress;

use Automattic\MSM_Sitemap\Application\Services\TaxonomySitemapService;
use Automattic\MSM_Sitemap\Domain\Contracts\WordPressIntegrationInterface;

/**
 * Handles cache invalidation for taxonomy sitemaps.
 *
 * Only invalidates on structural changes that affect the sitemap:
 * - Term created (new term might appear in sitemap)
 * - Term deleted (term removed from sitemap)
 * - Term slug changed (URL in sitemap changes)
 *
 * Does NOT invalidate on post saves - taxonomy sitemaps list terms, not posts.
 * Adding more posts to an existing term doesn't change the sitemap.
 * Uses TTL-based expiry for eventual consistency.
 */
class TaxonomySitemapCacheInvalidator implements WordPressIntegrationInterface {

	/**
	 * The taxonomy sitemap service.
	 *
	 * @var TaxonomySitemapService
	 */
	private TaxonomySitemapService $taxonomy_sitemap_service;

	/**
	 * Constructor.
	 *
	 * @param TaxonomySitemapService $taxonomy_sitemap_service The taxonomy sitemap service.
	 */
	public function __construct( TaxonomySitemapService $taxonomy_sitemap_service ) {
		$this->taxonomy_sitemap_service = $taxonomy_sitemap_service;
	}

	/**
	 * Register WordPress hooks.
	 */
	public function register_hooks(): void {
		// Invalidate when a term is created (might appear in sitemap if posts assigned later)
		add_action( 'created_term', array( $this, 'on_term_created' ), 10, 3 );

		// Invalidate when a term is deleted (removed from sitemap)
		add_action( 'delete_term', array( $this, 'on_term_deleted' ), 10, 4 );

		// Invalidate when a term is edited (slug might have changed)
		add_action( 'edited_term', array( $this, 'on_term_edited' ), 10, 3 );
	}

	/**
	 * Handle term created.
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy slug.
	 */
	public function on_term_created( int $term_id, int $tt_id, string $taxonomy ): void {
		$this->maybe_invalidate_taxonomy( $taxonomy );
	}

	/**
	 * Handle term deleted.
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy slug.
	 * @param mixed  $deleted_term Deleted term object.
	 */
	public function on_term_deleted( int $term_id, int $tt_id, string $taxonomy, $deleted_term ): void {
		$this->maybe_invalidate_taxonomy( $taxonomy );
	}

	/**
	 * Handle term edited.
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy slug.
	 */
	public function on_term_edited( int $term_id, int $tt_id, string $taxonomy ): void {
		$this->maybe_invalidate_taxonomy( $taxonomy );
	}

	/**
	 * Invalidate cache for a taxonomy if it's enabled for sitemaps.
	 *
	 * @param string $taxonomy Taxonomy slug.
	 */
	private function maybe_invalidate_taxonomy( string $taxonomy ): void {
		if ( ! $this->taxonomy_sitemap_service->is_enabled() ) {
			return;
		}

		$enabled_taxonomies = $this->taxonomy_sitemap_service->get_enabled_taxonomies();
		$enabled_slugs      = array_map(
			function ( $tax ) {
				return $tax->name;
			},
			$enabled_taxonomies
		);

		if ( in_array( $taxonomy, $enabled_slugs, true ) ) {
			$this->taxonomy_sitemap_service->invalidate_taxonomy_cache( $taxonomy );
		}
	}
}
