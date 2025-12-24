<?php
/**
 * Taxonomy Repository
 *
 * @package Automattic\MSM_Sitemap\Infrastructure\Repositories
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Infrastructure\Repositories;

use Automattic\MSM_Sitemap\Domain\Contracts\TaxonomyRepositoryInterface;
use WP_Taxonomy;
use WP_Term;

/**
 * Taxonomy Repository
 *
 * Handles fetching taxonomy and term data from WordPress for sitemap generation.
 */
class TaxonomyRepository implements TaxonomyRepositoryInterface {

	/**
	 * Get all public taxonomies suitable for sitemap inclusion.
	 *
	 * @return array<string, WP_Taxonomy> Array of taxonomy objects keyed by taxonomy name.
	 */
	public function get_public_taxonomies(): array {
		$taxonomies = get_taxonomies(
			array(
				'public'             => true,
				'publicly_queryable' => true,
			),
			'objects'
		);

		// Remove post formats - they're not useful for sitemaps.
		unset( $taxonomies['post_format'] );

		/**
		 * Filter the taxonomies included in sitemaps.
		 *
		 * @since 2.0.0
		 *
		 * @param array<string, WP_Taxonomy> $taxonomies Array of WP_Taxonomy objects.
		 */
		return apply_filters( 'msm_sitemap_taxonomies', $taxonomies );
	}

	/**
	 * Get terms for a taxonomy.
	 *
	 * @param string $taxonomy Taxonomy slug.
	 * @param int    $offset   Number of terms to skip.
	 * @param int    $limit    Maximum terms to return.
	 * @return array<WP_Term> Array of term objects.
	 */
	public function get_terms( string $taxonomy, int $offset = 0, int $limit = 2000 ): array {
		$args = array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => true,
			'number'     => $limit,
			'offset'     => $offset,
			'orderby'    => 'count',
			'order'      => 'DESC',
		);

		/**
		 * Filter term query arguments for sitemap generation.
		 *
		 * @since 2.0.0
		 *
		 * @param array  $args     Term query arguments.
		 * @param string $taxonomy Taxonomy slug.
		 */
		$args = apply_filters( 'msm_sitemap_taxonomy_term_args', $args, $taxonomy );

		$terms = get_terms( $args );

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		// Filter out noindex terms.
		return array_values(
			array_filter(
				$terms,
				function ( WP_Term $term ): bool {
					return ! $this->is_term_noindex( $term->term_id );
				}
			)
		);
	}

	/**
	 * Get total term count for a taxonomy.
	 *
	 * @param string $taxonomy Taxonomy slug.
	 * @return int Total number of terms.
	 */
	public function get_term_count( string $taxonomy ): int {
		$count = wp_count_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => true,
			)
		);

		return is_wp_error( $count ) ? 0 : (int) $count;
	}

	/**
	 * Get the URL for a term.
	 *
	 * @param WP_Term $term The term object.
	 * @return string|null The term URL or null if not available.
	 */
	public function get_term_url( WP_Term $term ): ?string {
		$url = get_term_link( $term );
		return is_wp_error( $url ) ? null : $url;
	}

	/**
	 * Check if a term should be excluded from sitemaps (noindex).
	 *
	 * @param int $term_id The term ID.
	 * @return bool True if the term should be excluded, false otherwise.
	 */
	public function is_term_noindex( int $term_id ): bool {
		/**
		 * Filter whether a term should be excluded from sitemaps.
		 *
		 * Allows integration with SEO plugins that set noindex on terms.
		 *
		 * @since 2.0.0
		 *
		 * @param bool $noindex Whether the term is noindex. Default false.
		 * @param int  $term_id The term ID.
		 */
		return (bool) apply_filters( 'msm_sitemap_term_noindex', false, $term_id );
	}

	/**
	 * Check if a taxonomy exists and is valid.
	 *
	 * @param string $taxonomy Taxonomy slug.
	 * @return bool True if taxonomy exists and is public.
	 */
	public function taxonomy_exists( string $taxonomy ): bool {
		$taxonomy_object = get_taxonomy( $taxonomy );

		if ( ! $taxonomy_object ) {
			return false;
		}

		return $taxonomy_object->public && $taxonomy_object->publicly_queryable;
	}
}
