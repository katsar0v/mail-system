<?php
/**
 * Cron Handler Service
 *
 * @package MSKD
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once MSKD_PLUGIN_DIR . 'includes/class-mskd-environment.php';

use MSKD\Traits\Email_Header_Footer;

/**
 * Class MSKD_Cron_Handler
 *
 * Handles WP-Cron queue processing
 */
class MSKD_Cron_Handler {

	use Email_Header_Footer;

	/**
	 * Maximum retry attempts for failed emails
	 */
	const MAX_ATTEMPTS = 3;

	/**
	 * Timeout in minutes for stuck processing emails
	 */
	const PROCESSING_TIMEOUT_MINUTES = 5;

	/**
	 * SMTP Mailer instance.
	 *
	 * @var MSKD_SMTP_Mailer|null
	 */
	private $smtp_mailer = null;

	/**
	 * Email tracking service instance.
	 *
	 * @var \MSKD\Services\Email_Tracking_Service|null
	 */
	private $tracking_service = null;

	/**
	 * Initialize cron hooks
	 */
	public function init() {
		add_action( 'mskd_process_queue', array( $this, 'process_queue' ) );
		$this->maybe_schedule_cron();
	}

	/**
	 * Ensure the recurring queue event is scheduled.
	 *
	 * Self-heals the `mskd_process_queue` event so the queue keeps processing
	 * even if the event was dropped from the WP-Cron array (e.g. during a plugin
	 * update, a momentary deactivation, or when the custom schedule was
	 * unavailable at reschedule time) without requiring re-activation.
	 */
	private function maybe_schedule_cron() {
		if ( ! wp_next_scheduled( 'mskd_process_queue' ) ) {
			// Schedule at the start of the next minute (00 seconds).
			$next_minute = mskd_normalize_timestamp( time() + 60 );
			wp_schedule_event( $next_minute, 'mskd_every_minute', 'mskd_process_queue' );
		}
	}

