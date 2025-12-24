<?php
/**
 * Admin UI rendering class
 *
 * @package MSM_Sitemap
 */

namespace Automattic\MSM_Sitemap\Infrastructure\WordPress\Admin;

use Automattic\MSM_Sitemap\Application\Services\AuthorSitemapService;
use Automattic\MSM_Sitemap\Application\Services\CronManagementService;
use Automattic\MSM_Sitemap\Application\Services\MissingSitemapDetectionService;
use Automattic\MSM_Sitemap\Application\Services\PageSitemapService;
use Automattic\MSM_Sitemap\Application\Services\SitemapStatsService;
use Automattic\MSM_Sitemap\Application\Services\SettingsService;
use Automattic\MSM_Sitemap\Application\Services\TaxonomySitemapService;
use Automattic\MSM_Sitemap\Domain\ValueObjects\Site;
use Automattic\MSM_Sitemap\Domain\Contracts\SitemapRepositoryInterface;
use Automattic\MSM_Sitemap\Domain\Contracts\WordPressIntegrationInterface;
use Automattic\MSM_Sitemap\Infrastructure\WordPress\Admin\ActionHandlers;
use Automattic\MSM_Sitemap\Infrastructure\WordPress\Admin\HelpTabs;
use Automattic\MSM_Sitemap\Infrastructure\WordPress\Admin\Notifications;


/**
 * Handles all admin page UI rendering
 */
class UI implements WordPressIntegrationInterface {

	/**
	 * The cron management service.
	 *
	 * @var CronManagementService
	 */
	private CronManagementService $cron_management;

	/**
	 * The plugin file path.
	 *
	 * @var string
	 */
	private string $plugin_file_path;

	/**
	 * The plugin version.
	 *
	 * @var string
	 */
	private string $plugin_version;

	/**
	 * The missing sitemap detection service.
	 *
	 * @var MissingSitemapDetectionService
	 */
	private MissingSitemapDetectionService $missing_detection_service;

	/**
	 * The sitemap stats service.
	 *
	 * @var SitemapStatsService
	 */
	private SitemapStatsService $stats_service;

	/**
	 * The settings service.
	 *
	 * @var SettingsService
	 */
	private SettingsService $settings_service;

	/**
	 * The sitemap repository.
	 *
	 * @var SitemapRepositoryInterface
	 */
	private SitemapRepositoryInterface $sitemap_repository;

	/**
	 * The action handlers.
	 *
	 * @var ActionHandlers
	 */
	private ActionHandlers $action_handlers;

	/**
	 * The taxonomy sitemap service.
	 *
	 * @var TaxonomySitemapService
	 */
	private TaxonomySitemapService $taxonomy_sitemap_service;

	/**
	 * The author sitemap service.
	 *
	 * @var AuthorSitemapService
	 */
	private AuthorSitemapService $author_sitemap_service;

	/**
	 * The page sitemap service.
	 *
	 * @var PageSitemapService
	 */
	private PageSitemapService $page_sitemap_service;

	/**
	 * Constructor.
	 *
	 * @param CronManagementService          $cron_management           The cron management service.
	 * @param string                         $plugin_file_path          The path to the main plugin file.
	 * @param string                         $plugin_version            The plugin version.
	 * @param MissingSitemapDetectionService $missing_detection_service The missing sitemap detection service.
	 * @param SitemapStatsService            $stats_service             The sitemap stats service.
	 * @param SettingsService                $settings_service          The settings service.
	 * @param SitemapRepositoryInterface     $sitemap_repository        The sitemap repository.
	 * @param ActionHandlers                 $action_handlers           The action handlers.
	 * @param TaxonomySitemapService         $taxonomy_sitemap_service  The taxonomy sitemap service.
	 * @param AuthorSitemapService           $author_sitemap_service    The author sitemap service.
	 * @param PageSitemapService             $page_sitemap_service      The page sitemap service.
	 */
	public function __construct(
		CronManagementService $cron_management,
		string $plugin_file_path,
		string $plugin_version,
		MissingSitemapDetectionService $missing_detection_service,
		SitemapStatsService $stats_service,
		SettingsService $settings_service,
		SitemapRepositoryInterface $sitemap_repository,
		ActionHandlers $action_handlers,
		TaxonomySitemapService $taxonomy_sitemap_service,
		AuthorSitemapService $author_sitemap_service,
		PageSitemapService $page_sitemap_service
	) {
		$this->cron_management           = $cron_management;
		$this->plugin_file_path          = $plugin_file_path;
		$this->plugin_version            = $plugin_version;
		$this->missing_detection_service = $missing_detection_service;
		$this->stats_service             = $stats_service;
		$this->settings_service          = $settings_service;
		$this->sitemap_repository        = $sitemap_repository;
		$this->action_handlers           = $action_handlers;
		$this->taxonomy_sitemap_service  = $taxonomy_sitemap_service;
		$this->author_sitemap_service    = $author_sitemap_service;
		$this->page_sitemap_service      = $page_sitemap_service;
	}

	/**
	 * Register WordPress hooks and filters for admin interface.
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
	}

	/**
	 * Register admin menu for sitemap
	 */
	public function register_admin_menu() {
		$page_hook = add_options_page(
			__( 'MSM Sitemap', 'msm-sitemap' ),
			__( 'Sitemap', 'msm-sitemap' ),
			'manage_options',
			'msm-sitemap',
			array( $this, 'render_options_page' )
		);
		add_action( 'admin_print_scripts-' . $page_hook, array( $this, 'enqueue_admin_assets' ) );

		// Add contextual help tabs.
		HelpTabs::setup( $page_hook );
	}

	/**
	 * Enqueue admin scripts and styles
	 */
	public function enqueue_admin_assets(): void {
		$plugin_url = plugin_dir_url( $this->plugin_file_path );

		wp_enqueue_style(
			'msm-sitemap-admin',
			$plugin_url . 'assets/admin.css',
			array(),
			$this->plugin_version
		);

		wp_enqueue_script(
			'msm-sitemap-admin',
			$plugin_url . 'assets/admin.js',
			array( 'jquery' ),
			$this->plugin_version,
			true
		);

		// Localize script for REST API and UI interactions.
		wp_localize_script(
			'msm-sitemap-admin',
			'msmSitemapAdmin',
			array(
				'restUrl'                => rest_url( 'msm-sitemap/v1/' ),
				'nonce'                  => wp_create_nonce( 'wp_rest' ),
				'generateMissingText'    => __( 'Generate Missing Sitemaps', 'msm-sitemap' ),
				'generatingText'         => __( 'Generating...', 'msm-sitemap' ),
				'schedulingText'         => __( 'Scheduling...', 'msm-sitemap' ),
				'generationSuccessText'  => __( 'Generation completed successfully.', 'msm-sitemap' ),
				'generationErrorText'    => __( 'Failed to start generation. Please try again.', 'msm-sitemap' ),
				'backgroundProgressText' => __( 'Background generation in progress...', 'msm-sitemap' ),
				'confirmResetText'       => __( 'Are you sure you want to reset all sitemap data? This action cannot be undone and will delete all sitemaps, metadata, and statistics.', 'msm-sitemap' ),
				'showText'               => __( 'Show', 'msm-sitemap' ),
				'hideText'               => __( 'Hide', 'msm-sitemap' ),
				'showDetailedStatsText'  => __( 'Show Detailed Statistics', 'msm-sitemap' ),
				'hideDetailedStatsText'  => __( 'Hide Detailed Statistics', 'msm-sitemap' ),
			)
		);
	}

