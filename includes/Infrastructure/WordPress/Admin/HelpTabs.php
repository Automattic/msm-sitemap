<?php
/**
 * Admin Help Tabs
 *
 * Provides contextual help for the MSM Sitemap admin page.
 *
 * @package MSM_Sitemap
 */

namespace Automattic\MSM_Sitemap\Infrastructure\WordPress\Admin;

/**
 * Handles contextual help tabs for the admin page.
 */
class HelpTabs {

	/**
	 * Set up help tab hooks.
	 *
	 * This should be called after the admin menu is registered.
	 *
	 * @param string $page_hook The admin page hook suffix.
	 */
	public static function setup( string $page_hook ): void {
		add_action( 'load-' . $page_hook, array( __CLASS__, 'add_help_tabs' ) );
	}

	/**
	 * Add help tabs to the admin page.
	 */
	public static function add_help_tabs(): void {
		$screen = get_current_screen();

		if ( ! $screen ) {
			return;
		}

		// Overview tab.
		$screen->add_help_tab(
			array(
				'id'      => 'msm-sitemap-overview',
				'title'   => __( 'Overview', 'msm-sitemap' ),
				'content' => self::get_overview_content(),
			)
		);

		// Automatic Updates tab.
		$screen->add_help_tab(
			array(
				'id'      => 'msm-sitemap-auto-updates',
				'title'   => __( 'Automatic Updates', 'msm-sitemap' ),
				'content' => self::get_auto_updates_content(),
			)
		);

		// Content Providers tab.
		$screen->add_help_tab(
			array(
				'id'      => 'msm-sitemap-content-providers',
				'title'   => __( 'Content Providers', 'msm-sitemap' ),
				'content' => self::get_content_providers_content(),
			)
		);

		// Generating Sitemaps tab.
		$screen->add_help_tab(
			array(
				'id'      => 'msm-sitemap-generation',
				'title'   => __( 'Generating Sitemaps', 'msm-sitemap' ),
				'content' => self::get_generation_content(),
			)
		);

		// Statistics tab.
		$screen->add_help_tab(
			array(
				'id'      => 'msm-sitemap-statistics',
				'title'   => __( 'Statistics', 'msm-sitemap' ),
				'content' => self::get_statistics_content(),
			)
		);

		// WP-CLI tab.
		$screen->add_help_tab(
			array(
				'id'      => 'msm-sitemap-cli',
				'title'   => __( 'WP-CLI Commands', 'msm-sitemap' ),
				'content' => self::get_cli_content(),
			)
		);

		// Troubleshooting tab.
		$screen->add_help_tab(
			array(
				'id'      => 'msm-sitemap-troubleshooting',
				'title'   => __( 'Troubleshooting', 'msm-sitemap' ),
				'content' => self::get_troubleshooting_content(),
			)
		);

		// Help sidebar.
		$screen->set_help_sidebar( self::get_help_sidebar() );
	}

	/**
	 * Get the overview help content.
	 *
	 * @return string
	 */
	private static function get_overview_content(): string {
		$content  = '<h3>' . esc_html__( 'MSM Sitemap', 'msm-sitemap' ) . '</h3>';
		$content .= '<p>' . esc_html__( 'MSM Sitemap generates XML sitemaps for your WordPress site to help search engines discover and index your content more efficiently.', 'msm-sitemap' ) . '</p>';
		$content .= '<p>' . esc_html__( 'Key features:', 'msm-sitemap' ) . '</p>';
		$content .= '<ul>';
		$content .= '<li>' . esc_html__( 'Automatic sitemap generation based on your content', 'msm-sitemap' ) . '</li>';
		$content .= '<li>' . esc_html__( 'Post sitemaps organized by date (one per day with content)', 'msm-sitemap' ) . '</li>';
		$content .= '<li>' . esc_html__( 'Taxonomy sitemaps for categories, tags, and custom taxonomies', 'msm-sitemap' ) . '</li>';
		$content .= '<li>' . esc_html__( 'Author sitemaps for user archive pages', 'msm-sitemap' ) . '</li>';
		$content .= '<li>' . esc_html__( 'Page sitemaps for hierarchical content', 'msm-sitemap' ) . '</li>';
		$content .= '<li>' . esc_html__( 'Image sitemaps with support for featured and content images', 'msm-sitemap' ) . '</li>';
		$content .= '<li>' . esc_html__( 'Sitemap index combining all sitemap types', 'msm-sitemap' ) . '</li>';
		$content .= '<li>' . esc_html__( 'Automatic updates when content changes', 'msm-sitemap' ) . '</li>';
		$content .= '<li>' . esc_html__( 'WP-CLI commands for advanced management', 'msm-sitemap' ) . '</li>';
		$content .= '</ul>';

		return $content;
	}

