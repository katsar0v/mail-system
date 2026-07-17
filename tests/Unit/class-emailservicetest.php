<?php
/**
 * Email Service Test
 *
 * @package MSKD\Tests\Unit
 */

namespace MSKD\Tests\Unit;

use Brain\Monkey\Functions;
use MSKD\Services\Email_Service;
use MSKD\Services\Subscriber_Service;
use Mockery;

/**
 * Class Email_Service_Test
 *
 * Test batch processing functionality in Email_Service.
 */
class Email_Service_Test extends TestCase {

	/**
	 * Email service instance.
	 *
	 * @var Email_Service
	 */
	private $email_service;

	/**
	 * Subscriber service mock.
	 *
	 * @var \Mockery\MockInterface
	 */
	private $subscriber_service;

	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {
		parent::setUp();
		
		// Set up wpdb mock.
		$this->setup_wpdb_mock();
		
		// Create subscriber service mock.
		$this->subscriber_service = Mockery::mock( Subscriber_Service::class );
		
		// Create email service with mocked dependencies.
		$this->email_service = new Email_Service();
		
		// Replace the subscriber service with our mock.
		$reflection = new \ReflectionClass( $this->email_service );
		$property = $reflection->getProperty( 'subscriber_service' );
		$property->setAccessible( true );
		$property->setValue( $this->email_service, $this->subscriber_service );
	}

	/**
	 * Test batch queue subscribers with external and internal subscribers.
	 */
	public function test_batch_queue_subscribers_mixed_types() {
		// Mock wpdb insert for campaign creation.
		$this->wpdb->shouldReceive( 'insert' )
			->once()
			->andReturn( true );
		$this->wpdb->insert_id = 1; // Campaign ID.

		// Mock option functions.
		Functions\stubs(
			array(
				'get_option'    => function ( $option, $default ) {
					return 'mskd_total_campaigns_created' === $option ? 0 : $default;
				},
				'update_option' => function () {
					return true;
				},
			)
		);

		// Prepare test data.
		$subscribers = array(
			(object) array(
				'id' => 'ext_test1',
				'email' => 'external1@example.com',
				'first_name' => 'External',
				'last_name' => 'One',
			),
			(object) array(
				'id' => 1,
				'email' => 'internal1@example.com',
				'first_name' => 'Internal',
				'last_name' => 'One',
			),
			(object) array(
				'id' => 'ext_test2',
				'email' => 'external2@example.com',
				'first_name' => 'External',
				'last_name' => 'Two',
			),
		);

		// Mock batch_get_or_create for external subscribers.
		$this->subscriber_service->shouldReceive( 'batch_get_or_create' )
			->once()
			->with(
				array(
					array(
						'email' => 'external1@example.com',
						'first_name' => 'External',
						'last_name' => 'One',
						'source' => 'external',
					),
					array(
						'email' => 'external2@example.com',
						'first_name' => 'External',
						'last_name' => 'Two',
						'source' => 'external',
					),
				)
			)
			->andReturn(
				array(
					'external1@example.com' => (object) array(
						'id' => 101,
						'email' => 'external1@example.com',
						'status' => 'active',
					),
					'external2@example.com' => (object) array(
						'id' => 102,
						'email' => 'external2@example.com',
						'status' => 'active',
					),
				)
			);

		// Mock batch_get_by_ids for internal subscribers.
		$this->subscriber_service->shouldReceive( 'batch_get_by_ids' )
			->once()
			->with( array( 1 ) )
			->andReturn(
				array(
					1 => (object) array(
						'id' => 1,
						'email' => 'internal1@example.com',
						'status' => 'active',
					),
				)
			);

		// Mock batch insert for queue items.
		$this->wpdb->shouldReceive( 'query' )
			->once()
			->with(
					Mockery::on(
						function ( $query ) {
							return false !== strpos( $query, 'tracking_token' )
								&& false !== strpos( $query, 'click_token' )
								&& 6 === preg_match_all( "/'[a-f0-9]{64}'/", $query );
						}
				)
			)
			->andReturn( 3 ); // 3 items inserted.

		// Note: prepare() is already mocked in create_wpdb_mock() with andReturnUsing().

		// Execute queue_campaign.
		$campaign_data = array(
			'subject' => 'Test Subject',
			'body' => 'Test Body',
			'subscribers' => $subscribers,
		);

		$result = $this->email_service->queue_campaign( $campaign_data );

		// Assert campaign was created.
		$this->assertEquals( 1, $result );
	}

