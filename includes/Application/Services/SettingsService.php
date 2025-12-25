<?php
/**
 * Settings Service
 *
 * @package Automattic\MSM_Sitemap\Application\Services
 */

declare(strict_types=1);

namespace Automattic\MSM_Sitemap\Application\Services;

use Automattic\MSM_Sitemap\Application\Services\CronManagementService;

/**
 * Service for managing all sitemap settings with centralized business logic
 */
class SettingsService {

	/**
	 * Default maximum images per sitemap
	 */
	const DEFAULT_MAX_IMAGES_PER_SITEMAP = 1000;

	/**
	 * Minimum allowed images per sitemap
	 */
	const MIN_IMAGES_PER_SITEMAP = 1;

	/**
	 * Maximum allowed images per sitemap
	 */
	const MAX_IMAGES_PER_SITEMAP = 10000;

	/**
	 * Default cache TTL in minutes (1 hour)
	 */
	const DEFAULT_CACHE_TTL_MINUTES = 60;

	/**
	 * Minimum cache TTL in minutes (5 minutes)
	 */
	const MIN_CACHE_TTL_MINUTES = 5;

	/**
	 * Maximum cache TTL in minutes (24 hours)
	 */
	const MAX_CACHE_TTL_MINUTES = 1440;

	/**
	 * Get all current settings
	 *
	 * @return array Current settings
	 */
	public function get_all_settings(): array {
		$settings = get_option( 'msm_sitemap', array() );
		
		return array_merge( $this->get_default_settings(), $settings );
	}

	/**
	 * Get a specific setting value
	 *
	 * @param string $key Setting key.
	 * @param mixed  $default_value Default value if setting doesn't exist.
	 * @return mixed Setting value.
	 */
	public function get_setting( string $key, $default_value = null ) {
		$settings = get_option( 'msm_sitemap', array() );
		
		if ( isset( $settings[ $key ] ) ) {
			return $settings[ $key ];
		}
		
		// If no default provided, try to get from default settings
		if ( null === $default_value ) {
			$default_settings = $this->get_default_settings();
			return $default_settings[ $key ] ?? null;
		}
		
		return $default_value;
	}

	/**
	 * Update specific settings
	 *
	 * @param array $settings Array of settings to update.
	 * @return array Result with success status and message.
	 */
	public function update_settings( array $settings ): array {
		// Validate and sanitize settings
		$validated_settings = $this->validate_and_sanitize_settings( $settings );
		
		if ( ! $validated_settings['valid'] ) {
			return array(
				'success'    => false,
				'message'    => $validated_settings['message'],
				'error_code' => 'validation_failed',
			);
		}

		// Get current settings
		$current_settings = get_option( 'msm_sitemap', array() );

		// Merge with new settings
		$updated_settings = array_merge( $current_settings, $validated_settings['settings'] );

		// Check if anything actually changed
		if ( $current_settings === $updated_settings ) {
			return array(
				'success'  => true,
				'message'  => __( 'Settings saved successfully.', 'msm-sitemap' ),
				'settings' => $updated_settings,
			);
		}

		// Save to database
		$updated = update_option( 'msm_sitemap', $updated_settings );

		if ( ! $updated ) {
			return array(
				'success'    => false,
				'message'    => __( 'Failed to save settings.', 'msm-sitemap' ),
				'error_code' => 'save_failed',
			);
		}

		return array(
			'success'  => true,
			'message'  => __( 'Settings saved successfully.', 'msm-sitemap' ),
			'settings' => $updated_settings,
		);
	}

	/**
	 * Update a single setting
	 *
	 * @param string $key Setting key.
	 * @param mixed  $value Setting value.
	 * @return array Result with success status and message.
	 */
	public function update_setting( string $key, $value ): array {
		return $this->update_settings( array( $key => $value ) );
	}

