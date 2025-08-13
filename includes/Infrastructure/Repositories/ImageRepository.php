<?php
/**
 * Image Repository
 *
 * @package Automattic\MSM_Sitemap\Infrastructure\Repositories
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Infrastructure\Repositories;

/**
 * Image Repository
 *
 * Handles fetching image attachments from WordPress posts for sitemap generation.
 * Provides methods to retrieve images associated with posts for specific dates.
 */
class ImageRepository {

	/**
	 * Get image attachment IDs for posts published on a specific date.
	 *
	 * @param string $date MySQL DATE format (e.g., '2024-01-15').
	 * @param int    $limit Maximum number of images to return.
	 * @return array<int> Array of image attachment IDs.
	 */
	public function get_image_ids_for_date( string $date, int $limit = 1000 ): array {
		// Get post IDs for the date first
		$post_ids = $this->get_post_ids_for_date( $date );
		
		if ( empty( $post_ids ) ) {
			return array();
		}

		// Get image attachments for these posts
		return $this->get_image_ids_for_posts( $post_ids, $limit );
	}

	/**
	 * Get image attachment IDs for specific post IDs.
	 *
	 * @param array<int> $post_ids Array of post IDs.
	 * @param int        $limit    Maximum number of images to return.
	 * @return array<int> Array of image attachment IDs.
	 */
	public function get_image_ids_for_posts( array $post_ids, int $limit = 1000 ): array {
		if ( empty( $post_ids ) ) {
			return array();
		}

		// Use WordPress functions instead of complex SQL
		$image_ids = array();
		
		foreach ( $post_ids as $post_id ) {
			$attachments = get_attached_media( 'image', $post_id );
			$featured_image_id = get_post_thumbnail_id( $post_id );
			
			foreach ( $attachments as $attachment ) {
				// Skip featured images - they should be handled separately
				if ( $attachment->ID === $featured_image_id ) {
					continue;
				}
				
				$image_ids[] = $attachment->ID;
				if ( count( $image_ids ) >= $limit ) {
					break 2;
				}
			}
		}
		
		return array_slice( $image_ids, 0, $limit );
	}

	/**
	 * Get featured image IDs for specific post IDs.
	 *
	 * @param array<int> $post_ids Array of post IDs.
	 * @return array<int> Array of featured image attachment IDs.
	 */
	public function get_featured_image_ids_for_posts( array $post_ids ): array {
		if ( empty( $post_ids ) ) {
			return array();
		}

		$featured_image_ids = array();
		
		foreach ( $post_ids as $post_id ) {
			$thumbnail_id = get_post_thumbnail_id( $post_id );
			if ( $thumbnail_id ) {
				$featured_image_ids[] = $thumbnail_id;
			}
		}
		
		return $featured_image_ids;
	}

	/**
	 * Get image metadata for attachment IDs.
	 *
	 * @param array<int> $attachment_ids Array of attachment IDs.
	 * @return array<int, array<string, mixed>> Array of image metadata keyed by attachment ID.
	 */
	public function get_image_metadata( array $attachment_ids ): array {
		if ( empty( $attachment_ids ) ) {
			return array();
		}

		$metadata = array();

		foreach ( $attachment_ids as $attachment_id ) {
			$attachment = get_post( $attachment_id );
			if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
				continue;
			}

			// Get image URL
			$image_url = wp_get_attachment_url( $attachment_id );
			if ( ! $image_url ) {
				continue;
			}

			// Get image metadata
			$attachment_metadata = wp_get_attachment_metadata( $attachment_id );
			
			$title = $attachment->post_title;
			if ( empty( $title ) ) {
				$title = '';
			}
			
			$caption = $attachment->post_excerpt;
			if ( empty( $caption ) ) {
				$caption = '';
			}
			
			$alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
			if ( empty( $alt ) ) {
				$alt = '';
			}
			
			$width = 0;
			if ( isset( $attachment_metadata['width'] ) ) {
				$width = $attachment_metadata['width'];
			}
			
			$height = 0;
			if ( isset( $attachment_metadata['height'] ) ) {
				$height = $attachment_metadata['height'];
			}
			
			$file = '';
			if ( isset( $attachment_metadata['file'] ) ) {
				$file = $attachment_metadata['file'];
			}
			
			$metadata[ $attachment_id ] = array(
				'url'     => $image_url,
				'title'   => $title,
				'caption' => $caption,
				'alt'     => $alt,
				'width'   => $width,
				'height'  => $height,
				'file'    => $file,
			);
		}

