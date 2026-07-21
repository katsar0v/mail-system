<?php
/**
 * Admin Class (Refactored)
 *
 * Thin orchestrator that delegates to specialized controllers.
 *
 * @package MSKD\Admin
 * @since   1.1.0
 */

namespace MSKD\Admin;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Admin
 *
 * Main admin class that coordinates all admin functionality.
 * Delegates to specialized controllers for each feature area.
 */
class Admin {

	/**
	 * Plugin pages prefix.
	 */
	const PAGE_PREFIX = 'mskd-';

	/**
	 * Menu controller.
	 *
	 * @var Admin_Menu
	 */
	private Admin_Menu $menu;

	/**
	 * Assets controller.
	 *
	 * @var Admin_Assets
	 */
	private Admin_Assets $assets;

	/**
	 * Notices controller.
	 *
	 * @var Admin_Notices
	 */
	private Admin_Notices $notices;

	/**
	 * Dashboard controller.
	 *
	 * @var Admin_Dashboard
	 */
	private Admin_Dashboard $dashboard;

	/**
	 * Subscribers controller.
	 *
	 * @var Admin_Subscribers
	 */
	private Admin_Subscribers $subscribers;

	/**
	 * Lists controller.
	 *
	 * @var Admin_Lists
	 */
	private Admin_Lists $lists;

	/**
	 * Email controller.
	 *
	 * @var Admin_Email
	 */
	private Admin_Email $email;

	/**
	 * Queue controller.
	 *
	 * @var Admin_Queue
	 */
	private Admin_Queue $queue;

	/**
	 * Settings controller.
	 *
	 * @var Admin_Settings
	 */
	private Admin_Settings $settings;

	/**
	 * AJAX handler.
	 *
	 * @var Admin_Ajax
	 */
	private Admin_Ajax $ajax;

	/**
	 * Import/Export controller.
	 *
	 * @var Admin_Import_Export
	 */
	private Admin_Import_Export $import_export;

	/**
	 * Templates controller.
	 *
	 * @var Admin_Templates
	 */
	private Admin_Templates $templates;

	/**
	 * Visual Editor controller.
	 *
	 * @var Admin_Visual_Editor
	 */
	private Admin_Visual_Editor $visual_editor;

	/**
	 * API Access controller.
	 *
	 * @var Admin_Api
	 */
	private Admin_Api $api;

	/**
	 * Constructor - initialize controllers.
	 */
	public function __construct() {
		// Initialize feature controllers.
		$this->subscribers   = new Admin_Subscribers();
		$this->lists         = new Admin_Lists();
		$this->email         = new Admin_Email();
		$this->queue         = new Admin_Queue();
		$this->settings      = new Admin_Settings();
		$this->ajax          = new Admin_Ajax();
		$this->import_export = new Admin_Import_Export();
		$this->templates     = new Admin_Templates();
		$this->visual_editor = new Admin_Visual_Editor();
		$this->api           = new Admin_Api();

		// Initialize infrastructure controllers.
		$this->dashboard = new Admin_Dashboard();
		$this->notices   = new Admin_Notices();
		$this->assets    = new Admin_Assets();
		$this->menu      = new Admin_Menu(
			array(
				'dashboard'     => $this->dashboard,
				'subscribers'   => $this->subscribers,
				'lists'         => $this->lists,
				'email'         => $this->email,
				'queue'         => $this->queue,
				'settings'      => $this->settings,
				'import_export' => $this->import_export,
				'templates'     => $this->templates,
				'visual_editor' => $this->visual_editor,
				'api'           => $this->api,
			)
		);
	}

	/**
	 * Initialize admin hooks and controllers.
	 *
	 * @return void
	 */
	public function init(): void {
		// Register hooks via controllers.
		add_action( 'admin_menu', array( $this->menu, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( $this->assets, 'enqueue' ) );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );

		// Initialize controllers that need their own hooks.
		$this->notices->init();
		$this->dashboard->init();
		$this->ajax->init();
		$this->visual_editor->init();
	}

	/**
	 * Handle admin actions by delegating to controllers.
	 *
	 * @return void
	 */
	public function handle_actions(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Delegate to appropriate controller.
		$this->subscribers->handle_actions();
		$this->lists->handle_actions();
		$this->email->handle_actions();
		$this->queue->handle_actions();
		$this->settings->handle_actions();
		$this->import_export->handle_actions();
		$this->templates->handle_actions();
		$this->api->handle_actions();
	}

