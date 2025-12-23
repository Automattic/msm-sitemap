<?php
/**
 * Recent URLs Command
 *
 * @package Automattic\MSM_Sitemap\Infrastructure\CLI\Commands
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Infrastructure\CLI\Commands;

use Automattic\MSM_Sitemap\Application\Services\SitemapStatsService;
use WP_CLI;
use function WP_CLI\Utils\format_items;

/**
 * Show recent URL counts for the last N days.
 */
class RecentUrlsCommand {

	/**
	 * The stats service.
	 *
	 * @var SitemapStatsService
	 */
	private SitemapStatsService $service;

	/**
	 * Constructor.
	 *
	 * @param SitemapStatsService $service The stats service.
	 */
	public function __construct( SitemapStatsService $service ) {
		$this->service = $service;
	}

	/**
	 * Show recent URL counts for the last N days.
	 *
	 * ## OPTIONS
	 *
	 * [--days=<days>]
	 * : Number of days to show (default: 7).
	 *
	 * [--format=<format>]
	 * : Output format: table, json, or csv. Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     # Show recent URL counts for last 14 days.
	 *     $ wp msm-sitemap recent-urls --days=14
	 *
	 *     # Show recent URL counts in JSON format.
	 *     $ wp msm-sitemap recent-urls --format=json
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$days   = (int) ( $assoc_args['days'] ?? 7 );
		$format = $assoc_args['format'] ?? 'table';

		$url_counts = $this->service->get_recent_url_counts( $days );

		if ( empty( $url_counts ) ) {
			WP_CLI::log( __( 'No recent URL counts found.', 'msm-sitemap' ) );
			return;
		}

		// Convert to items for formatting
		$items = array();
		foreach ( $url_counts as $date => $count ) {
			$items[] = array(
				'date'      => $date,
				'url_count' => $count,
			);
		}

		$fields = array( 'date', 'url_count' );
		format_items( $format, $items, $fields );
	}
}
