<?php
/**
 * Activator Tests
 *
 * @package MSKD\Tests\Unit
 */

namespace MSKD\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;

/**
 * Class ActivatorTest
 *
 * Tests for MSKD_Activator class.
 */
class ActivatorTest extends TestCase {

	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {
		parent::setUp();

		// Load the activator class.
		require_once \MSKD_PLUGIN_DIR . 'includes/class-activator.php';
	}

	/**
	 * Set up common get_option mock that returns false for mskd_settings.
	 *
	 * @param mixed $mskd_settings_value Value to return for mskd_settings option.
	 */
	protected function setup_get_option_mock( $mskd_settings_value = false ): void {
		Functions\when( 'get_option' )->alias(
			function ( $option, $default_value = false ) use ( $mskd_settings_value ) {
				if ( $option === 'mskd_settings' ) {
						return $mskd_settings_value;
				}
				return $default_value;
			}
		);
	}

	/**
	 * Test that activation calls dbDelta for all required tables.
	 */
	public function test_tables_created_on_activation(): void {
		$wpdb = $this->setup_wpdb_mock();
		$wpdb->shouldReceive( 'get_charset_collate' )
			->once()
			->andReturn( 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci' );

		// Track dbDelta calls.
		$db_delta_calls = array();
		Functions\when( 'dbDelta' )->alias(
			function ( $sql ) use ( &$db_delta_calls ) {
				$db_delta_calls[] = $sql;
				return array();
			}
		);

		// Mock other required functions using when() to override stubs.
		Functions\when( 'wp_next_scheduled' )->justReturn( false );
		Functions\when( 'wp_schedule_event' )->justReturn( true );
		Functions\when( 'flush_rewrite_rules' )->justReturn( null );

		// Override get_option to return false for mskd_settings.
		$this->setup_get_option_mock( false );

		// Track update_option calls.
		$update_option_calls = array();
		Functions\when( 'update_option' )->alias(
			function ( $option, $value ) use ( &$update_option_calls ) {
				$update_option_calls[] = array(
					'option' => $option,
					'value'  => $value,
				);
				return true;
			}
		);

		// Run activation.
		\MSKD_Activator::activate();

		// Verify all 6 tables are created.
		$this->assertCount( 6, $db_delta_calls, 'Should call dbDelta 6 times for 6 tables' );

		// Check table names in SQL.
		$all_sql = implode( ' ', $db_delta_calls );
		$this->assertStringContainsString( 'wp_mskd_subscribers', $all_sql, 'Should create subscribers table' );
		$this->assertStringContainsString( 'wp_mskd_lists', $all_sql, 'Should create lists table' );
		$this->assertStringContainsString( 'wp_mskd_subscriber_list', $all_sql, 'Should create subscriber_list pivot table' );
		$this->assertStringContainsString( 'wp_mskd_campaigns', $all_sql, 'Should create campaigns table' );
		$this->assertStringContainsString( 'wp_mskd_queue', $all_sql, 'Should create queue table' );
		$this->assertStringContainsString( 'wp_mskd_templates', $all_sql, 'Should create templates table' );
		$this->assertStringContainsString( 'tracking_token varchar(64)', $all_sql, 'Queue should include a tracking token' );
		$this->assertStringContainsString( 'opened_at datetime', $all_sql, 'Queue should include first-open timestamp' );
		$this->assertStringContainsString( 'open_count int(11) UNSIGNED', $all_sql, 'Queue should include open count' );
		$this->assertStringContainsString( 'UNIQUE KEY tracking_token', $all_sql, 'Tracking token should be unique' );

		// Verify update_option was called.
		$this->assertCount( 2, $update_option_calls, 'Should call update_option twice' );
	}

	/**
	 * Test that cron is scheduled on activation.
	 */
	public function test_cron_scheduled_on_activation(): void {
		$wpdb = $this->setup_wpdb_mock();
		$wpdb->shouldReceive( 'get_charset_collate' )
			->andReturn( 'DEFAULT CHARACTER SET utf8mb4' );

		Functions\when( 'dbDelta' )->justReturn( array() );
		Functions\when( 'flush_rewrite_rules' )->justReturn( null );
		Functions\when( 'update_option' )->justReturn( true );

		// Cron not yet scheduled.
		Functions\when( 'wp_next_scheduled' )->justReturn( false );

		// Track wp_schedule_event call.
		$schedule_event_called = false;
		Functions\when( 'wp_schedule_event' )->alias(
			function ( $timestamp, $recurrence, $hook ) use ( &$schedule_event_called ) {
				$schedule_event_called = true;
				$this->assertIsInt( $timestamp );
				$this->assertEquals( 'mskd_every_minute', $recurrence );
				$this->assertEquals( 'mskd_process_queue', $hook );
				return true;
			}
		);

		// Override get_option.
		$this->setup_get_option_mock( false );

		\MSKD_Activator::activate();

		$this->assertTrue( $schedule_event_called, 'wp_schedule_event should be called when cron not scheduled' );
	}

	/**
	 * Test that cron is not re-scheduled if already exists.
	 */
	public function test_cron_not_rescheduled_if_exists(): void {
		$wpdb = $this->setup_wpdb_mock();
		$wpdb->shouldReceive( 'get_charset_collate' )
			->andReturn( 'DEFAULT CHARACTER SET utf8mb4' );

		Functions\when( 'dbDelta' )->justReturn( array() );
		Functions\when( 'flush_rewrite_rules' )->justReturn( null );
		Functions\when( 'update_option' )->justReturn( true );

		// Cron already scheduled.
		Functions\when( 'wp_next_scheduled' )->justReturn( 1234567890 );

		// Should NOT schedule the cron event again - track if it's called.
		$schedule_event_called = false;
		Functions\when( 'wp_schedule_event' )->alias(
			function () use ( &$schedule_event_called ) {
				$schedule_event_called = true;
				return true;
			}
		);

		// Override get_option.
		$this->setup_get_option_mock( false );

		\MSKD_Activator::activate();

		$this->assertFalse( $schedule_event_called, 'wp_schedule_event should NOT be called when cron already scheduled' );
	}

	/**
	 * Test that default options are set on activation.
	 */
	public function test_default_options_set(): void {
		$wpdb = $this->setup_wpdb_mock();
		$wpdb->shouldReceive( 'get_charset_collate' )
			->andReturn( 'DEFAULT CHARACTER SET utf8mb4' );

		Functions\when( 'dbDelta' )->justReturn( array() );
		Functions\when( 'flush_rewrite_rules' )->justReturn( null );
		Functions\when( 'wp_next_scheduled' )->justReturn( 1234567890 );

		// No existing settings.
		$this->setup_get_option_mock( false );

		// Track update_option calls.
		$saved_settings = null;
		Functions\when( 'update_option' )->alias(
			function ( $option, $value ) use ( &$saved_settings ) {
				if ( $option === 'mskd_settings' ) {
						$saved_settings = $value;
				}
				return true;
			}
		);

		\MSKD_Activator::activate();

		// Verify default settings structure.
		$this->assertIsArray( $saved_settings );
		$this->assertArrayHasKey( 'from_name', $saved_settings );
		$this->assertArrayHasKey( 'from_email', $saved_settings );
		$this->assertArrayHasKey( 'reply_to', $saved_settings );
	}

	/**
	 * Test that existing options are not overwritten.
	 */
	public function test_existing_options_not_overwritten(): void {
		$wpdb = $this->setup_wpdb_mock();
		$wpdb->shouldReceive( 'get_charset_collate' )
			->andReturn( 'DEFAULT CHARACTER SET utf8mb4' );

		Functions\when( 'dbDelta' )->justReturn( array() );
		Functions\when( 'flush_rewrite_rules' )->justReturn( null );
		Functions\when( 'wp_next_scheduled' )->justReturn( 1234567890 );

		// Existing settings already present.
		$existing_settings = array(
			'from_name'  => 'Custom Name',
			'from_email' => 'custom@example.com',
			'reply_to'   => 'reply@example.com',
		);

		$this->setup_get_option_mock( $existing_settings );

		// Track update_option calls for mskd_settings.
		$mskd_settings_updated = false;
		Functions\when( 'update_option' )->alias(
			function ( $option, $value ) use ( &$mskd_settings_updated ) {
				if ( $option === 'mskd_settings' ) {
						$mskd_settings_updated = true;
				}
				return true;
			}
		);

		\MSKD_Activator::activate();

		// Verify mskd_settings was NOT updated (only db_version should be).
		$this->assertFalse( $mskd_settings_updated, 'mskd_settings should NOT be updated when already exists' );
	}

	/**
	 * Test that database version is stored.
	 */
	public function test_db_version_stored(): void {
		$wpdb = $this->setup_wpdb_mock();
		$wpdb->shouldReceive( 'get_charset_collate' )
			->andReturn( 'DEFAULT CHARACTER SET utf8mb4' );

		Functions\when( 'dbDelta' )->justReturn( array() );
		Functions\when( 'flush_rewrite_rules' )->justReturn( null );
		Functions\when( 'wp_next_scheduled' )->justReturn( 1234567890 );

		$this->setup_get_option_mock( array() ); // Existing settings.

		// Track update_option call for db_version.
		$db_version_stored = null;
		Functions\when( 'update_option' )->alias(
			function ( $option, $value ) use ( &$db_version_stored ) {
				if ( $option === 'mskd_db_version' ) {
						$db_version_stored = $value;
				}
				return true;
			}
		);

		\MSKD_Activator::activate();

		$this->assertEquals( \MSKD_Activator::DB_VERSION, $db_version_stored, 'Database version should be stored' );
	}
}
