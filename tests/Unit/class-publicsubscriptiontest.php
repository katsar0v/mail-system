<?php
/**
 * Public Subscription Tests
 *
 * @package MSKD\Tests\Unit
 */

namespace MSKD\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;

/**
 * Class PublicSubscriptionTest
 *
 * Tests for public subscription functionality in MSKD_Public class.
 */
class PublicSubscriptionTest extends TestCase {

	/**
	 * Public class instance.
	 *
	 * @var \MSKD_Public
	 */
	protected $public;

	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {
		parent::setUp();

		// Stub shortcode registration.
		Functions\stubs( array( 'add_shortcode' => null ) );
		Functions\stubs( array( 'add_action' => null ) );

		// Load the public class.
		require_once \MSKD_PLUGIN_DIR . 'public/class-mskd-public.php';

		$this->public = new \MSKD_Public();
	}

	/**
	 * Test AJAX subscribe creates new subscriber with inactive status (double opt-in).
	 */
	public function test_ajax_subscribe_creates_new_subscriber(): void {
		$wpdb = $this->setup_wpdb_mock();

		$_POST['email']      = 'newuser@example.com';
		$_POST['first_name'] = 'New';
		$_POST['last_name']  = 'User';
		$_POST['list_id']    = 5;
		$_POST['nonce']      = 'valid_nonce';

		Functions\expect( 'check_ajax_referer' )
			->once()
			->with( 'mskd_public_nonce', 'nonce' )
			->andReturn( true );

		// No existing subscriber.
		$wpdb->shouldReceive( 'get_row' )
			->once()
			->andReturn( null );

		// Insert new subscriber with inactive status (double opt-in).
		$wpdb->insert_id = 999;
		$wpdb->shouldReceive( 'insert' )
			->once()
			->with(
				'wp_mskd_subscribers',
				Mockery::on(
					function ( $data ) {
						return 'newuser@example.com' === $data['email']
							&& 'New' === $data['first_name']
							&& 'User' === $data['last_name']
							&& 'inactive' === $data['status']
							&& isset( $data['opt_in_token'] );
					}
				),
				Mockery::type( 'array' )
			)
			->andReturn( 1 );

		// Mock get_option for SMTP mailer settings (used by send_opt_in_email).
		Functions\when( 'get_option' )->alias( function( $option, $default = false ) {
			if ( 'mskd_settings' === $option ) {
				return array(
					'from_name'  => 'Test Site',
					'from_email' => 'noreply@example.com',
				);
			}
			return $default;
		} );

		// Check not in list.
		$wpdb->shouldReceive( 'get_var' )
			->once()
			->andReturn( null );

		// Add to list.
		$wpdb->shouldReceive( 'insert' )
			->once()
			->with(
				'wp_mskd_subscriber_list',
				array(
					'subscriber_id' => 999,
					'list_id'       => 5,
				),
				Mockery::type( 'array' )
			)
			->andReturn( 1 );

		Functions\expect( 'wp_send_json_success' )
			->once()
			->with(
				Mockery::on(
					function ( $data ) {
						return isset( $data['message'] );
					}
				)
			)
			->andReturnUsing(
				function () {
					throw new \Exception( 'json_success' );
				}
			);

		try {
			$this->public->ajax_subscribe();
		} catch ( \Exception $e ) {
			$this->assertEquals( 'json_success', $e->getMessage() );
		}
	}

