<?php
/**
 * SitemapService
 *
 * @package Automattic\MSM_Sitemap\Application\Services
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Application\Services;

use Automattic\MSM_Sitemap\Application\DTOs\SitemapOperationResult;
use Automattic\MSM_Sitemap\Application\DTOs\SitemapValidationResult;
use Automattic\MSM_Sitemap\Application\DTOs\SitemapRecountResult;
use Automattic\MSM_Sitemap\Infrastructure\Formatters\SitemapXmlFormatter;
use Automattic\MSM_Sitemap\Domain\Contracts\SitemapRepositoryInterface;
use Automattic\MSM_Sitemap\Infrastructure\Repositories\PostRepository;

// Additional services for dependency injection
// Note: These will be added when updating the constructor

/**
 * Service for managing sitemap operations.
 */
class SitemapService {

	/**
	 * The sitemap generator.
	 *
	 * @var SitemapGenerator
	 */
	private SitemapGenerator $generator;

	/**
	 * The XML formatter.
	 *
	 * @var SitemapXmlFormatter
	 */
	private SitemapXmlFormatter $formatter;

	/**
	 * The sitemap repository.
	 *
	 * @var SitemapRepositoryInterface
	 */
	private SitemapRepositoryInterface $repository;

	/**
	 * The query service.
	 *
	 * @var SitemapQueryService
	 */
	private SitemapQueryService $query_service;

	/**
	 * The generation service.
	 *
	 * @var SitemapGenerationService
	 */
	private SitemapGenerationService $generation_service;

	/**
	 * Constructor.
	 *
	 * @param SitemapGenerator $generator The sitemap generator.
	 * @param SitemapRepositoryInterface $repository The sitemap repository.
	 * @param SitemapQueryService|null $query_service The query service (optional, will create if not provided).
	 * @param SitemapGenerationService|null $generation_service The generation service (optional, will create if not provided).
	 */
	public function __construct( 
		SitemapGenerator $generator, 
		SitemapRepositoryInterface $repository,
		?SitemapQueryService $query_service = null,
		?SitemapGenerationService $generation_service = null
	) {
		$this->generator = $generator;
		$this->formatter = new SitemapXmlFormatter();
		$this->repository = $repository;
		
		// Create query service if not provided
		$this->query_service = $query_service ?: new SitemapQueryService();
		
		// Create generation service if not provided
		$this->generation_service = $generation_service ?: new SitemapGenerationService( $generator, $repository, $this->query_service );
	}

	/**
	 * Create a sitemap for a specific date.
	 *
	 * @param string $date The sitemap date (YYYY-MM-DD format).
	 * @param bool $force Whether to force regeneration even if sitemap exists.
	 * @return SitemapOperationResult The result of the operation.
	 */
	public function create_for_date( string $date, bool $force = false ): SitemapOperationResult {
		return $this->generation_service->create_for_date( $date, $force );
	}



	/**
	 * Generate sitemaps for specific date queries.
	 *
	 * @param array $date_queries Array of date queries with year, month, day keys.
	 * @param bool $force Whether to force regeneration even if sitemap exists.
	 * @return SitemapOperationResult The result of the operation.
	 */
	public function generate_for_date_queries( array $date_queries, bool $force = false ): SitemapOperationResult {
		return $this->generation_service->generate_for_date_queries( $date_queries, $force );
	}



	/**
	 * Delete sitemaps for a specific date.
	 *
	 * @param string $date The sitemap date (YYYY-MM-DD format).
	 * @return bool True on success, false on failure.
	 */
	public function delete_for_date( string $date ): bool {
		return $this->repository->delete_by_date( $date );
	}

	/**
	 * Count how many sitemaps would be deleted for the given date queries.
	 *
	 * @param array $date_queries Array of date queries with year, month, day keys.
	 * @return int Number of sitemaps that would be deleted.
	 */
	public function count_deletable_sitemaps( array $date_queries ): int {
		$sitemap_dates = $this->repository->get_all_sitemap_dates();
		return $this->query_service->count_matching_dates( $date_queries, $sitemap_dates );
	}

