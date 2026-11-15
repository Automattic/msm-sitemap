<?php
/**
 * Tests for Metro_Sitemap post status and date change handling.
 *
 * @package Metro_Sitemap/unit_tests
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Integration;

use Metro_Sitemap;

/**
 * Tests for Metro_Sitemap post status and date change handling.
 */
class PostStatusChangeTest extends TestCase {

	/**
	 * Days queued for a sitemap update, as Y-m-d strings.
	 *
	 * @var string[]
	 */
	private $queued_days = array();

	/**
	 * Start recording queued sitemap updates.
	 */
	private function record_queued_days(): void {
		$this->queued_days = array();
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
	 * Test that the status and date change hooks are registered.
	 */
	public function test_hooks_registered(): void {
		$this->assertSame( 10, has_action( 'transition_post_status', array( 'Metro_Sitemap', 'handle_post_status_transition' ) ) );
		$this->assertSame( 10, has_action( 'post_updated', array( 'Metro_Sitemap', 'handle_post_date_change' ) ) );
	}

	/**
	 * Test that unpublishing a post queues its day.
	 */
	public function test_unpublish_queues_day(): void {
		$post_id = $this->create_dummy_post( '2018-02-01 10:00:00' );
		$this->record_queued_days();

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'draft',
			)
		);

