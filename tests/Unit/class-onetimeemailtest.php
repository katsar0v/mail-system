<?php
/**
 * One-Time Email Tests
 *
 * @package MSKD\Tests\Unit
 */

namespace MSKD\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;

/**
 * Class OneTimeEmailTest
 *
 * Tests for one-time email functionality in MSKD_Admin class.
 */
class OneTimeEmailTest extends TestCase {

	/**
	 * Admin instance.
	 *
	 * @var \MSKD_Admin
	 */
	protected $admin;

	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {
		parent::setUp();

		// Set up a base wpdb mock BEFORE creating admin class.
		// Services capture $wpdb in their constructors.
		$this->setup_wpdb_mock();
		$this->wpdb->shouldIgnoreMissing();

		// Load the admin class.
		require_once \MSKD_PLUGIN_DIR . 'admin/class-mskd-admin.php';

		$this->admin = new \MSKD_Admin();
	}

	/**
	 * Test that one-time email menu is registered.
	 */
	public function test_one_time_email_menu_registered(): void {
		Functions\expect( 'add_menu_page' )->once()->andReturn( 'mskd-dashboard' );
		// At least 1 submenu should be registered - we're just testing that the method runs without error
		Functions\expect( 'add_submenu_page' )->atLeast()->times( 1 )->andReturn( true );

		$this->admin->register_menu();

		// Verify the expectations were met (implicit via Mockery)
		$this->assertTrue( true );
	}

	/**
	 * Test one-time email validation rejects empty fields.
	 */
	public function test_one_time_email_validation_rejects_empty_fields(): void {
		$_POST = array(
			'mskd_send_one_time_email' => 1,
			'mskd_nonce'               => 'test_nonce',
			'recipient_email'          => '',
			'recipient_name'           => '',
			'subject'                  => '',
			'body'                     => '',
		);

		Functions\expect( 'wp_verify_nonce' )
			->once()
			->with( 'test_nonce', 'mskd_send_one_time_email' )
			->andReturn( true );

		// Called multiple times: once in main handle_actions() and once per controller.
		Functions\expect( 'current_user_can' )
			->atLeast()
			->times( 1 )
			->with( 'manage_options' )
			->andReturn( true );

		$error_called = false;
		Functions\expect( 'add_settings_error' )
			->once()
			->andReturnUsing(
				function ( $setting, $code, $message, $type ) use ( &$error_called ) {
					$error_called = true;
					$this->assertEquals( 'mskd_messages', $setting );
					$this->assertEquals( 'mskd_error', $code );
					$this->assertEquals( 'error', $type );
				}
			);

		$this->admin->handle_actions();
		$this->assertTrue( $error_called, 'add_settings_error should be called for empty fields' );
	}

	/**
	 * Test one-time email validation rejects invalid email.
	 * Note: is_email is stubbed in TestCase to use filter_var validation.
	 */
	public function test_one_time_email_validation_rejects_invalid_email(): void {
		$_POST = array(
			'mskd_send_one_time_email' => 1,
			'mskd_nonce'               => 'test_nonce',
			'recipient_email'          => 'not-an-email',
			'recipient_name'           => 'Test User',
			'subject'                  => 'Test Subject',
			'body'                     => 'Test Body',
		);

		Functions\expect( 'wp_verify_nonce' )
			->once()
			->with( 'test_nonce', 'mskd_send_one_time_email' )
			->andReturn( true );

		// Called multiple times: once in main handle_actions() and once per controller.
		Functions\expect( 'current_user_can' )
			->atLeast()
			->times( 1 )
			->with( 'manage_options' )
			->andReturn( true );

		$error_called = false;
		Functions\expect( 'add_settings_error' )
			->once()
			->andReturnUsing(
				function ( $setting, $code, $message, $type ) use ( &$error_called ) {
					$error_called = true;
					$this->assertEquals( 'mskd_messages', $setting );
					$this->assertEquals( 'mskd_error', $code );
					$this->assertEquals( 'error', $type );
				}
			);

		$this->admin->handle_actions();
		$this->assertTrue( $error_called, 'add_settings_error should be called for invalid email' );
	}