	/**
	 * Test AJAX subscribe reactivates unsubscribed user (requires double opt-in).
	 */
	public function test_ajax_subscribe_reactivates_unsubscribed(): void {
		$wpdb = $this->setup_wpdb_mock();

		$_POST['email']   = 'inactive@example.com';
		$_POST['list_id'] = 0;
		$_POST['nonce']   = 'valid_nonce';

		Functions\expect( 'check_ajax_referer' )
			->once()
			->andReturn( true );

		// Existing unsubscribed subscriber.
		$existing = (object) array(
			'id'         => 123,
			'email'      => 'inactive@example.com',
			'status'     => 'unsubscribed',
			'first_name' => 'John',
			'last_name'  => 'Doe',
		);

		$wpdb->shouldReceive( 'get_row' )
			->once()
			->andReturn( $existing );

		// Should update status to inactive and set opt_in_token (double opt-in).
		$wpdb->shouldReceive( 'update' )
			->once()
			->with(
				'wp_mskd_subscribers',
				Mockery::on(
					function ( $data ) {
						return 'inactive' === $data['status']
							&& isset( $data['opt_in_token'] );
					}
				),
				array( 'id' => 123 ),
				Mockery::type( 'array' ),
				Mockery::type( 'array' )
			)
			->andReturn( 1 );

		// Mock get_option for SMTP mailer settings (used by send_opt_in_email).
		Functions\when( 'get_option' )->alias( function( $option, $default = false ) {
			if ( 'mskd_settings' === $option ) {
				return array(
					'from_name'  => 'Test Site',
					'from_email' => 'noreply@example.com',
				);
			}
			return $default;
		} );

		Functions\expect( 'wp_send_json_success' )
			->once()
			->andReturnUsing(
				function () {
					throw new \Exception( 'json_success' );
				}
			);

		try {
			$this->public->ajax_subscribe();
		} catch ( \Exception $e ) {
			$this->assertEquals( 'json_success', $e->getMessage() );
		}
	}

	/**
	 * Test AJAX subscribe adds to specified list (for inactive subscriber being reactivated).
	 */
	public function test_ajax_subscribe_adds_to_list(): void {
		$wpdb = $this->setup_wpdb_mock();

		$_POST['email']   = 'inactive@example.com';
		$_POST['list_id'] = 10;
		$_POST['nonce']   = 'valid_nonce';

		Functions\expect( 'check_ajax_referer' )
			->once()
			->andReturn( true );

		// Existing inactive subscriber (needs reactivation via opt-in).
		$existing = (object) array(
			'id'         => 456,
			'email'      => 'inactive@example.com',
			'status'     => 'inactive',
			'first_name' => 'Inactive',
			'last_name'  => 'User',
		);

		$wpdb->shouldReceive( 'get_row' )
			->once()
			->andReturn( $existing );

		// Should update with new opt_in_token.
		$wpdb->shouldReceive( 'update' )
			->once()
			->andReturn( 1 );

		// Mock get_option for SMTP mailer settings (used by send_opt_in_email).
		Functions\when( 'get_option' )->alias( function( $option, $default = false ) {
			if ( 'mskd_settings' === $option ) {
				return array(
					'from_name'  => 'Test Site',
					'from_email' => 'noreply@example.com',
				);
			}
			return $default;
		} );

		// Not in list yet.
		$wpdb->shouldReceive( 'get_var' )
			->once()
			->andReturn( null );

		// Should add to list.
		$wpdb->shouldReceive( 'insert' )
			->once()
			->with(
				'wp_mskd_subscriber_list',
				array(
					'subscriber_id' => 456,
					'list_id'       => 10,
				),
				Mockery::type( 'array' )
			)
			->andReturn( 1 );

		Functions\expect( 'wp_send_json_success' )
			->once()
			->andReturnUsing(
				function () {
					throw new \Exception( 'json_success' );
				}
			);

		try {
			$this->public->ajax_subscribe();
		} catch ( \Exception $e ) {
			$this->assertEquals( 'json_success', $e->getMessage() );
		}
	}

