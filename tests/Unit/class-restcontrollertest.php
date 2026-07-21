<?php
/**
 * REST Controller Test
 *
 * Covers the pure request-shaping helpers that do not require the WordPress REST
 * infrastructure. Route wiring and authentication are validated via integration
 * testing (see docs/rest-api.md).
 *
 * @package MSKD\Tests\Unit
 */

namespace MSKD\Tests\Unit;

use MSKD\Api\Rest_Controller;

/**
 * Class Rest_Controller_Test
 */
class Rest_Controller_Test extends TestCase {

	/**
	 * An empty scheduled_at means an immediate send.
	 */
	public function test_parse_scheduled_at_empty_is_immediate(): void {
		$result = Rest_Controller::parse_scheduled_at( '' );

		$this->assertTrue( $result['is_immediate'] );
		$this->assertSame( '', $result['scheduled_at'] );
	}

	/**
	 * A valid future ISO-8601 timestamp is normalized to the second boundary.
	 */
	public function test_parse_scheduled_at_future_is_accepted(): void {
		$result = Rest_Controller::parse_scheduled_at( '2030-01-01T10:07:30+00:00' );

		$this->assertArrayNotHasKey( 'error', $result );
		$this->assertFalse( $result['is_immediate'] );
		$this->assertSame( '2030-01-01 10:07:00', $result['scheduled_at'] );
	}

	/**
	 * A past timestamp is rejected rather than coerced to an immediate send.
	 */
	public function test_parse_scheduled_at_past_is_rejected(): void {
		$result = Rest_Controller::parse_scheduled_at( '2000-01-01T00:00:00+00:00' );

		$this->assertArrayHasKey( 'error', $result );
		$this->assertSame( 'past_schedule', $result['error'] );
	}

	/**
	 * A malformed timestamp is rejected.
	 */
	public function test_parse_scheduled_at_invalid_is_rejected(): void {
		$result = Rest_Controller::parse_scheduled_at( 'definitely-not-a-date' );

		$this->assertArrayHasKey( 'error', $result );
		$this->assertSame( 'invalid_schedule', $result['error'] );
	}

	/**
	 * Relative expressions are rejected: only ISO-8601 timestamps are accepted, not
	 * the natural-language forms DateTime would otherwise parse.
	 */
	public function test_parse_scheduled_at_relative_is_rejected(): void {
		foreach ( array( 'tomorrow', '+1 day', 'now', 'next monday' ) as $relative ) {
			$result = Rest_Controller::parse_scheduled_at( $relative );

			$this->assertArrayHasKey( 'error', $result, "Expected '{$relative}' to be rejected." );
			$this->assertSame( 'invalid_schedule', $result['error'] );
		}
	}

	/**
	 * Application error codes map to sensible HTTP statuses.
	 */
	public function test_status_for_error_mapping(): void {
		$this->assertSame( 500, Rest_Controller::status_for_error( 'db_error' ) );
		$this->assertSame( 400, Rest_Controller::status_for_error( 'missing_subject' ) );
		$this->assertSame( 400, Rest_Controller::status_for_error( 'invalid_bcc' ) );
		$this->assertSame( 400, Rest_Controller::status_for_error( 'no_recipients' ) );
		$this->assertSame( 400, Rest_Controller::status_for_error( null ) );
	}
}
