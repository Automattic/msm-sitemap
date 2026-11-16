<?php
/**
 * Admin UI rendering class
 *
 * @package MSM_Sitemap
 */

namespace Automattic\MSM_Sitemap\Admin;

use Automattic\MSM_Sitemap\Cron_Service;
use Automattic\MSM_Sitemap\Site;

/**
 * Handles all admin page UI rendering
 */
class UI {

	/**
	 * Render the complete admin options page
	 */
	public static function render_options_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'msm-sitemap' ) );
		}

		// Check if sitemaps are enabled
		if ( ! Site::are_sitemaps_enabled() ) {
			self::render_private_site_message();
			return;
		}

		// Handle form submissions
		self::handle_form_submissions();

		// Render the page content
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p>
				<a href="<?php echo esc_url( Site::get_sitemap_index_url() ); ?>" target="_blank" class="button button-secondary">
					<?php esc_html_e( 'View XML Sitemap Index', 'msm-sitemap' ); ?>
					<span class="dashicons dashicons-external" style="vertical-align: middle;" aria-hidden="true"></span>
					<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'msm-sitemap' ); ?></span>
				</a>
			</p>

			<?php self::render_stats_section(); ?>
			<?php self::render_latest_sitemaps_section(); ?>
			<?php self::render_cron_section(); ?>
			<?php self::render_generate_section(); ?>
			
		</div>
		<div id="tooltip">
			<strong class="content"></strong>
			<span class="url-label"
				<?php /* translators: %s: Number of indexed URLs. Keep the %s placeholder as-is; it is replaced in JavaScript. */ ?>
				data-template="<?php esc_attr_e( 'Indexed URLs: %s', 'msm-sitemap' ); ?>"
			></span>
		</div>
		<?php
	}

	/**
	 * Handle form submissions and action processing
	 */
	private static function handle_form_submissions() {
		if ( ! isset( $_POST['action'] ) ) {
			return;
		}

		check_admin_referer( 'msm-sitemap-action' );
		
		$action = sanitize_text_field( $_POST['action'] );
		
		// Route to appropriate action handler
		switch ( $action ) {
			case 'enable-cron':
				Action_Handlers::handle_enable_cron();
				break;
			case 'disable-cron':
				Action_Handlers::handle_disable_cron();
				break;
			case 'generate-full':
				Action_Handlers::handle_generate_full();
				break;
			case 'generate-latest':
				Action_Handlers::handle_generate_from_latest();
				break;
			case 'halt-generation':
				Action_Handlers::handle_halt_generation();
				break;
			case 'reset-data':
				Action_Handlers::handle_reset_data();
				break;
		}
	}

	/**
	 * Render private site message
	 */
	private static function render_private_site_message() {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<?php Notifications::show_error( __( 'Oops! Sitemaps are not supported on private sites. Please make your site public and try again.', 'msm-sitemap' ) ); ?>
		</div>
		<?php
	}

	/**
	 * Render the stats section
	 */
	private static function render_stats_section() {
		$sitemap_update_last_run = get_option( 'msm_sitemap_update_last_run' );
		?>
		<div class="stats-container">
			<div class="stats-box"><strong id="sitemap-count"><?php echo esc_html( number_format_i18n( \Metro_Sitemap::count_sitemaps() ) ); ?></strong><?php esc_html_e( 'Sitemaps', 'msm-sitemap' ); ?></div>
			<div class="stats-box"><strong id="sitemap-indexed-url-count"><?php echo esc_html( number_format_i18n( \Metro_Sitemap::get_total_indexed_url_count() ) ); ?></strong><?php esc_html_e( 'Indexed URLs', 'msm-sitemap' ); ?></div>
			<div class="stats-footer"><span><span class="noticon noticon-time" aria-hidden="true"></span>
			<?php
			printf(
				/* translators: %s: How long ago the sitemaps were updated, for example "5 mins ago". */
				esc_html__( 'Updated %s', 'msm-sitemap' ),
				'<strong>' . esc_html(
					sprintf(
						/* translators: %s: Human-readable time difference, for example "5 mins". */
						__( '%s ago', 'msm-sitemap' ),
						human_time_diff( (int) $sitemap_update_last_run )
					)
				) . '</strong>'
			);
			?>
			</span></div>
		</div>
		<?php
	}

	/**
	 * Render the latest sitemaps section
	 */
	private static function render_latest_sitemaps_section() {
		?>
		<h2><?php esc_html_e( 'Latest Sitemaps', 'msm-sitemap' ); ?></h2>
		<div class="stats-container stats-placeholder"></div>
		<div id="stats-graph-summary">
		<?php
		printf(
			/* translators: 1: Highest number of indexed URLs in a day, 2: Date of that day. */
			esc_html__( 'Max: %1$s on %2$s.', 'msm-sitemap' ),
			'<span id="stats-graph-max"></span>',
			'<span id="stats-graph-max-date"></span>'
		);
		echo ' ';
		echo esc_html(
			sprintf(
				/* translators: %s: Number of days shown in the graph. */
				_n( 'Showing the last %s day.', 'Showing the last %s days.', \Metro_Sitemap::STATS_NUM_DAYS, 'msm-sitemap' ),
				number_format_i18n( \Metro_Sitemap::STATS_NUM_DAYS )
			)
		);
		?>
		</div>
		<?php
	}

	/**
	 * Render the cron management section
	 */
	private static function render_cron_section() {
		$cron_status    = Cron_Service::get_cron_status();
		$cron_enabled   = $cron_status['enabled'];
		$next_scheduled = $cron_status['next_scheduled'];
		?>
		<h2><?php esc_html_e( 'Cron Management', 'msm-sitemap' ); ?></h2>
		<p><strong><?php esc_html_e( 'Automatic Sitemap Updates:', 'msm-sitemap' ); ?></strong> 
			<?php if ( $cron_enabled ) : ?>
				<span style="color: #007017;"><span aria-hidden="true">✅</span> <?php esc_html_e( 'Enabled', 'msm-sitemap' ); ?></span>
			<?php else : ?>
				<span style="color: #b32d2e;"><span aria-hidden="true">❌</span> <?php esc_html_e( 'Disabled', 'msm-sitemap' ); ?></span>
			<?php endif; ?>
		</p>
		<?php if ( $next_scheduled ) : ?>
			<p><strong><?php esc_html_e( 'Next Update:', 'msm-sitemap' ); ?></strong> <?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next_scheduled ) ); ?></p>
		<?php endif; ?>
		
		<form action="<?php echo menu_page_url( 'metro-sitemap', false ); ?>" method="post" style="margin-bottom: 20px;">
			<?php wp_nonce_field( 'msm-sitemap-action' ); ?>
			<?php if ( ! $cron_enabled ) : ?>
				<button type="submit" name="action" value="enable-cron" class="button-primary"><?php esc_html_e( 'Enable Automatic Updates', 'msm-sitemap' ); ?></button>
			<?php else : ?>
				<button type="submit" name="action" value="disable-cron" class="button-secondary"><?php esc_html_e( 'Disable Automatic Updates', 'msm-sitemap' ); ?></button>
			<?php endif; ?>
		</form>
		<?php
	}

	/**
	 * Render the generate section with buttons
	 */
	private static function render_generate_section() {
		$cron_status                = Cron_Service::get_cron_status();
		$sitemap_create_in_progress = (bool) get_option( 'msm_sitemap_create_in_progress' );
		$sitemap_halt_in_progress   = (bool) get_option( 'msm_stop_processing' );
		
		// Determine if generate buttons should be enabled
		$buttons_enabled = $cron_status['enabled'] && ! $sitemap_create_in_progress;
		
		// Determine sitemap status text
		$sitemap_create_status = apply_filters(
			'msm_sitemap_create_status',
			$sitemap_create_in_progress ? __( 'Running', 'msm-sitemap' ) : __( 'Not Running', 'msm-sitemap' )
		);
		
		?>
		<form action="<?php echo menu_page_url( 'metro-sitemap', false ); ?>" method="post">
			<?php wp_nonce_field( 'msm-sitemap-action' ); ?>
			
			<h2><?php esc_html_e( 'Generate', 'msm-sitemap' ); ?></h2>
			<p><strong><?php esc_html_e( 'Sitemap Creation Status:', 'msm-sitemap' ); ?></strong> <?php echo esc_html( $sitemap_create_status ); ?></p>
			
			<?php if ( $buttons_enabled ) : ?>
				<button type="submit" name="action" value="generate-full" class="button-secondary"><?php esc_html_e( 'Generate from all articles', 'msm-sitemap' ); ?></button>
				<button type="submit" name="action" value="generate-latest" class="button-secondary"><?php esc_html_e( 'Generate from recently modified posts', 'msm-sitemap' ); ?></button>
			<?php else : ?>
				<button type="submit" name="action" value="generate-full" class="button-secondary button-disabled" disabled><?php esc_html_e( 'Generate from all articles', 'msm-sitemap' ); ?></button>
				<button type="submit" name="action" value="generate-latest" class="button-secondary button-disabled" disabled><?php esc_html_e( 'Generate from recently modified posts', 'msm-sitemap' ); ?></button>
			<?php endif; ?>
			
			<?php if ( $sitemap_create_in_progress && ! $sitemap_halt_in_progress ) : ?>
				<button type="submit" name="action" value="halt-generation" class="button-secondary"><?php esc_html_e( 'Halt Sitemap Generation', 'msm-sitemap' ); ?></button>
			<?php endif; ?>
			
			<?php if ( ! $sitemap_create_in_progress && ! $sitemap_halt_in_progress ) : ?>
				<button type="submit" name="action" value="reset-data" class="button-secondary"><?php esc_html_e( 'Reset Sitemap Data', 'msm-sitemap' ); ?></button>
			<?php endif; ?>
			
			<?php self::render_generate_note(); ?>
		</form>
		<?php
	}

	/**
	 * Render a note about enabling automatic updates when generate buttons are disabled
	 */
	private static function render_generate_note() {
		$cron_status = Cron_Service::get_cron_status();
		
		if ( ! $cron_status['enabled'] ) {
			echo '<p style="margin-top: 10px; color: #666; font-style: italic;">';
			echo esc_html__( 'Note: Automatic updates must be enabled to use the generate functions.', 'msm-sitemap' );
			echo '</p>';
		}
	}
} 
