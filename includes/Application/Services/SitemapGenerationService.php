<?php
/**
 * SitemapGenerationService
 *
 * @package Automattic\MSM_Sitemap\Application\Services
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Application\Services;

use Automattic\MSM_Sitemap\Application\DTOs\SitemapOperationResult;
use Automattic\MSM_Sitemap\Domain\Contracts\SitemapRepositoryInterface;
use Automattic\MSM_Sitemap\Application\Services\SitemapGenerator;
use Automattic\MSM_Sitemap\Infrastructure\Formatters\SitemapXmlFormatter;

/**
 * Service for handling sitemap generation operations.
 */
class SitemapGenerationService {

	/**
	 * The sitemap generator.
	 *
	 * @var SitemapGenerator
	 */
	private SitemapGenerator $generator;

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
	 * The XML formatter.
	 *
	 * @var SitemapXmlFormatter
	 */
	private SitemapXmlFormatter $formatter;

	/**
	 * Constructor.
	 *
	 * @param SitemapGenerator $generator The sitemap generator.
	 * @param SitemapRepositoryInterface $repository The sitemap repository.
	 * @param SitemapQueryService|null $query_service The query service (optional, will create if not provided).
	 */
	public function __construct(
		SitemapGenerator $generator,
		SitemapRepositoryInterface $repository,
		?SitemapQueryService $query_service = null
	) {
		$this->generator = $generator;
		$this->repository = $repository;
		$this->query_service = $query_service ?: new SitemapQueryService();
		$this->formatter = new SitemapXmlFormatter();
	}

	/**
	 * Create a sitemap for a specific date.
	 *
	 * @param string $date The sitemap date (YYYY-MM-DD format).
	 * @param bool $force Whether to force regeneration even if sitemap exists.
	 * @return SitemapOperationResult The result of the operation.
	 */
	public function create_for_date( string $date, bool $force = false ): SitemapOperationResult {
		$existing_id = $this->repository->find_by_date( $date );
		
		if ( $existing_id && ! $force ) {
			return SitemapOperationResult::failure(
				sprintf(
					/* translators: %s: Date in YYYY-MM-DD format */
					__( 'Sitemap for %s already exists. Use --force to regenerate.', 'msm-sitemap' ),
					$date
				),
				'sitemap_exists'
			);
		}
		
		// Convert YYYY-MM-DD date to MySQL DATETIME format for the generator
		$datetime = $date . ' 00:00:00';
		$content = $this->generator->generate_sitemap_for_date( $datetime );
		
		if ( $content->count() === 0 ) {
			// No content to generate, delete existing sitemap if it exists
			if ( $existing_id ) {
				$this->repository->delete_by_date( $date );
			}
			return SitemapOperationResult::failure(
				sprintf(
					/* translators: %s: Date in YYYY-MM-DD format */
					__( 'No posts found for %s. Sitemap not created.', 'msm-sitemap' ),
					$date
				),
				'no_content'
			);
		}
		
		// Delete existing sitemap if it exists and we're forcing regeneration
		if ( $existing_id && $force ) {
			$this->repository->delete_by_date( $date );
		}
		
		// Create the sitemap
		$xml_content = $this->formatter->format( $content );
		$saved = $this->repository->save( $date, $xml_content, $content->count() );
		
		if ( ! $saved ) {
			return SitemapOperationResult::failure(
				sprintf(
					/* translators: %s: Date in YYYY-MM-DD format */
					__( 'Failed to create sitemap for %s.', 'msm-sitemap' ),
					$date
				),
				'creation_failed'
			);
		}
		
		return SitemapOperationResult::success(
			1,
			sprintf(
				/* translators: 1: Date in YYYY-MM-DD format, 2: Number of URLs */
				_n( 'Sitemap created for %1$s with %2$d URL.', 'Sitemap created for %1$s with %2$d URLs.', $content->count(), 'msm-sitemap' ),
				$date,
				$content->count()
			),
			array(
				'url_count' => $content->count(),
				'date' => $date,
			)
		);
	}

	/**
	 * Generate sitemaps for multiple date queries.
	 *
	 * @param array $date_queries Array of date queries.
	 * @param bool $force Whether to force regeneration even if sitemap exists.
	 * @return SitemapOperationResult The result of the operation.
	 */
	public function generate_for_date_queries( array $date_queries, bool $force = false ): SitemapOperationResult {
		if ( empty( $date_queries ) ) {
			return SitemapOperationResult::failure(
				__( 'No date queries provided.', 'msm-sitemap' ),
				'no_queries'
			);
		}

		// Check if generation should be stopped
		if ( $this->is_stopped() ) {
			return SitemapOperationResult::failure(
				__( 'Sitemap generation was stopped.', 'msm-sitemap' ),
				'stopped'
			);
		}

		// Expand date queries into actual dates
		$dates_to_generate = $this->query_service->expand_date_queries( $date_queries );
		
		if ( empty( $dates_to_generate ) ) {
			return SitemapOperationResult::failure(
				__( 'No valid dates found in the provided queries.', 'msm-sitemap' ),
				'no_valid_dates'
			);
		}

		$results = array();
		$success_count = 0;
		$failure_count = 0;
		$skipped_count = 0;

		foreach ( $dates_to_generate as $date ) {
			// Check if generation should be stopped
			if ( $this->is_stopped() ) {
				break;
			}

			$result = $this->create_for_date( $date, $force );
			$results[ $date ] = $result;

			if ( $result->is_success() ) {
				$success_count++;
			} elseif ( $result->get_error_code() === 'sitemap_exists' ) {
				$skipped_count++;
			} else {
				$failure_count++;
			}
		}

		// Build summary message
		$message_parts = array();
		if ( $success_count > 0 ) {
			$message_parts[] = sprintf(
				/* translators: %d: Number of sitemaps created */
				_n( '%d sitemap created', '%d sitemaps created', $success_count, 'msm-sitemap' ),
				$success_count
			);
		}
		if ( $skipped_count > 0 ) {
			$message_parts[] = sprintf(
				/* translators: %d: Number of sitemaps skipped */
				_n( '%d sitemap skipped', '%d sitemaps skipped', $skipped_count, 'msm-sitemap' ),
				$skipped_count
			);
		}
		if ( $failure_count > 0 ) {
			$message_parts[] = sprintf(
				/* translators: %d: Number of sitemaps that failed */
				_n( '%d sitemap failed', '%d sitemaps failed', $failure_count, 'msm-sitemap' ),
				$failure_count
			);
		}

		$message = implode( ', ', $message_parts );

		if ( $failure_count === 0 && $success_count > 0 ) {
			return SitemapOperationResult::success(
                $success_count,
                $message
            );
		} else {
			return SitemapOperationResult::failure( $message, 'partial_failure', array(
				'success_count' => $success_count,
				'skipped_count' => $skipped_count,
				'failure_count' => $failure_count,
				'results' => $results,
			) );
		}
	}

	/**
	 * Check if sitemap generation should be stopped.
	 *
	 * @return bool True if generation should be stopped, false otherwise.
	 */
	private function is_stopped(): bool {
		return (bool) get_option( 'msm_sitemap_stop_generation', false );
	}
}
