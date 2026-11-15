<?php
/**
 * Tests that users cannot create or edit sitemap posts or their meta.
 *
 * @package Automattic\MSM_Sitemap\Tests
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Integration;

use IXR_Error;
use Metro_Sitemap;
use WPDieException;
use wp_xmlrpc_server;

/**
 * Sitemap posts are written only by the plugin, so every route a user could
 * write one through must be closed: post-new.php, post.php (and quick, bulk and
 * custom-field edits that route through edit_post()), admin-ajax add-meta and
 * XML-RPC.
 */
final class SitemapPostTypeAccessTest extends TestCase {

	/**
	 * ID of a sitemap post built by the plugin.
	 *
	 * @var int
	 */
	private $sitemap_id;

	/**
	 * Build one sitemap to attack.
	 */
	public function setUp(): void {
		parent::setUp();

		require_once ABSPATH . 'wp-admin/includes/post.php';

		// The test framework unregisters meta keys after each test; init re-registers them on every real request.
		Metro_Sitemap::create_post_type();

		$this->create_dummy_post( '2018-01-01 00:00:00' );
		$this->build_sitemaps();
		$this->sitemap_id = (int) Metro_Sitemap::get_sitemap_post_id( 2018, 1, 1 );
		$this->assertGreaterThan( 0, $this->sitemap_id );
	}

	/**
	 * Clean up request globals.
	 */
	public function tearDown(): void {
		$_POST = array();
		parent::tearDown();
	}

	/**
	 * Roles that must not be able to touch sitemap posts.
	 *
	 * @return array<string, array{string}>
	 */
	public static function data_roles(): array {
		return array(
			'contributor'   => array( 'contributor' ),
			'author'        => array( 'author' ),
			'editor'        => array( 'editor' ),
			'administrator' => array( 'administrator' ),
		);
	}

	/**
	 * The post type has no admin screens: edit.php and post-new.php both die
	 * when show_ui is false.
	 */
	public function test_post_type_has_no_admin_ui_or_rest_route(): void {
		$post_type = get_post_type_object( Metro_Sitemap::SITEMAP_CPT );

		$this->assertFalse( $post_type->show_ui );
		$this->assertFalse( $post_type->show_in_rest );
	}

