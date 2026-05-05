<?php
/**
 * Admin Email Controller
 *
 * Handles email compose and one-time email admin actions.
 *
 * @package MSKD\Admin
 * @since   1.1.0
 */

namespace MSKD\Admin;

use MSKD\Services\Email_Service;
use MSKD\Traits\Email_Header_Footer;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Admin_Email
 *
 * Controller for email compose and one-time email pages.
 */
class Admin_Email {

	use Email_Header_Footer;

	/**
	 * Email service instance.
	 *
	 * @var Email_Service
	 */
	private $service;

	/**
	 * Form data preserved on error for one-time email.
	 *
	 * @var array
	 */
	private $one_time_email_form_data = array();

	/**
	 * Last mail error.
	 *
	 * @var string
	 */
	private $last_mail_error = '';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->service = new Email_Service();
	}

	/**
	 * Handle email-related actions.
	 *
	 * @return void
	 */
	public function handle_actions(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Handle wizard step 1 submission (template selection).
		$this->handle_wizard_step1();

		// Handle wizard step 2 submission (HTML editor mode).
		$this->handle_wizard_step2();

		// Handle compose/send action.
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce check only.
		if ( isset( $_POST['mskd_send_email'] ) && isset( $_POST['mskd_nonce'] ) && wp_verify_nonce( wp_unslash( $_POST['mskd_nonce'] ), 'mskd_send_email' ) ) {
			$this->handle_queue_email();
		}

		// Handle one-time email send action.
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce check only.
		if ( isset( $_POST['mskd_send_one_time_email'] ) && isset( $_POST['mskd_nonce'] ) && wp_verify_nonce( wp_unslash( $_POST['mskd_nonce'] ), 'mskd_send_one_time_email' ) ) {
			$this->handle_one_time_email();
		}
	}

	/**
	 * Handle wizard step 1 submission (template selection).
	 *
	 * @return void
	 */
	private function handle_wizard_step1(): void {
		if ( ! isset( $_POST['mskd_wizard_step1'] ) ) {
			return;
		}

		if ( ! isset( $_POST['mskd_wizard_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mskd_wizard_nonce'] ) ), 'mskd_compose_wizard' ) ) {
			return;
		}

		// Load Template Service.
		$template_service = new \MSKD\Services\Template_Service();

		// Get session data.
		$session_key  = 'mskd_compose_wizard_' . get_current_user_id();
		$session_data = get_transient( $session_key );
		if ( ! is_array( $session_data ) ) {
			$session_data = array(
				'template_id'   => 0,
				'use_visual'    => false,
				'subject'       => '',
				'content'       => '',
				'json_content'  => '',
				'lists'         => array(),
				'schedule_type' => 'now',
			);
		}

		$choice = isset( $_POST['template_choice'] ) ? sanitize_text_field( wp_unslash( $_POST['template_choice'] ) ) : 'scratch';

		if ( 'template' === $choice && ! empty( $_POST['template_id'] ) ) {
			$template_id = intval( $_POST['template_id'] );
			$template    = $template_service->get_by_id( $template_id );
			if ( $template ) {
				$session_data['template_id']  = $template_id;
				$session_data['subject']      = $template->subject;
				$session_data['content']      = $template->content;
				$session_data['json_content'] = $template->json_content;
				$session_data['use_visual']   = ! empty( $template->json_content );
			}
		} elseif ( 'visual' === $choice ) {
			$session_data['template_id']  = 0;
			$session_data['subject']      = '';
			$session_data['content']      = '';
			$session_data['json_content'] = '';
			$session_data['use_visual']   = true;
		} else {
			// Scratch / HTML editor.
			$session_data['template_id']  = 0;
			$session_data['subject']      = '';
			$session_data['content']      = '';
			$session_data['json_content'] = '';
			$session_data['use_visual']   = false;
		}

		set_transient( $session_key, $session_data, HOUR_IN_SECONDS );
		wp_safe_redirect( admin_url( 'admin.php?page=mskd-compose&step=2' ) );
		exit;
	}

	/**
	 * Handle wizard step 2 submission (HTML editor content).
	 *
	 * @return void
	 */
	private function handle_wizard_step2(): void {
		if ( ! isset( $_POST['mskd_wizard_step2'] ) ) {
			return;
		}

		if ( ! isset( $_POST['mskd_wizard_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mskd_wizard_nonce'] ) ), 'mskd_compose_wizard' ) ) {
			return;
		}

		// Get session data.
		$session_key  = 'mskd_compose_wizard_' . get_current_user_id();
		$session_data = get_transient( $session_key );
		if ( ! is_array( $session_data ) ) {
			$session_data = array();
		}

		$session_data['subject'] = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
		// Email HTML content must be preserved exactly (including <style> tags for MJML output).
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Admin-only, nonce-verified email content.
		$session_data['content'] = isset( $_POST['body'] ) ? wp_unslash( $_POST['body'] ) : '';

		set_transient( $session_key, $session_data, HOUR_IN_SECONDS );
		wp_safe_redirect( admin_url( 'admin.php?page=mskd-compose&step=3' ) );
		exit;
	}

	/**
	 * Handle queue email (campaign) action.
	 *
	 * @return void
	 */
	private function handle_queue_email(): void {
		// Load the List Provider service.
		require_once MSKD_PLUGIN_DIR . 'includes/services/class-mskd-list-provider.php';

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions() before calling this method.
		$subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
		// Email HTML content must be preserved exactly (including <style> tags for MJML output).
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- Admin-only, nonce-verified email content in handle_actions().
		$body = isset( $_POST['body'] ) ? wp_unslash( $_POST['body'] ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions() before calling this method.
		$list_ids = isset( $_POST['lists'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['lists'] ) ) : array();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions() before calling this method.
		$bcc = isset( $_POST['bcc'] ) ? sanitize_text_field( wp_unslash( $_POST['bcc'] ) ) : '';

		if ( empty( $subject ) || empty( $body ) || empty( $list_ids ) ) {
			add_settings_error(
				'mskd_messages',
				'mskd_error',
				__( 'Please fill in all fields.', 'mail-system' ),
				'error'
			);
			return;
		}

		// Validate custom from email if provided
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions() before calling this method.
		$use_custom_from = isset( $_POST['use_custom_from'] ) ? sanitize_text_field( wp_unslash( $_POST['use_custom_from'] ) ) : 'default';
		$from_email      = '';
		$from_name       = '';

		if ( 'custom' === $use_custom_from ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions() before calling this method.
			$from_email = isset( $_POST['from_email'] ) ? sanitize_email( wp_unslash( $_POST['from_email'] ) ) : '';
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions() before calling this method.
			$from_name = isset( $_POST['from_name'] ) ? sanitize_text_field( wp_unslash( $_POST['from_name'] ) ) : '';

			// Required validation
			if ( empty( $from_email ) ) {
				add_settings_error(
					'mskd_messages',
					'mskd_error',
					__( 'Custom sender email is required when using custom sender option.', 'mail-system' ),
					'error'
				);
				return;
			}

			// Format validation
			if ( ! is_email( $from_email ) ) {
				add_settings_error(
					'mskd_messages',
					'mskd_error',
					__( 'Invalid custom sender email address.', 'mail-system' ),
					'error'
				);
				return;
			}
		}

		// Validate Bcc email addresses if provided.
		$bcc_validation = $this->validate_bcc_emails( $bcc );
		if ( true !== $bcc_validation ) {
			add_settings_error(
				'mskd_messages',
				'mskd_error',
				$bcc_validation,
				'error'
			);
			return;
		}

		// Get active subscribers from selected lists with full data.
		$all_subscribers = array();
		$seen_emails     = array();

		foreach ( $list_ids as $list_id ) {
			$list_subscribers = \MSKD_List_Provider::get_list_subscribers_full( $list_id );
			foreach ( $list_subscribers as $subscriber ) {
				// Dedupe by email.
				if ( ! in_array( $subscriber->email, $seen_emails, true ) ) {
					$all_subscribers[] = $subscriber;
					$seen_emails[]     = $subscriber->email;
				}
			}
		}

		if ( empty( $all_subscribers ) ) {
			add_settings_error(
				'mskd_messages',
				'mskd_error',
				__( 'No active subscribers in the selected lists.', 'mail-system' ),
				'error'
			);
			return;
		}

		// Calculate scheduled time.
		$scheduled_at = $this->service->calculate_scheduled_time( $_POST );
		$is_immediate = $this->service->is_immediate_send( $_POST );

		// Queue the campaign.
		$campaign_id = $this->service->queue_campaign(
			array(
				'subject'      => $subject,
				'body'         => $body,
				'list_ids'     => $list_ids,
				'subscribers'  => $all_subscribers,
				'scheduled_at' => $scheduled_at,
				'bcc'          => $bcc,
				'from_email'   => $from_email,
				'from_name'    => $from_name,
			)
		);

		if ( ! $campaign_id ) {
			add_settings_error(
				'mskd_messages',
				'mskd_error',
				__( 'Error creating campaign.', 'mail-system' ),
				'error'
			);
			return;
		}

		$queued = count( $all_subscribers );

		if ( $is_immediate ) {
			$message = sprintf(
				__( '%d emails have been added to the sending queue.', 'mail-system' ),
				$queued
			);
		} else {
			// Format scheduled time for display.
			$wp_timezone    = wp_timezone();
			$scheduled_date = new \DateTime( $scheduled_at, $wp_timezone );
			$formatted_date = $scheduled_date->format( 'd.m.Y H:i' );

			$message = sprintf(
				__( '%1$d emails have been scheduled for %2$s.', 'mail-system' ),
				$queued,
				esc_html( $formatted_date )
			);
		}

		// Set transient for the success message to be displayed on the queue page.
		set_transient( 'mskd_campaign_success_message', $message, 60 );

		// Redirect to the queue page.
		wp_safe_redirect( admin_url( 'admin.php?page=mskd-queue&campaign_created=1' ) );
		exit;
	}

	/**
	 * Handle one-time email action.
	 *
	 * @return void
	 */
	private function handle_one_time_email(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions() before calling this method.
		$recipient_email = isset( $_POST['recipient_email'] ) ? sanitize_email( wp_unslash( $_POST['recipient_email'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions() before calling this method.
		$recipient_name = isset( $_POST['recipient_name'] ) ? sanitize_text_field( wp_unslash( $_POST['recipient_name'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions() before calling this method.
		$subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
		// Email HTML content must be preserved exactly (including <style> tags for MJML output).
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- Admin-only, nonce-verified email content in handle_actions().
		$body = isset( $_POST['body'] ) ? wp_unslash( $_POST['body'] ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions() before calling this method.
		$schedule_type = isset( $_POST['schedule_type'] ) ? sanitize_text_field( wp_unslash( $_POST['schedule_type'] ) ) : 'now';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions() before calling this method.
		$bcc = isset( $_POST['bcc'] ) ? sanitize_text_field( wp_unslash( $_POST['bcc'] ) ) : '';

		// Validate custom from email if provided
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions() before calling this method.
		$use_custom_from = isset( $_POST['use_custom_from'] ) ? sanitize_text_field( wp_unslash( $_POST['use_custom_from'] ) ) : 'default';
		$from_email      = '';
		$from_name       = '';

		if ( 'custom' === $use_custom_from ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions() before calling this method.
			$from_email = isset( $_POST['from_email'] ) ? sanitize_email( wp_unslash( $_POST['from_email'] ) ) : '';
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions() before calling this method.
			$from_name = isset( $_POST['from_name'] ) ? sanitize_text_field( wp_unslash( $_POST['from_name'] ) ) : '';

			// Required validation
			if ( empty( $from_email ) ) {
				add_settings_error(
					'mskd_messages',
					'mskd_error',
					__( 'Custom sender email is required when using custom sender option.', 'mail-system' ),
					'error'
				);
				return;
			}

			// Format validation
			if ( ! is_email( $from_email ) ) {
				add_settings_error(
					'mskd_messages',
					'mskd_error',
					__( 'Invalid custom sender email address.', 'mail-system' ),
					'error'
				);
				return;
			}
		}

		// Store form data for preservation on error.
		$this->one_time_email_form_data = array(
			'recipient_email'    => $recipient_email,
			'recipient_name'     => $recipient_name,
			'subject'            => $subject,
			'body'               => $body,
			'schedule_type'      => $schedule_type,
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions() before calling this method.
			'scheduled_datetime' => isset( $_POST['scheduled_datetime'] ) ? sanitize_text_field( wp_unslash( $_POST['scheduled_datetime'] ) ) : '',
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions() before calling this method.
			'delay_value'        => isset( $_POST['delay_value'] ) ? intval( $_POST['delay_value'] ) : 1,
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions() before calling this method.
			'delay_unit'         => isset( $_POST['delay_unit'] ) ? sanitize_text_field( wp_unslash( $_POST['delay_unit'] ) ) : 'hours',
			'bcc'                => $bcc,
			'from_email'         => $from_email,
			'from_name'          => $from_name,
		);

		// Validate required fields.
		if ( empty( $recipient_email ) || empty( $subject ) || empty( $body ) ) {
			add_settings_error(
				'mskd_messages',
				'mskd_error',
				__( 'Please fill in all required fields.', 'mail-system' ),
				'error'
			);
			return;
		}

		// Validate email format.
		if ( ! is_email( $recipient_email ) ) {
			add_settings_error(
				'mskd_messages',
				'mskd_error',
				__( 'Invalid recipient email address.', 'mail-system' ),
				'error'
			);
			return;
		}

		// Validate Bcc email addresses if provided.
		$bcc_validation = $this->validate_bcc_emails( $bcc );
		if ( true !== $bcc_validation ) {
			add_settings_error(
				'mskd_messages',
				'mskd_error',
				$bcc_validation,
				'error'
			);
			return;
		}

		// Replace basic placeholders.
		$body    = str_replace(
			array( '{recipient_name}', '{recipient_email}' ),
			array( $recipient_name, $recipient_email ),
			$body
		);
		$subject = str_replace(
			array( '{recipient_name}', '{recipient_email}' ),
			array( $recipient_name, $recipient_email ),
			$subject
		);

		// Calculate scheduled time.
		$scheduled_at = $this->service->calculate_scheduled_time( $_POST );
		$is_immediate = $this->service->is_immediate_send( $_POST );

		// Get settings for header/footer.
		$settings = get_option( 'mskd_settings', array() );

		// Load SMTP Mailer.
		require_once MSKD_PLUGIN_DIR . 'includes/services/class-mskd-smtp-mailer.php';
		$mailer = new \MSKD_SMTP_Mailer();

		if ( $is_immediate ) {
			// Apply header/footer for immediate sends.
			$body_with_wrapper = $this->apply_header_footer( $body, $settings );

			// Replace subscriber placeholders (including those in header/footer).
			$body_with_wrapper = $this->replace_one_time_placeholders( $body_with_wrapper, $recipient_email, $recipient_name );

			// Build headers array including Bcc if provided.
			$headers = array();
			if ( ! empty( $bcc ) ) {
				$bcc_emails = array_map( 'trim', explode( ',', $bcc ) );
				foreach ( $bcc_emails as $bcc_email ) {
					if ( ! empty( $bcc_email ) && is_email( $bcc_email ) ) {
						$headers[] = 'Bcc: ' . $bcc_email;
					}
				}
			}

			// Send immediately (via SMTP if configured, otherwise via PHP mail) with custom from email if provided.
			$sent = $mailer->send( $recipient_email, $subject, $body_with_wrapper, $headers, $from_email, $from_name );

			if ( ! $sent ) {
				$this->last_mail_error = $mailer->get_last_error();
			}

			// Queue for logging.
			$this->service->queue_one_time(
				array(
					'recipient_email' => $recipient_email,
					'recipient_name'  => $recipient_name,
					'subject'         => $subject,
					'body'            => $body,
					'scheduled_at'    => $scheduled_at,
					'is_immediate'    => true,
					'sent'            => $sent,
					'error_message'   => $sent ? null : ( $this->last_mail_error ?: __( 'wp_mail() failed for one-time email', 'mail-system' ) ),
					'bcc'             => $bcc,
					'from_email'      => $from_email,
					'from_name'       => $from_name,
				)
			);

			if ( $sent ) {
				// Clear form data on success.
				$this->one_time_email_form_data = array();
				add_settings_error(
					'mskd_messages',
					'mskd_success',
					sprintf(
						__( 'One-time email sent successfully to %s.', 'mail-system' ),
						esc_html( $recipient_email )
					),
					'success'
				);
			} else {
				$error_message = __( 'Error sending one-time email.', 'mail-system' );
				if ( ! empty( $this->last_mail_error ) ) {
					$error_message .= ' ' . sprintf(
						__( 'Reason: %s', 'mail-system' ),
						esc_html( $this->last_mail_error )
					);
				} else {
					$error_message .= ' ' . __( 'Please try again.', 'mail-system' );
				}
				add_settings_error( 'mskd_messages', 'mskd_error', $error_message, 'error' );
			}
		} else {
			// Schedule for later.
			$result = $this->service->queue_one_time(
				array(
					'recipient_email' => $recipient_email,
					'recipient_name'  => $recipient_name,
					'subject'         => $subject,
					'body'            => $body,
					'scheduled_at'    => $scheduled_at,
					'is_immediate'    => false,
					'bcc'             => $bcc,
					'from_email'      => $from_email,
					'from_name'       => $from_name,
				)
			);

			if ( $result ) {
				// Clear form data on success.
				$this->one_time_email_form_data = array();

				// Format scheduled time for display.
				$wp_timezone    = wp_timezone();
				$scheduled_date = new \DateTime( $scheduled_at, $wp_timezone );
				$formatted_date = $scheduled_date->format( 'd.m.Y H:i' );

				add_settings_error(
					'mskd_messages',
					'mskd_success',
					sprintf(
						__( 'One-time email to %1$s has been scheduled for %2$s.', 'mail-system' ),
						esc_html( $recipient_email ),
						esc_html( $formatted_date )
					),
					'success'
				);
			} else {
				add_settings_error(
					'mskd_messages',
					'mskd_error',
					__( 'Error scheduling email. Please try again.', 'mail-system' ),
					'error'
				);
			}
		}
	}

	/**
	 * Validate Bcc email addresses.
	 *
	 * @param string $bcc Comma-separated Bcc email addresses.
	 * @return bool|string True if valid, error message string if invalid.
	 */
	private function validate_bcc_emails( string $bcc ) {
		if ( empty( $bcc ) ) {
			return true;
		}

		$bcc_emails = array_map( 'trim', explode( ',', $bcc ) );
		foreach ( $bcc_emails as $bcc_email ) {
			if ( ! empty( $bcc_email ) && ! is_email( $bcc_email ) ) {
				return sprintf(
					/* translators: %s: Invalid email address */
					__( 'Invalid Bcc email address: %s', 'mail-system' ),
					esc_html( $bcc_email )
				);
			}
		}

		return true;
	}

	/**
	 * Replace subscriber placeholders for one-time emails.
	 *
	 * Checks if the recipient is an existing subscriber and uses their data.
	 * For non-subscribers, uses the provided name and removes unsubscribe links.
	 *
	 * @param string $content         Email content.
	 * @param string $recipient_email Recipient email address.
	 * @param string $recipient_name  Recipient name.
	 * @return string Content with placeholders replaced.
	 */
	private function replace_one_time_placeholders( string $content, string $recipient_email, string $recipient_name ): string {
		global $wpdb;

		// Check if recipient is an existing subscriber.
		$table_name = $wpdb->prefix . 'mskd_subscribers';
		$subscriber = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE email = %s LIMIT 1",
				$recipient_email
			)
		);

		if ( $subscriber && ! empty( $subscriber->unsubscribe_token ) ) {
			// Subscriber exists - use their data.
			$unsubscribe_url = add_query_arg(
				array(
					'mskd_unsubscribe' => $subscriber->unsubscribe_token,
				),
				home_url()
			);

			$first_name = ! empty( $subscriber->first_name ) ? $subscriber->first_name : $recipient_name;
			$last_name  = ! empty( $subscriber->last_name ) ? $subscriber->last_name : '';

			$placeholders = array(
				'{first_name}'       => $first_name,
				'{last_name}'        => $last_name,
				'{email}'            => $subscriber->email,
				'{unsubscribe_link}' => '<a href="' . esc_url( $unsubscribe_url ) . '">' . __( 'Unsubscribe', 'mail-system' ) . '</a>',
				'{unsubscribe_url}'  => $unsubscribe_url,
			);
		} else {
			// Not a subscriber - use provided data and remove unsubscribe links.
			$placeholders = array(
				'{first_name}'       => $recipient_name,
				'{last_name}'        => '',
				'{email}'            => $recipient_email,
				'{unsubscribe_link}' => '',
				'{unsubscribe_url}'  => '',
			);
		}

		return str_replace( array_keys( $placeholders ), array_values( $placeholders ), $content );
	}

	/**
	 * Render the compose page.
	 *
	 * @return void
	 */
	public function render_compose(): void {
		include MSKD_PLUGIN_DIR . 'admin/partials/compose-wizard.php';
	}

	/**
	 * Render the legacy compose page (direct access).
	 *
	 * @return void
	 */
	public function render_compose_legacy(): void {
		include MSKD_PLUGIN_DIR . 'admin/partials/compose.php';
	}

	/**
	 * Render the one-time email page.
	 *
	 * @return void
	 */
	public function render_one_time(): void {
		$form_data = $this->get_one_time_email_form_data();
		include MSKD_PLUGIN_DIR . 'admin/partials/one-time-email.php';
	}

	/**
	 * Get preserved form data for one-time email.
	 *
	 * @return array
	 */
	public function get_one_time_email_form_data(): array {
		return $this->one_time_email_form_data;
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
