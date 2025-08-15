<?php
/**
 * WordPress Integration Interface
 *
 * @package Automattic\MSM_Sitemap\Domain\Contracts
 */

declare(strict_types=1);

namespace Automattic\MSM_Sitemap\Domain\Contracts;

/**
 * Interface for classes that integrate with WordPress hooks and filters.
 * 
 * This interface ensures consistent behavior across all classes that need
 * to register WordPress actions and filters during plugin initialization.
 * 
 * Classes implementing this interface should register their WordPress hooks
 * in the register_hooks() method, which is called during plugin bootstrap.
 */
interface WordPressIntegrationInterface {

		/**
	 * Register WordPress hooks and filters.
	 *
	 * This method is called during plugin initialization to register
	 * any necessary WordPress action hooks, filters, or other integrations.
	 *
	 * Classes should register all their WordPress hooks in this method
	 * rather than in the constructor to ensure proper initialization order.
	 */
	public function register_hooks(): void;
}
