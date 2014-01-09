<?php

class WP_Test_Sitemap_Creation extends WP_UnitTestCase {
	
	public $core = null;
	
	private $posts = array();
	private $posts_created = array();

	/**
	 * Generate posts and build the sitemap
	 */
	function setup() {
		// Add a post for that last few days
                $num_days = 4;
		for ( $i = 0; $i < $num_days; $i++ ) {
			$post_data = array(
				'post_title' => (string) uniqid(),
                                'post_type' => 'post',
				'post_content' => (string) uniqid(),
				'post_status' => 'publish',
                                'post_author' => 1,
			);
                        
			$post_data['post_date'] = $post_data['post_modified'] = date( 'Y' ). '-' . date('m') . '-' . ( (int)date('d') - $i - 1 ) . ' 12:00:00';
			$post_data['ID'] = wp_insert_post( $post_data, true );
                        if ( is_wp_error($post_data['ID']) || 0 == $post_data['ID'] )
                                throw new Exception ("Error: WP Error encountered inserting post. {$post_data['ID']->errors}, {$post_data['ID']->error_data}");
                                
			$this->posts_created[] = $post_data['ID'];
			$this->posts[] = $post_data;
		}
                
                $this->assertCount($num_days, $this->posts);
		$this->build_sitemaps();
	}

	/**
	 * Remove the sample posts and the sitemap posts
	 */
	function teardown() {
		$sitemaps = get_posts( array(
			'post_type' => Metro_Sitemap::SITEMAP_CPT,
			'fields' => 'ids',
			'posts_per_page' => -1,
		) );
		array_map( 'wp_delete_post', array_merge( $this->posts_created, $sitemaps ) );
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
                
		$this->assertCount( 4, $sitemaps );
                
		foreach ( $sitemaps as $i => $map_id ) {
			$xml = get_post_meta( $map_id, 'msm_sitemap_xml', true );
			$post_id = $this->posts[$i]['ID'];
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

	/**
	 * Generate sitemaps; pretends to run cron six times
	 */
	private function build_sitemaps() {
		_set_cron_array( array() ); // isolate our cron jobs.
		MSM_Sitemap_Builder_Cron::reset_sitemap_data();
		delete_option( 'msm_stop_processing' );
		MSM_Sitemap_Builder_Cron::generate_full_sitemap();
		update_option( 'msm_sitemap_create_in_progress', true );
                
		$this->fake_cron(); // this year
		$this->fake_cron(); // this month
		$this->fake_cron(); // today
		$this->fake_cron(); // yesterday
		$this->fake_cron(); // two days ago
		$this->fake_cron(); // three days ago
                $this->fake_cron();
	}

	/**
	 * Fakes a cron job
	 */
	private function fake_cron() {
		$schedule = _get_cron_array();
		foreach ( $schedule as $timestamp => $cron ) {
			foreach ( $cron as $hook => $arg_wrapper ) {
				if ( substr( $hook, 0, 3 ) !== 'msm' ) continue; // only run our own jobs.
				$arg_struct = array_pop( $arg_wrapper );
				$args = $arg_struct['args'][0];
				wp_unschedule_event( $timestamp, $hook, $arg_struct['args'] );
				do_action( $hook, $args );
			}
		}
	}

}