<?php
/**
 * Campaign Application Service
 *
 * Reusable application service that turns a validated scheduling request into a
 * persisted campaign and queue. Shared by the admin compose controller and the
 * REST API so both entry points enforce identical business rules.
 *
 * @package MSKD\Application
 * @since   1.9.0
 */

namespace MSKD\Application;

use MSKD\Services\Email_Service;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Campaign_Service
 *
 * Orchestrates campaign validation, recipient resolution, deduplication and the
 * atomic persistence of a campaign together with its queue entries.
 */
class Campaign_Service {

	/**
	 * Email persistence service.
	 *
	 * @var Email_Service
	 */
	private $email_service;

	/**
	 * Constructor.
	 *
	 * @param Email_Service|null $email_service Optional persistence service (injected for testing).
	 */
	public function __construct( ?Email_Service $email_service = null ) {
		$this->email_service = $email_service ?? new Email_Service();
	}

	/**
	 * Validate and schedule a campaign.
	 *
	 * @param array $input {
	 *     Scheduling request. Values are expected to be pre-sanitized by the calling adapter.
	 *
	 *     @type string       $subject      Email subject.
	 *     @type string       $body         Email body (HTML). REST callers must pass content already
	 *                                       filtered through mskd_kses_email().
	 *     @type array        $list_ids     List identifiers (numeric database IDs or `ext_*` strings).
	 *     @type string|array $bcc          Optional Bcc recipients (comma string or array).
	 *     @type string       $from_email   Optional custom sender email.
	 *     @type string       $from_name    Optional custom sender name.
	 *     @type string       $scheduled_at MySQL datetime (WordPress timezone). Empty means immediate.
	 *     @type bool         $is_immediate Whether the send is immediate. Derived when omitted.
	 * }
	 * @return array {
	 *     Result of the operation.
	 *
	 *     @type bool        $success          Whether the campaign was created.
	 *     @type int|null    $campaign_id      Created campaign ID on success.
	 *     @type int         $queued           Number of recipients actually queued.
	 *     @type int         $total_recipients Number of resolved (deduplicated) recipients.
	 *     @type string      $scheduled_at     Effective scheduled time (MySQL datetime).
	 *     @type bool        $is_immediate     Whether the campaign sends immediately.
	 *     @type string|null $error_code       Machine-readable error code on failure.
	 *     @type string|null $error_message    Human-readable error message on failure.
	 * }
	 */
	public function schedule( array $input ): array {
		$subject    = isset( $input['subject'] ) ? trim( (string) $input['subject'] ) : '';
		$body       = isset( $input['body'] ) ? (string) $input['body'] : '';
		$list_ids   = isset( $input['list_ids'] ) && is_array( $input['list_ids'] ) ? $input['list_ids'] : array();
		$from_email = isset( $input['from_email'] ) ? trim( (string) $input['from_email'] ) : '';
		$from_name  = isset( $input['from_name'] ) ? trim( (string) $input['from_name'] ) : '';

		// Required-field validation.
		if ( '' === $subject ) {
			return $this->error( 'missing_subject', __( 'A subject is required.', 'mail-system' ) );
		}
		if ( '' === trim( wp_strip_all_tags( $body ) ) && '' === trim( $body ) ) {
			return $this->error( 'missing_body', __( 'A message body is required.', 'mail-system' ) );
		}
		if ( empty( $list_ids ) ) {
			return $this->error( 'missing_lists', __( 'At least one recipient list is required.', 'mail-system' ) );
		}

		// Sender validation.
		if ( '' !== $from_email && ! is_email( $from_email ) ) {
			return $this->error( 'invalid_sender', __( 'The custom sender email address is invalid.', 'mail-system' ) );
		}

		// Bcc validation and normalization.
		$bcc_result = $this->normalize_bcc( $input['bcc'] ?? '' );
		if ( isset( $bcc_result['error_code'] ) ) {
			return $bcc_result;
		}
		$bcc = $bcc_result['bcc'];

		// Resolve recipients from the selected lists.
		$resolution = $this->resolve_recipients( $list_ids );
		if ( isset( $resolution['error_code'] ) ) {
			return $resolution;
		}
		$recipients = $resolution['recipients'];

		if ( empty( $recipients ) ) {
			return $this->error( 'no_recipients', __( 'No active subscribers were found in the selected lists.', 'mail-system' ) );
		}

		// Determine scheduling.
		$scheduled_at = isset( $input['scheduled_at'] ) && '' !== (string) $input['scheduled_at']
			? (string) $input['scheduled_at']
			: mskd_current_time_normalized();
		$is_immediate = isset( $input['is_immediate'] )
			? (bool) $input['is_immediate']
			: ( $scheduled_at <= mskd_current_time_normalized() );

		// Persist atomically: campaign row and its queue entries succeed or fail together.
		// Atomicity relies on the plugin tables using InnoDB (the MySQL/MariaDB default);
		// on a MyISAM install these statements are silently ignored and the write is not
		// rolled back.
		$this->email_service->transaction( 'START TRANSACTION' );

		$campaign_id = $this->email_service->queue_campaign(
			array(
				'subject'      => $subject,
				'body'         => $body,
				'list_ids'     => array_map( 'strval', $list_ids ),
				'subscribers'  => $recipients,
				'scheduled_at' => $scheduled_at,
				'bcc'          => $bcc,
				'from_email'   => '' !== $from_email ? $from_email : null,
				'from_name'    => '' !== $from_name ? $from_name : null,
			)
		);

		if ( ! $campaign_id ) {
			$this->email_service->transaction( 'ROLLBACK' );
			return $this->error( 'db_error', __( 'The campaign could not be created.', 'mail-system' ) );
		}

		$queued = $this->email_service->get_last_queued_count();

		// If nothing was actually queued the whole write is undone rather than leaving an
		// empty campaign reported as a success.
		if ( $queued < 1 ) {
			$this->email_service->transaction( 'ROLLBACK' );
			return $this->error( 'no_recipients', __( 'No deliverable recipients remained when the campaign was queued.', 'mail-system' ) );
		}

		$this->email_service->transaction( 'COMMIT' );

		return array(
			'success'          => true,
			'campaign_id'      => (int) $campaign_id,
			'queued'           => (int) $queued,
			'total_recipients' => count( $recipients ),
			'scheduled_at'     => $scheduled_at,
			'is_immediate'     => $is_immediate,
			'error_code'       => null,
			'error_message'    => null,
		);
	}