	/**
	 * Delete a setting
	 *
	 * @param string $key Setting key to delete.
	 * @return array Result with success status and message.
	 */
	public function delete_setting( string $key ): array {
		$current_settings = get_option( 'msm_sitemap', array() );
		
		if ( ! isset( $current_settings[ $key ] ) ) {
			return array(
				'success'    => false,
				'message'    => sprintf(
					/* translators: %s: Setting key */
					__( 'Setting %s not found.', 'msm-sitemap' ),
					$key
				),
				'error_code' => 'setting_not_found',
			);
		}
		
		unset( $current_settings[ $key ] );
		$updated = update_option( 'msm_sitemap', $current_settings );
		
		if ( ! $updated ) {
			return array(
				'success'    => false,
				'message'    => __( 'Failed to delete setting.', 'msm-sitemap' ),
				'error_code' => 'delete_failed',
			);
		}
		
		return array(
			'success' => true,
			'message' => sprintf(
				/* translators: %s: Setting key */
				__( 'Setting %s deleted successfully.', 'msm-sitemap' ),
				$key
			),
		);
	}

	/**
	 * Get default settings
	 *
	 * @return array Default settings
	 */
	public function get_default_settings(): array {
		return array(
			// Post type settings
			'enabled_post_types'     => array( 'post' ),

			// Image settings
			'include_images'         => '1',
			'featured_images'        => '1',
			'content_images'         => '1',
			'max_images_per_sitemap' => self::DEFAULT_MAX_IMAGES_PER_SITEMAP,
			'cron_frequency'         => CronManagementService::DEFAULT_FREQUENCY,

			// Taxonomy settings
			'include_taxonomies'     => '0',
			'enabled_taxonomies'     => array( 'category', 'post_tag' ),
			'taxonomy_cache_ttl'     => self::DEFAULT_CACHE_TTL_MINUTES,

			// Author settings
			'include_authors'        => '0',
			'author_cache_ttl'       => self::DEFAULT_CACHE_TTL_MINUTES,

			// Page settings
			'include_pages'          => '0',
			'enabled_page_types'     => array( 'page' ),
			'page_cache_ttl'         => self::DEFAULT_CACHE_TTL_MINUTES,
		);
	}

	/**
	 * Reset all settings to defaults
	 *
	 * @return array Result with success status and message.
	 */
	public function reset_to_defaults(): array {
		$default_settings = $this->get_default_settings();
		$updated          = update_option( 'msm_sitemap', $default_settings );
		
		if ( ! $updated ) {
			return array(
				'success'    => false,
				'message'    => __( 'Failed to reset settings.', 'msm-sitemap' ),
				'error_code' => 'reset_failed',
			);
		}
		
		return array(
			'success'  => true,
			'message'  => __( 'Settings reset to defaults successfully.', 'msm-sitemap' ),
			'settings' => $default_settings,
		);
	}

