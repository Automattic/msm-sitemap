<?php
/**
 * Base test case for unit tests.
 *
 * @package Automattic\MSM_Sitemap\Tests\Unit
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Unit;

use Yoast\WPTestUtils\BrainMonkey\TestCase as BrainMonkeyTestCase;
use Brain\Monkey\Functions;

/**
 * Base test case for unit tests.
 */
abstract class TestCase extends BrainMonkeyTestCase {

	/**
	 * Set up test fixtures.
	 *
	 * Stubs WordPress translation functions for use in unit tests.
	 */
	protected function setUp(): void {
		parent::setUp();

		// Stub WordPress translation functions to return the input string.
		Functions\stubs(
			array(
				'__'          => static fn( string $text, string $domain = 'default' ): string => $text,
				'_n'          => static fn( string $single, string $plural, int $number, string $domain = 'default' ): string => $number === 1 ? $single : $plural,
				'_x'          => static fn( string $text, string $context, string $domain = 'default' ): string => $text,
				'esc_html__'  => static fn( string $text, string $domain = 'default' ): string => $text,
				'esc_attr__'  => static fn( string $text, string $domain = 'default' ): string => $text,
			)
		);

		// Stub echo functions separately (they return void).
		Functions\when( '_e' )->echoArg();
		Functions\when( 'esc_html_e' )->echoArg();
		Functions\when( 'esc_attr_e' )->echoArg();
	}
}
