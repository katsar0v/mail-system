<?php
/**
 * REST API Controller
 *
 * Registers the `mail-system/v1` REST routes and wires JWT bearer authentication,
 * scope enforcement and idempotency onto the shared campaign application services.
 * Controllers here are thin request/response adapters: all business rules live in
 * the MSKD\Application services.
 *
 * @package MSKD\Api
 * @since   1.9.0
 */

namespace MSKD\Api;

use MSKD\Application\Campaign_Service;
use MSKD\Application\Campaign_Query_Service;
use MSKD\Services\Email_Service;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Rest_Controller
 */
class Rest_Controller {

	/**
	 * REST namespace.
	 */
	const NAMESPACE = 'mail-system/v1';

	/**
	 * Idempotency record lifetime, in seconds.
	 */
	const IDEMPOTENCY_TTL = DAY_IN_SECONDS;

	/**
	 * Outcomes of looking up an Idempotency-Key in the replay cache.
	 */
	const IDEMPOTENCY_MISS     = 'miss';
	const IDEMPOTENCY_REPLAY   = 'replay';
	const IDEMPOTENCY_CONFLICT = 'conflict';

	/**
	 * Maximum time an abandoned idempotency lock may block retries.
	 */
	const IDEMPOTENCY_LOCK_TTL = HOUR_IN_SECONDS;

	/**
	 * Token service.
	 *
	 * @var Token_Service
	 */
	private $token_service;

	/**
	 * Campaign query (read) service.
	 *
	 * @var Campaign_Query_Service
	 */
	private $query_service;

	/**
	 * Authentication context for the current request.
	 *
	 * @var array|null
	 */
	private $current_auth = null;

	/**
	 * Constructor.
	 *
	 * @param Token_Service|null          $token_service Token service (injected for testing).
	 * @param Campaign_Query_Service|null $query_service Query service (injected for testing).
	 */
	public function __construct( ?Token_Service $token_service = null, ?Campaign_Query_Service $query_service = null ) {
		$this->token_service = $token_service ?? new Token_Service();
		$this->query_service = $query_service ?? new Campaign_Query_Service();
	}

	/**
	 * Register all REST routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/lists',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_lists' ),
				'permission_callback' => function ( $request ) {
					return $this->authorize( $request, Token_Service::SCOPE_READ );
				},
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/campaigns',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create_campaign' ),
				'permission_callback' => function ( $request ) {
					return $this->authorize( $request, Token_Service::SCOPE_WRITE );
				},
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/campaigns/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_campaign' ),
				'permission_callback' => function ( $request ) {
					return $this->authorize( $request, Token_Service::SCOPE_READ );
				},
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/campaigns/(?P<id>\d+)/cancel',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'cancel_campaign' ),
				'permission_callback' => function ( $request ) {
					return $this->authorize( $request, Token_Service::SCOPE_WRITE );
				},
			)
		);
	}

	/**
	 * Permission callback: authenticate the bearer token and enforce a scope.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @param string           $scope   Required scope.
	 * @return true|\WP_Error
	 */
	public function authorize( $request, string $scope ) {
		$auth = $this->token_service->authenticate( $this->extract_bearer_token( $request ) );

		if ( is_wp_error( $auth ) ) {
			return $auth;
		}

		if ( ! in_array( $scope, $auth['scopes'], true ) ) {
			return new \WP_Error(
				'insufficient_scope',
				__( 'This token does not have the required scope.', 'mail-system' ),
				array( 'status' => 403 )
			);
		}

		$this->current_auth = $auth;
		return true;
	}

