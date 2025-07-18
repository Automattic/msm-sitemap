<?php
/**
 * Mock WP-CLI classes for testing.
 *
 * @package Automattic\MSM_Sitemap\Tests\Includes
 */

declare( strict_types=1 );

namespace WP_CLI {
	// Define WP_CLI\ExitException if not already defined
	if ( ! class_exists( 'WP_CLI\ExitException' ) ) {
		eval( 'namespace WP_CLI; class ExitException extends \Exception {}' );
	}
}

namespace {
	if ( false === class_exists( 'WP_CLI' ) ) {
		class WP_CLI {
			public function __call( $method, $params ) {
				if ( $method === 'error' ) {
					throw new \WP_CLI\ExitException( 'WP_CLI error' );
				}
				if ( in_array( $method, array( 'log', 'success', 'warning' ) ) ) {
					echo $params[0] . "\n";
					return;
				}
				return;
			}

			public static function __callStatic( $method, $params ) {
				if ( $method === 'error' ) {
					throw new \WP_CLI\ExitException( 'WP_CLI error' );
				}
				if ( in_array( $method, array( 'log', 'success', 'warning' ) ) ) {
					echo $params[0] . "\n";
					return;
				}
				return;
			}

			public static function confirm( $question, $assoc_args = array() ) {
				if ( ! empty( $assoc_args['yes'] ) ) {
					// Proceed as confirmed
					return;
				}
				// Output the confirmation prompt and simulate user not confirming
				echo $question . PHP_EOL;
				throw new WP_CLI_ConfirmationException( 'Confirmation required: ' . $question );
			}
		}
	}

	if ( false === class_exists( 'WP_CLI_Command' ) ) {
		class WP_CLI_Command {
			public function __call( $method, $params ) {
				return;
			}

			public static function __callStatic( $method, $params ) {
				return;
			}
		}
	}

	class WP_CLI_ConfirmationException extends \Exception {}
}

namespace WP_CLI\Utils {
	/**
	 * Stub for format_items used in WP-CLI commands during tests.
	 * Returns a simple string for test assertions.
	 */
	function format_items( $format, $items, $fields ) {
		if ( $format === 'csv' ) {
			$out = implode( ',', $fields ) . "\n";
			foreach ( $items as $item ) {
				$row = array();
				foreach ( $fields as $field ) {
					$row[] = $item[ $field ] ?? '';
				}
				$out .= implode( ',', $row ) . "\n";
			}
			echo $out;
			return;
		}
		if ( $format === 'json' ) {
			echo json_encode( $items );
			return;
		}
		// Default: table-like output
		$out = implode( "\t", $fields ) . "\n";
		foreach ( $items as $item ) {
			$row = array();
			foreach ( $fields as $field ) {
				$row[] = $item[ $field ] ?? '';
			}
			$out .= implode( "\t", $row ) . "\n";
		}
		echo $out;
	}
}

