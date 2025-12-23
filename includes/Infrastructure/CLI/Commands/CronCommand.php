<?php
/**
 * Cron Command
 *
 * @package Automattic\MSM_Sitemap\Infrastructure\CLI\Commands
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Infrastructure\CLI\Commands;

use Automattic\MSM_Sitemap\Application\Services\CronManagementService;
use WP_CLI;
use function WP_CLI\Utils\format_items;

/**
 * Manage the sitemap cron functionality.
 */
class CronCommand {

	/**
	 * The cron management service.
	 *
	 * @var CronManagementService
	 */
	private CronManagementService $service;

	/**
	 * Constructor.
	 *
	 * @param CronManagementService $service The cron management service.
	 */
	public function __construct( CronManagementService $service ) {
		$this->service = $service;
	}

	/**
	 * Manage the sitemap cron functionality.
	 *
	 * ## SUBCOMMANDS
	 *
	 * [<command>]
	 * : Subcommand to run.
	 * ---
	 * default: status
	 * options:
	 *   - enable
	 *   - disable
	 *   - status
	 *   - reset
	 *   - frequency
	 * ---
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format for status command: table, json, or csv. Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     # Show cron status.
	 *     $ wp msm-sitemap cron
	 *
	 *     # Enable cron.
	 *     $ wp msm-sitemap cron enable
	 *
	 *     # Disable cron.
	 *     $ wp msm-sitemap cron disable
	 *
	 *     # Show status in JSON format.
	 *     $ wp msm-sitemap cron status --format=json
	 *
	 *     # Reset cron to clean state.
	 *     $ wp msm-sitemap cron reset
	 *
	 *     # Show or set frequency.
	 *     $ wp msm-sitemap cron frequency
	 *     $ wp msm-sitemap cron frequency hourly
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		// If no command provided, default to status
		if ( empty( $args ) ) {
			$this->status( array(), $assoc_args );
			return;
		}

		$command = $args[0];

		switch ( $command ) {
			case 'enable':
				$this->enable();
				break;
			case 'disable':
				$this->disable();
				break;
			case 'status':
				$this->status( array(), $assoc_args );
				break;
			case 'reset':
				$this->reset();
				break;
			case 'frequency':
				// Remove 'frequency' from args and pass the remaining arguments
				array_shift( $args );
				$this->frequency( $args );
				break;
			default:
				WP_CLI::error(
					sprintf(
						/* translators: %s: Unknown subcommand name */
						__( 'Unknown subcommand: %s', 'msm-sitemap' ),
						$command
					)
				);
		}
	}

	/**
	 * Enable the sitemap cron functionality.
	 */
	private function enable(): void {
		$result = $this->service->enable_cron();

		if ( $result['success'] ) {
			WP_CLI::success( '✅ ' . $result['message'] );
		} elseif ( isset( $result['error_code'] ) && 'blog_not_public' === $result['error_code'] ) {
			WP_CLI::error( '❌ ' . $result['message'] );
		} else {
			WP_CLI::warning( '⚠️ ' . $result['message'] );
		}
	}

	/**
	 * Disable the sitemap cron functionality.
	 */
	private function disable(): void {
		$result = $this->service->disable_cron();

		if ( $result['success'] ) {
			WP_CLI::success( '✅ ' . $result['message'] );

			// Check if cron was actually cleared
			$next_scheduled = wp_next_scheduled( 'msm_cron_update_sitemap' );
			if ( $next_scheduled ) {
				WP_CLI::warning( __( '⚠️ Warning: Cron event still scheduled. This may be a WordPress cron system delay.', 'msm-sitemap' ) );
			} else {
				WP_CLI::log( __( '✅ Cron events cleared successfully.', 'msm-sitemap' ) );
			}
		} else {
			WP_CLI::warning( '⚠️ ' . $result['message'] );
		}
	}

	/**
	 * Show the current status of the sitemap cron functionality.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	private function status( array $args, array $assoc_args ): void {
		$status = $this->service->get_cron_status();

		$format = $assoc_args['format'] ?? 'table';
		$fields = array( 'enabled', 'next_scheduled', 'blog_public', 'generating', 'halted', 'current_frequency' );
		$items  = array(
			array(
				'enabled'           => $status['enabled'] ? 'Yes' : 'No',
				'next_scheduled'    => $status['next_scheduled'] ? wp_date( 'Y-m-d H:i:s T', $status['next_scheduled'] ) : 'Not scheduled',
				'blog_public'       => $status['blog_public'] ? 'Yes' : 'No',
				'generating'        => $status['generating'] ? 'Yes' : 'No',
				'halted'            => $status['halted'] ? 'Yes' : 'No',
				'current_frequency' => $status['current_frequency'],
			),
		);
		format_items( $format, $items, $fields );
	}

	/**
	 * Reset the sitemap cron to a clean state.
	 */
	private function reset(): void {
		$result = $this->service->reset_cron();

		if ( $result['success'] ) {
			WP_CLI::success( '✅ ' . $result['message'] );
			WP_CLI::log( __( '📝 This simulates a fresh install state.', 'msm-sitemap' ) );
		} else {
			WP_CLI::error( '❌ ' . $result['message'] );
		}
	}

	/**
	 * Manage the sitemap cron frequency.
	 *
	 * @param array $args Positional arguments.
	 */
	private function frequency( array $args ): void {
		// If no frequency provided, show current frequency
		if ( empty( $args ) ) {
			$current_frequency = $this->service->get_current_frequency();
			$valid_frequencies = $this->service->get_valid_frequencies();

			WP_CLI::log(
				sprintf(
					/* translators: %s: Current frequency */
					__( 'Current cron frequency: %s', 'msm-sitemap' ),
					$current_frequency
				)
			);
			WP_CLI::log( __( 'Valid frequencies:', 'msm-sitemap' ) );
			foreach ( $valid_frequencies as $frequency ) {
				WP_CLI::log( sprintf( '  - %s', $frequency ) );
			}
			return;
		}

		$frequency = $args[0];
		$result    = $this->service->update_frequency( $frequency );

		if ( $result['success'] ) {
			WP_CLI::success( '✅ ' . $result['message'] );
		} else {
			WP_CLI::error( '❌ ' . $result['message'] );
		}
	}
}
