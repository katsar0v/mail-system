<?php
/**
 * JWT Codec
 *
 * Minimal, dependency-free JSON Web Token encoder/decoder scoped to the single
 * algorithm this plugin uses (HS256). Keeping the surface tiny avoids shipping a
 * Composer dependency into production and removes the algorithm-confusion and
 * `alg:none` foot-guns that general-purpose libraries expose.
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
 * Class Jwt_Codec
 */
class Jwt_Codec {

	/**
	 * The only supported signing algorithm.
	 */
	const ALGORITHM = 'HS256';

	/**
	 * Encode a payload into a signed compact JWT.
	 *
	 * @param array  $payload Claims to encode.
	 * @param string $key     Raw HMAC secret.
	 * @return string Signed JWT (header.payload.signature).
	 */
	public static function encode( array $payload, string $key ): string {
		$header = array(
			'typ' => 'JWT',
			'alg' => self::ALGORITHM,
		);

		$segments = array(
			self::base64url_encode( (string) wp_json_encode( $header ) ),
			self::base64url_encode( (string) wp_json_encode( $payload ) ),
		);

		$signing_input = implode( '.', $segments );
		$signature     = hash_hmac( 'sha256', $signing_input, $key, true );
		$segments[]    = self::base64url_encode( $signature );

		return implode( '.', $segments );
	}

	/**
	 * Decode and verify a compact JWT.
	 *
	 * Verifies structure, that the header algorithm is exactly HS256, and the HMAC
	 * signature. Temporal and claim validation are the caller's responsibility.
	 *
	 * @param string $jwt The compact JWT string.
	 * @param string $key Raw HMAC secret.
	 * @return array Decoded claims.
	 * @throws \InvalidArgumentException When the token is structurally invalid or uses a disallowed algorithm.
	 * @throws \RuntimeException When the signature does not verify.
	 */
	public static function decode( string $jwt, string $key ): array {
		$parts = explode( '.', $jwt );

		if ( 3 !== count( $parts ) ) {
			throw new \InvalidArgumentException( 'Malformed token.' );
		}

		list( $encoded_header, $encoded_payload, $encoded_signature ) = $parts;

		$header_json = self::base64url_decode( $encoded_header );
		$header      = json_decode( $header_json, true );

		if ( ! is_array( $header ) || ! isset( $header['alg'] ) ) {
			throw new \InvalidArgumentException( 'Invalid token header.' );
		}

		// Reject anything other than HS256 up front. This closes both the `alg:none`
		// bypass and asymmetric-to-symmetric algorithm-confusion attacks.
		if ( ! hash_equals( self::ALGORITHM, (string) $header['alg'] ) ) {
			throw new \InvalidArgumentException( 'Unsupported token algorithm.' );
		}

		$signing_input      = $encoded_header . '.' . $encoded_payload;
		$expected_signature = self::base64url_encode( hash_hmac( 'sha256', $signing_input, $key, true ) );

		if ( ! hash_equals( $expected_signature, $encoded_signature ) ) {
			throw new \RuntimeException( 'Signature verification failed.' );
		}

		$payload = json_decode( self::base64url_decode( $encoded_payload ), true );

		if ( ! is_array( $payload ) ) {
			throw new \InvalidArgumentException( 'Invalid token payload.' );
		}

		return $payload;
	}

	/**
	 * URL-safe base64 encode without padding.
	 *
	 * @param string $data Raw data.
	 * @return string
	 */
	private static function base64url_encode( string $data ): string {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Required for JWT wire format.
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}

	/**
	 * URL-safe base64 decode.
	 *
	 * @param string $data Encoded data.
	 * @return string
	 */
	private static function base64url_decode( string $data ): string {
		$remainder = strlen( $data ) % 4;
		if ( $remainder ) {
			$data .= str_repeat( '=', 4 - $remainder );
		}
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Required for JWT wire format.
		return (string) base64_decode( strtr( $data, '-_', '+/' ) );
	}
}
