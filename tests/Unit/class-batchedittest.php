<?php
/**
 * Batch Edit Tests
 *
 * @package MSKD\Tests\Unit
 */

namespace MSKD\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;
use MSKD\Services\Subscriber_Service;

/**
 * Class BatchEditTest
 *
 * Tests for batch list assignment functionality.
 */
class BatchEditTest extends TestCase {

	/**
	 * Subscriber service instance.
	 *
	 * @var Subscriber_Service
	 */
	protected $service;

	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {
		parent::setUp();

		// Set up wpdb mock.
		$this->setup_wpdb_mock();
		$this->wpdb->shouldIgnoreMissing();

		// Load the subscriber service.
		$this->service = new Subscriber_Service();
	}

	/**
	 * Test batch assign lists to multiple subscribers.
	 */
	public function test_batch_assign_lists_success(): void {
		$wpdb = $this->wpdb;

		// Mock get_row for get_by_id - return subscriber objects.
		$wpdb->shouldReceive( 'get_row' )
			->andReturn(
				(object) array(
					'id'    => 1,
					'email' => 'test@example.com',
				)
			);

		// Mock get_col for get_lists - return empty array.
		$wpdb->shouldReceive( 'get_col' )
			->andReturn( array() );

		// Mock delete for sync_lists.
		$wpdb->shouldReceive( 'delete' )
			->andReturn( 1 );

		// Mock insert for sync_lists.
		$wpdb->shouldReceive( 'insert' )
			->andReturn( 1 );

		$result = $this->service->batch_assign_lists( array( 1, 2 ), array( 5, 6 ) );

		$this->assertEquals( 2, $result['success'] );
		$this->assertEquals( 0, $result['failed'] );
		$this->assertEmpty( $result['errors'] );
	}

	/**
	 * Test batch assign lists with non-existent subscriber.
	 */
	public function test_batch_assign_lists_with_missing_subscriber(): void {
		$wpdb = $this->wpdb;

		$call_count = 0;
		// First subscriber exists, second doesn't.
		$wpdb->shouldReceive( 'get_row' )
			->andReturnUsing(
				function () use ( &$call_count ) {
					++$call_count;
					if ( 1 === $call_count ) {
							return (object) array(
								'id'    => 1,
								'email' => 'test1@example.com',
							);
					}
					return null; // Second subscriber doesn't exist.
				}
			);

		// Mock get_col for get_lists.
		$wpdb->shouldReceive( 'get_col' )
			->andReturn( array() );

		// Mock delete and insert for sync_lists.
		$wpdb->shouldReceive( 'delete' )
			->andReturn( 1 );
		$wpdb->shouldReceive( 'insert' )
			->andReturn( 1 );

		$result = $this->service->batch_assign_lists( array( 1, 999 ), array( 5 ) );

		$this->assertEquals( 1, $result['success'] );
		$this->assertEquals( 1, $result['failed'] );
		$this->assertCount( 1, $result['errors'] );
		$this->assertStringContainsString( '999', $result['errors'][0] );
	}

	/**
	 * Test batch assign lists with empty subscriber IDs.
	 */
	public function test_batch_assign_lists_empty_subscribers(): void {
		$result = $this->service->batch_assign_lists( array(), array( 1, 2 ) );

		$this->assertEquals( 0, $result['success'] );
		$this->assertEquals( 0, $result['failed'] );
		$this->assertEmpty( $result['errors'] );
	}

	/**
	 * Test batch assign lists with empty list IDs.
	 */
	public function test_batch_assign_lists_empty_lists(): void {
		$result = $this->service->batch_assign_lists( array( 1, 2 ), array() );

		$this->assertEquals( 0, $result['success'] );
		$this->assertEquals( 0, $result['failed'] );
		$this->assertEmpty( $result['errors'] );
	}

	/**
	 * Test batch remove lists from multiple subscribers.
	 */
	public function test_batch_remove_lists_success(): void {
		$wpdb = $this->wpdb;

		// Mock get_row for get_by_id.
		$wpdb->shouldReceive( 'get_row' )
			->andReturn(
				(object) array(
					'id'    => 1,
					'email' => 'test@example.com',
				)
			);

		// Mock get_col for get_lists - subscriber has lists 5, 6, 7.
		$wpdb->shouldReceive( 'get_col' )
			->andReturn( array( '5', '6', '7' ) );

		// Mock delete and insert for sync_lists.
		$wpdb->shouldReceive( 'delete' )
			->andReturn( 1 );
		$wpdb->shouldReceive( 'insert' )
			->andReturn( 1 );

		$result = $this->service->batch_remove_lists( array( 1, 2 ), array( 5 ) );

		$this->assertEquals( 2, $result['success'] );
		$this->assertEquals( 0, $result['failed'] );
		$this->assertEmpty( $result['errors'] );
	}

