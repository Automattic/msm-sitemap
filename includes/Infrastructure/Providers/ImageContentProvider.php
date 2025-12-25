<?php
/**
 * Image Content Provider
 *
 * @package Automattic\MSM_Sitemap\Infrastructure\Providers
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Infrastructure\Providers;

use Automattic\MSM_Sitemap\Application\Services\SettingsService;
use Automattic\MSM_Sitemap\Domain\Contracts\ContentProviderInterface;
use Automattic\MSM_Sitemap\Domain\ValueObjects\UrlEntry;
use Automattic\MSM_Sitemap\Domain\ValueObjects\UrlSet;
use Automattic\MSM_Sitemap\Infrastructure\Factories\ImageEntryFactory;
use Automattic\MSM_Sitemap\Infrastructure\Factories\UrlEntryFactory;
use Automattic\MSM_Sitemap\Infrastructure\Factories\UrlSetFactory;
use Automattic\MSM_Sitemap\Infrastructure\Repositories\ImageRepository;

/**
 * Image Content Provider
 *
 * Provides image URLs for sitemap generation by associating images with their parent posts.
 * Images are included as additional metadata for post URLs rather than as separate URLs.
 */
class ImageContentProvider implements ContentProviderInterface {

	/**
	 * Default images per sitemap page.
	 *
	 * @var int
	 */
	private const DEFAULT_IMAGES_PER_SITEMAP_PAGE = 1000;

	/**
	 * Image repository.
	 *
	 * @var ImageRepository
	 */
	private ImageRepository $image_repository;

	/**
	 * Settings service.
	 *
	 * @var SettingsService
	 */
	private SettingsService $settings_service;

	/**
	 * Constructor.
	 *
	 * @param ImageRepository  $image_repository  Image repository.
	 * @param SettingsService  $settings_service  Settings service.
	 */
	public function __construct( ImageRepository $image_repository, SettingsService $settings_service ) {
		$this->image_repository = $image_repository;
		$this->settings_service = $settings_service;
	}

	/**
	 * Get URLs for a specific date.
	 *
	 * @param string $date MySQL DATETIME format (e.g., '2024-01-15 00:00:00').
	 * @return UrlSet Collection of URL entries for the date.
	 */
	public function get_urls_for_date( string $date ): UrlSet {
		// This provider doesn't create its own URLs, it enhances existing ones
		// Images are added to post URLs through the SitemapGenerator
		return UrlSetFactory::create_empty();
	}

	/**
	 * Enhance existing URL entries with images.
	 *
	 * @param array<UrlEntry> $url_entries Array of URL entries to enhance.
	 * @return array<UrlEntry> Array of enhanced URL entries.
	 */
	public function enhance_url_entries_with_images( array $url_entries ): array {
		// Check if images should be included
		if ( ! $this->image_repository->should_include_images() ) {
			return $url_entries;
		}

		$enhanced_entries = array();

		foreach ( $url_entries as $url_entry ) {
			// Extract post ID from URL using multiple methods
			$post_id = $this->get_post_id_from_url( $url_entry->loc() );
			if ( ! $post_id ) {
				$enhanced_entries[] = $url_entry;
				continue;
			}

			// Get image metadata for this post
			$image_metadata = $this->get_image_metadata_for_posts( array( $post_id ) );

			// Add images if available
			if ( isset( $image_metadata[ $post_id ] ) ) {
				$images = ImageEntryFactory::from_metadata( $image_metadata[ $post_id ] );
				if ( ! empty( $images ) ) {
					// Create new URL entry with images
					$enhanced_entry     = new UrlEntry(
						$url_entry->loc(),
						$url_entry->lastmod(),
						$url_entry->changefreq(),
						$url_entry->priority(),
						$images
					);
					$enhanced_entries[] = $enhanced_entry;
				} else {
					$enhanced_entries[] = $url_entry;
				}
			} else {
				$enhanced_entries[] = $url_entry;
			}
		}

		return $enhanced_entries;
	}

	/**
	 * Get enabled post types from settings.
	 *
	 * @return array<string> Array of enabled post type slugs.
	 */
	private function get_enabled_post_types(): array {
		$enabled = $this->settings_service->get_setting( 'enabled_post_types', array( 'post' ) );

		if ( ! is_array( $enabled ) || empty( $enabled ) ) {
			return array( 'post' );
		}

		return $enabled;
	}

