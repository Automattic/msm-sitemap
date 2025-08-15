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
	 * The settings service.
	 *
	 * @var SettingsService
	 */
	private SettingsService $settings;

	/**
	 * The cron scheduling service.
	 *
	 * @var CronSchedulingService
	 */
	private CronSchedulingService $cron_scheduler;

	/**
	 * Constructor.
	 *
	 * @param SettingsService $settings The settings service.
	 * @param CronSchedulingService $cron_scheduler The cron scheduling service.
	 */
	public function __construct( SettingsService $settings, CronSchedulingService $cron_scheduler ) {
		$this->settings       = $settings;
		$this->cron_scheduler = $cron_scheduler;
	}

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
	public function get_current_frequency(): string {
		return $this->settings->get_setting( 'cron_frequency', self::DEFAULT_FREQUENCY );
	}

	/**
	 * Update cron frequency
	 *
	 * @param string $frequency New frequency to set.
	 * @return array Result with success status and message.
	 */
	public function update_frequency( string $frequency ): array {
		if ( ! $this->is_valid_frequency( $frequency ) ) {
			return array(
				'success'    => false,
				'message'    => __( 'Invalid frequency specified.', 'msm-sitemap' ),
				'error_code' => 'invalid_frequency',
			);
		}

		// Update the frequency option
		$this->settings->update_setting( 'cron_frequency', $frequency );
		
		// Reschedule the cron job with the new frequency
		$success = $this->cron_scheduler->reschedule_cron( $frequency );
		
		if ( ! $success ) {
			return array(
				'success'    => false,
				'message'    => __( 'Failed to reschedule cron job. Please try again.', 'msm-sitemap' ),
				'error_code' => 'reschedule_failed',
			);
		}

		return array(
			'success'   => true,
			'message'   => __( 'Automatic update frequency successfully changed.', 'msm-sitemap' ),
			'frequency' => $frequency,
		);
	}

	/**
	 * Get comprehensive cron status
	 *
	 * @return array Cron status information
	 */
	public function get_cron_status(): array {
		$status = $this->cron_scheduler->get_cron_status();
		
		return array_merge(
			$status,
			array(
				'current_frequency' => $this->get_current_frequency(),
				'valid_frequencies' => $this->get_valid_frequencies(),
			)
		);
	}

	/**
	 * Enable cron
	 *
	 * @return array Result with success status and message.
	 */
	public function enable_cron(): array {
		$status = $this->cron_scheduler->get_cron_status();
		
		if ( ! $status['blog_public'] ) {
			return array(
				'success'    => false,
				'message'    => __( 'Cannot enable cron: blog is not public.', 'msm-sitemap' ),
				'error_code' => 'blog_not_public',
			);
		}
		
		$result = $this->cron_scheduler->enable_cron();
		if ( $result ) {
			return array(
				'success' => true,
				'message' => __( 'Automatic sitemap updates enabled successfully.', 'msm-sitemap' ),
			);
		} else {
			return array(
				'success'    => false,
				'message'    => __( 'Automatic updates are already enabled.', 'msm-sitemap' ),
				'error_code' => 'already_enabled',
			);
		}
	}

	/**
	 * Disable cron
	 *
	 * @return array Result with success status and message.
	 */
	public function disable_cron(): array {
		$result = $this->cron_scheduler->disable_cron();
		if ( $result ) {
			return array(
				'success' => true,
				'message' => __( 'Automatic sitemap updates disabled successfully.', 'msm-sitemap' ),
			);
		} else {
			return array(
				'success'    => false,
				'message'    => __( 'Automatic updates are already disabled.', 'msm-sitemap' ),
				'error_code' => 'already_disabled',
			);
		}
	}

	/**
	 * Reset cron
	 *
	 * @return array Result with success status and message.
	 */
	public function reset_cron(): array {
		$result = $this->cron_scheduler->reset_cron();
		
		if ( $result ) {
			return array(
				'success' => true,
				'message' => __( 'Sitemap cron reset to clean state.', 'msm-sitemap' ),
			);
		} else {
			return array(
				'success'    => false,
				'message'    => __( 'Failed to reset sitemap cron.', 'msm-sitemap' ),
				'error_code' => 'reset_failed',
			);
		}
	}
}