	/**
	 * Get the API Access controller.
	 *
	 * @return Admin_Api
	 */
	public function get_api_controller(): Admin_Api {
		return $this->api;
	}

	/**
	 * Get preserved form data for one-time email.
	 *
	 * @return array
	 */
	public function get_one_time_email_form_data(): array {
		return $this->email->get_one_time_email_form_data();
	}

	/**
	 * Register admin menu and submenus.
	 * Delegates to the menu controller.
	 *
	 * @deprecated 2.0.0 Use Admin_Menu::register() directly.
	 * @return void
	 */
	public function register_menu(): void {
		$this->menu->register();
	}

	/**
	 * Enqueue admin assets.
	 * Delegates to the assets controller.
	 *
	 * @deprecated 2.0.0 Use Admin_Assets::enqueue() directly.
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		$this->assets->enqueue( $hook );
	}

	/**
	 * Show WP-Cron warning notice.
	 * Delegates to the notices controller.
	 *
	 * @deprecated 2.0.0 Use Admin_Notices::show_cron_notice() directly.
	 * @return void
	 */
	public function show_cron_notice(): void {
		$this->notices->show_cron_notice();
	}

	/**
	 * Show share notice.
	 * Delegates to the notices controller.
	 *
	 * @deprecated 2.0.0 Use Admin_Notices::show_share_notice() directly.
	 * @return void
	 */
	public function show_share_notice(): void {
		$this->notices->show_share_notice();
	}

	/**
	 * Show database upgrade notice.
	 * Delegates to the notices controller.
	 *
	 * @deprecated 2.0.0 Use Admin_Notices::show_database_upgrade_notice() directly.
	 * @return void
	 */
	public function show_database_upgrade_notice(): void {
		$this->notices->show_database_upgrade_notice();
	}

	/**
	 * Handle database upgrade.
	 * Delegates to the notices controller.
	 *
	 * @deprecated 2.0.0 Use Admin_Notices::handle_database_upgrade() directly.
	 * @return void
	 */
	public function handle_database_upgrade(): void {
		$this->notices->handle_database_upgrade();
	}

	/**
	 * Render Dashboard page.
	 * Delegates to the dashboard controller.
	 *
	 * @deprecated 2.0.0 Use Admin_Dashboard::render() directly.
	 * @return void
	 */
	public function render_dashboard(): void {
		$this->dashboard->render();
	}

	/**
	 * Render Subscribers page.
	 * Delegates to the subscribers controller.
	 *
	 * @deprecated 2.0.0 Use Admin_Subscribers::render() directly.
	 * @return void
	 */
	public function render_subscribers(): void {
		$this->subscribers->render();
	}

	/**
	 * Render Lists page.
	 * Delegates to the lists controller.
	 *
	 * @deprecated 2.0.0 Use Admin_Lists::render() directly.
	 * @return void
	 */
	public function render_lists(): void {
		$this->lists->render();
	}

	/**
	 * Render Compose page.
	 * Delegates to the email controller.
	 *
	 * @deprecated 2.0.0 Use Admin_Email::render_compose() directly.
	 * @return void
	 */
	public function render_compose(): void {
		$this->email->render_compose();
	}

	/**
	 * Render Queue page.
	 * Delegates to the queue controller.
	 *
	 * @deprecated 2.0.0 Use Admin_Queue::render() directly.
	 * @return void
	 */
	public function render_queue(): void {
		$this->queue->render();
	}

	/**
	 * Render Settings page.
	 * Delegates to the settings controller.
	 *
	 * @deprecated 2.0.0 Use Admin_Settings::render() directly.
	 * @return void
	 */
	public function render_settings(): void {
		$this->settings->render();
	}

	/**
	 * Render One-Time Email page.
	 * Delegates to the email controller.
	 *
	 * @deprecated 2.0.0 Use Admin_Email::render_one_time() directly.
	 * @return void
	 */
	public function render_one_time_email(): void {
		$this->email->render_one_time();
	}

	/**
	 * Render Shortcodes page.
	 * Delegates to the dashboard controller.
	 *
	 * @deprecated 2.0.0 Use Admin_Dashboard::render_shortcodes() directly.
	 * @return void
	 */
	public function render_shortcodes(): void {
		$this->dashboard->render_shortcodes();
	}

	/**
	 * Register dashboard widget.
	 * Delegates to the dashboard controller.
	 *
	 * @deprecated 2.0.0 Use Admin_Dashboard::register_widget() directly.
	 * @return void
	 */
	public function register_dashboard_widget(): void {
		$this->dashboard->register_widget();
	}

