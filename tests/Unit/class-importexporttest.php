<?php
/**
 * Import/Export Service Tests
 *
 * @package MSKD\Tests\Unit
 */

namespace MSKD\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;
use MSKD\Services\Import_Export_Service;

/**
 * Class ImportExportTest
 *
 * Tests for Import/Export service functionality.
 */
class ImportExportTest extends TestCase {

	/**
	 * Import/Export service instance.
	 *
	 * @var Import_Export_Service
	 */
	protected $service;

	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {
		parent::setUp();

		// Create a fresh wpdb mock with flexible prepare handling.
		$this->wpdb         = Mockery::mock( 'wpdb' );
		$this->wpdb->prefix = 'wp_';
		$this->wpdb->shouldReceive( 'prepare' )
			->andReturnUsing(
				function ( $query, ...$args ) {
					// Handle the case where an array is passed as second argument.
					if ( count( $args ) === 1 && is_array( $args[0] ) ) {
						$args = $args[0];
					}
					// Simple replacement for testing.
					$result = $query;
					foreach ( $args as $arg ) {
						$replacement = is_string( $arg ) ? "'" . $arg . "'" : (string) $arg;
						$result      = preg_replace( '/%[sd]/', $replacement, $result, 1 );
					}
					return $result;
				}
			);
		$this->wpdb->shouldIgnoreMissing();
		$GLOBALS['wpdb'] = $this->wpdb;

		// Load the service.
		$this->service = new Import_Export_Service();
	}

	/**
	 * Test export subscribers to CSV format.
	 */
	public function test_export_subscribers_csv_returns_valid_csv(): void {
		// Mock subscriber results.
		$subscribers = array(
			(object) array(
				'id'         => 1,
				'email'      => 'john@example.com',
				'first_name' => 'John',
				'last_name'  => 'Doe',
				'status'     => 'active',
				'created_at' => '2024-01-15 10:00:00',
			),
			(object) array(
				'id'         => 2,
				'email'      => 'jane@example.com',
				'first_name' => 'Jane',
				'last_name'  => 'Smith',
				'status'     => 'inactive',
				'created_at' => '2024-01-16 11:00:00',
			),
		);

		$this->wpdb->shouldReceive( 'get_var' )
			->andReturn( 2 );

		$this->wpdb->shouldReceive( 'get_results' )
			->andReturn( $subscribers );

		$this->wpdb->shouldReceive( 'get_col' )
			->andReturn( array() ); // No lists assigned.

		$csv = $this->service->export_subscribers_csv();

		// Check that CSV contains headers and data.
		$this->assertStringContainsString( 'email', $csv );
		$this->assertStringContainsString( 'first_name', $csv );
		$this->assertStringContainsString( 'john@example.com', $csv );
		$this->assertStringContainsString( 'Jane', $csv );
	}

	/**
	 * Test export lists to CSV format.
	 */
	public function test_export_lists_csv_returns_valid_csv(): void {
		$lists = array(
			(object) array(
				'id'               => 1,
				'name'             => 'Newsletter',
				'description'      => 'Weekly newsletter',
				'subscriber_count' => 50,
				'created_at'       => '2024-01-01 00:00:00',
			),
		);

		$this->wpdb->shouldReceive( 'get_results' )
			->andReturn( $lists );

		$csv = $this->service->export_lists_csv();

		$this->assertStringContainsString( 'name', $csv );
		$this->assertStringContainsString( 'Newsletter', $csv );
		$this->assertStringContainsString( 'Weekly newsletter', $csv );
	}

	/**
	 * Test parse subscribers CSV validates email column.
	 */
	public function test_parse_subscribers_csv_requires_email_column(): void {
		// Create a temp file with missing email column.
		$temp_file = tempnam( sys_get_temp_dir(), 'mskd_test' );
		file_put_contents( $temp_file, "first_name,last_name\nJohn,Doe" );

		$result = $this->service->parse_subscribers_csv( $temp_file );

		$this->assertFalse( $result['valid'] );
		$this->assertStringContainsString( 'email', $result['error'] );

		unlink( $temp_file );
	}

	/**
	 * Test parse subscribers CSV with valid data.
	 */
	public function test_parse_subscribers_csv_with_valid_data(): void {
		$csv_content  = "email,first_name,last_name,status\n";
		$csv_content .= "john@example.com,John,Doe,active\n";
		$csv_content .= "jane@example.com,Jane,Smith,inactive\n";

		$temp_file = tempnam( sys_get_temp_dir(), 'mskd_test' );
		file_put_contents( $temp_file, $csv_content );

		$result = $this->service->parse_subscribers_csv( $temp_file );

		$this->assertTrue( $result['valid'] );
		$this->assertCount( 2, $result['rows'] );
		$this->assertEquals( 'john@example.com', $result['rows'][0]['email'] );
		$this->assertEquals( 'Jane', $result['rows'][1]['first_name'] );
		$this->assertEmpty( $result['errors'] );

		unlink( $temp_file );
	}

