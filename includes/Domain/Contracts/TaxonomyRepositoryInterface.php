<?php
/**
 * Taxonomy Repository Interface
 *
 * @package Automattic\MSM_Sitemap\Domain\Contracts
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Domain\Contracts;

use WP_Taxonomy;
use WP_Term;

/**
 * Taxonomy Repository Interface
 *
 * Defines the contract for accessing taxonomy and term data for sitemap generation.
 */
interface TaxonomyRepositoryInterface {

	/**
	 * Get all public taxonomies suitable for sitemap inclusion.
	 *
	 * @return array<string, WP_Taxonomy> Array of taxonomy objects keyed by taxonomy name.
	 */
	public function get_public_taxonomies(): array;

	/**
	 * Get terms for a taxonomy.
	 *
	 * @param string $taxonomy Taxonomy slug.
	 * @param int    $offset   Number of terms to skip.
	 * @param int    $limit    Maximum terms to return.
	 * @return array<WP_Term> Array of term objects.
	 */
	public function get_terms( string $taxonomy, int $offset = 0, int $limit = 2000 ): array;

	/**
	 * Get total term count for a taxonomy.
	 *
	 * Only counts non-empty terms that should appear in sitemaps.
	 *
	 * @param string $taxonomy Taxonomy slug.
	 * @return int Total number of terms.
	 */
	public function get_term_count( string $taxonomy ): int;

	/**
	 * Get the URL for a term.
	 *
	 * @param WP_Term $term The term object.
	 * @return string|null The term URL or null if not available.
	 */
	public function get_term_url( WP_Term $term ): ?string;

	/**
	 * Check if a term should be excluded from sitemaps (noindex).
	 *
	 * @param int $term_id The term ID.
	 * @return bool True if the term should be excluded, false otherwise.
	 */
	public function is_term_noindex( int $term_id ): bool;

	/**
	 * Check if a taxonomy exists and is valid.
	 *
	 * @param string $taxonomy Taxonomy slug.
	 * @return bool True if taxonomy exists and is public.
	 */
	public function taxonomy_exists( string $taxonomy ): bool;
}
