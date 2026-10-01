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

	/**
	 * Well-typed campaign payloads pass validation.
	 */
	public function test_validate_campaign_params_accepts_valid_payload(): void {
		$this->assertNull(
			Rest_Controller::validate_campaign_params(
				array(
					'subject'    => 'Hello',
					'body'       => '<p>x</p>',
					'list_ids'   => array( '1', 2, 'ext_customers' ),
					'from_email' => 'sender@example.com',
					'from_name'  => 'Sender',
				)
			)
		);
		// A single list identifier is still accepted.
		$this->assertNull( Rest_Controller::validate_campaign_params( array( 'list_ids' => '1' ) ) );
		// Missing fields are the campaign service's job (missing_subject etc.).
		$this->assertNull( Rest_Controller::validate_campaign_params( array() ) );
	}

	/**
	 * Array/object/number text fields are rejected instead of being stored as "Array".
	 *
	 * @dataProvider non_string_field_provider
	 *
	 * @param string $field Field name.
	 * @param mixed  $value Wrongly typed value.
	 */
	public function test_validate_campaign_params_rejects_non_string_text_fields( string $field, $value ): void {
		$error = Rest_Controller::validate_campaign_params(
			array(
				'subject'  => 'ok',
				'body'     => '<p>ok</p>',
				'list_ids' => array( '1' ),
				$field     => $value,
			)
		);

		$this->assertInstanceOf( \WP_Error::class, $error );
		$this->assertSame( 'invalid_param', $error->get_error_code() );
		$this->assertSame( array( 'status' => 400 ), $error->get_error_data() );
		$this->assertStringContainsString( $field, $error->get_error_message() );
	}

	/**
	 * Wrongly typed values for each text field.
	 *
	 * @return array
	 */
	public function non_string_field_provider(): array {
		return array(
			'array subject'     => array( 'subject', array( 'a', 'b' ) ),
			'array body'        => array( 'body', array( '<p>x</p>' ) ),
			'numeric from_name' => array( 'from_name', 42 ),
			'array from_email'  => array( 'from_email', array( 'a@example.com' ) ),
			'bool subject'      => array( 'subject', true ),
		);
	}

	/**
	 * Nested or non-scalar list identifiers are rejected.
	 */
	public function test_validate_campaign_params_rejects_invalid_list_ids(): void {
		$error = Rest_Controller::validate_campaign_params( array( 'list_ids' => array( array( '1' ) ) ) );

		$this->assertInstanceOf( \WP_Error::class, $error );
		$this->assertSame( 'invalid_param', $error->get_error_code() );
		$this->assertStringContainsString( 'list_ids', $error->get_error_message() );
	}

	/**
	 * An invalid from_email reaches the campaign service and maps to a 400.
	 */
	public function test_invalid_sender_maps_to_bad_request(): void {
		$this->assertSame( 400, Rest_Controller::status_for_error( 'invalid_sender' ) );
		$this->assertSame( 400, Rest_Controller::status_for_error( 'invalid_param' ) );
	}
}
