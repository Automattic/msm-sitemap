<?php
/**
 * CLI setup and registration
 *
 * @package MSM_Sitemap
 */

namespace Automattic\MSM_Sitemap\Infrastructure\CLI;

use WP_CLI;

/**
 * Handles WP-CLI command setup and registration
 */
class CLISetup {

	/**
	 * Initialize CLI functionality
	 */
	public static function init(): void {
		// Only setup CLI commands if WP-CLI is available
		if ( ! defined( 'WP_CLI' ) || true !== \WP_CLI ) {
			return;
		}

		// Create the CLI command with proper dependency injection
		$cli_command = CLI_Command::create();
		
		\WP_CLI::add_command( 'msm-sitemap', $cli_command );
	}
}
