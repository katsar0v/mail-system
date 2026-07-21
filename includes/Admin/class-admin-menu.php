<?php
/**
 * Admin Menu Controller
 *
 * Handles WordPress admin menu registration for the plugin.
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
 * Class Admin_Menu
 *
 * Controller for admin menu registration.
 */
class Admin_Menu {

	/**
	 * Plugin pages prefix.
	 */
	const PAGE_PREFIX = 'mskd-';

	/**
	 * Controller references for render callbacks.
	 *
	 * @var array<string, object>
	 */
	private array $controllers;

	/**
	 * Constructor.
	 *
	 * @param array<string, object> $controllers Array of controller instances.
	 */
	public function __construct( array $controllers ) {
		$this->controllers = $controllers;
	}

	/**
	 * Register admin menu and submenus.
	 *
	 * @return void
	 */
	public function register(): void {
		// Main menu.
		add_menu_page(
			__( 'Mail System', 'mail-system' ),
			__( 'Emails', 'mail-system' ),
			'manage_options',
			self::PAGE_PREFIX . 'dashboard',
			array( $this->controllers['dashboard'], 'render' ),
			'dashicons-email-alt',
			26
		);

		// Dashboard submenu (same as main).
		add_submenu_page(
			self::PAGE_PREFIX . 'dashboard',
			__( 'Dashboard', 'mail-system' ),
			__( 'Dashboard', 'mail-system' ),
			'manage_options',
			self::PAGE_PREFIX . 'dashboard',
			array( $this->controllers['dashboard'], 'render' )
		);

		// Subscribers.
		add_submenu_page(
			self::PAGE_PREFIX . 'dashboard',
			__( 'Subscribers', 'mail-system' ),
			__( 'Subscribers', 'mail-system' ),
			'manage_options',
			self::PAGE_PREFIX . 'subscribers',
			array( $this->controllers['subscribers'], 'render' )
		);

		// Lists.
		add_submenu_page(
			self::PAGE_PREFIX . 'dashboard',
			__( 'Lists', 'mail-system' ),
			__( 'Lists', 'mail-system' ),
			'manage_options',
			self::PAGE_PREFIX . 'lists',
			array( $this->controllers['lists'], 'render' )
		);

		// Templates.
		add_submenu_page(
			self::PAGE_PREFIX . 'dashboard',
			__( 'Templates', 'mail-system' ),
			__( 'Templates', 'mail-system' ),
			'manage_options',
			self::PAGE_PREFIX . 'templates',
			array( $this->controllers['templates'], 'render' )
		);

		// Visual Editor (hidden from menu but accessible via URL).
		add_submenu_page(
			null, // No parent - hidden from menu.
			__( 'Visual Editor', 'mail-system' ),
			__( 'Visual Editor', 'mail-system' ),
			'manage_options',
			self::PAGE_PREFIX . 'visual-editor',
			array( $this->controllers['visual_editor'], 'render' )
		);

		// Compose.
		add_submenu_page(
			self::PAGE_PREFIX . 'dashboard',
			__( 'New campaign', 'mail-system' ),
			__( 'New campaign', 'mail-system' ),
			'manage_options',
			self::PAGE_PREFIX . 'compose',
			array( $this->controllers['email'], 'render_compose' )
		);

		// One-Time Email.
		add_submenu_page(
			self::PAGE_PREFIX . 'dashboard',
			__( 'One-time email', 'mail-system' ),
			__( 'One-time email', 'mail-system' ),
			'manage_options',
			self::PAGE_PREFIX . 'one-time-email',
			array( $this->controllers['email'], 'render_one_time' )
		);

		// Queue.
		add_submenu_page(
			self::PAGE_PREFIX . 'dashboard',
			__( 'Queue', 'mail-system' ),
			__( 'Queue', 'mail-system' ),
			'manage_options',
			self::PAGE_PREFIX . 'queue',
			array( $this->controllers['queue'], 'render' )
		);

		// Settings.
		add_submenu_page(
			self::PAGE_PREFIX . 'dashboard',
			__( 'Settings', 'mail-system' ),
			__( 'Settings', 'mail-system' ),
			'manage_options',
			self::PAGE_PREFIX . 'settings',
			array( $this->controllers['settings'], 'render' )
		);

		// API Access.
		add_submenu_page(
			self::PAGE_PREFIX . 'dashboard',
			__( 'API Access', 'mail-system' ),
			__( 'API Access', 'mail-system' ),
			'manage_options',
			self::PAGE_PREFIX . 'api',
			array( $this->controllers['api'], 'render' )
		);

		// Import/Export.
		add_submenu_page(
			self::PAGE_PREFIX . 'dashboard',
			__( 'Import / Export', 'mail-system' ),
			__( 'Import / Export', 'mail-system' ),
			'manage_options',
			self::PAGE_PREFIX . 'import-export',
			array( $this->controllers['import_export'], 'render' )
		);

		// Shortcodes.
		add_submenu_page(
			self::PAGE_PREFIX . 'dashboard',
			__( 'Shortcodes', 'mail-system' ),
			__( 'Shortcodes', 'mail-system' ),
			'manage_options',
			self::PAGE_PREFIX . 'shortcodes',
			array( $this->controllers['dashboard'], 'render_shortcodes' )
		);
	}

	/**
	 * Get the page prefix.
	 *
	 * @return string
	 */
	public function get_page_prefix(): string {
		return self::PAGE_PREFIX;
	}
}