	/**
	 * Test parse subscribers CSV reports invalid emails.
	 */
	public function test_parse_subscribers_csv_reports_invalid_emails(): void {
		$csv_content  = "email,first_name\n";
		$csv_content .= "invalid-email,John\n";
		$csv_content .= "jane@example.com,Jane\n";

		$temp_file = tempnam( sys_get_temp_dir(), 'mskd_test' );
		file_put_contents( $temp_file, $csv_content );

		$result = $this->service->parse_subscribers_csv( $temp_file );

		$this->assertTrue( $result['valid'] );
		$this->assertCount( 1, $result['rows'] ); // Only valid row.
		$this->assertCount( 1, $result['errors'] ); // One error for invalid email.
		$this->assertStringContainsString( 'Invalid email', $result['errors'][0] );

		unlink( $temp_file );
	}

	/**
	 * Test import subscribers creates new subscribers.
	 */
	public function test_import_subscribers_creates_new(): void {
		$rows = array(
			array(
				'email'      => 'new@example.com',
				'first_name' => 'New',
				'last_name'  => 'User',
				'status'     => 'active',
				'lists'      => '',
			),
		);

		// Mock no existing subscriber.
		$this->wpdb->shouldReceive( 'get_row' )
			->andReturn( null );

		// Mock insert.
		$this->wpdb->insert_id = 1;
		$this->wpdb->shouldReceive( 'insert' )
			->once()
			->andReturn( 1 );

		$result = $this->service->import_subscribers( $rows );

		$this->assertEquals( 1, $result['imported'] );
		$this->assertEquals( 0, $result['updated'] );
		$this->assertEquals( 0, $result['skipped'] );
		$this->assertEmpty( $result['errors'] );
	}

	/**
	 * Test import subscribers skips existing by default.
	 */
	public function test_import_subscribers_skips_existing_by_default(): void {
		$rows = array(
			array(
				'email'      => 'existing@example.com',
				'first_name' => 'Existing',
				'last_name'  => 'User',
				'status'     => 'active',
				'lists'      => '',
			),
		);

		// Mock existing subscriber.
		$this->wpdb->shouldReceive( 'get_row' )
			->andReturn(
				(object) array(
					'id'    => 1,
					'email' => 'existing@example.com',
				)
			);

		$result = $this->service->import_subscribers( $rows );

		$this->assertEquals( 0, $result['imported'] );
		$this->assertEquals( 0, $result['updated'] );
		$this->assertEquals( 1, $result['skipped'] );
	}

	/**
	 * Test import subscribers updates existing when option enabled.
	 */
	public function test_import_subscribers_updates_existing_when_enabled(): void {
		$rows = array(
			array(
				'email'      => 'existing@example.com',
				'first_name' => 'Updated',
				'last_name'  => 'Name',
				'status'     => 'inactive',
				'lists'      => '',
			),
		);

		// Mock existing subscriber.
		$this->wpdb->shouldReceive( 'get_row' )
			->andReturn(
				(object) array(
					'id'    => 1,
					'email' => 'existing@example.com',
				)
			);

		// Mock update.
		$this->wpdb->shouldReceive( 'update' )
			->once()
			->andReturn( 1 );

		$result = $this->service->import_subscribers( $rows, array( 'update_existing' => true ) );

		$this->assertEquals( 0, $result['imported'] );
		$this->assertEquals( 1, $result['updated'] );
		$this->assertEquals( 0, $result['skipped'] );
	}

	/**
	 * Build a row set that tries to re-activate an existing unsubscribed subscriber.
	 *
	 * @return array
	 */
	private function unsubscribed_row_fixture(): array {
		$this->wpdb->shouldReceive( 'get_row' )
			->andReturn(
				(object) array(
					'id'     => 1,
					'email'  => 'carol@example.test',
					'status' => 'unsubscribed',
				)
			);

		return array(
			array(
				'email'      => 'carol@example.test',
				'first_name' => 'Carol',
				'last_name'  => '',
				'status'     => 'active',
				'lists'      => '',
			),
		);
	}

