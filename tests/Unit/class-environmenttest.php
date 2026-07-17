<?php
/**
 * Environment Tests
 *
 * @package MSKD\Tests\Unit
 */

namespace MSKD\Tests\Unit;

use Brain\Monkey\Functions;

/**
 * Class EnvironmentTest
 *
 * Tests for local-environment email delivery protection.
 */
class EnvironmentTest extends TestCase {

	/**
	 * Load the environment helper.
	 */
	protected function setUp(): void {
		parent::setUp();

		require_once \MSKD_PLUGIN_DIR . 'includes/class-mskd-environment.php';
	}

	/**
	 * Test explicit WordPress local environment detection.
	 */
	public function test_detects_wordpress_local_environment(): void {
		$GLOBALS['mskd_test_environment_type'] = 'local';
		Functions\when( 'apply_filters' )->alias(
			function ( $hook, $is_local ) {
				return $is_local;
			}
		);

		$this->assertTrue( \MSKD_Environment::is_local() );
	}

	/**
	 * Test common local hostname fallback detection.
	 *
	 * @dataProvider local_host_provider
	 *
	 * @param string $host Local host.
	 */
	public function test_detects_local_hostname( string $host ): void {
		Functions\when( 'home_url' )->justReturn( 'http://' . $host );
		Functions\when( 'site_url' )->justReturn( 'https://example.com' );
		Functions\when( 'apply_filters' )->alias(
			function ( $hook, $is_local ) {
				return $is_local;
			}
		);

		$this->assertTrue( \MSKD_Environment::is_local() );
	}

	/**
	 * Provide common local host names.
	 *
	 * @return array
	 */
	public function local_host_provider(): array {
		return array(
			'localhost' => array( 'localhost' ),
			'loopback IPv4' => array( '127.0.0.1' ),
			'loopback IPv6' => array( '[::1]' ),
			'local TLD' => array( 'mail-system.local' ),
			'test TLD' => array( 'mail-system.test' ),
		);
	}

	/**
	 * Test that project-specific filters can override detection.
	 */
	public function test_filter_can_override_local_detection(): void {
		Functions\when( 'home_url' )->justReturn( 'http://localhost' );
		Functions\when( 'site_url' )->justReturn( 'http://localhost' );
		Functions\when( 'apply_filters' )->alias(
			function ( $hook, $is_local ) {
				$this->assertSame( 'mskd_is_local_environment', $hook );
				$this->assertTrue( $is_local );
				return false;
			}
		);

		$this->assertFalse( \MSKD_Environment::is_local() );
	}
}
