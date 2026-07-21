<?php
/**
 * Campaign Service Test
 *
 * @package MSKD\Tests\Unit
 */

namespace MSKD\Tests\Unit;

use MSKD\Application\Campaign_Service;
use MSKD\Services\Email_Service;
use Mockery;

/**
 * Test double that bypasses the list provider by returning a preset recipient set.
 */
class Stub_Campaign_Service extends Campaign_Service {

	/**
	 * Preset resolution result.
	 *
	 * @var array
	 */
	public $resolution = array( 'recipients' => array() );

	/**
	 * Return the preset recipients instead of querying the list provider.
	 *
	 * @param array $list_ids List identifiers.
	 * @return array
	 */
	protected function resolve_recipients( array $list_ids ): array {
		unset( $list_ids );
		return $this->resolution;
	}
}

/**
 * Class Campaign_Service_Test
 */
class Campaign_Service_Test extends TestCase {

	/**
	 * Build a minimal valid input array.
	 *
	 * @param array $overrides Field overrides.
	 * @return array
	 */
	private function valid_input( array $overrides = array() ): array {
		return array_merge(
			array(
				'subject'      => 'Hello',
				'body'         => '<p>Hi</p>',
				'list_ids'     => array( '1' ),
				'bcc'          => '',
				'from_email'   => '',
				'from_name'    => '',
				'scheduled_at' => '2026-08-01 10:00:00',
				'is_immediate' => false,
			),
			$overrides
		);
	}

	/**
	 * A subscriber-like recipient object.
	 *
	 * @param string $email Email.
	 * @return object
	 */
	private function recipient( string $email ): object {
		return (object) array(
			'id'         => 1,
			'email'      => $email,
			'first_name' => 'Test',
			'last_name'  => 'User',
		);
	}

	/**
	 * A missing subject is rejected before any persistence.
	 */
	public function test_missing_subject_is_rejected(): void {
		$service = new Campaign_Service( Mockery::mock( Email_Service::class ) );
		$result  = $service->schedule( $this->valid_input( array( 'subject' => '   ' ) ) );

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'missing_subject', $result['error_code'] );
	}

	/**
	 * A missing body is rejected.
	 */
	public function test_missing_body_is_rejected(): void {
		$service = new Campaign_Service( Mockery::mock( Email_Service::class ) );
		$result  = $service->schedule( $this->valid_input( array( 'body' => '' ) ) );

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'missing_body', $result['error_code'] );
	}

	/**
	 * An empty list selection is rejected.
	 */
	public function test_missing_lists_is_rejected(): void {
		$service = new Campaign_Service( Mockery::mock( Email_Service::class ) );
		$result  = $service->schedule( $this->valid_input( array( 'list_ids' => array() ) ) );

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'missing_lists', $result['error_code'] );
	}

	/**
	 * An invalid custom sender email is rejected.
	 */
	public function test_invalid_sender_is_rejected(): void {
		$service = new Campaign_Service( Mockery::mock( Email_Service::class ) );
		$result  = $service->schedule( $this->valid_input( array( 'from_email' => 'not-an-email' ) ) );

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'invalid_sender', $result['error_code'] );
	}

	/**
	 * An invalid Bcc address is rejected.
	 */
	public function test_invalid_bcc_is_rejected(): void {
		$service = new Campaign_Service( Mockery::mock( Email_Service::class ) );
		$result  = $service->schedule( $this->valid_input( array( 'bcc' => 'good@example.com, bad-address' ) ) );

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'invalid_bcc', $result['error_code'] );
	}

	/**
	 * When no recipients resolve, the campaign is not created.
	 */
	public function test_no_recipients_is_rejected(): void {
		$email   = Mockery::mock( Email_Service::class );
		$service = new Stub_Campaign_Service( $email );
		// Default stub resolution has no recipients.

		$result = $service->schedule( $this->valid_input() );

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'no_recipients', $result['error_code'] );
	}

	/**
	 * A valid request commits atomically and returns the real queued count.
	 */
	public function test_successful_schedule_commits_and_reports_count(): void {
		$email = Mockery::mock( Email_Service::class );
		$email->shouldReceive( 'transaction' )->with( 'START TRANSACTION' )->once();
		$email->shouldReceive( 'queue_campaign' )->once()->andReturn( 55 );
		$email->shouldReceive( 'get_last_queued_count' )->once()->andReturn( 3 );
		$email->shouldReceive( 'transaction' )->with( 'COMMIT' )->once();
		$email->shouldNotReceive( 'transaction' )->with( 'ROLLBACK' );

		$service             = new Stub_Campaign_Service( $email );
		$service->resolution = array(
			'recipients' => array(
				$this->recipient( 'a@example.com' ),
				$this->recipient( 'b@example.com' ),
				$this->recipient( 'c@example.com' ),
			),
		);

		$result = $service->schedule( $this->valid_input() );

		$this->assertTrue( $result['success'] );
		$this->assertSame( 55, $result['campaign_id'] );
		$this->assertSame( 3, $result['queued'] );
		$this->assertSame( 3, $result['total_recipients'] );
		$this->assertFalse( $result['is_immediate'] );
	}

	/**
	 * When persistence queues nothing, the transaction is rolled back.
	 */
	public function test_zero_queued_rolls_back(): void {
		$email = Mockery::mock( Email_Service::class );
		$email->shouldReceive( 'transaction' )->with( 'START TRANSACTION' )->once();
		$email->shouldReceive( 'queue_campaign' )->once()->andReturn( 55 );
		$email->shouldReceive( 'get_last_queued_count' )->once()->andReturn( 0 );
		$email->shouldReceive( 'transaction' )->with( 'ROLLBACK' )->once();
		$email->shouldNotReceive( 'transaction' )->with( 'COMMIT' );

		$service             = new Stub_Campaign_Service( $email );
		$service->resolution = array( 'recipients' => array( $this->recipient( 'a@example.com' ) ) );

		$result = $service->schedule( $this->valid_input() );

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'no_recipients', $result['error_code'] );
	}

	/**
	 * A failed campaign insert rolls back and reports a database error.
	 */
	public function test_failed_insert_rolls_back(): void {
		$email = Mockery::mock( Email_Service::class );
		$email->shouldReceive( 'transaction' )->with( 'START TRANSACTION' )->once();
		$email->shouldReceive( 'queue_campaign' )->once()->andReturn( false );
		$email->shouldReceive( 'transaction' )->with( 'ROLLBACK' )->once();

		$service             = new Stub_Campaign_Service( $email );
		$service->resolution = array( 'recipients' => array( $this->recipient( 'a@example.com' ) ) );

		$result = $service->schedule( $this->valid_input() );

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'db_error', $result['error_code'] );
	}
}
