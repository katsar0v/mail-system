<?php
/**
 * Email Tracking Service Tests
 *
 * @package MSKD\Tests\Unit
 */

namespace MSKD\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;
use MSKD\Services\Email_Tracking_Service;

/**
 * Class EmailTrackingServiceTest
 */
class EmailTrackingServiceTest extends TestCase {

	/**
	 * Tracking service instance.
	 *
	 * @var Email_Tracking_Service
	 */
	private $service;

	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->setup_wpdb_mock();
		$this->service = new Email_Tracking_Service();

		Functions\when( 'esc_url' )->alias(
			function ( $url ) {
				return $url;
			}
		);
	}

	/**
	 * Tokens are unpredictable, unique, and safe to use in URLs.
	 */
	public function test_generate_token_returns_unique_hex_tokens(): void {
		$first  = $this->service->generate_token();
		$second = $this->service->generate_token();

		$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $first );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $second );
		$this->assertNotSame( $first, $second );
	}

	/**
	 * A pixel is inserted before the closing body tag and is not duplicated.
	 */
	public function test_append_tracking_pixel_inserts_pixel_once(): void {
		$token = str_repeat( 'a', 64 );
		$body  = '<html><body><p>Hello</p></body></html>';

		$tracked = $this->service->append_tracking_pixel( $body, $token );

		$this->assertStringContainsString( '?mskd_track_open=' . $token, $tracked );
		$this->assertStringContainsString( 'width="1" height="1"', $tracked );
		$this->assertLessThan( strpos( $tracked, '</body>' ), strpos( $tracked, $token ) );
		$this->assertSame( 1, substr_count( $tracked, $token ) );
		$this->assertSame( $tracked, $this->service->append_tracking_pixel( $tracked, $token ) );
	}

	/**
	 * Invalid tokens do not alter email content.
	 */
	public function test_append_tracking_pixel_rejects_invalid_token(): void {
		$body = '<p>Hello</p>';

		$this->assertSame( $body, $this->service->append_tracking_pixel( $body, 'not-a-token' ) );
	}

	/**
	 * A valid event preserves the first-open timestamp and increments load count.
	 */
	public function test_record_open_updates_matching_sent_item(): void {
		$token = str_repeat( 'b', 64 );

		$this->wpdb->shouldReceive( 'query' )
			->once()
			->with(
				Mockery::on(
					function ( $query ) use ( $token ) {
						return false !== strpos( $query, 'opened_at = COALESCE(opened_at' )
							&& false !== strpos( $query, 'open_count = open_count + 1' )
							&& false !== strpos( $query, "status IN ('processing', 'sent')" )
							&& false !== strpos( $query, $token );
					}
				)
			)
			->andReturn( 1 );

		$this->assertTrue( $this->service->record_open( $token ) );
	}

	/**
	 * Invalid tokens are discarded without touching the database.
	 */
	public function test_record_open_rejects_invalid_token(): void {
		$this->wpdb->shouldReceive( 'query' )->never();

		$this->assertFalse( $this->service->record_open( 'invalid' ) );
	}

	/**
	 * Unknown valid tokens produce no analytics update.
	 */
	public function test_record_open_returns_false_for_unknown_token(): void {
		$this->wpdb->shouldReceive( 'query' )->once()->andReturn( 0 );

		$this->assertFalse( $this->service->record_open( str_repeat( 'c', 64 ) ) );
	}
}
