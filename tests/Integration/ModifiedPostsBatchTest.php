<?php
/**
 * Tests that the modified posts cron works through more than one batch of changes.
 *
 * @package Metro_Sitemap/unit_tests
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Integration;

use Metro_Sitemap;

/**
 * Tests that the modified posts cron works through more than one batch of changes.
 */
class ModifiedPostsBatchTest extends TestCase {

	/**
	 * Days queued for a sitemap update, as Y-m-d strings.
	 *
	 * @var string[]
	 */
	private $queued_days = array();

	/**
	 * A time comfortably before the test posts were modified.
	 *
	 * @var int
	 */
	private $base;

	/**
	 * Record queued days and set the last run before any test post was modified.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->base = time() - 2000;
		update_option( 'msm_sitemap_update_last_run', $this->base, false );

		add_filter(
			'msm_update_sitemap_for_year_month_date',
			function ( $date ) {
				$this->queued_days[] = vsprintf( '%04d-%02d-%02d', $date );
			},
			10,
			2
		);
	}

	/**
	 * Insert published posts directly, which is much faster than wp_insert_post() for a thousand of them.
	 *
	 * @param int    $count       Number of posts.
	 * @param string $post_date   Local post date.
	 * @param int    $modified_at Timestamp of the first post's modification; later posts are a second apart unless $same_second.
	 * @param bool   $same_second Whether every post shares the same modified time.
	 */
	private function insert_posts( int $count, string $post_date, int $modified_at, bool $same_second = false ): void {
		global $wpdb;

		for ( $i = 0; $i < $count; $i++ ) {
			$modified = gmdate( 'Y-m-d H:i:s', $same_second ? $modified_at : $modified_at + $i );
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->posts,
				array(
					'post_type'             => 'post',
					'post_status'           => 'publish',
					'post_title'            => 'Batch post',
					'post_content'          => '',
					'post_excerpt'          => '',
					'to_ping'               => '',
					'pinged'                => '',
					'post_content_filtered' => '',
					'post_date'             => $post_date,
					'post_date_gmt'         => $post_date,
					'post_modified'         => $modified,
					'post_modified_gmt'     => $modified,
				)
			);
		}
	}

	/**
	 * Changes beyond the first 1,000 are picked up by the next run.
	 */
	public function test_next_run_carries_on_after_a_full_batch(): void {
		$this->insert_posts( 1000, '2020-01-01 10:00:00', $this->base + 1 );
		$this->insert_posts( 1, '2019-06-15 10:00:00', $this->base + 1001 );

		Metro_Sitemap::update_sitemap_from_modified_posts();

		$this->assertSame( array( '2020-01-01' ), $this->queued_days );
		$this->assertSame( $this->base + 1000, (int) get_option( 'msm_sitemap_update_last_run' ) );

		$this->queued_days = array();
		Metro_Sitemap::update_sitemap_from_modified_posts();

		$this->assertContains( '2019-06-15', $this->queued_days );
	}

	/**
	 * A full batch modified in the same second does not hold the cron in place.
	 */
	public function test_full_batch_in_one_second_does_not_get_stuck(): void {
		$this->insert_posts( 1001, '2020-01-01 10:00:00', $this->base + 5, true );

		Metro_Sitemap::update_sitemap_from_modified_posts();
		$this->assertSame( $this->base + 5, (int) get_option( 'msm_sitemap_update_last_run' ) );

		Metro_Sitemap::update_sitemap_from_modified_posts();
		$this->assertSame( $this->base + 6, (int) get_option( 'msm_sitemap_update_last_run' ) );
	}

	/**
	 * A run under the limit records the time it started.
	 */
	public function test_partial_batch_records_run_start(): void {
		$this->insert_posts( 2, '2020-01-01 10:00:00', $this->base + 1 );

		$before = time();
		Metro_Sitemap::update_sitemap_from_modified_posts();
		$last_run = (int) get_option( 'msm_sitemap_update_last_run' );

		$this->assertGreaterThanOrEqual( $before, $last_run );
		$this->assertLessThanOrEqual( time(), $last_run );
	}
}
