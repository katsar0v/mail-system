<?php
/**
 * API Token Service
 *
 * Issues, stores, authenticates and revokes JWT bearer tokens for the REST API.
 * Only a hash of each token's random identifier (`jti`) is persisted, so the raw
 * token is shown once at creation and can never be recovered. Deleting the stored
 * record immediately and irreversibly revokes the token.
 *
 * @package MSKD\Api
 * @since   1.9.0
 */

namespace MSKD\Api;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Token_Service
 */
class Token_Service {

	/**
	 * Audience claim value. Tokens are only accepted for this audience.
	 */
	const AUDIENCE = 'mail-system-rest';

	/**
	 * Clock-skew leeway, in seconds, applied to time-based claims.
	 */
	const LEEWAY = 60;

	/**
	 * Read scope: list and campaign-status routes.
	 */
	const SCOPE_READ = 'campaigns:read';

	/**
	 * Write scope: create and cancel routes.
	 */
	const SCOPE_WRITE = 'campaigns:write';

	/**
	 * WordPress database instance.
	 *
	 * @var \wpdb
	 */
	private $wpdb;

	/**
	 * Tokens table name.
	 *
	 * @var string
	 */
	private $table;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb  = $wpdb;
		$this->table = $wpdb->prefix . 'mskd_api_tokens';
	}

	/**
	 * All scopes the API recognizes.
	 *
	 * @return string[]
	 */
	public static function available_scopes(): array {
		return array( self::SCOPE_READ, self::SCOPE_WRITE );
	}

	/**
	 * Selectable token lifetimes, in days. `0` represents no expiry.
	 *
	 * @return int[]
	 */
	public static function ttl_choices(): array {
		return array( 30, 90, 365, 0 );
	}

	/**
	 * Derive the HMAC signing secret from WordPress salts.
	 *
	 * Because the secret is bound to AUTH_KEY/SECURE_AUTH_KEY, rotating the site
	 * salts invalidates every previously issued token.
	 *
	 * @return string Raw binary secret.
	 */
	public static function secret(): string {
		$material = '';
		if ( defined( 'AUTH_KEY' ) ) {
			$material .= AUTH_KEY;
		}
		if ( defined( 'SECURE_AUTH_KEY' ) ) {
			$material .= SECURE_AUTH_KEY;
		}
		if ( '' === $material && function_exists( 'wp_salt' ) ) {
			$material = wp_salt( 'auth' );
		}

		return hash_hmac( 'sha256', 'mskd-api-jwt-v1', $material, true );
	}

	/**
	 * The issuer claim value for this site.
	 *
	 * @return string
	 */
	public function issuer(): string {
		return home_url();
	}

	/**
	 * Create and persist a new token.
	 *
	 * @param string   $name     Human-readable token name.
	 * @param string[] $scopes   Requested scopes.
	 * @param int|null $ttl_days Lifetime in days, `0`/null for no expiry.
	 * @param int      $user_id  Creating administrator's user ID.
	 * @return array|\WP_Error { jwt, id, name, scopes, expires_at } on success.
	 */
	public function create_token( string $name, array $scopes, ?int $ttl_days, int $user_id ) {
		$name = trim( $name );
		if ( '' === $name ) {
			return new \WP_Error( 'invalid_name', __( 'A token name is required.', 'mail-system' ) );
		}

		$scopes = $this->sanitize_scopes( $scopes );
		if ( empty( $scopes ) ) {
			return new \WP_Error( 'invalid_scopes', __( 'At least one valid scope is required.', 'mail-system' ) );
		}

		$now        = time();
		$expires_ts = null;
		if ( null !== $ttl_days && (int) $ttl_days > 0 ) {
			$expires_ts = $now + ( (int) $ttl_days * DAY_IN_SECONDS );
		}

		$jti      = wp_generate_password( 43, false, false );
		$jti_hash = hash( 'sha256', $jti );

		$payload = array(
			'iss'    => $this->issuer(),
			'aud'    => self::AUDIENCE,
			'sub'    => (string) $user_id,
			'jti'    => $jti,
			'iat'    => $now,
			'nbf'    => $now,
			'scopes' => $scopes,
		);
		if ( $expires_ts ) {
			$payload['exp'] = $expires_ts;
		}

		$jwt        = Jwt_Codec::encode( $payload, self::secret() );
		$expires_at = $expires_ts ? mskd_local_time_from_timestamp( $expires_ts ) : null;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table write.
		$inserted = $this->wpdb->insert(
			$this->table,
			array(
				'name'       => $name,
				'jti_hash'   => $jti_hash,
				'user_id'    => $user_id,
				'scopes'     => implode( ',', $scopes ),
				'expires_at' => $expires_at,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%d', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return new \WP_Error( 'db_error', __( 'The token could not be saved.', 'mail-system' ) );
		}

		return array(
			'jwt'        => $jwt,
			'id'         => (int) $this->wpdb->insert_id,
			'name'       => $name,
			'scopes'     => $scopes,
			'expires_at' => $expires_at,
		);
	}

	/**
	 * Authenticate a raw bearer token.
	 *
	 * @param string $jwt The bearer token from the Authorization header.
	 * @return array|\WP_Error { user_id, token_id, scopes } on success.
	 */
	public function authenticate( string $jwt ) {
		$jwt = trim( $jwt );
		if ( '' === $jwt ) {
			return new \WP_Error( 'missing_token', __( 'Authentication token is missing.', 'mail-system' ), array( 'status' => 401 ) );
		}

		try {
			$claims = Jwt_Codec::decode( $jwt, self::secret() );
		} catch ( \Exception $e ) {
			return new \WP_Error( 'invalid_token', __( 'The API token is invalid.', 'mail-system' ), array( 'status' => 401 ) );
		}

		// Issuer and audience must match this site exactly.
		if ( (string) ( $claims['iss'] ?? '' ) !== $this->issuer() || self::AUDIENCE !== (string) ( $claims['aud'] ?? '' ) ) {
			return new \WP_Error( 'invalid_token', __( 'The API token is invalid.', 'mail-system' ), array( 'status' => 401 ) );
		}

		$now = time();

		// Not-before and expiry (with a small leeway for clock skew).
		if ( isset( $claims['nbf'] ) && ( $now + self::LEEWAY ) < (int) $claims['nbf'] ) {
			return new \WP_Error( 'invalid_token', __( 'The API token is not yet valid.', 'mail-system' ), array( 'status' => 401 ) );
		}
		if ( isset( $claims['exp'] ) && ( $now - self::LEEWAY ) >= (int) $claims['exp'] ) {
			return new \WP_Error( 'expired_token', __( 'The API token has expired.', 'mail-system' ), array( 'status' => 401 ) );
		}

		$jti = (string) ( $claims['jti'] ?? '' );
		if ( '' === $jti ) {
			return new \WP_Error( 'invalid_token', __( 'The API token is invalid.', 'mail-system' ), array( 'status' => 401 ) );
		}

		// The stored record is the revocation source of truth: no record means revoked.
		$record = $this->get_by_jti_hash( hash( 'sha256', $jti ) );
		if ( ! $record ) {
			return new \WP_Error( 'revoked_token', __( 'The API token has been revoked.', 'mail-system' ), array( 'status' => 401 ) );
		}

		// Enforce the persisted expiry too, independently of the token's own claim.
		if ( ! empty( $record->expires_at ) ) {
			try {
				// Database datetimes are stored in the WordPress site timezone, not UTC.
				$record_expiry = new \DateTime( (string) $record->expires_at, wp_timezone() );
				if ( $record_expiry->getTimestamp() <= ( $now - self::LEEWAY ) ) {
					return new \WP_Error( 'expired_token', __( 'The API token has expired.', 'mail-system' ), array( 'status' => 401 ) );
				}
			} catch ( \Exception $e ) {
				return new \WP_Error( 'invalid_token', __( 'The API token is invalid.', 'mail-system' ), array( 'status' => 401 ) );
			}
		}

		// The creator must still be an administrator; losing the capability disables the token.
		if ( ! user_can( (int) $record->user_id, 'manage_options' ) ) {
			return new \WP_Error( 'forbidden_token', __( 'The token owner is no longer allowed to use the API.', 'mail-system' ), array( 'status' => 403 ) );
		}

		$this->touch( (int) $record->id );

		return array(
			'user_id'  => (int) $record->user_id,
			'token_id' => (int) $record->id,
			'scopes'   => $this->parse_scopes( $record->scopes ),
		);
	}

	/**
	 * List all stored tokens (metadata only; secrets are never stored).
	 *
	 * @return array<int,object>
	 */
	public function list_tokens(): array {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom admin table read; table name is hardcoded.
		$rows = $this->wpdb->get_results( "SELECT id, name, user_id, scopes, expires_at, created_at, last_used_at FROM {$this->table} ORDER BY created_at DESC" );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Delete (revoke) a token by its database ID.
	 *
	 * @param int $id Token record ID.
	 * @return bool
	 */
	public function delete_token( int $id ): bool {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table write.
		$deleted = $this->wpdb->delete( $this->table, array( 'id' => $id ), array( '%d' ) );
		return (bool) $deleted;
	}

	/**
	 * Look up a token record by the hash of its `jti`.
	 *
	 * @param string $jti_hash SHA-256 hash of the token identifier.
	 * @return object|null
	 */
	private function get_by_jti_hash( string $jti_hash ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $this->wpdb->get_row(
			$this->wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is hardcoded and safe.
				"SELECT * FROM {$this->table} WHERE jti_hash = %s",
				$jti_hash
			)
		);
	}

	/**
	 * Record the last-used timestamp for a token (best-effort).
	 *
	 * @param int $id Token record ID.
	 * @return void
	 */
	private function touch( int $id ): void {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table write.
		$this->wpdb->update(
			$this->table,
			array( 'last_used_at' => current_time( 'mysql' ) ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Keep only recognized scopes.
	 *
	 * @param string[] $scopes Requested scopes.
	 * @return string[]
	 */
	private function sanitize_scopes( array $scopes ): array {
		$allowed = self::available_scopes();
		$clean   = array();
		foreach ( $scopes as $scope ) {
			$scope = trim( (string) $scope );
			if ( in_array( $scope, $allowed, true ) && ! in_array( $scope, $clean, true ) ) {
				$clean[] = $scope;
			}
		}
		return $clean;
	}

	/**
	 * Parse a stored comma-separated scope string.
	 *
	 * @param string $scopes Stored scopes.
	 * @return string[]
	 */
	private function parse_scopes( string $scopes ): array {
		if ( '' === trim( $scopes ) ) {
			return array();
		}
		return $this->sanitize_scopes( explode( ',', $scopes ) );
	}
}