		$this->assertSame( array( '2018-02-01' ), $this->queued_days );
	}

	/**
	 * Test that setting the status to trash directly queues its day.
	 *
	 * This path does not fire trashed_post.
	 */
	public function test_direct_status_change_to_trash_queues_day(): void {
		$post_id = $this->create_dummy_post( '2018-02-14 10:00:00' );
		$this->record_queued_days();

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'trash',
			)
		);

		$this->assertSame( array( '2018-02-14' ), $this->queued_days );
	}

	/**
	 * Test that a scheduled post going live queues its day.
	 */
	public function test_scheduled_post_going_live_queues_day(): void {
		$date    = gmdate( 'Y-m-d', strtotime( '+2 days' ) );
		$post_id = $this->create_dummy_post( $date . ' 10:00:00', 'future' );
		$this->assertSame( 'future', get_post_status( $post_id ) );
		$this->record_queued_days();

		wp_publish_post( $post_id );

		$this->assertSame( array( $date ), $this->queued_days );
	}

	/**
	 * Test that saving a draft as a draft does not queue anything.
	 */
	public function test_draft_to_draft_does_not_queue(): void {
		$post_id = $this->create_dummy_post( '2018-02-02 10:00:00', 'draft' );
		$this->record_queued_days();

		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => 'Updated draft',
			)
		);

		$this->assertSame( array(), $this->queued_days );
	}

	/**
	 * Test that unsupported post types are ignored.
	 */
	public function test_unsupported_post_type_is_ignored(): void {
		$post_id = $this->create_dummy_post( '2018-02-03 10:00:00', 'publish', 'page' );
		$this->record_queued_days();

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'draft',
			)
		);

		$this->assertSame( array(), $this->queued_days );
	}

	/**
	 * Test that moving a published post to another day queues both days.
	 */
	public function test_date_change_on_published_post_queues_old_and_new_day(): void {
		$post_id = $this->create_dummy_post( '2018-02-04 10:00:00' );
		$this->record_queued_days();

		wp_update_post(
			array(
				'ID'            => $post_id,
				'post_date'     => '2018-02-05 10:00:00',
				'post_date_gmt' => '2018-02-05 10:00:00',
			)
		);

		$this->assertSame( array( '2018-02-04', '2018-02-05' ), $this->queued_days );
	}

	/**
	 * Test that changing the time but not the day does not queue anything.
	 */
	public function test_time_change_on_same_day_does_not_queue(): void {
		$post_id = $this->create_dummy_post( '2018-02-06 10:00:00' );
		$this->record_queued_days();

		wp_update_post(
			array(
				'ID'            => $post_id,
				'post_date'     => '2018-02-06 18:00:00',
				'post_date_gmt' => '2018-02-06 18:00:00',
			)
		);

		$this->assertSame( array(), $this->queued_days );
	}

	/**
	 * Test that moving a draft to another day does not queue anything.
	 */
	public function test_date_change_on_draft_does_not_queue(): void {
		$post_id = $this->create_dummy_post( '2018-02-07 10:00:00', 'draft' );
		$this->record_queued_days();

		wp_update_post(
			array(
				'ID'            => $post_id,
				'post_date'     => '2018-02-08 10:00:00',
				'post_date_gmt' => '2018-02-08 10:00:00',
			)
		);

		$this->assertSame( array(), $this->queued_days );
	}

	/**
	 * Test that unpublishing and moving a post in one save removes it from the old day,
	 * and does not add it to the new one.
	 */
	public function test_unpublish_with_date_change_removes_post_from_old_day(): void {
		$post_id = $this->create_dummy_post( '2018-02-09 10:00:00' );
		Metro_Sitemap::generate_sitemap_for_date( '2018-02-09' );

		wp_update_post(
			array(
				'ID'            => $post_id,
				'post_status'   => 'draft',
				'post_date'     => '2018-02-10 10:00:00',
				'post_date_gmt' => '2018-02-10 10:00:00',
			)
		);
		$this->fake_cron();

		$this->assertFalse( Metro_Sitemap::get_sitemap_post_id( 2018, 2, 9 ) );
		$this->assertFalse( Metro_Sitemap::get_sitemap_post_id( 2018, 2, 10 ) );
	}

	/**
	 * Test that the msm_sitemap_post_status filter is respected.
	 */
	public function test_respects_custom_sitemap_status(): void {
		add_filter(
			'msm_sitemap_post_status',
			function () {
				return 'private';
			}
		);

		$published_id = $this->create_dummy_post( '2018-02-11 10:00:00' );
		$private_id   = $this->create_dummy_post( '2018-02-12 10:00:00', 'private' );
		$this->record_queued_days();

		wp_update_post(
			array(
				'ID'          => $published_id,
				'post_status' => 'draft',
			)
		);
		wp_update_post(
			array(
				'ID'          => $private_id,
				'post_status' => 'draft',
			)
		);

		$this->assertSame( array( '2018-02-12' ), $this->queued_days );
	}

	/**
	 * Test that unpublishing the only post on a day removes that day's sitemap.
	 */
	public function test_unpublishing_only_post_removes_sitemap(): void {
		$post_id = $this->create_dummy_post( '2018-02-13 10:00:00' );
		Metro_Sitemap::generate_sitemap_for_date( '2018-02-13' );
		$this->assertNotFalse( Metro_Sitemap::get_sitemap_post_id( 2018, 2, 13 ) );

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'draft',
			)
		);
		$this->fake_cron();

		$this->assertFalse( Metro_Sitemap::get_sitemap_post_id( 2018, 2, 13 ) );
	}

	/**
	 * Test that moving a published post to another day moves it between sitemaps.
	 */
	public function test_date_change_moves_post_between_sitemaps(): void {
		$moved_id = $this->create_dummy_post( '2018-03-01 10:00:00' );
		$staying  = $this->create_dummy_post( '2018-03-02 10:00:00' );
		Metro_Sitemap::generate_sitemap_for_date( '2018-03-01' );
		Metro_Sitemap::generate_sitemap_for_date( '2018-03-02' );

		wp_update_post(
			array(
				'ID'            => $moved_id,
				'post_date'     => '2018-03-02 12:00:00',
				'post_date_gmt' => '2018-03-02 12:00:00',
			)
		);
		$this->fake_cron();

		$this->assertFalse( Metro_Sitemap::get_sitemap_post_id( 2018, 3, 1 ), 'The old day should have no sitemap left.' );
		$this->assertSame(
			array( get_permalink( $staying ), get_permalink( $moved_id ) ),
			$this->get_sitemap_urls( 2018, 3, 2 )
		);
		$this->assertSame( 2, Metro_Sitemap::get_total_indexed_url_count() );
	}

	/**
	 * Test that status changes in and out of the sitemap status rebuild the post's day.
	 *
	 * @dataProvider data_status_changes
	 *
	 * @param string $from     The post's status before the change.
	 * @param string $to       The post's status after the change.
	 * @param bool   $included Whether the post should be in the sitemap after the change.
	 */
	public function test_status_change_rebuilds_day( string $from, string $to, bool $included ): void {
		$other_id = $this->create_dummy_post( '2018-04-01 09:00:00' );
		$post_id  = $this->create_dummy_post( '2018-04-01 10:00:00', $from );
		Metro_Sitemap::generate_sitemap_for_date( '2018-04-01' );

		// Keep the post's date, as an editor who set one would. Without it, publishing an undated draft moves it to today.
		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => $to,
				'edit_date'   => true,
			)
		);
		$this->fake_cron();

		$expected = array( get_permalink( $other_id ) );
		if ( $included ) {
			$expected[] = get_permalink( $post_id );
		}
		$this->assertSame( $expected, $this->get_sitemap_urls( 2018, 4, 1 ) );
	}

	/**
	 * Data provider for test_status_change_rebuilds_day().
	 *
	 * @return array<string, array{string, string, bool}>
	 */
	public function data_status_changes(): array {
		return array(
			'draft to publish'   => array( 'draft', 'publish', true ),
			'pending to publish' => array( 'pending', 'publish', true ),
			'publish to draft'   => array( 'publish', 'draft', false ),
			'publish to pending' => array( 'publish', 'pending', false ),
			'publish to private' => array( 'publish', 'private', false ),
		);
	}

	/**
	 * Test that publishing an undated draft adds it to the sitemap for the day it is published.
	 */
	public function test_publishing_undated_draft_adds_it_to_todays_sitemap(): void {
		$post_id = $this->create_dummy_post( '2018-04-03 10:00:00', 'draft' );

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'publish',
			)
		);
		$this->fake_cron();

		list( $year, $month, $day ) = array_map( 'intval', explode( '-', substr( get_post( $post_id )->post_date, 0, 10 ) ) );
		$this->assertContains( get_permalink( $post_id ), $this->get_sitemap_urls( $year, $month, $day ) );
	}

	/**
	 * Test that trashing a post with wp_trash_post() removes it from its day's sitemap.
	 */
	public function test_trashing_post_removes_it_from_sitemap(): void {
		$other_id = $this->create_dummy_post( '2018-04-02 09:00:00' );
		$post_id  = $this->create_dummy_post( '2018-04-02 10:00:00' );
		Metro_Sitemap::generate_sitemap_for_date( '2018-04-02' );

		wp_trash_post( $post_id );
		$this->fake_cron();

		$this->assertSame( array( get_permalink( $other_id ) ), $this->get_sitemap_urls( 2018, 4, 2 ) );
	}

	/**
	 * Get the URLs listed in a day's sitemap, in order.
	 *
	 * @return string[]
	 */
	private function get_sitemap_urls( int $year, int $month, int $day ): array {
		$sitemap_id = Metro_Sitemap::get_sitemap_post_id( $year, $month, $day );
		$this->assertNotFalse( $sitemap_id, 'The day should have a sitemap.' );

		$xml = simplexml_load_string( get_post_meta( $sitemap_id, 'msm_sitemap_xml', true ) );

		$urls = array();
		foreach ( $xml->url as $url ) {
			$urls[] = (string) $url->loc;
		}

		return $urls;
	}
}
