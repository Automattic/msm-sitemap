<?php
/**
 * Cron Handler Interface
 *
 * @package Automattic\MSM_Sitemap\Domain\Contracts
 */

declare(strict_types=1);

namespace Automattic\MSM_Sitemap\Domain\Contracts;

/**
 * Interface for all cron generation handlers.
 * 
 * This interface ensures consistent behavior across all
 * cron-based sitemap generation handlers (full, incremental, etc.)
 */
interface CronHandlerInterface {

	/**
	 * Set up the cron handler (register hooks, schedules, etc.)
	 * 
	 * This method is called during plugin initialization to register
	 * any necessary WordPress action hooks or cron schedules.
	 */
	public static function setup(): void;

	/**
	 * Execute the generation process.
	 * 
	 * This is the main method that performs the actual sitemap generation
	 * work for this handler. Should handle all the business logic.
	 */
	public static function execute(): void;

	/**
	 * Check if the generation process can/should run.
	 * 
	 * This method performs pre-conditions checking to determine if
	 * the generation process should proceed (cron enabled, posts available, etc.)
	 * 
	 * @return bool True if the handler can run, false otherwise.
	 */
	public static function can_execute(): bool;
}
