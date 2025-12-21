<?php
/**
 * SitemapValidationService
 *
 * @package Automattic\MSM_Sitemap\Application\Services
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Application\Services;

use Automattic\MSM_Sitemap\Application\DTOs\SitemapValidationResult;
use Automattic\MSM_Sitemap\Domain\Contracts\SitemapRepositoryInterface;

/**
 * Service for validating sitemap content and structure.
 */
class SitemapValidationService {

	/**
	 * The sitemap repository.
	 *
	 * @var SitemapRepositoryInterface
	 */
	private SitemapRepositoryInterface $repository;

	/**
	 * Constructor.
	 *
	 * @param SitemapRepositoryInterface $repository The sitemap repository.
	 */
	public function __construct( SitemapRepositoryInterface $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Validate sitemap XML content.
	 *
	 * @param string $xml_content The XML content to validate.
	 * @return array Validation results with 'valid' boolean and 'errors' array.
	 */
	public function validate_sitemap_xml( string $xml_content ): array {
		$errors   = array();
		$warnings = array();

		// Enable internal error handling
		libxml_use_internal_errors( true );

		// Create DOM document
		$dom = new \DOMDocument();
		$dom->loadXML( $xml_content );

		// Get any XML parsing errors
		$xml_errors = libxml_get_errors();
		libxml_clear_errors();

		if ( ! empty( $xml_errors ) ) {
			foreach ( $xml_errors as $error ) {
				$errors[] = sprintf(
					'XML Error: %s at line %d',
					trim( $error->message ),
					$error->line
				);
			}
		}

		// Check for required elements
		$urlset = $dom->getElementsByTagName( 'urlset' );
		if ( 0 === $urlset->length ) {
			$errors[] = 'Root element must be <urlset>';
		} elseif ( $urlset->length > 1 ) {
			$warnings[] = 'Multiple <urlset> elements found (should be only one)';
		}

		// Check for required namespace
		$root_element = $dom->documentElement;
		if ( $root_element && 'http://www.sitemaps.org/schemas/sitemap/0.9' !== $root_element->namespaceURI ) {
			$errors[] = 'Missing or invalid sitemap namespace';
		}

		// Check for URLs
		$urls = $dom->getElementsByTagName( 'url' );
		if ( 0 === $urls->length ) {
			$errors[] = 'Sitemap must contain at least one <url> entry';
		}

		// Validate individual URLs
		foreach ( $urls as $url ) {
			$url_errors = $this->validate_url_element( $url );
			$errors     = array_merge( $errors, $url_errors );
		}

		return array(
			'valid'    => empty( $errors ),
			'errors'   => $errors,
			'warnings' => $warnings,
		);
	}

	/**
	 * Validate sitemaps for given date queries.
	 *
	 * @param array|null $date_queries Optional array of date queries to filter sitemaps.
	 * @return SitemapValidationResult Validation result containing overall status and details.
	 */
	public function validate_sitemaps( ?array $date_queries = null ): SitemapValidationResult {
		// Check for halt signal
		if ( $this->is_stopped() ) {
			return SitemapValidationResult::failure(
				__( 'Sitemap validation was stopped by user request.', 'msm-sitemap' ),
				'stopped'
			);
		}

		$total_sitemaps    = 0;
		$valid_sitemaps    = 0;
		$invalid_sitemaps  = 0;
		$total_errors      = 0;
		$validation_errors = array();

		// Get sitemap dates to validate
		$sitemap_dates = $this->repository->get_all_sitemap_dates();
		
		if ( ! empty( $date_queries ) ) {
			$sitemap_dates = $this->filter_dates_by_queries( $sitemap_dates, $date_queries );
		}

		foreach ( $sitemap_dates as $date ) {
			++$total_sitemaps;
			
			// Get sitemap post
			$sitemap_post_id = $this->repository->find_by_date( $date );
			if ( ! $sitemap_post_id ) {
				++$invalid_sitemaps;
				++$total_errors;
				$validation_errors[] = sprintf( 'Sitemap for date %s not found.', $date );
				continue;
			}

			$sitemap_post = get_post( $sitemap_post_id );
			if ( ! $sitemap_post ) {
				++$invalid_sitemaps;
				++$total_errors;
				$validation_errors[] = sprintf( 'Sitemap for date %s could not be retrieved.', $date );
				continue;
			}

			// Get sitemap content
			$sitemap_content = get_post_meta( $sitemap_post_id, 'msm_sitemap_xml', true );
			if ( empty( $sitemap_content ) ) {
				++$invalid_sitemaps;
				++$total_errors;
				$validation_errors[] = sprintf( 'Sitemap for date %s has no XML data.', $date );
				continue;
			}

			// Validate XML content
			$validation_result = $this->validate_sitemap_xml( $sitemap_content );
			
			if ( $validation_result['valid'] ) {
				++$valid_sitemaps;
			} else {
				++$invalid_sitemaps;
				$total_errors     += count( $validation_result['errors'] );
				$validation_errors = array_merge( $validation_errors, $validation_result['errors'] );
			}
		}

		if ( 0 === $total_sitemaps ) {
			return SitemapValidationResult::failure(
				'No sitemaps found to validate.',
				'no_sitemaps_found'
			);
		}

		$message = sprintf(
			'Validated %d sitemaps: %d valid, %d invalid with %d total errors',
			$total_sitemaps,
			$valid_sitemaps,
			$invalid_sitemaps,
			$total_errors
		);

		return SitemapValidationResult::success(
			$total_sitemaps,
			$message,
			$validation_errors,
			$valid_sitemaps,
			$invalid_sitemaps
		);
	}

	/**
	 * Validate a single URL element in the sitemap.
	 *
	 * @param \DOMElement $url_element The URL element to validate.
	 * @return array Array of validation errors.
	 */
	private function validate_url_element( \DOMElement $url_element ): array {
		$errors = array();

		// Check for loc element
		$loc = $url_element->getElementsByTagName( 'loc' );
		if ( 0 === $loc->length ) {
			$errors[] = 'URL missing required <loc> element';
		} elseif ( $loc->length > 1 ) {
			$errors[] = 'URL has multiple <loc> elements (should be only one)';
		} else {
			$url = trim( $loc->item( 0 )->textContent );
			if ( empty( $url ) ) {
				$errors[] = 'URL <loc> element is empty';
			} elseif ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
				$errors[] = 'URL <loc> element contains invalid URL: ' . $url;
			}
		}

		// Check for lastmod element (optional but should be valid if present)
		$lastmod = $url_element->getElementsByTagName( 'lastmod' );
		if ( $lastmod->length > 0 ) {
			$lastmod_value = trim( $lastmod->item( 0 )->textContent );
			if ( ! empty( $lastmod_value ) ) {
				// Validate ISO 8601 date format
				if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:[+-]\d{2}:\d{2}|Z)$/', $lastmod_value ) ) {
					$errors[] = 'URL <lastmod> element contains invalid date format: ' . $lastmod_value;
				}
			}
		}

		return $errors;
	}

	/**
	 * Filter dates by date queries.
	 *
	 * @param array $dates Array of dates to filter.
	 * @param array $date_queries Array of date queries.
	 * @return array Filtered dates.
	 */
	private function filter_dates_by_queries( array $dates, array $date_queries ): array {
		$filtered_dates = array();

		foreach ( $dates as $date ) {
			$date_parts = explode( '-', $date );
			if ( count( $date_parts ) !== 3 ) {
				continue;
			}

			list( $year, $month, $day ) = $date_parts;

			foreach ( $date_queries as $query ) {
				$matches = true;

				if ( isset( $query['year'] ) && $query['year'] !== (int) $year ) {
					$matches = false;
				}

				if ( isset( $query['month'] ) && $query['month'] !== (int) $month ) {
					$matches = false;
				}

				if ( isset( $query['day'] ) && $query['day'] !== (int) $day ) {
					$matches = false;
				}

				if ( $matches ) {
					$filtered_dates[] = $date;
					break;
				}
			}
		}

		return $filtered_dates;
	}

	/**
	 * Check if sitemap validation has been stopped by user request.
	 *
	 * @return bool True if stopped, false otherwise.
	 */
	private function is_stopped(): bool {
		return (bool) get_option( 'msm_stop_processing' );
	}
}
