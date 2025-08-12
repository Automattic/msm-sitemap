<?php
/**
 * Plugin links management
 *
 * @package MSM_Sitemap
 */

namespace Automattic\MSM_Sitemap\Infrastructure\WordPress;

/**
 * Handles plugin action links in the WordPress admin
 */
class PluginLinks {

	/**
	 * Initialize plugin links functionality
	 */
	public static function setup(): void {
		// Add Settings link to plugins page
		add_filter( 'plugin_action_links_msm-sitemap/msm-sitemap.php', array( __CLASS__, 'add_plugin_action_links' ) );
		add_filter( 'network_admin_plugin_action_links_msm-sitemap/msm-sitemap.php', array( __CLASS__, 'add_plugin_action_links' ) );
	}

	/**
	 * Add Settings link to the plugin action links
	 *
	 * @param array $actions Plugin action links.
	 * @return array Modified plugin action links.
	 */
	public static function add_plugin_action_links( array $actions ): array {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'options-general.php?page=msm-sitemap' ) ),
			esc_html__( 'Settings', 'msm-sitemap' )
		);
		
		// Insert Settings link before Deactivate
		$deactivate_key = array_search( 'deactivate', array_keys( $actions ), true );
		if ( false !== $deactivate_key ) {
			$actions = array_merge(
				array_slice( $actions, 0, $deactivate_key ),
				array( 'settings' => $settings_link ),
				array_slice( $actions, $deactivate_key )
			);
		} else {
			// If Deactivate link not found, just add Settings at the end
			$actions['settings'] = $settings_link;
		}
		
		return $actions;
	}
}
