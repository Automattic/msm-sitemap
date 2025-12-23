<?php
/**
 * Delete Command
 *
 * @package Automattic\MSM_Sitemap\Infrastructure\CLI\Commands
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Infrastructure\CLI\Commands;

use Automattic\MSM_Sitemap\Application\Services\SitemapService;
use Automattic\MSM_Sitemap\Infrastructure\CLI\Traits\DateQueryTrait;
use WP_CLI;

/**
 * Delete sitemaps for the specified date or all dates.
 */
class DeleteCommand {

	use DateQueryTrait;

	/**
	 * The sitemap service.
	 *
	 * @var SitemapService
	 */
	private SitemapService $service;

	/**
	 * Constructor.
	 *
	 * @param SitemapService $service The sitemap service.
	 */
	public function __construct( SitemapService $service ) {
		$this->service = $service;
	}

	/**
	 * Delete sitemaps for the specified date or all dates.
	 *
	 * ## OPTIONS
	 *
	 * [--date=<date>]
	 * : Delete sitemaps for a specific year (YYYY), month (YYYY-MM), or day (YYYY-MM-DD).
	 *
	 * [--all]
	 * : Delete all sitemaps. Requires confirmation.
	 *
	 * [--quiet]
	 * : Suppress all output except errors.
	 *
	 * [--yes]
	 * : Answer yes to any confirmation prompts.
	 *
	 * ## SAFETY
	 *
	 * You must specify either --date or --all. If --all is used, or --date matches multiple sitemaps, you must confirm deletion (or use --yes).
	 *
	 * ## EXAMPLES
	 *
	 *     # Delete sitemaps for a specific month.
	 *     $ wp msm-sitemap delete --date=2024-07
	 *
	 *     # Delete all sitemaps without confirmation.
	 *     $ wp msm-sitemap delete --all --yes
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$quiet = ! empty( $assoc_args['quiet'] );
		$all   = ! empty( $assoc_args['all'] );
		$date  = $assoc_args['date'] ?? null;
		$yes   = ! empty( $assoc_args['yes'] );

		if ( ! $all && empty( $date ) ) {
			WP_CLI::error( __( 'You must specify either --date or --all to delete sitemaps.', 'msm-sitemap' ) );
		}

		if ( $all ) {
			// Confirm bulk delete
			if ( ! $yes ) {
				WP_CLI::confirm( 'Are you sure you want to delete ALL sitemaps?', $assoc_args );
			}
			$result = $this->service->delete_all();
		} else {
			$date_queries = $this->parse_date_query( $date, false );

			// Count how many sitemaps would be deleted for confirmation
			$to_delete_count = $this->service->count_deletable_sitemaps( $date_queries );

			if ( $to_delete_count > 1 && ! $yes ) {
				WP_CLI::confirm( sprintf( 'Are you sure you want to delete %d sitemaps for the specified date?', $to_delete_count ), $assoc_args );
			}

			$result = $this->service->delete_for_date_queries( $date_queries );
		}

		if ( ! $quiet ) {
			if ( $result->is_success() ) {
				WP_CLI::success( $result->get_message() );
			} else {
				WP_CLI::log( $result->get_message() );
			}
		}
	}
}
