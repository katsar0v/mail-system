<?php
/**
 * Email Tracking Service
 *
 * Adds recipient-specific tracking pixels and links, and records engagement.
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
	 * Click analytics table name.
	 *
	 * @var string
	 */
	private $clicks_table;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;

		$this->wpdb         = $wpdb;
		$this->queue_table  = $wpdb->prefix . 'mskd_queue';
		$this->clicks_table = $wpdb->prefix . 'mskd_clicks';
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
	 * Rewrite eligible anchor destinations to recipient-specific tracking URLs.
	 *
	 * The callback changes only the href value, preserving the rest of the email
	 * markup. Link indexes are assigned by eligible-link position so the same
	 * template can be aggregated consistently across recipients.
	 *
	 * @param string $body        Rendered email body.
	 * @param string $click_token Recipient click token.
	 * @return string Email body with eligible links rewritten.
	 */
	public function rewrite_links( string $body, string $click_token ): string {
		if ( ! $this->is_valid_token( $click_token ) ) {
			return $body;
		}

		$link_index = 0;
		$rewritten  = preg_replace_callback(
			'/<a\b[^>]*>/is',
			function ( array $matches ) use ( $click_token, &$link_index ): string {
				$tag = $matches[0];

				if ( preg_match( '/\sdata-mskd-no-track(?:\s|=|>)/i', $tag ) ) {
					return $tag;
				}

				if ( ! preg_match( '/\bhref\s*=\s*(["\'])(.*?)\1/is', $tag, $href_match, PREG_OFFSET_CAPTURE ) ) {
					return $tag;
				}

				$encoded_destination = $href_match[2][0];
				$destination         = html_entity_decode( $encoded_destination, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

				if ( ! $this->is_trackable_destination( $destination ) ) {
					return $tag;
				}

				++$link_index;
				$tracking_url = $this->create_click_url( $click_token, $link_index, $destination );

				if ( '' === $tracking_url ) {
					return $tag;
				}

				$offset = $href_match[2][1];
				return substr_replace( $tag, esc_url( $tracking_url ), $offset, strlen( $encoded_destination ) );
			},
			$body
		);

		return is_string( $rewritten ) ? $rewritten : $body;
	}

	/**
	 * Build a signed public click URL.
	 *
	 * @param string $click_token Recipient click token.
	 * @param int    $link_index  Stable eligible-link position.
	 * @param string $destination Exact redirect destination.
	 * @return string Tracking URL or an empty string for invalid input.
	 */
	public function create_click_url( string $click_token, int $link_index, string $destination ): string {
		if ( ! $this->is_valid_token( $click_token ) || $link_index < 1 || $link_index > 65535 || ! $this->is_trackable_destination( $destination ) ) {
			return '';
		}

		$encoded_destination = $this->base64url_encode( $destination );
		$signature           = $this->sign_click_payload( $click_token, $link_index, $destination );

		return add_query_arg(
			array(
				'mskd_track_click' => $click_token,
				'link'             => $link_index,
				'url'              => $encoded_destination,
				'sig'              => $signature,
			),
			home_url( '/' )
		);
	}

	/**
	 * Validate a signed click request, optionally record it, and return its target.
	 *
	 * @param string $click_token       Recipient click token.
	 * @param int    $link_index        Stable eligible-link position.
	 * @param string $encoded_url       Base64url-encoded destination.
	 * @param string $signature         HMAC signature.
	 * @param bool   $record            Whether to increment analytics. HEAD requests pass false.
	 * @return string|false Valid redirect destination or false.
	 */
	public function resolve_click( string $click_token, int $link_index, string $encoded_url, string $signature, bool $record = true ) {
		if ( ! $this->is_valid_token( $click_token ) || $link_index < 1 || $link_index > 65535 || ! $this->is_valid_signature( $signature ) ) {
			return false;
		}

		$destination = $this->base64url_decode( $encoded_url );
		if ( false === $destination || ! $this->is_trackable_destination( $destination ) ) {
			return false;
		}

		$expected_signature = $this->sign_click_payload( $click_token, $link_index, $destination );
		if ( ! hash_equals( $expected_signature, strtolower( $signature ) ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Public analytics token lookup must reflect the current queue state.
		$queue_item = $this->wpdb->get_row(
			$this->wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is constructed from the trusted WordPress prefix.
				"SELECT id, campaign_id FROM {$this->queue_table} WHERE click_token = %s AND status IN ('processing', 'sent')",
				$click_token
			)
		);

		if ( ! $queue_item ) {
			return false;
		}

		if ( $record && ! $this->record_click( $queue_item, $link_index, $destination ) ) {
			return false;
		}

		return $destination;
	}

	/**
	 * Atomically insert or update a per-recipient, per-link click aggregate.
	 *
	 * @param object $queue_item Queue row containing id and campaign_id.
	 * @param int    $link_index Stable eligible-link position.
	 * @param string $destination Exact redirect destination.
	 * @return bool True when the aggregate was written.
	 */
	private function record_click( $queue_item, int $link_index, string $destination ): bool {
		$clicked_at  = current_time( 'mysql' );
		$display_url = $this->sanitize_display_url( $destination );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Analytics event must be written immediately and atomically.
		$result = $this->wpdb->query(
			$this->wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is constructed from the trusted WordPress prefix.
				"INSERT INTO {$this->clicks_table}
					(queue_id, campaign_id, link_index, display_url, first_clicked_at, last_clicked_at, click_count)
				VALUES (%d, %d, %d, %s, %s, %s, 1)
				ON DUPLICATE KEY UPDATE
					last_clicked_at = VALUES(last_clicked_at),
					click_count = click_count + 1",
				(int) $queue_item->id,
				(int) $queue_item->campaign_id,
				$link_index,
				$display_url,
				$clicked_at,
				$clicked_at
			)
		);

		if ( false === $result ) {
			return false;
		}

		// A click proves interaction even if the recipient blocked remote images.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Analytics event must be written immediately.
		$this->wpdb->query(
			$this->wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is constructed from the trusted WordPress prefix.
				"UPDATE {$this->queue_table} SET opened_at = COALESCE(opened_at, %s) WHERE id = %d",
				$clicked_at,
				(int) $queue_item->id
			)
		);

		return true;
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

	/**
	 * Determine whether a destination can be tracked and redirected safely.
	 *
	 * @param string $destination Destination URL.
	 * @return bool
	 */
	private function is_trackable_destination( string $destination ): bool {
		if ( '' === $destination || false !== strpos( $destination, "\r" ) || false !== strpos( $destination, "\n" ) ) {
			return false;
		}

		$parts = wp_parse_url( $destination );
		if ( false === $parts || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return false;
		}

		$scheme = strtolower( $parts['scheme'] );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return false;
		}

		$query = array();
		if ( ! empty( $parts['query'] ) ) {
			parse_str( $parts['query'], $query );
		}

		return ! isset( $query['mskd_unsubscribe'] )
			&& ! isset( $query['mskd_confirm'] )
			&& ! isset( $query['mskd_track_open'] )
			&& ! isset( $query['mskd_track_click'] );
	}

	/**
	 * Produce a privacy-safe URL for admin reporting.
	 *
	 * Paths, query strings, fragments, credentials, and personalized values are
	 * omitted. The stable link index distinguishes links on the same origin.
	 *
	 * @param string $destination Exact destination.
	 * @return string Sanitized display URL.
	 */
	private function sanitize_display_url( string $destination ): string {
		$parts = wp_parse_url( $destination );
		if ( false === $parts || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return '';
		}

		$display = strtolower( $parts['scheme'] ) . '://' . strtolower( $parts['host'] );
		if ( isset( $parts['port'] ) ) {
			$display .= ':' . (int) $parts['port'];
		}
		return $display . '/';
	}

	/**
	 * Sign all click-routing fields with the site's nonce salt.
	 *
	 * @param string $click_token Recipient click token.
	 * @param int    $link_index  Stable eligible-link position.
	 * @param string $destination Exact redirect destination.
	 * @return string Lowercase hexadecimal HMAC.
	 */
	private function sign_click_payload( string $click_token, int $link_index, string $destination ): string {
		$payload = $click_token . '|' . $link_index . '|' . $destination;
		return hash_hmac( 'sha256', $payload, wp_salt( 'nonce' ) );
	}

	/**
	 * Validate the signature shape before constant-time comparison.
	 *
	 * @param string $signature Signature value.
	 * @return bool
	 */
	private function is_valid_signature( string $signature ): bool {
		return 1 === preg_match( '/^[a-f0-9]{64}$/i', $signature );
	}

	/**
	 * Encode a value for a URL query parameter without padding.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private function base64url_encode( string $value ): string {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- URL-safe transport encoding for the signed redirect destination, not code obfuscation.
		return rtrim( strtr( base64_encode( $value ), '+/', '-_' ), '=' );
	}

	/**
	 * Decode a strict base64url value.
	 *
	 * @param string $value Encoded value.
	 * @return string|false
	 */
	private function base64url_decode( string $value ) {
		if ( '' === $value || 1 !== preg_match( '/^[A-Za-z0-9_-]+$/', $value ) ) {
			return false;
		}

		$padding = strlen( $value ) % 4;
		if ( $padding ) {
			$value .= str_repeat( '=', 4 - $padding );
		}

		return base64_decode( strtr( $value, '-_', '+/' ), true );
	}
}
