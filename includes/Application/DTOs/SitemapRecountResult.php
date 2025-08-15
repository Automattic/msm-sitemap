<?php
/**
 * DTO for sitemap recount operation results.
 *
 * @package Automattic\MSM_Sitemap\Application\DTOs
 */

namespace Automattic\MSM_Sitemap\Application\DTOs;

/**
 * DTO for sitemap recount operation results.
 */
final class SitemapRecountResult implements SitemapOperationResultInterface {
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
	 * Total URLs found.
	 *
	 * @var int
	 */
	private int $total_urls;

	/**
	 * Recount errors.
	 *
	 * @var array
	 */
	private array $recount_errors;

	/**
	 * Constructor.
	 *
	 * @param bool $success Whether the operation was successful.
	 * @param int $count The number of sitemaps affected.
	 * @param string $message The message describing the result.
	 * @param string|null $error_code The error code if the operation failed.
	 * @param int $total_urls Total URLs found.
	 * @param array $recount_errors Recount errors.
	 */
	private function __construct(
		bool $success,
		int $count,
		string $message,
		?string $error_code,
		int $total_urls = 0,
		array $recount_errors = array()
	) {
		$this->success        = $success;
		$this->count          = $count;
		$this->message        = $message;
		$this->error_code     = $error_code;
		$this->total_urls     = $total_urls;
		$this->recount_errors = $recount_errors;
	}

	/**
	 * Create a successful recount result.
	 *
	 * @param int $count The number of sitemaps affected.
	 * @param string $message The message describing the result.
	 * @param int $total_urls Total URLs found.
	 * @param array $recount_errors Recount errors.
	 * @return self
	 */
	public static function success(
		int $count,
		string $message,
		int $total_urls = 0,
		array $recount_errors = array()
	): self {
		return new self( true, $count, $message, null, $total_urls, $recount_errors );
	}

	/**
	 * Create a failure recount result.
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
	 * Get total URLs found.
	 *
	 * @return int
	 */
	public function get_total_urls(): int {
		return $this->total_urls;
	}

	/**
	 * Get recount errors.
	 *
	 * @return array
	 */
	public function get_recount_errors(): array {
		return $this->recount_errors;
	}

	/**
	 * Convert to array for CLI output.
	 *
	 * @return array
	 */
}
