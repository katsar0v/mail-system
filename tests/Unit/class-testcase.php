<?php
/**
 * Base Test Case
 *
 * @package MSKD\Tests\Unit
 */

namespace MSKD\Tests\Unit;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;

/**
 * Class TestCase
 *
 * Base test case for all unit tests.
 */
abstract class TestCase extends PHPUnitTestCase {

	/**
	 * Mock wpdb object.
	 *
	 * @var \Mockery\MockInterface
	 */
	protected $wpdb;

	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Set up common WordPress functions.
		$this->setup_common_wp_functions();
	}

	/**
	 * Tear down test environment.
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		Mockery::close();
		parent::tearDown();
	}

	/**
	 * Set up commonly used WordPress functions.
	 */
	protected function setup_common_wp_functions(): void {
		// Escaping functions - return input as-is.
		Functions\stubs(
			array(
				'esc_html'          => null,
				'esc_attr'          => null,
				'esc_url'           => null,
				'esc_html__'        => null,
				'esc_attr__'        => null,
				'esc_js'            => function ( $text, $domain = 'default' ) {
					return $text;
				},
				'wp_kses_post'      => null,
				'wp_strip_all_tags' => function ( $string, $remove_breaks = false ) {
					return strip_tags( $string );
				},
				'absint'            => function ( $value ) {
					return abs( (int) $value );
				},
			)
		);

		// Translation functions.
		Functions\stubs(
			array(
				'__'         => function ( $text, $domain = 'default' ) {
					return $text;
				},
				'_e'         => function ( $text, $domain = 'default' ) {
					echo $text;
				},
				'esc_html__' => function ( $text, $domain = 'default' ) {
					return $text;
				},
				'esc_attr__' => function ( $text, $domain = 'default' ) {
					return $text;
				},
			)
		);

		// Sanitization functions.
		Functions\stubs(
			array(
				'sanitize_text_field'     => function ( $str ) {
					return trim( strip_tags( $str ) );
				},
				'sanitize_email'          => function ( $email ) {
					return filter_var( $email, FILTER_SANITIZE_EMAIL );
				},
				'sanitize_textarea_field' => function ( $str ) {
					return trim( strip_tags( $str ) );
				},
				'is_email'                => function ( $email ) {
					return filter_var( $email, FILTER_VALIDATE_EMAIL ) !== false;
				},
				'wp_unslash'              => function ( $value ) {
					return is_array( $value ) ? array_map( 'stripslashes_deep', $value ) : stripslashes( $value );
				},
				'stripslashes_deep'       => function ( $value ) {
					return is_array( $value ) ? array_map( 'stripslashes_deep', $value ) : stripslashes( $value );
				},
			)
		);

		// URL functions.
		Functions\stubs(
			array(
				'home_url'        => function ( $path = '' ) {
					return 'https://example.com' . $path;
				},
				'admin_url'       => function ( $path = '' ) {
					return 'https://example.com/wp-admin/' . $path;
				},
				'add_query_arg'   => function ( $args, $url = '' ) {
					return $url . '?' . http_build_query( $args );
				},
				'plugin_dir_path' => function ( $file ) {
					return dirname( $file ) . '/';
				},
				'plugin_dir_url'  => function ( $file ) {
					return 'https://example.com/wp-content/plugins/' . basename( dirname( $file ) ) . '/';
				},
			)
		);

		// Option functions (default stubs).
		Functions\stubs(
			array(
				'get_option'    => function ( $option, $default = false ) {
					return $default;
				},
				'update_option' => function () {
					return true;
				},
				'delete_option' => function () {
					return true;
				},
			)
		);

		// Other common functions.
		Functions\stubs(
			array(
				'wp_parse_args'        => function ( $args, $defaults = array() ) {
					if ( is_object( $args ) ) {
						$args = get_object_vars( $args );
					}
					return array_merge( $defaults, $args );
				},
				'wp_generate_password' => function ( $length = 12, $special_chars = true ) {
					return substr( str_shuffle( 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789' ), 0, $length );
				},
				'current_time'         => function ( $type ) {
					return $type === 'mysql' ? gmdate( 'Y-m-d H:i:s' ) : time();
				},
				'get_bloginfo'         => function ( $show ) {
					$values = array(
						'name'        => 'Test Site',
						'admin_email' => 'admin@example.com',
						'charset'     => 'UTF-8',
					);
					return $values[ $show ] ?? '';
				},
				'bloginfo'             => function ( $show ) {
					$values = array(
						'name'        => 'Test Site',
						'admin_email' => 'admin@example.com',
						'charset'     => 'UTF-8',
					);
					echo $values[ $show ] ?? '';
				},
				'wp_timezone'          => function () {
					return new \DateTimeZone( 'UTC' );
				},
				'wp_timezone_string'   => function () {
					return 'UTC';
				},
				'wp_strip_all_tags'    => function ( $string ) {
					return strip_tags( $string );
				},
				'language_attributes'  => function () {
					echo 'lang="en-US"';
				},
			)
		);

		// Plugin-specific helper functions (with 00 seconds normalization).
		Functions\stubs(
			array(
				'mskd_normalize_timestamp'     => function ( $timestamp = null ) {
					if ( null === $timestamp ) {
						$timestamp = time();
					}
					return (int) ( floor( $timestamp / 60 ) * 60 );
				},
				'mskd_current_time_normalized' => function () {
					$now = new \DateTime( 'now', new \DateTimeZone( 'UTC' ) );
					$now->setTime( (int) $now->format( 'H' ), (int) $now->format( 'i' ), 0 );
					return $now->format( 'Y-m-d H:i:s' );
				},
				'mskd_kses_email'              => function ( $content ) {
					// In tests, return content as-is (sanitization happens in WordPress).
					return $content;
				},
			)
		);
	}

	/**
	 * Create a mock wpdb object.
	 *
	 * @return \Mockery\MockInterface
	 */
	protected function create_wpdb_mock() {
		$wpdb         = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';
		$wpdb->shouldReceive( 'prepare' )
			->andReturnUsing(
				function ( $query, ...$args ) {
					// Flatten args if nested (handles splat operator patterns).
					$flat_args = array();
					foreach ( $args as $arg ) {
						if ( is_array( $arg ) ) {
							$flat_args = array_merge( $flat_args, $arg );
						} else {
							$flat_args[] = $arg;
						}
					}

					// Simple placeholder replacement for testing.
					// Count placeholders and only use as many args as needed.
					$placeholder_count = preg_match_all( '/%[sdf]/', $query );
					$flat_args         = array_slice( $flat_args, 0, $placeholder_count );

					$query = str_replace( '%s', "'%s'", $query );
					return empty( $flat_args ) ? $query : vsprintf( $query, $flat_args );
				}
			);
		return $wpdb;
	}

	/**
	 * Set up the global $wpdb mock.
	 *
	 * @return \Mockery\MockInterface
	 */
	protected function setup_wpdb_mock() {
		$this->wpdb      = $this->create_wpdb_mock();
		$GLOBALS['wpdb'] = $this->wpdb;
		return $this->wpdb;
	}
}