	/**
	 * No role can create, edit, publish or delete sitemap posts.
	 *
	 * @dataProvider data_roles
	 *
	 * @param string $role Role to test.
	 */
	public function test_role_cannot_manage_sitemap_posts( string $role ): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );
		$cap = get_post_type_object( Metro_Sitemap::SITEMAP_CPT )->cap;

		$this->assertFalse( current_user_can( $cap->create_posts ), 'create_posts' );
		$this->assertFalse( current_user_can( $cap->edit_posts ), 'edit_posts' );
		$this->assertFalse( current_user_can( $cap->publish_posts ), 'publish_posts' );
		$this->assertFalse( current_user_can( 'edit_post', $this->sitemap_id ), 'edit_post' );
		$this->assertFalse( current_user_can( 'delete_post', $this->sitemap_id ), 'delete_post' );
	}

	/**
	 * No role can add, edit or delete the sitemap meta, so admin-ajax add-meta
	 * and the custom fields box are closed even if post caps change later.
	 *
	 * @dataProvider data_roles
	 *
	 * @param string $role Role to test.
	 */
	public function test_role_cannot_write_sitemap_meta( string $role ): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );

		foreach ( array( 'msm_sitemap_xml', 'msm_indexed_url_count' ) as $key ) {
			foreach ( array( 'add_post_meta', 'edit_post_meta', 'delete_post_meta' ) as $meta_cap ) {
				$this->assertFalse( current_user_can( $meta_cap, $this->sitemap_id, $key ), "$meta_cap $key" );
			}
		}
	}

	/**
	 * The meta stays locked even for a user who is granted every sitemap post
	 * cap, so loosening the post caps later cannot reopen the XML to users.
	 */
	public function test_meta_is_locked_independently_of_post_caps(): void {
		$user = self::factory()->user->create_and_get( array( 'role' => 'editor' ) );
		foreach ( (array) get_post_type_object( Metro_Sitemap::SITEMAP_CPT )->cap as $cap ) {
			$user->add_cap( $cap );
		}
		wp_set_current_user( $user->ID );

		$this->assertTrue( current_user_can( 'edit_post', $this->sitemap_id ), 'precondition: user can edit the post' );

		foreach ( array( 'msm_sitemap_xml', 'msm_indexed_url_count' ) as $key ) {
			foreach ( array( 'add_post_meta', 'edit_post_meta', 'delete_post_meta' ) as $meta_cap ) {
				$this->assertFalse( current_user_can( $meta_cap, $this->sitemap_id, $key ), "$meta_cap $key" );
			}
		}
	}

	/**
	 * post.php: an Author cannot set a sitemap's XML through the custom fields
	 * that edit_post() always processes, even on a sitemap post they own.
	 */
	public function test_author_cannot_write_sitemap_xml_via_edit_post(): void {
		$author_id = self::factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $author_id );
		$own_sitemap_id = self::factory()->post->create(
			array(
				'post_type'   => Metro_Sitemap::SITEMAP_CPT,
				'post_author' => $author_id,
				'post_status' => 'draft',
			)
		);

		$_POST = array(
			'post_ID'      => $own_sitemap_id,
			'post_title'   => 'x',
			'metakeyinput' => 'msm_sitemap_xml',
			'metavalue'    => '<html xmlns="http://www.w3.org/1999/xhtml"><script>alert(1)</script></html>',
		);

		try {
			edit_post();
			$this->fail( 'edit_post() should die for a sitemap post.' );
		} catch ( WPDieException $e ) {
			$this->assertStringContainsString( 'not allowed', $e->getMessage() );
		}

		$this->assertSame( '', get_post_meta( $own_sitemap_id, 'msm_sitemap_xml', true ) );
	}

	/**
	 * post-new.php: an Author cannot create a sitemap post through
	 * wp_write_post(), the handler behind the "Add New" form.
	 */
	public function test_author_cannot_create_sitemap_via_write_post(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );

		$_POST = array(
			'post_type'    => Metro_Sitemap::SITEMAP_CPT,
			'post_title'   => 'x',
			'post_status'  => 'publish',
			'metakeyinput' => 'msm_sitemap_xml',
			'metavalue'    => '<x/>',
		);

		$result = wp_write_post();

		$this->assertWPError( $result );
		$this->assertSame( 'edit_posts', $result->get_error_code() );
	}

	/**
	 * XML-RPC: an Author cannot create a sitemap post with wp.newPost.
	 */
	public function test_author_cannot_create_sitemap_via_xmlrpc(): void {
		require_once ABSPATH . WPINC . '/class-IXR.php';
		require_once ABSPATH . WPINC . '/class-wp-xmlrpc-server.php';

		add_filter( 'pre_option_enable_xmlrpc', '__return_true' );
		self::factory()->user->create(
			array(
				'role'       => 'author',
				'user_login' => 'msm_author',
				'user_pass'  => 'msm_author',
			)
		);

		$server = new wp_xmlrpc_server();
		$result = $server->wp_newPost(
			array(
				1,
				'msm_author',
				'msm_author',
				array(
					'post_type'     => Metro_Sitemap::SITEMAP_CPT,
					'post_status'   => 'publish',
					'post_title'    => 'x',
					'post_date'     => '1999-01-01 00:00:00',
					'custom_fields' => array(
						array(
							'key'   => 'msm_sitemap_xml',
							'value' => '<x/>',
						),
					),
				),
			)
		);

		$this->assertInstanceOf( IXR_Error::class, $result );
		$this->assertSame( 401, $result->code );
		$this->assertFalse( Metro_Sitemap::get_sitemap_post_id( 1999, 1, 1 ) );
	}

	/**
	 * Sitemap generation still works once users are locked out, because it
	 * writes through wp_insert_post() and update_post_meta() with no cap checks.
	 */
	public function test_generation_still_writes_sitemap_xml(): void {
		$this->assertStringContainsString( '<urlset', get_post_meta( $this->sitemap_id, 'msm_sitemap_xml', true ) );
		$this->assertSame( 1, (int) get_post_meta( $this->sitemap_id, 'msm_indexed_url_count', true ) );
	}
}
