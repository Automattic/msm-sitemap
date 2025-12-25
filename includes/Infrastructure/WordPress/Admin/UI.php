<?php
/**
 * Admin UI for MSM Sitemap
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
use Automattic\MSM_Sitemap\Domain\Contracts\SitemapRepositoryInterface;
use Automattic\MSM_Sitemap\Domain\Contracts\WordPressIntegrationInterface;

/**
 * Admin settings page for MSM Sitemap
 */
class UI implements WordPressIntegrationInterface {

	/**
	 * Services
	 */
	private CronManagementService $cron_management;
	private MissingSitemapDetectionService $missing_detection_service;
	private SitemapStatsService $stats_service;
	private SettingsService $settings_service;
	private SitemapRepositoryInterface $sitemap_repository;
	private TaxonomySitemapService $taxonomy_sitemap_service;
	private AuthorSitemapService $author_sitemap_service;
	private PageSitemapService $page_sitemap_service;
	private ActionHandlers $action_handlers;

	/** @var string */
	private string $plugin_file_path;

	/** @var string */
	private string $plugin_version;

	/**
	 * Constructor.
	 */
	public function __construct(
		CronManagementService $cron_management,
		MissingSitemapDetectionService $missing_detection_service,
		SitemapStatsService $stats_service,
		SettingsService $settings_service,
		SitemapRepositoryInterface $sitemap_repository,
		TaxonomySitemapService $taxonomy_sitemap_service,
		AuthorSitemapService $author_sitemap_service,
		PageSitemapService $page_sitemap_service,
		ActionHandlers $action_handlers,
		string $plugin_file_path = '',
		string $plugin_version = '1.0.0'
	) {
		$this->cron_management           = $cron_management;
		$this->missing_detection_service = $missing_detection_service;
		$this->stats_service             = $stats_service;
		$this->settings_service          = $settings_service;
		$this->sitemap_repository        = $sitemap_repository;
		$this->taxonomy_sitemap_service  = $taxonomy_sitemap_service;
		$this->author_sitemap_service    = $author_sitemap_service;
		$this->page_sitemap_service      = $page_sitemap_service;
		$this->action_handlers           = $action_handlers;
		$this->plugin_file_path          = $plugin_file_path;
		$this->plugin_version            = $plugin_version;
	}

	/**
	 * Register the admin page
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'add_admin_page' ) );

		// Register AJAX handler globally so it works for AJAX requests
		add_action( 'wp_ajax_msm_toggle_section', array( $this, 'ajax_toggle_section' ) );
	}

	/**
	 * Add the settings page under Settings menu
	 */
	public function add_admin_page(): void {
		$page_hook = add_options_page(
			__( 'MSM Sitemap', 'msm-sitemap' ),
			__( 'Sitemap', 'msm-sitemap' ),
			'manage_options',
			'msm-sitemap',
			array( $this, 'render_page' )
		);

		if ( $page_hook ) {
			HelpTabs::setup( $page_hook );
			add_action( 'load-' . $page_hook, array( $this, 'enqueue_admin_assets' ) );
			add_action( 'load-' . $page_hook, array( $this, 'add_screen_options' ) );
		}
	}

	/**
	 * Add screen options for the page
	 */
	public function add_screen_options(): void {
		// Add a dummy screen option to force the Screen Options tab to appear
		$screen = get_current_screen();
		if ( $screen ) {
			// This forces the Screen Options tab to appear
			add_screen_option(
				'msm_layout_options',
				array(
					'label'   => __( 'Section visibility', 'msm-sitemap' ),
					'default' => '',
				)
			);
		}

		// Add the screen options form
		add_filter( 'screen_settings', array( $this, 'render_screen_options' ), 10, 2 );
	}

	/**
	 * Render screen options
	 *
	 * @param string    $settings Screen settings HTML.
	 * @param \WP_Screen $screen   Current screen object.
	 * @return string Modified screen settings HTML.
	 */
	public function render_screen_options( string $settings, \WP_Screen $screen ): string {
		if ( 'settings_page_msm-sitemap' !== $screen->id ) {
			return $settings;
		}

		$show_stats       = $this->get_user_section_visibility( 'stats' );
		$show_danger_zone = $this->get_user_section_visibility( 'danger_zone' );
		$nonce            = wp_create_nonce( 'msm_toggle_section' );

		ob_start();
		?>
		<fieldset class="metabox-prefs">
			<legend><?php esc_html_e( 'Show on screen', 'msm-sitemap' ); ?></legend>
			<label>
				<input type="checkbox" id="msm-show-stats" <?php checked( $show_stats ); ?>>
				<?php esc_html_e( 'Statistics', 'msm-sitemap' ); ?>
			</label>
			<label style="margin-left: 15px;">
				<input type="checkbox" id="msm-show-danger-zone" <?php checked( $show_danger_zone ); ?>>
				<?php esc_html_e( 'Danger Zone', 'msm-sitemap' ); ?>
			</label>
		</fieldset>
		<script>
		jQuery(document).ready(function($) {
			$('#msm-show-stats, #msm-show-danger-zone').on('change', function() {
				var section = $(this).attr('id') === 'msm-show-stats' ? 'stats' : 'danger_zone';
				var visible = $(this).is(':checked');

				// Toggle visibility immediately
				if (section === 'stats') {
					$('#msm-stats-section').toggle(visible);
				} else {
					$('#msm-danger-zone-section').toggle(visible);
				}

				// Save to user meta
				$.post(ajaxurl, {
					action: 'msm_toggle_section',
					section: section,
					visible: visible ? 1 : 0,
					nonce: '<?php echo esc_js( $nonce ); ?>'
				});
			});
		});
		</script>
		<?php
		$settings .= ob_get_clean();

		return $settings;
	}

