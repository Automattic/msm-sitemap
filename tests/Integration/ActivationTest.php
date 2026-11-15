<?php
/**
 * Tests for plugin activation and deactivation.
 *
 * @package Automattic\MSM_Sitemap\Tests\Integration
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Integration;

/**
 * Activating or deactivating the plugin must clear the stored rewrite rules,
 * so /sitemap.xml works straight away without visiting Settings > Permalinks.
 */
final class ActivationTest extends TestCase {

	/**
	 * Data provider for the activation and deactivation hooks.
	 *
	 * @return iterable<string, array{string}>
	 */
	public function hook_data_provider(): iterable {
		yield 'activation' => array( 'activate_' );
		yield 'deactivation' => array( 'deactivate_' );
	}

	/**
	 * The stored rewrite rules are cleared, so WordPress rebuilds them on the next request.
	 *
	 * @dataProvider hook_data_provider
	 *
	 * @param string $prefix The hook prefix.
	 */
	public function test_rewrite_rules_are_cleared( string $prefix ): void {
		update_option( 'rewrite_rules', array( '^stale$' => 'index.php' ) );

		do_action( $prefix . plugin_basename( dirname( __DIR__, 2 ) . '/msm-sitemap.php' ) );

		$this->assertEmpty( get_option( 'rewrite_rules' ) );
	}
}
