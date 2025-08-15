<?php
/**
 * Image Entry Factory
 *
 * @package Automattic\MSM_Sitemap\Infrastructure\Factories
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Infrastructure\Factories;

use Automattic\MSM_Sitemap\Domain\ValueObjects\ImageEntry;
use InvalidArgumentException;

/**
 * Factory for creating ImageEntry objects from WordPress data.
 *
 * Handles WordPress-specific concerns like attachment retrieval and metadata
 * while keeping the domain layer pure.
 */
class ImageEntryFactory {

	/**
	 * Create an ImageEntry from a WordPress attachment ID.
	 *
	 * @param int $attachment_id The attachment ID.
	 * @return ImageEntry|null The image entry or null if attachment should be skipped.
	 */
	public static function from_attachment_id( int $attachment_id ): ?ImageEntry {
		$attachment = get_post( $attachment_id );
		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return null;
		}

		// Check if image should be skipped
		if ( apply_filters( 'msm_sitemap_skip_image', false, $attachment_id ) ) {
			return null;
		}

		// Get image URL
		$image_url = wp_get_attachment_url( $attachment_id );
		if ( ! $image_url ) {
			return null;
		}

		// Get image metadata
		$attachment_metadata = wp_get_attachment_metadata( $attachment_id );
		
		// Prepare image data
		$title = $attachment->post_title;
		if ( empty( $title ) ) {
			$title = null;
		}
		
		$caption = $attachment->post_excerpt;
		if ( empty( $caption ) ) {
			$caption = null;
		}
		
		// Get alt text
		$alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
		if ( empty( $alt ) ) {
			$alt = null;
		}

		// Use alt text as caption if no caption is set
		if ( null === $caption && null !== $alt ) {
			$caption = $alt;
		}

		// Get geographic location if available
		$geo_location = self::get_image_geo_location( $attachment_id );

		// Get license if available
		$license = self::get_image_license( $attachment_id );

		try {
			return new ImageEntry(
				$image_url,
				$caption,
				$geo_location,
				$title,
				$license
			);
		} catch ( InvalidArgumentException $e ) {
			// Log the error but don't break sitemap generation
			error_log( 'MSM Sitemap: Invalid image entry for attachment ' . $attachment_id . ': ' . $e->getMessage() );
			return null;
		}
	}

	/**
	 * Create ImageEntry objects from an array of attachment IDs.
	 *
	 * @param array<int> $attachment_ids Array of attachment IDs.
	 * @return array<ImageEntry> Array of image entries.
	 */
	public static function from_attachment_ids( array $attachment_ids ): array {
		$image_entries = array();
		foreach ( $attachment_ids as $attachment_id ) {
			$image_entry = self::from_attachment_id( $attachment_id );
			if ( $image_entry ) {
				$image_entries[] = $image_entry;
			}
		}
		return $image_entries;
	}

	/**
	 * Create ImageEntry objects from image metadata array.
	 *
	 * @param array<int, array<string, mixed>> $image_metadata Array of image metadata.
	 * @return array<ImageEntry> Array of image entries.
	 */
	public static function from_metadata( array $image_metadata ): array {
		$image_entries = array();
		foreach ( $image_metadata as $attachment_id => $metadata ) {
			$image_entry = self::from_metadata_item( $attachment_id, $metadata );
			if ( $image_entry ) {
				$image_entries[] = $image_entry;
			}
		}
		return $image_entries;
	}

	/**
	 * Create an ImageEntry from metadata item.
	 *
	 * @param int $attachment_id The attachment ID.
	 * @param array<string, mixed> $metadata The image metadata.
	 * @return ImageEntry|null The image entry or null if invalid.
	 */
	private static function from_metadata_item( int $attachment_id, array $metadata ): ?ImageEntry {
		if ( empty( $metadata['url'] ) ) {
			return null;
		}

		// Check if image should be skipped
		if ( apply_filters( 'msm_sitemap_skip_image', false, $attachment_id ) ) {
			return null;
		}

		$title = $metadata['title'] ?? null;
		if ( empty( $title ) ) {
			$title = null;
		}

		$caption = $metadata['caption'] ?? null;
		if ( empty( $caption ) ) {
			$caption = null;
		}

		// Use alt text as caption if no caption is set
		$alt = $metadata['alt'] ?? null;
		if ( null === $caption && ! empty( $alt ) ) {
			$caption = $alt;
		}

		// Get geographic location if available
		$geo_location = self::get_image_geo_location( $attachment_id );

		// Get license if available
		$license = self::get_image_license( $attachment_id );

		try {
			return new ImageEntry(
				$metadata['url'],
				$caption,
				$geo_location,
				$title,
				$license
			);
		} catch ( InvalidArgumentException $e ) {
			// Log the error but don't break sitemap generation
			error_log( 'MSM Sitemap: Invalid image entry for attachment ' . $attachment_id . ': ' . $e->getMessage() );
			return null;
		}
	}

	/**
	 * Get geographic location for an image.
	 *
	 * @param int $attachment_id The attachment ID.
	 * @return string|null The geographic location or null if not available.
	 */
	private static function get_image_geo_location( int $attachment_id ): ?string {
		// Check for EXIF GPS data
		$metadata = wp_get_attachment_metadata( $attachment_id );
		if ( isset( $metadata['image_meta']['location'] ) ) {
			return $metadata['image_meta']['location'];
		}

		// Check for custom field
		$geo_location = get_post_meta( $attachment_id, '_msm_sitemap_geo_location', true );
		if ( ! empty( $geo_location ) ) {
			return $geo_location;
		}

		return null;
	}

	/**
	 * Get license information for an image.
	 *
	 * @param int $attachment_id The attachment ID.
	 * @return string|null The license URL or null if not available.
	 */
	private static function get_image_license( int $attachment_id ): ?string {
		// Check for custom field
		$license = get_post_meta( $attachment_id, '_msm_sitemap_license', true );
		if ( ! empty( $license ) ) {
			return $license;
		}

		return null;
	}

	/**
	 * Create an ImageEntry from raw data (for testing or non-WordPress contexts).
	 *
	 * @param string $loc The URL of the image.
	 * @param string|null $caption The caption of the image.
	 * @param string|null $geo_location The geographic location of the image.
	 * @param string|null $title The title of the image.
	 * @param string|null $license The license URL of the image.
	 * @return ImageEntry The image entry.
	 * @throws InvalidArgumentException If any parameter is invalid.
	 */
	public static function from_data(
		string $loc,
		?string $caption = null,
		?string $geo_location = null,
		?string $title = null,
		?string $license = null
	): ImageEntry {
		return new ImageEntry( $loc, $caption, $geo_location, $title, $license );
	}
}