		return $metadata;
	}

	/**
	 * Get post IDs for a specific date.
	 *
	 * @param string $date MySQL DATE format (e.g., '2024-01-15').
	 * @return array<int> Array of post IDs.
	 */
	private function get_post_ids_for_date( string $date ): array {
		global $wpdb;

		$results = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID 
				FROM {$wpdb->posts} 
				WHERE post_type = 'post' 
				AND post_status = 'publish' 
				AND DATE(post_date) = %s 
				ORDER BY ID",
				$date
			)
		);
		
		return array_map( 'intval', $results );
	}

	/**
	 * Get image count for posts published on a specific date.
	 *
	 * @param string $date MySQL DATE format (e.g., '2024-01-15').
	 * @return int Number of images.
	 */
	public function get_image_count_for_date( string $date ): int {
		$image_ids = $this->get_image_ids_for_date( $date );
		return count( $image_ids );
	}

	/**
	 * Check if images should be included in sitemaps.
	 *
	 * @return bool True if images should be included, false otherwise.
	 */
	public function should_include_images(): bool {
		$saved_enabled = get_option( 'msm_sitemap_images_provider_enabled' );
		// If option doesn't exist, default to true
		if ( false === $saved_enabled ) {
			$saved_enabled = '1';
		}
		$filtered_enabled = apply_filters( 'msm_sitemap_images_provider_enabled', '1' === $saved_enabled );
		return (bool) $filtered_enabled;
	}

	/**
	 * Get the maximum number of images per sitemap.
	 *
	 * @return int Maximum number of images.
	 */
	public function get_max_images_per_sitemap(): int {
		$default_max  = 1000;
		$saved_max    = get_option( 'msm_sitemap_max_images_per_sitemap', $default_max );
		$filtered_max = apply_filters( 'msm_sitemap_max_images_per_sitemap', $saved_max );
		return $filtered_max;
	}

	/**
	 * Check if featured images should be included.
	 *
	 * @return bool True if featured images should be included, false otherwise.
	 */
	public function should_include_featured_images(): bool {
		$saved_enabled = get_option( 'msm_sitemap_include_featured_images' );
		// If option doesn't exist, default to true
		if ( false === $saved_enabled ) {
			$saved_enabled = '1';
		}
		$filtered_enabled = apply_filters( 'msm_sitemap_include_featured_images', '1' === $saved_enabled );
		return (bool) $filtered_enabled;
	}

	/**
	 * Check if content images should be included.
	 *
	 * @return bool True if content images should be included, false otherwise.
	 */
	public function should_include_content_images(): bool {
		$saved_enabled = get_option( 'msm_sitemap_include_content_images' );
		// If option doesn't exist, default to true
		if ( false === $saved_enabled ) {
			$saved_enabled = '1';
		}
		$filtered_enabled = apply_filters( 'msm_sitemap_include_content_images', '1' === $saved_enabled );
		return (bool) $filtered_enabled;
	}

	/**
	 * Get image types to include.
	 *
	 * @return array<string> Array of image types to include.
	 */
	public function get_included_image_types(): array {
		$types = array();
		
		if ( $this->should_include_featured_images() ) {
			$types[] = 'featured';
		}
		
		if ( $this->should_include_content_images() ) {
			$types[] = 'content';
		}
		
		return apply_filters( 'msm_sitemap_image_types', $types );
	}
}
