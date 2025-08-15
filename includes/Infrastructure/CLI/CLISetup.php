<?php
/**
 * CLI setup and registration
 *
 * @package MSM_Sitemap
 */

namespace Automattic\MSM_Sitemap\Infrastructure\CLI;

use Automattic\MSM_Sitemap\Domain\Contracts\WordPressIntegrationInterface;
use Automattic\MSM_Sitemap\Infrastructure\CLI\CLICommand;
use WP_CLI;

/**
 * Handles WP-CLI command setup and registration
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

		// Create the CLI command with proper dependency injection
		$cli_command = CLICommand::create();
		
		\WP_CLI::add_command( 'msm-sitemap', $cli_command );
	}
}
