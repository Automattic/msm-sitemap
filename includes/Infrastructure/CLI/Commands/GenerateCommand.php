<?php
/**
 * Generate Command
 *
 * @package Automattic\MSM_Sitemap\Infrastructure\CLI\Commands
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Infrastructure\CLI\Commands;

use Automattic\MSM_Sitemap\Application\UseCases\GenerateSitemapUseCase;
use Automattic\MSM_Sitemap\Application\Commands\GenerateSitemapCommand;
use WP_CLI;

/**
 * Generate sitemaps for the specified date or all dates.
 */
class GenerateCommand {

	/**
	 * The generate sitemap use case.
	 *
	 * @var GenerateSitemapUseCase
	 */
	private GenerateSitemapUseCase $use_case;

	/**
	 * Constructor.
	 *
	 * @param GenerateSitemapUseCase $use_case The generate sitemap use case.
	 */
	public function __construct( GenerateSitemapUseCase $use_case ) {
		$this->use_case = $use_case;
	}

	/**
	 * Generate sitemaps for the specified date or all dates.
	 *
	 * ## OPTIONS
	 *
	 * [--date=<date>]
	 * : Generate sitemaps for a specific year (YYYY), month (YYYY-MM), or day (YYYY-MM-DD).
	 *
	 * [--all]
	 * : Generate sitemaps for all years with posts.
	 *
	 * [--force]
	 * : Force regeneration even if sitemap already exists.
	 *
	 * [--quiet]
	 * : Suppress all output except errors.
	 *
	 * ## EXAMPLES
	 *
	 *     # Generate sitemaps for a specific month.
	 *     $ wp msm-sitemap generate --date=2024-07
	 *
	 *     # Generate all sitemaps.
	 *     $ wp msm-sitemap generate --all
	 *
	 *     # Force regeneration.
	 *     $ wp msm-sitemap generate --date=2024-07-15 --force
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$quiet = ! empty( $assoc_args['quiet'] );
		$force = ! empty( $assoc_args['force'] );
		$all   = ! empty( $assoc_args['all'] );
		$date  = $assoc_args['date'] ?? null;

		// Create command with parameters
		$command = new GenerateSitemapCommand(
			$date,
			array(), // Will be handled by the use case
			$force,
			$all
		);

		// Execute the use case
		$result = $this->use_case->execute( $command );

		// CLI-specific output handling
		if ( ! $quiet ) {
			if ( $result->is_success() ) {
				WP_CLI::success( $result->get_message() );
			} else {
				WP_CLI::error( $result->get_message() );
			}
		}
	}
}