	/**
	 * AJAX handler for toggling section visibility
	 */
	public function ajax_toggle_section(): void {
		check_ajax_referer( 'msm_toggle_section', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$section = isset( $_POST['section'] ) ? sanitize_key( $_POST['section'] ) : '';
		$visible = isset( $_POST['visible'] ) ? (bool) intval( $_POST['visible'] ) : false;

		if ( ! in_array( $section, array( 'stats', 'danger_zone' ), true ) ) {
			wp_send_json_error( 'Invalid section' );
		}

		$user_id  = get_current_user_id();
		$meta_key = 'msm_sitemap_show_' . $section;
		update_user_meta( $user_id, $meta_key, $visible ? '1' : '0' );

		wp_send_json_success();
	}

	/**
	 * Get user's section visibility preference
	 *
	 * @param string $section The section name ('stats' or 'danger_zone').
	 * @return bool Whether the section should be visible.
	 */
	private function get_user_section_visibility( string $section ): bool {
		$user_id  = get_current_user_id();
		$meta_key = 'msm_sitemap_show_' . $section;
		$value    = get_user_meta( $user_id, $meta_key, true );

		// Default: both hidden
		if ( '' === $value ) {
			return false;
		}

		return '1' === $value;
	}

	/**
	 * Enqueue admin scripts and styles
	 */
	public function enqueue_admin_assets(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'load_scripts' ) );
	}

	/**
	 * Load scripts on the page
	 */
	public function load_scripts(): void {
		$asset_url = plugins_url( 'assets/', $this->plugin_file_path );

		wp_enqueue_style(
			'msm-sitemap-admin',
			$asset_url . 'admin.css',
			array(),
			$this->plugin_version
		);

		wp_enqueue_script(
			'msm-sitemap-admin',
			$asset_url . 'admin.js',
			array( 'jquery' ),
			$this->plugin_version,
			true
		);

		wp_localize_script(
			'msm-sitemap-admin',
			'msmSitemapAdmin',
			array(
				'restUrl'                => rest_url( 'msm-sitemap/v1/' ),
				'nonce'                  => wp_create_nonce( 'wp_rest' ),
				'cronEnabled'            => $this->cron_management->is_enabled(),
				'generatingText'         => __( 'Generating...', 'msm-sitemap' ),
				'generateText'           => __( 'Generate Now (Direct)', 'msm-sitemap' ),
				'generateBackgroundText' => __( 'Generate in Background', 'msm-sitemap' ),
			)
		);
	}

	/**
	 * Handle form submissions and action processing
	 */
	private function handle_form_submissions(): void {
		if ( ! isset( $_POST['action'] ) ) {
			return;
		}

		check_admin_referer( 'msm-sitemap-action' );

		$action = sanitize_text_field( wp_unslash( $_POST['action'] ) );

		// Route to appropriate action handler
		switch ( $action ) {
			case 'Generate Full Sitemap (All Content)':
			case 'Generate All Sitemaps (Force)':
			case 'Regenerate Now':
			case 'Regenerate All Sitemaps':
				$this->action_handlers->handle_generate_full();
				break;
			case 'Generate Missing Sitemaps':
			case 'Generate Missing Sitemaps (Direct)':
				$this->action_handlers->handle_generate_missing_sitemaps();
				break;
			case 'Save Content Provider Settings':
				$this->action_handlers->handle_save_content_provider_settings();
				break;
			case 'Stop adding missing sitemaps...':
			case 'Stop full sitemaps generation...':
				$this->action_handlers->handle_halt_generation();
				break;
			case 'Reset Sitemap Data':
				$this->action_handlers->handle_reset_data();
				break;
		}
	}

	/**
	 * Render the settings page
	 */
	public function render_page(): void {
		// Permission check
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'msm-sitemap' ) );
		}

		// Check if the site is private
		if ( ! get_option( 'blog_public', 1 ) ) {
			$this->render_private_blog_message();
			return;
		}

		// Handle form submissions first
		$this->handle_form_submissions();

		$sitemap_url         = home_url( '/sitemap.xml' );
		$cron_status         = $this->cron_management->get_cron_status();
		$missing_data        = $this->missing_detection_service->get_missing_sitemaps();
		$comprehensive_stats = $this->stats_service->get_comprehensive_stats();
		$is_generating       = (bool) get_option( 'msm_generation_in_progress' );

		// Post sitemaps vs content sitemaps
		$post_sitemap_count = $comprehensive_stats['overview']['total_sitemaps'];
		$taxonomy_entries   = $this->taxonomy_sitemap_service->is_enabled() ? count( $this->taxonomy_sitemap_service->get_sitemap_index_entries() ) : 0;
		$author_entries     = $this->author_sitemap_service->is_enabled() ? count( $this->author_sitemap_service->get_sitemap_index_entries() ) : 0;
		$page_entries       = $this->page_sitemap_service->is_enabled() ? count( $this->page_sitemap_service->get_sitemap_index_entries() ) : 0;
		$content_sitemaps   = $taxonomy_entries + $author_entries + $page_entries;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'XML Sitemaps', 'msm-sitemap' ); ?></h1>

			<?php $this->render_status_section( $sitemap_url, $post_sitemap_count, $taxonomy_entries, $author_entries, $page_entries, $missing_data, $is_generating, $cron_status ); ?>

			<?php $this->render_settings_section( $cron_status ); ?>

			<?php $this->render_stats_section( $comprehensive_stats ); ?>

			<?php $this->render_danger_zone_section(); ?>
		</div>
		<?php
	}

	/**
	 * Render the private blog message
	 */
	private function render_private_blog_message(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'XML Sitemaps', 'msm-sitemap' ); ?></h1>
			<div class="notice notice-warning">
				<p><?php esc_html_e( 'Sitemaps are not supported on private sites. Please make your site public to use sitemaps.', 'msm-sitemap' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the status overview section
	 */
	private function render_status_section( string $sitemap_url, int $post_sitemap_count, int $taxonomy_entries, int $author_entries, int $page_entries, array $missing_data, bool $is_generating, array $cron_status ): void {
		$content_sitemaps        = $taxonomy_entries + $author_entries + $page_entries;
		$has_any_sitemaps        = $post_sitemap_count > 0 || $content_sitemaps > 0;
		$missing_post_count      = $missing_data['all_dates_count'];
		$settings_changed        = $this->settings_service->has_content_settings_changed();
		$has_any_content_enabled = $this->settings_service->has_any_content_enabled();
		$needs_regeneration      = $settings_changed || $missing_post_count > 0;
		?>
		<div style="background: #fff; border: 1px solid #c3c4c7; border-left: 4px solid #2271b1; padding: 20px; margin-bottom: 20px;">
			<h2 style="margin-top: 0;"><?php esc_html_e( 'Your Sitemap', 'msm-sitemap' ); ?></h2>

			<div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
				<code style="font-size: 14px; padding: 8px 12px; background: #f0f0f1; border-radius: 4px;">
					<?php echo esc_url( $sitemap_url ); ?>
				</code>
				<a href="<?php echo esc_url( $sitemap_url ); ?>" target="_blank" class="button">
					<?php esc_html_e( 'View Sitemap', 'msm-sitemap' ); ?>
				</a>
				<button type="button" class="button" onclick="navigator.clipboard.writeText('<?php echo esc_js( $sitemap_url ); ?>'); this.textContent='<?php esc_attr_e( 'Copied!', 'msm-sitemap' ); ?>'; setTimeout(() => this.textContent='<?php esc_attr_e( 'Copy URL', 'msm-sitemap' ); ?>', 2000);">
					<?php esc_html_e( 'Copy URL', 'msm-sitemap' ); ?>
				</button>
			</div>

			<!-- Status display -->
			<div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
				<!-- Dynamic content area for AJAX updates -->
				<span id="missing-sitemaps-content">
					<?php if ( $is_generating ) : ?>
						<span style="color: #2271b1;">
							<span class="dashicons dashicons-update" style="animation: spin 1s linear infinite;"></span>
							<?php esc_html_e( 'Generating sitemaps...', 'msm-sitemap' ); ?>
						</span>
					<?php elseif ( 0 === $post_sitemap_count && 0 === $content_sitemaps ) : ?>
						<span style="color: #996800;">
							<span class="dashicons dashicons-warning"></span>
							<?php esc_html_e( 'No sitemaps generated yet', 'msm-sitemap' ); ?>
						</span>
					<?php elseif ( $settings_changed ) : ?>
						<span style="color: #996800; font-weight: bold;">
							<span class="dashicons dashicons-warning"></span>
							<?php esc_html_e( 'Content settings changed', 'msm-sitemap' ); ?>
						</span>
					<?php elseif ( $missing_post_count > 0 ) : ?>
						<span style="color: #dc3232; font-weight: bold;">
							🔍
							<?php
							printf(
								/* translators: %d: number of post sitemaps */
								esc_html( _n( '%d sitemap needs generating', '%d sitemaps need generating', $missing_post_count, 'msm-sitemap' ) ),
								esc_html( $missing_post_count )
							);
							?>
						</span>
					<?php else : ?>
						<span style="color: #46b450;">
							✅ <?php esc_html_e( 'All sitemaps up to date', 'msm-sitemap' ); ?>
						</span>
					<?php endif; ?>
				</span>

				<!-- Generation summary -->
				<span id="sitemap-summary-counts" style="color: #666; font-size: 13px; <?php echo $has_any_sitemaps ? '' : 'display: none;'; ?>">
					<?php
					$parts = array();
					if ( $post_sitemap_count > 0 ) {
						$parts[] = sprintf(
							/* translators: %d: number of daily sitemaps */
							_n( '%d daily sitemap', '%d daily sitemaps', $post_sitemap_count, 'msm-sitemap' ),
							$post_sitemap_count
						);
					}
					if ( $taxonomy_entries > 0 ) {
						$parts[] = sprintf(
							/* translators: %d: number of taxonomy sitemaps */
							_n( '%d taxonomy', '%d taxonomies', $taxonomy_entries, 'msm-sitemap' ),
							$taxonomy_entries
						);
					}
					if ( $author_entries > 0 ) {
						$parts[] = sprintf(
							/* translators: %d: number of author sitemaps */
							_n( '%d author', '%d authors', $author_entries, 'msm-sitemap' ),
							$author_entries
						);
					}
					if ( $page_entries > 0 ) {
						$parts[] = sprintf(
							/* translators: %d: number of page sitemaps */
							_n( '%d page', '%d pages', $page_entries, 'msm-sitemap' ),
							$page_entries
						);
					}
					echo esc_html( implode( ', ', $parts ) );
					?>
				</span>
			</div>

			<!-- Generate buttons - uses same AJAX as main page -->
			<?php if ( $needs_regeneration && ! $is_generating ) : ?>
			<div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee;">
				<?php if ( $cron_status['enabled'] ) : ?>
					<?php if ( $settings_changed ) : ?>
						<?php if ( ! $has_any_content_enabled && 0 < $post_sitemap_count ) : ?>
							<p style="margin: 0 0 10px 0; color: #996800; font-size: 13px;">
								<span class="dashicons dashicons-warning" style="vertical-align: text-top;"></span>
								<?php
								echo esc_html(
									sprintf(
										/* translators: %d: number of sitemaps that will be deleted */
										_n(
											'No content types are enabled. Clicking the button below will delete %d existing sitemap.',
											'No content types are enabled. Clicking the button below will delete %d existing sitemaps.',
											$post_sitemap_count,
											'msm-sitemap'
										),
										$post_sitemap_count
									)
								);
								?>
							</p>
						<?php elseif ( ! $has_any_content_enabled ) : ?>
							<p style="margin: 0 0 10px 0; color: #666; font-size: 13px;">
								<?php esc_html_e( 'No content types are enabled. Enable at least one content type above to generate sitemaps.', 'msm-sitemap' ); ?>
							</p>
						<?php else : ?>
							<p style="margin: 0 0 10px 0; color: #666; font-size: 13px;">
								<?php esc_html_e( 'Your content settings have changed. Regenerating will rebuild all sitemaps using your current settings. This runs in the background and may take a few minutes for large sites.', 'msm-sitemap' ); ?>
							</p>
						<?php endif; ?>
						<form action="<?php echo esc_url( admin_url( 'options-general.php?page=msm-sitemap' ) ); ?>" method="post" style="display: inline;">
							<?php wp_nonce_field( 'msm-sitemap-action' ); ?>
							<input type="submit" name="action" class="button button-primary" value="<?php esc_attr_e( 'Regenerate All Sitemaps', 'msm-sitemap' ); ?>">
						</form>
					<?php else : ?>
						<button type="button" id="generate-missing-direct-button" class="button button-primary">
							<?php esc_html_e( 'Generate Now', 'msm-sitemap' ); ?>
						</button>
						<button type="button" id="generate-missing-background-button" class="button button-secondary" style="margin-left: 5px;">
							<?php esc_html_e( 'Generate in Background', 'msm-sitemap' ); ?>
						</button>
					<?php endif; ?>
				<?php else : ?>
					<p style="margin: 0; color: #666; font-size: 13px;">
						<?php esc_html_e( 'Enable automatic updates to generate sitemaps.', 'msm-sitemap' ); ?>
					</p>
				<?php endif; ?>
			</div>
			<?php endif; ?>

			<!-- Background generation progress area -->
			<div id="background-generation-progress" style="display: none; margin-top: 15px; padding: 10px; background: #f0f6fc; border: 1px solid #c5d7e8; border-radius: 4px;">
				<span class="dashicons dashicons-update" style="animation: spin 1s linear infinite; margin-right: 5px;"></span>
				<span id="background-progress-text"><?php esc_html_e( 'Background generation in progress...', 'msm-sitemap' ); ?></span>
				<span id="background-progress-count" style="margin-left: 10px; font-weight: bold;"></span>
			</div>
		</div>
		<?php
	}

	/**
	 * Render unified settings section with single form
	 */
	private function render_settings_section( array $cron_status ): void {
		$current_frequency    = $this->settings_service->get_setting( 'cron_frequency', '15min' );
		$enabled_post_types   = $this->settings_service->get_setting( 'enabled_post_types', array( 'post' ) );
		$enabled_taxonomies   = $this->settings_service->get_setting( 'enabled_taxonomies', array( 'category', 'post_tag' ) );
		$enabled_page_types   = $this->settings_service->get_setting( 'enabled_page_types', array( 'page' ) );
		$authors_enabled      = $this->author_sitemap_service->is_enabled();
		$images_settings      = $this->settings_service->get_image_settings();
		$available_taxonomies = $this->taxonomy_sitemap_service->get_available_taxonomies();
		$post_types           = get_post_types( array( 'public' => true ), 'objects' );
		$hierarchical_types   = get_post_types(
			array(
				'public'       => true,
				'hierarchical' => true,
			),
			'objects' 
		);
		?>
		<div style="background: #fff; border: 1px solid #c3c4c7; padding: 20px; margin-bottom: 20px;">
			<h2 style="margin-top: 0;"><?php esc_html_e( 'Settings', 'msm-sitemap' ); ?></h2>

			<!-- Single unified form -->
			<form method="post" action="<?php echo esc_url( admin_url( 'options-general.php?page=msm-sitemap' ) ); ?>">
				<?php wp_nonce_field( 'msm-sitemap-action' ); ?>
				<input type="hidden" name="action" value="Save Content Provider Settings">

				<!-- Automatic Updates -->
				<div style="margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #eee;">
					<h3 style="margin: 0 0 10px 0;"><?php esc_html_e( 'Automatic Updates', 'msm-sitemap' ); ?></h3>
					<p class="description" style="margin-bottom: 10px;">
						<?php esc_html_e( 'Automatically check for content changes and update sitemaps.', 'msm-sitemap' ); ?>
					</p>
					<div style="display: flex; align-items: center; gap: 20px; flex-wrap: wrap;">
						<label style="display: flex; align-items: center; gap: 8px;">
							<input type="checkbox" name="automatic_updates_enabled" value="1" <?php checked( $cron_status['enabled'] ); ?>>
							<strong><?php esc_html_e( 'Enable automatic updates', 'msm-sitemap' ); ?></strong>
						</label>
						<label style="display: flex; align-items: center; gap: 8px;">
							<?php esc_html_e( 'Check every:', 'msm-sitemap' ); ?>
							<select name="cron_frequency" style="width: auto;">
								<option value="5min" <?php selected( $current_frequency, '5min' ); ?>><?php esc_html_e( '5 minutes', 'msm-sitemap' ); ?></option>
								<option value="15min" <?php selected( $current_frequency, '15min' ); ?>><?php esc_html_e( '15 minutes', 'msm-sitemap' ); ?></option>
								<option value="30min" <?php selected( $current_frequency, '30min' ); ?>><?php esc_html_e( '30 minutes', 'msm-sitemap' ); ?></option>
								<option value="hourly" <?php selected( $current_frequency, 'hourly' ); ?>><?php esc_html_e( '1 hour', 'msm-sitemap' ); ?></option>
							</select>
						</label>
					</div>
					<?php if ( $cron_status['enabled'] && $cron_status['next_scheduled'] ) : ?>
					<p style="margin: 10px 0 0 0; color: #666; font-size: 13px;">
						<?php
						printf(
							/* translators: %s: time until next check */
							esc_html__( 'Next check: in %s', 'msm-sitemap' ),
							esc_html( human_time_diff( time(), $cron_status['next_scheduled'] ) )
						);
						?>
					</p>
					<?php endif; ?>
				</div>

				<!-- Content Configuration -->
				<div style="margin-bottom: 20px;">
					<h3 style="margin: 0 0 15px 0;"><?php esc_html_e( 'Content to Include', 'msm-sitemap' ); ?></h3>

					<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
						<!-- Post Types -->
						<div>
							<h4 style="margin: 0 0 10px 0;"><?php esc_html_e( 'Content Types', 'msm-sitemap' ); ?></h4>
							<?php foreach ( $post_types as $post_type ) : ?>
								<?php
								if ( 'attachment' === $post_type->name || $post_type->hierarchical ) {
									continue;}
								?>
								<label style="display: block; margin-bottom: 6px;">
									<input type="checkbox" name="enabled_post_types[]" value="<?php echo esc_attr( $post_type->name ); ?>" <?php checked( in_array( $post_type->name, $enabled_post_types, true ) ); ?>>
									<?php echo esc_html( $post_type->labels->name ); ?>
								</label>
							<?php endforeach; ?>
						</div>

						<!-- Page Types -->
						<div>
							<h4 style="margin: 0 0 10px 0;"><?php esc_html_e( 'Page Types', 'msm-sitemap' ); ?></h4>
							<?php foreach ( $hierarchical_types as $page_type ) : ?>
								<?php
								if ( 'attachment' === $page_type->name ) {
									continue;}
								?>
								<label style="display: block; margin-bottom: 6px;">
									<input type="checkbox" name="enabled_page_types[]" value="<?php echo esc_attr( $page_type->name ); ?>" <?php checked( in_array( $page_type->name, $enabled_page_types, true ) ); ?>>
									<?php echo esc_html( $page_type->labels->name ); ?>
								</label>
							<?php endforeach; ?>
						</div>

						<!-- Taxonomies -->
						<div>
							<h4 style="margin: 0 0 10px 0;"><?php esc_html_e( 'Taxonomies', 'msm-sitemap' ); ?></h4>
							<?php foreach ( $available_taxonomies as $taxonomy ) : ?>
								<label style="display: block; margin-bottom: 6px;">
									<input type="checkbox" name="enabled_taxonomies[]" value="<?php echo esc_attr( $taxonomy->name ); ?>" <?php checked( in_array( $taxonomy->name, $enabled_taxonomies, true ) ); ?>>
									<?php echo esc_html( $taxonomy->labels->name ); ?>
								</label>
							<?php endforeach; ?>
						</div>

						<!-- Images -->
						<div>
							<h4 style="margin: 0 0 10px 0;"><?php esc_html_e( 'Images', 'msm-sitemap' ); ?></h4>
							<label style="display: block; margin-bottom: 6px;">
								<input type="checkbox" name="images_provider_enabled" value="1" <?php checked( '1' === $images_settings['include_images'] ); ?> onchange="document.getElementById('image-options-alt').style.display = this.checked ? 'block' : 'none';">
								<?php esc_html_e( 'Include images', 'msm-sitemap' ); ?>
							</label>
							<div id="image-options-alt" style="margin-left: 20px; <?php echo '1' !== $images_settings['include_images'] ? 'display: none;' : ''; ?>">
								<label style="display: block; margin-bottom: 4px; font-size: 13px;">
									<input type="checkbox" name="include_featured_images" value="1" <?php checked( '1' === $images_settings['featured_images'] ); ?>>
									<?php esc_html_e( 'Featured images', 'msm-sitemap' ); ?>
								</label>
								<label style="display: block; margin-bottom: 4px; font-size: 13px;">
									<input type="checkbox" name="include_content_images" value="1" <?php checked( '1' === $images_settings['content_images'] ); ?>>
									<?php esc_html_e( 'Content images', 'msm-sitemap' ); ?>
								</label>
							</div>
						</div>

						<!-- Authors -->
						<div>
							<h4 style="margin: 0 0 10px 0;"><?php esc_html_e( 'Authors', 'msm-sitemap' ); ?></h4>
							<label style="display: block; margin-bottom: 6px;">
								<input type="checkbox" name="authors_provider_enabled" value="1" <?php checked( $authors_enabled ); ?>>
								<?php esc_html_e( 'Include author archives', 'msm-sitemap' ); ?>
							</label>
						</div>
					</div>
				</div>

				<div style="padding-top: 15px; border-top: 1px solid #eee;">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'msm-sitemap' ); ?></button>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Render stats section with full insight cards
	 */
	private function render_stats_section( array $comprehensive_stats ): void {
		$show_stats = $this->get_user_section_visibility( 'stats' );

		$overview    = $comprehensive_stats['overview'];
		$health      = $comprehensive_stats['health'];
		$url_counts  = $comprehensive_stats['url_counts'];
		$coverage    = $comprehensive_stats['coverage'];
		$performance = $comprehensive_stats['performance'];
		$storage     = $comprehensive_stats['storage'];
		$timeline    = $comprehensive_stats['timeline'];

		// Extract date range from timeline for coverage display
		$earliest_date = $timeline['date_range']['start'] ?? '';
		$latest_date   = $timeline['date_range']['end'] ?? '';
		?>
		<div id="msm-stats-section" style="background: #fff; border: 1px solid #c3c4c7; padding: 20px; margin-bottom: 20px; <?php echo $show_stats ? '' : 'display: none;'; ?>">
			<h2 style="margin-top: 0;"><?php esc_html_e( 'Statistics', 'msm-sitemap' ); ?></h2>

			<!-- Stats cards using same styling as main UI -->
			<div class="stats-insights insight-grid">
				<!-- Health Card -->
				<div class="insight-card">
					<h3><?php esc_html_e( 'Health', 'msm-sitemap' ); ?></h3>
					<div class="insight-content">
						<div class="insight-item">
							<span class="insight-label"><?php esc_html_e( 'Total Sitemaps:', 'msm-sitemap' ); ?></span>
							<span class="insight-value"><?php echo esc_html( number_format( $overview['total_sitemaps'] ) ); ?></span>
						</div>
						<div class="insight-item stat-descriptor">
							<?php esc_html_e( 'Total number of daily sitemaps generated', 'msm-sitemap' ); ?>
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
							<?php esc_html_e( 'Percentage of expected sitemaps successfully generated', 'msm-sitemap' ); ?>
						</div>
						<?php if ( $health['average_generation_time'] > 0 ) : ?>
						<div class="insight-item">
							<span class="insight-label"><?php esc_html_e( 'Avg Generation Time:', 'msm-sitemap' ); ?></span>
							<span class="insight-value"><?php echo esc_html( $health['average_generation_time'] ); ?>s</span>
						</div>
						<div class="insight-item stat-descriptor">
							<?php esc_html_e( 'Average time to generate a sitemap', 'msm-sitemap' ); ?>
						</div>
						<?php endif; ?>
					</div>
				</div>

				<!-- Trends Card -->
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
							<?php esc_html_e( 'Whether URLs per sitemap is growing, declining, or stable', 'msm-sitemap' ); ?>
						</div>
						<div class="insight-item">
							<span class="insight-label"><?php esc_html_e( 'Average URLs per Sitemap:', 'msm-sitemap' ); ?></span>
							<span class="insight-value"><?php echo esc_html( number_format( $performance['recent_average'] ) ); ?></span>
						</div>
						<div class="insight-item stat-descriptor">
							<?php esc_html_e( 'Average URLs per sitemap in the selected date range', 'msm-sitemap' ); ?>
						</div>
						<?php if ( $url_counts['max_urls'] > 0 ) : ?>
						<div class="insight-item">
							<span class="insight-label"><?php esc_html_e( 'Peak URLs:', 'msm-sitemap' ); ?></span>
							<span class="insight-value"><?php echo esc_html( number_format( $url_counts['max_urls'] ) ); ?></span>
						</div>
						<div class="insight-item stat-descriptor">
							<?php esc_html_e( 'Highest number of URLs in a single sitemap', 'msm-sitemap' ); ?>
						</div>
						<?php endif; ?>
					</div>
				</div>

				<!-- Coverage Card -->
				<div class="insight-card">
					<h3><?php esc_html_e( 'Coverage', 'msm-sitemap' ); ?></h3>
					<div class="insight-content">
						<?php if ( ! empty( $earliest_date ) && ! empty( $latest_date ) ) : ?>
						<div class="insight-item">
							<span class="insight-label"><?php esc_html_e( 'Date Range:', 'msm-sitemap' ); ?></span>
							<span class="insight-value"><?php echo esc_html( $earliest_date ); ?> - <?php echo esc_html( $latest_date ); ?></span>
						</div>
						<div class="insight-item stat-descriptor">
							<?php esc_html_e( 'Full date range from first to last sitemap', 'msm-sitemap' ); ?>
						</div>
						<?php endif; ?>
						<div class="insight-item">
							<span class="insight-label"><?php esc_html_e( 'Days Covered:', 'msm-sitemap' ); ?></span>
							<span class="insight-value"><?php echo esc_html( number_format( $coverage['covered_days'] ?? 0 ) ); ?> / <?php echo esc_html( number_format( $coverage['total_days'] ?? 0 ) ); ?></span>
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

				<!-- Storage Card -->
				<div class="insight-card">
					<h3><?php esc_html_e( 'Storage', 'msm-sitemap' ); ?></h3>
					<div class="insight-content">
						<div class="insight-item">
							<span class="insight-label"><?php esc_html_e( 'Total Size:', 'msm-sitemap' ); ?></span>
							<span class="insight-value"><?php echo esc_html( $storage['total_size_human'] ); ?></span>
						</div>
						<div class="insight-item stat-descriptor">
							<?php esc_html_e( 'Total database storage used by sitemap posts', 'msm-sitemap' ); ?>
						</div>
						<div class="insight-item">
							<span class="insight-label"><?php esc_html_e( 'Average Size:', 'msm-sitemap' ); ?></span>
							<span class="insight-value"><?php echo esc_html( $storage['average_size_human'] ); ?></span>
						</div>
						<div class="insight-item stat-descriptor">
							<?php esc_html_e( 'Average storage per individual sitemap', 'msm-sitemap' ); ?>
						</div>
						<div class="insight-item">
							<span class="insight-label"><?php esc_html_e( 'Content Coverage:', 'msm-sitemap' ); ?></span>
							<span class="insight-value"><?php echo esc_html( $coverage['coverage_quality'] ?? 0 ); ?>%</span>
						</div>
						<div class="insight-item stat-descriptor">
							<?php esc_html_e( 'Percentage of site content included in sitemaps', 'msm-sitemap' ); ?>
						</div>
					</div>
				</div>

			</div>
		</div>
		<?php
	}

	/**
	 * Render danger zone section with working buttons
	 */
	private function render_danger_zone_section(): void {
		$show_danger_zone = $this->get_user_section_visibility( 'danger_zone' );

		$cron_status                = $this->cron_management->get_cron_status();
		$sitemap_create_in_progress = (bool) get_option( 'msm_generation_in_progress' );
		$sitemap_halt_in_progress   = (bool) get_option( 'msm_sitemap_stop_generation' );

		$buttons_enabled = $cron_status['enabled'] && ! $sitemap_create_in_progress && ! $sitemap_halt_in_progress;
		$reset_disabled  = $sitemap_create_in_progress || $sitemap_halt_in_progress;
		?>
		<div id="msm-danger-zone-section" style="border: 1px solid #dc3232; border-radius: 4px; padding: 15px; background-color: #fef7f7; <?php echo $show_danger_zone ? '' : 'display: none;'; ?>">
			<div style="display: flex; align-items: center; margin-bottom: 15px;">
				<h2 style="margin: 0; color: #dc3232;"><?php esc_html_e( 'Danger Zone', 'msm-sitemap' ); ?></h2>
				<button type="button" id="danger-zone-toggle" class="button button-secondary" style="font-size: 12px; margin-left: 10px;">
					<span class="dashicons dashicons-arrow-down-alt2" id="danger-zone-icon" style="vertical-align: middle;"></span>
					<span id="danger-zone-toggle-text" style="vertical-align: middle;"><?php esc_html_e( 'Show', 'msm-sitemap' ); ?></span>
				</button>
			</div>

			<div id="danger-zone-content" style="display: none; grid-template-columns: 1fr 1fr; gap: 20px;">
				<!-- Full Generation Section -->
				<div style="padding: 15px; background-color: #fff; border: 1px solid #dc3232; border-radius: 4px;">
					<h3 style="margin: 0 0 10px 0; color: #dc3232;">
						<?php esc_html_e( 'Full Generation', 'msm-sitemap' ); ?>
					</h3>
					<p style="margin: 0 0 15px 0; color: #666; font-size: 13px;">
						<?php esc_html_e( 'Regenerate ALL sitemaps, even those already up to date.', 'msm-sitemap' ); ?>
					</p>

					<form action="<?php echo esc_url( admin_url( 'options-general.php?page=msm-sitemap' ) ); ?>" method="post" style="display: inline;">
						<?php wp_nonce_field( 'msm-sitemap-action' ); ?>
						<?php if ( $buttons_enabled ) : ?>
							<input type="submit" name="action" class="button button-secondary" value="<?php esc_attr_e( 'Generate All Sitemaps (Force)', 'msm-sitemap' ); ?>">
						<?php elseif ( $sitemap_create_in_progress && ! $sitemap_halt_in_progress ) : ?>
							<input type="submit" name="action" class="button button-secondary" value="<?php esc_attr_e( 'Stop full sitemaps generation...', 'msm-sitemap' ); ?>">
						<?php elseif ( ! $cron_status['enabled'] ) : ?>
							<p style="margin: 0; color: #666; font-size: 13px; font-style: italic;">
								<?php esc_html_e( 'Enable automatic updates to use this feature.', 'msm-sitemap' ); ?>
							</p>
						<?php else : ?>
							<p style="margin: 0; color: #666; font-size: 13px; font-style: italic;">
								<?php esc_html_e( 'Generation is being halted...', 'msm-sitemap' ); ?>
							</p>
						<?php endif; ?>
					</form>
				</div>

				<!-- Reset Section -->
				<div style="padding: 15px; background-color: #fff; border: 1px solid #dc3232; border-radius: 4px;">
					<h3 style="margin: 0 0 8px 0; color: #dc3232;">
						<?php esc_html_e( 'Reset Sitemap Data', 'msm-sitemap' ); ?>
					</h3>
					<p style="margin: 0; color: #666; font-size: 13px;">
						<?php esc_html_e( 'This action will permanently delete:', 'msm-sitemap' ); ?>
					</p>
					<ul style="margin: 8px 0 0 20px; color: #666; font-size: 13px;">
						<li><?php esc_html_e( 'All sitemap post entries', 'msm-sitemap' ); ?></li>
						<li><?php esc_html_e( 'All sitemap metadata and statistics', 'msm-sitemap' ); ?></li>
						<li><?php esc_html_e( 'All processing options and progress', 'msm-sitemap' ); ?></li>
					</ul>
					<p style="margin: 8px 0 12px 0; color: #666; font-size: 13px; font-style: italic;">
						<?php esc_html_e( 'This cannot be undone. Your sitemaps will need to be regenerated.', 'msm-sitemap' ); ?>
					</p>

					<form action="<?php echo esc_url( admin_url( 'options-general.php?page=msm-sitemap' ) ); ?>" method="post" style="display: inline;" onsubmit="return confirm('<?php esc_attr_e( 'Are you sure you want to reset all sitemap data? This action cannot be undone and will delete all sitemaps, metadata, and statistics.', 'msm-sitemap' ); ?>');">
						<?php wp_nonce_field( 'msm-sitemap-action' ); ?>
						<input type="submit" name="action" class="button button-secondary" value="<?php esc_attr_e( 'Reset Sitemap Data', 'msm-sitemap' ); ?>" <?php disabled( $reset_disabled ); ?>>
					</form>
				</div>
			</div>
		</div>
		<?php
	}
}
