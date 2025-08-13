<?php
/**
 * SitemapGenerationService Test
 *
 * @package Automattic\MSM_Sitemap\Tests\Application\Services
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Application\Services;

use Automattic\MSM_Sitemap\Application\Services\SitemapGenerationService;
use Automattic\MSM_Sitemap\Application\Services\SitemapQueryService;
use Automattic\MSM_Sitemap\Application\DTOs\SitemapOperationResult;
use Automattic\MSM_Sitemap\Domain\Contracts\SitemapRepositoryInterface;
use Automattic\MSM_Sitemap\Application\Services\SitemapGenerator;
use Automattic\MSM_Sitemap\Infrastructure\Repositories\SitemapPostRepository;
use Automattic\MSM_Sitemap\Tests\TestCase;

/**
 * Test SitemapGenerationService
 */
class SitemapGenerationServiceTest extends TestCase {

	/**
	 * Test creating a sitemap for a specific date.
	 */
	public function test_create_for_date_success(): void {
		$generator = $this->createMock( SitemapGenerator::class );
		$repository = $this->createMock( SitemapRepositoryInterface::class );
		$query_service = new SitemapQueryService();

		// Mock generator to return content
		$content = $this->createMock( \Automattic\MSM_Sitemap\Domain\ValueObjects\SitemapContent::class );
		$content->method( 'count' )->willReturn( 5 );
		$generator->method( 'generate_sitemap_for_date' )->willReturn( $content );

		// Mock repository
		$repository->method( 'find_by_date' )->willReturn( null ); // No existing sitemap
		$repository->method( 'save' )->willReturn( true ); // Return success

		$service = new SitemapGenerationService( $generator, $repository, $query_service );

		$result = $service->create_for_date( '2024-01-01' );

		$this->assertTrue( $result->is_success() );
		$this->assertEquals( 'Sitemap created for 2024-01-01 with 5 URLs.', $result->get_message() );
		$this->assertEquals( 1, $result->get_count() );
	}

	/**
	 * Test creating a sitemap when one already exists.
	 */
	public function test_create_for_date_already_exists(): void {
		$generator = $this->createMock( SitemapGenerator::class );
		$repository = $this->createMock( SitemapRepositoryInterface::class );
		$query_service = new SitemapQueryService();

		// Mock repository to return existing sitemap
		$repository->method( 'find_by_date' )->willReturn( 123 );

		$service = new SitemapGenerationService( $generator, $repository, $query_service );

		$result = $service->create_for_date( '2024-01-01' );

		$this->assertFalse( $result->is_success() );
		$this->assertEquals( 'sitemap_exists', $result->get_error_code() );
	}

	/**
	 * Test creating a sitemap when no content is found.
	 */
	public function test_create_for_date_no_content(): void {
		$generator = $this->createMock( SitemapGenerator::class );
		$repository = $this->createMock( SitemapRepositoryInterface::class );
		$query_service = new SitemapQueryService();

		// Mock generator to return empty content
		$content = $this->createMock( \Automattic\MSM_Sitemap\Domain\ValueObjects\SitemapContent::class );
		$content->method( 'count' )->willReturn( 0 );
		$generator->method( 'generate_sitemap_for_date' )->willReturn( $content );

		// Mock repository
		$repository->method( 'find_by_date' )->willReturn( null );
		$repository->method( 'delete_by_date' )->willReturn( true );

		$service = new SitemapGenerationService( $generator, $repository, $query_service );

		$result = $service->create_for_date( '2024-01-01' );

		$this->assertFalse( $result->is_success() );
		$this->assertEquals( 'no_content', $result->get_error_code() );
	}

	/**
	 * Test generating sitemaps for date queries.
	 */
	public function test_generate_for_date_queries_success(): void {
		$generator = $this->createMock( SitemapGenerator::class );
		$repository = $this->createMock( SitemapRepositoryInterface::class );
		$query_service = $this->createMock( SitemapQueryService::class );

		// Mock generator to return content
		$content = $this->createMock( \Automattic\MSM_Sitemap\Domain\ValueObjects\SitemapContent::class );
		$content->method( 'count' )->willReturn( 3 );
		$generator->method( 'generate_sitemap_for_date' )->willReturn( $content );

		// Mock repository
		$repository->method( 'find_by_date' )->willReturn( null );
		$repository->method( 'save' )->willReturn( true );

		// Mock query service to return dates
		$query_service->method( 'expand_date_queries_with_posts' )->willReturn( array( '2024-01-01', '2024-01-02' ) );

		$service = new SitemapGenerationService( $generator, $repository, $query_service );

		$date_queries = array(
			array( 'year' => 2024, 'month' => 1, 'day' => 1 ),
			array( 'year' => 2024, 'month' => 1, 'day' => 2 ),
		);

		$result = $service->generate_for_date_queries( $date_queries );

		$this->assertTrue( $result->is_success() );
		$this->assertEquals( 2, $result->get_count() );
	}

	/**
	 * Test generating sitemaps with empty queries.
	 */
	public function test_generate_for_date_queries_empty(): void {
		$generator = $this->createMock( SitemapGenerator::class );
		$repository = $this->createMock( SitemapRepositoryInterface::class );
		$query_service = new SitemapQueryService();

		$service = new SitemapGenerationService( $generator, $repository, $query_service );

		$result = $service->generate_for_date_queries( array() );

		$this->assertFalse( $result->is_success() );
		$this->assertEquals( 'no_queries', $result->get_error_code() );
	}
}
