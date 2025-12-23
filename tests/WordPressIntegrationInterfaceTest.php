<?php
/**
 * WordPressIntegrationInterfaceTest.php
 *
 * @package Automattic\MSM_Sitemap\Tests
 */

declare(strict_types=1);

namespace Automattic\MSM_Sitemap\Tests;

use Automattic\MSM_Sitemap\Domain\Contracts\WordPressIntegrationInterface;
use Automattic\MSM_Sitemap\Infrastructure\HTTP\XslRequestHandler;
use Automattic\MSM_Sitemap\Infrastructure\WordPress\Permalinks;
use Automattic\MSM_Sitemap\Infrastructure\WordPress\PluginLinks;
use Automattic\MSM_Sitemap\Infrastructure\CLI\CLISetup;
use Automattic\MSM_Sitemap\Infrastructure\WordPress\CoreIntegration;
use Automattic\MSM_Sitemap\Infrastructure\REST\RESTSetup;
use Automattic\MSM_Sitemap\Infrastructure\WordPress\PostTypeRegistration;
use Automattic\MSM_Sitemap\Infrastructure\WordPress\Admin\UI;
use Automattic\MSM_Sitemap\Infrastructure\Cron\AutomaticUpdateCronHandler;
use Automattic\MSM_Sitemap\Infrastructure\Cron\BackgroundGenerationCronHandler;
use Automattic\MSM_Sitemap\Tests\TestCase;

/**
 * Test WordPressIntegrationInterface implementation.
 */
class WordPressIntegrationInterfaceTest extends TestCase {

	/**
	 * Test that classes implementing WordPressIntegrationInterface have setup method.
	 */
	public function test_classes_implement_interface(): void {
		$classes = array(
			XslRequestHandler::class,
			Permalinks::class,
			PluginLinks::class,
			CLISetup::class,
			CoreIntegration::class,
			RESTSetup::class,
			PostTypeRegistration::class,
			UI::class,
			AutomaticUpdateCronHandler::class,
			BackgroundGenerationCronHandler::class,
		);

		foreach ( $classes as $class ) {
			$this->assertTrue(
				is_subclass_of( $class, WordPressIntegrationInterface::class ),
				sprintf( 'Class %s should implement WordPressIntegrationInterface', $class )
			);
		}
	}

	/**
	 * Test that setup method can be called on interface implementations.
	 */
	public function test_setup_method_can_be_called(): void {
		$container = $this->container;

		$integrations = array(
			$container->get( XslRequestHandler::class ),
			$container->get( Permalinks::class ),
			$container->get( PluginLinks::class ),
			$container->get( CLISetup::class ),
			$container->get( CoreIntegration::class ),
			$container->get( RESTSetup::class ),
			$container->get( PostTypeRegistration::class ),
			$container->get( UI::class ),
			$container->get( AutomaticUpdateCronHandler::class ),
			$container->get( BackgroundGenerationCronHandler::class ),
		);

		foreach ( $integrations as $integration ) {
			$this->assertInstanceOf( WordPressIntegrationInterface::class, $integration );
			
			// Should not throw an exception
			$integration->register_hooks();
		}
	}
}
