<?php
/**
 * Token Service Test
 *
 * @package MSKD\Tests\Unit
 */

namespace MSKD\Tests\Unit;

use Brain\Monkey\Functions;
use MSKD\Api\Token_Service;
use MSKD\Api\Jwt_Codec;

/**
 * Class Token_Service_Test
 */
class Token_Service_Test extends TestCase {

	/**
	 * Token service under test.
	 *
	 * @var Token_Service
	 */
	private $service;

	/**
	 * Set up wpdb mock and encoding stubs.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->setup_wpdb_mock();
		Functions\when( 'wp_json_encode' )->alias(
			function ( $data ) {
				return json_encode( $data );
			}
		);
		$this->service = new Token_Service();
	}

	/**
	 * Build a signed JWT with arbitrary claims using the service secret.
	 *
	 * @param array $claims Claims to encode.
	 * @return string
	 */
	private function make_token( array $claims ): string {
		return Jwt_Codec::encode( $claims, Token_Service::secret() );
	}

	/**
	 * Base valid claim set for authentication tests.
	 *
	 * @param array $overrides Claim overrides.
	 * @return array
	 */
	private function base_claims( array $overrides = array() ): array {
		$now = time();
		return array_merge(
			array(
				'iss'    => 'https://example.com',
				'aud'    => Token_Service::AUDIENCE,
				'sub'    => '5',
				'jti'    => 'test-jti-value',
				'iat'    => $now,
				'nbf'    => $now,
				'scopes' => array( 'campaigns:read', 'campaigns:write' ),
			),
			$overrides
		);
	}

	/**
	 * create_token issues a decodable token and persists a record.
	 */
	public function test_create_token_issues_valid_jwt(): void {
		$this->wpdb->shouldReceive( 'insert' )->once()->andReturn( 1 );
		$this->wpdb->insert_id = 42;

		$result = $this->service->create_token( 'My token', array( 'campaigns:read', 'campaigns:write' ), 90, 7 );

		$this->assertIsArray( $result );
		$this->assertSame( 42, $result['id'] );
		$this->assertNotEmpty( $result['jwt'] );

		$claims = Jwt_Codec::decode( $result['jwt'], Token_Service::secret() );
		$this->assertSame( 'https://example.com', $claims['iss'] );
		$this->assertSame( Token_Service::AUDIENCE, $claims['aud'] );
		$this->assertSame( '7', $claims['sub'] );
		$this->assertSame( array( 'campaigns:read', 'campaigns:write' ), $claims['scopes'] );
		$this->assertArrayHasKey( 'exp', $claims );
	}

	/**
	 * A no-expiry token omits the exp claim.
	 */
	public function test_create_token_without_expiry_has_no_exp(): void {
		$this->wpdb->shouldReceive( 'insert' )->once()->andReturn( 1 );
		$this->wpdb->insert_id = 1;

		$result = $this->service->create_token( 'Forever', array( 'campaigns:read' ), 0, 1 );
		$claims = Jwt_Codec::decode( $result['jwt'], Token_Service::secret() );

		$this->assertArrayNotHasKey( 'exp', $claims );
	}

