<?php
/**
 * CLI setup and registration
 *
 * @package MSM_Sitemap
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Infrastructure\CLI;

use Automattic\MSM_Sitemap\Domain\Contracts\WordPressIntegrationInterface;
use Automattic\MSM_Sitemap\Infrastructure\CLI\Commands\GenerateCommand;
use Automattic\MSM_Sitemap\Infrastructure\CLI\Commands\DeleteCommand;
use Automattic\MSM_Sitemap\Infrastructure\CLI\Commands\ListCommand;
use Automattic\MSM_Sitemap\Infrastructure\CLI\Commands\GetCommand;
use Automattic\MSM_Sitemap\Infrastructure\CLI\Commands\StatsCommand;
use Automattic\MSM_Sitemap\Infrastructure\CLI\Commands\ValidateCommand;
use Automattic\MSM_Sitemap\Infrastructure\CLI\Commands\ExportCommand;
use Automattic\MSM_Sitemap\Infrastructure\CLI\Commands\RecountCommand;
use Automattic\MSM_Sitemap\Infrastructure\CLI\Commands\CronCommand;
use Automattic\MSM_Sitemap\Infrastructure\CLI\Commands\OptionsCommand;
use Automattic\MSM_Sitemap\Infrastructure\CLI\Commands\RecentUrlsCommand;
use function Automattic\MSM_Sitemap\Infrastructure\DI\msm_sitemap_container;

/**
 * Handles WP-CLI command setup and registration.
 *
 * Registers individual command classes following the one-class-per-command pattern.
 * Each command is a dedicated class with constructor dependency injection.
 */
class CLISetup implements WordPressIntegrationInterface {

	/**
	 * Register WordPress hooks and filters for CLI functionality.
	 */
	public function register_hooks(): void {
		// Only setup CLI commands if WP-CLI is available
		if ( ! defined( 'WP_CLI' ) || true !== \WP_CLI ) {
			return;
		}

		$container = msm_sitemap_container();

		// Register individual commands with their dependencies
		\WP_CLI::add_command( 'msm-sitemap generate', $container->get( GenerateCommand::class ) );
		\WP_CLI::add_command( 'msm-sitemap delete', $container->get( DeleteCommand::class ) );
		\WP_CLI::add_command( 'msm-sitemap list', $container->get( ListCommand::class ) );
		\WP_CLI::add_command( 'msm-sitemap get', $container->get( GetCommand::class ) );
		\WP_CLI::add_command( 'msm-sitemap stats', $container->get( StatsCommand::class ) );
		\WP_CLI::add_command( 'msm-sitemap recent-urls', $container->get( RecentUrlsCommand::class ) );
		\WP_CLI::add_command( 'msm-sitemap validate', $container->get( ValidateCommand::class ) );
		\WP_CLI::add_command( 'msm-sitemap export', $container->get( ExportCommand::class ) );
		\WP_CLI::add_command( 'msm-sitemap recount', $container->get( RecountCommand::class ) );
		\WP_CLI::add_command( 'msm-sitemap cron', $container->get( CronCommand::class ) );
		\WP_CLI::add_command( 'msm-sitemap options', $container->get( OptionsCommand::class ) );
	}
}
