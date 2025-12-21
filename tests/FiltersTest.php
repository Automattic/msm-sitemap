<?php
/**
 * WP_Test_Sitemap_Filter Query
 *
 * @package Automattic\MSM_Sitemap\Tests
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests;

use Automattic\MSM_Sitemap\Infrastructure\REST\SitemapEndpointHandler;
use Automattic\MSM_Sitemap\Infrastructure\Repositories\PostRepository;
use WP_Query;

/**
 * Unit Tests to validate Filters applied when generating Sitemaps.
 */
class FiltersTest extends TestCase {

	/**
	 * Verify that request for sitemap url doesn't cause Main Query to hit db.
	 */
	public function test_bypass_main_query(): void {
		global $wp_query;

		// Verify post_pre_query on sitemap query_var returns empty array
		set_query_var( 'sitemap', 'true' );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Testing WordPress core hook.
		$posts = apply_filters_ref_array( 'posts_pre_query', array( null, $wp_query ) );
		$this->assertIsArray( $posts );
		$this->assertEmpty( $posts );
	}

	/**
	 * Verify that secondary query is not get modified if sitemap var is set.
	 */
	public function test_secondary_query_not_bypassed(): void {

		// Verify post_pre_query filter returns null by default
		$exp_result = array( 1 );

		$query         = new WP_Query(
			array(
				'post_type' => 'post',
				'sitemap'   => 'true',
			) 
		);
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Testing WordPress core hook.
		$sitemap_posts = apply_filters_ref_array( 'posts_pre_query', array( $exp_result, $query ) );
		$this->assertEquals( $exp_result, $sitemap_posts, 'Non-Main WP_Query is being modified from sitemap query var' );
	}

	/**
	 * Verify that msm_sitemap_index filter runs when sitemap index is generated.
	 */
	public function test_msm_sitemap_index_filter_ran(): void {
		$ran = false;

		add_filter(
			'msm_sitemap_index_sitemaps',
			function () use ( &$ran ) {
				$ran = true;
				return array();
			} 
		);
		
		$sitemap_endpoint_handler = $this->get_service( SitemapEndpointHandler::class );
		$sitemap_endpoint_handler->get_sitemap_index_xml();

		$this->assertTrue( $ran );
	}

	/**
	 * Verify that msm_sitemap_pre_get_post_year_range filter can short-circuit the year range.
	 */
	public function test_pre_get_post_year_range_filter_short_circuits(): void {
		add_filter(
			'msm_sitemap_pre_get_post_year_range',
			function () {
				return array( 2000, 2001, 2002 );
			} 
		);

		$post_repository = new PostRepository();
		$years           = $post_repository->get_post_year_range();

		$this->assertEquals( array( 2000, 2001, 2002 ), $years );

		remove_all_filters( 'msm_sitemap_pre_get_post_year_range' );
	}

	/**
	 * Verify that msm_sitemap_index_appended_xml filter can append XML and receives correct arguments.
	 */
	public function test_msm_sitemap_index_appended_xml_filter(): void {
		// Create a post and build sitemaps to ensure there is at least one sitemap.
		$this->add_a_post_for_today();
		$this->build_sitemaps();

		$called_args = null;
		$append_xml  = '<sitemap><loc>https://example.com/custom.xml</loc></sitemap>';

		add_filter(
			'msm_sitemap_index_appended_xml',
			function ( $appended_xml, $year, $sitemaps ) use ( $append_xml, &$called_args ) {
				$called_args = array( $appended_xml, $year, $sitemaps );
				return $append_xml;
			},
			10,
			3
		);

		$sitemap_endpoint_handler = $this->get_service( SitemapEndpointHandler::class );
		$xml                      = $sitemap_endpoint_handler->get_sitemap_index_xml();
		
		$this->assertStringContainsString( $append_xml, $xml );
		$this->assertIsArray( $called_args );
		$this->assertEquals( '', $called_args[0] );
		$this->assertFalse( $called_args[1] );
		$this->assertIsArray( $called_args[2] );
		$this->assertNotEmpty( $called_args[2] );

		remove_all_filters( 'msm_sitemap_index_appended_xml' );
	}

	/**
	 * Verify that msm_sitemap_index_xml filter can modify the final XML and receives correct arguments.
	 */
	public function test_msm_sitemap_index_xml_filter(): void {
		// Create a post and build sitemaps to ensure there is at least one sitemap.
		$this->add_a_post_for_today();
		$this->build_sitemaps();

		$called_args     = null;
		$replacement_xml = '<?xml version="1.0"?><sitemapindex><sitemap><loc>https://example.com/override.xml</loc></sitemap></sitemapindex>';

		add_filter(
			'msm_sitemap_index_xml',
			function ( $xml_string, $year, $sitemaps ) use ( $replacement_xml, &$called_args ) {
				$called_args = array( $xml_string, $year, $sitemaps );
				return $replacement_xml;
			},
			10,
			3
		);

		$sitemap_endpoint_handler = $this->get_service( SitemapEndpointHandler::class );
		$xml                      = $sitemap_endpoint_handler->get_sitemap_index_xml();
		
		$this->assertEquals( $replacement_xml, $xml );
		$this->assertIsArray( $called_args );
		$this->assertStringContainsString( '<sitemapindex', $called_args[0] );
		$this->assertFalse( $called_args[1] );
		$this->assertIsArray( $called_args[2] );
		$this->assertNotEmpty( $called_args[2] );

		remove_all_filters( 'msm_sitemap_index_xml' );
	}
}