	/**
	 * GET /lists.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public function get_lists( $request ) {
		unset( $request );
		return new \WP_REST_Response( array( 'lists' => $this->query_service->get_available_lists() ), 200 );
	}

	/**
	 * POST /campaigns.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_campaign( $request ) {
		$idempotency_key = trim( (string) $request->get_header( 'idempotency-key' ) );
		if ( '' === $idempotency_key ) {
			return new \WP_Error( 'missing_idempotency_key', __( 'An Idempotency-Key header is required.', 'mail-system' ), array( 'status' => 400 ) );
		}

		$user_id  = isset( $this->current_auth['user_id'] ) ? (int) $this->current_auth['user_id'] : 0;
		$idem_key = 'mskd_idem_' . hash( 'sha256', $user_id . '|' . $idempotency_key );

		$params      = is_array( $request->get_json_params() ) ? $request->get_json_params() : array();
		$fingerprint = self::payload_fingerprint( $params );

		$replay = $this->idempotency_result( get_transient( $idem_key ), $fingerprint );
		if ( null !== $replay ) {
			// Replay the original outcome (or reject a key reused with another payload)
			// without creating a duplicate campaign.
			return $replay;
		}

		// Transient reads and writes are not atomic. Guard the critical section with a
		// database-level mutex so two concurrent retries with the same key cannot both
		// create a campaign.
		$lock_key = $idem_key . '_lock';
		if ( ! $this->acquire_lock( $lock_key ) ) {
			$replay = $this->idempotency_result( get_transient( $idem_key ), $fingerprint );
			if ( null !== $replay ) {
				return $replay;
			}

			return new \WP_Error(
				'idempotency_in_progress',
				__( 'Another request with this Idempotency-Key is already being processed.', 'mail-system' ),
				array( 'status' => 409 )
			);
		}

		try {
			// Reject wrongly typed fields instead of coercing them (e.g. an array subject to "Array").
			$param_error = self::validate_campaign_params( $params );
			if ( null !== $param_error ) {
				return $param_error;
			}

			// Resolve scheduling from an optional ISO-8601 timestamp.
			$schedule = self::parse_scheduled_at( $params['scheduled_at'] ?? '' );
			if ( isset( $schedule['error'] ) ) {
				return new \WP_Error( $schedule['error'], $schedule['message'], array( 'status' => 400 ) );
			}

			$list_ids = isset( $params['list_ids'] ) ? (array) $params['list_ids'] : array();
			$bcc      = $params['bcc'] ?? '';

			$campaign_service = new Campaign_Service();
			$result           = $campaign_service->schedule(
				array(
					'subject'      => isset( $params['subject'] ) ? sanitize_text_field( (string) $params['subject'] ) : '',
					// REST content is untrusted: constrain it to the email-safe HTML allowlist.
					'body'         => isset( $params['body'] ) ? mskd_kses_email( (string) $params['body'] ) : '',
					'list_ids'     => array_map( 'sanitize_text_field', $list_ids ),
					'bcc'          => $bcc,
					// Not sanitized here: sanitize_email() would turn an invalid address into '' and the
					// sender would silently fall back to the default. Campaign_Service validates it.
					'from_email'   => isset( $params['from_email'] ) ? trim( $params['from_email'] ) : '',
					'from_name'    => isset( $params['from_name'] ) ? sanitize_text_field( (string) $params['from_name'] ) : '',
					'scheduled_at' => $schedule['scheduled_at'],
					'is_immediate' => $schedule['is_immediate'],
				)
			);

			if ( ! $result['success'] ) {
				return new \WP_Error(
					$result['error_code'],
					$result['error_message'],
					array( 'status' => self::status_for_error( $result['error_code'] ) )
				);
			}

			$response_body = array(
				'campaign_id'      => $result['campaign_id'],
				'status'           => $result['is_immediate'] ? 'queued' : 'scheduled',
				'scheduled_at'     => $result['scheduled_at'],
				'is_immediate'     => $result['is_immediate'],
				'queued'           => $result['queued'],
				'total_recipients' => $result['total_recipients'],
			);

			set_transient(
				$idem_key,
				array(
					'fingerprint' => $fingerprint,
					'response'    => $response_body,
				),
				self::IDEMPOTENCY_TTL
			);

			return new \WP_REST_Response( $response_body, 201 );
		} finally {
			delete_option( $lock_key );
		}
	}

	/**
	 * Validate the JSON types of the POST /campaigns fields.
	 *
	 * Text fields must be strings, and `list_ids` must be an array (or a single value) of
	 * strings/integers. Anything else is rejected with a 400 rather than cast.
	 *
	 * @param array $params Decoded JSON body.
	 * @return \WP_Error|null Error naming the first offending field, or null when valid.
	 */
	public static function validate_campaign_params( array $params ): ?\WP_Error {
		foreach ( array( 'subject', 'body', 'from_email', 'from_name' ) as $field ) {
			if ( isset( $params[ $field ] ) && ! is_string( $params[ $field ] ) ) {
				return new \WP_Error(
					'invalid_param',
					sprintf(
						/* translators: %s: Request field name */
						__( 'The "%s" field must be a string.', 'mail-system' ),
						$field
					),
					array( 'status' => 400 )
				);
			}
		}

		if ( isset( $params['list_ids'] ) ) {
			$list_ids = is_array( $params['list_ids'] ) ? $params['list_ids'] : array( $params['list_ids'] );
			foreach ( $list_ids as $list_id ) {
				if ( ! is_string( $list_id ) && ! is_int( $list_id ) ) {
					return new \WP_Error(
						'invalid_param',
						__( 'The "list_ids" field must be an array of list identifiers (strings or integers).', 'mail-system' ),
						array( 'status' => 400 )
					);
				}
			}
		}

		return null;
	}