	/**
	 * Get the automatic updates help content.
	 *
	 * @return string
	 */
	private static function get_auto_updates_content(): string {
		$content  = '<h3>' . esc_html__( 'Automatic Sitemap Updates', 'msm-sitemap' ) . '</h3>';
		$content .= '<p>' . esc_html__( 'When enabled, MSM Sitemap automatically checks for content changes and updates affected sitemaps.', 'msm-sitemap' ) . '</p>';

		$content .= '<h4>' . esc_html__( 'Status', 'msm-sitemap' ) . '</h4>';
		$content .= '<p>' . esc_html__( 'Shows whether automatic updates are enabled or disabled. Click the button to toggle.', 'msm-sitemap' ) . '</p>';

		$content .= '<h4>' . esc_html__( 'Last Checked vs Last Updated', 'msm-sitemap' ) . '</h4>';
		$content .= '<ul>';
		$content .= '<li><strong>' . esc_html__( 'Last Checked:', 'msm-sitemap' ) . '</strong> ' . esc_html__( 'When the cron job last ran to check for changes', 'msm-sitemap' ) . '</li>';
		$content .= '<li><strong>' . esc_html__( 'Last Updated:', 'msm-sitemap' ) . '</strong> ' . esc_html__( 'When sitemaps were actually regenerated (only happens when content changed)', 'msm-sitemap' ) . '</li>';
		$content .= '</ul>';

		$content .= '<h4>' . esc_html__( 'Frequency', 'msm-sitemap' ) . '</h4>';
		$content .= '<p>' . esc_html__( 'Controls how often the plugin checks for content changes. More frequent checks mean faster sitemap updates but slightly more server load.', 'msm-sitemap' ) . '</p>';
		$content .= '<ul>';
		$content .= '<li>' . esc_html__( 'High-traffic sites: 5-15 minutes recommended', 'msm-sitemap' ) . '</li>';
		$content .= '<li>' . esc_html__( 'Medium-traffic sites: 30-60 minutes recommended', 'msm-sitemap' ) . '</li>';
		$content .= '<li>' . esc_html__( 'Low-traffic sites: 1-3 hours is usually sufficient', 'msm-sitemap' ) . '</li>';
		$content .= '</ul>';

		return $content;
	}

