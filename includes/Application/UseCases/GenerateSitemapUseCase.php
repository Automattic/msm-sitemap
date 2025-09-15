<?php
/**
 * Generate Sitemap Use Case
 *
 * @package Automattic\MSM_Sitemap\Application\UseCases
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Application\UseCases;

use Automattic\MSM_Sitemap\Application\Commands\GenerateSitemapCommand;
use Automattic\MSM_Sitemap\Application\DTOs\SitemapOperationResult;
use Automattic\MSM_Sitemap\Application\Events\SitemapGeneratedEvent;
use Automattic\MSM_Sitemap\Application\Services\SitemapService;
use Automattic\MSM_Sitemap\Infrastructure\Events\EventDispatcher;

/**
 * Use case for generating sitemaps.
 *
 * Orchestrates the business logic for sitemap generation and dispatches events.
 */
class GenerateSitemapUseCase {

	/**
	 * The sitemap service.
	 *
	 * @var SitemapService
	 */
	private SitemapService $sitemap_service;

	/**
	 * The event dispatcher.
	 *
	 * @var EventDispatcher
	 */
	private EventDispatcher $event_dispatcher;

	/**
	 * Constructor.
	 *
	 * @param SitemapService    $sitemap_service    The sitemap service.
	 * @param EventDispatcher   $event_dispatcher   The event dispatcher.
	 */
	public function __construct(
		SitemapService $sitemap_service,
		EventDispatcher $event_dispatcher
	) {
		$this->sitemap_service  = $sitemap_service;
		$this->event_dispatcher = $event_dispatcher;
	}

	/**
	 * Execute the sitemap generation use case.
	 *
	 * @param GenerateSitemapCommand $command The command containing generation parameters.
	 * @return SitemapOperationResult The result of the operation.
	 */
	public function execute( GenerateSitemapCommand $command ): SitemapOperationResult {
		// Validate the command
		if ( ! $command->validate() ) {
			return SitemapOperationResult::failure(
				$command->get_error_message(),
				'validation_failed'
			);
		}

		// Prepare date queries based on command parameters
		$date_queries = $this->prepare_date_queries( $command );

		// Execute the generation
		$start_time      = microtime( true );
		$result          = $this->sitemap_service->generate_for_date_queries(
			$date_queries,
			$command->is_force()
		);
		$generation_time = microtime( true ) - $start_time;

		// Dispatch event if generation was successful
		if ( $result->is_success() && $result->get_count() > 0 ) {
			$this->dispatch_generation_events( $command, $result, $generation_time );
		}

		return $result;
	}

	/**
	 * Prepare date queries based on the command parameters.
	 *
	 * @param GenerateSitemapCommand $command The command.
	 * @return array The prepared date queries.
	 */
	private function prepare_date_queries( GenerateSitemapCommand $command ): array {
		// If specific date queries are provided, use them
		if ( ! empty( $command->get_date_queries() ) ) {
			return $command->get_date_queries();
		}

		// If generating for all years, create comprehensive date queries
		if ( $command->is_all() ) {
			return $this->create_all_years_queries();
		}

		// If a specific date is provided, convert it to date queries
		if ( ! empty( $command->get_date() ) ) {
			return $this->convert_date_to_queries( $command->get_date() );
		}

		// Fallback: empty array
		return array();
	}

	/**
	 * Create date queries for all years from 1970 to current year.
	 *
	 * @return array Array of date queries.
	 */
	private function create_all_years_queries(): array {
		$current_year = (int) gmdate( 'Y' );
		$queries      = array();

		for ( $year = 1970; $year <= $current_year; $year++ ) {
			$queries[] = array( 'year' => $year );
		}

		return $queries;
	}

	/**
	 * Convert a date string to date queries.
	 *
	 * @param string $date The date string (YYYY, YYYY-MM, or YYYY-MM-DD).
	 * @return array Array of date queries.
	 */
	private function convert_date_to_queries( string $date ): array {
		$parts = explode( '-', $date );
		$query = array( 'year' => (int) $parts[0] );

		if ( isset( $parts[1] ) ) {
			$query['month'] = (int) $parts[1];
		}

		if ( isset( $parts[2] ) ) {
			$query['day'] = (int) $parts[2];
		}

		return array( $query );
	}

	/**
	 * Dispatch generation events for successful operations.
	 *
	 * @param GenerateSitemapCommand $command         The command that was executed.
	 * @param SitemapOperationResult $result          The result of the operation.
	 * @param float                  $generation_time The time taken to generate.
	 * @return void
	 */
	private function dispatch_generation_events(
		GenerateSitemapCommand $command,
		SitemapOperationResult $result,
		float $generation_time
	): void {
		// For simplicity, we'll dispatch one event per successful generation
		$date = $command->get_date() ?? 'multiple';
		
		$event = new SitemapGeneratedEvent(
			$date,
			$result->get_count(),
			$generation_time,
			$this->determine_generation_source()
		);

		$this->event_dispatcher->dispatch( $event );
	}

	/**
	 * Determine the source that triggered the generation.
	 *
	 * @return string The generation source.
	 */
	private function determine_generation_source(): string {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return 'cli';
		}

		if ( wp_doing_ajax() ) {
			return 'ajax';
		}

		if ( wp_doing_cron() ) {
			return 'cron';
		}

		return 'rest';
	}
}
