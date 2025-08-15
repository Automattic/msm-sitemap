<?php
/**
 * MSM Sitemap - joint collaboration between Metro.co.uk, MAKE, Alley Interactive, and WordPress VIP.
 *
 * @package           automattic/msm-sitemap
 * @author            Automattic
 * @copyright         2015-onwards Artur Synowiec, Paul Kevan, and contributors.
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       MSM Sitemap
 * Plugin URI:        https://github.com/Automattic/msm-sitemap
 * Description:       Smart date-based sitemaps with automatic generation, detailed monitoring, and enterprise-ready architecture.
 * Version:           1.5.2
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            Metro.co.uk, MAKE, Alley Interactive, WordPress VIP.
 * Text Domain:       msm-sitemap
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.txt
 */

// Load and register the PSR-4 autoloader
// This handles autoloading for all classes in the Automattic\MSM_Sitemap namespace
require __DIR__ . '/includes/Autoloader.php';
MSM_Sitemap_Autoloader::register( __DIR__ );

// Load the dependency injection container
require __DIR__ . '/includes/Infrastructure/DI/container.php';

// Initialize plugin
$GLOBALS['msm_sitemap_plugin'] = new \Automattic\MSM_Sitemap\Plugin( __FILE__, '1.5.2' );
add_action( 'after_setup_theme', array( $GLOBALS['msm_sitemap_plugin'], 'run' ) );

/**
 * Get the plugin instance
 *
 * @return \Automattic\MSM_Sitemap\Plugin
 */
function msm_sitemap_plugin(): \Automattic\MSM_Sitemap\Plugin {
	return $GLOBALS['msm_sitemap_plugin'];
}