	/**
	 * Test that duplicate subscribers across lists are deduped before queueing.
	 */
	public function test_queue_campaign_dedupes_duplicate_subscribers(): void {
		// Mock wpdb insert for campaign creation and capture total recipients (should be deduped).
		$this->wpdb->shouldReceive( 'insert' )
			->once()
			->with(
				'wp_mskd_campaigns',
				Mockery::on(
					function ( $data ) {
						return isset( $data['total_recipients'] ) && 2 === $data['total_recipients'];
					}
				),
				Mockery::type( 'array' )
			)
			->andReturn( true );
		$this->wpdb->insert_id = 1; // Campaign ID.

		// Mock option functions.
		Functions\stubs(
			array(
				'get_option'    => function ( $option, $default ) {
					return 'mskd_total_campaigns_created' === $option ? 0 : $default;
				},
				'update_option' => function () {
					return true;
				},
			)
		);

		$subscribers = array(
			(object) array(
				'id'         => 1,
				'email'      => 'duplicate@example.com',
				'first_name' => 'Primary',
				'last_name'  => 'User',
			),
			(object) array(
				'id'         => 1,
				'email'      => 'duplicate@example.com',
				'first_name' => 'Duplicate',
				'last_name'  => 'User',
			),
			(object) array(
				'id'         => 'ext_1',
				'email'      => 'DUPLICATE@example.com',
				'first_name' => 'External',
				'last_name'  => 'Duplicate',
			),
			(object) array(
				'id'         => 2,
				'email'      => 'unique@example.com',
				'first_name' => 'Unique',
				'last_name'  => 'User',
			),
		);

		// Only unique internal IDs 1 and 2 should be fetched.
		$this->subscriber_service->shouldReceive( 'batch_get_by_ids' )
			->once()
			->with( array( 1, 2 ) )
			->andReturn(
				array(
					1 => (object) array(
						'id'     => 1,
						'email'  => 'duplicate@example.com',
						'status' => 'active',
					),
					2 => (object) array(
						'id'     => 2,
						'email'  => 'unique@example.com',
						'status' => 'active',
					),
				)
			);

		// No external subscribers should remain after dedupe.
		$this->subscriber_service->shouldReceive( 'batch_get_or_create' )
			->never();

		// Only two queue items should be inserted.
		$this->wpdb->shouldReceive( 'query' )
			->once()
			->andReturn( 2 );

		$campaign_data = array(
			'subject'     => 'Test Subject',
			'body'        => 'Test Body',
			'subscribers' => $subscribers,
		);

		$result = $this->email_service->queue_campaign( $campaign_data );

		$this->assertEquals( 1, $result );
	}

	/**
	 * Test batch queue subscribers with unsubscribed subscribers filtered out.
	 */
	public function test_batch_queue_subscribers_filters_unsubscribed() {
		// Mock wpdb insert for campaign creation.
		$this->wpdb->shouldReceive( 'insert' )
			->once()
			->andReturn( true );
		$this->wpdb->insert_id = 1; // Campaign ID.

		// Mock option functions.
		Functions\stubs(
			array(
				'get_option'    => function ( $option, $default ) {
					return 'mskd_total_campaigns_created' === $option ? 0 : $default;
				},
				'update_option' => function () {
					return true;
				},
			)
		);

		// Prepare test data with one unsubscribed subscriber.
		$subscribers = array(
			(object) array(
				'id' => 1,
				'email' => 'active@example.com',
				'first_name' => 'Active',
				'last_name' => 'User',
			),
			(object) array(
				'id' => 2,
				'email' => 'unsubscribed@example.com',
				'first_name' => 'Unsubscribed',
				'last_name' => 'User',
			),
		);

		// Mock batch_get_by_ids.
		$this->subscriber_service->shouldReceive( 'batch_get_by_ids' )
			->once()
			->with( array( 1, 2 ) )
			->andReturn(
				array(
					1 => (object) array(
						'id' => 1,
						'email' => 'active@example.com',
						'status' => 'active',
					),
					2 => (object) array(
						'id' => 2,
						'email' => 'unsubscribed@example.com',
						'status' => 'unsubscribed',
					),
				)
			);

		// Mock batch insert for queue items (only 1 should be inserted).
		$this->wpdb->shouldReceive( 'query' )
			->once()
			->andReturn( 1 ); // Only 1 item inserted.

		// Note: prepare() is already mocked in create_wpdb_mock() with andReturnUsing().

		// Execute queue_campaign.
		$campaign_data = array(
			'subject' => 'Test Subject',
			'body' => 'Test Body',
			'subscribers' => $subscribers,
		);

		$result = $this->email_service->queue_campaign( $campaign_data );

		// Assert campaign was created.
		$this->assertEquals( 1, $result );
	}

