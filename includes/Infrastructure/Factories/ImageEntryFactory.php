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
 * Per Google's Image Sitemap specification, only the loc (URL) element is required.
 * Other elements (caption, geo_location, title, license) have been deprecated by Google.
 *
 * @see https://developers.google.com/search/docs/crawling-indexing/sitemaps/image-sitemaps
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

		// Check if image should be skipped.
		if ( apply_filters( 'msm_sitemap_skip_image', false, $attachment_id ) ) {
			return null;
		}

		// Get image URL.
		$image_url = wp_get_attachment_url( $attachment_id );
		if ( ! $image_url ) {
			return null;
		}

		try {
			return new ImageEntry( $image_url );
		} catch ( InvalidArgumentException $e ) {
			// Log the error but don't break sitemap generation.
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
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
	 * @param int                  $attachment_id The attachment ID.
	 * @param array<string, mixed> $metadata The image metadata.
	 * @return ImageEntry|null The image entry or null if invalid.
	 */
	private static function from_metadata_item( int $attachment_id, array $metadata ): ?ImageEntry {
		if ( empty( $metadata['url'] ) ) {
			return null;
		}

		// Check if image should be skipped.
		if ( apply_filters( 'msm_sitemap_skip_image', false, $attachment_id ) ) {
			return null;
		}

		try {
			return new ImageEntry( $metadata['url'] );
		} catch ( InvalidArgumentException $e ) {
			// Log the error but don't break sitemap generation.
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'MSM Sitemap: Invalid image entry for attachment ' . $attachment_id . ': ' . $e->getMessage() );
			return null;
		}
	}

	/**
	 * Create an ImageEntry from raw data (for testing or non-WordPress contexts).
	 *
	 * @param string $loc The URL of the image.
	 * @return ImageEntry The image entry.
	 * @throws InvalidArgumentException If the URL is invalid.
	 */
	public static function from_data( string $loc ): ImageEntry {
		return new ImageEntry( $loc );
	}
}