	/**
	 * Get the content providers help content.
	 *
	 * @return string
	 */
	private static function get_content_providers_content(): string {
		$content  = '<h3>' . esc_html__( 'Content Providers', 'msm-sitemap' ) . '</h3>';
		$content .= '<p>' . esc_html__( 'Content providers determine what content is included in your sitemaps. Enable or disable each type based on your needs.', 'msm-sitemap' ) . '</p>';

		$content .= '<h4>' . esc_html__( 'Posts', 'msm-sitemap' ) . '</h4>';
		$content .= '<p>' . esc_html__( 'Select which post types to include. Post sitemaps are organized by date, with one sitemap per day that has published content.', 'msm-sitemap' ) . '</p>';

		$content .= '<h4>' . esc_html__( 'Images', 'msm-sitemap' ) . '</h4>';
		$content .= '<p>' . esc_html__( 'When enabled, image URLs are included in post sitemaps. Options:', 'msm-sitemap' ) . '</p>';
		$content .= '<ul>';
		$content .= '<li><strong>' . esc_html__( 'Featured Images:', 'msm-sitemap' ) . '</strong> ' . esc_html__( 'Include post thumbnails (only for post types that support them)', 'msm-sitemap' ) . '</li>';
		$content .= '<li><strong>' . esc_html__( 'Content Images:', 'msm-sitemap' ) . '</strong> ' . esc_html__( 'Include images embedded in post content', 'msm-sitemap' ) . '</li>';
		$content .= '<li><strong>' . esc_html__( 'Max Images:', 'msm-sitemap' ) . '</strong> ' . esc_html__( 'Limit images per sitemap to control file size', 'msm-sitemap' ) . '</li>';
		$content .= '</ul>';

		$content .= '<h4>' . esc_html__( 'Taxonomies', 'msm-sitemap' ) . '</h4>';
		$content .= '<p>' . esc_html__( 'Include taxonomy archive pages (categories, tags, custom taxonomies). These are dynamically generated and cached.', 'msm-sitemap' ) . '</p>';

		$content .= '<h4>' . esc_html__( 'Authors', 'msm-sitemap' ) . '</h4>';
		$content .= '<p>' . esc_html__( 'Include author archive pages for users who have published posts.', 'msm-sitemap' ) . '</p>';

		$content .= '<h4>' . esc_html__( 'Pages', 'msm-sitemap' ) . '</h4>';
		$content .= '<p>' . esc_html__( 'Include hierarchical page content. Unlike posts, pages are not organized by date.', 'msm-sitemap' ) . '</p>';

		$content .= '<h4>' . esc_html__( 'Cache TTL', 'msm-sitemap' ) . '</h4>';
		$content .= '<p>' . esc_html__( 'Taxonomy, author, and page sitemaps are cached for performance. The cache TTL (time to live) controls how long the cache is valid before regenerating.', 'msm-sitemap' ) . '</p>';

		return $content;
	}

	/**
	 * Get the generation help content.
	 *
	 * @return string
	 */
	private static function get_generation_content(): string {
		$content = '<h3>' . esc_html__( 'Generating Sitemaps', 'msm-sitemap' ) . '</h3>';

		$content .= '<h4>' . esc_html__( 'Missing Sitemaps', 'msm-sitemap' ) . '</h4>';
		$content .= '<p>' . esc_html__( 'Shows dates that have content but no sitemap. Use "Generate Missing Sitemaps" to create sitemaps only for these dates. This is the recommended option for most cases.', 'msm-sitemap' ) . '</p>';

		$content .= '<h4>' . esc_html__( 'Generate All Sitemaps (Force)', 'msm-sitemap' ) . '</h4>';
		$content .= '<p>' . esc_html__( 'Found in the Danger Zone. This regenerates ALL sitemaps, even those that already exist. Use this only if:', 'msm-sitemap' ) . '</p>';
		$content .= '<ul>';
		$content .= '<li>' . esc_html__( 'You suspect existing sitemaps are corrupted or incomplete', 'msm-sitemap' ) . '</li>';
		$content .= '<li>' . esc_html__( 'You changed which post types to include in sitemaps', 'msm-sitemap' ) . '</li>';
		$content .= '<li>' . esc_html__( 'You need to update URL structures across all sitemaps', 'msm-sitemap' ) . '</li>';
		$content .= '</ul>';

		$content .= '<h4>' . esc_html__( 'How Generation Works', 'msm-sitemap' ) . '</h4>';
		$content .= '<p>' . esc_html__( 'Sitemaps are generated via WordPress cron in batches to avoid timeout issues. You can continue using the admin while generation runs in the background.', 'msm-sitemap' ) . '</p>';

		return $content;
	}