	/**
	 * Render dashboard widget content.
	 * Delegates to the dashboard controller.
	 *
	 * @deprecated 2.0.0 Use Admin_Dashboard::render_widget() directly.
	 * @return void
	 */
	public function render_dashboard_widget(): void {
		$this->dashboard->render_widget();
	}

	/**
	 * AJAX handler to dismiss share notice.
	 * Delegates to the AJAX controller.
	 *
	 * @deprecated 2.0.0 Use Admin_Ajax::dismiss_share_notice() directly.
	 * @return void
	 */
	public function ajax_dismiss_share_notice(): void {
		$this->ajax->dismiss_share_notice();
	}

	/**
	 * AJAX handler for SMTP test.
	 * Delegates to the AJAX controller.
	 *
	 * @deprecated 2.0.0 Use Admin_Ajax::test_smtp() directly.
	 * @return void
	 */
	public function ajax_test_smtp(): void {
		$this->ajax->test_smtp();
	}

	/**
	 * AJAX handler: Truncate subscribers table.
	 * Delegates to the AJAX controller.
	 *
	 * @deprecated 2.0.0 Use Admin_Ajax::truncate_subscribers() directly.
	 * @return void
	 */
	public function ajax_truncate_subscribers(): void {
		$this->ajax->truncate_subscribers();
	}

	/**
	 * AJAX handler: Truncate lists table.
	 * Delegates to the AJAX controller.
	 *
	 * @deprecated 2.0.0 Use Admin_Ajax::truncate_lists() directly.
	 * @return void
	 */
	public function ajax_truncate_lists(): void {
		$this->ajax->truncate_lists();
	}

	/**
	 * AJAX handler: Truncate queue table.
	 * Delegates to the AJAX controller.
	 *
	 * @deprecated 2.0.0 Use Admin_Ajax::truncate_queue() directly.
	 * @return void
	 */
	public function ajax_truncate_queue(): void {
		$this->ajax->truncate_queue();
	}

	/**
	 * Get the menu controller.
	 *
	 * @return Admin_Menu
	 */
	public function get_menu_controller(): Admin_Menu {
		return $this->menu;
	}

	/**
	 * Get the assets controller.
	 *
	 * @return Admin_Assets
	 */
	public function get_assets_controller(): Admin_Assets {
		return $this->assets;
	}

	/**
	 * Get the notices controller.
	 *
	 * @return Admin_Notices
	 */
	public function get_notices_controller(): Admin_Notices {
		return $this->notices;
	}

	/**
	 * Get the dashboard controller.
	 *
	 * @return Admin_Dashboard
	 */
	public function get_dashboard_controller(): Admin_Dashboard {
		return $this->dashboard;
	}

	/**
	 * Get the subscribers controller.
	 *
	 * @return Admin_Subscribers
	 */
	public function get_subscribers_controller(): Admin_Subscribers {
		return $this->subscribers;
	}

	/**
	 * Get the lists controller.
	 *
	 * @return Admin_Lists
	 */
	public function get_lists_controller(): Admin_Lists {
		return $this->lists;
	}

	/**
	 * Get the email controller.
	 *
	 * @return Admin_Email
	 */
	public function get_email_controller(): Admin_Email {
		return $this->email;
	}

	/**
	 * Get the queue controller.
	 *
	 * @return Admin_Queue
	 */
	public function get_queue_controller(): Admin_Queue {
		return $this->queue;
	}

	/**
	 * Get the settings controller.
	 *
	 * @return Admin_Settings
	 */
	public function get_settings_controller(): Admin_Settings {
		return $this->settings;
	}

	/**
	 * Get the AJAX handler.
	 *
	 * @return Admin_Ajax
	 */
	public function get_ajax_handler(): Admin_Ajax {
		return $this->ajax;
	}

	/**
	 * Get the import/export controller.
	 *
	 * @return Admin_Import_Export
	 */
	public function get_import_export_controller(): Admin_Import_Export {
		return $this->import_export;
	}

	/**
	 * Get the templates controller.
	 *
	 * @return Admin_Templates
	 */
	public function get_templates_controller(): Admin_Templates {
		return $this->templates;
	}

	/**
	 * Get the visual editor controller.
	 *
	 * @return Admin_Visual_Editor
	 */
	public function get_visual_editor_controller(): Admin_Visual_Editor {
		return $this->visual_editor;
	}
}
