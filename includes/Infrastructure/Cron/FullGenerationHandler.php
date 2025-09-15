<?php
/**
 * Full Generation Cron Handler
 *
 * @package Automattic\MSM_Sitemap\Infrastructure\Cron
 */

declare(strict_types=1);

namespace Automattic\MSM_Sitemap\Infrastructure\Cron;

use Automattic\MSM_Sitemap\Application\Services\SitemapService;
use Automattic\MSM_Sitemap\Application\Services\SitemapGenerator;
use Automattic\MSM_Sitemap\Application\UseCases\GenerateSitemapUseCase;
use Automattic\MSM_Sitemap\Application\Commands\GenerateSitemapCommand;
use Automattic\MSM_Sitemap\Domain\Contracts\CronHandlerInterface;
use Automattic\MSM_Sitemap\Domain\Utilities\DateUtility;
use Automattic\MSM_Sitemap\Infrastructure\Cron\CronSchedulingService;
use Automattic\MSM_Sitemap\Infrastructure\Repositories\SitemapPostRepository;
use Automattic\MSM_Sitemap\Infrastructure\Repositories\PostRepository;

/**
 * Handler class for managing full sitemap generation via cascading cron jobs.
 * 
 * This handler orchestrates the background generation of complete sitemaps
 * by breaking the work into manageable chunks (year → month → day) and 
 * maintaining resumable state between cron events.
 */
class FullGenerationHandler implements CronHandlerInterface {

	/**
	 * How far apart should full cron generation events be spaced (in seconds).
	 *
	 * @var int
	 */
	private const INTERVAL_PER_GENERATION_EVENT = 60;

	/**
	 * The cron scheduling service.
	 *
	 * @var CronSchedulingService
	 */
	private CronSchedulingService $cron_scheduler;

	/**
	 * The post repository.
	 *
	 * @var PostRepository
	 */
	private PostRepository $post_repository;

	/**
	 * The sitemap generator.
	 *
	 * @var SitemapGenerator
	 */
	private SitemapGenerator $sitemap_generator;

	/**
	 * The generate sitemap use case.
	 *
	 * @var GenerateSitemapUseCase
	 */
	private GenerateSitemapUseCase $generate_use_case;

	/**
	 * Constructor.
	 *
	 * @param CronSchedulingService $cron_scheduler The cron scheduling service.
	 * @param PostRepository $post_repository The post repository.
	 * @param SitemapGenerator $sitemap_generator The sitemap generator.
	 * @param GenerateSitemapUseCase $generate_use_case The generate sitemap use case.
	 */
	public function __construct( 
		CronSchedulingService $cron_scheduler,
		PostRepository $post_repository,
		SitemapGenerator $sitemap_generator,
		GenerateSitemapUseCase $generate_use_case
	) {
		$this->cron_scheduler    = $cron_scheduler;
		$this->post_repository   = $post_repository;
		$this->sitemap_generator = $sitemap_generator;
		$this->generate_use_case = $generate_use_case;
	}

	/**
	 * Register WordPress hooks and filters for cron functionality.
	 */
	public function register_hooks(): void {
		add_action( 'msm_cron_generate_sitemap_for_year', array( $this, 'generate_sitemap_for_year' ) );
		add_action( 'msm_cron_generate_sitemap_for_year_month', array( $this, 'generate_sitemap_for_year_month' ) );
		add_action( 'msm_cron_generate_sitemap_for_year_month_day', array( $this, 'generate_sitemap_for_year_month_day' ) );
	}

	/**
	 * Execute the full generation process.
	 * 
	 * This initiates the cascading full sitemap generation by directly
	 * starting the year-by-year process.
	 */
	public function execute(): void {
		if ( ! $this->can_execute() ) {
			return;
		}

		// Set the generation in progress flag
		update_option( 'msm_generation_in_progress', true );

		$this->start_generation();
	}

