<?php
/**
 * Interface for image repository operations.
 *
 * @package Automattic\MSM_Sitemap\Domain\Contracts
 */

declare(strict_types=1);

namespace Automattic\MSM_Sitemap\Domain\Contracts;

/**
 * Interface for image repository operations.
 */
interface ImageRepositoryInterface extends RepositoryInterface {

	/**
	 * Get image attachment IDs for posts published on a specific date.
	 *
	 * @param string $date MySQL DATE format (e.g., '2024-01-15').
	 * @param int $limit Maximum number of images to return.
	 * @return array<int> Array of image attachment IDs.
	 */
	public function get_image_ids_for_date( string $date, int $limit = 1000 ): array;

	/**
	 * Get image attachment IDs for specific post IDs.
	 *
	 * @param array<int> $post_ids Array of post IDs.
	 * @param int $limit Maximum number of images to return.
	 * @return array<int> Array of image attachment IDs.
	 */
	public function get_image_ids_for_posts( array $post_ids, int $limit = 1000 ): array;

	/**
	 * Get featured image IDs for specific post IDs.
	 *
	 * @param array<int> $post_ids Array of post IDs.
	 * @return array<int> Array of featured image attachment IDs.
	 */
	public function get_featured_image_ids_for_posts( array $post_ids ): array;

	/**
	 * Get image metadata for attachment IDs.
	 *
	 * @param array<int> $attachment_ids Array of attachment IDs.
	 * @return array<int, array<string, mixed>> Array of image metadata keyed by attachment ID.
	 */
	public function get_image_metadata( array $attachment_ids ): array;

	/**
	 * Get image count for posts published on a specific date.
	 *
	 * @param string $date MySQL DATE format (e.g., '2024-01-15').
	 * @return int Number of images.
	 */
	public function get_image_count_for_date( string $date ): int;

	/**
	 * Check if images should be included in sitemaps.
	 *
	 * @return bool True if images should be included, false otherwise.
	 */
	public function should_include_images(): bool;

	/**
	 * Get the maximum number of images per sitemap.
	 *
	 * @return int Maximum number of images.
	 */
	public function get_max_images_per_sitemap(): int;

	/**
	 * Check if featured images should be included.
	 *
	 * @return bool True if featured images should be included, false otherwise.
	 */
	public function should_include_featured_images(): bool;

	/**
	 * Check if content images should be included.
	 *
	 * @return bool True if content images should be included, false otherwise.
	 */
	public function should_include_content_images(): bool;

	/**
	 * Get image types to include.
	 *
	 * @return array<string> Array of image types to include.
	 */
	public function get_included_image_types(): array;
}