	/**
	 * Delete sitemaps for specific date queries.
	 *
	 * @param array $date_queries Array of date queries with year, month, day keys.
	 * @return SitemapOperationResult The result of the operation.
	 */
	public function delete_for_date_queries( array $date_queries ): SitemapOperationResult {
		$deleted_count = $this->repository->delete_for_date_queries( $date_queries );
		
		// Update the total count using fast recount
		$this->recount_urls();
		
		if ( $deleted_count > 0 ) {
			return SitemapOperationResult::success(
				$deleted_count,
				sprintf(
					/* translators: %d: Number of sitemaps deleted */
					_n( 'Deleted %d sitemap.', 'Deleted %d sitemaps.', $deleted_count, 'msm-sitemap' ),
					$deleted_count
				)
			);
		} else {
			return SitemapOperationResult::failure(
				__( 'No sitemaps found to delete.', 'msm-sitemap' ),
				'no_sitemaps_found'
			);
		}
	}

	/**
	 * Delete all sitemaps.
	 *
	 * @return SitemapOperationResult The result of the operation.
	 */
	public function delete_all(): SitemapOperationResult {
		$deleted_count = $this->repository->delete_all();
		
		// Reset the total count
		update_option( 'msm_sitemap_indexed_url_count', 0 );
		
		return SitemapOperationResult::success(
			$deleted_count,
			sprintf(
				/* translators: %d: Number of sitemaps deleted */
				_n( 'Deleted %d sitemap.', 'Deleted %d sitemaps.', $deleted_count, 'msm-sitemap' ),
				$deleted_count
			)
		);
	}

	/**
	 * Get all sitemap dates.
	 *
	 * @return array Array of sitemap dates.
	 */
	public function get_all_sitemap_dates(): array {
		global $wpdb;
		
		$dates = $wpdb->get_col( $wpdb->prepare( 
			"SELECT post_name FROM $wpdb->posts WHERE post_type = %s ORDER BY post_name", 
			\Automattic\MSM_Sitemap\Plugin::SITEMAP_CPT 
		) );
		
		return $dates;
	}

	/**
	 * Get sitemap data for a specific date.
	 *
	 * @param string $date The sitemap date (YYYY-MM-DD format).
	 * @return array|null Sitemap data or null if not found.
	 */
	public function get_sitemap_data( string $date ): ?array {
		$post_id = $this->repository->find_by_date( $date );
		
		if ( ! $post_id ) {
			return null;
		}
		
		$post = get_post( $post_id );
		if ( ! $post ) {
			return null;
		}
		
		return array(
			'id' => $post_id,
			'date' => $date,
			'url_count' => get_post_meta( $post_id, 'msm_indexed_url_count', true ) ?: 0,
			'xml_content' => get_post_meta( $post_id, 'msm_sitemap_xml', true ),
			'created' => $post->post_date,
			'modified' => $post->post_modified,
		);
	}





	/**
	 * Recount URLs in sitemaps with different modes for performance vs accuracy.
	 *
	 * @param bool $full_recount Whether to do a full XML-based recount (slower but more accurate).
	 * @return SitemapRecountResult The recount result.
	 */
	public function recount_urls( bool $full_recount = false ): SitemapRecountResult {
		if ( $full_recount ) {
			return $this->recount_urls_full();
		} else {
			return $this->recount_urls_fast();
		}
	}

