<?php
/**
 * Sitemap Admin UI
 *
 * @package Automattic\MSM_Sitemap
 */

declare(strict_types=1);

namespace Automattic\MSM_Sitemap;

/**
 * Admin UI class for sitemap cron management.
 * 
 * Handles all admin interface logic for cron management.
 */
class Admin_UI {

	/**
	 * Setup admin UI hooks
	 */
	public static function setup() {
		add_filter( 'msm_sitemap_actions', array( __CLASS__, 'add_cron_actions' ) );
		add_action( 'msm_sitemap_action-enable_cron', array( __CLASS__, 'handle_enable_cron' ) );
		add_action( 'msm_sitemap_action-disable_cron', array( __CLASS__, 'handle_disable_cron' ) );
	}

	/**
	 * Add cron management actions to the admin page
	 *
	 * @param array $actions The actions to show on the admin page.
	 * @return array
	 */
	public static function add_cron_actions( $actions ) {
		// No actions for private blogs
		if ( ! \Metro_Sitemap::is_blog_public() ) {
			return $actions;
		}

		$cron_status = Cron_Service::get_cron_status();

		// Add cron management actions
		if ( ! $cron_status['enabled'] ) {
			$actions['enable_cron'] = array(
				'text'    => __( 'Enable Automatic Updates', 'msm-sitemap' ),
				'enabled' => true,
			);
		} else {
			$actions['disable_cron'] = array(
				'text'    => __( 'Disable Automatic Updates', 'msm-sitemap' ),
				'enabled' => true,
			);
		}

		return $actions;
	}

	/**
	 * Handle enable cron action from admin interface
	 */
	public static function handle_enable_cron() {
		$result = Cron_Service::enable_cron();
		if ( $result ) {
			\Metro_Sitemap::show_action_message( __( '✅ Automatic sitemap updates enabled successfully.', 'msm-sitemap' ) );
		} else {
			\Metro_Sitemap::show_action_message( __( '⚠️ Automatic updates are already enabled.', 'msm-sitemap' ), 'warning' );
		}
	}

	/**
	 * Handle disable cron action from admin interface
	 */
	public static function handle_disable_cron() {
		$result = Cron_Service::disable_cron();
		if ( $result ) {
			\Metro_Sitemap::show_action_message( __( '✅ Automatic sitemap updates disabled successfully.', 'msm-sitemap' ) );
		} else {
			\Metro_Sitemap::show_action_message( __( '⚠️ Automatic updates are already disabled.', 'msm-sitemap' ), 'warning' );
		}
	}

	/**
	 * Render the cron management section for the admin page
	 */
	public static function render_cron_section() {
		$cron_status = Cron_Service::get_cron_status();
		$cron_enabled = $cron_status['enabled'];
		$next_scheduled = $cron_status['next_scheduled'];
		?>
		<h2><?php esc_html_e( 'Cron Management', 'msm-sitemap' ); ?></h2>
		<p><strong><?php esc_html_e( 'Automatic Sitemap Updates:', 'msm-sitemap' ); ?></strong> 
			<?php echo $cron_enabled ? '<span style="color: green;">✅ Enabled</span>' : '<span style="color: red;">❌ Disabled</span>'; ?>
		</p>
		<?php if ( $next_scheduled ) : ?>
			<p><strong><?php esc_html_e( 'Next Update:', 'msm-sitemap' ); ?></strong> <?php echo esc_html( date( 'Y-m-d H:i:s T', $next_scheduled ) ); ?></p>
		<?php endif; ?>
		
		<form action="<?php echo menu_page_url( 'metro-sitemap', false ); ?>" method="post" style="margin-bottom: 20px;">
			<?php wp_nonce_field( 'msm-sitemap-action' ); ?>
			<?php if ( ! $cron_enabled ) : ?>
				<input type="submit" name="action" class="button-primary" value="<?php esc_attr_e( 'Enable Automatic Updates', 'msm-sitemap' ); ?>">
			<?php else : ?>
				<input type="submit" name="action" class="button-secondary" value="<?php esc_attr_e( 'Disable Automatic Updates', 'msm-sitemap' ); ?>">
			<?php endif; ?>
		</form>
		<?php
	}
} 