	/**
	 * Validate and sanitize settings
	 *
	 * @param array $settings Raw settings to validate.
	 * @return array Validation result with valid flag, message, and sanitized settings.
	 */
	private function validate_and_sanitize_settings( array $settings ): array {
		$sanitized_settings = array();
		$errors             = array();

		// Handle boolean settings
		$boolean_settings = array( 'include_images', 'featured_images', 'content_images', 'include_taxonomies', 'include_authors', 'include_pages' );
		foreach ( $boolean_settings as $setting ) {
			if ( isset( $settings[ $setting ] ) ) {
				$sanitized_settings[ $setting ] = $settings[ $setting ] ? '1' : '0';
			}
		}

		// Handle enabled_post_types array
		if ( isset( $settings['enabled_post_types'] ) ) {
			if ( is_array( $settings['enabled_post_types'] ) ) {
				$sanitized_settings['enabled_post_types'] = array_map( 'sanitize_text_field', $settings['enabled_post_types'] );
			} else {
				$sanitized_settings['enabled_post_types'] = array();
			}
		}

		// Handle enabled_taxonomies array
		if ( isset( $settings['enabled_taxonomies'] ) ) {
			if ( is_array( $settings['enabled_taxonomies'] ) ) {
				$sanitized_settings['enabled_taxonomies'] = array_map( 'sanitize_text_field', $settings['enabled_taxonomies'] );
			} else {
				$sanitized_settings['enabled_taxonomies'] = array();
			}
		}

		// Handle enabled_page_types array
		if ( isset( $settings['enabled_page_types'] ) ) {
			if ( is_array( $settings['enabled_page_types'] ) ) {
				$sanitized_settings['enabled_page_types'] = array_map( 'sanitize_text_field', $settings['enabled_page_types'] );
			} else {
				$sanitized_settings['enabled_page_types'] = array();
			}
		}

		// Handle max images per sitemap
		if ( isset( $settings['max_images_per_sitemap'] ) ) {
			$max_images = intval( $settings['max_images_per_sitemap'] );
			
			if ( $max_images < self::MIN_IMAGES_PER_SITEMAP || $max_images > self::MAX_IMAGES_PER_SITEMAP ) {
				$errors[] = sprintf(
					/* translators: 1: Minimum value, 2: Maximum value */
					__( 'Maximum images per sitemap must be between %1$d and %2$d.', 'msm-sitemap' ),
					self::MIN_IMAGES_PER_SITEMAP,
					self::MAX_IMAGES_PER_SITEMAP
				);
			} else {
				$sanitized_settings['max_images_per_sitemap'] = $max_images;
			}
		}

		// Handle cron frequency
		if ( isset( $settings['cron_frequency'] ) ) {
			$frequency = sanitize_text_field( $settings['cron_frequency'] );

			if ( ! CronManagementService::is_valid_frequency( $frequency ) ) {
				$valid_frequencies = CronManagementService::get_valid_frequencies();
				$errors[]          = sprintf(
					/* translators: %s: Comma-separated list of valid frequencies */
					__( 'Invalid cron frequency. Valid frequencies are: %s.', 'msm-sitemap' ),
					implode( ', ', $valid_frequencies )
				);
			} else {
				$sanitized_settings['cron_frequency'] = $frequency;
			}
		}

		// Handle cache TTL settings
		$cache_ttl_settings = array( 'taxonomy_cache_ttl', 'author_cache_ttl', 'page_cache_ttl' );
		foreach ( $cache_ttl_settings as $ttl_setting ) {
			if ( isset( $settings[ $ttl_setting ] ) ) {
				$ttl_value = intval( $settings[ $ttl_setting ] );

				if ( $ttl_value < self::MIN_CACHE_TTL_MINUTES || $ttl_value > self::MAX_CACHE_TTL_MINUTES ) {
					$errors[] = sprintf(
						/* translators: 1: Setting name, 2: Minimum value, 3: Maximum value */
						__( '%1$s must be between %2$d and %3$d minutes.', 'msm-sitemap' ),
						ucfirst( str_replace( '_', ' ', $ttl_setting ) ),
						self::MIN_CACHE_TTL_MINUTES,
						self::MAX_CACHE_TTL_MINUTES
					);
				} else {
					$sanitized_settings[ $ttl_setting ] = $ttl_value;
				}
			}
		}

		// If there are validation errors, return them
		if ( ! empty( $errors ) ) {
			return array(
				'valid'   => false,
				'message' => implode( ' ', $errors ),
			);
		}

		return array(
			'valid'    => true,
			'settings' => $sanitized_settings,
		);
	}

	/**
	 * Get image-specific settings (for backward compatibility)
	 *
	 * @return array Image settings
	 */
	public function get_image_settings(): array {
		$all_settings = $this->get_all_settings();

		return array(
			'include_images'         => $all_settings['include_images'],
			'featured_images'        => $all_settings['featured_images'],
			'content_images'         => $all_settings['content_images'],
			'max_images_per_sitemap' => $all_settings['max_images_per_sitemap'],
		);
	}

