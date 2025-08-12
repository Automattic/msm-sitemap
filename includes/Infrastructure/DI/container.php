<?php
/**
 * Container helper functions
 *
 * @package Automattic\MSM_Sitemap\Infrastructure\DI
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Infrastructure\DI;

/**
 * Get the sitemap container instance.
 *
 * @return SitemapContainer The container instance.
 */
function msm_sitemap_container(): SitemapContainer {
	static $container = null;
	
	if ( null === $container ) {
		$container = new SitemapContainer();
	}
	
	return $container;
}
