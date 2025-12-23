<?php
/**
 * REST API Setup
 *
 * @package Automattic\MSM_Sitemap\Infrastructure\REST
 */

declare(strict_types=1);

namespace Automattic\MSM_Sitemap\Infrastructure\REST;

use Automattic\MSM_Sitemap\Domain\Contracts\WordPressIntegrationInterface;
use Automattic\MSM_Sitemap\Infrastructure\REST\Controllers\SitemapsController;
use Automattic\MSM_Sitemap\Infrastructure\REST\Controllers\StatsController;
use Automattic\MSM_Sitemap\Infrastructure\REST\Controllers\HealthController;
use Automattic\MSM_Sitemap\Infrastructure\REST\Controllers\ValidationController;
use Automattic\MSM_Sitemap\Infrastructure\REST\Controllers\ExportController;
use Automattic\MSM_Sitemap\Infrastructure\REST\Controllers\CronController;
use Automattic\MSM_Sitemap\Infrastructure\REST\Controllers\GenerationController;
use Automattic\MSM_Sitemap\Infrastructure\REST\Controllers\RecountController;
use Automattic\MSM_Sitemap\Infrastructure\REST\Controllers\ResetController;

use function Automattic\MSM_Sitemap\Infrastructure\DI\msm_sitemap_container;

/**
 * Sets up REST API controllers and registers routes.
 */
class RESTSetup implements WordPressIntegrationInterface {

	/**
	 * Register WordPress hooks and filters for REST API functionality.
	 */
	public function register_hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register all REST API routes via individual controllers.
	 */
	public function register_routes(): void {
		$container = msm_sitemap_container();

		$controllers = array(
			SitemapsController::class,
			StatsController::class,
			HealthController::class,
			ValidationController::class,
			ExportController::class,
			CronController::class,
			GenerationController::class,
			RecountController::class,
			ResetController::class,
		);

		foreach ( $controllers as $controller_class ) {
			$controller = $container->get( $controller_class );
			$controller->register_routes();
		}
	}
}
