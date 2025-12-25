<?php
/**
 * Sitemap Generator Test
 *
 * @package Automattic\MSM_Sitemap\Tests\Application\Services
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Application\Services;

use Automattic\MSM_Sitemap\Application\Services\SettingsService;
use Automattic\MSM_Sitemap\Application\Services\SitemapGenerator;
use Automattic\MSM_Sitemap\Domain\ValueObjects\SitemapContent;
use Automattic\MSM_Sitemap\Infrastructure\Providers\PostContentProvider;
use Automattic\MSM_Sitemap\Infrastructure\Repositories\PostRepository;
use Mockery;

/**
 * Test SitemapGenerator.
 */
class SitemapGeneratorTest extends \Automattic\MSM_Sitemap\Tests\TestCase {

	/**
	 * Sitemap generator instance.
	 *
	 * @var SitemapGenerator
	 */
	private SitemapGenerator $generator;

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->generator = new SitemapGenerator();
	}

	/**
	 * Create a PostContentProvider with proper dependencies.
	 *
	 * @return PostContentProvider
	 */
	private function create_post_content_provider(): PostContentProvider {
		$settings_service = Mockery::mock( SettingsService::class );
		$settings_service->shouldReceive( 'get_setting' )
			->with( 'enabled_post_types', Mockery::any() )
			->andReturn( array( 'post' ) );
		$post_repository = new PostRepository( $settings_service );
		return new PostContentProvider( $post_repository );
	}

	/**
	 * Test generator creates with empty content types.
	 */
	public function test_generator_creates_with_empty_content_types(): void {
		$providers = $this->generator->get_providers();
		$this->assertCount( 0, $providers );
	}

	/**
	 * Test generate_sitemap_for_date with no content returns empty set.
	 */
	public function test_generate_sitemap_for_date_with_no_content_returns_empty_set(): void {
		$sitemap_content = $this->generator->generate_sitemap_for_date( '2024-01-15 00:00:00' );
		$this->assertInstanceOf( SitemapContent::class, $sitemap_content );
		$this->assertTrue( $sitemap_content->is_empty() );
	}

	/**
	 * Test generate_sitemap_for_date with content returns url set.
	 */
	public function test_generate_sitemap_for_date_with_content_returns_url_set(): void {
		// Add a provider first
		$this->generator->add_provider( $this->create_post_content_provider() );

		// Create a test post
		$post_id = $this->create_dummy_post( '2024-01-15' );

		$sitemap_content = $this->generator->generate_sitemap_for_date( '2024-01-15 00:00:00' );
		$this->assertInstanceOf( SitemapContent::class, $sitemap_content );
		$this->assertFalse( $sitemap_content->is_empty() );
		$this->assertEquals( 1, $sitemap_content->count() );

		// Clean up
		wp_delete_post( $post_id, true );
	}

	/**
	 * Test get_provider_status returns provider information.
	 */
	public function test_get_provider_status_returns_provider_information(): void {
		// Add a provider first
		$this->generator->add_provider( $this->create_post_content_provider() );

		$status = $this->generator->get_provider_status();

		$this->assertIsArray( $status );
		$this->assertArrayHasKey( 'posts', $status );
		$this->assertEquals( 'Posts', $status['posts']['display_name'] );
		$this->assertEquals( 'Include published posts in sitemaps', $status['posts']['description'] );
	}

	/**
	 * Test add_provider adds new provider.
	 */
	public function test_add_provider_adds_new_provider(): void {
		$initial_count = count( $this->generator->get_providers() );

		// Add a post provider
		$this->generator->add_provider( $this->create_post_content_provider() );

		$new_count = count( $this->generator->get_providers() );
		$this->assertEquals( $initial_count + 1, $new_count );
	}

	/**
	 * Test remove_provider_by_type removes provider.
	 */
	public function test_remove_provider_by_type_removes_provider(): void {
		// Add a provider first
		$this->generator->add_provider( $this->create_post_content_provider() );
		$initial_count = count( $this->generator->get_providers() );

		// Remove posts provider
		$this->generator->remove_provider_by_type( 'posts' );

		$new_count = count( $this->generator->get_providers() );
		$this->assertEquals( $initial_count - 1, $new_count );
	}

	/**
	 * Test generator works with no providers.
	 */
	public function test_generator_works_with_no_providers(): void {
		$sitemap_content = $this->generator->generate_sitemap_for_date( '2024-01-15 00:00:00' );
		$this->assertTrue( $sitemap_content->is_empty() );
	}
}