	/**
	 * Test one-time email sends successfully.
	 */
	public function test_one_time_email_sends_successfully(): void {
		// Use the wpdb mock set up in setUp().
		$wpdb = $this->wpdb;

		$_POST = array(
			'mskd_send_one_time_email' => 1,
			'mskd_nonce'               => 'test_nonce',
			'recipient_email'          => 'user@example.com',
			'recipient_name'           => 'Test User',
			'subject'                  => 'Hello {recipient_name}!',
			'body'                     => 'Dear {recipient_name}, your email is {recipient_email}. <a href="https://example.org/account?user=42">Account</a>',
		);

		Functions\expect( 'wp_verify_nonce' )
			->once()
			->with( 'test_nonce', 'mskd_send_one_time_email' )
			->andReturn( true );

		// Called multiple times: once in main handle_actions() and once per controller.
		Functions\expect( 'current_user_can' )
			->atLeast()
			->times( 1 )
			->with( 'manage_options' )
			->andReturn( true );

		// Use when() to handle multiple get_option calls (in admin class and SMTP mailer).
		Functions\when( 'get_option' )->alias(
			function ( $option, $default = false ) {
				if ( $option === 'mskd_settings' ) {
						return array(
							'smtp_enabled' => true,
							'smtp_host'    => 'smtp.example.com',
							'from_name'    => 'Test Site',
							'from_email'   => 'noreply@example.com',
							'reply_to'     => 'reply@example.com',
						);
				}
				return $default;
			}
		);

		// Note: SMTP mailer uses PHPMailer directly, not wp_mail.
		// The mock PHPMailer in bootstrap.php returns true from send().

		// Mock all get_row calls:
		// 1. replace_one_time_placeholders() checks if recipient is subscriber → null (not found)
		// 2. subscriber_service->get_by_email() in get_or_create() → null (not found)
		// 3. subscriber_service->get_by_id() after creating subscriber → subscriber object
		$call_count           = 0;
		$subscriber_insert_id = 100;
		$wpdb->shouldReceive( 'get_row' )
			->andReturnUsing(
				function () use ( &$call_count, $subscriber_insert_id ) {
					++$call_count;
					// First two calls return null (subscriber not found).
					if ( $call_count <= 2 ) {
							return null;
					}
					// Third call returns the newly created subscriber.
					return (object) array(
						'id'                => $subscriber_insert_id,
						'email'             => 'user@example.com',
						'first_name'        => 'Test User',
						'last_name'         => '',
						'status'            => 'active',
						'unsubscribe_token' => 'test_token_123',
					);
				}
			);

		// Mock subscriber creation (insert into subscribers table by get_or_create).
		$wpdb->shouldReceive( 'insert' )
			->once()
			->with(
				'wp_mskd_subscribers',
				Mockery::type( 'array' ),
				Mockery::type( 'array' )
			)
			->andReturnUsing(
				function () use ( $wpdb, $subscriber_insert_id ) {
					$wpdb->insert_id = $subscriber_insert_id;
					return 1;
				}
			);

		// Expect database inserts: campaigns table, then queue table.
		$wpdb->shouldReceive( 'insert' )
			->once()
			->with(
				'wp_mskd_campaigns',
				Mockery::type( 'array' ),
				Mockery::type( 'array' )
			)
			->andReturnUsing(
				function () use ( $wpdb ) {
					$wpdb->insert_id = 1;
					return 1;
				}
			);

		$wpdb->shouldReceive( 'insert' )
			->once()
			->with(
				'wp_mskd_queue',
				Mockery::on(
					function ( $data ) use ( $subscriber_insert_id ) {
						return $data['subscriber_id'] === $subscriber_insert_id
							&& $data['status'] === 'sent'
							&& strpos( $data['subject'], 'Test User' ) !== false;
					}
				),
				Mockery::type( 'array' )
			)
			->andReturn( 1 );

		$success_called = false;
		Functions\expect( 'add_settings_error' )
			->once()
			->andReturnUsing(
				function ( $setting, $code, $message, $type ) use ( &$success_called ) {
					$success_called = true;
					$this->assertEquals( 'mskd_messages', $setting );
					$this->assertEquals( 'mskd_success', $code );
					$this->assertEquals( 'success', $type );
				}
			);

		\PHPMailer\PHPMailer\PHPMailer::$lastBody = '';
		$this->admin->handle_actions();
		$this->assertTrue( $success_called, 'add_settings_error should be called for success' );
		$this->assertStringContainsString( 'mskd_track_click=', \PHPMailer\PHPMailer\PHPMailer::$lastBody );
		$this->assertStringContainsString( 'mskd_track_open=', \PHPMailer\PHPMailer\PHPMailer::$lastBody );
	}

