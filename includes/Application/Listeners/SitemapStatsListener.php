<?php
/**
 * Sitemap Stats Listener
 *
 * @package Automattic\MSM_Sitemap\Application\Listeners
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Application\Listeners;

use Automattic\MSM_Sitemap\Application\Events\SitemapGeneratedEvent;
use Automattic\MSM_Sitemap\Application\Services\SitemapStatsService;

/**
 * Listener for sitemap generation events that updates statistics.
 */
class SitemapStatsListener {
	/**
	 * The stats service.
	 *
	 * @var SitemapStatsService
	 */
	private SitemapStatsService $stats_service;

	/**
	 * Constructor.
	 *
	 * @param SitemapStatsService $stats_service The stats service.
	 */
	public function __construct( SitemapStatsService $stats_service )
	{
		$this->stats_service = $stats_service;
	}

	/**
	 * Handle sitemap generated events.
	 *
	 * @param SitemapGeneratedEvent $event The generation event.
	 * @return void
	 */
	public function on_sitemap_generated( SitemapGeneratedEvent $event ): void
	{
		// For now, just log the generation for debugging/monitoring
		// The SitemapStatsService doesn't have update methods, so we'll
		// just log the event. In a real implementation, you might want to:
		// 1. Add update methods to SitemapStatsService, or
		// 2. Use a different service for tracking generation events
		$this->log_generation_event( $event );
	}

	/**
	 * Log the generation event for monitoring purposes.
	 *
	 * @param SitemapGeneratedEvent $event The generation event.
	 * @return void
	 */
	private function log_generation_event( SitemapGeneratedEvent $event ): void
	{
		// Only log in debug mode
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$message = sprintf(
			'Sitemap generated: %s with %d URLs in %.2f seconds by %s',
			$event->get_date(),
			$event->get_url_count(),
			$event->get_generation_time(),
			$event->get_generated_by()
		);

		error_log( $message );
	}
}
