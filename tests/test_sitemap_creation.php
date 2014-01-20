<?php

require_once( 'msm_sitemap_test.php');

class WP_Test_Sitemap_Creation extends WP_UnitTestCase {
	
	public $core = null;
	
	private $num_days = 4;
	private $posts = array();
	private $posts_created = array();
	private $test_base;

	/**
	 * Generate posts and build the sitemap
	 */
	function setup() {
		$this->test_base = new MSM_SiteMap_Test();
		
		// Create posts for the last num_days days
		$dates = array();
		for ( $i = 0; $i < $this->num_days; $i++ )
			$dates[] = date( 'Y' ). '-' . date('m') . '-' . ( (int) date('d') - $i - 1 );
		
		$this->test_base->create_dummy_posts($dates);
		
		$this->assertCount($this->num_days, $this->test_base->posts);
		$this->test_base->build_sitemaps();
	}

	/**
	 * Remove the sample posts and the sitemap posts
	 */
	function teardown() {
		$this->test_base->posts = array();
		$sitemaps = get_posts( array(
			'post_type' => Metro_Sitemap::SITEMAP_CPT,
			'fields' => 'ids',
			'posts_per_page' => -1,
		) );
		array_map( 'wp_delete_post', array_merge( $this->test_base->posts_created, $sitemaps ) );
	}

	/**
	 * Examines the XML stored in the database after sitemap generation
	 */
	function test_sitemap_posts_were_created() {
		global $post;
		
		$sitemaps = get_posts( array(
			'post_type' => Metro_Sitemap::SITEMAP_CPT,
			'fields' => 'ids',
			'posts_per_page' => -1,
		) );
		
		$this->assertCount( $this->num_days, $sitemaps );
		
		foreach ( $sitemaps as $i => $map_id ) {
			$xml = get_post_meta( $map_id, 'msm_sitemap_xml', true );
			$post_id = $this->test_base->posts[$i]['ID'];
			$this->assertContains( 'p=' . $post_id, $xml );

			$xml_struct = simplexml_load_string( $xml );
			$this->assertNotEmpty( $xml_struct->url );
			$this->assertNotEmpty( $xml_struct->url->loc );
			$this->assertNotEmpty( $xml_struct->url->lastmod );
			$this->assertContains( 'p=' . $post_id, (string) $xml_struct->url->loc );

			$post = get_post( $post_id );
			setup_postdata( $post );
			$mod_date = get_the_modified_date( 'Y-m-d' ) . 'T' . get_the_modified_date( 'H:i:s' ) . 'Z';
			$this->assertSame( $mod_date, (string) $xml_struct->url->lastmod );
			wp_reset_postdata();
		}
	}
}