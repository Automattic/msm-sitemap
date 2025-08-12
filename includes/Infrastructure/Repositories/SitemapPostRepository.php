<?php
/**
 * Repository for managing sitemap posts in the WordPress database.
 *
 * @package Automattic\MSM_Sitemap\Infrastructure\Repositories
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Infrastructure\Repositories;

use Automattic\MSM_Sitemap\Domain\Contracts\SitemapRepositoryInterface;

/**
 * Repository for managing sitemap posts in the WordPress database.
 */
class SitemapPostRepository implements SitemapRepositoryInterface {

	/**
	 * Save a sitemap to the WordPress database.
	 *
	 * @param string $date The sitemap date (YYYY-MM-DD format).
	 * @param string $xml_content The XML content to save.
	 * @param int    $url_count The number of URLs in the sitemap.
	 * @return bool True on success, false on failure.
	 */
	public function save( string $date, string $xml_content, int $url_count ): bool {
		global $wpdb;

		$sitemap_data = array(
			'post_name'   => $date,
			'post_title'  => $date,
			'post_type'   => \Automattic\MSM_Sitemap\Plugin::SITEMAP_CPT,
			'post_status' => 'publish',
			'post_date'   => $date,
		);

		// Check if sitemap already exists
		$existing_id = $wpdb->get_var( $wpdb->prepare( 
			"SELECT ID FROM $wpdb->posts WHERE post_type = %s AND post_name = %s LIMIT 1", 
			\Automattic\MSM_Sitemap\Plugin::SITEMAP_CPT, 
			$date 
		) );

		if ( $existing_id ) {
			// Update existing sitemap
			$sitemap_data['ID'] = $existing_id;
			$post_id = wp_update_post( $sitemap_data );
		} else {
			// Create new sitemap
			$post_id = wp_insert_post( $sitemap_data );
		}

		if ( ! $post_id ) {
			return false;
		}

		// Get the old count BEFORE updating post meta
		$old_count = 0;
		if ( $existing_id ) {
			$old_count = get_post_meta( $existing_id, 'msm_indexed_url_count', true ) ?: 0;
		}

		// Save XML content and URL count as post meta
		update_post_meta( $post_id, 'msm_sitemap_xml', $xml_content );
		update_post_meta( $post_id, 'msm_indexed_url_count', $url_count );

		// Update total indexed URL count
		$total_count = get_option( 'msm_sitemap_indexed_url_count', 0 );
		$new_total = $total_count - $old_count + $url_count;
		update_option( 'msm_sitemap_indexed_url_count', $new_total );

		return true;
	}

	/**
	 * Find a sitemap post ID by date.
	 *
	 * @param string $date The sitemap date (YYYY-MM-DD format).
	 * @return int|null The post ID if found, null otherwise.
	 */
	public function find_by_date( string $date ): ?int {
		global $wpdb;

		$post_id = $wpdb->get_var( $wpdb->prepare( 
			"SELECT ID FROM $wpdb->posts WHERE post_type = %s AND post_name = %s LIMIT 1", 
			'msm_sitemap', 
			$date 
		) );

		return $post_id ? (int) $post_id : null;
	}

	/**
	 * Delete a sitemap post by date.
	 *
	 * @param string $date The sitemap date (YYYY-MM-DD format).
	 * @return bool True on success, false on failure.
	 */
	public function delete_by_date( string $date ): bool {
		$post_id = $this->find_by_date( $date );
		
		if ( ! $post_id ) {
			return false;
		}

		// Get the URL count before deletion
		$url_count = get_post_meta( $post_id, 'msm_indexed_url_count', true ) ?: 0;

		// Delete the post
		$result = wp_delete_post( $post_id, true );

		if ( $result ) {
			// Update total indexed URL count
			$total_count = get_option( 'msm_sitemap_indexed_url_count', 0 );
			$new_total = $total_count - $url_count;
			update_option( 'msm_sitemap_indexed_url_count', $new_total );
		}

		return (bool) $result;
	}

	/**
	 * Delete sitemaps for specific date queries.
	 *
	 * @param array $date_queries Array of date queries with year, month, day keys.
	 * @return int Number of sitemaps deleted.
	 */
	public function delete_for_date_queries( array $date_queries ): int {
		global $wpdb;
		
		$deleted_count = 0;
		
		foreach ( $date_queries as $query ) {
			$sitemap_query = new \WP_Query(
				array(
					'post_type'      => \Automattic\MSM_Sitemap\Plugin::SITEMAP_CPT,
					'post_status'    => 'any',
					'fields'         => 'ids',
					'posts_per_page' => -1,
					'date_query'     => array( $query ),
				)
			);
			
			foreach ( $sitemap_query->posts as $post_id ) {
				if ( wp_delete_post( $post_id, true ) ) {
					++$deleted_count;
				}
			}
		}
		
		return $deleted_count;
	}

	/**
	 * Delete all sitemaps.
	 *
	 * @return int Number of sitemaps deleted.
	 */
	public function delete_all(): int {
		global $wpdb;
		
		$sitemap_ids = $wpdb->get_col( $wpdb->prepare( 
			"SELECT ID FROM $wpdb->posts WHERE post_type = %s", 
			\Automattic\MSM_Sitemap\Plugin::SITEMAP_CPT 
		) );
		
		$deleted_count = 0;
		foreach ( $sitemap_ids as $post_id ) {
			if ( wp_delete_post( $post_id, true ) ) {
				++$deleted_count;
			}
		}
		
		return $deleted_count;
	}

	/**
	 * Get all sitemap dates.
	 *
	 * @return array Array of sitemap dates in YYYY-MM-DD format.
	 */
	public function get_all_sitemap_dates(): array {
		global $wpdb;
		
		$dates = $wpdb->get_col( $wpdb->prepare(
			"SELECT post_name FROM $wpdb->posts WHERE post_type = %s AND post_status = 'publish'",
			\Automattic\MSM_Sitemap\Plugin::SITEMAP_CPT
		) );
		
		return array_filter( $dates, function( $date ) {
			return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date );
		} );
	}
}
