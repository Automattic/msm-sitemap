<?php
/**
 * Custom Eris generator for valid URLs.
 *
 * Generates syntactically valid URLs suitable for use with UrlEntry and ImageEntry
 * value objects. URLs are always well-formed HTTPS URLs within the 2048 character limit.
 *
 * @package Automattic\MSM_Sitemap\Tests\Generators
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Tests\Generators;

use Eris\Generator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Random\RandomRange;

/**
 * Generates valid HTTPS URLs for property-based testing.
 */
class ValidUrlGenerator implements Generator {

	/**
	 * Example path segments to build URLs from.
	 *
	 * @var array<string>
	 */
	private const PATH_SEGMENTS = array(
		'page',
		'post',
		'article',
		'category',
		'tag',
		'archive',
		'about',
		'contact',
		'products',
		'services',
		'blog',
		'news',
		'help',
		'faq',
		'docs',
		'api',
		'users',
		'settings',
	);

	/**
	 * Example domains to build URLs from.
	 *
	 * @var array<string>
	 */
	private const DOMAINS = array(
		'example.com',
		'test.org',
		'sample.net',
		'demo.io',
		'mysite.com',
	);

	/**
	 * Generate a valid URL.
	 *
	 * @param int         $_size The generation size (controls complexity).
	 * @param RandomRange $rand  The random number generator.
	 * @return GeneratedValueSingle The generated URL value.
	 */
	public function __invoke( $_size, RandomRange $rand ) {
		$domain_index = $rand->rand( 0, count( self::DOMAINS ) - 1 );
		$domain       = self::DOMAINS[ $domain_index ];

		$num_segments = $rand->rand( 1, min( 4, max( 1, (int) ( $_size / 50 ) + 1 ) ) );
		$path_parts   = array();
		for ( $i = 0; $i < $num_segments; $i++ ) {
			$segment_index = $rand->rand( 0, count( self::PATH_SEGMENTS ) - 1 );
			$path_parts[]  = self::PATH_SEGMENTS[ $segment_index ];
			// Sometimes add a numeric suffix.
			if ( $rand->rand( 0, 2 ) === 0 ) {
				$path_parts[ count( $path_parts ) - 1 ] .= $rand->rand( 1, 9999 );
			}
		}

		$url = 'https://' . $domain . '/' . implode( '/', $path_parts );

		return GeneratedValueSingle::fromJustValue( $url, 'valid-url' );
	}

	/**
	 * Shrink a generated URL towards a simpler form.
	 *
	 * @param GeneratedValue $element The value to shrink.
	 * @return GeneratedValueSingle The shrunk value.
	 */
	public function shrink( GeneratedValue $element ) {
		return GeneratedValueSingle::fromJustValue(
			'https://example.com/page',
			'valid-url'
		);
	}
}
