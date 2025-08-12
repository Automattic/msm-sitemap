<?php
/**
 * Base interface for sitemap operation results.
 *
 * @package Automattic\MSM_Sitemap\Application\DTOs
 */

namespace Automattic\MSM_Sitemap\Application\DTOs;

/**
 * Base interface for sitemap operation results.
 */
interface SitemapOperationResultInterface {
	/**
	 * Check if the operation was successful.
	 *
	 * @return bool
	 */
	public function is_success(): bool;

	/**
	 * Get the number of sitemaps affected.
	 *
	 * @return int
	 */
	public function get_count(): int;

	/**
	 * Get the message describing the result.
	 *
	 * @return string
	 */
	public function get_message(): string;

	/**
	 * Get the error code if the operation failed.
	 *
	 * @return string|null
	 */
	public function get_error_code(): ?string;
}
