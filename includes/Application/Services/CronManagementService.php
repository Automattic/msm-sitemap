<?php
/**
 * Cron Management Service
 *
 * @package Automattic\MSM_Sitemap\Application\Services
 */

declare(strict_types=1);

namespace Automattic\MSM_Sitemap\Application\Services;

use Automattic\MSM_Sitemap\Infrastructure\Cron\CronSchedulingService;

/**
 * Service for managing cron functionality with centralized business logic
 */
class CronManagementService {

	/**
	 * Valid cron frequencies
	 */
	const VALID_FREQUENCIES = array(
		'5min',
		'10min', 
		'15min',
		'30min',
		'hourly',
		'2hourly',
		'3hourly',
	);

	/**
	 * Default cron frequency
	 */
	const DEFAULT_FREQUENCY = '15min';

	/**
	 * Get all valid cron frequencies
	 *
	 * @return array Array of valid frequency strings
	 */
	public static function get_valid_frequencies(): array {
		return self::VALID_FREQUENCIES;
	}

	/**
	 * Check if a frequency is valid
	 *
	 * @param string $frequency The frequency to validate.
	 * @return bool True if valid, false otherwise.
	 */
	public static function is_valid_frequency( string $frequency ): bool {
		return in_array( $frequency, self::VALID_FREQUENCIES, true );
	}

	/**
	 * Get current cron frequency
	 *
	 * @return string Current frequency setting
	 */
	public static function get_current_frequency(): string {
		$container = \Automattic\MSM_Sitemap\Infrastructure\DI\msm_sitemap_container();
		$settings_service = $container->get( \Automattic\MSM_Sitemap\Application\Services\SettingsService::class );
		return $settings_service->get_setting( 'cron_frequency', self::DEFAULT_FREQUENCY );
	}

	/**
	 * Update cron frequency
	 *
	 * @param string $frequency New frequency to set.
	 * @return array Result with success status and message.
	 */
	public static function update_frequency( string $frequency ): array {
		if ( ! self::is_valid_frequency( $frequency ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid frequency specified.', 'msm-sitemap' ),
				'error_code' => 'invalid_frequency',
			);
		}

		// Update the frequency option
		$container = \Automattic\MSM_Sitemap\Infrastructure\DI\msm_sitemap_container();
		$settings_service = $container->get( \Automattic\MSM_Sitemap\Application\Services\SettingsService::class );
		$settings_service->update_setting( 'cron_frequency', $frequency );
		
		// Reschedule the cron job with the new frequency
		$success = CronSchedulingService::reschedule_cron( $frequency );
		
		if ( ! $success ) {
			return array(
				'success' => false,
				'message' => __( 'Failed to reschedule cron job. Please try again.', 'msm-sitemap' ),
				'error_code' => 'reschedule_failed',
			);
		}

		return array(
			'success' => true,
			'message' => __( 'Automatic update frequency successfully changed.', 'msm-sitemap' ),
			'frequency' => $frequency,
		);
	}

	/**
	 * Get comprehensive cron status
	 *
	 * @return array Cron status information
	 */
	public static function get_cron_status(): array {
		$status = CronSchedulingService::get_cron_status();
		
		return array_merge(
			$status,
			array(
				'current_frequency' => self::get_current_frequency(),
				'valid_frequencies' => self::get_valid_frequencies(),
			)
		);
	}

	/**
	 * Enable cron
	 *
	 * @return array Result with success status and message.
	 */
	public static function enable_cron(): array {
		$status = CronSchedulingService::get_cron_status();
		
		if ( ! $status['blog_public'] ) {
			return array(
				'success' => false,
				'message' => __( 'Cannot enable cron: blog is not public.', 'msm-sitemap' ),
				'error_code' => 'blog_not_public',
			);
		}
		
		$result = CronSchedulingService::enable_cron();
		if ( $result ) {
			return array(
				'success' => true,
				'message' => __( 'Automatic sitemap updates enabled successfully.', 'msm-sitemap' ),
			);
		} else {
			return array(
				'success' => false,
				'message' => __( 'Automatic updates are already enabled.', 'msm-sitemap' ),
				'error_code' => 'already_enabled',
			);
		}
	}

	/**
	 * Disable cron
	 *
	 * @return array Result with success status and message.
	 */
	public static function disable_cron(): array {
		$result = CronSchedulingService::disable_cron();
		if ( $result ) {
			return array(
				'success' => true,
				'message' => __( 'Automatic sitemap updates disabled successfully.', 'msm-sitemap' ),
			);
		} else {
			return array(
				'success' => false,
				'message' => __( 'Automatic updates are already disabled.', 'msm-sitemap' ),
				'error_code' => 'already_disabled',
			);
		}
	}

	/**
	 * Reset cron
	 *
	 * @return array Result with success status and message.
	 */
	public static function reset_cron(): array {
		$result = CronSchedulingService::reset_cron();
		
		if ( $result ) {
			return array(
				'success' => true,
				'message' => __( 'Sitemap cron reset to clean state.', 'msm-sitemap' ),
			);
		} else {
			return array(
				'success' => false,
				'message' => __( 'Failed to reset sitemap cron.', 'msm-sitemap' ),
				'error_code' => 'reset_failed',
			);
		}
	}
}
