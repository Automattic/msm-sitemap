<?php
/**
 * PSR-4 Autoloader for MSM Sitemap Plugin
 *
 * @package Automattic\MSM_Sitemap
 */

declare( strict_types=1 );

/**
 * Simple PSR-4 autoloader for the MSM Sitemap plugin.
 * 
 * Handles autoloading for classes in the Automattic\MSM_Sitemap namespace
 * and legacy classes in the root plugin namespace.
 */
class MSM_Sitemap_Autoloader {

	/**
	 * PSR-4 namespace to directory mappings.
	 *
	 * @var array<string, string>
	 */
	private static array $namespaces = array();

	/**
	 * Legacy class name to file mappings.
	 *
	 * @var array<string, string>
	 */
	private static array $legacy_classes = array();

	/**
	 * Whether the autoloader is registered.
	 *
	 * @var bool
	 */
	private static bool $registered = false;

	/**
	 * Register the autoloader.
	 *
	 * @param string $plugin_dir The plugin directory path.
	 */
	public static function register( string $plugin_dir ): void {
		if ( self::$registered ) {
			return;
		}

		// Register PSR-4 namespaces
		self::$namespaces = array(
			'Automattic\\MSM_Sitemap\\' => $plugin_dir . '/includes/',
		);

		// Register legacy classes that don't use namespaces
		// Note: Most classes now use PSR-4 namespaces and are autoloaded automatically
		self::$legacy_classes = array(
			// Add any remaining legacy classes here that don't use namespaces
		);

		// Register the autoloader
		spl_autoload_register( array( __CLASS__, 'autoload' ), true, true );
		
		// Register class aliases for backward compatibility
		self::register_class_aliases();
		
		self::$registered = true;
	}

	/**
	 * Unregister the autoloader.
	 */
	public static function unregister(): void {
		if ( ! self::$registered ) {
			return;
		}

		spl_autoload_unregister( array( __CLASS__, 'autoload' ) );
		self::$registered = false;
	}

	/**
	 * Autoload a class.
	 *
	 * @param string $class_name The fully qualified class name.
	 */
	public static function autoload( string $class_name ): void {
		// Handle legacy classes first
		if ( isset( self::$legacy_classes[ $class_name ] ) ) {
			$file = self::$legacy_classes[ $class_name ];
			if ( is_readable( $file ) ) {
				require_once $file;
			}
			return;
		}

		// Handle PSR-4 namespaced classes
		foreach ( self::$namespaces as $namespace => $base_dir ) {
			if ( 0 === strpos( $class_name, $namespace ) ) {
				$relative_class = substr( $class_name, strlen( $namespace ) );
				$file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';
				
				if ( is_readable( $file ) ) {
					require_once $file;
					return;
				}
			}
		}
	}

	/**
	 * Check if a class can be autoloaded.
	 *
	 * @param string $class_name The fully qualified class name.
	 * @return bool True if the class can be autoloaded, false otherwise.
	 */
	public static function can_autoload( string $class_name ): bool {
		// Check legacy classes
		if ( isset( self::$legacy_classes[ $class_name ] ) ) {
			return is_readable( self::$legacy_classes[ $class_name ] );
		}

		// Check PSR-4 classes
		foreach ( self::$namespaces as $namespace => $base_dir ) {
			if ( 0 === strpos( $class_name, $namespace ) ) {
				$relative_class = substr( $class_name, strlen( $namespace ) );
				$file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';
				return is_readable( $file );
			}
		}

		return false;
	}

	/**
	 * Get all registered namespaces.
	 *
	 * @return array<string, string> Namespace to directory mappings.
	 */
	public static function get_namespaces(): array {
		return self::$namespaces;
	}

	/**
	 * Get all registered legacy classes.
	 *
	 * @return array<string, string> Class name to file mappings.
	 */
	public static function get_legacy_classes(): array {
		return self::$legacy_classes;
	}

	/**
	 * Register class aliases for backward compatibility.
	 * 
	 * This allows legacy code to continue using class names without namespaces
	 * while the actual classes use PSR-4 namespaces.
	 */
	private static function register_class_aliases(): void {
		$aliases = array(
			// Legacy class name => Fully qualified namespaced class
			'Site'              => 'Automattic\\MSM_Sitemap\\Domain\\ValueObjects\\Site',
			'CoreIntegration'   => 'Automattic\\MSM_Sitemap\\Infrastructure\\WordPress\\CoreIntegration',
			'StylesheetManager' => 'Automattic\\MSM_Sitemap\\Infrastructure\\WordPress\\StylesheetManager',
			'Permalinks'        => 'Automattic\\MSM_Sitemap\\Infrastructure\\WordPress\\Permalinks',
			'UI'                => 'Automattic\\MSM_Sitemap\\Admin\\UI',
			'Notifications'     => 'Automattic\\MSM_Sitemap\\Admin\\Notifications',
			'ActionHandlers'    => 'Automattic\\MSM_Sitemap\\Admin\\ActionHandlers',
		);

		foreach ( $aliases as $alias => $class ) {
			if ( ! class_exists( $alias, false ) && class_exists( $class, true ) ) {
				class_alias( $class, $alias );
			}
		}
	}
}
