<?php
/**
 * Admin Notices Controller
 *
 * Handles all admin notices and database upgrade functionality.
 *
 * @package MSKD\Admin
 * @since   2.0.0
 */

namespace MSKD\Admin;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Admin_Notices
 *
 * Controller for admin notices and database upgrades.
 */
class Admin_Notices {

	/**
	 * Plugin pages prefix.
	 */
	const PAGE_PREFIX = 'mskd-';

	/**
	 * Initialize notice hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_notices', array( $this, 'show_cron_notice' ) );
		add_action( 'admin_notices', array( $this, 'show_share_notice' ) );
		add_action( 'admin_notices', array( $this, 'show_database_upgrade_notice' ) );
		add_action( 'admin_init', array( $this, 'handle_database_upgrade' ) );
	}

	/**
	 * Show WP-Cron warning notice only on plugin pages.
	 *
	 * @return void
	 */
	public function show_cron_notice(): void {
		if ( ! $this->is_plugin_page() ) {
			return;
		}

		// Check if WP-Cron is disabled.
		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
			return;
		}

		?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'Recommendation for Mail System:', 'mail-system-by-katsarov-design' ); ?></strong>
				<?php esc_html_e( 'For more reliable email sending, we recommend using system cron instead of WP-Cron.', 'mail-system-by-katsarov-design' ); ?>
			</p>
			<p>
				<?php esc_html_e( 'Add to wp-config.php:', 'mail-system-by-katsarov-design' ); ?>
				<code>define('DISABLE_WP_CRON', true);</code>
			</p>
			<p>
				<?php esc_html_e( 'And set up system cron:', 'mail-system-by-katsarov-design' ); ?>
				<code>* * * * * php <?php echo esc_html( ABSPATH . 'wp-cron.php' ); ?></code>
			</p>
		</div>
		<?php
	}

	/**
	 * Show share notice asking users to share the plugin.
	 *
	 * @return void
	 */
	public function show_share_notice(): void {
		// Check if notice was already dismissed.
		if ( get_option( 'mskd_share_notice_dismissed' ) ) {
			return;
		}

		// Only show to users who can manage options.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! $this->is_plugin_page() ) {
			return;
		}

		// Only show after at least 3 campaigns have been created.
		$campaign_count = (int) get_option( 'mskd_total_campaigns_created', 0 );
		if ( $campaign_count < 3 ) {
			return;
		}

		?>
		<div class="notice notice-info mskd-share-notice" style="padding: 15px;">
			<p style="font-size: 14px; margin-bottom: 10px;">
				<strong><?php esc_html_e( 'Enjoying Mail System by Katsarov Design?', 'mail-system-by-katsarov-design' ); ?></strong>
				<?php esc_html_e( 'If you like this plugin, please share it with your friends!', 'mail-system-by-katsarov-design' ); ?>
			</p>
			<p>
				<a href="#" class="button button-primary mskd-share-dismiss" data-nonce="<?php echo esc_attr( wp_create_nonce( 'mskd_dismiss_share_notice' ) ); ?>">
					<?php esc_html_e( 'Yes, of course!', 'mail-system-by-katsarov-design' ); ?>
				</a>
				<a href="https://github.com/katsar0v/mail-system-by-katsarov-design" target="_blank" class="button" style="margin-left: 10px;">
					<?php esc_html_e( 'Nah, I do not like the plugin', 'mail-system-by-katsarov-design' ); ?>
				</a>
			</p>
		</div>
		<script type="text/javascript">
			jQuery(document).ready(function($) {
				$('.mskd-share-dismiss').on('click', function(e) {
					e.preventDefault();
					var $notice = $(this).closest('.mskd-share-notice');
					var nonce = $(this).data('nonce');

					$.post(ajaxurl, {
						action: 'mskd_dismiss_share_notice',
						nonce: nonce
					}, function() {
						$notice.fadeOut();
					});
				});
			});
		</script>
		<?php
	}

	/**
	 * Show database upgrade notice when schema is outdated.
	 *
	 * @return void
	 */
	public function show_database_upgrade_notice(): void {
		// Only show to users who can manage options.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! $this->is_plugin_page() ) {
			return;
		}

		// Check if database needs upgrade.
		$installed_version = get_option( 'mskd_db_version', '1.0.0' );
		$required_version  = \MSKD_Activator::DB_VERSION;

		if ( version_compare( $installed_version, $required_version, '>=' ) ) {
			// Also verify the opt_in_token column exists (in case upgrade failed silently).
			if ( ! $this->is_database_schema_valid() ) {
				$this->render_database_repair_notice();
			}
			return;
		}

		$upgrade_url = wp_nonce_url(
			add_query_arg( 'mskd_upgrade_db', '1', admin_url( 'admin.php?page=' . self::PAGE_PREFIX . 'dashboard' ) ),
			'mskd_upgrade_db'
		);

		?>
		<div class="notice notice-error">
			<p>
				<strong><?php esc_html_e( 'Mail System Database Update Required', 'mail-system-by-katsarov-design' ); ?></strong>
			</p>
			<p>
				<?php
				printf(
					/* translators: 1: Current DB version, 2: Required DB version */
					esc_html__( 'Your database schema (version %1$s) is outdated. Version %2$s is required for the plugin to work correctly.', 'mail-system-by-katsarov-design' ),
					esc_html( $installed_version ),
					esc_html( $required_version )
				);
				?>
			</p>
			<p>
				<a href="<?php echo esc_url( $upgrade_url ); ?>" class="button button-primary">
					<?php esc_html_e( 'Update Database Now', 'mail-system-by-katsarov-design' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Handle database upgrade action.
	 *
	 * @return void
	 */
	public function handle_database_upgrade(): void {
		// Show success message if redirected after upgrade.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Just displaying a message.
		if ( isset( $_GET['mskd_db_updated'] ) && '1' === $_GET['mskd_db_updated'] ) {
			add_action(
				'admin_notices',
				function () {
					?>
					<div class="notice notice-success is-dismissible">
						<p>
							<strong><?php esc_html_e( 'Mail System:', 'mail-system-by-katsarov-design' ); ?></strong>
							<?php esc_html_e( 'Database has been updated successfully.', 'mail-system-by-katsarov-design' ); ?>
						</p>
					</div>
					<?php
				}
			);
		}

		// Show error message if the database repair failed.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Just displaying a message.
		if ( isset( $_GET['mskd_db_error'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Just displaying a message.
			$db_error = sanitize_text_field( wp_unslash( $_GET['mskd_db_error'] ) );
			add_action(
				'admin_notices',
				function () use ( $db_error ) {
					?>
					<div class="notice notice-error">
						<p>
							<strong><?php esc_html_e( 'Mail System:', 'mail-system-by-katsarov-design' ); ?></strong>
							<?php esc_html_e( 'Database repair failed. One or more required tables or columns could not be created. Please deactivate and reactivate the plugin, or contact your hosting provider if the issue persists.', 'mail-system-by-katsarov-design' ); ?>
						</p>
						<?php if ( '1' !== $db_error ) : ?>
						<p><em><?php echo esc_html( $db_error ); ?></em></p>
						<?php endif; ?>
					</div>
					<?php
				}
			);
		}

		if ( ! isset( $_GET['mskd_upgrade_db'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Verify nonce.
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'mskd_upgrade_db' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'mail-system-by-katsarov-design' ) );
		}

		// Run full activation: uses dbDelta to create missing tables and columns.
		\MSKD_Activator::activate();

		// Verify the schema is now valid.
		if ( ! $this->is_database_schema_valid() ) {
			global $wpdb;
			$db_error    = $wpdb->last_error;
			$error_param = $db_error ? '&mskd_db_error=' . rawurlencode( $db_error ) : '&mskd_db_error=1';
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_PREFIX . 'dashboard' . $error_param ) );
			exit;
		}

		// Redirect to remove the query args.
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_PREFIX . 'dashboard&mskd_db_updated=1' ) );
		exit;
	}

	/**
	 * Render database repair notice when schema is invalid.
	 *
	 * @return void
	 */
	private function render_database_repair_notice(): void {
		$upgrade_url = wp_nonce_url(
			add_query_arg( 'mskd_upgrade_db', '1', admin_url( 'admin.php?page=' . self::PAGE_PREFIX . 'dashboard' ) ),
			'mskd_upgrade_db'
		);

		?>
		<div class="notice notice-error">
			<p>
				<strong><?php esc_html_e( 'Mail System Database Repair Required', 'mail-system-by-katsarov-design' ); ?></strong>
			</p>
			<p>
				<?php esc_html_e( 'Some required database tables or columns are missing. This may cause subscription confirmation links to fail. Click the button below to repair the database.', 'mail-system-by-katsarov-design' ); ?>
			</p>
			<p>
				<a href="<?php echo esc_url( $upgrade_url ); ?>" class="button button-primary">
					<?php esc_html_e( 'Repair Database Now', 'mail-system-by-katsarov-design' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Check if database schema has all required columns.
	 *
	 * @return bool True if schema is valid.
	 */
	private function is_database_schema_valid(): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'mskd_subscribers';

		// First check the table itself exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema check.
		$table_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
		);

		if ( ! $table_exists ) {
			return false;
		}

		// Then check the opt_in_token column exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema check.
		$column_exists = $wpdb->get_results(
			$wpdb->prepare(
				"SHOW COLUMNS FROM {$table} LIKE %s",
				'opt_in_token'
			)
		);

		return ! empty( $column_exists );
	}

	/**
	 * Check if current screen is a plugin page.
	 *
	 * @return bool True if on a plugin page.
	 */
	private function is_plugin_page(): bool {
		$screen = get_current_screen();

		if ( ! $screen ) {
			return false;
		}

		return false !== strpos( $screen->id, self::PAGE_PREFIX );
	}
}