	/**
	 * Render the complete admin options page
	 */
	public function render_options_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'msm-sitemap' ) );
		}

		// Check if sitemaps are enabled
		if ( ! Site::are_sitemaps_enabled() ) {
			$this->render_private_site_message();
			return;
		}

		// Handle form submissions
		$this->handle_form_submissions();

		// Render the page content
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php $this->render_content_providers_section(); ?>
			<?php $this->render_generate_section(); ?>
			<?php $this->render_stats_section(); ?>
			<?php $this->render_dangerous_actions_section(); ?>

		</div>
		<?php
	}

	/**
	 * Handle form submissions and action processing
	 */
	private function handle_form_submissions() {
		if ( ! isset( $_POST['action'] ) ) {
			return;
		}

		check_admin_referer( 'msm-sitemap-action' );

		$action = sanitize_text_field( $_POST['action'] );

		// Route to appropriate action handler
		switch ( $action ) {
			case 'Generate Full Sitemap (All Content)':
				$this->action_handlers->handle_generate_full();
				break;
			case 'Generate Missing Sitemaps':
			case 'Generate Missing Sitemaps (Direct)':
				$this->action_handlers->handle_generate_missing_sitemaps();
				break;
			case 'Generate All Sitemaps (Force)':
				$this->action_handlers->handle_generate_full();
				break;
			case 'Save Content Provider Settings':
				$this->action_handlers->handle_save_content_provider_settings();
				break;
			case 'Stop adding missing sitemaps...':
			case 'Stop full sitemaps generation...':
				$this->action_handlers->handle_halt_generation();
				break;
			// Content validation removed for now
			case 'Reset Sitemap Data':
				$this->action_handlers->handle_reset_data();
				break;
		}
	}

	/**
	 * Render private site message
	 */
	private function render_private_site_message() {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<?php Notifications::show_error( __( 'Oops! Sitemaps are not supported on private sites. Please make your site is public and try again.', 'msm-sitemap' ) ); ?>
		</div>
		<?php
	}

	/**
	 * Render the stats section
	 */
	private function render_stats_section() {
		$sitemap_update_last_run = get_option( 'msm_sitemap_update_last_run' );
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only admin filtering, no state modification.
		$date_range          = isset( $_GET['date_range'] ) ? sanitize_text_field( wp_unslash( $_GET['date_range'] ) ) : 'all';
		$start_date          = isset( $_GET['start_date'] ) ? sanitize_text_field( wp_unslash( $_GET['start_date'] ) ) : '';
		$end_date            = isset( $_GET['end_date'] ) ? sanitize_text_field( wp_unslash( $_GET['end_date'] ) ) : '';
		$comprehensive_stats = $this->stats_service->get_comprehensive_stats( $date_range, $start_date, $end_date );
		$sitemap_count       = $comprehensive_stats['overview']['total_sitemaps'];
		?>
		<h2><?php esc_html_e( 'Sitemap Statistics', 'msm-sitemap' ); ?></h2>

		<h3><?php esc_html_e( 'Post Sitemaps', 'msm-sitemap' ); ?></h3>
		<p class="description"><?php esc_html_e( 'Statistics for date-based post sitemaps (one sitemap per day with published content).', 'msm-sitemap' ); ?></p>

		<!-- Date Range Filter -->
		<div class="date-range-filter">
			<form method="get" style="display: inline;">
				<input type="hidden" name="page" value="msm-sitemap">
				<label for="stats-date-range"><?php esc_html_e( 'Date Range:', 'msm-sitemap' ); ?></label>
				<select id="stats-date-range" name="date_range">
					<option value="all" <?php selected( $date_range, 'all' ); ?>><?php esc_html_e( 'All Time', 'msm-sitemap' ); ?></option>
					<option value="7" <?php selected( $date_range, '7' ); ?>><?php esc_html_e( 'Last 7 Days', 'msm-sitemap' ); ?></option>
					<option value="30" <?php selected( $date_range, '30' ); ?>><?php esc_html_e( 'Last 30 Days', 'msm-sitemap' ); ?></option>
					<option value="90" <?php selected( $date_range, '90' ); ?>><?php esc_html_e( 'Last 90 Days', 'msm-sitemap' ); ?></option>
					<option value="180" <?php selected( $date_range, '180' ); ?>><?php esc_html_e( 'Last 6 Months', 'msm-sitemap' ); ?></option>
					<option value="365" <?php selected( $date_range, '365' ); ?>><?php esc_html_e( 'Last 12 Months', 'msm-sitemap' ); ?></option>
					<?php
					// Get available years from sitemap data
					$all_sitemap_dates = $this->sitemap_repository->get_all_sitemap_dates();
					$years             = array();
					foreach ( $all_sitemap_dates as $date ) {
						$year = substr( $date, 0, 4 );
						if ( ! in_array( $year, $years, true ) ) {
							$years[] = $year;
						}
					}
					rsort( $years ); // Sort years in descending order (newest first)

					// Add year options
					foreach ( $years as $year ) {
						$year_option = 'year_' . $year;
						?>
						<option value="<?php echo esc_attr( $year_option ); ?>" <?php selected( $date_range, $year_option ); ?>><?php echo esc_html( $year ); ?></option>
						<?php
					}
					?>
					<option value="custom" <?php selected( $date_range, 'custom' ); ?>><?php esc_html_e( 'Custom Range', 'msm-sitemap' ); ?></option>
				</select>

				<span id="custom-date-range" style="display: <?php echo 'custom' === $date_range ? 'inline' : 'none'; ?>; margin-left: 10px;">
					<label for="start-date"><?php esc_html_e( 'From:', 'msm-sitemap' ); ?></label>
					<input type="date" id="start-date" name="start_date" value="<?php echo esc_attr( $start_date ); ?>" style="margin-right: 10px;">
					<label for="end-date"><?php esc_html_e( 'To:', 'msm-sitemap' ); ?></label>
					<input type="date" id="end-date" name="end_date" value="<?php echo esc_attr( $end_date ); ?>" style="margin-right: 10px;">
					<button type="submit" class="button button-secondary"><?php esc_html_e( 'Apply', 'msm-sitemap' ); ?></button>
				</span>
			</form>
		</div>
		
		<!-- Detailed Stats Section - Always Visible -->
		<div class="detailed-stats-section">
			<?php $this->render_detailed_stats( $comprehensive_stats ); ?>
		</div>

		<?php $this->render_paginated_sitemaps_stats(); ?>
		<?php
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Render statistics for paginated sitemaps (taxonomies, authors, pages).
	 */
	private function render_paginated_sitemaps_stats(): void {
		$taxonomy_enabled = $this->taxonomy_sitemap_service->is_enabled();
		$author_enabled   = $this->author_sitemap_service->is_enabled();
		$page_enabled     = $this->page_sitemap_service->is_enabled();

		// If none are enabled, don't show this section.
		if ( ! $taxonomy_enabled && ! $author_enabled && ! $page_enabled ) {
			return;
		}
		?>
		<h3><?php esc_html_e( 'Content Sitemaps', 'msm-sitemap' ); ?></h3>
		<p class="description"><?php esc_html_e( 'Dynamically generated sitemaps for taxonomies, authors, and pages.', 'msm-sitemap' ); ?></p>

		<div class="stats-insights insight-grid" style="margin-bottom: 20px;">
			<?php if ( $taxonomy_enabled ) : ?>
			<div class="insight-card">
				<h3><?php esc_html_e( 'Taxonomies', 'msm-sitemap' ); ?></h3>
				<div class="insight-content">
					<?php
					$taxonomy_entries = $this->taxonomy_sitemap_service->get_sitemap_index_entries();
					$enabled_taxonomies = $this->taxonomy_sitemap_service->get_enabled_taxonomies();
					?>
					<div class="insight-item">
						<span class="insight-label"><?php esc_html_e( 'Status:', 'msm-sitemap' ); ?></span>
						<span class="insight-value" style="color: green;">✓ <?php esc_html_e( 'Enabled', 'msm-sitemap' ); ?></span>
					</div>
					<div class="insight-item">
						<span class="insight-label"><?php esc_html_e( 'Taxonomies:', 'msm-sitemap' ); ?></span>
						<span class="insight-value"><?php echo esc_html( count( $enabled_taxonomies ) ); ?></span>
					</div>
					<div class="insight-item stat-descriptor">
						<?php
						$taxonomy_names = array_map(
							function ( $tax ) {
								return $tax->labels->name;
							},
							$enabled_taxonomies
						);
						echo esc_html( implode( ', ', $taxonomy_names ) );
						?>
					</div>
					<div class="insight-item">
						<span class="insight-label"><?php esc_html_e( 'Sitemap Files:', 'msm-sitemap' ); ?></span>
						<span class="insight-value"><?php echo esc_html( count( $taxonomy_entries ) ); ?></span>
					</div>
				</div>
			</div>
			<?php endif; ?>

			<?php if ( $author_enabled ) : ?>
			<div class="insight-card">
				<h3><?php esc_html_e( 'Authors', 'msm-sitemap' ); ?></h3>
				<div class="insight-content">
					<?php $author_entries = $this->author_sitemap_service->get_sitemap_index_entries(); ?>
					<div class="insight-item">
						<span class="insight-label"><?php esc_html_e( 'Status:', 'msm-sitemap' ); ?></span>
						<span class="insight-value" style="color: green;">✓ <?php esc_html_e( 'Enabled', 'msm-sitemap' ); ?></span>
					</div>
					<div class="insight-item">
						<span class="insight-label"><?php esc_html_e( 'Sitemap Files:', 'msm-sitemap' ); ?></span>
						<span class="insight-value"><?php echo esc_html( count( $author_entries ) ); ?></span>
					</div>
				</div>
			</div>
			<?php endif; ?>

			<?php if ( $page_enabled ) : ?>
			<div class="insight-card">
				<h3><?php esc_html_e( 'Pages', 'msm-sitemap' ); ?></h3>
				<div class="insight-content">
					<?php $page_entries = $this->page_sitemap_service->get_sitemap_index_entries(); ?>
					<div class="insight-item">
						<span class="insight-label"><?php esc_html_e( 'Status:', 'msm-sitemap' ); ?></span>
						<span class="insight-value" style="color: green;">✓ <?php esc_html_e( 'Enabled', 'msm-sitemap' ); ?></span>
					</div>
					<div class="insight-item">
						<span class="insight-label"><?php esc_html_e( 'Sitemap Files:', 'msm-sitemap' ); ?></span>
						<span class="insight-value"><?php echo esc_html( count( $page_entries ) ); ?></span>
					</div>
				</div>
			</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render detailed statistics in the expandable section
	 *
	 * @param array $comprehensive_stats The comprehensive statistics data.
	 */
	private function render_detailed_stats( array $comprehensive_stats ) {
		// Extract key stats for easy access
		$overview         = $comprehensive_stats['overview'];
		$performance      = $comprehensive_stats['performance'];
		$coverage         = $comprehensive_stats['coverage'];
		$storage          = $comprehensive_stats['storage'];
		$url_counts       = $comprehensive_stats['url_counts'];
		$health           = $comprehensive_stats['health'];
		$content_analysis = $comprehensive_stats['content_analysis'];
		?>
		
		<!-- Performance and Health Indicators -->
		<div class="stats-insights insight-grid">
			<div class="insight-card">
				<h3><?php esc_html_e( 'Health', 'msm-sitemap' ); ?></h3>
				<div class="insight-content">
					<!-- Key metrics moved to top of Health section -->
					<div class="insight-item">
						<span class="insight-label"><?php esc_html_e( 'Total Sitemaps:', 'msm-sitemap' ); ?></span>
						<span class="insight-value"><?php echo esc_html( number_format( $overview['total_sitemaps'] ) ); ?></span>
					</div>
					<div class="insight-item stat-descriptor">
						<?php esc_html_e( 'Total number of sitemaps generated', 'msm-sitemap' ); ?>
					</div>
					<div class="insight-item">
						<span class="insight-label"><?php esc_html_e( 'Total Indexed URLs:', 'msm-sitemap' ); ?></span>
						<span class="insight-value"><?php echo esc_html( number_format( $overview['total_urls'] ) ); ?></span>
					</div>
					<div class="insight-item stat-descriptor">
						<?php esc_html_e( 'Total number of URLs across all sitemaps', 'msm-sitemap' ); ?>
					</div>
					<div class="insight-item">
						<span class="insight-label"><?php esc_html_e( 'Success Rate:', 'msm-sitemap' ); ?></span>
						<span class="insight-value"><?php echo esc_html( $health['success_rate'] ); ?>%</span>
					</div>
					<div class="insight-item stat-descriptor">
						<?php esc_html_e( 'Percentage of expected sitemaps that were successfully generated', 'msm-sitemap' ); ?>
					</div>
					<?php if ( $health['last_error'] ) : ?>
					<div class="insight-item">
						<span class="insight-label"><?php esc_html_e( 'Last Error:', 'msm-sitemap' ); ?></span>
						<span class="insight-value"><?php echo esc_html( $health['last_error']['human_time'] ); ?></span>
					</div>
					<div class="insight-item stat-descriptor">
						<?php echo esc_html( $health['last_error']['message'] ); ?>
					</div>
					<?php endif; ?>
					<?php if ( $health['average_generation_time'] > 0 ) : ?>
					<div class="insight-item">
						<span class="insight-label"><?php esc_html_e( 'Avg Generation Time:', 'msm-sitemap' ); ?></span>
						<span class="insight-value"><?php echo esc_html( $health['average_generation_time'] ); ?>s</span>
					</div>
					<div class="insight-item stat-descriptor">
						<?php esc_html_e( 'Average time to generate a sitemap in seconds', 'msm-sitemap' ); ?>
					</div>
					<?php endif; ?>
					<div class="insight-item">
						<span class="insight-label"><?php esc_html_e( 'Processing Queue:', 'msm-sitemap' ); ?></span>
						<span class="insight-value"><?php echo esc_html( $health['processing_queue'] ); ?></span>
					</div>
					<div class="insight-item stat-descriptor">
						<?php esc_html_e( 'Number of sitemap generations currently in progress', 'msm-sitemap' ); ?>
					</div>
				</div>
			</div>

			<div class="insight-card">
				<h3><?php esc_html_e( 'Trends', 'msm-sitemap' ); ?></h3>
				<div class="insight-content">
					<div class="insight-item">
						<span class="insight-label"><?php esc_html_e( 'URL Counts per Sitemap:', 'msm-sitemap' ); ?></span>
						<span class="insight-value">
							<?php
							$trend_icons = array(
								'increasing' => '↗️',
								'decreasing' => '↘️',
								'stable'     => '→',
							);
							echo esc_html( $trend_icons[ $performance['recent_trend'] ] ?? '→' );
							echo ' ' . esc_html( ucfirst( $performance['recent_trend'] ) );
							?>
						</span>
					</div>
					<div class="insight-item stat-descriptor">
						<?php esc_html_e( 'Whether the number of URLs per sitemap is growing, declining, or stable', 'msm-sitemap' ); ?>
					</div>
					<div class="insight-item">
						<span class="insight-label"><?php esc_html_e( 'Average URLs per Sitemap:', 'msm-sitemap' ); ?></span>
						<span class="insight-value"><?php echo esc_html( number_format( $performance['recent_average'] ) ); ?> <?php echo esc_html( _n( 'URL', 'URLs', $performance['recent_average'], 'msm-sitemap' ) ); ?></span>
					</div>
					<div class="insight-item stat-descriptor">
						<?php esc_html_e( 'Average URLs per sitemap in the selected date range', 'msm-sitemap' ); ?>
					</div>
					<?php if ( ! empty( $coverage['longest_streak'] ) ) : ?>
					<div class="insight-item">
						<span class="insight-label"><?php esc_html_e( 'Longest Streak:', 'msm-sitemap' ); ?></span>
						<span class="insight-value"><?php echo esc_html( $coverage['longest_streak']['length'] ); ?> <?php echo esc_html( _n( 'day', 'days', $coverage['longest_streak']['length'], 'msm-sitemap' ) ); ?></span>
					</div>
					<div class="insight-item stat-descriptor">
						<?php esc_html_e( 'Longest consecutive run of daily sitemap generation', 'msm-sitemap' ); ?>
					</div>
					<?php endif; ?>
					<?php if ( $url_counts['max_urls'] > 0 ) : ?>
					<div class="insight-item">
						<span class="insight-label"><?php esc_html_e( 'Peak URLs:', 'msm-sitemap' ); ?></span>
						<span class="insight-value"><?php echo esc_html( number_format( $url_counts['max_urls'] ) ); ?></span>
					</div>
					<div class="insight-item stat-descriptor">
						<?php esc_html_e( 'Highest number of URLs ever in a single sitemap', 'msm-sitemap' ); ?>
					</div>
					<?php endif; ?>
				</div>
			</div>

			<div class="insight-card">
				<h3><?php esc_html_e( 'Content Analysis', 'msm-sitemap' ); ?></h3>
				<div class="insight-content">
					<?php if ( ! empty( $content_analysis['url_types'] ) ) : ?>
					<div class="insight-item">
						<span class="insight-label"><?php esc_html_e( 'URL Types Breakdown:', 'msm-sitemap' ); ?></span>
						<span class="insight-value">
							<?php
							$type_counts = array();
							foreach ( $content_analysis['url_types'] as $type => $count ) {
								$type_counts[] = $type . ': ' . number_format( $count );
							}
							echo esc_html( implode( ', ', $type_counts ) );
							?>
						</span>
					</div>
					<div class="insight-item stat-descriptor">
						<?php esc_html_e( 'How many posts, pages, categories, tags, etc.', 'msm-sitemap' ); ?>
					</div>
					<?php endif; ?>
					<?php if ( ! empty( $content_analysis['post_type_counts'] ) ) : ?>
					<div class="insight-item">
						<span class="insight-label"><?php esc_html_e( 'Most Active Content:', 'msm-sitemap' ); ?></span>
						<span class="insight-value">
							<?php
							$post_type_counts = array();
							$count            = 0;
							foreach ( $content_analysis['post_type_counts'] as $post_type => $post_count ) {
								if ( $count++ < 3 ) { // Show top 3
									$post_type_obj      = get_post_type_object( $post_type );
									$post_type_name     = $post_type_obj ? $post_type_obj->labels->singular_name : $post_type;
									$post_type_counts[] = $post_type_name . ': ' . number_format( $post_count );
								}
							}
							echo esc_html( implode( ', ', $post_type_counts ) );
							?>
						</span>
					</div>
					<div class="insight-item stat-descriptor">
						<?php esc_html_e( 'Which post types generate the most URLs', 'msm-sitemap' ); ?>
					</div>
					<?php endif; ?>
					<?php if ( $content_analysis['content_freshness'] > 0 ) : ?>
					<div class="insight-item">
						<span class="insight-label"><?php esc_html_e( 'Content Freshness:', 'msm-sitemap' ); ?></span>
						<span class="insight-value"><?php echo esc_html( $content_analysis['content_freshness'] ); ?> <?php echo esc_html( _n( 'day', 'days', $content_analysis['content_freshness'], 'msm-sitemap' ) ); ?></span>
					</div>
					<div class="insight-item stat-descriptor">
						<?php esc_html_e( 'Average age of content in sitemaps', 'msm-sitemap' ); ?>
					</div>
					<?php endif; ?>
					<?php if ( $content_analysis['duplicate_urls'] > 0 ) : ?>
					<div class="insight-item">
						<span class="insight-label"><?php esc_html_e( 'Duplicate Detection:', 'msm-sitemap' ); ?></span>
						<span class="insight-value"><?php echo esc_html( number_format( $content_analysis['duplicate_urls'] ) ); ?></span>
					</div>
					<div class="insight-item stat-descriptor">
						<?php esc_html_e( 'Number of duplicate URLs found', 'msm-sitemap' ); ?>
					</div>
					<?php endif; ?>
					<?php if ( ! empty( $content_analysis['yearly_breakdown'] ) ) : ?>
					<div class="insight-item">
						<span class="insight-label"><?php esc_html_e( 'Yearly Breakdown:', 'msm-sitemap' ); ?></span>
						<span class="insight-value">
							<?php
							$year_counts = array();
							foreach ( $content_analysis['yearly_breakdown'] as $year => $count ) {
								$year_counts[] = $year . ': ' . $count;
							}
							echo esc_html( implode( ', ', $year_counts ) );
							?>
						</span>
					</div>
					<div class="insight-item stat-descriptor">
						<?php esc_html_e( 'Number of sitemaps generated per year', 'msm-sitemap' ); ?>
					</div>
					<?php endif; ?>
				</div>
			</div>

			<div class="insight-card">
				<h3><?php esc_html_e( 'Coverage', 'msm-sitemap' ); ?></h3>
				<div class="insight-content">
					<div class="insight-item">
						<span class="insight-label"><?php esc_html_e( 'Date Range:', 'msm-sitemap' ); ?></span>
						<span class="insight-value">
							<?php
							$timeline = $comprehensive_stats['timeline'];
							if ( ! empty( $timeline['date_range']['start'] ) && ! empty( $timeline['date_range']['end'] ) ) {
								echo esc_html( $timeline['date_range']['start'] ) . ' - ' . esc_html( $timeline['date_range']['end'] );
							} else {
								esc_html_e( 'No data', 'msm-sitemap' );
							}
							?>
						</span>
					</div>
					<div class="insight-item stat-descriptor">
						<?php esc_html_e( 'Full date range from first to last sitemap', 'msm-sitemap' ); ?>
					</div>
					<div class="insight-item">
						<span class="insight-label"><?php esc_html_e( 'Covered Days:', 'msm-sitemap' ); ?></span>
						<span class="insight-value"><?php echo esc_html( $coverage['covered_days'] ?? 0 ); ?> / <?php echo esc_html( $coverage['total_days'] ?? 0 ); ?></span>
					</div>
					<div class="insight-item stat-descriptor">
						<?php esc_html_e( 'Days with sitemaps vs total days in range', 'msm-sitemap' ); ?>
					</div>
					<?php if ( ! empty( $coverage['gaps'] ) ) : ?>
					<div class="insight-item">
						<span class="insight-label"><?php esc_html_e( 'Gaps:', 'msm-sitemap' ); ?></span>
						<span class="insight-value"><?php echo esc_html( count( $coverage['gaps'] ) ); ?> <?php echo esc_html( _n( 'day', 'days', count( $coverage['gaps'] ), 'msm-sitemap' ) ); ?></span>
					</div>
					<div class="insight-item stat-descriptor">
						<?php esc_html_e( 'Days without sitemap generation (may indicate issues)', 'msm-sitemap' ); ?>
					</div>
					<?php endif; ?>

				</div>
			</div>

			<div class="insight-card">
				<h3><?php esc_html_e( 'Storage', 'msm-sitemap' ); ?></h3>
				<div class="insight-content">
					<div class="insight-item">
						<span class="insight-label"><?php esc_html_e( 'Total Size:', 'msm-sitemap' ); ?></span>
						<span class="insight-value"><?php echo esc_html( $storage['total_size_human'] ); ?></span>
					</div>
					<div class="insight-item stat-descriptor">
						<?php esc_html_e( 'Total database storage used by all sitemap posts and metadata', 'msm-sitemap' ); ?>
					</div>
					<div class="insight-item">
						<span class="insight-label"><?php esc_html_e( 'Average Size:', 'msm-sitemap' ); ?></span>
						<span class="insight-value"><?php echo esc_html( $storage['average_size_human'] ); ?></span>
					</div>
					<div class="insight-item stat-descriptor">
						<?php esc_html_e( 'Average storage per individual sitemap post', 'msm-sitemap' ); ?>
					</div>
					<div class="insight-item">
						<span class="insight-label"><?php esc_html_e( 'Content Coverage:', 'msm-sitemap' ); ?></span>
						<span class="insight-value"><?php echo esc_html( $coverage['coverage_quality'] ?? 0 ); ?>%</span>
					</div>
					<div class="insight-item stat-descriptor">
						<?php
						printf(
							/* translators: %d is the number of posts per sitemap */
							esc_html__( 'Percentage of site content included in sitemaps (limited to %d posts per sitemap)', 'msm-sitemap' ),
							esc_html( $coverage['posts_per_sitemap_limit'] ?? 1000 )
						);
						?>
					</div>
				</div>
			</div>

			<!-- Latest Sitemaps Card -->
			<div class="insight-card">
				<h3><?php esc_html_e( 'Latest Sitemaps', 'msm-sitemap' ); ?></h3>
				<div class="insight-content">
					<?php
					$sitemap_dates = $this->sitemap_repository->get_all_sitemap_dates();
					$recent_dates  = array_slice( $sitemap_dates, -5, null, true );
					?>
					
					<!-- Sitemap Index Link -->
					<div class="insight-item">
						<span class="insight-label"><?php esc_html_e( 'Sitemap Index:', 'msm-sitemap' ); ?></span>
						<span class="insight-value">
							<a href="<?php echo esc_url( home_url( '/sitemap.xml' ) ); ?>" target="_blank">
								<?php echo esc_url( home_url( '/sitemap.xml' ) ); ?>
							</a>
						</span>
					</div>
					
					<!-- Recent Sitemaps -->
					<?php if ( ! empty( $recent_dates ) ) : ?>
						<div class="insight-item">
							<span class="insight-label"><?php esc_html_e( 'Recent Sitemaps:', 'msm-sitemap' ); ?></span>
							<span class="insight-value">
								<?php
								$sitemap_links = array();
								foreach ( array_reverse( $recent_dates ) as $date ) {
									$post_id   = $this->sitemap_repository->find_by_date( $date );
									$url_count = 0;
									if ( $post_id ) {
										$url_count = get_post_meta( $post_id, 'msm_indexed_url_count', true );
										if ( ! $url_count ) {
											$url_count = 0;
										}
									}
									$sitemap_url = home_url( '/sitemap.xml?yyyy=' . substr( $date, 0, 4 ) . '&mm=' . substr( $date, 5, 2 ) . '&dd=' . substr( $date, 8, 2 ) );
									/* translators: %s: number of URLs */
									$sitemap_links[] = '<a href="' . esc_url( $sitemap_url ) . '" target="_blank">' . esc_html( $date ) . '</a> (' . sprintf( _n( '%s URL', '%s URLs', $url_count, 'msm-sitemap' ), number_format( $url_count ) ) . ')';
								}
								echo wp_kses_post( implode( '<br>', $sitemap_links ) );
								?>
							</span>
						</div>
					<?php else : ?>
						<div class="insight-item">
							<span class="insight-label"><?php esc_html_e( 'Recent Sitemaps:', 'msm-sitemap' ); ?></span>
							<span class="insight-value"><?php esc_html_e( 'No sitemaps have been generated yet.', 'msm-sitemap' ); ?></span>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the manual generation section
	 */
	private function render_generate_section() {
		$cron_status                = $this->cron_management->get_cron_status();
		$sitemap_create_in_progress = (bool) get_option( 'msm_generation_in_progress' );
		$sitemap_halt_in_progress   = (bool) get_option( 'msm_sitemap_stop_generation' );

		// Clear the halt flag if generation is not actually running
		if ( $sitemap_halt_in_progress && ! $sitemap_create_in_progress ) {
			delete_option( 'msm_sitemap_stop_generation' );
			$sitemap_halt_in_progress = false;
		}

		// Determine if generate buttons should be enabled
		$buttons_enabled = ! $sitemap_create_in_progress && ! $sitemap_halt_in_progress;

		// Get missing content summary
		$missing_data    = $this->missing_detection_service->get_missing_sitemaps();
		$missing_summary = $this->missing_detection_service->get_missing_content_summary();

		?>
		<h2><?php esc_html_e( 'Missing Sitemaps', 'msm-sitemap' ); ?></h2>

		<div style="display: table; width: 100%; margin-bottom: 20px;">
			<div style="display: table-row;">
				<div style="display: table-cell; width: 120px; padding: 8px 0; font-weight: bold; vertical-align: text-bottom;">
					<?php esc_html_e( 'Status:', 'msm-sitemap' ); ?>
				</div>
				<div style="display: table-cell; padding: 8px 0;">
					<div id="missing-sitemaps-content" style="display: inline; vertical-align: text-bottom;">
						<span class="dashicons dashicons-update" style="animation: spin 1s linear infinite;"></span>
						<?php esc_html_e( 'Loading missing sitemaps...', 'msm-sitemap' ); ?>
					</div>

					<?php if ( $cron_status['enabled'] ) : ?>
						<!-- Two buttons when cron is enabled -->
						<span style="margin-left: 15px; vertical-align: text-bottom;">
							<button type="button" id="generate-missing-direct-button" class="button button-secondary" disabled="disabled">
								<?php esc_html_e( 'Generate Now (Direct)', 'msm-sitemap' ); ?>
							</button>
							<button type="button" id="generate-missing-background-button" class="button button-secondary" disabled="disabled" style="margin-left: 5px;">
								<?php esc_html_e( 'Generate in Background', 'msm-sitemap' ); ?>
							</button>
						</span>
					<?php else : ?>
						<!-- Single button when cron is disabled -->
						<button type="button" id="generate-missing-direct-button" class="button button-secondary" disabled="disabled" style="margin-left: 15px; vertical-align: text-bottom;">
							<?php esc_html_e( 'Generate Missing Sitemaps', 'msm-sitemap' ); ?>
						</button>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<!-- Background generation progress area -->
		<div id="background-generation-progress" style="display: none; margin-bottom: 20px; padding: 10px; background: #f0f6fc; border: 1px solid #c5d7e8; border-radius: 4px;">
			<span class="dashicons dashicons-update" style="animation: spin 1s linear infinite; margin-right: 5px;"></span>
			<span id="background-progress-text"><?php esc_html_e( 'Background generation in progress...', 'msm-sitemap' ); ?></span>
			<span id="background-progress-count" style="margin-left: 10px; font-weight: bold;"></span>
		</div>

		<?php $this->render_generate_note(); ?>
		<?php
	}

	/**
	 * Render a note about enabling automatic updates when generate buttons are disabled
	 */
	private function render_generate_note() {
		$cron_status = $this->cron_management->get_cron_status();

		if ( ! $cron_status['enabled'] ) {
			echo '<p style="margin-top: 10px; color: #666; font-style: italic;">';
			echo esc_html__( 'Note: Automatic updates must be enabled to use the generate functions.', 'msm-sitemap' );
			echo '</p>';
		}
	}

	/**
	 * Render the dangerous actions section
	 */
	private function render_dangerous_actions_section() {
		$cron_status                = $this->cron_management->get_cron_status();
		$sitemap_create_in_progress = (bool) get_option( 'msm_generation_in_progress' );
		$sitemap_halt_in_progress   = (bool) get_option( 'msm_sitemap_stop_generation' );

		// Determine if buttons should be disabled
		$buttons_enabled = $cron_status['enabled'] && ! $sitemap_create_in_progress && ! $sitemap_halt_in_progress;
		$reset_disabled  = $sitemap_create_in_progress || $sitemap_halt_in_progress;
		?>
		<div style="margin-top: 40px; border: 1px solid #dc3232; border-radius: 4px; padding: 15px; background-color: #fef7f7;">
			<div style="display: flex; align-items: center; margin-bottom: 15px;">
				<h2 style="margin: 0; color: #dc3232;"><?php esc_html_e( 'Danger Zone', 'msm-sitemap' ); ?></h2>
				<button type="button" id="danger-zone-toggle" class="button button-secondary" style="font-size: 12px; margin-left: 10px;">
					<span class="dashicons dashicons-arrow-down-alt2" id="danger-zone-icon" style="vertical-align: middle;"></span>
					<span id="danger-zone-toggle-text" style="vertical-align: middle;"><?php esc_html_e( 'Show', 'msm-sitemap' ); ?></span>
				</button>
			</div>
			
			<div id="danger-zone-content" style="display: none; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
				<!-- Full Generation Section -->
				<div style="padding: 15px; background-color: #fff; border: 1px solid #dc3232; border-radius: 4px;">
					<h3 style="margin: 0 0 10px 0; color: #dc3232;">
						<span class="dashicons dashicons-warning" style="color: #dc3232;"></span>
						<?php esc_html_e( 'Full Generation', 'msm-sitemap' ); ?>
					</h3>
					<p style="margin: 0 0 10px 0; color: #666; font-size: 13px;">
						<strong><?php esc_html_e( '⚠️ Warning:', 'msm-sitemap' ); ?></strong> <?php esc_html_e( 'This will regenerate ALL sitemaps for every date with posts, even if they already exist and are up to date.', 'msm-sitemap' ); ?>
					</p>
					<p style="margin: 0 0 15px 0; color: #666; font-size: 13px; font-style: italic;">
						<?php esc_html_e( 'For most cases, use "Generate Now" or "Schedule Background Generation" instead, which only regenerates missing or stale sitemaps.', 'msm-sitemap' ); ?>
					</p>
					
					<form action="<?php echo esc_url( menu_page_url( 'msm-sitemap', false ) ); ?>" method="post" style="display: inline;">
						<?php wp_nonce_field( 'msm-sitemap-action' ); ?>
						<?php if ( $buttons_enabled ) : ?>
							<input type="submit" name="action" class="button button-secondary" value="<?php esc_attr_e( 'Generate All Sitemaps (Force)', 'msm-sitemap' ); ?>">
						<?php elseif ( $sitemap_create_in_progress && ! $sitemap_halt_in_progress ) : ?>
							<input type="submit" name="action" class="button button-secondary" value="<?php esc_attr_e( 'Stop full sitemaps generation...', 'msm-sitemap' ); ?>">
						<?php elseif ( $sitemap_halt_in_progress ) : ?>
							<p style="margin-top: 10px; color: #666; font-style: italic;">
								<?php esc_html_e( 'Sitemap generation is being halted. The process will stop at the next available checkpoint.', 'msm-sitemap' ); ?>
							</p>
						<?php else : ?>
							<input type="submit" name="action" class="button button-secondary button-disabled" value="<?php esc_attr_e( 'Generate All Sitemaps (Force)', 'msm-sitemap' ); ?>" disabled="disabled">
						<?php endif; ?>
					</form>
				</div>
				
				<!-- Reset Sitemap Data Section -->
				<div style="padding: 15px; background-color: #fff; border: 1px solid #dc3232; border-radius: 4px;">
					<h3 style="margin: 0 0 8px 0; color: #dc3232;">
						<span class="dashicons dashicons-warning" style="color: #dc3232;"></span>
						<?php esc_html_e( 'Reset Sitemap Data', 'msm-sitemap' ); ?>
					</h3>
					<p style="margin: 0; color: #666;">
						<?php esc_html_e( 'This action will permanently delete:', 'msm-sitemap' ); ?>
					</p>
					<ul style="margin: 8px 0 0 20px; color: #666;">
						<li><?php esc_html_e( 'All sitemap post entries', 'msm-sitemap' ); ?></li>
						<li><?php esc_html_e( 'All sitemap metadata and statistics', 'msm-sitemap' ); ?></li>
						<li><?php esc_html_e( 'All processing options and progress', 'msm-sitemap' ); ?></li>
					</ul>
					<p style="margin: 8px 0 0 0; color: #666; font-style: italic;">
						<?php esc_html_e( 'This action cannot be undone. Your sitemaps will need to be regenerated from scratch.', 'msm-sitemap' ); ?>
					</p>
					
					<?php if ( $reset_disabled ) : ?>
						<p style="margin-top: 10px; color: #dc3232; font-style: italic;">
							<span class="dashicons dashicons-info" style="color: #dc3232;"></span>
							<?php esc_html_e( 'Reset is disabled while sitemap generation is in progress.', 'msm-sitemap' ); ?>
						</p>
						<input type="button" class="button button-link-delete button-disabled" value="<?php esc_attr_e( 'Reset Sitemap Data', 'msm-sitemap' ); ?>" disabled="disabled">
					<?php else : ?>
						<form action="<?php echo esc_url( menu_page_url( 'msm-sitemap', false ) ); ?>" method="post" style="margin-top: 10px;">
							<?php wp_nonce_field( 'msm-sitemap-action' ); ?>
							<input type="submit" name="action" class="button button-link-delete" value="<?php esc_attr_e( 'Reset Sitemap Data', 'msm-sitemap' ); ?>">
						</form>
					<?php endif; ?>
				</div>
			</div>
			

				

		</div>
		<?php
	}

	/**
	 * Get public post types grouped by hierarchy
	 *
	 * @return array Array with 'hierarchical' and 'non_hierarchical' keys.
	 */
	private function get_grouped_post_types(): array {
		$post_types = get_post_types(
			array(
				'public' => true,
			),
			'objects'
		);

		$grouped = array(
			'hierarchical'     => array(),
			'non_hierarchical' => array(),
		);

		foreach ( $post_types as $post_type ) {
			// Skip attachments - not suitable for sitemaps
			if ( 'attachment' === $post_type->name ) {
				continue;
			}

			if ( $post_type->hierarchical ) {
				$grouped['hierarchical'][] = $post_type;
			} else {
				$grouped['non_hierarchical'][] = $post_type;
			}
		}

		return $grouped;
	}

	/**
	 * Render the content providers settings section
	 */
	private function render_content_providers_section() {
		// Get current tab from URL or default to 'posts'
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only reading tab parameter for display
		$current_tab = isset( $_GET['provider_tab'] ) ? sanitize_key( $_GET['provider_tab'] ) : 'posts';
		$valid_tabs  = array( 'posts', 'pages', 'taxonomies', 'authors' );
		if ( ! in_array( $current_tab, $valid_tabs, true ) ) {
			$current_tab = 'posts';
		}

		// Get settings for each provider
		$settings               = $this->settings_service->get_image_settings();
		$images_provider_option = $settings['include_images'];
		$taxonomies_enabled     = $this->settings_service->get_setting( 'include_taxonomies', '0' );
		$enabled_taxonomies     = $this->settings_service->get_setting( 'enabled_taxonomies', array( 'category', 'post_tag' ) );
		$available_taxonomies   = $this->taxonomy_sitemap_service->get_available_taxonomies();
		$authors_enabled        = $this->settings_service->get_setting( 'include_authors', '0' );
		$pages_enabled          = $this->settings_service->get_setting( 'include_pages', '0' );

		// Get cron status for Posts tab
		$cron_status       = $this->cron_management->get_cron_status();
		$cron_enabled      = $cron_status['enabled'];
		$next_scheduled    = $cron_status['next_scheduled'];
		$current_frequency = $this->settings_service->get_setting( 'cron_frequency', '15min' );

		// Get grouped post types for display
		$grouped_post_types = $this->get_grouped_post_types();
		?>

		<style>
			.msm-tab-panel {
				padding: 20px;
				background: #fff;
				border: 1px solid #c3c4c7;
				border-top: none;
			}
			.msm-provider-header {
				display: flex;
				align-items: center;
				gap: 8px;
				margin-bottom: 10px;
			}
			.msm-provider-header h3 {
				margin: 0;
			}
			.msm-provider-settings {
				margin-top: 20px;
				padding: 15px;
				background-color: #f6f7f7;
				border-left: 4px solid #2271b1;
			}
		</style>

		<!-- Tab Navigation -->
		<h2 class="nav-tab-wrapper" id="msm-provider-tabs">
			<a href="<?php echo esc_url( add_query_arg( 'provider_tab', 'posts' ) ); ?>"
				class="nav-tab <?php echo 'posts' === $current_tab ? 'nav-tab-active' : ''; ?>"
				data-tab="posts">
				<?php esc_html_e( 'Posts', 'msm-sitemap' ); ?>
			</a>
			<a href="<?php echo esc_url( add_query_arg( 'provider_tab', 'pages' ) ); ?>"
				class="nav-tab <?php echo 'pages' === $current_tab ? 'nav-tab-active' : ''; ?>"
				data-tab="pages">
				<?php esc_html_e( 'Pages', 'msm-sitemap' ); ?>
			</a>
			<a href="<?php echo esc_url( add_query_arg( 'provider_tab', 'taxonomies' ) ); ?>"
				class="nav-tab <?php echo 'taxonomies' === $current_tab ? 'nav-tab-active' : ''; ?>"
				data-tab="taxonomies">
				<?php esc_html_e( 'Taxonomies', 'msm-sitemap' ); ?>
			</a>
			<a href="<?php echo esc_url( add_query_arg( 'provider_tab', 'authors' ) ); ?>"
				class="nav-tab <?php echo 'authors' === $current_tab ? 'nav-tab-active' : ''; ?>"
				data-tab="authors">
				<?php esc_html_e( 'Authors', 'msm-sitemap' ); ?>
			</a>
		</h2>

		<form action="" method="post" id="msm-provider-settings-form">
			<?php wp_nonce_field( 'msm-sitemap-action' ); ?>
			<input type="hidden" name="provider_tab" value="<?php echo esc_attr( $current_tab ); ?>">

			<!-- Posts Tab Panel -->
			<div class="msm-tab-panel" data-tab-panel="posts" style="<?php echo 'posts' !== $current_tab ? 'display: none;' : ''; ?>">
				<?php
				$enabled_post_types = $this->settings_service->get_setting( 'enabled_post_types', array( 'post' ) );
				?>
				<p class="description">
					<?php esc_html_e( 'Non-hierarchical post types are organised by publication date into daily sitemaps.', 'msm-sitemap' ); ?>
					<?php esc_html_e( 'View sitemap:', 'msm-sitemap' ); ?>
					<a href="<?php echo esc_url( home_url( '/sitemap.xml' ) ); ?>" target="_blank"><?php esc_html_e( 'Posts', 'msm-sitemap' ); ?></a>
				</p>

				<?php foreach ( $grouped_post_types['non_hierarchical'] as $post_type ) : ?>
				<p>
					<label for="post_type_<?php echo esc_attr( $post_type->name ); ?>_enabled">
						<input type="checkbox"
								class="msm-post-type-checkbox"
								id="post_type_<?php echo esc_attr( $post_type->name ); ?>_enabled"
								name="enabled_post_types[]"
								value="<?php echo esc_attr( $post_type->name ); ?>"
								<?php checked( in_array( $post_type->name, $enabled_post_types, true ) ); ?>>
						<?php
						printf(
							/* translators: %s: Post type name */
							esc_html__( 'Include %s in sitemaps', 'msm-sitemap' ),
							esc_html( strtolower( $post_type->labels->name ) )
						);
						?>
						<span style="color: #666; font-size: 12px;">(<?php echo esc_html( $post_type->name ); ?>)</span>
					</label>
				</p>
				<?php endforeach; ?>

				<div id="post_images_wrapper" style="<?php echo empty( $enabled_post_types ) ? 'display: none;' : ''; ?>">
					<h4 style="margin-top: 20px; margin-bottom: 10px;"><?php esc_html_e( 'Images', 'msm-sitemap' ); ?></h4>
					<p>
						<label for="images_provider_enabled">
							<input type="checkbox"
									id="images_provider_enabled"
									name="images_provider_enabled"
									value="1"
									<?php checked( '1' === $images_provider_option ); ?>>
							<?php esc_html_e( 'Include images in post sitemaps', 'msm-sitemap' ); ?>
						</label>
					</p>

					<div id="images_settings" style="margin-left: 24px; <?php echo '1' !== $images_provider_option ? 'display: none;' : ''; ?>">
						<?php
						// Check if any enabled post types support thumbnails
						$any_supports_thumbnail = false;
						foreach ( $enabled_post_types as $post_type_name ) {
							if ( post_type_supports( $post_type_name, 'thumbnail' ) ) {
								$any_supports_thumbnail = true;
								break;
							}
						}
						?>
						<?php if ( $any_supports_thumbnail ) : ?>
						<p>
							<label for="include_featured_images">
								<input type="checkbox"
										id="include_featured_images"
										name="include_featured_images"
										value="1"
										<?php checked( '1' === $settings['featured_images'] ); ?>>
								<?php esc_html_e( 'Include featured images', 'msm-sitemap' ); ?>
							</label>
						</p>
						<?php endif; ?>

						<p>
							<label for="include_content_images">
								<input type="checkbox"
										id="include_content_images"
										name="include_content_images"
										value="1"
										<?php checked( '1' === $settings['content_images'] ); ?>>
								<?php esc_html_e( 'Include content images', 'msm-sitemap' ); ?>
							</label>
						</p>

						<p>
							<label for="max_images_per_sitemap">
								<?php esc_html_e( 'Maximum images per sitemap:', 'msm-sitemap' ); ?>
								<input type="number"
										id="max_images_per_sitemap"
										name="max_images_per_sitemap"
										value="<?php echo esc_attr( $settings['max_images_per_sitemap'] ); ?>"
										min="1"
										max="10000"
										style="width: 100px;">
							</label>
						</p>
					</div>
				</div>

				<h4 style="margin-top: 20px; margin-bottom: 10px;"><?php esc_html_e( 'Automatic Updates', 'msm-sitemap' ); ?></h4>
				<p>
					<label>
						<input type="checkbox" name="automatic_updates_enabled" id="automatic_updates_enabled" value="1" <?php checked( $cron_enabled ); ?>>
						<?php esc_html_e( 'Enable automatic updates', 'msm-sitemap' ); ?>
					</label>
				</p>

				<div id="automatic_updates_settings" style="margin-left: 24px; <?php echo ! $cron_enabled ? 'display: none;' : ''; ?>">
					<?php
					// Get last check time
					$last_check           = get_option( 'msm_sitemap_last_check' );
					$last_check_timestamp = is_numeric( $last_check ) ? (int) $last_check : strtotime( $last_check );
					$last_check_relative  = $last_check ? human_time_diff( $last_check_timestamp, time() ) : '';
					$last_check_absolute  = $last_check ? wp_date( 'Y-m-d H:i:s T', $last_check_timestamp ) : '';

					// Get last update time
					$last_update           = get_option( 'msm_sitemap_last_update' );
					$last_update_timestamp = is_numeric( $last_update ) ? (int) $last_update : strtotime( $last_update );
					$last_update_relative  = $last_update ? human_time_diff( $last_update_timestamp, time() ) : '';
					$last_update_absolute  = $last_update ? wp_date( 'Y-m-d H:i:s T', $last_update_timestamp ) : '';

					// Next check
					$next_check_relative = $next_scheduled ? human_time_diff( time(), $next_scheduled ) : '';
					$next_check_absolute = $next_scheduled ? wp_date( 'Y-m-d H:i:s T', $next_scheduled ) : '';
					?>
					<p>
						<?php esc_html_e( 'Last checked:', 'msm-sitemap' ); ?>
						<?php if ( $last_check_relative ) : ?>
							<?php
							/* translators: %s: Time ago */
							printf( esc_html__( '%s ago', 'msm-sitemap' ), esc_html( $last_check_relative ) );
							?>
							<small style="color: #666;">(<?php echo esc_html( $last_check_absolute ); ?>)</small>
						<?php else : ?>
							<?php esc_html_e( 'Never', 'msm-sitemap' ); ?>
						<?php endif; ?>
					</p>
					<p>
						<?php esc_html_e( 'Last updated:', 'msm-sitemap' ); ?>
						<?php if ( $last_update_relative ) : ?>
							<?php
							/* translators: %s: Time ago */
							printf( esc_html__( '%s ago', 'msm-sitemap' ), esc_html( $last_update_relative ) );
							?>
							<small style="color: #666;">(<?php echo esc_html( $last_update_absolute ); ?>)</small>
						<?php else : ?>
							<?php esc_html_e( 'Never', 'msm-sitemap' ); ?>
						<?php endif; ?>
					</p>
					<?php if ( $next_scheduled ) : ?>
					<p>
						<?php esc_html_e( 'Next check:', 'msm-sitemap' ); ?>
						<?php
						/* translators: %s: Time until next check */
						printf( esc_html__( 'in %s', 'msm-sitemap' ), esc_html( $next_check_relative ) );
						?>
						<small style="color: #666;">(<?php echo esc_html( $next_check_absolute ); ?>)</small>
					</p>
					<?php endif; ?>
					<p>
						<label for="cron-frequency">
							<?php esc_html_e( 'Frequency:', 'msm-sitemap' ); ?>
							<select id="cron-frequency" name="cron_frequency">
								<option value="5min" <?php selected( $current_frequency, '5min' ); ?>><?php esc_html_e( 'Every 5 minutes', 'msm-sitemap' ); ?></option>
								<option value="10min" <?php selected( $current_frequency, '10min' ); ?>><?php esc_html_e( 'Every 10 minutes', 'msm-sitemap' ); ?></option>
								<option value="15min" <?php selected( $current_frequency, '15min' ); ?>><?php esc_html_e( 'Every 15 minutes', 'msm-sitemap' ); ?></option>
								<option value="30min" <?php selected( $current_frequency, '30min' ); ?>><?php esc_html_e( 'Every 30 minutes', 'msm-sitemap' ); ?></option>
								<option value="hourly" <?php selected( $current_frequency, 'hourly' ); ?>><?php esc_html_e( 'Every hour', 'msm-sitemap' ); ?></option>
								<option value="2hourly" <?php selected( $current_frequency, '2hourly' ); ?>><?php esc_html_e( 'Every 2 hours', 'msm-sitemap' ); ?></option>
								<option value="3hourly" <?php selected( $current_frequency, '3hourly' ); ?>><?php esc_html_e( 'Every 3 hours', 'msm-sitemap' ); ?></option>
							</select>
						</label>
					</p>
				</div>
			</div>

			<!-- Pages Tab Panel -->
			<div class="msm-tab-panel" data-tab-panel="pages" style="<?php echo 'pages' !== $current_tab ? 'display: none;' : ''; ?>">
				<?php
				$enabled_page_types = $this->settings_service->get_setting( 'enabled_page_types', array( 'page' ) );
				?>
				<p class="description">
					<?php esc_html_e( 'Hierarchical post types are organised into a single paginated sitemap.', 'msm-sitemap' ); ?>
					<?php esc_html_e( 'View sitemap:', 'msm-sitemap' ); ?>
					<a href="<?php echo esc_url( home_url( '/sitemap-page.xml' ) ); ?>" target="_blank"><?php esc_html_e( 'Pages', 'msm-sitemap' ); ?></a>
				</p>

				<?php foreach ( $grouped_post_types['hierarchical'] as $post_type ) : ?>
				<p>
					<label for="page_type_<?php echo esc_attr( $post_type->name ); ?>_enabled">
						<input type="checkbox"
								class="msm-page-type-checkbox"
								id="page_type_<?php echo esc_attr( $post_type->name ); ?>_enabled"
								name="enabled_page_types[]"
								value="<?php echo esc_attr( $post_type->name ); ?>"
								<?php checked( in_array( $post_type->name, $enabled_page_types, true ) ); ?>>
						<?php
						printf(
							/* translators: %s: Post type name */
							esc_html__( 'Include %s in sitemaps', 'msm-sitemap' ),
							esc_html( strtolower( $post_type->labels->name ) )
						);
						?>
						<span style="color: #666; font-size: 12px;">(<?php echo esc_html( $post_type->name ); ?>)</span>
					</label>
				</p>
				<?php endforeach; ?>

				<div id="page_cache_settings" style="margin-top: 20px; <?php echo empty( $enabled_page_types ) ? 'display: none;' : ''; ?>">
					<p>
						<label for="page_cache_ttl">
							<?php esc_html_e( 'Cache duration (minutes):', 'msm-sitemap' ); ?>
							<?php
							$page_cache_ttl = $this->settings_service->get_setting( 'page_cache_ttl', \Automattic\MSM_Sitemap\Application\Services\SettingsService::DEFAULT_CACHE_TTL_MINUTES );
							?>
							<input type="number"
									id="page_cache_ttl"
									name="page_cache_ttl"
									value="<?php echo esc_attr( $page_cache_ttl ); ?>"
									min="<?php echo esc_attr( \Automattic\MSM_Sitemap\Application\Services\SettingsService::MIN_CACHE_TTL_MINUTES ); ?>"
									max="<?php echo esc_attr( \Automattic\MSM_Sitemap\Application\Services\SettingsService::MAX_CACHE_TTL_MINUTES ); ?>"
									style="width: 80px;">
						</label>
						<span class="description" style="margin-left: 10px;">
							<?php esc_html_e( 'How long to cache page sitemaps before regenerating.', 'msm-sitemap' ); ?>
						</span>
					</p>
				</div>
			</div>

			<!-- Taxonomies Tab Panel -->
			<div class="msm-tab-panel" data-tab-panel="taxonomies" style="<?php echo 'taxonomies' !== $current_tab ? 'display: none;' : ''; ?>">
				<p class="description">
					<?php esc_html_e( 'Add category, tag, and custom taxonomy archive pages to your sitemaps.', 'msm-sitemap' ); ?>
					<?php if ( ! empty( $enabled_taxonomies ) ) : ?>
						<?php esc_html_e( 'View sitemaps:', 'msm-sitemap' ); ?>
						<?php
						$taxonomy_links = array();
						foreach ( $enabled_taxonomies as $tax_name ) {
							$tax_obj = get_taxonomy( $tax_name );
							if ( $tax_obj ) {
								$sitemap_url      = home_url( '/sitemap-taxonomy-' . $tax_name . '.xml' );
								$taxonomy_links[] = '<a href="' . esc_url( $sitemap_url ) . '" target="_blank">' . esc_html( $tax_obj->labels->name ) . '</a>';
							}
						}
						echo wp_kses_post( implode( ', ', $taxonomy_links ) );
						?>
					<?php endif; ?>
				</p>

				<?php foreach ( $available_taxonomies as $taxonomy ) : ?>
				<p>
					<label for="taxonomy_<?php echo esc_attr( $taxonomy->name ); ?>_enabled">
						<input type="checkbox"
								class="msm-taxonomy-checkbox"
								id="taxonomy_<?php echo esc_attr( $taxonomy->name ); ?>_enabled"
								name="enabled_taxonomies[]"
								value="<?php echo esc_attr( $taxonomy->name ); ?>"
								<?php checked( in_array( $taxonomy->name, $enabled_taxonomies, true ) ); ?>>
						<?php
						printf(
							/* translators: %s: Taxonomy name */
							esc_html__( 'Include %s in sitemaps', 'msm-sitemap' ),
							esc_html( strtolower( $taxonomy->labels->name ) )
						);
						?>
						<span style="color: #666; font-size: 12px;">(<?php echo esc_html( $taxonomy->name ); ?>)</span>
					</label>
				</p>
				<?php endforeach; ?>

				<div id="taxonomy_cache_settings" style="margin-top: 20px; <?php echo empty( $enabled_taxonomies ) ? 'display: none;' : ''; ?>">
					<p>
						<label for="taxonomy_cache_ttl">
							<?php esc_html_e( 'Cache duration (minutes):', 'msm-sitemap' ); ?>
							<?php
							$taxonomy_cache_ttl = $this->settings_service->get_setting( 'taxonomy_cache_ttl', \Automattic\MSM_Sitemap\Application\Services\SettingsService::DEFAULT_CACHE_TTL_MINUTES );
							?>
							<input type="number"
									id="taxonomy_cache_ttl"
									name="taxonomy_cache_ttl"
									value="<?php echo esc_attr( $taxonomy_cache_ttl ); ?>"
									min="<?php echo esc_attr( \Automattic\MSM_Sitemap\Application\Services\SettingsService::MIN_CACHE_TTL_MINUTES ); ?>"
									max="<?php echo esc_attr( \Automattic\MSM_Sitemap\Application\Services\SettingsService::MAX_CACHE_TTL_MINUTES ); ?>"
									style="width: 80px;">
						</label>
						<span class="description" style="margin-left: 10px;">
							<?php esc_html_e( 'How long to cache taxonomy sitemaps before regenerating.', 'msm-sitemap' ); ?>
						</span>
					</p>
				</div>
			</div>

			<!-- Authors Tab Panel -->
			<div class="msm-tab-panel" data-tab-panel="authors" style="<?php echo 'authors' !== $current_tab ? 'display: none;' : ''; ?>">
				<p class="description">
					<?php esc_html_e( 'Add author archive pages to your sitemaps. Only authors with published posts will be included.', 'msm-sitemap' ); ?>
					<?php if ( '1' === $authors_enabled ) : ?>
						<?php esc_html_e( 'View sitemap:', 'msm-sitemap' ); ?>
						<a href="<?php echo esc_url( home_url( '/sitemap-author.xml' ) ); ?>" target="_blank"><?php esc_html_e( 'Authors', 'msm-sitemap' ); ?></a>
					<?php endif; ?>
				</p>

				<p>
					<label for="authors_provider_enabled">
						<input type="checkbox"
								id="authors_provider_enabled"
								name="authors_provider_enabled"
								value="1"
								<?php checked( '1' === $authors_enabled ); ?>>
						<?php esc_html_e( 'Include authors in sitemaps', 'msm-sitemap' ); ?>
					</label>
				</p>

				<div id="authors_settings" style="margin-top: 20px; <?php echo '1' !== $authors_enabled ? 'display: none;' : ''; ?>">
					<p>
						<label for="author_cache_ttl">
							<?php esc_html_e( 'Cache duration (minutes):', 'msm-sitemap' ); ?>
							<?php
							$author_cache_ttl = $this->settings_service->get_setting( 'author_cache_ttl', \Automattic\MSM_Sitemap\Application\Services\SettingsService::DEFAULT_CACHE_TTL_MINUTES );
							?>
							<input type="number"
									id="author_cache_ttl"
									name="author_cache_ttl"
									value="<?php echo esc_attr( $author_cache_ttl ); ?>"
									min="<?php echo esc_attr( \Automattic\MSM_Sitemap\Application\Services\SettingsService::MIN_CACHE_TTL_MINUTES ); ?>"
									max="<?php echo esc_attr( \Automattic\MSM_Sitemap\Application\Services\SettingsService::MAX_CACHE_TTL_MINUTES ); ?>"
									style="width: 80px;">
						</label>
						<span class="description" style="margin-left: 10px;">
							<?php esc_html_e( 'How long to cache author sitemaps before regenerating.', 'msm-sitemap' ); ?>
						</span>
					</p>
				</div>
			</div>

			<p class="submit" style="margin-top: 20px;">
				<input type="submit" name="action" id="msm-save-provider-settings" class="button button-primary" value="<?php esc_attr_e( 'Save Content Provider Settings', 'msm-sitemap' ); ?>" disabled>
			</p>
		</form>
		<?php
	}
}