	/**
	 * An import must not re-activate an unsubscribed subscriber by default.
	 */
	public function test_import_update_existing_keeps_unsubscribed_status(): void {
		$rows = $this->unsubscribed_row_fixture();

		$updates = array();
		$this->wpdb->shouldReceive( 'update' )
			->andReturnUsing(
				function ( $table, $data ) use ( &$updates ) {
					$updates[] = $data;
					return 1;
				}
			);

		$result = $this->service->import_subscribers( $rows, array( 'update_existing' => true ) );

		$this->assertSame( 1, $result['updated'] );
		$this->assertSame( 1, $result['kept_unsubscribed'] );
		$this->assertNotEmpty( $updates );
		foreach ( $updates as $data ) {
			$this->assertArrayNotHasKey( 'status', $data );
		}
	}

	/**
	 * With allow_resubscribe the CSV status is applied to an unsubscribed subscriber.
	 */
	public function test_import_update_existing_resubscribes_when_allowed(): void {
		$rows = $this->unsubscribed_row_fixture();

		$updates = array();
		$this->wpdb->shouldReceive( 'update' )
			->andReturnUsing(
				function ( $table, $data ) use ( &$updates ) {
					$updates[] = $data;
					return 1;
				}
			);

		$result = $this->service->import_subscribers(
			$rows,
			array(
				'update_existing'   => true,
				'allow_resubscribe' => true,
			)
		);

		$this->assertSame( 1, $result['updated'] );
		$this->assertSame( 0, $result['kept_unsubscribed'] );
		$statuses = array_column( $updates, 'status' );
		$this->assertContains( 'active', $statuses );
	}

	/**
	 * Test parse lists CSV requires name column.
	 */
	public function test_parse_lists_csv_requires_name_column(): void {
		$temp_file = tempnam( sys_get_temp_dir(), 'mskd_test' );
		file_put_contents( $temp_file, "description\nSome description" );

		$result = $this->service->parse_lists_csv( $temp_file );

		$this->assertFalse( $result['valid'] );
		$this->assertStringContainsString( 'name', $result['error'] );

		unlink( $temp_file );
	}

	/**
	 * Test parse lists CSV with valid data.
	 */
	public function test_parse_lists_csv_with_valid_data(): void {
		$csv_content  = "name,description\n";
		$csv_content .= "Newsletter,Weekly updates\n";
		$csv_content .= "Alerts,System alerts\n";

		$temp_file = tempnam( sys_get_temp_dir(), 'mskd_test' );
		file_put_contents( $temp_file, $csv_content );

		$result = $this->service->parse_lists_csv( $temp_file );

		$this->assertTrue( $result['valid'] );
		$this->assertCount( 2, $result['rows'] );
		$this->assertEquals( 'Newsletter', $result['rows'][0]['name'] );
		$this->assertEquals( 'System alerts', $result['rows'][1]['description'] );

		unlink( $temp_file );
	}

	/**
	 * Test import lists creates new lists.
	 */
	public function test_import_lists_creates_new(): void {
		$rows = array(
			array(
				'name'        => 'New List',
				'description' => 'A new mailing list',
			),
		);

		// Mock no existing list.
		$this->wpdb->shouldReceive( 'get_row' )
			->andReturn( null );

		// Mock insert.
		$this->wpdb->insert_id = 1;
		$this->wpdb->shouldReceive( 'insert' )
			->once()
			->andReturn( 1 );

		$result = $this->service->import_lists( $rows );

		$this->assertEquals( 1, $result['imported'] );
		$this->assertEquals( 0, $result['skipped'] );
		$this->assertEmpty( $result['errors'] );
	}

	/**
	 * Test import lists skips existing.
	 */
	public function test_import_lists_skips_existing(): void {
		$rows = array(
			array(
				'name'        => 'Existing List',
				'description' => 'Description',
			),
		);

		// Mock existing list.
		$this->wpdb->shouldReceive( 'get_row' )
			->andReturn(
				(object) array(
					'id'   => 1,
					'name' => 'Existing List',
				)
			);

		$result = $this->service->import_lists( $rows );

		$this->assertEquals( 0, $result['imported'] );
		$this->assertEquals( 1, $result['skipped'] );
	}

