<?php
/**
 * Admin AJAX Controller
 *
 * Handles all AJAX requests for the admin area.
 *
 * @package MSKD\Admin
 * @since   1.1.0
 */

namespace MSKD\Admin;

use MSKD\Services\Subscriber_Service;
use MSKD\Services\List_Service;
use MSKD\Services\Email_Service;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Admin_Ajax
 *
 * Handles AJAX requests for admin functionality.
 */
class Admin_Ajax {

	/**
	 * Initialize AJAX hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_ajax_mskd_test_smtp', array( $this, 'test_smtp' ) );
		add_action( 'wp_ajax_mskd_truncate_subscribers', array( $this, 'truncate_subscribers' ) );
		add_action( 'wp_ajax_mskd_truncate_lists', array( $this, 'truncate_lists' ) );
		add_action( 'wp_ajax_mskd_truncate_queue', array( $this, 'truncate_queue' ) );
		add_action( 'wp_ajax_mskd_dismiss_share_notice', array( $this, 'dismiss_share_notice' ) );
		add_action( 'wp_ajax_mskd_batch_assign_lists', array( $this, 'batch_assign_lists' ) );
		add_action( 'wp_ajax_mskd_batch_remove_lists', array( $this, 'batch_remove_lists' ) );
		add_action( 'wp_ajax_mskd_batch_delete_subscribers', array( $this, 'batch_delete_subscribers' ) );
		add_action( 'wp_ajax_mskd_delete_inactive_subscribers', array( $this, 'delete_inactive_subscribers' ) );
		add_action( 'wp_ajax_mskd_preview_email', array( $this, 'preview_email' ) );
	}

	/**
	 * AJAX handler for SMTP test.
	 *
	 * @return void
	 */
	public function test_smtp(): void {
		// Verify nonce.
		if ( ! check_ajax_referer( 'mskd_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid request. Please refresh the page.', 'mail-system' ),
				)
			);
		}

		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You do not have permission for this operation.', 'mail-system' ),
				)
			);
		}

		// Load SMTP Mailer.
		require_once MSKD_PLUGIN_DIR . 'includes/services/class-mskd-smtp-mailer.php';

		$smtp_mailer = new \MSKD_SMTP_Mailer();
		$result      = $smtp_mailer->test_connection();

		if ( $result['success'] ) {
			wp_send_json_success(
				array(
					'message' => $result['message'],
				)
			);
		} else {
			wp_send_json_error(
				array(
					'message' => $result['message'],
				)
			);
		}
	}

	/**
	 * AJAX handler: Truncate subscribers table.
	 *
	 * @return void
	 */
	public function truncate_subscribers(): void {
		check_ajax_referer( 'mskd_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You do not have permission for this operation.', 'mail-system' ),
				)
			);
		}

		$service = new Subscriber_Service();
		$service->truncate_all();

		wp_send_json_success(
			array(
				'message' => __( 'All subscribers deleted successfully.', 'mail-system' ),
			)
		);
	}

	/**
	 * AJAX handler: Truncate lists table.
	 *
	 * @return void
	 */
	public function truncate_lists(): void {
		check_ajax_referer( 'mskd_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You do not have permission for this operation.', 'mail-system' ),
				)
			);
		}

		$service = new List_Service();
		$service->truncate_all();

		wp_send_json_success(
			array(
				'message' => __( 'All lists deleted successfully.', 'mail-system' ),
			)
		);
	}

	/**
	 * AJAX handler: Truncate queue table.
	 *
	 * @return void
	 */
	public function truncate_queue(): void {
		check_ajax_referer( 'mskd_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You do not have permission for this operation.', 'mail-system' ),
				)
			);
		}

		$service = new Email_Service();
		$service->truncate_queue();

		wp_send_json_success(
			array(
				'message' => __( 'All campaigns deleted successfully.', 'mail-system' ),
			)
		);
	}

	/**
	 * AJAX handler: Dismiss share notice permanently.
	 *
	 * @return void
	 */
	public function dismiss_share_notice(): void {
		check_ajax_referer( 'mskd_dismiss_share_notice', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		update_option( 'mskd_share_notice_dismissed', true );
		wp_send_json_success();
	}

	/**
	 * AJAX handler: Batch assign lists to subscribers.
	 *
	 * @return void
	 */
	public function batch_assign_lists(): void {
		check_ajax_referer( 'mskd_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You do not have permission for this operation.', 'mail-system' ),
				)
			);
		}

		// Get subscriber IDs.
		      // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Array is sanitized with intval below.
		if ( isset( $_POST['subscriber_ids'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Array is sanitized with intval below.
			$subscriber_ids = wp_unslash( $_POST['subscriber_ids'] );
		} else {
			$subscriber_ids = array();
		}
		if ( ! is_array( $subscriber_ids ) ) {
			$subscriber_ids = array();
		}
		$subscriber_ids = array_map( 'intval', $subscriber_ids );

		// Get list IDs.
		      // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Array is sanitized with intval below.
		if ( isset( $_POST['list_ids'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Array is sanitized with intval below.
			$list_ids = wp_unslash( $_POST['list_ids'] );
		} else {
			$list_ids = array();
		}
		if ( ! is_array( $list_ids ) ) {
			$list_ids = array();
		}
		$list_ids = array_map( 'intval', $list_ids );

		// Validate inputs.
		if ( empty( $subscriber_ids ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'No subscribers selected.', 'mail-system' ),
				)
			);
		}

		if ( empty( $list_ids ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'No lists selected.', 'mail-system' ),
				)
			);
		}

		// Perform batch assignment.
		$service = new Subscriber_Service();
		$result  = $service->batch_assign_lists( $subscriber_ids, $list_ids );

		if ( $result['success'] > 0 ) {
			$message = sprintf(
				/* translators: %d: number of subscribers updated */
				_n(
					'%d subscriber updated successfully.',
					'%d subscribers updated successfully.',
					$result['success'],
					'mail-system'
				),
				$result['success']
			);

			if ( $result['failed'] > 0 ) {
				$message .= ' ' . sprintf(
					/* translators: %d: number of subscribers that failed */
					_n(
						'%d subscriber failed.',
						'%d subscribers failed.',
						$result['failed'],
						'mail-system'
					),
					$result['failed']
				);
			}

			wp_send_json_success(
				array(
					'message' => $message,
					'success' => $result['success'],
					'failed'  => $result['failed'],
					'errors'  => $result['errors'],
				)
			);
		} else {
			wp_send_json_error(
				array(
					'message' => __( 'No subscribers were updated.', 'mail-system' ),
					'errors'  => $result['errors'],
				)
			);
		}
	}

	/**
	 * AJAX handler: Batch remove lists from subscribers.
	 *
	 * @return void
	 */
	public function batch_remove_lists(): void {
		check_ajax_referer( 'mskd_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You do not have permission for this operation.', 'mail-system' ),
				)
			);
		}

		// Get subscriber IDs.
		      // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Array is sanitized with intval below.
		if ( isset( $_POST['subscriber_ids'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Array is sanitized with intval below.
			$subscriber_ids = wp_unslash( $_POST['subscriber_ids'] );
		} else {
			$subscriber_ids = array();
		}
		if ( ! is_array( $subscriber_ids ) ) {
			$subscriber_ids = array();
		}
		$subscriber_ids = array_map( 'intval', $subscriber_ids );

		// Get list IDs.
		      // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Array is sanitized with intval below.
		if ( isset( $_POST['list_ids'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Array is sanitized with intval below.
			$list_ids = wp_unslash( $_POST['list_ids'] );
		} else {
			$list_ids = array();
		}
		if ( ! is_array( $list_ids ) ) {
			$list_ids = array();
		}
		$list_ids = array_map( 'intval', $list_ids );

		// Validate inputs.
		if ( empty( $subscriber_ids ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'No subscribers selected.', 'mail-system' ),
				)
			);
		}

		if ( empty( $list_ids ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'No lists selected.', 'mail-system' ),
				)
			);
		}

		// Perform batch removal.
		$service = new Subscriber_Service();
		$result  = $service->batch_remove_lists( $subscriber_ids, $list_ids );

		if ( $result['success'] > 0 ) {
			$message = sprintf(
				/* translators: %d: number of subscribers updated */
				_n(
					'%d subscriber updated successfully.',
					'%d subscribers updated successfully.',
					$result['success'],
					'mail-system'
				),
				$result['success']
			);

			if ( $result['failed'] > 0 ) {
				$message .= ' ' . sprintf(
					/* translators: %d: number of subscribers that failed */
					_n(
						'%d subscriber failed.',
						'%d subscribers failed.',
						$result['failed'],
						'mail-system'
					),
					$result['failed']
				);
			}

			wp_send_json_success(
				array(
					'message' => $message,
					'success' => $result['success'],
					'failed'  => $result['failed'],
					'errors'  => $result['errors'],
				)
			);
		} else {
			wp_send_json_error(
				array(
					'message' => __( 'No subscribers were updated.', 'mail-system' ),
					'errors'  => $result['errors'],
				)
			);
		}
	}

	/**
	 * AJAX handler: Preview email with full header and footer.
	 *
	 * Returns HTML output of the complete email (header + body + footer)
	 * for display in preview iframe. This allows accurate preview of the
	 * final email appearance before sending.
	 *
	 * Security: Nonce verified, admin-only, content sanitized.
	 *
	 * @return void
	 */
	public function preview_email(): void {
		// Verify nonce.
		if ( ! check_ajax_referer( 'mskd_preview_nonce', 'nonce', false ) ) {
			wp_die( esc_html__( 'Invalid request. Please refresh the page.', 'mail-system' ), 403 );
		}

		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission for this operation.', 'mail-system' ), 403 );
		}

		// Get email content from POST or campaign ID.
		$content     = '';
		$campaign_id = isset( $_POST['campaign_id'] ) ? intval( $_POST['campaign_id'] ) : 0;

		if ( $campaign_id > 0 ) {
			// Load content from campaign in database.
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Simple read for preview, no caching needed.
			$campaign = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT body FROM {$wpdb->prefix}mskd_campaigns WHERE id = %d",
					$campaign_id
				)
			);

			if ( $campaign ) {
				$content = $campaign->body;
			}
		} elseif ( isset( $_POST['content'] ) ) {
			// Use content from POST (for compose wizard preview).
			// Sanitize email HTML using our custom sanitizer that allows email-specific tags.
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized with mskd_kses_email() below.
			$content = mskd_kses_email( wp_unslash( $_POST['content'] ) );
		}

		// If no content provided, return error.
		if ( empty( $content ) ) {
			wp_die( esc_html__( 'No content to preview.', 'mail-system' ), 400 );
		}

		// Get plugin settings for header/footer.
		$settings = get_option( 'mskd_settings', array() );

		// Apply header and footer to content using static helper method.
		$full_content = self::render_email_with_header_footer( $content, $settings );

		// Output the full HTML directly (for iframe display).
		// No JSON wrapper needed - iframe expects raw HTML.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Admin-only preview of email HTML, sanitized with mskd_kses_email() above.
		echo $full_content;
		wp_die();
	}

	/**
	 * Apply header and footer to email content.
	 *
	 * Static helper method that applies the configured email header and footer
	 * to the email body. Uses the same logic as the Email_Header_Footer trait.
	 *
	 * @param string $content  Email body content.
	 * @param array  $settings Plugin settings array containing 'email_header' and 'email_footer' keys.
	 * @return string Email content with header prepended and footer appended.
	 */
	public static function render_email_with_header_footer( string $content, array $settings ): string {
		$header = $settings['email_header'] ?? '';
		$footer = $settings['email_footer'] ?? '';

		// Only modify content if header or footer is set.
		if ( empty( $header ) && empty( $footer ) ) {
			return $content;
		}

		// Prepend header if set.
		if ( ! empty( $header ) ) {
			$content = $header . $content;
		}

		// Append footer if set.
		if ( ! empty( $footer ) ) {
			$content = $content . $footer;
		}

		return $content;
	}

	/**
	 * AJAX handler: Batch delete subscribers.
	 *
	 * Deletes multiple subscribers at once.
	 *
	 * Security: Nonce verified, admin-only, IDs sanitized.
	 *
	 * @return void
	 */
	public function batch_delete_subscribers(): void {
		check_ajax_referer( 'mskd_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You do not have permission for this operation.', 'mail-system' ),
				)
			);
		}

		// Get subscriber IDs.
		      // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Array is sanitized with intval below.
		if ( isset( $_POST['subscriber_ids'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Array is sanitized with intval below.
			$subscriber_ids = wp_unslash( $_POST['subscriber_ids'] );
		} else {
			$subscriber_ids = array();
		}
		if ( ! is_array( $subscriber_ids ) ) {
			$subscriber_ids = array();
		}
		$subscriber_ids = array_map( 'intval', $subscriber_ids );

		// Validate inputs.
		if ( empty( $subscriber_ids ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'No subscribers selected.', 'mail-system' ),
				)
			);
		}

		// Perform batch deletion.
		$service = new Subscriber_Service();
		$result  = $service->batch_delete( $subscriber_ids );

		if ( $result['success'] > 0 ) {
			$message = sprintf(
				/* translators: %d: number of subscribers deleted */
				_n(
					'%d subscriber deleted successfully.',
					'%d subscribers deleted successfully.',
					$result['success'],
					'mail-system'
				),
				$result['success']
			);

			if ( $result['failed'] > 0 ) {
				$message .= ' ' . sprintf(
					/* translators: %d: number of subscribers that failed */
					_n(
						'%d subscriber failed.',
						'%d subscribers failed.',
						$result['failed'],
						'mail-system'
					),
					$result['failed']
				);
			}

			wp_send_json_success(
				array(
					'message' => $message,
					'success' => $result['success'],
					'failed'  => $result['failed'],
					'errors'  => $result['errors'],
				)
			);
		} else {
			wp_send_json_error(
				array(
					'message' => __( 'No subscribers were deleted.', 'mail-system' ),
					'errors'  => $result['errors'],
				)
			);
		}
	}

	/**
	 * AJAX handler: Delete all inactive (unconfirmed) and unsubscribed subscribers.
	 *
	 * Deletes every subscriber whose status is 'inactive' or 'unsubscribed', together with
	 * their list associations and any queued items.
	 *
	 * Security: Nonce verified, admin-only.
	 *
	 * @return void
	 */
	public function delete_inactive_subscribers(): void {
		check_ajax_referer( 'mskd_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You do not have permission for this operation.', 'mail-system' ),
				)
			);
		}

		$service = new Subscriber_Service();
		$deleted = $service->delete_inactive();

		if ( 0 === $deleted ) {
			wp_send_json_success(
				array(
					'message' => __( 'No inactive or unsubscribed subscribers found to delete.', 'mail-system' ),
				)
			);
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %d: number of subscribers deleted */
					_n(
						'%d inactive or unsubscribed subscriber deleted successfully.',
						'%d inactive or unsubscribed subscribers deleted successfully.',
						$deleted,
						'mail-system'
					),
					$deleted
				),
			)
		);
	}
}
