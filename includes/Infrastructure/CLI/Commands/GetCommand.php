<?php
/**
 * Get Command
 *
 * @package Automattic\MSM_Sitemap\Infrastructure\CLI\Commands
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Infrastructure\CLI\Commands;

use Automattic\MSM_Sitemap\Application\Services\SitemapService;
use WP_CLI;
use function WP_CLI\Utils\format_items;

/**
 * Get details for a sitemap by ID or date.
 */
class GetCommand {

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
	 * Get details for a sitemap by ID or date.
	 *
	 * ## OPTIONS
	 *
	 * <id|date>
	 * : The sitemap post ID or date (YYYY-MM-DD, YYYY-MM, YYYY).
	 *
	 * [--format=<format>]
	 * : Output format: table, json, or csv. Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     # Get sitemap by ID.
	 *     $ wp msm-sitemap get 123
	 *
	 *     # Get sitemap by date in JSON format.
	 *     $ wp msm-sitemap get 2024-07-10 --format=json
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		if ( empty( $args ) ) {
			WP_CLI::error( __( 'No ID or date provided.', 'msm-sitemap' ) );
		}

		$input  = $args[0] ?? null;
		$format = $assoc_args['format'] ?? 'table';

		$items = array();

		if ( is_numeric( $input ) ) {
			// Get by ID
			$sitemap_data = $this->service->get_sitemap_by_id( (int) $input );
			if ( ! $sitemap_data ) {
				WP_CLI::error( __( 'Sitemap not found for that ID.', 'msm-sitemap' ) );
			}
			$items[] = $sitemap_data;
		} else {
			// Get by date
			$sitemap_data = $this->service->get_sitemaps_by_date( $input );
			if ( empty( $sitemap_data ) ) {
				WP_CLI::error( __( 'No sitemaps found for that date.', 'msm-sitemap' ) );
			}
			if ( count( $sitemap_data ) > 1 && 'json' !== $format ) {
				WP_CLI::warning( __( 'Multiple sitemaps found for that date. Showing all.', 'msm-sitemap' ) );
			}
			$items = $sitemap_data;
		}

		format_items( $format, $items, array( 'id', 'date', 'url_count', 'status', 'last_modified', 'sitemap_url' ) );
	}
}