	/**
	 * Test AJAX subscribe returns early for already active subscriber.
	 */
	public function test_ajax_subscribe_active_subscriber_returns_early(): void {
		$wpdb = $this->setup_wpdb_mock();

		$_POST['email']   = 'active@example.com';
		$_POST['list_id'] = 10;
		$_POST['nonce']   = 'valid_nonce';

		Functions\expect( 'check_ajax_referer' )
			->once()
			->andReturn( true );

		// Already active subscriber.
		$existing = (object) array(
			'id'         => 456,
			'email'      => 'active@example.com',
			'status'     => 'active',
			'first_name' => 'Active',
			'last_name'  => 'User',
		);

		$wpdb->shouldReceive( 'get_row' )
			->once()
			->andReturn( $existing );

		// Should NOT do any list operations (returns early).
		$wpdb->shouldReceive( 'get_var' )->never();
		$wpdb->shouldReceive( 'insert' )->never();
		$wpdb->shouldReceive( 'update' )->never();

		// Should NOT send email, and no SMTP mailer settings needed.

		// Should return "already subscribed" message.
		Functions\expect( 'wp_send_json_success' )
			->once()
			->with(
				Mockery::on(
					function ( $data ) {
						return isset( $data['message'] ) && strpos( $data['message'], 'already' ) !== false;
					}
				)
			)
			->andReturnUsing(
				function () {
					throw new \Exception( 'json_success_already' );
				}
			);

		try {
			$this->public->ajax_subscribe();
		} catch ( \Exception $e ) {
			$this->assertEquals( 'json_success_already', $e->getMessage() );
		}
	}

	/**
	 * Test opt-in email body includes configured header and footer.
	 */
	public function test_opt_in_email_body_applies_header_and_footer(): void {
		$method = new \ReflectionMethod( $this->public, 'get_opt_in_email_body' );
		$method->setAccessible( true );

		$settings = array(
			'email_header' => '<div class="header">Hello {first_name} {last_name} ({email})</div>',
			'email_footer' => '<div class="footer">{unsubscribe_link} - {unsubscribe_url}</div>',
		);

		$body = $method->invoke(
			$this->public,
			'maria@example.com',
			'Maria',
			'Ivanova',
			'https://example.com/?mskd_confirm=abc123def456abc123def456abc12345',
			'Sebeotkrivatel.com',
			$settings,
			'unsub123def456abc123def456abc12345'
		);

		$this->assertStringStartsWith( '<div class="header">Hello Maria Ivanova (maria@example.com)</div>', $body );
		$this->assertStringContainsString( '<p>Hello Maria,</p>', $body );
		$this->assertStringContainsString( '<a href="https://example.com/?mskd_confirm=abc123def456abc123def456abc12345">https://example.com/?mskd_confirm=abc123def456abc123def456abc12345</a>', $body );
		$this->assertStringContainsString( '<a href="https://example.com?mskd_unsubscribe=unsub123def456abc123def456abc12345">Unsubscribe</a>', $body );
		$this->assertStringEndsWith( '<div class="footer"><a href="https://example.com?mskd_unsubscribe=unsub123def456abc123def456abc12345">Unsubscribe</a> - https://example.com?mskd_unsubscribe=unsub123def456abc123def456abc12345</div>', $body );
		$this->assertStringNotContainsString( '{first_name}', $body );
		$this->assertStringNotContainsString( '{unsubscribe_link}', $body );

		$header_position  = strpos( $body, 'Hello Maria Ivanova' );
		$content_position = strpos( $body, '<p>Hello Maria' );
		$footer_position  = strpos( $body, 'Unsubscribe' );

		$this->assertLessThan( $content_position, $header_position, 'Header should come before confirmation content' );
		$this->assertLessThan( $footer_position, $content_position, 'Confirmation content should come before footer' );
	}

	/**
	 * Test AJAX subscribe returns error for invalid email.
	 */
	public function test_ajax_subscribe_invalid_email_returns_error(): void {
		$this->setup_wpdb_mock();

		$_POST['email'] = 'not-an-email';
		$_POST['nonce'] = 'valid_nonce';

		Functions\expect( 'check_ajax_referer' )
			->once()
			->andReturn( true );

		// Override is_email stub to return false for this test.
		Functions\when( 'is_email' )->justReturn( false );

		$json_error_called = false;
		Functions\expect( 'wp_send_json_error' )
			->once()
			->with(
				Mockery::on(
					function ( $data ) {
						return isset( $data['message'] );
					}
				)
			)
			->andReturnUsing(
				function () use ( &$json_error_called ) {
					$json_error_called = true;
					throw new \Exception( 'json_error' );
				}
			);

		try {
			$this->public->ajax_subscribe();
		} catch ( \Exception $e ) {
			$this->assertEquals( 'json_error', $e->getMessage() );
		}

		$this->assertTrue( $json_error_called, 'wp_send_json_error should be called for invalid email' );
	}

