<?php
/**
 * Environment detection helper.
 *
 * @package MSKD
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MSKD_Environment
 *
 * Detects local WordPress installations where Mail System must not deliver email.
 */
class MSKD_Environment {

	/**
	 * Determine whether the current site is running in a local environment.
	 *
	 * The result can be overridden for project-specific development hosts.
	 *
	 * @return bool True when delivery must be disabled.
	 */
	public static function is_local() {
		$is_local = function_exists( 'wp_get_environment_type' ) && 'local' === wp_get_environment_type();
		$hosts    = self::get_site_hosts();

		if ( ! $is_local ) {
			foreach ( $hosts as $host ) {
				if ( self::is_local_host( $host ) ) {
					$is_local = true;
					break;
				}
			}
		}

		/**
		 * Filters whether Mail System should disable email delivery for the current environment.
		 *
		 * @param bool  $is_local Whether the current environment is local.
		 * @param array $hosts    Site and home hosts used for fallback detection.
		 */
		return (bool) apply_filters( 'mskd_is_local_environment', $is_local, $hosts );
	}

	/**
	 * Get the site and home hosts used for fallback detection.
	 *
	 * @return array
	 */
	private static function get_site_hosts() {
		$hosts = array();

		foreach ( array( home_url(), site_url() ) as $url ) {
			$host = wp_parse_url( $url, PHP_URL_HOST );
			if ( is_string( $host ) && '' !== $host ) {
				$hosts[] = strtolower( trim( $host, '[]' ) );
			}
		}

		return array_unique( $hosts );
	}

	/**
	 * Determine whether a host is a common local development host.
	 *
	 * @param string $host Host name without brackets.
	 * @return bool
	 */
	private static function is_local_host( $host ) {
		return 'localhost' === $host
			|| '::1' === $host
			|| 0 === strpos( $host, '127.' )
			|| 0 === strpos( $host, '0.' )
			|| ( strlen( $host ) > 6 && '.local' === substr( $host, -6 ) )
			|| ( strlen( $host ) > 5 && '.test' === substr( $host, -5 ) );
	}
}
