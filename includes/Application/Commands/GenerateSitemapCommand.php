<?php
/**
 * Generate Sitemap Command
 *
 * @package Automattic\MSM_Sitemap\Application\Commands
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Application\Commands;

/**
 * Command for generating sitemaps.
 *
 * Encapsulates the parameters and validation logic for sitemap generation.
 */
class GenerateSitemapCommand {
	/**
	 * The date to generate sitemaps for (YYYY-MM-DD, YYYY-MM, or YYYY).
	 *
	 * @var string|null
	 */
	private ?string $date;

	/**
	 * Array of date queries for bulk generation.
	 *
	 * @var array
	 */
	private array $date_queries;

	/**
	 * Whether to force regeneration even if sitemap exists.
	 *
	 * @var bool
	 */
	private bool $force;

	/**
	 * Whether to generate for all years.
	 *
	 * @var bool
	 */
	private bool $all;

	/**
	 * Constructor.
	 *
	 * @param string|null $date         The date to generate for.
	 * @param array       $date_queries Array of date queries.
	 * @param bool        $force        Whether to force regeneration.
	 * @param bool        $all          Whether to generate for all years.
	 */
	public function __construct(
		?string $date = null,
		array $date_queries = array(),
		bool $force = false,
		bool $all = false
	) {
		$this->date         = $date;
		$this->date_queries = $date_queries;
		$this->force        = $force;
		$this->all          = $all;
	}

	/**
	 * Validate the command parameters.
	 *
	 * @return bool True if valid, false otherwise.
	 */
	public function validate(): bool {
		// Must have either date, date_queries, or all flag
		if ( empty( $this->date ) && empty( $this->date_queries ) && ! $this->all ) {
			return false;
		}

		// If date is provided, validate its format
		if ( ! empty( $this->date ) && ! $this->is_valid_date_format( $this->date ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Get error message for validation failures.
	 *
	 * @return string The error message.
	 */
	public function get_error_message(): string {
		if ( empty( $this->date ) && empty( $this->date_queries ) && ! $this->all ) {
			return __( 'You must specify either a date, date_queries, or --all to generate sitemaps.', 'msm-sitemap' );
		}

		return __( 'Invalid date format. Use YYYY, YYYY-MM, or YYYY-MM-DD.', 'msm-sitemap' );
	}

	/**
	 * Check if date format is valid.
	 *
	 * @param string $date The date to validate.
	 * @return bool True if valid, false otherwise.
	 */
	private function is_valid_date_format( string $date ): bool {
		// Allow YYYY, YYYY-MM, or YYYY-MM-DD formats
		$patterns = array(
			'/^\d{4}$/',           // YYYY
			'/^\d{4}-\d{2}$/',     // YYYY-MM
			'/^\d{4}-\d{2}-\d{2}$/', // YYYY-MM-DD
		);

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $date ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get the date.
	 *
	 * @return string|null The date.
	 */
	public function get_date(): ?string {
		return $this->date;
	}

	/**
	 * Get the date queries.
	 *
	 * @return array The date queries.
	 */
	public function get_date_queries(): array {
		return $this->date_queries;
	}

	/**
	 * Check if force regeneration is enabled.
	 *
	 * @return bool True if force is enabled.
	 */
	public function is_force(): bool {
		return $this->force;
	}

	/**
	 * Check if generating for all years.
	 *
	 * @return bool True if generating for all years.
	 */
	public function is_all(): bool {
		return $this->all;
	}
}
