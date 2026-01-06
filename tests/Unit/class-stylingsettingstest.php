<?php
/**
 * Styling Settings Tests
 *
 * @package MSKD\Tests\Unit
 */

namespace MSKD\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;
use MSKD\Admin\Admin_Settings;

/**
 * Class StylingSettingsTest
 *
 * Tests for styling settings functionality.
 */
class StylingSettingsTest extends TestCase {

	/**
	 * Admin Settings instance.
	 *
	 * @var Admin_Settings
	 */
	protected $admin_settings;

	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {
		parent::setUp();

		// Define MSKD_BATCH_SIZE if not defined.
		if ( ! defined( 'MSKD_BATCH_SIZE' ) ) {
			define( 'MSKD_BATCH_SIZE', 10 );
		}

		$this->admin_settings = new Admin_Settings();
	}

	/**
	 * Tear down test environment.
	 */
	protected function tearDown(): void {
		// Clean up any POST data.
		$_POST = array();

		parent::tearDown();
	}

	/**
	 * Test default styling settings are returned when no settings exist.
	 */
	public function test_get_settings_returns_default_styling_colors(): void {
		// Override get_option stub for this test.
		Functions\stubs(
			array(
				'get_option' => function ( $option, $default = false ) {
					if ( 'mskd_settings' === $option ) {
						return array(); // No settings saved.
					}
					return $default;
				},
			)
		);

		$settings = $this->admin_settings->get_settings();

		$this->assertArrayHasKey( 'highlight_color', $settings );
		$this->assertArrayHasKey( 'button_text_color', $settings );
		$this->assertEquals( '#2271b1', $settings['highlight_color'] );
		$this->assertEquals( '#ffffff', $settings['button_text_color'] );
	}

	/**
	 * Test custom styling colors are returned when saved.
	 */
	public function test_get_settings_returns_custom_styling_colors(): void {
		$custom_settings = array(
			'highlight_color'   => '#ff0000',
			'button_text_color' => '#000000',
		);

		Functions\stubs(
			array(
				'get_option' => function ( $option, $default = false ) use ( $custom_settings ) {
					if ( 'mskd_settings' === $option ) {
						return $custom_settings;
					}
					return $default;
				},
			)
		);

		$settings = $this->admin_settings->get_settings();

		$this->assertEquals( '#ff0000', $settings['highlight_color'] );
		$this->assertEquals( '#000000', $settings['button_text_color'] );
	}

	/**
	 * Test styling colors are merged with defaults.
	 */
	public function test_get_settings_merges_styling_with_defaults(): void {
		// Only highlight color is set.
		$partial_settings = array(
			'highlight_color' => '#00ff00',
		);

		Functions\stubs(
			array(
				'get_option' => function ( $option, $default = false ) use ( $partial_settings ) {
					if ( 'mskd_settings' === $option ) {
						return $partial_settings;
					}
					return $default;
				},
			)
		);

		$settings = $this->admin_settings->get_settings();

		// Custom highlight color.
		$this->assertEquals( '#00ff00', $settings['highlight_color'] );
		// Default button text color.
		$this->assertEquals( '#ffffff', $settings['button_text_color'] );
	}

	/**
	 * Test all expected settings keys exist.
	 */
	public function test_get_settings_contains_all_required_keys(): void {
		Functions\stubs(
			array(
				'get_option' => function ( $option, $default = false ) {
					if ( 'mskd_settings' === $option ) {
						return array();
					}
					return $default;
				},
			)
		);

		$settings = $this->admin_settings->get_settings();

		$expected_keys = array(
			'from_name',
			'from_email',
			'reply_to',
			'emails_per_minute',
			'email_header',
			'email_footer',
			'highlight_color',
			'button_text_color',
			'show_name_field',
			'enable_ga4_tracking',
			'smtp_enabled',
			'smtp_host',
			'smtp_port',
			'smtp_security',
			'smtp_auth',
			'smtp_username',
			'smtp_password',
		);

		foreach ( $expected_keys as $key ) {
			$this->assertArrayHasKey( $key, $settings, "Missing expected key: $key" );
		}
	}

	/**
	 * Test default value for enable_ga4_tracking is false.
	 */
	public function test_get_settings_returns_default_ga4_tracking_disabled(): void {
		Functions\stubs(
			array(
				'get_option' => function ( $option, $default = false ) {
					if ( 'mskd_settings' === $option ) {
						return array(); // No settings saved.
					}
					return $default;
				},
			)
		);

		$settings = $this->admin_settings->get_settings();

		$this->assertArrayHasKey( 'enable_ga4_tracking', $settings );
		$this->assertEquals( 0, $settings['enable_ga4_tracking'] );
		$this->assertFalse( (bool) $settings['enable_ga4_tracking'] );
	}

	/**
	 * Test GA4 tracking setting can be enabled.
	 */
	public function test_get_settings_returns_enabled_ga4_tracking(): void {
		$custom_settings = array(
			'enable_ga4_tracking' => 1,
		);

		Functions\stubs(
			array(
				'get_option' => function ( $option, $default = false ) use ( $custom_settings ) {
					if ( 'mskd_settings' === $option ) {
						return $custom_settings;
					}
					return $default;
				},
			)
		);

		$settings = $this->admin_settings->get_settings();

		$this->assertEquals( 1, $settings['enable_ga4_tracking'] );
		$this->assertTrue( (bool) $settings['enable_ga4_tracking'] );
	}
}