	/**
	 * Get post ID from URL.
	 *
	 * @param string $url The URL to extract post ID from.
	 * @return int|null The post ID or null if not found.
	 */
	private function get_post_id_from_url( string $url ): ?int {
		$enabled_post_types = $this->get_enabled_post_types();

		// Method 1: Use url_to_postid (WordPress standard method)
		$post_id = url_to_postid( $url );
		if ( $post_id ) {
			$post = get_post( $post_id );
			if ( $post && in_array( $post->post_type, $enabled_post_types, true ) ) {
				return $post_id;
			}
		}

		// Method 2: Try get_page_by_path with enabled post types
		$parsed_url = wp_parse_url( $url );
		if ( isset( $parsed_url['path'] ) ) {
			$path = trim( $parsed_url['path'], '/' );
			$post = get_page_by_path( $path, OBJECT, $enabled_post_types );
			if ( $post ) {
				return $post->ID;
			}
		}

		return null;
	}

	/**
	 * Get image metadata for posts.
	 *
	 * @param array<int> $post_ids Array of post IDs.
	 * @return array<int, array<int, array<string, mixed>>> Array of image metadata keyed by post ID.
	 */
	private function get_image_metadata_for_posts( array $post_ids ): array {
		$image_metadata = array();

		foreach ( $post_ids as $post_id ) {
			$post_images = array();

			// Get featured images if enabled
			if ( $this->image_repository->should_include_featured_images() ) {
				$featured_image_ids = $this->image_repository->get_featured_image_ids_for_posts( array( $post_id ) );
				if ( ! empty( $featured_image_ids ) ) {
					$featured_metadata = $this->image_repository->get_image_metadata( $featured_image_ids );
					$post_images       = array_merge( $post_images, $featured_metadata );
				}
			}

			// Get content images if enabled
			if ( $this->image_repository->should_include_content_images() ) {
				$content_image_ids = $this->image_repository->get_image_ids_for_posts( array( $post_id ) );
				if ( ! empty( $content_image_ids ) ) {
					$content_metadata = $this->image_repository->get_image_metadata( $content_image_ids );
					$post_images      = array_merge( $post_images, $content_metadata );
				}
			}

			// Remove duplicates (featured images might also be content images)
			$post_images = array_unique( $post_images, SORT_REGULAR );

			if ( ! empty( $post_images ) ) {
				$image_metadata[ $post_id ] = $post_images;
			}
		}

		return $image_metadata;
	}

	/**
	 * Create URL entries with images.
	 *
	 * @param array<int> $post_ids Array of post IDs.
	 * @param array<int, array<int, array<string, mixed>>> $image_metadata Array of image metadata.
	 * @return array<UrlEntry> Array of URL entries.
	 */
	private function create_url_entries_with_images( array $post_ids, array $image_metadata ): array {
		$url_entries = array();

		foreach ( $post_ids as $post_id ) {
			// Create base URL entry
			$url_entry = UrlEntryFactory::from_post( $post_id );
			if ( ! $url_entry ) {
				continue;
			}

			// Add images if available
			if ( isset( $image_metadata[ $post_id ] ) ) {
				$images = ImageEntryFactory::from_metadata( $image_metadata[ $post_id ] );
				if ( ! empty( $images ) ) {
					// Create new URL entry with images
					$url_entry = new UrlEntry(
						$url_entry->loc(),
						$url_entry->lastmod(),
						$url_entry->changefreq(),
						$url_entry->priority(),
						$images
					);
				}
			}

			$url_entries[] = $url_entry;
		}

		return $url_entries;
	}

	/**
	 * Get the content type this provider handles.
	 *
	 * @return string The content type.
	 */
	public function get_content_type(): string {
		return 'images';
	}

	/**
	 * Get the display name for this content provider.
	 *
	 * @return string The display name.
	 */
	public function get_display_name(): string {
		return __( 'Images', 'msm-sitemap' );
	}

	/**
	 * Get the description for this content provider.
	 *
	 * @return string The description.
	 */
	public function get_description(): string {
		return __( 'Include images from posts in sitemaps', 'msm-sitemap' );
	}

	/**
	 * Enhance existing URL entries with additional data (optional).
	 *
	 * @param array<UrlEntry> $url_entries Array of URL entries to enhance.
	 * @return array<UrlEntry> Array of enhanced URL entries.
	 */
	public function enhance_url_entries( array $url_entries ): array {
		return $this->enhance_url_entries_with_images( $url_entries );
	}
}