	/**
	 * Test one-time email succeeds using PHP mail when SMTP is not configured.
	 *
	 * Note: Since we now allow sending emails without SMTP (using PHP mail as fallback),
	 * this test verifies that sending works even when SMTP is not configured.
	 * The mock PHPMailer always succeeds in send().
	 */
	public function test_one_time_email_logs_failure(): void {
		// wpdb mock already set up in setUp().
		$wpdb = $this->wpdb;

		$_POST = array(
			'mskd_send_one_time_email' => 1,
			'mskd_nonce'               => 'test_nonce',
			'recipient_email'          => 'user@example.com',
			'recipient_name'           => 'Test User',
			'subject'                  => 'Test Subject',
			'body'                     => 'Test Body',
		);

		Functions\expect( 'wp_verify_nonce' )
			->once()
			->with( 'test_nonce', 'mskd_send_one_time_email' )
			->andReturn( true );

		// Called multiple times: once in main handle_actions() and once per controller.
		Functions\expect( 'current_user_can' )
			->atLeast()
			->times( 1 )
			->with( 'manage_options' )
			->andReturn( true );

		// Return settings without SMTP configured (no host).
		Functions\when( 'get_option' )->alias(
			function ( $option, $default = false ) {
				if ( $option === 'mskd_settings' ) {
						return array(
							'smtp_enabled' => false,
							'smtp_host'    => '', // No SMTP - will use PHP mail fallback.
						);
				}
				return $default;
			}
		);

		// Mock subscriber lookup for placeholder replacement (recipient is NOT a subscriber).
		// This is called by replace_one_time_placeholders() in Admin_Email.
		// Then subscriber_service->get_or_create() also does lookups.
		$call_count           = 0;
		$subscriber_insert_id = 100;
		$wpdb->shouldReceive( 'get_row' )
			->andReturnUsing(
				function () use ( &$call_count, $subscriber_insert_id ) {
					++$call_count;
					// First two calls return null (subscriber not found).
					if ( $call_count <= 2 ) {
							return null;
					}
					// Third call returns the newly created subscriber.
					return (object) array(
						'id'                => $subscriber_insert_id,
						'email'             => 'user@example.com',
						'first_name'        => 'Test User',
						'last_name'         => '',
						'status'            => 'active',
						'unsubscribe_token' => 'test_token_123',
					);
				}
			);

		// Mock subscriber creation (insert into subscribers table by get_or_create).
		$wpdb->shouldReceive( 'insert' )
			->once()
			->with(
				'wp_mskd_subscribers',
				Mockery::type( 'array' ),
				Mockery::type( 'array' )
			)
			->andReturnUsing(
				function () use ( $wpdb, $subscriber_insert_id ) {
					$wpdb->insert_id = $subscriber_insert_id;
					return 1;
				}
			);

		// Expect database insert for campaigns table.
		$wpdb->shouldReceive( 'insert' )
			->once()
			->with(
				'wp_mskd_campaigns',
				Mockery::type( 'array' ),
				Mockery::type( 'array' )
			)
			->andReturnUsing(
				function () use ( $wpdb ) {
					$wpdb->insert_id = 1;
					return 1;
				}
			);

		// Expect database insert for logging the sent email.
		$wpdb->shouldReceive( 'insert' )
			->once()
			->with(
				'wp_mskd_queue',
				\Mockery::type( 'array' ),
				\Mockery::type( 'array' )
			)
			->andReturn( true );

		// Should show success message since PHP mail fallback works.
		$success_called = false;
		Functions\expect( 'add_settings_error' )
			->once()
			->andReturnUsing(
				function ( $setting, $code, $message, $type ) use ( &$success_called ) {
					$success_called = true;
					$this->assertEquals( 'mskd_messages', $setting );
					$this->assertEquals( 'mskd_success', $code );
					$this->assertEquals( 'success', $type );
				}
			);

		$this->admin->handle_actions();
		$this->assertTrue( $success_called, 'add_settings_error should be called for success' );
	}

