<?php
/**
 * Admin Assets Controller
 *
 * Handles enqueuing of admin CSS and JavaScript assets.
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
 * Class Admin_Assets
 *
 * Controller for admin asset management.
 */
class Admin_Assets {

	/**
	 * Plugin pages prefix.
	 */
	const PAGE_PREFIX = 'mskd-';

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue( string $hook ): void {
		// Load minimal styles on the WordPress dashboard for the widget.
		if ( $this->is_wordpress_dashboard( $hook ) ) {
			$this->enqueue_dashboard_widget_styles();
			return;
		}

		// Only load full assets on plugin pages.
		if ( ! $this->is_plugin_page( $hook ) ) {
			return;
		}

		$this->enqueue_vendor_assets();
		$this->enqueue_plugin_assets();
		$this->localize_scripts();
	}

	/**
	 * Check if current page is the WordPress dashboard.
	 *
	 * @param string $hook Current admin page hook.
	 * @return bool True if on WordPress dashboard.
	 */
	private function is_wordpress_dashboard( string $hook ): bool {
		return 'index.php' === $hook;
	}

	/**
	 * Check if current page is a plugin page.
	 *
	 * @param string $hook Current admin page hook.
	 * @return bool True if on a plugin page.
	 */
	private function is_plugin_page( string $hook ): bool {
		return false !== strpos( $hook, self::PAGE_PREFIX );
	}

	/**
	 * Enqueue minimal styles for the dashboard widget.
	 *
	 * @return void
	 */
	private function enqueue_dashboard_widget_styles(): void {
		wp_enqueue_style(
			'mskd-admin-style',
			MSKD_PLUGIN_URL . 'admin/css/admin-style.css',
			array(),
			MSKD_VERSION
		);
	}

	/**
	 * Enqueue vendor assets (SlimSelect).
	 *
	 * @return void
	 */
	private function enqueue_vendor_assets(): void {
		// SlimSelect CSS.
		wp_enqueue_style(
			'slimselect',
			'https://cdn.jsdelivr.net/npm/slim-select@2.9.2/dist/slimselect.min.css',
			array(),
			'2.9.2'
		);

		// SlimSelect JS.
		wp_enqueue_script(
			'slimselect',
			'https://cdn.jsdelivr.net/npm/slim-select@2.9.2/dist/slimselect.min.js',
			array(),
			'2.9.2',
			false
		);
	}

	/**
	 * Enqueue plugin-specific assets.
	 *
	 * @return void
	 */
	private function enqueue_plugin_assets(): void {
		wp_enqueue_style(
			'mskd-admin-style',
			MSKD_PLUGIN_URL . 'admin/css/admin-style.css',
			array( 'slimselect' ),
			MSKD_VERSION
		);

		wp_enqueue_script(
			'mskd-admin-script',
			MSKD_PLUGIN_URL . 'admin/js/admin-script.js',
			array( 'jquery', 'slimselect' ),
			MSKD_VERSION,
			true
		);
	}

	/**
	 * Localize scripts with translations and configuration.
	 *
	 * @return void
	 */
	private function localize_scripts(): void {
		wp_localize_script(
			'mskd-admin-script',
			'mskd_admin',
			array(
				'ajax_url'      => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'mskd_admin_nonce' ),
				'preview_nonce' => wp_create_nonce( 'mskd_preview_nonce' ),
				'strings'       => $this->get_localized_strings(),
			)
		);
	}

	/**
	 * Get localized strings for JavaScript.
	 *
	 * @return array<string, string>
	 */
	private function get_localized_strings(): array {
		return array(
			'confirm_delete'                      => __( 'Are you sure you want to delete?', 'mail-system-by-katsarov-design' ),
			'sending'                             => __( 'Sending...', 'mail-system-by-katsarov-design' ),
			'success'                             => __( 'Success!', 'mail-system-by-katsarov-design' ),
			'error'                               => __( 'Error!', 'mail-system-by-katsarov-design' ),
			'timeout'                             => __( 'Connection timed out. Check SMTP settings.', 'mail-system-by-katsarov-design' ),
			'datetime_past'                       => __( 'Please select a future date and time.', 'mail-system-by-katsarov-design' ),
			'processing'                          => __( 'Processing...', 'mail-system-by-katsarov-design' ),
			'confirm_truncate_subscribers'        => __( 'Are you sure you want to delete ALL subscribers? This action cannot be undone!', 'mail-system-by-katsarov-design' ),
			'confirm_truncate_lists'              => __( 'Are you sure you want to delete ALL lists? This action cannot be undone!', 'mail-system-by-katsarov-design' ),
			'confirm_truncate_queue'              => __( 'Are you sure you want to delete ALL campaigns? This action cannot be undone!', 'mail-system-by-katsarov-design' ),
			'confirm_delete_inactive_subscribers' => __( 'Are you sure you want to delete all unconfirmed, inactive, and unsubscribed subscribers? This action cannot be undone!', 'mail-system-by-katsarov-design' ),
			'select_lists_placeholder'            => __( 'Select lists...', 'mail-system-by-katsarov-design' ),
			'search_placeholder'                  => __( 'Search...', 'mail-system-by-katsarov-design' ),
			'no_results'                          => __( 'No results found', 'mail-system-by-katsarov-design' ),
			'copied'                              => __( 'Copied!', 'mail-system-by-katsarov-design' ),
			'copy_error'                          => __( 'Copy failed', 'mail-system-by-katsarov-design' ),
			'no_subscribers_selected'             => __( 'No subscribers selected.', 'mail-system-by-katsarov-design' ),
			'no_lists_selected'                   => __( 'No lists selected.', 'mail-system-by-katsarov-design' ),
		);
	}
}
