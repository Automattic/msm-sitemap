<?php
/**
 * Post Content Provider
 *
 * @package Automattic\MSM_Sitemap\Infrastructure\Providers
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Infrastructure\Providers;

use Automattic\MSM_Sitemap\Domain\Contracts\ContentProviderInterface;
use Automattic\MSM_Sitemap\Domain\ValueObjects\UrlSet;
use Automattic\MSM_Sitemap\Domain\ValueObjects\UrlEntry;
use Automattic\MSM_Sitemap\Infrastructure\Factories\UrlEntryFactory;
use Automattic\MSM_Sitemap\Infrastructure\Factories\UrlSetFactory;
use Automattic\MSM_Sitemap\Infrastructure\Repositories\PostRepository;

/**
 * Post Content Provider
 *
 * Provides post URLs for sitemap generation.
 */
class PostContentProvider implements ContentProviderInterface {

	/**
	 * Default posts per sitemap page.
	 */
	private const DEFAULT_POSTS_PER_SITEMAP_PAGE = 500;

	/**
	 * Post repository.
	 */
	private PostRepository $post_repository;

	/**
	 * Constructor.
	 *
	 * @param PostRepository $post_repository Post repository.
	 */
	public function __construct( PostRepository $post_repository ) {
		$this->post_repository = $post_repository;
	}

	/**
	 * Get URLs for a specific date.
	 *
	 * @param string $date MySQL DATETIME format (e.g., '2024-01-15 00:00:00').
	 * @return UrlSet Collection of URL entries for the date.
	 */
	public function get_urls_for_date( string $date ): UrlSet {
		// Extract date components and validate
		$timestamp = strtotime( $date );
		if ( false === $timestamp ) {
			return UrlSetFactory::create_empty();
		}
		
		$sitemap_date = date( 'Y-m-d', $timestamp );

		// Get post IDs for the date using the repository
		$post_ids = $this->post_repository->get_post_ids_for_date( $sitemap_date, self::DEFAULT_POSTS_PER_SITEMAP_PAGE );

		if ( empty( $post_ids ) ) {
			return UrlSetFactory::create_empty();
		}

		// Create URL entries from posts
		$url_entries = UrlEntryFactory::from_posts( $post_ids );

		return UrlSetFactory::from_entries( $url_entries );
	}

	/**
	 * Get the content type this provider handles.
	 *
	 * @return string The content type.
	 */
	public function get_content_type(): string {
		return 'posts';
	}

	/**
	 * Get the display name for this content provider.
	 *
	 * @return string The display name.
	 */
	public function get_display_name(): string {
		return __( 'Posts', 'msm-sitemap' );
	}

	/**
	 * Get the description for this content provider.
	 *
	 * @return string The description.
	 */
	public function get_description(): string {
		return __( 'Include published posts in sitemaps', 'msm-sitemap' );
	}

	/**
	 * Enhance existing URL entries with additional data (optional).
	 *
	 * @param array<UrlEntry> $url_entries Array of URL entries to enhance.
	 * @return array<UrlEntry> Array of enhanced URL entries.
	 */
	public function enhance_url_entries( array $url_entries ): array {
		// Posts provider doesn't enhance other entries
		return $url_entries;
	}
}
