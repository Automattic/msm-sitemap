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
			// Image settings
			'include_images'         => '1',
			'featured_images'        => '1',
			'content_images'         => '1',
			'max_images_per_sitemap' => self::DEFAULT_MAX_IMAGES_PER_SITEMAP,
			'cron_frequency'         => CronManagementService::DEFAULT_FREQUENCY,

			// Taxonomy settings
			'include_taxonomies'     => '0',
			'enabled_taxonomies'     => array( 'category', 'post_tag' ),

			// Author settings
			'include_authors'        => '0',
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
		$boolean_settings = array( 'include_images', 'featured_images', 'content_images', 'include_taxonomies', 'include_authors' );
		foreach ( $boolean_settings as $setting ) {
			if ( isset( $settings[ $setting ] ) ) {
				$sanitized_settings[ $setting ] = $settings[ $setting ] ? '1' : '0';
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
}