	/**
	 * An empty name is rejected.
	 */
	public function test_create_token_requires_name(): void {
		$result = $this->service->create_token( '   ', array( 'campaigns:read' ), 30, 1 );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'invalid_name', $result->get_error_code() );
	}

	/**
	 * Unknown scopes are dropped and an empty scope list is rejected.
	 */
	public function test_create_token_rejects_invalid_scopes(): void {
		$result = $this->service->create_token( 'Bad scopes', array( 'nonsense', 'delete:everything' ), 30, 1 );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'invalid_scopes', $result->get_error_code() );
	}

	/**
	 * A valid token for an administrator authenticates successfully.
	 */
	public function test_authenticate_accepts_valid_token(): void {
		Functions\when( 'user_can' )->justReturn( true );

		$this->wpdb->shouldReceive( 'get_row' )->once()->andReturn(
			(object) array(
				'id'         => 10,
				'user_id'    => 5,
				'scopes'     => 'campaigns:read,campaigns:write',
				'expires_at' => null,
			)
		);
		$this->wpdb->shouldReceive( 'update' )->once()->andReturn( 1 );

		$jwt    = $this->make_token( $this->base_claims() );
		$result = $this->service->authenticate( $jwt );

		$this->assertIsArray( $result );
		$this->assertSame( 5, $result['user_id'] );
		$this->assertSame( 10, $result['token_id'] );
		$this->assertContains( 'campaigns:write', $result['scopes'] );
	}

	/**
	 * A token whose record was deleted is treated as revoked.
	 */
	public function test_authenticate_rejects_revoked_token(): void {
		Functions\when( 'user_can' )->justReturn( true );
		$this->wpdb->shouldReceive( 'get_row' )->once()->andReturn( null );

		$result = $this->service->authenticate( $this->make_token( $this->base_claims() ) );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'revoked_token', $result->get_error_code() );
	}

	/**
	 * An expired token is rejected before any database lookup.
	 */
	public function test_authenticate_rejects_expired_token(): void {
		$claims = $this->base_claims( array( 'exp' => time() - 3600 ) );
		$result = $this->service->authenticate( $this->make_token( $claims ) );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'expired_token', $result->get_error_code() );
	}

	/**
	 * A token minted for another issuer is rejected.
	 */
	public function test_authenticate_rejects_wrong_issuer(): void {
		$claims = $this->base_claims( array( 'iss' => 'https://evil.example.org' ) );
		$result = $this->service->authenticate( $this->make_token( $claims ) );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'invalid_token', $result->get_error_code() );
	}

	/**
	 * A token for the wrong audience is rejected.
	 */
	public function test_authenticate_rejects_wrong_audience(): void {
		$claims = $this->base_claims( array( 'aud' => 'some-other-api' ) );
		$result = $this->service->authenticate( $this->make_token( $claims ) );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'invalid_token', $result->get_error_code() );
	}

	/**
	 * A token whose owner is no longer an administrator is forbidden.
	 */
	public function test_authenticate_rejects_when_owner_lacks_capability(): void {
		Functions\when( 'user_can' )->justReturn( false );
		$this->wpdb->shouldReceive( 'get_row' )->once()->andReturn(
			(object) array(
				'id'         => 10,
				'user_id'    => 5,
				'scopes'     => 'campaigns:read',
				'expires_at' => null,
			)
		);

		$result = $this->service->authenticate( $this->make_token( $this->base_claims() ) );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'forbidden_token', $result->get_error_code() );
	}

	/**
	 * A garbage token string is rejected as invalid.
	 */
	public function test_authenticate_rejects_garbage_token(): void {
		$result = $this->service->authenticate( 'this-is-not-a-jwt' );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'invalid_token', $result->get_error_code() );
	}

	/**
	 * An empty token is reported as missing.
	 */
	public function test_authenticate_rejects_empty_token(): void {
		$result = $this->service->authenticate( '' );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'missing_token', $result->get_error_code() );
	}

	/**
	 * A token signed with a secret other than the site's is rejected.
	 *
	 * This is the mechanism by which rotating the WordPress salts (which changes the
	 * derived secret) invalidates every previously issued token.
	 */
	public function test_token_signed_with_foreign_secret_is_rejected(): void {
		$jwt = Jwt_Codec::encode( $this->base_claims(), 'a-secret-from-before-salt-rotation' );

		$result = $this->service->authenticate( $jwt );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'invalid_token', $result->get_error_code() );
	}

	/**
	 * delete_token proxies to the database delete.
	 */
	public function test_delete_token(): void {
		$this->wpdb->shouldReceive( 'delete' )->once()->with( 'wp_mskd_api_tokens', array( 'id' => 3 ), array( '%d' ) )->andReturn( 1 );
		$this->assertTrue( $this->service->delete_token( 3 ) );
	}
}
