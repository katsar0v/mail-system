<?php
/**
 * Admin Lists Controller
 *
 * Handles list-related admin actions and page rendering.
 *
 * @package MSKD\Admin
 * @since   1.1.0
 */

namespace MSKD\Admin;

use MSKD\Services\List_Service;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Admin_Lists
 *
 * Controller for lists admin page.
 */
class Admin_Lists {

	/**
	 * List service instance.
	 *
	 * @var List_Service
	 */
	private $service;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->service = new List_Service();
	}

	/**
	 * Handle list-related actions.
	 *
	 * @return void
	 */
	public function handle_actions(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Handle add list.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce checked on line 52.
		if ( isset( $_POST['mskd_add_list'] ) && isset( $_POST['mskd_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mskd_nonce'] ) ), 'mskd_add_list' ) ) {
			$this->handle_add();
		}

		// Handle edit list.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce checked on line 57.
		if ( isset( $_POST['mskd_edit_list'] ) && isset( $_POST['mskd_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mskd_nonce'] ) ), 'mskd_edit_list' ) ) {
			$this->handle_edit();
		}

		// Handle delete list.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified in the if condition below.
		if ( isset( $_GET['action'] ) && 'delete_list' === sanitize_text_field( wp_unslash( $_GET['action'] ) ) && isset( $_GET['id'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified here, sanitized before use.
			if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'delete_list_' . intval( $_GET['id'] ) ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified above.
				$this->handle_delete( intval( $_GET['id'] ) );
			}
		}
	}

	/**
	 * Handle add list action.
	 *
	 * @return void
	 */
	private function handle_add(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions() before calling this method.
		$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions() before calling this method.
		$description = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';

		// Validate name.
		if ( empty( $name ) ) {
			add_settings_error(
				'mskd_messages',
				'mskd_error',
				__( 'List name is required.', 'mail-system' ),
				'error'
			);
			return;
		}

		// Create list.
		$list_id = $this->service->create(
			array(
				'name'        => $name,
				'description' => $description,
			)
		);

		if ( $list_id ) {
			add_settings_error(
				'mskd_messages',
				'mskd_success',
				__( 'List added successfully.', 'mail-system' ),
				'success'
			);
		} else {
			add_settings_error(
				'mskd_messages',
				'mskd_error',
				__( 'Error adding list.', 'mail-system' ),
				'error'
			);
		}
	}

	/**
	 * Handle edit list action.
	 *
	 * @return void
	 */
	private function handle_edit(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions() before calling this method.
		$id = isset( $_POST['list_id'] ) ? intval( $_POST['list_id'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions() before calling this method.
		$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions() before calling this method.
		$description = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';

		// Validate name.
		if ( empty( $name ) ) {
			add_settings_error(
				'mskd_messages',
				'mskd_error',
				__( 'List name is required.', 'mail-system' ),
				'error'
			);
			return;
		}

		// Update list.
		$this->service->update(
			$id,
			array(
				'name'        => $name,
				'description' => $description,
			)
		);

		add_settings_error(
			'mskd_messages',
			'mskd_success',
			__( 'List updated successfully.', 'mail-system' ),
			'success'
		);

		// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Redirecting to admin page.
		wp_redirect( admin_url( 'admin.php?page=mskd-lists' ) );
		exit;
	}

	/**
	 * Handle delete list action.
	 *
	 * @param int $id List ID.
	 * @return void
	 */
	private function handle_delete( int $id ): void {
		$this->service->delete( $id );

		add_settings_error(
			'mskd_messages',
			'mskd_success',
			__( 'List deleted successfully.', 'mail-system' ),
			'success'
		);

		// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Redirecting to admin page.
		wp_redirect( admin_url( 'admin.php?page=mskd-lists' ) );
		exit;
	}

	/**
	 * Render the lists page.
	 *
	 * @return void
	 */
	public function render(): void {
		include MSKD_PLUGIN_DIR . 'admin/partials/lists.php';
	}

	/**
	 * Get the service instance.
	 *
	 * @return List_Service
	 */
	public function get_service(): List_Service {
		return $this->service;
	}
}