	/**
	 * Test placeholder replacement in one-time email.
	 */
	public function test_placeholder_replacement_in_one_time_email(): void {
		// Use the wpdb mock set up in setUp().
		$wpdb = $this->wpdb;

		$_POST = array(
			'mskd_send_one_time_email' => 1,
			'mskd_nonce'               => 'test_nonce',
			'recipient_email'          => 'john@example.com',
			'recipient_name'           => 'John Doe',
			'subject'                  => 'Welcome {recipient_name}',
			'body'                     => 'Hello {recipient_name}, your email is {recipient_email}.',
		);

		Functions\expect( 'wp_verify_nonce' )
			->once()
			->andReturn( true );

		// Called multiple times: once in main handle_actions() and once per controller.
		Functions\expect( 'current_user_can' )
			->atLeast()
			->times( 1 )
			->andReturn( true );

		// Use when() to handle multiple get_option calls.
		Functions\when( 'get_option' )->alias(
			function ( $option, $default = false ) {
				if ( $option === 'mskd_settings' ) {
						return array(
							'smtp_enabled' => true,
							'smtp_host'    => 'smtp.example.com',
							'from_name'    => 'Test',
							'from_email'   => 'test@example.com',
							'reply_to'     => 'reply@example.com',
						);
				}
				return $default;
			}
		);

		// Note: SMTP mailer uses PHPMailer directly, not wp_mail.
		// Capture the sent email content from the database insert.
		$sent_subject = '';
		$sent_body    = '';

		// Mock all get_row calls:
		// 1. replace_one_time_placeholders() checks if recipient is subscriber → null (not found)
		// 2. subscriber_service->get_by_email() in get_or_create() → null (not found)
		// 3. subscriber_service->get_by_id() after creating subscriber → subscriber object
		$call_count           = 0;
		$subscriber_insert_id = 100;
		$wpdb->shouldReceive( 'get_row' )
			->andReturnUsing(
				function () use ( &$call_count, $subscriber_insert_id ) {
					++$call_count;
					// First two calls return null (subscriber not found).
					if ( $call_count <= 2 ) {
							return null;
					}
					// Third call returns the newly created subscriber.
					return (object) array(
						'id'                => $subscriber_insert_id,
						'email'             => 'john@example.com',
						'first_name'        => 'John Doe',
						'last_name'         => '',
						'status'            => 'active',
						'unsubscribe_token' => 'test_token_123',
					);
				}
			);

		// Mock subscriber creation (insert into subscribers table by get_or_create).
		$wpdb->shouldReceive( 'insert' )
			->once()
			->with(
				'wp_mskd_subscribers',
				Mockery::type( 'array' ),
				Mockery::type( 'array' )
			)
			->andReturnUsing(
				function () use ( $wpdb, $subscriber_insert_id ) {
					$wpdb->insert_id = $subscriber_insert_id;
					return 1;
				}
			);

		// First insert is to campaigns table.
		$wpdb->shouldReceive( 'insert' )
			->once()
			->with(
				'wp_mskd_campaigns',
				Mockery::type( 'array' ),
				Mockery::type( 'array' )
			)
			->andReturnUsing(
				function () use ( $wpdb ) {
					$wpdb->insert_id = 1;
					return 1;
				}
			);

		// Second insert is to queue table - capture the sent content.
		$wpdb->shouldReceive( 'insert' )
			->once()
			->with(
				'wp_mskd_queue',
				Mockery::on(
					function ( $data ) use ( &$sent_subject, &$sent_body ) {
						$sent_subject = $data['subject'];
						$sent_body    = $data['body'];
						return true;
					}
				),
				Mockery::type( 'array' )
			)
			->andReturn( 1 );

		Functions\expect( 'add_settings_error' )
			->once();

		$this->admin->handle_actions();

		// Verify placeholders are replaced.
		$this->assertStringContainsString( 'John Doe', $sent_subject, 'Subject should contain recipient name' );
		$this->assertStringContainsString( 'John Doe', $sent_body, 'Body should contain recipient name' );
		$this->assertStringContainsString( 'john@example.com', $sent_body, 'Body should contain recipient email' );
	}