	/**
	 * Check if the full generation process can/should run.
	 * 
	 * @return bool True if the handler can run, false otherwise.
	 */
	public function can_execute(): bool {
		// Check if cron is enabled
		if ( ! $this->cron_scheduler->is_enabled() ) {
			return false;
		}

		// Check if generation is already in progress
		if ( get_option( 'msm_generation_in_progress', false ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Start or continue the cascading full generation.
	 *
	 * This method kicks off the full generation by determining the appropriate
	 * years to process and scheduling the first year generation event.
	 */
	private function start_generation(): void {
		// Clear any existing generation state to start fresh
		delete_option( 'msm_years_to_process' );
		delete_option( 'msm_months_to_process' );
		delete_option( 'msm_days_to_process' );

		// Check if a partial generation is already running
		$is_partial_or_running = get_option( 'msm_generation_in_progress', false );

		if ( empty( $is_partial_or_running ) ) {
			$all_years_with_posts = $this->post_repository->get_years_with_posts();
			update_option( 'msm_years_to_process', $all_years_with_posts );
		} else {
			// Continue with existing year list if generation was in progress
			$all_years_with_posts = get_option( 'msm_years_to_process', array() );
		}

		if ( ! empty( $all_years_with_posts ) ) {
			// Schedule the first year for processing
			$year = array_shift( $all_years_with_posts );
			update_option( 'msm_years_to_process', $all_years_with_posts );

			// Schedule year generation
			wp_schedule_single_event( time() + 10, 'msm_cron_generate_sitemap_for_year', array( $year ) );
		} else {
			// No years to process, mark generation as complete
			delete_option( 'msm_generation_in_progress' );
		}
	}

	/**
	 * Get a configured sitemap service instance.
	 *
	 * @return SitemapService Configured sitemap service.
	 */
	private function get_sitemap_service() {
		$repository = new SitemapPostRepository();
		return new SitemapService( $this->sitemap_generator, $repository );
	}

	/**
	 * Generate sitemaps for a specific year.
	 *
	 * This method processes all months within a year, scheduling
	 * month generation events and managing the cascading process.
	 *
	 * @param int $year Year to generate sitemaps for.
	 */
	public function generate_sitemap_for_year( $year ) {
		if ( ! $this->can_execute() ) {
			return;
		}

		// Check if generation should be stopped
		if ( (bool) get_option( 'msm_sitemap_stop_generation' ) ) {
			delete_option( 'msm_generation_in_progress' );
			delete_option( 'msm_sitemap_stop_generation' );
			return;
		}

		// Get all months for this year that have posts
		$months_with_posts = array();
		for ( $month = 1; $month <= 12; $month++ ) {
			$start_date    = sprintf( '%04d-%02d-01', $year, $month );
			$days_in_month = DateUtility::get_days_in_month( $year, $month );
			$end_date      = sprintf( '%04d-%02d-%02d', $year, $month, $days_in_month );
			
			if ( $this->post_repository->date_range_has_posts( $start_date, $end_date ) ) {
				$months_with_posts[] = $month;
			}
		}

		if ( ! empty( $months_with_posts ) ) {
			// Store months to process and schedule the first month
			update_option( 'msm_months_to_process', $months_with_posts );
			update_option( 'msm_current_year', $year );

			// Prefer the current month when processing the current year
			$current_month = (int) gmdate( 'n' );
			$month         = in_array( $current_month, $months_with_posts, true ) ? $current_month : $months_with_posts[0];

			// Schedule month generation without removing it from the stored list
			wp_schedule_single_event( time() + self::INTERVAL_PER_GENERATION_EVENT, 'msm_cron_generate_sitemap_for_year_month', array( $year, $month ) );
		} else {
			// No months with posts, continue to next year
			$this->continue_to_next_year();
		}
	}

	/**
	 * Generate sitemaps for a specific year and month.
	 *
	 * This method processes all days within a month, scheduling
	 * day generation events and managing the cascading process.
	 *
	 * @param int $year Year of the month to process.
	 * @param int $month Month to generate sitemaps for.
	 */
	public function generate_sitemap_for_year_month( $year, $month ) {
		if ( ! $this->can_execute() ) {
			return;
		}

		// Check if generation should be stopped
		if ( (bool) get_option( 'msm_sitemap_stop_generation' ) ) {
			delete_option( 'msm_generation_in_progress' );
			delete_option( 'msm_sitemap_stop_generation' );
			return;
		}

		// Get all days for this month that have posts
		$days_with_posts = array();
		$days_in_month   = DateUtility::get_days_in_month( $year, $month );
		
		for ( $day = 1; $day <= $days_in_month; $day++ ) {
			$date = sprintf( '%04d-%02d-%02d', $year, $month, $day );
			
			$post_repository = new PostRepository();
			if ( $post_repository->get_post_ids_for_date( $date ) ) {
				$days_with_posts[] = $day;
			}
		}

		if ( ! empty( $days_with_posts ) ) {
			// Store days to process and schedule the first day
			update_option( 'msm_days_to_process', $days_with_posts );
			update_option( 'msm_current_month', $month );

			// Prefer current day (or latest past day) when processing current month
			$today     = (int) gmdate( 'j' );
			$preferred = $today;
			// If today is not in the list (e.g., no posts today), choose the first available day
			if ( ! in_array( $preferred, $days_with_posts, true ) ) {
				$preferred = $days_with_posts[0];
			}

			// Schedule day generation without removing it from the stored list
			wp_schedule_single_event( time() + self::INTERVAL_PER_GENERATION_EVENT, 'msm_cron_generate_sitemap_for_year_month_day', array( $year, $month, $preferred ) );
		} else {
			// No days with posts, continue to next month
			$this->continue_to_next_month();
		}
	}

	/**
	 * Generate sitemap for a specific date.
	 *
	 * This is the final step in the cascading process that actually
	 * generates the sitemap for a specific year/month/day combination.
	 *
	 * @param int $year Year of the date.
	 * @param int $month Month of the date.
	 * @param int $day Day of the date.
	 */
	public function generate_sitemap_for_year_month_day( $year, $month, $day ) {
		if ( ! $this->can_execute() ) {
			return;
		}

		// Check if generation should be stopped
		if ( (bool) get_option( 'msm_sitemap_stop_generation' ) ) {
			delete_option( 'msm_generation_in_progress' );
			delete_option( 'msm_sitemap_stop_generation' );
			return;
		}

		$date = sprintf( '%04d-%02d-%02d', $year, $month, $day );
		
		// Use the new use case for sitemap generation
		$command = new GenerateSitemapCommand( $date, array(), true, false );
		$result  = $this->generate_use_case->execute( $command );

		// Update last update timestamp if sitemap was successfully created
		if ( $result->is_success() ) {
			update_option( 'msm_sitemap_last_update', current_time( 'timestamp' ) );
		}

		// Continue to next day or cascade up
		$this->continue_to_next_day();
	}

	/**
	 * Continue to the next day in the current month.
	 *
	 * Handles the cascading logic for day-by-day processing.
	 */
	private function continue_to_next_day(): void {
		$days_to_process = get_option( 'msm_days_to_process', array() );
		$current_year    = get_option( 'msm_current_year' );
		$current_month   = get_option( 'msm_current_month' );

		if ( ! empty( $days_to_process ) && $current_year && $current_month ) {
			// Process next day
			$day = array_shift( $days_to_process );
			update_option( 'msm_days_to_process', $days_to_process );

			// Schedule next day generation
			wp_schedule_single_event( time() + self::INTERVAL_PER_GENERATION_EVENT, 'msm_cron_generate_sitemap_for_year_month_day', array( $current_year, $current_month, $day ) );
		} else {
			// No more days, continue to next month
			delete_option( 'msm_days_to_process' );
			delete_option( 'msm_current_month' );
			$this->continue_to_next_month();
		}
	}

	/**
	 * Continue to the next month in the current year.
	 *
	 * Handles the cascading logic for month-by-month processing.
	 */
	private function continue_to_next_month(): void {
		$months_to_process = get_option( 'msm_months_to_process', array() );
		$current_year      = get_option( 'msm_current_year' );

		if ( ! empty( $months_to_process ) && $current_year ) {
			// Process next month
			$month = array_shift( $months_to_process );
			update_option( 'msm_months_to_process', $months_to_process );

			// Schedule next month generation
			wp_schedule_single_event( time() + self::INTERVAL_PER_GENERATION_EVENT, 'msm_cron_generate_sitemap_for_year_month', array( $current_year, $month ) );
		} else {
			// No more months, continue to next year
			delete_option( 'msm_months_to_process' );
			delete_option( 'msm_current_year' );
			$this->continue_to_next_year();
		}
	}

	/**
	 * Continue to the next year.
	 *
	 * Handles the cascading logic for year-by-year processing.
	 */
	private function continue_to_next_year(): void {
		$years_to_process = get_option( 'msm_years_to_process', array() );

		if ( ! empty( $years_to_process ) ) {
			// Process next year
			$year = array_shift( $years_to_process );
			update_option( 'msm_years_to_process', $years_to_process );

			// Schedule next year generation
			wp_schedule_single_event( time() + self::INTERVAL_PER_GENERATION_EVENT, 'msm_cron_generate_sitemap_for_year', array( $year ) );
		} else {
			// No more years, generation is complete
			delete_option( 'msm_years_to_process' );
			delete_option( 'msm_generation_in_progress' );
			delete_option( 'msm_sitemap_stop_generation' );
			
			// Update the last run timestamp
			update_option( 'msm_sitemap_update_last_run', current_time( 'timestamp' ) );
		}
	}
}
