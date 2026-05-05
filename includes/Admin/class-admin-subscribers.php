<?php
/**
 * Admin Subscribers Controller
 *
 * Handles subscriber-related admin actions and page rendering.
 *
 * @package MSKD\Admin
 * @since   1.1.0
 */

namespace MSKD\Admin;

use MSKD\Services\Subscriber_Service;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Admin_Subscribers
 *
 * Controller for subscriber admin page.
 */
class Admin_Subscribers {

	/**
	 * Subscriber service instance.
	 *
	 * @var Subscriber_Service
	 */
	private $service;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->service = new Subscriber_Service();
	}

	/**
	 * Handle subscriber-related actions.
	 *
	 * @return void
	 */
	public function handle_actions(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Handle add subscriber.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce checked on line 52.
		if ( isset( $_POST['mskd_add_subscriber'] ) && isset( $_POST['mskd_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mskd_nonce'] ) ), 'mskd_add_subscriber' ) ) {
			$this->handle_add();
		}

		// Handle edit subscriber.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce checked on line 57.
		if ( isset( $_POST['mskd_edit_subscriber'] ) && isset( $_POST['mskd_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mskd_nonce'] ) ), 'mskd_edit_subscriber' ) ) {
			$this->handle_edit();
		}

		// Handle delete subscriber.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified in the if condition below.
		if ( isset( $_GET['action'] ) && 'delete_subscriber' === sanitize_text_field( wp_unslash( $_GET['action'] ) ) && isset( $_GET['id'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified here, sanitized before use.
			if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'delete_subscriber_' . intval( $_GET['id'] ) ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified above.
				$this->handle_delete( intval( $_GET['id'] ) );
			}
		}
	}

	/**
	 * Handle add subscriber action.
	 *
	 * @return void
	 */
	private function handle_add(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions() before calling this method.
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions() before calling this method.
		$first_name = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions() before calling this method.
		$last_name = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions() before calling this method.
		$status = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'active';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions() before calling this method.
		$lists = isset( $_POST['lists'] ) ? array_map( 'intval', wp_unslash( $_POST['lists'] ) ) : array();

		// Validate status.
		$allowed_statuses = array( 'active', 'inactive', 'unsubscribed' );
		if ( ! in_array( $status, $allowed_statuses, true ) ) {
			$status = 'active';
		}

		// Validate email.
		if ( ! is_email( $email ) ) {
			add_settings_error(
				'mskd_messages',
				'mskd_error',
				__( 'Invalid email address.', 'mail-system' ),
				'error'
			);
			return;
		}

		// Check if email already exists.
		if ( $this->service->email_exists( $email ) ) {
			add_settings_error(
				'mskd_messages',
				'mskd_error',
				__( 'This email already exists.', 'mail-system' ),
				'error'
			);
			return;
		}

		// Create subscriber.
		$subscriber_id = $this->service->create(
			array(
				'email'      => $email,
				'first_name' => $first_name,
				'last_name'  => $last_name,
				'status'     => $status,
			)
		);

		if ( $subscriber_id ) {
			// Sync lists.
			$this->service->sync_lists( $subscriber_id, $lists );

			add_settings_error(
				'mskd_messages',
				'mskd_success',
				__( 'Subscriber added successfully.', 'mail-system' ),
				'success'
			);
		} else {
			add_settings_error(
				'mskd_messages',
				'mskd_error',
				__( 'Error adding subscriber.', 'mail-system' ),
				'error'
			);
		}
	}

	/**
	 * Handle edit subscriber action.
	 *
	 * @return void
	 */
	private function handle_edit(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions() before calling this method.
		$id = isset( $_POST['subscriber_id'] ) ? intval( $_POST['subscriber_id'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions() before calling this method.
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions() before calling this method.
		$first_name = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions() before calling this method.
		$last_name = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions() before calling this method.
		$status = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'active';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions() before calling this method.
		$lists = isset( $_POST['lists'] ) ? array_map( 'intval', wp_unslash( $_POST['lists'] ) ) : array();

		// Validate status.
		$allowed_statuses = array( 'active', 'inactive', 'unsubscribed' );
		if ( ! in_array( $status, $allowed_statuses, true ) ) {
			$status = 'active';
		}

		// Validate email.
		if ( ! is_email( $email ) ) {
			add_settings_error(
				'mskd_messages',
				'mskd_error',
				__( 'Invalid email address.', 'mail-system' ),
				'error'
			);
			return;
		}

		// Check if email exists for another subscriber.
		if ( $this->service->email_exists( $email, $id ) ) {
			add_settings_error(
				'mskd_messages',
				'mskd_error',
				__( 'This email already exists.', 'mail-system' ),
				'error'
			);
			return;
		}

		// Update subscriber.
		$this->service->update(
			$id,
			array(
				'email'      => $email,
				'first_name' => $first_name,
				'last_name'  => $last_name,
				'status'     => $status,
			)
		);

		// Sync lists.
		$this->service->sync_lists( $id, $lists );

		add_settings_error(
			'mskd_messages',
			'mskd_success',
			__( 'Subscriber updated successfully.', 'mail-system' ),
			'success'
		);

		// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Redirecting to admin page.
		wp_redirect( admin_url( 'admin.php?page=mskd-subscribers' ) );
		exit;
	}

	/**
	 * Handle delete subscriber action.
	 *
	 * @param int $id Subscriber ID.
	 * @return void
	 */
	private function handle_delete( int $id ): void {
		$this->service->delete( $id );

		add_settings_error(
			'mskd_messages',
			'mskd_success',
			__( 'Subscriber deleted successfully.', 'mail-system' ),
			'success'
		);

		// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Redirecting to admin page.
		wp_redirect( admin_url( 'admin.php?page=mskd-subscribers' ) );
		exit;
	}

	/**
	 * Render the subscribers page.
	 *
	 * @return void
	 */
	public function render(): void {
		include MSKD_PLUGIN_DIR . 'admin/partials/subscribers.php';
	}

	/**
	 * Get the service instance.
	 *
	 * @return Subscriber_Service
	 */
	public function get_service(): Subscriber_Service {
		return $this->service;
	}
}
