<?php
/**
 * Stats Command
 *
 * @package Automattic\MSM_Sitemap\Infrastructure\CLI\Commands
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Infrastructure\CLI\Commands;

use Automattic\MSM_Sitemap\Application\Services\SitemapStatsService;
use WP_CLI;
use function WP_CLI\Utils\format_items;

/**
 * Show sitemap statistics.
 */
class StatsCommand {

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
	 * Show sitemap statistics (total, most recent, etc).
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format: table, json, or csv. Default: table.
	 *
	 * [--detailed]
	 * : Show detailed statistics including timeline, coverage, and storage info.
	 *
	 * [--section=<section>]
	 * : Show only a specific section: overview, timeline, url_counts, performance, coverage, storage.
	 *
	 * ## EXAMPLES
	 *
	 *     # Show basic stats.
	 *     $ wp msm-sitemap stats
	 *
	 *     # Show stats in JSON format.
	 *     $ wp msm-sitemap stats --format=json
	 *
	 *     # Show detailed stats.
	 *     $ wp msm-sitemap stats --detailed --format=table
	 *
	 *     # Show specific section.
	 *     $ wp msm-sitemap stats --section=coverage --format=json
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$detailed = ! empty( $assoc_args['detailed'] );
		$section  = $assoc_args['section'] ?? null;
		$format   = $assoc_args['format'] ?? 'table';

		if ( $detailed || $section ) {
			// Use comprehensive stats service
			$stats = $this->service->get_comprehensive_stats();

			if ( $section ) {
				if ( ! isset( $stats[ $section ] ) ) {
					WP_CLI::error(
						sprintf(
							/* translators: 1: Unknown section name, 2: Comma-separated list of available sections */
							__( 'Unknown section: %1$s. Available sections: %2$s', 'msm-sitemap' ),
							$section,
							implode( ', ', array_keys( $stats ) )
						)
					);
				}
				$stats = $stats[ $section ];
			}

			// For detailed output, use JSON format for better readability
			if ( $detailed && 'table' === $format ) {
				$format = 'json';
			}

			if ( 'json' === $format ) {
				WP_CLI::log( wp_json_encode( $stats, JSON_PRETTY_PRINT ) );
			} else {
				// For table format, show overview by default
				$overview_stats = $stats['overview'] ?? $stats;
				$items          = array( $overview_stats );
				$fields         = array_keys( $overview_stats );
				format_items( $format, $items, $fields );
			}
		} else {
			// Use basic stats from stats service for backward compatibility
			$comprehensive_stats = $this->service->get_comprehensive_stats();
			$stats               = array(
				'total'       => $comprehensive_stats['overview']['total_sitemaps'],
				'most_recent' => $comprehensive_stats['overview']['most_recent']['date'] ? $comprehensive_stats['overview']['most_recent']['date'] . ' (ID ' . $comprehensive_stats['overview']['most_recent']['id'] . ')' : '',
				'created'     => $comprehensive_stats['overview']['most_recent']['created'],
			);

			$fields = array( 'total', 'most_recent', 'created' );
			$items  = array( $stats );
			format_items( $format, $items, $fields );
		}
	}
}
