<?php
/**
 * DTO for sitemap validation operation results.
 *
 * @package Automattic\MSM_Sitemap\Application\DTOs
 */

namespace Automattic\MSM_Sitemap\Application\DTOs;

/**
 * DTO for sitemap validation operation results.
 */
final class SitemapValidationResult implements SitemapOperationResultInterface {
	/**
	 * Whether the operation was successful.
	 *
	 * @var bool
	 */
	private bool $success;

	/**
	 * The number of sitemaps affected.
	 *
	 * @var int
	 */
	private int $count;

	/**
	 * The message describing the result.
	 *
	 * @var string
	 */
	private string $message;

	/**
	 * The error code if the operation failed.
	 *
	 * @var string|null
	 */
	private ?string $error_code;

	/**
	 * Validation errors.
	 *
	 * @var array
	 */
	private array $validation_errors;

	/**
	 * Number of valid sitemaps.
	 *
	 * @var int
	 */
	private int $valid_count;

	/**
	 * Number of invalid sitemaps.
	 *
	 * @var int
	 */
	private int $invalid_count;

	/**
	 * Constructor.
	 *
	 * @param bool $success Whether the operation was successful.
	 * @param int $count The number of sitemaps affected.
	 * @param string $message The message describing the result.
	 * @param string|null $error_code The error code if the operation failed.
	 * @param array $validation_errors Validation errors.
	 * @param int $valid_count Number of valid sitemaps.
	 * @param int $invalid_count Number of invalid sitemaps.
	 */
	private function __construct(
		bool $success,
		int $count,
		string $message,
		?string $error_code,
		array $validation_errors = array(),
		int $valid_count = 0,
		int $invalid_count = 0
	) {
		$this->success           = $success;
		$this->count             = $count;
		$this->message           = $message;
		$this->error_code        = $error_code;
		$this->validation_errors = $validation_errors;
		$this->valid_count       = $valid_count;
		$this->invalid_count     = $invalid_count;
	}

	/**
	 * Create a successful validation result.
	 *
	 * @param int $count The number of sitemaps affected.
	 * @param string $message The message describing the result.
	 * @param array $validation_errors Validation errors.
	 * @param int $valid_count Number of valid sitemaps.
	 * @param int $invalid_count Number of invalid sitemaps.
	 * @return self
	 */
	public static function success(
		int $count,
		string $message,
		array $validation_errors = array(),
		int $valid_count = 0,
		int $invalid_count = 0
	): self {
		return new self( true, $count, $message, null, $validation_errors, $valid_count, $invalid_count );
	}

	/**
	 * Create a failure validation result.
	 *
	 * @param string $message The error message.
	 * @param string $error_code The error code.
	 * @return self
	 */
	public static function failure( string $message, string $error_code ): self {
		return new self( false, 0, $message, $error_code );
	}

	/**
	 * Check if the operation was successful.
	 *
	 * @return bool
	 */
	public function is_success(): bool {
		return $this->success;
	}

	/**
	 * Get the number of sitemaps affected.
	 *
	 * @return int
	 */
	public function get_count(): int {
		return $this->count;
	}

	/**
	 * Get the message describing the result.
	 *
	 * @return string
	 */
	public function get_message(): string {
		return $this->message;
	}

	/**
	 * Get the error code if the operation failed.
	 *
	 * @return string|null
	 */
	public function get_error_code(): ?string {
		return $this->error_code;
	}

	/**
	 * Get validation errors.
	 *
	 * @return array
	 */
	public function get_validation_errors(): array {
		return $this->validation_errors;
	}

	/**
	 * Get number of valid sitemaps.
	 *
	 * @return int
	 */
	public function get_valid_count(): int {
		return $this->valid_count;
	}

	/**
	 * Get number of invalid sitemaps.
	 *
	 * @return int
	 */
	public function get_invalid_count(): int {
		return $this->invalid_count;
	}

	/**
	 * Convert to array for CLI output.
	 *
	 * @return array
	 */
}