	/**
	 * Get the statistics help content.
	 *
	 * @return string
	 */
	private static function get_statistics_content(): string {
		$content  = '<h3>' . esc_html__( 'Sitemap Statistics', 'msm-sitemap' ) . '</h3>';
		$content .= '<p>' . esc_html__( 'The statistics section provides insights into your sitemap health and content coverage.', 'msm-sitemap' ) . '</p>';

		$content .= '<h4>' . esc_html__( 'Content Sitemaps', 'msm-sitemap' ) . '</h4>';
		$content .= '<p>' . esc_html__( 'Shows statistics for dynamically generated sitemaps:', 'msm-sitemap' ) . '</p>';
		$content .= '<ul>';
		$content .= '<li><strong>' . esc_html__( 'Taxonomies:', 'msm-sitemap' ) . '</strong> ' . esc_html__( 'Enabled taxonomies and number of sitemap files', 'msm-sitemap' ) . '</li>';
		$content .= '<li><strong>' . esc_html__( 'Authors:', 'msm-sitemap' ) . '</strong> ' . esc_html__( 'Number of author sitemap files', 'msm-sitemap' ) . '</li>';
		$content .= '<li><strong>' . esc_html__( 'Pages:', 'msm-sitemap' ) . '</strong> ' . esc_html__( 'Number of page sitemap files', 'msm-sitemap' ) . '</li>';
		$content .= '</ul>';

		$content .= '<h4>' . esc_html__( 'Post Sitemaps', 'msm-sitemap' ) . '</h4>';
		$content .= '<p>' . esc_html__( 'Shows statistics for date-based post sitemaps (one per day with content).', 'msm-sitemap' ) . '</p>';

		$content .= '<h4>' . esc_html__( 'Date Range Filter', 'msm-sitemap' ) . '</h4>';
		$content .= '<p>' . esc_html__( 'Filter post sitemap statistics by time period: all time, recent days, specific year, or custom date range.', 'msm-sitemap' ) . '</p>';

		$content .= '<h4>' . esc_html__( 'Post Sitemap Metrics', 'msm-sitemap' ) . '</h4>';
		$content .= '<ul>';
		$content .= '<li><strong>' . esc_html__( 'Health:', 'msm-sitemap' ) . '</strong> ' . esc_html__( 'Total sitemaps, indexed URLs, and success rate', 'msm-sitemap' ) . '</li>';
		$content .= '<li><strong>' . esc_html__( 'Trends:', 'msm-sitemap' ) . '</strong> ' . esc_html__( 'Whether your URL counts are growing, stable, or declining', 'msm-sitemap' ) . '</li>';
		$content .= '<li><strong>' . esc_html__( 'Content Analysis:', 'msm-sitemap' ) . '</strong> ' . esc_html__( 'Breakdown by content type and post type', 'msm-sitemap' ) . '</li>';
		$content .= '<li><strong>' . esc_html__( 'Coverage:', 'msm-sitemap' ) . '</strong> ' . esc_html__( 'Date range covered and any gaps in coverage', 'msm-sitemap' ) . '</li>';
		$content .= '<li><strong>' . esc_html__( 'Storage:', 'msm-sitemap' ) . '</strong> ' . esc_html__( 'Database space used by sitemap data', 'msm-sitemap' ) . '</li>';
		$content .= '</ul>';

		return $content;
	}

	/**
	 * Get the CLI help content.
	 *
	 * @return string
	 */
	private static function get_cli_content(): string {
		$content  = '<h3>' . esc_html__( 'WP-CLI Commands', 'msm-sitemap' ) . '</h3>';
		$content .= '<p>' . esc_html__( 'MSM Sitemap includes WP-CLI commands for advanced sitemap management.', 'msm-sitemap' ) . '</p>';

		$content .= '<h4>' . esc_html__( 'Available Commands', 'msm-sitemap' ) . '</h4>';
		$content .= '<pre style="background: #f5f5f5; padding: 10px; overflow-x: auto;">';
		$content .= esc_html__( '# List all sitemaps', 'msm-sitemap' ) . "\n";
		$content .= 'wp msm-sitemap list' . "\n\n";
		$content .= esc_html__( '# Get a specific sitemap by date or ID', 'msm-sitemap' ) . "\n";
		$content .= 'wp msm-sitemap get 2024-01-15' . "\n";
		$content .= 'wp msm-sitemap get 123' . "\n\n";
		$content .= esc_html__( '# Generate sitemap for a specific date', 'msm-sitemap' ) . "\n";
		$content .= 'wp msm-sitemap generate --date=2024-01-15' . "\n\n";
		$content .= esc_html__( '# Generate all missing sitemaps', 'msm-sitemap' ) . "\n";
		$content .= 'wp msm-sitemap generate --missing' . "\n\n";
		$content .= esc_html__( '# Delete sitemap for a specific date', 'msm-sitemap' ) . "\n";
		$content .= 'wp msm-sitemap delete 2024-01-15' . "\n\n";
		$content .= esc_html__( '# View cron status', 'msm-sitemap' ) . "\n";
		$content .= 'wp msm-sitemap cron status' . "\n\n";
		$content .= esc_html__( '# Enable/disable cron', 'msm-sitemap' ) . "\n";
		$content .= 'wp msm-sitemap cron enable' . "\n";
		$content .= 'wp msm-sitemap cron disable' . "\n";
		$content .= '</pre>';

		$content .= '<p>' . esc_html__( 'For full command documentation:', 'msm-sitemap' ) . ' <code>wp help msm-sitemap</code></p>';

		return $content;
	}

