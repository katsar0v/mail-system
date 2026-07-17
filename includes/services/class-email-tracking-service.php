<?php
/**
 * Email Tracking Service
 *
 * Adds recipient-specific tracking pixels and records email opens.
 *
 * @package MSKD\Services
 * @since   1.2.0
 */

namespace MSKD\Services;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Email_Tracking_Service
 */
class Email_Tracking_Service {

	/**
	 * WordPress database instance.
	 *
	 * @var \wpdb
	 */
	private $wpdb;

	/**
	 * Queue table name.
	 *
	 * @var string
	 */
	private $queue_table;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;

		$this->wpdb        = $wpdb;
		$this->queue_table = $wpdb->prefix . 'mskd_queue';
	}

	/**
	 * Generate an unpredictable, URL-safe tracking token.
	 *
	 * @return string 64-character hexadecimal token.
	 */
	public function generate_token(): string {
		return bin2hex( random_bytes( 32 ) );
	}

	/**
	 * Append a tracking pixel before the closing body tag when possible.
	 *
	 * @param string $body  Rendered email body.
	 * @param string $token Recipient tracking token.
	 * @return string Email body with tracking pixel.
	 */
	public function append_tracking_pixel( string $body, string $token ): string {
		if ( ! $this->is_valid_token( $token ) || false !== strpos( $body, $token ) ) {
			return $body;
		}

		$tracking_url = add_query_arg(
			array( 'mskd_track_open' => $token ),
			home_url( '/' )
		);
		$pixel        = sprintf(
			'<img src="%s" width="1" height="1" alt="" style="display:block;width:1px;height:1px;border:0;overflow:hidden;" />',
			esc_url( $tracking_url )
		);

		if ( preg_match( '/<\/body\s*>/i', $body ) ) {
			return (string) preg_replace( '/<\/body\s*>/i', $pixel . '</body>', $body, 1 );
		}

		return $body . $pixel;
	}

	/**
	 * Record an open for a sent or currently sending queue item.
	 *
	 * The first-open timestamp is immutable while every image request increments
	 * the load count. No IP address or user-agent data is retained.
	 *
	 * @param string $token Recipient tracking token.
	 * @return bool True when a matching queue row was updated.
	 */
	public function record_open( string $token ): bool {
		if ( ! $this->is_valid_token( $token ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Analytics event must be written immediately.
		$result = $this->wpdb->query(
			$this->wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is constructed from the trusted WordPress prefix.
				"UPDATE {$this->queue_table}
				SET opened_at = COALESCE(opened_at, %s), open_count = open_count + 1
				WHERE tracking_token = %s AND status IN ('processing', 'sent')",
				current_time( 'mysql' ),
				$token
			)
		);

		return 0 < (int) $result;
	}

	/**
	 * Validate a tracking token without querying the database.
	 *
	 * @param string $token Tracking token.
	 * @return bool
	 */
	public function is_valid_token( string $token ): bool {
		return 1 === preg_match( '/^[a-f0-9]{64}$/', $token );
	}
}
