<?php
/**
 * Author Repository Interface
 *
 * @package Automattic\MSM_Sitemap\Domain\Contracts
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Domain\Contracts;

use WP_User;

/**
 * Interface for author/user data access for sitemap generation.
 */
interface AuthorRepositoryInterface {

	/**
	 * Get authors who have published posts.
	 *
	 * @param int $offset Number of authors to skip.
	 * @param int $limit  Maximum authors to return.
	 * @return array<WP_User> Array of user objects.
	 */
	public function get_authors_with_posts( int $offset = 0, int $limit = 2000 ): array;

	/**
	 * Get total count of authors with published posts.
	 *
	 * @return int Total number of authors.
	 */
	public function get_author_count(): int;

	/**
	 * Get the URL for an author's archive page.
	 *
	 * @param WP_User $author The user object.
	 * @return string|null The author archive URL or null if not available.
	 */
	public function get_author_url( WP_User $author ): ?string;

	/**
	 * Check if an author should be excluded from sitemaps (noindex).
	 *
	 * @param int $user_id The user ID.
	 * @return bool True if the author should be excluded, false otherwise.
	 */
	public function is_author_noindex( int $user_id ): bool;
}
