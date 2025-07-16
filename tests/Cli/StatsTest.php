<?php
/**
 * Class StatsTest
 *
 * @package Automattic\MSM_Sitemap\Tests\Cli
 */
declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Cli;

use Metro_Sitemap_CLI;

require_once __DIR__ . '/../Includes/mock-wp-cli.php';
require_once __DIR__ . '/../../includes/wp-cli.php';

/**
 * Class StatsTest
 *
 * @package Automattic\MSM_Sitemap\Tests\Cli
 */
final class StatsTest extends \Automattic\MSM_Sitemap\Tests\TestCase {

    /**
     * Test sitemap post ID.
     *
     * @var int
     */
    private int $post_id;

    /**
     * Set up test environment.
     *
     * @return void
     */
    public function setUp(): void {
        parent::setUp();
        $date = '2024-07-10';
        $this->post_id = wp_insert_post( [
            'post_type'   => 'msm_sitemap',
            'post_name'   => $date,
            'post_title'  => $date,
            'post_status' => 'publish',
            'post_date'   => $date . ' 00:00:00',
        ] );
        $this->assertIsInt( $this->post_id );
        update_post_meta( $this->post_id, 'msm_indexed_url_count', 1 );
    }

    /**
     * Clean up after tests.
     *
     * @return void
     */
    public function tearDown(): void {
        wp_delete_post( $this->post_id, true );
        parent::tearDown();
    }

    /**
     * Test stats output for sitemaps.
     *
     * @return void
     */
    public function test_stats_output(): void {
        $cli = new Metro_Sitemap_CLI();
        $this->expectOutputRegex( '/total.*most_recent.*created/s' );
        $cli->stats( [], [ 'format' => 'json' ] );
    }

    /**
     * Test stats output when there are no sitemaps.
     *
     * @return void
     */
    public function test_stats_no_sitemaps(): void {
        $cli = new Metro_Sitemap_CLI();
        wp_delete_post($this->post_id, true);
        $this->expectOutputRegex('/"total"\s*:\s*0/');
        $cli->stats([], ['format' => 'json']);
    }

    /**
     * Test stats output with multiple sitemaps.
     *
     * @return void
     */
    public function test_stats_multiple_sitemaps(): void {
        $cli = new Metro_Sitemap_CLI();
        $date2 = '2024-07-11';
        $post_id2 = wp_insert_post([
            'post_type' => 'msm_sitemap',
            'post_name' => $date2,
            'post_title' => $date2,
            'post_status' => 'publish',
            'post_date' => $date2 . ' 00:00:00',
        ]);
        $this->assertIsInt($post_id2);
        update_post_meta($post_id2, 'msm_indexed_url_count', 1);
        $this->expectOutputRegex('/"total"\s*:\s*[12]/');
        $cli->stats([], ['format' => 'json']);
        wp_delete_post($post_id2, true);
    }

    /**
     * Test stats output with sitemaps having the same date.
     *
     * @return void
     */
    public function test_stats_same_date(): void {
        $cli = new Metro_Sitemap_CLI();
        $date2 = '2024-07-10';
        $post_id2 = wp_insert_post([
            'post_type' => 'msm_sitemap',
            'post_name' => $date2,
            'post_title' => $date2,
            'post_status' => 'publish',
            'post_date' => $date2 . ' 00:00:00',
        ]);
        $this->assertIsInt($post_id2);
        update_post_meta($post_id2, 'msm_indexed_url_count', 1);
        $this->expectOutputRegex('/"total"\s*:\s*[12]/');
        $cli->stats([], ['format' => 'json']);
        wp_delete_post($post_id2, true);
    }
} 