	/**
	 * Fingerprint a request payload so an Idempotency-Key can be tied to what it created.
	 *
	 * Object key order does not matter; values and list order do.
	 *
	 * @param array $params Decoded JSON body.
	 * @return string SHA-256 hex digest.
	 */
	public static function payload_fingerprint( array $params ): string {
		$json = wp_json_encode( self::ksort_recursive( $params ) );

		return hash( 'sha256', false === $json ? '' : $json );
	}

	/**
	 * Sort an array by key at every depth so equal JSON objects encode identically.
	 *
	 * @param array $data Array to normalize.
	 * @return array
	 */
	private static function ksort_recursive( array $data ): array {
		ksort( $data );
		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) ) {
				$data[ $key ] = self::ksort_recursive( $value );
			}
		}

		return $data;
	}

	/**
	 * Decide what a cached Idempotency-Key entry means for the current request.
	 *
	 * @param mixed  $stored      Value read from the replay cache (false when absent).
	 * @param string $fingerprint Fingerprint of the current request payload.
	 * @return array { outcome: miss|replay|conflict, response: array|null }
	 */
	public static function classify_idempotency_entry( $stored, string $fingerprint ): array {
		if ( ! is_array( $stored ) ) {
			return array(
				'outcome'  => self::IDEMPOTENCY_MISS,
				'response' => null,
			);
		}

		// Entries cached before payloads were fingerprinted hold the bare response body.
		if ( ! isset( $stored['fingerprint'], $stored['response'] ) || ! is_array( $stored['response'] ) ) {
			return array(
				'outcome'  => self::IDEMPOTENCY_REPLAY,
				'response' => $stored,
			);
		}

		return array(
			'outcome'  => hash_equals( (string) $stored['fingerprint'], $fingerprint ) ? self::IDEMPOTENCY_REPLAY : self::IDEMPOTENCY_CONFLICT,
			'response' => $stored['response'],
		);
	}

	/**
	 * Turn a cached Idempotency-Key entry into a response, if it applies.
	 *
	 * @param mixed  $stored      Value read from the replay cache.
	 * @param string $fingerprint Fingerprint of the current request payload.
	 * @return \WP_REST_Response|\WP_Error|null Replay, 422 conflict, or null when nothing is cached.
	 */
	private function idempotency_result( $stored, string $fingerprint ) {
		$entry = self::classify_idempotency_entry( $stored, $fingerprint );

		if ( self::IDEMPOTENCY_REPLAY === $entry['outcome'] ) {
			return new \WP_REST_Response( $entry['response'], 200 );
		}

		if ( self::IDEMPOTENCY_CONFLICT === $entry['outcome'] ) {
			return new \WP_Error(
				'idempotency_key_reused',
				__( 'This Idempotency-Key was already used with a different request payload.', 'mail-system' ),
				array( 'status' => 422 )
			);
		}

		return null;
	}

	/**
	 * Acquire a short-lived, database-level mutex.
	 *
	 * Uses `INSERT IGNORE` against the options table: the UNIQUE `option_name` index
	 * makes acquisition atomic, so exactly one concurrent caller inserts the row and
	 * wins. A lock older than IDEMPOTENCY_LOCK_TTL is treated as abandoned (left behind
	 * by a crashed request) and reclaimed. Mirrors WP_Upgrader::create_lock().
	 *
	 * @param string $lock_key Option name to use as the lock.
	 * @return bool True when the lock was acquired.
	 */
	private function acquire_lock( string $lock_key ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Atomic mutex acquisition; $wpdb->options is safe and caching would defeat the lock.
		$acquired = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO `$wpdb->options` ( `option_name`, `option_value`, `autoload` ) VALUES (%s, %s, 'no')", $lock_key, (string) time() ) );

		if ( $acquired ) {
			return true;
		}

		// The row already exists. Reclaim it only if the previous holder crashed and
		// left it behind past the TTL.
		$held_since = (int) get_option( $lock_key, 0 );
		if ( $held_since > 0 && ( time() - $held_since ) <= self::IDEMPOTENCY_LOCK_TTL ) {
			return false;
		}

		delete_option( $lock_key );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Atomic mutex acquisition; $wpdb->options is safe and caching would defeat the lock.
		$acquired = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO `$wpdb->options` ( `option_name`, `option_value`, `autoload` ) VALUES (%s, %s, 'no')", $lock_key, (string) time() ) );

		return (bool) $acquired;
	}

	/**
	 * GET /campaigns/{id}.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_campaign( $request ) {
		$id     = (int) $request['id'];
		$status = $this->query_service->get_campaign_status( $id );

		if ( null === $status ) {
			return new \WP_Error( 'not_found', __( 'Campaign not found.', 'mail-system' ), array( 'status' => 404 ) );
		}

		return new \WP_REST_Response( $status, 200 );
	}

	/**
	 * POST /campaigns/{id}/cancel.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function cancel_campaign( $request ) {
		$id            = (int) $request['id'];
		$email_service = new Email_Service();

		if ( null === $email_service->get_campaign( $id ) ) {
			return new \WP_Error( 'not_found', __( 'Campaign not found.', 'mail-system' ), array( 'status' => 404 ) );
		}

		$cancelled = $email_service->cancel_campaign( $id );

		if ( false === $cancelled ) {
			return new \WP_Error( 'not_cancellable', __( 'This campaign can no longer be cancelled.', 'mail-system' ), array( 'status' => 409 ) );
		}

		$campaign = $email_service->get_campaign( $id );

		return new \WP_REST_Response(
			array(
				'campaign_id' => $id,
				'status'      => $campaign ? $campaign->status : 'cancelled',
				'cancelled'   => (int) $cancelled,
			),
			200
		);
	}

	/**
	 * Extract the raw bearer token from the request Authorization header.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return string
	 */
	private function extract_bearer_token( $request ): string {
		$header = (string) $request->get_header( 'authorization' );
		if ( '' === $header ) {
			// Some servers expose the header only under this alias.
			$header = (string) $request->get_header( 'x-authorization' );
		}

		if ( 0 === stripos( $header, 'bearer ' ) ) {
			return trim( substr( $header, 7 ) );
		}

		return '';
	}

	/**
	 * Parse an optional ISO-8601 scheduled_at value into a normalized MySQL datetime.
	 *
	 * An empty value means "send immediately". Malformed or past timestamps are
	 * rejected rather than silently coerced to an immediate send.
	 *
	 * @param mixed $raw Raw scheduled_at value.
	 * @return array { scheduled_at, is_immediate } or { error, message }.
	 */
	public static function parse_scheduled_at( $raw ): array {
		$raw = is_string( $raw ) ? trim( $raw ) : '';

		if ( '' === $raw ) {
			return array(
				'scheduled_at' => '',
				'is_immediate' => true,
			);
		}

		// Require an ISO-8601-style date prefix (YYYY-MM-DD...) so relative expressions
		// DateTime would otherwise accept ("tomorrow", "+1 day", "now") are rejected.
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}/', $raw ) ) {
			return array(
				'error'   => 'invalid_schedule',
				'message' => __( 'The scheduled_at value is not a valid ISO-8601 timestamp.', 'mail-system' ),
			);
		}

		try {
			// A timezone in the string wins; otherwise the value is read as site-local.
			$date = new \DateTime( $raw, wp_timezone() );
		} catch ( \Exception $e ) {
			return array(
				'error'   => 'invalid_schedule',
				'message' => __( 'The scheduled_at value is not a valid ISO-8601 timestamp.', 'mail-system' ),
			);
		}

		$date->setTimezone( wp_timezone() );
		$date->setTime( (int) $date->format( 'H' ), (int) $date->format( 'i' ), 0 );

		if ( $date->getTimestamp() < ( time() - Token_Service::LEEWAY ) ) {
			return array(
				'error'   => 'past_schedule',
				'message' => __( 'The scheduled_at value is in the past.', 'mail-system' ),
			);
		}

		return array(
			'scheduled_at' => $date->format( 'Y-m-d H:i:s' ),
			'is_immediate' => false,
		);
	}

	/**
	 * Map an application-layer error code to an HTTP status code.
	 *
	 * @param string|null $error_code Error code from Campaign_Service.
	 * @return int
	 */
	public static function status_for_error( ?string $error_code ): int {
		switch ( $error_code ) {
			case 'db_error':
				return 500;
			case 'missing_subject':
			case 'missing_body':
			case 'missing_lists':
			case 'invalid_sender':
			case 'invalid_bcc':
			case 'unknown_list':
			case 'no_recipients':
			default:
				return 400;
		}
	}
}