	/**
	 * Test batch queue subscribers with large list (chunking).
	 */
	public function test_batch_queue_subscribers_with_chunking() {
		// Mock wpdb insert for campaign creation.
		$this->wpdb->shouldReceive( 'insert' )
			->once()
			->andReturn( true );
		$this->wpdb->insert_id = 1; // Campaign ID.

		// Mock option functions.
		Functions\stubs(
			array(
				'get_option'    => function ( $option, $default ) {
					return 'mskd_total_campaigns_created' === $option ? 0 : $default;
				},
				'update_option' => function () {
					return true;
				},
			)
		);

		// Create a large list of subscribers (1000 to test chunking).
		$subscribers = array();
		$expected_external_data = array();
		$expected_external_results = array();
		$expected_internal_ids = array();
		$expected_internal_results = array();

		for ( $i = 1; $i <= 1000; $i++ ) {
			if ( $i % 2 === 0 ) {
				// External subscriber.
				$email = "external{$i}@example.com";
				$subscribers[] = (object) array(
					'id' => "ext_test{$i}",
					'email' => $email,
					'first_name' => "External {$i}",
					'last_name' => 'User',
				);
				$expected_external_data[] = array(
					'email' => $email,
					'first_name' => "External {$i}",
					'last_name' => 'User',
					'source' => 'external',
				);
				$expected_external_results[ $email ] = (object) array(
					'id' => 1000 + $i,
					'email' => $email,
					'status' => 'active',
				);
			} else {
				// Internal subscriber.
				$subscribers[] = (object) array(
					'id' => $i,
					'email' => "internal{$i}@example.com",
					'first_name' => "Internal {$i}",
					'last_name' => 'User',
				);
				$expected_internal_ids[] = $i;
				$expected_internal_results[ $i ] = (object) array(
					'id' => $i,
					'email' => "internal{$i}@example.com",
					'status' => 'active',
				);
			}
		}

		// Mock batch_get_or_create for external subscribers (should be called twice for 2 chunks).
		$this->subscriber_service->shouldReceive( 'batch_get_or_create' )
			->twice()
			->andReturnUsing(
				function ( $data ) use ( $expected_external_results ) {
					$results = array();
					foreach ( $data as $item ) {
						if ( isset( $expected_external_results[ $item['email'] ] ) ) {
							$results[ $item['email'] ] = $expected_external_results[ $item['email'] ];
						}
					}
					return $results;
				}
			);

		// Mock batch_get_by_ids for internal subscribers (should be called twice for 2 chunks).
		$this->subscriber_service->shouldReceive( 'batch_get_by_ids' )
			->twice()
			->andReturnUsing(
				function ( $ids ) use ( $expected_internal_results ) {
					$results = array();
					foreach ( $ids as $id ) {
						if ( isset( $expected_internal_results[ $id ] ) ) {
							$results[ $id ] = $expected_internal_results[ $id ];
						}
					}
					return $results;
				}
			);

		// Mock batch insert for queue items (should be called twice for 2 chunks).
		$this->wpdb->shouldReceive( 'query' )
			->twice()
			->andReturn( 500, 500 ); // 500 items inserted in each chunk.

		// Note: prepare() is already mocked in create_wpdb_mock() with andReturnUsing().

		// Execute queue_campaign.
		$campaign_data = array(
			'subject' => 'Test Subject',
			'body' => 'Test Body',
			'subscribers' => $subscribers,
		);

		$result = $this->email_service->queue_campaign( $campaign_data );

		// Assert campaign was created.
		$this->assertEquals( 1, $result );
	}

	/**
	 * Test batch queue subscribers with empty list.
	 */
	public function test_batch_queue_subscribers_empty_list() {
		// Execute queue_campaign with empty subscribers.
		$campaign_data = array(
			'subject' => 'Test Subject',
			'body' => 'Test Body',
			'subscribers' => array(),
		);

		$result = $this->email_service->queue_campaign( $campaign_data );

		// Assert false is returned when no subscribers are provided.
		$this->assertFalse( $result );
	}
}