	/**
	 * Fast recount using existing meta values (database-based).
	 *
	 * @return SitemapRecountResult The recount result.
	 */
	private function recount_urls_fast(): SitemapRecountResult {
		global $wpdb;

		$total_count = $wpdb->get_var( $wpdb->prepare(
			"SELECT SUM(meta_value) FROM $wpdb->postmeta pm
			JOIN $wpdb->posts p ON pm.post_id = p.ID
			WHERE p.post_type = %s AND p.post_status = 'publish' AND pm.meta_key = %s",
			\Automattic\MSM_Sitemap\Plugin::SITEMAP_CPT,
			'msm_indexed_url_count'
		) );

		$total_count = (int) ( $total_count ?: 0 );
		update_option( 'msm_sitemap_indexed_url_count', $total_count );

		// Get sitemap count for reporting
		$sitemap_count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $wpdb->posts
			WHERE post_type = %s AND post_status = 'publish'",
			\Automattic\MSM_Sitemap\Plugin::SITEMAP_CPT
		) );
		$sitemap_count = (int) ( $sitemap_count ?: 0 );

		$message = sprintf(
			/* translators: %s is the total number of URLs found. */
			__( 'Total URLs found: %s', 'msm-sitemap' ),
			$total_count
		);
		$message .= ' ' . sprintf(
			/* translators: %s is the total number of sitemaps found. */
			__( 'Number of sitemaps found: %s', 'msm-sitemap' ),
			$sitemap_count
		);

		return SitemapRecountResult::success(
			$sitemap_count,
			$message,
			$total_count,
			array()
		);
	}

	/**
	 * Full recount parsing XML for each sitemap (accurate but slower).
	 *
	 * @return SitemapRecountResult The recount result.
	 */
	private function recount_urls_full(): SitemapRecountResult {
		$sitemap_dates = $this->repository->get_all_sitemap_dates();

		$total_count = 0;
		$sitemap_count = 0;
		$errors = array();

		foreach ( $sitemap_dates as $date ) {
			// Check for halt signal
			if ( $this->is_stopped() ) {
				return SitemapRecountResult::failure(
					__( 'URL recount was stopped by user request.', 'msm-sitemap' ),
					'stopped'
				);
			}

			$post_id = $this->repository->find_by_date( $date );
			if ( ! $post_id ) {
				continue;
			}

			$post = get_post( $post_id );
			if ( ! $post || $post->post_status !== 'publish' ) {
				continue;
			}

			$xml_data = get_post_meta( $post_id, 'msm_sitemap_xml', true );
			if ( ! $xml_data ) {
				$errors[] = sprintf(
					/* translators: %d is the sitemap ID. */
					__( 'Sitemap %d has no XML data.', 'msm-sitemap' ),
					$post_id
				);
				continue;
			}

			libxml_use_internal_errors( true );
			$xml = simplexml_load_string( $xml_data );
			libxml_clear_errors();

			$count = 0;
			if ( is_object( $xml ) && isset( $xml->url ) ) {
				$count = count( $xml->url );
			}

			update_post_meta( $post_id, 'msm_indexed_url_count', $count );
			$total_count += $count;
			$sitemap_count++;
		}

		update_option( 'msm_sitemap_indexed_url_count', $total_count, false );

		$message = sprintf(
			/* translators: %s is the total number of URLs found. */
			__( 'Total URLs found: %s', 'msm-sitemap' ),
			$total_count
		);
		$message .= ' ' . sprintf(
			/* translators: %s is the total number of sitemaps found. */
			__( 'Number of sitemaps found: %s', 'msm-sitemap' ),
			$sitemap_count
		);

		return SitemapRecountResult::success(
			$sitemap_count,
			$message,
			$total_count,
			$errors
		);
	}

	/**
	 * Get sitemap data for listing.
	 *
	 * @param array|null $date_queries Optional date queries to filter by.
	 * @return array Array of sitemap data.
	 */
	public function get_sitemap_list_data( ?array $date_queries = null ): array {
		$sitemap_dates = $this->repository->get_all_sitemap_dates();
		
		// If no date queries, return all sitemaps
		if ( empty( $date_queries ) ) {
			return $this->build_sitemap_data_from_dates( $sitemap_dates );
		}
		
		// Filter dates based on queries
		$filtered_dates = array();
		foreach ( $date_queries as $query ) {
			$matching_dates = $this->query_service->get_matching_dates_for_query( $query, $sitemap_dates );
			$filtered_dates = array_merge( $filtered_dates, $matching_dates );
		}
		
		return $this->build_sitemap_data_from_dates( array_unique( $filtered_dates ) );
	}

	/**
	 * Get detailed sitemap data by ID.
	 *
	 * @param int $sitemap_id The sitemap post ID.
	 * @return array|null Sitemap data or null if not found.
	 */
	public function get_sitemap_by_id( int $sitemap_id ): ?array {
		$post = get_post( $sitemap_id );
		if ( ! $post || $post->post_type !== \Automattic\MSM_Sitemap\Plugin::SITEMAP_CPT ) {
			return null;
		}
		
		return $this->build_sitemap_data_from_post( $post );
	}

	/**
	 * Get detailed sitemap data by date.
	 *
	 * @param string $date The sitemap date (YYYY-MM-DD, YYYY-MM, or YYYY).
	 * @return array Array of sitemap data (may be multiple for date ranges).
	 */
	public function get_sitemaps_by_date( string $date ): array {
		$date_queries = $this->query_service->parse_date_query( $date );
		if ( empty( $date_queries ) ) {
			return array();
		}
		
		$sitemap_dates = $this->repository->get_all_sitemap_dates();
		$matching_dates = array();
		
		foreach ( $date_queries as $query ) {
			$dates = $this->query_service->get_matching_dates_for_query( $query, $sitemap_dates );
			$matching_dates = array_merge( $matching_dates, $dates );
		}
		
		return $this->build_sitemap_data_from_dates( array_unique( $matching_dates ) );
	}

	/**
	 * Build sitemap data from an array of dates.
	 *
	 * @param array $dates Array of sitemap dates.
	 * @return array Array of sitemap data.
	 */
	private function build_sitemap_data_from_dates( array $dates ): array {
		$sitemap_data = array();
		
		foreach ( $dates as $date ) {
			$post_id = $this->repository->find_by_date( $date );
			if ( $post_id ) {
				$post = get_post( $post_id );
				if ( $post ) {
					$sitemap_data[] = $this->build_sitemap_data_from_post( $post );
				}
			}
		}
		
		// Sort by date in descending order (newest first)
		usort( $sitemap_data, function( $a, $b ) {
			return strcmp( $b['date'], $a['date'] );
		} );
		
		return $sitemap_data;
	}

	/**
	 * Build sitemap data from a post object.
	 *
	 * @param \WP_Post $post The sitemap post.
	 * @return array Sitemap data.
	 */
	private function build_sitemap_data_from_post( \WP_Post $post ): array {
		return array(
			'id'            => $post->ID,
			'date'          => $post->post_name,
			'url_count'     => (int) get_post_meta( $post->ID, 'msm_indexed_url_count', true ),
			'status'        => $post->post_status,
			'last_modified' => $post->post_modified_gmt,
			'sitemap_url'   => $this->build_sitemap_url_from_post_name( $post->post_name ),
		);
	}

	/**
	 * Build sitemap URL from post name.
	 *
	 * @param string $post_name The sitemap post name (date).
	 * @return string The sitemap URL.
	 */
	private function build_sitemap_url_from_post_name( string $post_name ): string {
		// Convert YYYY-MM-DD to MySQL DATETIME format
		$sitemap_date = $post_name . ' 00:00:00';
		
		// Use our factory to create a single entry and extract the URL
		$entries = \Automattic\MSM_Sitemap\Infrastructure\Factories\SitemapIndexEntryFactory::from_sitemap_dates( array( $sitemap_date ) );
		
		if ( empty( $entries ) ) {
			return '';
		}
		
		return $entries[0]->loc();
	}







	/**
	 * Check if sitemap generation has been stopped by user request.
	 *
	 * @return bool True if stopped, false otherwise.
	 */
	private function is_stopped(): bool {
		return (bool) get_option( 'msm_sitemap_stop_generation' );
	}

	/**
	 * Reset all sitemap data and options.
	 * 
	 * This removes all sitemap posts, meta data, and processing state options.
	 * 
	 * @return bool True on success.
	 */
	public function reset_all_data(): bool {
		// Remove the stats meta information
		delete_post_meta_by_key( 'msm_indexed_url_count' );

		// Remove the XML sitemap data
		delete_post_meta_by_key( 'msm_sitemap_xml' );

		// Delete state options
		delete_option( 'msm_days_to_process' );
		delete_option( 'msm_months_to_process' );
		delete_option( 'msm_years_to_process' );
		delete_option( 'msm_sitemap_stop_generation' );
		delete_option( 'msm_generation_in_progress' );

		// Delete stats options
		delete_option( 'msm_sitemap_indexed_url_count' );

		// Delete all sitemap posts via repository
		$this->repository->delete_all();

		return true;
	}
}

