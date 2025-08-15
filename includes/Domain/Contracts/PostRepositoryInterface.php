<?php
/**
 * Interface for post repository operations.
 *
 * @package Automattic\MSM_Sitemap\Domain\Contracts
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Domain\Contracts;

/**
 * Interface for post repository operations.
 */
interface PostRepositoryInterface extends RepositoryInterface {

	/**
	 * Get posts that have been modified since the given timestamp.
	 *
	 * @param int|string|false $since_timestamp Timestamp to check since, or false for last hour.
	 * @return array Array of post objects.
	 */
	public function get_modified_posts_since( $since_timestamp = false ): array;

	/**
	 * Get all unique post publication dates (YYYY-MM-DD format).
	 *
	 * @return array<string> Array of unique publication dates.
	 */
	public function get_all_post_publication_dates(): array;
}
