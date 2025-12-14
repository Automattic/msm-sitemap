<?php
/**
 * Unit tests for the UrlEntry value object.
 *
 * @package Automattic\MSM_Sitemap\Tests\Unit
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Unit;

use Automattic\MSM_Sitemap\Domain\ValueObjects\UrlEntry;
use Brain\Monkey\Functions;
use InvalidArgumentException;

/**
 * Test case for UrlEntry.
 */
class UrlEntryTest extends TestCase {

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();

		// Stub the translation function to return the first argument.
		Functions\stubs( [ '__' => null ] );
	}

	/**
	 * Test creating a UrlEntry with just a URL.
	 */
	public function testCreateWithLocOnly(): void {
		$entry = new UrlEntry( 'https://example.com/page' );

		$this->assertSame( 'https://example.com/page', $entry->loc() );
		$this->assertNull( $entry->lastmod() );
		$this->assertNull( $entry->changefreq() );
		$this->assertNull( $entry->priority() );
	}

	/**
	 * Test creating a UrlEntry with all parameters.
	 */
	public function testCreateWithAllParameters(): void {
		$entry = new UrlEntry(
			'https://example.com/page',
			'2024-06-15',
			'weekly',
			0.8
		);

		$this->assertSame( 'https://example.com/page', $entry->loc() );
		$this->assertSame( '2024-06-15', $entry->lastmod() );
		$this->assertSame( 'weekly', $entry->changefreq() );
		$this->assertSame( 0.8, $entry->priority() );
	}

	/**
	 * Test that empty URL throws exception.
	 */
	public function testEmptyUrlThrowsException(): void {
		$this->expectException( InvalidArgumentException::class );

		new UrlEntry( '' );
	}

	/**
	 * Test that invalid URL format throws exception.
	 */
	public function testInvalidUrlFormatThrowsException(): void {
		$this->expectException( InvalidArgumentException::class );

		new UrlEntry( 'not-a-valid-url' );
	}

	/**
	 * Test that URL exceeding max length throws exception.
	 */
	public function testUrlExceedingMaxLengthThrowsException(): void {
		$this->expectException( InvalidArgumentException::class );

		// Create a URL longer than 2048 characters.
		$long_url = 'https://example.com/' . str_repeat( 'a', 2050 );
		new UrlEntry( $long_url );
	}

	/**
	 * Test valid lastmod date formats.
	 *
	 * @dataProvider validLastmodProvider
	 */
	public function testValidLastmodFormats( string $lastmod ): void {
		$entry = new UrlEntry( 'https://example.com/page', $lastmod );

		$this->assertSame( $lastmod, $entry->lastmod() );
	}

	/**
	 * Data provider for valid lastmod dates.
	 *
	 * @return array<string, array<string>>
	 */
	public static function validLastmodProvider(): array {
		return [
			'date only'     => [ '2024-06-15' ],
			'with timezone' => [ '2024-06-15T14:30:00+00:00' ],
		];
	}

	/**
	 * Test invalid lastmod format throws exception.
	 */
	public function testInvalidLastmodFormatThrowsException(): void {
		$this->expectException( InvalidArgumentException::class );

		new UrlEntry( 'https://example.com/page', '15-06-2024' );
	}

	/**
	 * Test invalid date in lastmod throws exception.
	 */
	public function testInvalidDateInLastmodThrowsException(): void {
		$this->expectException( InvalidArgumentException::class );

		new UrlEntry( 'https://example.com/page', '2024-02-30' );
	}

	/**
	 * Test valid changefreq values.
	 *
	 * @dataProvider validChangefreqProvider
	 */
	public function testValidChangefreqValues( string $changefreq ): void {
		$entry = new UrlEntry( 'https://example.com/page', null, $changefreq );

		$this->assertSame( $changefreq, $entry->changefreq() );
	}

	/**
	 * Data provider for valid changefreq values.
	 *
	 * @return array<string, array<string>>
	 */
	public static function validChangefreqProvider(): array {
		return [
			'always'  => [ 'always' ],
			'hourly'  => [ 'hourly' ],
			'daily'   => [ 'daily' ],
			'weekly'  => [ 'weekly' ],
			'monthly' => [ 'monthly' ],
			'yearly'  => [ 'yearly' ],
			'never'   => [ 'never' ],
		];
	}

	/**
	 * Test invalid changefreq value throws exception.
	 */
	public function testInvalidChangefreqThrowsException(): void {
		$this->expectException( InvalidArgumentException::class );

		new UrlEntry( 'https://example.com/page', null, 'invalid' );
	}

	/**
	 * Test valid priority values.
	 *
	 * @dataProvider validPriorityProvider
	 */
	public function testValidPriorityValues( float $priority ): void {
		$entry = new UrlEntry( 'https://example.com/page', null, null, $priority );

		$this->assertSame( $priority, $entry->priority() );
	}

	/**
	 * Data provider for valid priority values.
	 *
	 * @return array<string, array<float>>
	 */
	public static function validPriorityProvider(): array {
		return [
			'minimum' => [ 0.0 ],
			'middle'  => [ 0.5 ],
			'maximum' => [ 1.0 ],
		];
	}

	/**
	 * Test priority below minimum throws exception.
	 */
	public function testPriorityBelowMinimumThrowsException(): void {
		$this->expectException( InvalidArgumentException::class );

		new UrlEntry( 'https://example.com/page', null, null, -0.1 );
	}

	/**
	 * Test priority above maximum throws exception.
	 */
	public function testPriorityAboveMaximumThrowsException(): void {
		$this->expectException( InvalidArgumentException::class );

		new UrlEntry( 'https://example.com/page', null, null, 1.1 );
	}

	/**
	 * Test to_array with minimal data.
	 */
	public function testToArrayMinimal(): void {
		$entry = new UrlEntry( 'https://example.com/page' );

		$expected = [ 'loc' => 'https://example.com/page' ];

		$this->assertSame( $expected, $entry->to_array() );
	}

	/**
	 * Test to_array with all data.
	 */
	public function testToArrayFull(): void {
		$entry = new UrlEntry(
			'https://example.com/page',
			'2024-06-15',
			'weekly',
			0.8
		);

		$expected = [
			'loc'        => 'https://example.com/page',
			'lastmod'    => '2024-06-15',
			'changefreq' => 'weekly',
			'priority'   => 0.8,
		];

		$this->assertSame( $expected, $entry->to_array() );
	}

	/**
	 * Test equals method returns true for identical entries.
	 */
	public function testEqualsReturnsTrueForIdentical(): void {
		$entry1 = new UrlEntry( 'https://example.com/page', '2024-06-15', 'weekly', 0.8 );
		$entry2 = new UrlEntry( 'https://example.com/page', '2024-06-15', 'weekly', 0.8 );

		$this->assertTrue( $entry1->equals( $entry2 ) );
	}

	/**
	 * Test equals method returns false for different entries.
	 */
	public function testEqualsReturnsFalseForDifferent(): void {
		$entry1 = new UrlEntry( 'https://example.com/page1' );
		$entry2 = new UrlEntry( 'https://example.com/page2' );

		$this->assertFalse( $entry1->equals( $entry2 ) );
	}
}
