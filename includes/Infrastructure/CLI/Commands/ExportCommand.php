<?php
/**
 * Export Command
 *
 * @package Automattic\MSM_Sitemap\Infrastructure\CLI\Commands
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Infrastructure\CLI\Commands;

use Automattic\MSM_Sitemap\Application\Services\SitemapExportService;
use Automattic\MSM_Sitemap\Infrastructure\CLI\Traits\DateQueryTrait;
use WP_CLI;

/**
 * Export sitemaps for the specified date or all dates.
 */
class ExportCommand {

	use DateQueryTrait;

	/**
	 * The export service.
	 *
	 * @var SitemapExportService
	 */
	private SitemapExportService $service;

	/**
	 * Constructor.
	 *
	 * @param SitemapExportService $service The export service.
	 */
	public function __construct( SitemapExportService $service ) {
		$this->service = $service;
	}

	/**
	 * Export sitemaps for the specified date or all dates.
	 *
	 * ## OPTIONS
	 *
	 * [--date=<date>]
	 * : Export sitemaps for a specific year (YYYY), month (YYYY-MM), or day (YYYY-MM-DD).
	 *
	 * [--all]
	 * : Export all sitemaps.
	 *
	 * --output=<path>
	 * : Output directory or file path. (Required)
	 *
	 * [--pretty]
	 * : Pretty-print (indent) the exported XML for human readability.
	 *
	 * ## EXAMPLES
	 *
	 *     # Export all sitemaps.
	 *     $ wp msm-sitemap export --all --output=export
	 *
	 *     # Export sitemaps for a specific month with pretty printing.
	 *     $ wp msm-sitemap export --date=2024-07 --output=export --pretty
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		if ( empty( $assoc_args['output'] ) ) {
			WP_CLI::error( __( 'You must specify an output directory with --output. Example: --output=/path/to/dir', 'msm-sitemap' ) );
		}

		$output       = $assoc_args['output'];
		$all          = ! empty( $assoc_args['all'] );
		$date         = $assoc_args['date'] ?? null;
		$pretty       = ! empty( $assoc_args['pretty'] );
		$date_queries = $this->parse_date_query( $date, $all );

		// Export sitemaps using export service directly
		$result = $this->service->export_sitemaps( $output, $date_queries, $pretty );

		if ( $result['success'] ) {
			WP_CLI::success( $result['message'] );

			// Output export errors as warnings
			foreach ( $result['errors'] as $error ) {
				WP_CLI::warning( $error );
			}

			// Show platform-specific open command if files were exported
			if ( $result['count'] > 0 && isset( $result['output_dir'] ) ) {
				$quoted_dir = '"' . $result['output_dir'] . '"';
				if ( strtoupper( substr( PHP_OS, 0, 3 ) ) === 'DAR' ) {
					/* translators: %s is the path to the exported sitemaps. */
					WP_CLI::log( sprintf( __( 'To view the files, run: open %s', 'msm-sitemap' ), $quoted_dir ) );
				} elseif ( strtoupper( substr( PHP_OS, 0, 3 ) ) === 'WIN' ) {
					/* translators: %s is the path to the exported sitemaps. */
					WP_CLI::log( sprintf( __( 'To view the files, run: start %s', 'msm-sitemap' ), $quoted_dir ) );
				} else {
					/* translators: %s is the path to the exported sitemaps. */
					WP_CLI::log( sprintf( __( 'To view the files, run: xdg-open %s', 'msm-sitemap' ), $quoted_dir ) );
				}
			}
		} else {
			WP_CLI::error( $result['message'] );
		}
	}
}
