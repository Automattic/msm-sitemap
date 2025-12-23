<?php
/**
 * Validate Command
 *
 * @package Automattic\MSM_Sitemap\Infrastructure\CLI\Commands
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Infrastructure\CLI\Commands;

use Automattic\MSM_Sitemap\Application\Services\SitemapValidationService;
use Automattic\MSM_Sitemap\Infrastructure\CLI\Traits\DateQueryTrait;
use WP_CLI;

/**
 * Validate sitemap XML content and structure.
 */
class ValidateCommand {

	use DateQueryTrait;

	/**
	 * The validation service.
	 *
	 * @var SitemapValidationService
	 */
	private SitemapValidationService $service;

	/**
	 * Constructor.
	 *
	 * @param SitemapValidationService $service The validation service.
	 */
	public function __construct( SitemapValidationService $service ) {
		$this->service = $service;
	}

	/**
	 * Validate sitemap XML content and structure.
	 *
	 * ## OPTIONS
	 *
	 * [--date=<date>]
	 * : Validate sitemaps for a specific date (YYYY-MM-DD format).
	 *
	 * [--year=<year>]
	 * : Validate sitemaps for a specific year.
	 *
	 * [--month=<month>]
	 * : Validate sitemaps for a specific month (YYYY-MM format).
	 *
	 * ## EXAMPLES
	 *
	 *     # Validate all sitemaps.
	 *     $ wp msm-sitemap validate
	 *
	 *     # Validate sitemaps for a specific date.
	 *     $ wp msm-sitemap validate --date=2024-01-15
	 *
	 *     # Validate sitemaps for a specific year.
	 *     $ wp msm-sitemap validate --year=2024
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		// Parse date queries
		$date_queries = $this->parse_date_query( $assoc_args['date'] ?? null, ! empty( $assoc_args['year'] ) || ! empty( $assoc_args['month'] ) );

		if ( ! empty( $assoc_args['year'] ) ) {
			$date_queries[] = array( 'year' => (int) $assoc_args['year'] );
		}

		if ( ! empty( $assoc_args['month'] ) ) {
			$parsed_month = $this->parse_date_query( $assoc_args['month'], false );
			if ( $parsed_month ) {
				$date_queries[] = $parsed_month;
			}
		}

		// Validate sitemaps using validation service directly
		$result = $this->service->validate_sitemaps( $date_queries );

		if ( $result->is_success() ) {
			WP_CLI::log( $result->get_message() );

			// Output validation errors as warnings
			foreach ( $result->get_validation_errors() as $error ) {
				WP_CLI::warning( $error );
			}
		} elseif ( 'no_sitemaps_found' === $result->get_error_code() ) {
			// For validation, treat "no sitemaps found" as a log message, not an error
			WP_CLI::log( $result->get_message() );
		} else {
			WP_CLI::error( $result->get_message() );
		}
	}
}
