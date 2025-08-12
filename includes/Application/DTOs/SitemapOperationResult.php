<?php
/**
 * DTO for sitemap operation results.
 *
 * @package Automattic\MSM_Sitemap\Application\DTOs
 */

namespace Automattic\MSM_Sitemap\Application\DTOs;

/**
 * Base DTO for sitemap operation results.
 */
final class SitemapOperationResult implements SitemapOperationResultInterface {
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
	 * Constructor.
	 *
	 * @param bool $success Whether the operation was successful.
	 * @param int $count The number of sitemaps affected.
	 * @param string $message The message describing the result.
	 * @param string|null $error_code The error code if the operation failed.
	 */
	private function __construct( bool $success, int $count, string $message, ?string $error_code = null ) {
		$this->success = $success;
		$this->count = $count;
		$this->message = $message;
		$this->error_code = $error_code;
	}

	/**
	 * Create a successful result.
	 *
	 * @param int $count The number of sitemaps affected.
	 * @param string $message The message describing the result.
	 * @return self
	 */
	public static function success( int $count, string $message ): self {
		return new self( true, $count, $message );
	}

	/**
	 * Create a failure result.
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
}