	/**
	 * Test import subscribers with target_list_ids overrides CSV lists.
	 */
	public function test_import_subscribers_with_target_list_ids_overrides_csv(): void {
		$rows = array(
			array(
				'email'      => 'new@example.com',
				'first_name' => 'New',
				'last_name'  => 'User',
				'status'     => 'active',
				'lists'      => 'Newsletter;Updates', // These should be ignored.
			),
		);

		// Mock no existing subscriber.
		$this->wpdb->shouldReceive( 'get_row' )
			->with( Mockery::pattern( '/mskd_subscribers.*email/i' ), Mockery::any() )
			->andReturn( null );

		// Mock list exists check for target list.
		$this->wpdb->shouldReceive( 'get_row' )
			->with( Mockery::pattern( '/mskd_lists.*id.*=.*5/i' ), Mockery::any() )
			->andReturn(
				(object) array(
					'id'   => 5,
					'name' => 'Target List',
				)
			);

		// Mock insert subscriber and list assignments.
		$this->wpdb->insert_id = 1;
		$this->wpdb->shouldReceive( 'insert' )
			->andReturn( 1 );

		// Mock delete existing list assignments.
		$this->wpdb->shouldReceive( 'delete' )
			->andReturn( true );

		$result = $this->service->import_subscribers(
			$rows,
			array( 'target_list_ids' => array( 5 ) )
		);

		$this->assertEquals( 1, $result['imported'] );
		$this->assertEquals( 0, $result['skipped'] );
		$this->assertEmpty( $result['errors'] );
	}

	/**
	 * Test import subscribers with multiple target_list_ids.
	 */
	public function test_import_subscribers_with_multiple_target_list_ids(): void {
		$rows = array(
			array(
				'email'      => 'multi@example.com',
				'first_name' => 'Multi',
				'last_name'  => 'List',
				'status'     => 'active',
				'lists'      => '',
			),
		);

		// Mock no existing subscriber.
		$this->wpdb->shouldReceive( 'get_row' )
			->with( Mockery::pattern( '/mskd_subscribers.*email/i' ), Mockery::any() )
			->andReturn( null );

		// Mock list exists check for target lists (ids 3 and 7).
		$this->wpdb->shouldReceive( 'get_row' )
			->with( Mockery::pattern( '/mskd_lists.*id.*=.*3/i' ), Mockery::any() )
			->andReturn(
				(object) array(
					'id'   => 3,
					'name' => 'List Three',
				)
			);

		$this->wpdb->shouldReceive( 'get_row' )
			->with( Mockery::pattern( '/mskd_lists.*id.*=.*7/i' ), Mockery::any() )
			->andReturn(
				(object) array(
					'id'   => 7,
					'name' => 'List Seven',
				)
			);

		// Mock insert subscriber.
		$this->wpdb->insert_id = 2;
		$this->wpdb->shouldReceive( 'insert' )
			->andReturn( 1 );

		// Mock delete existing list assignments.
		$this->wpdb->shouldReceive( 'delete' )
			->andReturn( true );

		$result = $this->service->import_subscribers(
			$rows,
			array( 'target_list_ids' => array( 3, 7 ) )
		);

		$this->assertEquals( 1, $result['imported'] );
	}

	/**
	 * Test import subscribers with empty target_list_ids uses CSV lists.
	 */
	public function test_import_subscribers_without_target_list_ids_uses_csv(): void {
		$rows = array(
			array(
				'email'      => 'csv@example.com',
				'first_name' => 'CSV',
				'last_name'  => 'User',
				'status'     => 'active',
				'lists'      => 'FromCSV',
			),
		);

		// Mock no existing subscriber.
		$this->wpdb->shouldReceive( 'get_row' )
			->with( Mockery::pattern( '/mskd_subscribers.*email/i' ), Mockery::any() )
			->andReturn( null );

		// Mock list lookup by name (for CSV list).
		$this->wpdb->shouldReceive( 'get_row' )
			->with( Mockery::pattern( '/mskd_lists.*name.*FromCSV/i' ), Mockery::any() )
			->andReturn(
				(object) array(
					'id'   => 10,
					'name' => 'FromCSV',
				)
			);

		// Mock insert subscriber.
		$this->wpdb->insert_id = 3;
		$this->wpdb->shouldReceive( 'insert' )
			->andReturn( 1 );

		// Mock delete existing list assignments.
		$this->wpdb->shouldReceive( 'delete' )
			->andReturn( true );

		$result = $this->service->import_subscribers(
			$rows,
			array(
				'target_list_ids' => array(), // Empty - should use CSV.
				'assign_lists'    => true,
			)
		);

		$this->assertEquals( 1, $result['imported'] );
	}

