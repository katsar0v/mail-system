<?php
/**
 * SMTP Mailer Tests
 *
 * @package MSKD\Tests\Unit
 */

namespace MSKD\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;

/**
 * Class SmtpMailerTest
 *
 * Tests for MSKD_SMTP_Mailer class.
 */
class SmtpMailerTest extends TestCase {

	/**
	 * SMTP Mailer instance.
	 *
	 * @var \MSKD_SMTP_Mailer
	 */
	protected $smtp_mailer;

	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {
		parent::setUp();

		// Load the SMTP mailer class.
		require_once \MSKD_PLUGIN_DIR . 'includes/services/class-mskd-smtp-mailer.php';
	}

	/**
	 * Test that is_smtp_enabled returns false when SMTP is disabled.
	 */
	public function test_is_enabled_returns_false_when_disabled(): void {
		$settings = array(
			'smtp_enabled' => false,
			'smtp_host'    => 'smtp.example.com',
		);

		$this->smtp_mailer = new \MSKD_SMTP_Mailer( $settings );

		$this->assertFalse( $this->smtp_mailer->is_smtp_enabled() );
	}

	/**
	 * Test that is_smtp_enabled returns false when host is empty.
	 */
	public function test_is_enabled_returns_false_when_host_empty(): void {
		$settings = array(
			'smtp_enabled' => true,
			'smtp_host'    => '',
		);

		$this->smtp_mailer = new \MSKD_SMTP_Mailer( $settings );

		$this->assertFalse( $this->smtp_mailer->is_smtp_enabled() );
	}

	/**
	 * Test that is_enabled returns true when properly configured.
	 */
	public function test_is_enabled_returns_true_when_configured(): void {
		$settings = array(
			'smtp_enabled' => true,
			'smtp_host'    => 'smtp.example.com',
		);

		$this->smtp_mailer = new \MSKD_SMTP_Mailer( $settings );

		$this->assertTrue( $this->smtp_mailer->is_enabled() );
	}

	/**
	 * Test that send works using PHP mail when SMTP is not configured.
	 */
	public function test_send_returns_false_when_not_enabled(): void {
		$settings = array(
			'smtp_enabled' => false,
			'smtp_host'    => '',
		);

		$this->smtp_mailer = new \MSKD_SMTP_Mailer( $settings );

		// When SMTP is not configured, it falls back to PHP mail.
		// The mock PHPMailer always returns true for send().
		$result = $this->smtp_mailer->send(
			'test@example.com',
			'Test Subject',
			'<p>Test Body</p>'
		);

		// Should succeed using PHP mail fallback.
		$this->assertTrue( $result );
	}

	/**
	 * Test that local environments block normal sends before PHPMailer is loaded.
	 */
	public function test_send_is_blocked_in_local_environment(): void {
		$GLOBALS['mskd_test_environment_type'] = 'local';
		$this->smtp_mailer                      = new \MSKD_SMTP_Mailer();

		$this->assertFalse(
			$this->smtp_mailer->send( 'test@example.com', 'Test Subject', '<p>Test Body</p>' )
		);
		$this->assertSame(
			'Email delivery is disabled in local environments.',
			$this->smtp_mailer->get_last_error()
		);
	}

	/**
	 * Test that local environments block SMTP test messages.
	 */
	public function test_connection_is_blocked_in_local_environment(): void {
		$GLOBALS['mskd_test_environment_type'] = 'local';
		$this->smtp_mailer                      = new \MSKD_SMTP_Mailer();

		$result = $this->smtp_mailer->test_connection();

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'Email delivery is disabled in local environments.', $result['message'] );
	}

	/**
	 * Test that get_last_error returns empty string initially.
	 */
	public function test_get_last_error_returns_empty_initially(): void {
		$settings = array(
			'smtp_enabled' => true,
			'smtp_host'    => 'smtp.example.com',
		);

		$this->smtp_mailer = new \MSKD_SMTP_Mailer( $settings );

		$this->assertEmpty( $this->smtp_mailer->get_last_error() );
	}

	/**
	 * Test that get_debug_log returns empty array initially.
	 */
	public function test_get_debug_log_returns_empty_array_initially(): void {
		$settings = array(
			'smtp_enabled' => true,
			'smtp_host'    => 'smtp.example.com',
		);

		$this->smtp_mailer = new \MSKD_SMTP_Mailer( $settings );

		$this->assertIsArray( $this->smtp_mailer->get_debug_log() );
		$this->assertEmpty( $this->smtp_mailer->get_debug_log() );
	}

	/**
	 * Test that test_connection returns error when not configured.
	 */
	public function test_test_connection_returns_error_when_not_configured(): void {
		$settings = array(
			'smtp_enabled' => false,
			'smtp_host'    => '',
		);

		$this->smtp_mailer = new \MSKD_SMTP_Mailer( $settings );

		$result = $this->smtp_mailer->test_connection();

		$this->assertIsArray( $result );
		$this->assertFalse( $result['success'] );
		$this->assertNotEmpty( $result['message'] );
	}

	/**
	 * Test that settings are loaded from get_option when not provided.
	 */
	public function test_settings_loaded_from_option_when_not_provided(): void {
		// When empty settings are provided to constructor.
		Functions\when( 'get_option' )->justReturn(
			array(
				'smtp_enabled' => true,
				'smtp_host'    => 'smtp.test.com',
			)
		);

		$this->smtp_mailer = new \MSKD_SMTP_Mailer();

		$this->assertTrue( $this->smtp_mailer->is_enabled() );
	}

	/**
	 * Test valid SMTP settings configuration.
	 */
	public function test_valid_smtp_settings(): void {
		$settings = array(
			'smtp_enabled'  => true,
			'smtp_host'     => 'smtp.gmail.com',
			'smtp_port'     => 587,
			'smtp_security' => 'tls',
			'smtp_auth'     => true,
			'smtp_username' => 'user@gmail.com',
			'smtp_password' => 'apppassword',
			'from_name'     => 'Test Site',
			'from_email'    => 'user@gmail.com',
			'reply_to'      => 'user@gmail.com',
		);

		$this->smtp_mailer = new \MSKD_SMTP_Mailer( $settings );

		$this->assertTrue( $this->smtp_mailer->is_enabled() );
	}

	/**
	 * Test SSL security setting.
	 */
	public function test_ssl_security_setting(): void {
		$settings = array(
			'smtp_enabled'  => true,
			'smtp_host'     => 'smtp.example.com',
			'smtp_port'     => 465,
			'smtp_security' => 'ssl',
		);

		$this->smtp_mailer = new \MSKD_SMTP_Mailer( $settings );

		$this->assertTrue( $this->smtp_mailer->is_enabled() );
	}

	/**
	 * Test no encryption setting.
	 */
	public function test_no_encryption_setting(): void {
		$settings = array(
			'smtp_enabled'  => true,
			'smtp_host'     => 'smtp.example.com',
			'smtp_port'     => 25,
			'smtp_security' => '',
		);

		$this->smtp_mailer = new \MSKD_SMTP_Mailer( $settings );

		$this->assertTrue( $this->smtp_mailer->is_enabled() );
	}
}