	/**
	 * Process email queue
	 *
	 * Sends up to the configured emails_per_minute limit per run.
	 * All subscribers (internal, external, one-time) are stored in the subscribers table.
	 */
	public function process_queue() {
		// Keep queued email pending while local delivery is disabled.
		if ( MSKD_Environment::is_local() ) {
			return;
		}

		global $wpdb;

		// Record the cron run timestamp at the start of processing.
		// This indicates when the cron was last triggered, useful for verifying cron health.
		update_option( 'mskd_last_cron_run', time() );

		// First, recover stuck emails (processing for too long).
		$this->recover_stuck_emails();

		// Get the configured batch size (emails per minute).
		$batch_size = $this->get_batch_size();

		// Get pending emails - all subscribers are now in the subscribers table.
		// Join with campaigns table to get Bcc information and custom from email data.
		$queue_items = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names are hardcoded and safe.
				"SELECT q.*, s.email, s.first_name, s.last_name, s.unsubscribe_token,
				       c.bcc, c.bcc_sent, c.type as campaign_type, c.from_email, c.from_name
				FROM {$wpdb->prefix}mskd_queue q
				INNER JOIN {$wpdb->prefix}mskd_subscribers s ON q.subscriber_id = s.id
				LEFT JOIN {$wpdb->prefix}mskd_campaigns c ON q.campaign_id = c.id
				WHERE q.status = 'pending'
				AND q.scheduled_at <= %s
				AND s.status = 'active'
				ORDER BY q.scheduled_at ASC
				LIMIT %d",
				current_time( 'mysql' ),
				$batch_size
			)
		);

		if ( empty( $queue_items ) ) {
			return;
		}

		// Get settings.
		$settings = get_option( 'mskd_settings', array() );

		// Initialize mailer (uses SMTP if configured, otherwise PHP mail).
		require_once MSKD_PLUGIN_DIR . 'includes/services/class-mskd-smtp-mailer.php';
		$this->smtp_mailer      = new MSKD_SMTP_Mailer( $settings );
		$this->tracking_service = new \MSKD\Services\Email_Tracking_Service();
		$bcc_sent_campaigns     = array();

		foreach ( $queue_items as $item ) {
			// Skip items with invalid email.
			if ( empty( $item->email ) || ! is_email( $item->email ) ) {
				$wpdb->update(
					$wpdb->prefix . 'mskd_queue',
					array(
						'status'        => 'failed',
						'error_message' => __( 'Invalid or missing email address', 'mail-system' ),
					),
					array( 'id' => $item->id ),
					array( '%s', '%s' ),
					array( '%d' )
				);
				continue;
			}

			// Atomically claim the item: only the worker that flips it from `pending`
			// to `processing` proceeds. This prevents two overlapping cron runs from
			// sending the same email twice.
			$claimed = $wpdb->update(
				$wpdb->prefix . 'mskd_queue',
				array(
					'status'                => 'processing',
					'attempts'              => $item->attempts + 1,
					'processing_started_at' => current_time( 'mysql' ),
				),
				array(
					'id'     => $item->id,
					'status' => 'pending',
				),
				array( '%s', '%d', '%s' ),
				array( '%d', '%s' )
			);

			if ( ! $claimed ) {
				// Another cron run already claimed this item.
				continue;
			}

			// Prepare headers for Bcc if available.
			// For regular campaigns, only send Bcc with the first email (bcc_sent = 0).
			// For one_time campaigns, always send Bcc (they only have one email).
			$headers         = array();
			$should_send_bcc = false;

			if ( ! empty( $item->bcc ) ) {
				// Check if we should send Bcc for this email.
				if ( 'one_time' === $item->campaign_type ) {
					// One-time emails: always send Bcc.
					$should_send_bcc = true;
				} elseif ( ! empty( $item->campaign_id ) && empty( $item->bcc_sent ) && ! isset( $bcc_sent_campaigns[ (int) $item->campaign_id ] ) ) {
					// Regular campaigns: send Bcc only if not already sent.
					$should_send_bcc = true;
				}

				if ( $should_send_bcc ) {
					// Parse multiple Bcc addresses separated by commas.
					$bcc_emails = array_map( 'trim', explode( ',', $item->bcc ) );
					foreach ( $bcc_emails as $bcc_email ) {
						if ( ! empty( $bcc_email ) && is_email( $bcc_email ) ) {
							$headers[] = 'Bcc: ' . $bcc_email;
						}
					}
				}
			}

			// Prepare email content with header, footer, and placeholders. A message
			// carrying Bcc is deliberately untracked: otherwise a Bcc recipient's
			// image load or click would be credited to the primary queue recipient.
			$tracking_token = isset( $item->tracking_token ) ? (string) $item->tracking_token : '';
			$click_token    = isset( $item->click_token ) ? (string) $item->click_token : '';
			$body           = $this->apply_header_footer( $item->body, $settings );
			$body           = $this->replace_placeholders( $body, $item );
			$subject        = $this->replace_placeholders( $item->subject, $item );

			if ( ! $should_send_bcc ) {
				$body = $this->tracking_service->rewrite_links( $body, $click_token );
				$body = $this->tracking_service->append_tracking_pixel( $body, $tracking_token );
			}

			// Send email using SMTP mailer.
			$sent          = false;
			$error_message = '';

			$sent = $this->smtp_mailer->send(
				$item->email,
				$subject,
				$body,
				$headers,
				$item->from_email,
				$item->from_name
			);
			if ( ! $sent ) {
				$error_message = $this->smtp_mailer->get_last_error();
			}

			if ( $sent ) {
				// Mark as sent.
				$wpdb->update(
					$wpdb->prefix . 'mskd_queue',
					array(
						'status'  => 'sent',
						'sent_at' => current_time( 'mysql' ),
					),
					array( 'id' => $item->id ),
					array( '%s', '%s' ),
					array( '%d' )
				);

				// If Bcc was sent with this email, mark the campaign as bcc_sent.
				if ( $should_send_bcc && ! empty( $item->campaign_id ) && 'campaign' === $item->campaign_type ) {
					$wpdb->update(
						$wpdb->prefix . 'mskd_campaigns',
						array( 'bcc_sent' => 1 ),
						array( 'id' => $item->campaign_id ),
						array( '%d' ),
						array( '%d' )
					);
					$bcc_sent_campaigns[ (int) $item->campaign_id ] = true;
				}

				// Log Bcc recipients for audit/compliance.
				if ( $should_send_bcc && ! empty( $item->bcc ) && defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
					error_log(
						sprintf(
							'[MSKD] Campaign %d: Email sent to %s with Bcc to: %s',
							$item->campaign_id,
							$item->email,
							$item->bcc
						)
					);
				}
			} else {
				// Check if we should retry or mark as failed.
				$new_attempts = $item->attempts + 1;

				// Build error message with details.
				$base_error = __( 'SMTP sending failed', 'mail-system' );

				if ( $new_attempts < self::MAX_ATTEMPTS ) {
					// Schedule for retry - set back to pending with delayed schedule.
					$retry_delay   = $new_attempts * 2; // 2, 4 minutes delay
					$retry_message = sprintf(
						/* translators: 1: Attempt number, 2: Error details */
						__( 'Attempt %1$d failed. %2$s Will retry.', 'mail-system' ),
						$new_attempts,
						$error_message ? '(' . $error_message . ')' : ''
					);
					// Normalize to 00 seconds.
					$retry_timestamp = mskd_normalize_timestamp( strtotime( "+{$retry_delay} minutes" ) );
					$wpdb->update(
						$wpdb->prefix . 'mskd_queue',
						array(
							'status'        => 'pending',
							'scheduled_at'  => mskd_local_time_from_timestamp( $retry_timestamp ),
							'error_message' => $retry_message,
						),
						array( 'id' => $item->id ),
						array( '%s', '%s', '%s' ),
						array( '%d' )
					);
				} else {
					// Max attempts reached, mark as failed.
					$fail_message = sprintf(
						/* translators: 1: Base error message, 2: Max attempts, 3: Error details */
						__( '%1$s after %2$d attempts. %3$s', 'mail-system' ),
						$base_error,
						self::MAX_ATTEMPTS,
						$error_message ? '(' . $error_message . ')' : ''
					);
					$wpdb->update(
						$wpdb->prefix . 'mskd_queue',
						array(
							'status'        => 'failed',
							'error_message' => $fail_message,
						),
						array( 'id' => $item->id ),
						array( '%s', '%s' ),
						array( '%d' )
					);
				}
			}

			// Update campaign status if this item belongs to a campaign.
			if ( ! empty( $item->campaign_id ) ) {
				$this->update_campaign_status( $item->campaign_id );
			}
		}
	}

	/**
	 * Update campaign status based on queue item statuses
	 *
	 * @param int $campaign_id The campaign ID to update.
	 */
	private function update_campaign_status( $campaign_id ) {
		global $wpdb;

		// Get counts of queue items for this campaign.
		$stats = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is hardcoded and safe.
				"SELECT
		              COUNT(*) as total,
		              SUM(CASE WHEN status IN ('pending', 'processing') THEN 1 ELSE 0 END) as pending,
		              SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
		              SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
		              SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
		          FROM {$wpdb->prefix}mskd_queue
		          WHERE campaign_id = %d",
				$campaign_id
			)
		);

		if ( ! $stats ) {
			return;
		}

		// Determine campaign status.
		$pending = intval( $stats->pending );
		$total   = intval( $stats->total );

		if ( $pending > 0 ) {
			// Still has pending emails - mark as processing.
			$wpdb->update(
				$wpdb->prefix . 'mskd_campaigns',
				array( 'status' => 'processing' ),
				array( 'id' => $campaign_id ),
				array( '%s' ),
				array( '%d' )
			);
		} else {
			// All emails are done - mark as completed.
			$wpdb->update(
				$wpdb->prefix . 'mskd_campaigns',
				array(
					'status'       => 'completed',
					'completed_at' => current_time( 'mysql' ),
				),
				array( 'id' => $campaign_id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
		}
	}

	/**
	 * Recover stuck emails that have been processing for too long
	 */
	private function recover_stuck_emails() {
		global $wpdb;

		// Normalize to 00 seconds.
		$timeout_timestamp = mskd_normalize_timestamp( strtotime( '-' . self::PROCESSING_TIMEOUT_MINUTES . ' minutes' ) );
		$timeout_threshold = mskd_local_time_from_timestamp( $timeout_timestamp );

		// Find emails stuck in processing status. Recovery is keyed off when the item
		// was actually claimed (processing_started_at), not its original schedule, so a
		// far-future scheduled item that gets claimed and then stalls is still recovered.
		// Rows claimed before this column existed fall back to their schedule time.
		$stuck_items = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is hardcoded and safe.
				"SELECT id, attempts FROM {$wpdb->prefix}mskd_queue
		          WHERE status = 'processing'
		          AND ( processing_started_at < %s OR ( processing_started_at IS NULL AND scheduled_at < %s ) )",
				$timeout_threshold,
				$timeout_threshold
			)
		);

		foreach ( $stuck_items as $item ) {
			if ( $item->attempts < self::MAX_ATTEMPTS ) {
				// Reset to pending for retry.
				$wpdb->update(
					$wpdb->prefix . 'mskd_queue',
					array(
						'status'                => 'pending',
						'scheduled_at'          => mskd_current_time_normalized(),
						'processing_started_at' => null,
						'error_message'         => __( 'Recovered after stuck in processing', 'mail-system' ),
					),
					array( 'id' => $item->id ),
					array( '%s', '%s', '%s', '%s' ),
					array( '%d' )
				);
			} else {
				// Max attempts reached, mark as failed.
				$wpdb->update(
					$wpdb->prefix . 'mskd_queue',
					array(
						'status'        => 'failed',
						'error_message' => __( 'Failed after maximum attempts (stuck)', 'mail-system' ),
					),
					array( 'id' => $item->id ),
					array( '%s', '%s' ),
					array( '%d' )
				);
			}
		}
	}

	/**
	 * Replace placeholders in email content
	 *
	 * @param string $content Email content.
	 * @param object $subscriber Subscriber data.
	 * @return string
	 */
	private function replace_placeholders( $content, $subscriber ) {
		$unsubscribe_url = add_query_arg(
			array(
				'mskd_unsubscribe' => $subscriber->unsubscribe_token,
			),
			home_url()
		);

		$placeholders = array(
			'{first_name}'       => $subscriber->first_name,
			'{last_name}'        => $subscriber->last_name,
			'{email}'            => $subscriber->email,
			'{unsubscribe_link}' => '<a href="' . esc_url( $unsubscribe_url ) . '">' . __( 'Unsubscribe', 'mail-system' ) . '</a>',
			'{unsubscribe_url}'  => $unsubscribe_url,
		);

		return str_replace( array_keys( $placeholders ), array_values( $placeholders ), $content );
	}

	/**
	 * Get the batch size (emails per minute) from settings.
	 *
	 * Falls back to MSKD_BATCH_SIZE constant if setting is not configured.
	 *
	 * @return int The number of emails to send per minute.
	 */
	private function get_batch_size() {
		$settings = get_option( 'mskd_settings', array() );

		if ( isset( $settings['emails_per_minute'] ) && absint( $settings['emails_per_minute'] ) > 0 ) {
			return absint( $settings['emails_per_minute'] );
		}

		return MSKD_BATCH_SIZE;
	}
}
