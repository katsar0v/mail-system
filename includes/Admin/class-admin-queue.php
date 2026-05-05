<?php
/**
 * Admin Queue Controller
 *
 * Handles queue-related admin actions and page rendering.
 *
 * @package MSKD\Admin
 * @since   1.1.0
 */

namespace MSKD\Admin;

use MSKD\Services\Email_Service;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Admin_Queue
 *
 * Controller for queue admin page.
 */
class Admin_Queue {

	/**
	 * Email service instance.
	 *
	 * @var Email_Service
	 */
	private $service;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->service = new Email_Service();
	}

	/**
	 * Handle queue-related actions.
	 *
	 * @return void
	 */
	public function handle_actions(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Handle cancel queue item action.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified in the if condition below.
		if ( isset( $_GET['action'] ) && 'cancel_queue_item' === sanitize_text_field( wp_unslash( $_GET['action'] ) ) && isset( $_GET['id'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified here, sanitized before use.
			if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'cancel_queue_item_' . intval( $_GET['id'] ) ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified above.
				$this->handle_cancel_item( intval( $_GET['id'] ) );
			}
		}

		// Handle cancel campaign action.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified in the if condition below.
		if ( isset( $_GET['action'] ) && 'cancel_campaign' === sanitize_text_field( wp_unslash( $_GET['action'] ) ) && isset( $_GET['id'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified here, sanitized before use.
			if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'cancel_campaign_' . intval( $_GET['id'] ) ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified above.
				$this->handle_cancel_campaign( intval( $_GET['id'] ) );
			}
		}

		// Handle campaign creation success message.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Just displaying a message based on a flag.
		if ( isset( $_GET['campaign_created'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['campaign_created'] ) ) ) {
			$message = get_transient( 'mskd_campaign_success_message' );
			if ( $message ) {
				add_settings_error(
					'mskd_messages',
					'mskd_success',
					$message,
					'success'
				);
				delete_transient( 'mskd_campaign_success_message' );
			}
		}
	}

	/**
	 * Handle cancel queue item action.
	 *
	 * @param int $id Queue item ID.
	 * @return void
	 */
	private function handle_cancel_item( int $id ): void {
		$item = $this->service->get_queue_item( $id );

		if ( ! $item ) {
			add_settings_error(
				'mskd_messages',
				'mskd_error',
				__( 'Record not found.', 'mail-system' ),
				'error'
			);
			// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Redirecting to admin page.
			wp_redirect( admin_url( 'admin.php?page=mskd-queue' ) );
			exit;
		}

		if ( ! in_array( $item->status, array( 'pending', 'processing' ), true ) ) {
			add_settings_error(
				'mskd_messages',
				'mskd_error',
				__( 'This email cannot be cancelled.', 'mail-system' ),
				'error'
			);
			// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Redirecting to admin page.
			wp_redirect( admin_url( 'admin.php?page=mskd-queue' ) );
			exit;
		}

		$result = $this->service->cancel_queue_item( $id );

		if ( $result ) {
			add_settings_error(
				'mskd_messages',
				'mskd_success',
				__( 'Email cancelled successfully.', 'mail-system' ),
				'success'
			);
		} else {
			add_settings_error(
				'mskd_messages',
				'mskd_error',
				__( 'Error cancelling email.', 'mail-system' ),
				'error'
			);
		}

		// Check if we should return to campaign detail page.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only parameter for redirect URL.
		$return_campaign = isset( $_GET['return_campaign'] ) ? intval( $_GET['return_campaign'] ) : 0;
		if ( $return_campaign > 0 ) {
			// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Redirecting to admin page with campaign parameter.
			wp_redirect( admin_url( 'admin.php?page=mskd-queue&action=view&campaign_id=' . $return_campaign ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only parameter for redirect URL.
		} elseif ( isset( $_GET['view'] ) && 'legacy' === sanitize_text_field( wp_unslash( $_GET['view'] ) ) ) {
			// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Redirecting to admin page with view parameter.
			wp_redirect( admin_url( 'admin.php?page=mskd-queue&view=legacy' ) );
		} else {
			// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Redirecting to admin page.
			wp_redirect( admin_url( 'admin.php?page=mskd-queue' ) );
		}
		exit;
	}

	/**
	 * Handle cancel campaign action.
	 *
	 * @param int $id Campaign ID.
	 * @return void
	 */
	private function handle_cancel_campaign( int $id ): void {
		$campaign = $this->service->get_campaign( $id );

		if ( ! $campaign ) {
			add_settings_error(
				'mskd_messages',
				'mskd_error',
				__( 'Campaign not found.', 'mail-system' ),
				'error'
			);
			// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Redirecting to admin page.
			wp_redirect( admin_url( 'admin.php?page=mskd-queue' ) );
			exit;
		}

		if ( ! in_array( $campaign->status, array( 'pending', 'processing' ), true ) ) {
			add_settings_error(
				'mskd_messages',
				'mskd_error',
				__( 'This campaign cannot be cancelled.', 'mail-system' ),
				'error'
			);
			// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Redirecting to admin page.
			wp_redirect( admin_url( 'admin.php?page=mskd-queue' ) );
			exit;
		}

		$cancelled_count = $this->service->cancel_campaign( $id );

		if ( false !== $cancelled_count ) {
			add_settings_error(
				'mskd_messages',
				'mskd_success',
				sprintf(
					/* translators: %d: number of emails cancelled */
					__( 'Campaign cancelled. %d emails were cancelled.', 'mail-system' ),
					$cancelled_count
				),
				'success'
			);
		} else {
			add_settings_error(
				'mskd_messages',
				'mskd_error',
				__( 'Error cancelling campaign.', 'mail-system' ),
				'error'
			);
		}

		// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Redirecting to admin page.
		wp_redirect( admin_url( 'admin.php?page=mskd-queue' ) );
		exit;
	}

	/**
	 * Render the queue page.
	 *
	 * @return void
	 */
	public function render(): void {
		include MSKD_PLUGIN_DIR . 'admin/partials/queue.php';
	}

	/**
	 * Get the service instance.
	 *
	 * @return Email_Service
	 */
	public function get_service(): Email_Service {
		return $this->service;
	}
}
