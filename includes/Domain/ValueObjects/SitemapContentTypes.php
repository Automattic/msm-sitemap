<?php
/**
 * Sitemap Content Types Collection
 *
 * @package Automattic\MSM_Sitemap\Domain\ValueObjects
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Domain\ValueObjects;

use Automattic\MSM_Sitemap\Domain\Contracts\ContentProviderInterface;
use InvalidArgumentException;

/**
 * Sitemap Content Types Collection
 *
 * Manages a collection of content providers for sitemaps.
 * Acts as a registry and collection for all available content providers.
 */
final class SitemapContentTypes implements \Countable {

	/**
	 * Registered content providers.
	 *
	 * @var array<string, ContentProviderInterface>
	 */
	private array $providers = array();

	/**
	 * Register a content provider.
	 *
	 * @param ContentProviderInterface $provider The content provider to register.
	 * @throws InvalidArgumentException If a provider with the same content type is already registered.
	 */
	public function register( ContentProviderInterface $provider ): void {
		$content_type = $provider->get_content_type();
		
		if ( $this->is_registered( $content_type ) ) {
			throw new InvalidArgumentException(
				sprintf(
					/* translators: %s is the content type. */
					__( 'Content provider for "%s" is already registered', 'msm-sitemap' ),
					$content_type
				)
			);
		}

		$this->providers[ $content_type ] = $provider;
	}

	/**
	 * Unregister a content provider.
	 *
	 * @param string $content_type The content type to unregister.
	 * @return bool True if unregistered, false if not found.
	 */
	public function unregister( string $content_type ): bool {
		if ( ! $this->is_registered( $content_type ) ) {
			return false;
		}

		unset( $this->providers[ $content_type ] );
		return true;
	}

	/**
	 * Check if a content provider is registered.
	 *
	 * @param string $content_type The content type to check.
	 * @return bool True if registered, false otherwise.
	 */
	public function is_registered( string $content_type ): bool {
		return isset( $this->providers[ $content_type ] );
	}

	/**
	 * Get a registered content provider.
	 *
	 * @param string $content_type The content type to get.
	 * @return ContentProviderInterface|null The content provider or null if not found.
	 */
	public function get( string $content_type ): ?ContentProviderInterface {
		return $this->providers[ $content_type ] ?? null;
	}

	/**
	 * Get all registered content providers.
	 *
	 * @return array<ContentProviderInterface> Array of registered content providers.
	 */
	public function get_all(): array {
		return array_values( $this->providers );
	}

	/**
	 * Get all registered content types.
	 *
	 * @return array<string> Array of registered content types.
	 */
	public function get_all_types(): array {
		return array_keys( $this->providers );
	}

	/**
	 * Get the count of registered content providers.
	 *
	 * @return int The number of registered content providers.
	 */
	public function count(): int {
		return count( $this->providers );
	}

	/**
	 * Check if the collection is empty.
	 *
	 * @return bool True if empty, false otherwise.
	 */
	public function is_empty(): bool {
		return empty( $this->providers );
	}

	/**
	 * Clear all registered content providers.
	 */
	public function clear(): void {
		$this->providers = array();
	}

	/**
	 * Check if this collection equals another.
	 *
	 * @param SitemapContentTypes $other The other collection to compare.
	 * @return bool True if equal, false otherwise.
	 */
	public function equals( SitemapContentTypes $other ): bool {
		if ( $this->count() !== $other->count() ) {
			return false;
		}

		foreach ( $this->providers as $content_type => $provider ) {
			$other_provider = $other->get( $content_type );
			if ( ! $other_provider || ! $this->providers_equal( $provider, $other_provider ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check if two providers are equal.
	 *
	 * @param ContentProviderInterface $provider1 First provider.
	 * @param ContentProviderInterface $provider2 Second provider.
	 * @return bool True if equal, false otherwise.
	 */
	private function providers_equal( ContentProviderInterface $provider1, ContentProviderInterface $provider2 ): bool {
		return $provider1->get_content_type() === $provider2->get_content_type() &&
			   $provider1->get_display_name() === $provider2->get_display_name() &&
			   $provider1->get_description() === $provider2->get_description();
	}
}
