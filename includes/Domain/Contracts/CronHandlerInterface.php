<?php
/**
 * Cron Handler Interface
 *
 * @package Automattic\MSM_Sitemap\Domain\Contracts
 */

declare(strict_types=1);

namespace Automattic\MSM_Sitemap\Domain\Contracts;

use Automattic\MSM_Sitemap\Domain\Contracts\WordPressIntegrationInterface;

/**
 * Interface for all cron generation handlers.
 * 
 * This interface ensures consistent behavior across all
 * cron-based sitemap generation handlers (full, incremental, etc.)
 * 
 * Cron handlers are WordPress integrations that register cron hooks and schedules.
 */
interface CronHandlerInterface extends WordPressIntegrationInterface {

	/**
	 * Register WordPress hooks and filters for cron functionality.
	 * 
	 * This method is called during plugin initialization to register
	 * any necessary WordPress action hooks or cron schedules.
	 */
	public function register_hooks(): void;

	/**
	 * Execute the generation process.
	 * 
	 * This is the main method that performs the actual sitemap generation
	 * work for this handler. Should handle all the business logic.
	 */
	public function execute(): void;

	/**
	 * Check if the generation process can/should run.
	 * 
	 * This method performs pre-conditions checking to determine if
	 * the generation process should proceed (cron enabled, posts available, etc.)
	 * 
	 * @return bool True if the handler can run, false otherwise.
	 */
	public function can_execute(): bool;
}