	/**
	 * Get the troubleshooting help content.
	 *
	 * @return string
	 */
	private static function get_troubleshooting_content(): string {
		$content = '<h3>' . esc_html__( 'Troubleshooting', 'msm-sitemap' ) . '</h3>';

		$content .= '<h4>' . esc_html__( 'Sitemaps Not Updating', 'msm-sitemap' ) . '</h4>';
		$content .= '<ul>';
		$content .= '<li>' . esc_html__( 'Check that automatic updates are enabled', 'msm-sitemap' ) . '</li>';
		$content .= '<li>' . esc_html__( 'Verify WordPress cron is running (wp-cron.php)', 'msm-sitemap' ) . '</li>';
		$content .= '<li>' . esc_html__( 'Check "Last Checked" time - if it\'s not updating, cron may be disabled', 'msm-sitemap' ) . '</li>';
		$content .= '<li>' . esc_html__( 'Try manually generating missing sitemaps', 'msm-sitemap' ) . '</li>';
		$content .= '</ul>';

		$content .= '<h4>' . esc_html__( 'Missing Content in Sitemaps', 'msm-sitemap' ) . '</h4>';
		$content .= '<ul>';
		$content .= '<li>' . esc_html__( 'Only published posts are included', 'msm-sitemap' ) . '</li>';
		$content .= '<li>' . esc_html__( 'Check Content Providers settings to ensure the post type is enabled', 'msm-sitemap' ) . '</li>';
		$content .= '<li>' . esc_html__( 'After changing post type settings, regenerate sitemaps to update', 'msm-sitemap' ) . '</li>';
		$content .= '<li>' . esc_html__( 'Check if content has noindex meta tags', 'msm-sitemap' ) . '</li>';
		$content .= '</ul>';

		$content .= '<h4>' . esc_html__( 'Generation Taking Too Long', 'msm-sitemap' ) . '</h4>';
		$content .= '<ul>';
		$content .= '<li>' . esc_html__( 'Large sites may take several hours for full generation', 'msm-sitemap' ) . '</li>';
		$content .= '<li>' . esc_html__( 'Generation runs in background batches to avoid timeouts', 'msm-sitemap' ) . '</li>';
		$content .= '<li>' . esc_html__( 'Use WP-CLI for faster generation: wp msm-sitemap generate --all', 'msm-sitemap' ) . '</li>';
		$content .= '</ul>';

		$content .= '<h4>' . esc_html__( 'Private Site Warning', 'msm-sitemap' ) . '</h4>';
		$content .= '<p>' . esc_html__( 'If your site is set to "Discourage search engines" in Settings > Reading, sitemaps will be disabled. Search engines should not index private sites.', 'msm-sitemap' ) . '</p>';

		return $content;
	}

	/**
	 * Get the help sidebar content.
	 *
	 * @return string
	 */
	private static function get_help_sidebar(): string {
		$content  = '<p><strong>' . esc_html__( 'Resources', 'msm-sitemap' ) . '</strong></p>';
		$content .= '<p><a href="https://github.com/Automattic/msm-sitemap" target="_blank">' . esc_html__( 'GitHub Repository', 'msm-sitemap' ) . '</a></p>';
		$content .= '<p><a href="https://github.com/Automattic/msm-sitemap/issues" target="_blank">' . esc_html__( 'Report an Issue', 'msm-sitemap' ) . '</a></p>';
		$content .= '<p><a href="https://github.com/Automattic/msm-sitemap#readme" target="_blank">' . esc_html__( 'Documentation', 'msm-sitemap' ) . '</a></p>';

		return $content;
	}
}
