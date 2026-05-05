<?php
/**
 * Admin Dashboard Controller
 *
 * Handles the plugin dashboard page and WordPress dashboard widget.
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
 * Class Admin_Dashboard
 *
 * Controller for the plugin dashboard and WordPress dashboard widget.
 */
class Admin_Dashboard {

	/**
	 * Initialize dashboard hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_dashboard_setup', array( $this, 'register_widget' ) );
	}

	/**
	 * Render the main dashboard page.
	 *
	 * @return void
	 */
	public function render(): void {
		include MSKD_PLUGIN_DIR . 'admin/partials/dashboard.php';
	}

	/**
	 * Render the shortcodes documentation page.
	 *
	 * @return void
	 */
	public function render_shortcodes(): void {
		include MSKD_PLUGIN_DIR . 'admin/partials/shortcodes.php';
	}

	/**
	 * Register the WordPress dashboard widget.
	 *
	 * @return void
	 */
	public function register_widget(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'mskd_queue_stats_widget',
			__( 'Mail System - Queue Statistics', 'mail-system' ),
			array( $this, 'render_widget' )
		);
	}

	/**
	 * Render the dashboard widget content.
	 *
	 * @return void
	 */
	public function render_widget(): void {
		$stats = $this->get_queue_statistics();

		$pending = $stats['pending'];
		$sent    = $stats['sent'];
		$failed  = $stats['failed'];

		// Get last cron run timestamp.
		$last_cron_run = get_option( 'mskd_last_cron_run', 0 );

		// Include the widget template.
		include MSKD_PLUGIN_DIR . 'admin/partials/dashboard-widget.php';
	}

	/**
	 * Get queue statistics from the database.
	 *
	 * @return array{pending: int, sent: int, failed: int}
	 */
	private function get_queue_statistics(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Static query with no user input, caching not needed for dashboard widget.
		$queue_stats = $wpdb->get_row(
			"SELECT 
				SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
				SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
				SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
			FROM {$wpdb->prefix}mskd_queue"
		);

		// Defensive null check for PHP 8+ compatibility.
		return array(
			'pending' => $queue_stats ? intval( $queue_stats->pending ?? 0 ) : 0,
			'sent'    => $queue_stats ? intval( $queue_stats->sent ?? 0 ) : 0,
			'failed'  => $queue_stats ? intval( $queue_stats->failed ?? 0 ) : 0,
		);
	}
}