	/**
	 * Test that header and footer are applied to immediate one-time emails.
	 *
	 * This test verifies that the Admin_Email class correctly uses the
	 * Email_Header_Footer trait's apply_header_footer method. The method
	 * itself is thoroughly tested in EmailHeaderFooterTest.
	 */
	public function test_one_time_email_applies_header_footer(): void {
		// Load Admin_Email to test trait integration.
		require_once \MSKD_PLUGIN_DIR . 'includes/Admin/class-admin-email.php';

		$admin_email = new \MSKD\Admin\Admin_Email();

		// Test that Admin_Email class has the apply_header_footer method from trait.
		$this->assertTrue(
			method_exists( $admin_email, 'apply_header_footer' ),
			'Admin_Email should have apply_header_footer method from trait'
		);

		// Test the method works correctly (trait integration test).
		$content  = '<p>Main email content</p>';
		$settings = array(
			'email_header' => '<div class="header">Company Header</div>',
			'email_footer' => '<div class="footer">Company Footer</div>',
		);

		$result = $admin_email->apply_header_footer( $content, $settings );

		$this->assertStringContainsString( '<div class="header">Company Header</div>', $result );
		$this->assertStringContainsString( '<p>Main email content</p>', $result );
		$this->assertStringContainsString( '<div class="footer">Company Footer</div>', $result );
	}

	/**
	 * Test that empty header and footer don't modify content.
	 *
	 * This test verifies that the Admin_Email class correctly handles
	 * empty header/footer settings via the Email_Header_Footer trait.
	 */
	public function test_one_time_email_with_empty_header_footer(): void {
		// Load Admin_Email to test trait integration.
		require_once \MSKD_PLUGIN_DIR . 'includes/Admin/class-admin-email.php';

		$admin_email = new \MSKD\Admin\Admin_Email();

		$content  = '<p>Main email content</p>';
		$settings = array(
			'email_header' => '',
			'email_footer' => '',
		);

		$result = $admin_email->apply_header_footer( $content, $settings );

		$this->assertEquals(
			$content,
			$result,
			'Content should remain unchanged when header and footer are empty'
		);
	}

	/**
	 * Clean up after each test.
	 */
	protected function tearDown(): void {
		unset( $_POST['mskd_send_one_time_email'] );
		unset( $_POST['mskd_nonce'] );
		unset( $_POST['recipient_email'] );
		unset( $_POST['recipient_name'] );
		unset( $_POST['subject'] );
		unset( $_POST['body'] );
		unset( $_POST['schedule_type'] );
		unset( $_POST['scheduled_datetime'] );
		unset( $_POST['delay_value'] );
		unset( $_POST['delay_unit'] );

		parent::tearDown();
	}
}
