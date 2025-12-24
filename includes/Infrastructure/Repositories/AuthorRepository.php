<?php
/**
 * Author Repository
 *
 * @package Automattic\MSM_Sitemap\Infrastructure\Repositories
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Infrastructure\Repositories;

use Automattic\MSM_Sitemap\Domain\Contracts\AuthorRepositoryInterface;
use WP_User;
use WP_User_Query;

/**
 * Author Repository
 *
 * Handles fetching author/user data from WordPress for sitemap generation.
 */
class AuthorRepository implements AuthorRepositoryInterface {

	/**
	 * Get authors who have published posts.
	 *
	 * @param int $offset Number of authors to skip.
	 * @param int $limit  Maximum authors to return.
	 * @return array<WP_User> Array of user objects.
	 */
	public function get_authors_with_posts( int $offset = 0, int $limit = 2000 ): array {
		$args = array(
			'has_published_posts' => true,
			'number'              => $limit,
			'offset'              => $offset,
			'orderby'             => 'post_count',
			'order'               => 'DESC',
			'fields'              => 'all',
		);

		/**
		 * Filter author query arguments for sitemap generation.
		 *
		 * @since 2.0.0
		 *
		 * @param array $args Author query arguments.
		 */
		$args = apply_filters( 'msm_sitemap_author_query_args', $args );

		$query   = new WP_User_Query( $args );
		$authors = $query->get_results();

		if ( empty( $authors ) ) {
			return array();
		}

		// Filter out noindex authors
		return array_values(
			array_filter(
				$authors,
				function ( WP_User $author ): bool {
					return ! $this->is_author_noindex( $author->ID );
				}
			)
		);
	}

	/**
	 * Get total count of authors with published posts.
	 *
	 * @return int Total number of authors.
	 */
	public function get_author_count(): int {
		$args = array(
			'has_published_posts' => true,
			'fields'              => 'ID',
			'count_total'         => true,
		);

		$query = new WP_User_Query( $args );

		return (int) $query->get_total();
	}

	/**
	 * Get the URL for an author's archive page.
	 *
	 * @param WP_User $author The user object.
	 * @return string|null The author archive URL or null if not available.
	 */
	public function get_author_url( WP_User $author ): ?string {
		$url = get_author_posts_url( $author->ID, $author->user_nicename );

		return $url ? $url : null;
	}

	/**
	 * Check if an author should be excluded from sitemaps (noindex).
	 *
	 * @param int $user_id The user ID.
	 * @return bool True if the author should be excluded, false otherwise.
	 */
	public function is_author_noindex( int $user_id ): bool {
		/**
		 * Filter whether an author should be excluded from sitemaps.
		 *
		 * Allows integration with SEO plugins that set noindex on authors.
		 *
		 * @since 2.0.0
		 *
		 * @param bool $noindex Whether the author is noindex. Default false.
		 * @param int  $user_id The user ID.
		 */
		return (bool) apply_filters( 'msm_sitemap_author_noindex', false, $user_id );
	}
}
