<?php
/**
 * Shared REST Controller Trait
 *
 * @package Automattic\MSM_Sitemap\Infrastructure\REST\Traits
 */

declare(strict_types=1);

namespace Automattic\MSM_Sitemap\Infrastructure\REST\Traits;

/**
 * Trait providing common REST controller functionality.
 */
trait RestControllerTrait {

	/**
	 * The REST API namespace.
	 *
	 * @var string
	 */
	protected string $namespace = 'msm-sitemap/v1';

	/**
	 * Check if user has manage_options capability.
	 *
	 * @return bool True if user has permission, false otherwise.
	 */
	public function check_manage_options_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Validate date format (YYYY-MM-DD).
	 *
	 * @param string $date The date to validate.
	 * @return bool True if valid, false otherwise.
	 */
	public function validate_date_format( string $date ): bool {
		if ( '' === $date ) {
			return true;
		}
		return (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date );
	}

	/**
	 * Validate date queries array.
	 *
	 * @param array $date_queries The date queries to validate.
	 * @return bool True if valid, false otherwise.
	 */
	public function validate_date_queries( array $date_queries ): bool {
		foreach ( $date_queries as $query ) {
			if ( ! is_array( $query ) ) {
				return false;
			}

			if ( ! isset( $query['year'] ) ) {
				return false;
			}

			if ( ! is_numeric( $query['year'] ) || $query['year'] < 1900 || $query['year'] > 2100 ) {
				return false;
			}

			if ( isset( $query['month'] ) ) {
				if ( ! is_numeric( $query['month'] ) || $query['month'] < 1 || $query['month'] > 12 ) {
					return false;
				}
			}

			if ( isset( $query['day'] ) ) {
				if ( ! is_numeric( $query['day'] ) || $query['day'] < 1 || $query['day'] > 31 ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Clear sitemap-related caches.
	 */
	protected function clear_sitemap_caches(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_msm_sitemap_rest_sitemaps_%'" );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_msm_sitemap_rest_sitemap_%'" );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_msm_sitemap_rest_stats_%'" );
	}
}
