<?php
/**
 * Sitemap Generated Event
 *
 * @package Automattic\MSM_Sitemap\Application\Events
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Application\Events;

/**
 * Event fired when a sitemap is successfully generated.
 */
class SitemapGeneratedEvent {

	/**
	 * The date for which the sitemap was generated.
	 *
	 * @var string
	 */
	private string $date;

	/**
	 * The number of URLs in the generated sitemap.
	 *
	 * @var int
	 */
	private int $url_count;

	/**
	 * The time taken to generate the sitemap in seconds.
	 *
	 * @var float
	 */
	private float $generation_time;

	/**
	 * The source that triggered the generation (cli, rest, cron, etc.).
	 *
	 * @var string
	 */
	private string $generated_by;

	/**
	 * Constructor.
	 *
	 * @param string $date            The date for which the sitemap was generated.
	 * @param int    $url_count       The number of URLs in the sitemap.
	 * @param float  $generation_time The time taken to generate in seconds.
	 * @param string $generated_by    The source that triggered generation.
	 */
	public function __construct(
		string $date,
		int $url_count,
		float $generation_time,
		string $generated_by = 'unknown'
	) {
		$this->date            = $date;
		$this->url_count       = $url_count;
		$this->generation_time = $generation_time;
		$this->generated_by    = $generated_by;
	}

	/**
	 * Get the date for which the sitemap was generated.
	 *
	 * @return string The date.
	 */
	public function get_date(): string {
		return $this->date;
	}

	/**
	 * Get the number of URLs in the sitemap.
	 *
	 * @return int The URL count.
	 */
	public function get_url_count(): int {
		return $this->url_count;
	}

	/**
	 * Get the time taken to generate the sitemap.
	 *
	 * @return float The generation time in seconds.
	 */
	public function get_generation_time(): float {
		return $this->generation_time;
	}

	/**
	 * Get the source that triggered the generation.
	 *
	 * @return string The generation source.
	 */
	public function get_generated_by(): string {
		return $this->generated_by;
	}
}
