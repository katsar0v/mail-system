<?php
/**
 * JWT Codec Test
 *
 * @package MSKD\Tests\Unit
 */

namespace MSKD\Tests\Unit;

use Brain\Monkey\Functions;
use MSKD\Api\Jwt_Codec;

/**
 * Class Jwt_Codec_Test
 */
class Jwt_Codec_Test extends TestCase {

	/**
	 * Signing key used across tests.
	 *
	 * @var string
	 */
	private $key = 'test-secret-key';

	/**
	 * Set up common encoding stubs.
	 */
	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'wp_json_encode' )->alias(
			function ( $data ) {
				return json_encode( $data );
			}
		);
	}

	/**
	 * A signed token round-trips back to its original claims.
	 */
	public function test_encode_decode_round_trip(): void {
		$payload = array(
			'iss'    => 'https://example.com',
			'sub'    => '1',
			'scopes' => array( 'campaigns:read', 'campaigns:write' ),
		);

		$jwt     = Jwt_Codec::encode( $payload, $this->key );
		$decoded = Jwt_Codec::decode( $jwt, $this->key );

		$this->assertSame( $payload, $decoded );
	}

	/**
	 * A token has three dot-separated segments and a stable HS256 header.
	 */
	public function test_encoded_token_structure(): void {
		$jwt   = Jwt_Codec::encode( array( 'a' => 1 ), $this->key );
		$parts = explode( '.', $jwt );

		$this->assertCount( 3, $parts );

		$header = json_decode( self::base64url_decode( $parts[0] ), true );
		$this->assertSame( 'HS256', $header['alg'] );
		$this->assertSame( 'JWT', $header['typ'] );
	}

	/**
	 * A token signed with a different key fails verification.
	 */
	public function test_decode_rejects_wrong_signature(): void {
		$jwt = Jwt_Codec::encode( array( 'a' => 1 ), $this->key );

		$this->expectException( \RuntimeException::class );
		Jwt_Codec::decode( $jwt, 'a-different-key' );
	}

	/**
	 * A tampered payload invalidates the signature.
	 */
	public function test_decode_rejects_tampered_payload(): void {
		$jwt   = Jwt_Codec::encode( array( 'sub' => '1' ), $this->key );
		$parts = explode( '.', $jwt );

		$forged_payload = self::base64url_encode( json_encode( array( 'sub' => '999' ) ) );
		$tampered       = $parts[0] . '.' . $forged_payload . '.' . $parts[2];

		$this->expectException( \RuntimeException::class );
		Jwt_Codec::decode( $tampered, $this->key );
	}

	/**
	 * The `alg: none` bypass is rejected.
	 */
	public function test_decode_rejects_alg_none(): void {
		$header    = self::base64url_encode( json_encode( array( 'typ' => 'JWT', 'alg' => 'none' ) ) );
		$payload   = self::base64url_encode( json_encode( array( 'sub' => '1' ) ) );
		$forged    = $header . '.' . $payload . '.';

		$this->expectException( \InvalidArgumentException::class );
		Jwt_Codec::decode( $forged, $this->key );
	}

	/**
	 * An asymmetric algorithm header is rejected (algorithm-confusion defense).
	 */
	public function test_decode_rejects_algorithm_confusion(): void {
		$signing_input = self::base64url_encode( json_encode( array( 'typ' => 'JWT', 'alg' => 'RS256' ) ) )
			. '.' . self::base64url_encode( json_encode( array( 'sub' => '1' ) ) );
		// Sign with HMAC while claiming RS256, the classic confusion attack.
		$signature = self::base64url_encode( hash_hmac( 'sha256', $signing_input, $this->key, true ) );
		$forged    = $signing_input . '.' . $signature;

		$this->expectException( \InvalidArgumentException::class );
		Jwt_Codec::decode( $forged, $this->key );
	}

	/**
	 * A structurally malformed token is rejected.
	 */
	public function test_decode_rejects_malformed_token(): void {
		$this->expectException( \InvalidArgumentException::class );
		Jwt_Codec::decode( 'not-a-jwt', $this->key );
	}

	/**
	 * URL-safe base64 encode helper mirroring the codec's private format.
	 *
	 * @param string $data Raw data.
	 * @return string
	 */
	private static function base64url_encode( string $data ): string {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}

	/**
	 * URL-safe base64 decode helper.
	 *
	 * @param string $data Encoded data.
	 * @return string
	 */
	private static function base64url_decode( string $data ): string {
		$remainder = strlen( $data ) % 4;
		if ( $remainder ) {
			$data .= str_repeat( '=', 4 - $remainder );
		}
		return (string) base64_decode( strtr( $data, '-_', '+/' ) );
	}
}
