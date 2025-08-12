<?php
/**
 * Date utility functions for sitemap operations
 *
 * @package MSM_Sitemap
 */

namespace Automattic\MSM_Sitemap\Domain\Utilities;

/**
 * Utility class for date formatting and manipulation within the sitemap domain
 */
class DateUtility {

	/**
	 * Get properly formatted date stamp from year, month, and day
	 *
	 * @param int $year The year (e.g., 2025)
	 * @param int $month The month (1-12)
	 * @param int $day The day (1-31)
	 * @return string Formatted date stamp in YYYY-MM-DD format
	 */
	public static function format_date_stamp( int $year, int $month, int $day ): string {
		return sprintf( 
			'%s-%s-%s', 
			$year, 
			str_pad( (string) $month, 2, '0', STR_PAD_LEFT ), 
			str_pad( (string) $day, 2, '0', STR_PAD_LEFT ) 
		);
	}

	/**
	 * Validate that a date is valid
	 *
	 * @param int $year The year
	 * @param int $month The month (1-12)
	 * @param int $day The day (1-31)
	 * @return bool True if date is valid, false otherwise
	 */
	public static function is_valid_date( int $year, int $month, int $day ): bool {
		return checkdate( $month, $day, $year );
	}

	/**
	 * Get the number of days in a given month and year
	 *
	 * @param int $year The year
	 * @param int $month The month (1-12)
	 * @return int Number of days in the month
	 */
	public static function get_days_in_month( int $year, int $month ): int {
		// Validate month before calling cal_days_in_month to avoid ValueError in PHP 8.0+
		if ( $month < 1 || $month > 12 ) {
			throw new \InvalidArgumentException( sprintf( 'Invalid month: %d. Month must be between 1 and 12.', esc_html( (string) $month ) ) );
		}
		
		return (int) cal_days_in_month( CAL_GREGORIAN, $month, $year );
	}
}