	/**
	 * Test batch remove lists with non-existent subscriber.
	 */
	public function test_batch_remove_lists_with_missing_subscriber(): void {
		$wpdb = $this->wpdb;

		$call_count = 0;
		// First subscriber exists, second doesn't.
		$wpdb->shouldReceive( 'get_row' )
			->andReturnUsing(
				function () use ( &$call_count ) {
					++$call_count;
					if ( 1 === $call_count ) {
							return (object) array(
								'id'    => 1,
								'email' => 'test1@example.com',
							);
					}
					return null; // Second subscriber doesn't exist.
				}
			);

		// Mock get_col for get_lists.
		$wpdb->shouldReceive( 'get_col' )
			->andReturn( array( '5', '6' ) );

		// Mock delete and insert for sync_lists.
		$wpdb->shouldReceive( 'delete' )
			->andReturn( 1 );
		$wpdb->shouldReceive( 'insert' )
			->andReturn( 1 );

		$result = $this->service->batch_remove_lists( array( 1, 999 ), array( 5 ) );

		$this->assertEquals( 1, $result['success'] );
		$this->assertEquals( 1, $result['failed'] );
		$this->assertCount( 1, $result['errors'] );
		$this->assertStringContainsString( '999', $result['errors'][0] );
	}

	/**
	 * Test batch operations filter out invalid IDs.
	 */
	public function test_batch_operations_filter_invalid_ids(): void {
		// Test with negative IDs and zeros - should be filtered out.
		$result = $this->service->batch_assign_lists( array( -1, 0, -5 ), array( 1 ) );

		$this->assertEquals( 0, $result['success'] );
		$this->assertEquals( 0, $result['failed'] );

		$result = $this->service->batch_assign_lists( array( 1 ), array( -1, 0 ) );

		$this->assertEquals( 0, $result['success'] );
		$this->assertEquals( 0, $result['failed'] );
	}

	/**
	 * Test batch update status to active for multiple subscribers.
	 */
	public function test_batch_update_status_success(): void {
		$wpdb = $this->wpdb;

		// Mock get_row for get_by_id - return subscriber objects.
		$wpdb->shouldReceive( 'get_row' )
			->andReturn(
				(object) array(
					'id'     => 1,
					'email'  => 'test@example.com',
					'status' => 'inactive',
				)
			);

		// Mock update for the status change.
		$wpdb->shouldReceive( 'update' )
			->andReturn( 1 );

		$result = $this->service->batch_update_status( array( 1, 2 ), 'active' );

		$this->assertEquals( 2, $result['success'] );
		$this->assertEquals( 0, $result['failed'] );
		$this->assertEmpty( $result['errors'] );
	}

	/**
	 * Test batch update status with non-existent subscriber.
	 */
	public function test_batch_update_status_with_missing_subscriber(): void {
		$wpdb = $this->wpdb;

		$call_count = 0;
		// First subscriber exists, second doesn't.
		$wpdb->shouldReceive( 'get_row' )
			->andReturnUsing(
				function () use ( &$call_count ) {
					++$call_count;
					if ( 1 === $call_count ) {
							return (object) array(
								'id'    => 1,
								'email' => 'test1@example.com',
							);
					}
					return null; // Second subscriber doesn't exist.
				}
			);

		$wpdb->shouldReceive( 'update' )
			->andReturn( 1 );

		$result = $this->service->batch_update_status( array( 1, 999 ), 'inactive' );

		$this->assertEquals( 1, $result['success'] );
		$this->assertEquals( 1, $result['failed'] );
		$this->assertCount( 1, $result['errors'] );
		$this->assertStringContainsString( '999', $result['errors'][0] );
	}

	/**
	 * Test batch update status with empty subscriber IDs.
	 */
	public function test_batch_update_status_empty_subscribers(): void {
		$result = $this->service->batch_update_status( array(), 'active' );

		$this->assertEquals( 0, $result['success'] );
		$this->assertEquals( 0, $result['failed'] );
		$this->assertEmpty( $result['errors'] );
	}

	/**
	 * Test batch update status filters out invalid IDs.
	 */
	public function test_batch_update_status_filters_invalid_ids(): void {
		$result = $this->service->batch_update_status( array( -1, 0, -5 ), 'active' );

		$this->assertEquals( 0, $result['success'] );
		$this->assertEquals( 0, $result['failed'] );
		$this->assertEmpty( $result['errors'] );
	}

	/**
	 * Test batch update status with an invalid target status.
	 */
	public function test_batch_update_status_invalid_status(): void {
		$result = $this->service->batch_update_status( array( 1, 2 ), 'unsubscribed' );

		$this->assertEquals( 0, $result['success'] );
		$this->assertEquals( 0, $result['failed'] );
		$this->assertNotEmpty( $result['errors'] );
	}

	/**
	 * Clean up after each test.
	 */
	protected function tearDown(): void {
		parent::tearDown();
	}
}
