<?php
/**
 * Options Command
 *
 * @package Automattic\MSM_Sitemap\Infrastructure\CLI\Commands
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Infrastructure\CLI\Commands;

use Automattic\MSM_Sitemap\Application\Services\SettingsService;
use WP_CLI;
use function WP_CLI\Utils\format_items;

/**
 * Manage sitemap options.
 */
class OptionsCommand {

	/**
	 * The settings service.
	 *
	 * @var SettingsService
	 */
	private SettingsService $service;

	/**
	 * Constructor.
	 *
	 * @param SettingsService $service The settings service.
	 */
	public function __construct( SettingsService $service ) {
		$this->service = $service;
	}

	/**
	 * Manage sitemap options.
	 *
	 * ## SUBCOMMANDS
	 *
	 * [<command>]
	 * : Subcommand to run.
	 * ---
	 * default: list
	 * options:
	 *   - list
	 *   - get
	 *   - update
	 *   - delete
	 *   - reset
	 * ---
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format: table, json, csv, count, yaml. Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     # List all options.
	 *     $ wp msm-sitemap options
	 *
	 *     # List options in JSON format.
	 *     $ wp msm-sitemap options list --format=json
	 *
	 *     # Get a specific option.
	 *     $ wp msm-sitemap options get include_images
	 *
	 *     # Update an option.
	 *     $ wp msm-sitemap options update featured_images 1
	 *     $ wp msm-sitemap options update content_images false
	 *     $ wp msm-sitemap options update max_images_per_sitemap 500
	 *
	 *     # Reset all options to defaults.
	 *     $ wp msm-sitemap options reset
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		// If no command provided, default to list
		if ( empty( $args ) ) {
			$this->list_options( $assoc_args );
			return;
		}

		$command = $args[0];

		switch ( $command ) {
			case 'list':
				$this->list_options( $assoc_args );
				break;
			case 'get':
				$this->get_option( $args );
				break;
			case 'update':
				$this->update_option( $args );
				break;
			case 'delete':
				$this->delete_option( $args );
				break;
			case 'reset':
				$this->reset_options();
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
	 * List all sitemap options.
	 *
	 * @param array $assoc_args Associative arguments.
	 */
	private function list_options( array $assoc_args ): void {
		$settings = $this->service->get_all_settings();
		$format   = $assoc_args['format'] ?? 'table';

		if ( 'json' === $format ) {
			WP_CLI::log( wp_json_encode( $settings, JSON_PRETTY_PRINT ) );
		} else {
			$items = array();
			foreach ( $settings as $key => $value ) {
				$items[] = array(
					'option' => $key,
					'value'  => $value,
				);
			}
			$fields = array( 'option', 'value' );
			format_items( $format, $items, $fields );
		}
	}

	/**
	 * Get a specific option value.
	 *
	 * @param array $args Positional arguments.
	 */
	private function get_option( array $args ): void {
		if ( empty( $args[1] ) ) {
			WP_CLI::error( __( 'Please specify an option name.', 'msm-sitemap' ) );
		}

		$option_name = $args[1];
		$value       = $this->service->get_setting( $option_name );

		if ( null === $value ) {
			WP_CLI::error(
				sprintf(
					/* translators: %s: Option name */
					__( 'Option %s not found.', 'msm-sitemap' ),
					$option_name
				)
			);
		}

		WP_CLI::log( $value );
	}

	/**
	 * Update an option value.
	 *
	 * @param array $args Positional arguments.
	 */
	private function update_option( array $args ): void {
		if ( empty( $args[1] ) ) {
			WP_CLI::error( __( 'Please specify an option name.', 'msm-sitemap' ) );
		}

		if ( empty( $args[2] ) ) {
			WP_CLI::error( __( 'Please specify an option value.', 'msm-sitemap' ) );
		}

		$option_name  = $args[1];
		$option_value = $args[2];

		// Validate option name
		$valid_options = array( 'include_images', 'featured_images', 'content_images', 'max_images_per_sitemap' );

		if ( ! in_array( $option_name, $valid_options, true ) ) {
			WP_CLI::error(
				sprintf(
					/* translators: 1: Unknown option name, 2: Comma-separated list of valid options */
					__( 'Unknown option: %1$s. Valid options: %2$s', 'msm-sitemap' ),
					$option_name,
					implode( ', ', $valid_options )
				)
			);
		}

		$result = $this->service->update_setting( $option_name, $this->parse_option_value( $option_value ) );

		if ( $result['success'] ) {
			WP_CLI::success(
				sprintf(
					/* translators: %s: Option name */
					__( 'Updated %s option.', 'msm-sitemap' ),
					$option_name
				)
			);
		} else {
			WP_CLI::error( '❌ ' . $result['message'] );
		}
	}

	/**
	 * Delete an option.
	 *
	 * @param array $args Positional arguments.
	 */
	private function delete_option( array $args ): void {
		if ( empty( $args[1] ) ) {
			WP_CLI::error( __( 'Please specify an option name.', 'msm-sitemap' ) );
		}

		$option_name = $args[1];

		// Validate option name
		$valid_options = array( 'include_images', 'featured_images', 'content_images', 'max_images_per_sitemap' );

		if ( ! in_array( $option_name, $valid_options, true ) ) {
			WP_CLI::error(
				sprintf(
					/* translators: 1: Unknown option name, 2: Comma-separated list of valid options */
					__( 'Unknown option: %1$s. Valid options: %2$s', 'msm-sitemap' ),
					$option_name,
					implode( ', ', $valid_options )
				)
			);
		}

		$result = $this->service->delete_setting( $option_name );

		if ( $result['success'] ) {
			WP_CLI::success( $result['message'] );
		} else {
			WP_CLI::error( $result['message'] );
		}
	}

	/**
	 * Reset all options to defaults.
	 */
	private function reset_options(): void {
		$result = $this->service->reset_to_defaults();

		if ( $result['success'] ) {
			WP_CLI::success( '✅ ' . $result['message'] );
		} else {
			WP_CLI::error( '❌ ' . $result['message'] );
		}
	}

	/**
	 * Parse option value from string.
	 *
	 * @param mixed $value Value to parse.
	 * @return mixed Parsed value.
	 */
	private function parse_option_value( $value ) {
		// Handle boolean values
		if ( in_array( $value, array( 'true', 'false', '1', '0', 'yes', 'no', 'on', 'off' ), true ) ) {
			return $this->parse_boolean_value( $value );
		}

		// Handle numeric values
		if ( is_numeric( $value ) ) {
			return (int) $value;
		}

		// Return as string
		return $value;
	}

	/**
	 * Parse boolean value from string.
	 *
	 * @param mixed $value Value to parse.
	 * @return bool Parsed boolean value.
	 */
	private function parse_boolean_value( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		$string_value = (string) $value;
		return in_array( $string_value, array( '1', 'true', 'yes', 'on' ), true );
	}
}