	/**
	 * Test import subscribers with invalid target_list_id ignores it.
	 */
	public function test_import_subscribers_filters_invalid_target_list_ids(): void {
		$rows = array(
			array(
				'email'      => 'invalid@example.com',
				'first_name' => 'Invalid',
				'last_name'  => 'List',
				'status'     => 'active',
				'lists'      => '',
			),
		);

		// Mock no existing subscriber.
		$this->wpdb->shouldReceive( 'get_row' )
			->with( Mockery::pattern( '/mskd_subscribers.*email/i' ), Mockery::any() )
			->andReturn( null );

		// Mock list does not exist (return null for id 999).
		$this->wpdb->shouldReceive( 'get_row' )
			->with( Mockery::pattern( '/mskd_lists.*id.*=.*999/i' ), Mockery::any() )
			->andReturn( null );

		// Mock insert subscriber.
		$this->wpdb->insert_id = 4;
		$this->wpdb->shouldReceive( 'insert' )
			->andReturn( 1 );

		$result = $this->service->import_subscribers(
			$rows,
			array( 'target_list_ids' => array( 999 ) ) // Invalid list ID.
		);

		// Should still import, just without list assignment.
		$this->assertEquals( 1, $result['imported'] );
	}

	/**
	 * Test import subscribers with target_list_ids and existing subscriber replaces lists.
	 */
	public function test_import_subscribers_existing_with_target_list_ids_replaces_lists(): void {
		$rows = array(
			array(
				'email'      => 'existing@example.com',
				'first_name' => 'Updated',
				'last_name'  => 'Name',
				'status'     => 'active',
				'lists'      => 'OldList', // Should be ignored when target_list_ids provided.
			),
		);

		// Mock get_row to return different results based on query.
		$this->wpdb->shouldReceive( 'get_row' )
			->andReturnUsing(
				function ( $query ) {
					// List lookup for target list validation.
					if ( strpos( $query, 'mskd_lists' ) !== false ) {
						return (object) array(
							'id'   => 8,
							'name' => 'New Target List',
						);
					}
					// Subscriber lookup.
					if ( strpos( $query, 'mskd_subscribers' ) !== false ) {
						return (object) array(
							'id'    => 1,
							'email' => 'existing@example.com',
						);
					}
					return null;
				}
			);

		// Mock update subscriber.
		$this->wpdb->shouldReceive( 'update' )
			->andReturn( 1 );

		// Mock delete existing list assignments (sync_lists replaces all).
		$this->wpdb->shouldReceive( 'delete' )
			->andReturn( true );

		// Mock insert new list assignment.
		$this->wpdb->insert_id = 1;
		$this->wpdb->shouldReceive( 'insert' )
			->andReturn( 1 );

		$result = $this->service->import_subscribers(
			$rows,
			array(
				'update_existing' => true,
				'target_list_ids' => array( 8 ),
			)
		);

		$this->assertEquals( 0, $result['imported'] );
		$this->assertEquals( 1, $result['updated'] );
		$this->assertEquals( 0, $result['skipped'] );
	}

	/**
	 * Test validate import file checks file size.
	 */
	public function test_validate_import_file_checks_size(): void {
		$file = array(
			'name'     => 'test.csv',
			'type'     => 'text/csv',
			'size'     => 6000000, // 6MB - exceeds limit.
			'tmp_name' => '/tmp/test.csv',
			'error'    => UPLOAD_ERR_OK,
		);

		$result = $this->service->validate_import_file( $file, 'csv' );

		$this->assertFalse( $result['valid'] );
		$this->assertStringContainsString( '5MB', $result['error'] );
	}

	/**
	 * Test validate import file checks extension.
	 */
	public function test_validate_import_file_checks_extension(): void {
		$file = array(
			'name'     => 'test.txt',
			'type'     => 'text/plain',
			'size'     => 1000,
			'tmp_name' => '/tmp/test.txt',
			'error'    => UPLOAD_ERR_OK,
		);

		$result = $this->service->validate_import_file( $file, 'csv' );

		$this->assertFalse( $result['valid'] );
		$this->assertStringContainsString( 'CSV', $result['error'] );
	}

	/**
	 * Test CSV handles UTF-8 BOM.
	 */
	public function test_parse_subscribers_csv_handles_utf8_bom(): void {
		// Create CSV with UTF-8 BOM.
		$csv_content  = "\xEF\xBB\xBFemail,first_name\n";
		$csv_content .= "test@example.com,Test\n";

		$temp_file = tempnam( sys_get_temp_dir(), 'mskd_test' );
		file_put_contents( $temp_file, $csv_content );

		$result = $this->service->parse_subscribers_csv( $temp_file );

		$this->assertTrue( $result['valid'] );
		$this->assertCount( 1, $result['rows'] );
		$this->assertEquals( 'test@example.com', $result['rows'][0]['email'] );

		unlink( $temp_file );
	}

	/**
	 * Clean up after each test.
	 */
	protected function tearDown(): void {
		parent::tearDown();
	}
}