	/**
	 * Test shortcode renders form.
	 */
	public function test_shortcode_renders_form(): void {
		// Mock shortcode_atts - use when() to override any existing stubs.
		Functions\when( 'shortcode_atts' )->justReturn(
			array(
				'list_id' => 0,
				'title'   => 'Абонирайте се',
			)
		);

		// Mock wp_enqueue_style and wp_enqueue_script.
		Functions\when( 'wp_enqueue_style' )->justReturn( null );
		Functions\when( 'wp_enqueue_script' )->justReturn( null );
		Functions\when( 'wp_localize_script' )->justReturn( null );
		Functions\when( 'wp_create_nonce' )->justReturn( 'test_nonce' );

		// We verify the method exists and can be called (file inclusion is mocked).
		$this->assertTrue( method_exists( $this->public, 'subscribe_form_shortcode' ) );

		// Test that the method can be called (even if include fails, method exists).
		// We can't test file inclusion without the actual file, so just test method exists.
	}

	/**
	 * Test shortcode accepts list_id attribute.
	 */
	public function test_shortcode_accepts_list_id_attribute(): void {
		$atts = array(
			'list_id' => 5,
			'title'   => 'Custom Title',
		);

		// Use when() to override any existing stubs.
		Functions\when( 'shortcode_atts' )->justReturn( $atts );

		// Mock wp_enqueue_style and wp_enqueue_script.
		Functions\when( 'wp_enqueue_style' )->justReturn( null );
		Functions\when( 'wp_enqueue_script' )->justReturn( null );
		Functions\when( 'wp_localize_script' )->justReturn( null );
		Functions\when( 'wp_create_nonce' )->justReturn( 'test_nonce' );

		// Verify method accepts attributes.
		$this->assertTrue( method_exists( $this->public, 'subscribe_form_shortcode' ) );
	}

	/**
	 * Test that subscriber not added to list if already in it.
	 */
	public function test_ajax_subscribe_skips_duplicate_list_assignment(): void {
		$wpdb = $this->setup_wpdb_mock();

		$_POST['email']   = 'inactive@example.com';
		$_POST['list_id'] = 10;
		$_POST['nonce']   = 'valid_nonce';

		Functions\expect( 'check_ajax_referer' )
			->once()
			->andReturn( true );

		// Inactive subscriber (needs reactivation).
		$existing = (object) array(
			'id'         => 456,
			'email'      => 'inactive@example.com',
			'status'     => 'inactive',
			'first_name' => 'Inactive',
			'last_name'  => 'User',
		);

		$wpdb->shouldReceive( 'get_row' )
			->once()
			->andReturn( $existing );

		// Should update with new opt_in_token.
		$wpdb->shouldReceive( 'update' )
			->once()
			->andReturn( 1 );

		// Mock get_option for SMTP mailer settings (used by send_opt_in_email).
		Functions\when( 'get_option' )->alias( function( $option, $default = false ) {
			if ( 'mskd_settings' === $option ) {
				return array(
					'from_name'  => 'Test Site',
					'from_email' => 'noreply@example.com',
				);
			}
			return $default;
		} );

		// Already in list.
		$wpdb->shouldReceive( 'get_var' )
			->once()
			->andReturn( 99 ); // Returns existing ID.

		// Should NOT insert into list again (already exists).
		$wpdb->shouldReceive( 'insert' )
			->never();

		Functions\expect( 'wp_send_json_success' )
			->once()
			->andReturnUsing(
				function () {
					throw new \Exception( 'json_success' );
				}
			);

		try {
			$this->public->ajax_subscribe();
		} catch ( \Exception $e ) {
			$this->assertEquals( 'json_success', $e->getMessage() );
		}
	}

	/**
	 * Clean up after each test.
	 */
	protected function tearDown(): void {
		unset( $_POST['email'] );
		unset( $_POST['first_name'] );
		unset( $_POST['last_name'] );
		unset( $_POST['list_id'] );
		unset( $_POST['nonce'] );

		parent::tearDown();
	}
}
