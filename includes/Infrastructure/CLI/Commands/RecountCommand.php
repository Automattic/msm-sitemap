<?php
/**
 * Recount Command
 *
 * @package Automattic\MSM_Sitemap\Infrastructure\CLI\Commands
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Infrastructure\CLI\Commands;

use Automattic\MSM_Sitemap\Application\Services\SitemapService;
use WP_CLI;

/**
 * Recount URLs in all sitemaps and update counts.
 */
class RecountCommand {

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
	 * Recount URLs in all sitemaps and update counts.
	 *
	 * ## OPTIONS
	 *
	 * [--full]
	 * : Perform a full recount by parsing XML (slower but more accurate).
	 *
	 * ## EXAMPLES
	 *
	 *     # Quick recount.
	 *     $ wp msm-sitemap recount
	 *
	 *     # Full recount by parsing XML.
	 *     $ wp msm-sitemap recount --full
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$full_recount = ! empty( $assoc_args['full'] );

		// Recount URLs using service
		$result = $this->service->recount_urls( $full_recount );

		if ( $result->is_success() ) {
			WP_CLI::log( $result->get_message() );

			// Output recount errors as warnings
			foreach ( $result->get_recount_errors() as $error ) {
				WP_CLI::warning( $error );
			}
		} else {
			WP_CLI::log( $result->get_message() );
		}
	}
}