	/**
	 * Normalize and validate Bcc recipients.
	 *
	 * @param string|array $bcc Raw Bcc value.
	 * @return array Either { bcc: string } or an error result.
	 */
	private function normalize_bcc( $bcc ): array {
		if ( is_array( $bcc ) ) {
			$candidates = $bcc;
		} else {
			$candidates = explode( ',', (string) $bcc );
		}

		$clean = array();
		foreach ( $candidates as $address ) {
			$address = trim( (string) $address );
			if ( '' === $address ) {
				continue;
			}
			if ( ! is_email( $address ) ) {
				return $this->error(
					'invalid_bcc',
					sprintf(
						/* translators: %s: Invalid email address */
						__( 'Invalid Bcc email address: %s', 'mail-system' ),
						$address
					)
				);
			}
			$clean[] = $address;
		}

		return array( 'bcc' => implode( ', ', $clean ) );
	}

	/**
	 * Resolve, validate and deduplicate recipients from the selected lists.
	 *
	 * Protected to provide a seam for unit tests that need to bypass the list provider.
	 *
	 * @param array $list_ids List identifiers.
	 * @return array Either { recipients: array } or an error result.
	 */
	protected function resolve_recipients( array $list_ids ): array {
		$recipients  = array();
		$seen_emails = array();

		foreach ( $list_ids as $list_id ) {
			$list_id = is_string( $list_id ) ? trim( $list_id ) : $list_id;

			if ( ! \MSKD_List_Provider::list_exists( $list_id ) ) {
				return $this->error(
					'unknown_list',
					sprintf(
						/* translators: %s: List identifier */
						__( 'Unknown list: %s', 'mail-system' ),
						(string) $list_id
					)
				);
			}

			$list_subscribers = \MSKD_List_Provider::get_list_subscribers_full( $list_id );
			foreach ( $list_subscribers as $subscriber ) {
				$email = isset( $subscriber->email ) ? strtolower( trim( (string) $subscriber->email ) ) : '';
				if ( '' === $email || isset( $seen_emails[ $email ] ) ) {
					continue;
				}
				$seen_emails[ $email ] = true;
				$recipients[]          = $subscriber;
			}
		}

		return array( 'recipients' => $recipients );
	}

	/**
	 * Build a failure result.
	 *
	 * @param string $code    Machine-readable error code.
	 * @param string $message Human-readable error message.
	 * @return array
	 */
	private function error( string $code, string $message ): array {
		return array(
			'success'          => false,
			'campaign_id'      => null,
			'queued'           => 0,
			'total_recipients' => 0,
			'scheduled_at'     => '',
			'is_immediate'     => false,
			'error_code'       => $code,
			'error_message'    => $message,
		);
	}
}