	/**
	 * Get a hash of content-affecting settings.
	 *
	 * This hash is used to detect when settings have changed and sitemaps
	 * need to be regenerated.
	 *
	 * @return string MD5 hash of content settings.
	 */
	public function get_content_settings_hash(): string {
		$all_settings = $this->get_all_settings();

		// Settings that affect sitemap content
		// Note: max_images_per_sitemap is excluded as it's dev-filterable
		$content_settings = array(
			'enabled_post_types' => $all_settings['enabled_post_types'] ?? array(),
			'enabled_page_types' => $all_settings['enabled_page_types'] ?? array(),
			'enabled_taxonomies' => $all_settings['enabled_taxonomies'] ?? array(),
			'include_images'     => $all_settings['include_images'] ?? '0',
			'featured_images'    => $all_settings['featured_images'] ?? '0',
			'content_images'     => $all_settings['content_images'] ?? '0',
			'include_authors'    => $all_settings['include_authors'] ?? '0',
		);

		// Sort arrays for consistent hashing
		if ( is_array( $content_settings['enabled_post_types'] ) ) {
			sort( $content_settings['enabled_post_types'] );
		}
		if ( is_array( $content_settings['enabled_page_types'] ) ) {
			sort( $content_settings['enabled_page_types'] );
		}
		if ( is_array( $content_settings['enabled_taxonomies'] ) ) {
			sort( $content_settings['enabled_taxonomies'] );
		}

		return md5( wp_json_encode( $content_settings ) );
	}

	/**
	 * Save the current content settings hash.
	 *
	 * Called after sitemap regeneration to track the settings used.
	 *
	 * @return void
	 */
	public function save_content_settings_hash(): void {
		update_option( 'msm_sitemap_content_settings_hash', $this->get_content_settings_hash(), false );
	}

	/**
	 * Check if content settings have changed since the last sitemap update.
	 *
	 * @return bool True if settings have changed and sitemaps need regeneration.
	 */
	public function has_content_settings_changed(): bool {
		$stored_hash  = get_option( 'msm_sitemap_content_settings_hash', '' );
		$current_hash = $this->get_content_settings_hash();

		// If no stored hash, save current hash for future change detection
		// This handles upgrades from before the hash feature was added
		if ( empty( $stored_hash ) ) {
			$this->save_content_settings_hash();
			return false;
		}

		return $stored_hash !== $current_hash;
	}

	/**
	 * Get a human-readable summary of what content settings have changed.
	 *
	 * @return string Description of what has changed.
	 */
	public function get_settings_change_summary(): string {
		if ( ! $this->has_content_settings_changed() ) {
			return '';
		}

		return __( 'Content settings have changed. Sitemaps need to be regenerated to reflect the new configuration.', 'msm-sitemap' );
	}

	/**
	 * Check if any content types are enabled for sitemap generation.
	 *
	 * This checks post types, page types, taxonomies, and authors.
	 *
	 * @return bool True if at least one content type is enabled.
	 */
	public function has_any_content_enabled(): bool {
		$all_settings = $this->get_all_settings();

		// Check post types (non-hierarchical content like posts)
		$post_types = $all_settings['enabled_post_types'] ?? array();
		if ( ! empty( $post_types ) ) {
			return true;
		}

		// Check page types (hierarchical content like pages)
		$page_types    = $all_settings['enabled_page_types'] ?? array();
		$include_pages = $all_settings['include_pages'] ?? '0';
		if ( ! empty( $page_types ) && '1' === $include_pages ) {
			return true;
		}

		// Check taxonomies
		$taxonomies         = $all_settings['enabled_taxonomies'] ?? array();
		$include_taxonomies = $all_settings['include_taxonomies'] ?? '0';
		if ( ! empty( $taxonomies ) && '1' === $include_taxonomies ) {
			return true;
		}

		// Check authors
		$include_authors = $all_settings['include_authors'] ?? '0';
		if ( '1' === $include_authors ) {
			return true;
		}

		return false;
	}
}
