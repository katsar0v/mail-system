<?php
/**
 * Timezone Consistency Tests
 *
 * Verifies that scheduling/queue datetime helpers operate on the
 * WordPress-configured timezone (Settings → General) rather than the
 * server/DB timezone, keeping writes, comparisons and display consistent.
 *
 * Regression coverage for the "phantom UTC-offset" bug where retry writes
 * used UTC (gmdate) while comparisons used site-local current_time('mysql').
 *
 * @package MSKD\Tests\Unit
 */

namespace MSKD\Tests\Unit;

use Brain\Monkey\Functions;

/**
 * Class TimezoneConsistencyTest
 */
class TimezoneConsistencyTest extends TestCase {

	/**
	 * Site timezone used for the non-UTC scenario (matches the bug report).
	 */
	private const SITE_TIMEZONE = 'Europe/Sofia';

	/**
	 * Point in time used for the assertions (fixed instant).
	 */
	private const FIXED_TIMESTAMP = 1751983380; // 2025-07-08 14:03:00 UTC.

	/**
	 * Re-stub the timezone-related helpers with production-faithful bodies
	 * bound to a non-UTC site timezone and a fixed "now".
	 */
	private function stub_non_utc_timezone(): void {
		$tz = self::SITE_TIMEZONE;
		$fixed = self::FIXED_TIMESTAMP;

		Functions\when( 'wp_timezone' )->alias(
			static function () use ( $tz ) {
				return new \DateTimeZone( $tz );
			}
		);

		// current_time( 'mysql' ) returns site-local time (00 seconds).
		Functions\when( 'current_time' )->alias(
			static function ( $type ) use ( $tz, $fixed ) {
				if ( 'timestamp' === $type ) {
					return $fixed;
				}
				$now = new \DateTime( '@' . $fixed );
				$now->setTimezone( new \DateTimeZone( $tz ) );
				return $now->format( 'Y-m-d H:i:s' );
			}
		);

		Functions\when( 'mskd_current_time_normalized' )->alias(
			static function () use ( $tz, $fixed ) {
				$now = new \DateTime( '@' . $fixed );
				$now->setTimezone( new \DateTimeZone( $tz ) );
				$now->setTime( (int) $now->format( 'H' ), (int) $now->format( 'i' ), 0 );
				return $now->format( 'Y-m-d H:i:s' );
			}
		);

		Functions\when( 'mskd_local_time_from_timestamp' )->alias(
			static function ( $timestamp ) use ( $tz ) {
				$date = new \DateTime( '@' . (int) $timestamp );
				$date->setTimezone( new \DateTimeZone( $tz ) );
				return $date->format( 'Y-m-d H:i:s' );
			}
		);
	}

	/**
	 * The local formatter must render an absolute timestamp in the site
	 * timezone, not UTC (the previous gmdate behavior).
	 */
	public function test_local_formatter_uses_site_timezone(): void {
		$this->stub_non_utc_timezone();

		// Europe/Sofia is UTC+3 in July, so 14:03 UTC => 17:03 local.
		$this->assertSame(
			'2025-07-08 17:03:00',
			mskd_local_time_from_timestamp( self::FIXED_TIMESTAMP )
		);
		$this->assertNotSame(
			gmdate( 'Y-m-d H:i:s', self::FIXED_TIMESTAMP ),
			mskd_local_time_from_timestamp( self::FIXED_TIMESTAMP )
		);
	}

	/**
	 * A retry scheduled +2 minutes from now must land in the future relative
	 * to current_time('mysql'), so the retry delay is honored on non-UTC sites.
	 */
	public function test_retry_schedule_is_in_future_relative_to_local_now(): void {
		$this->stub_non_utc_timezone();

		$now_local     = current_time( 'mysql' );
		$retry_seconds = self::FIXED_TIMESTAMP + ( 2 * 60 );
		$retry_local   = mskd_local_time_from_timestamp( $retry_seconds );

		$tz          = new \DateTimeZone( self::SITE_TIMEZONE );
		$now_dt      = new \DateTime( $now_local, $tz );
		$retry_dt    = new \DateTime( $retry_local, $tz );
		$diff_minutes = ( $retry_dt->getTimestamp() - $now_dt->getTimestamp() ) / 60;

		$this->assertSame( 2, $diff_minutes, 'Retry delay must be honored on non-UTC sites.' );
		$this->assertGreaterThan( $now_local, $retry_local );
	}

	/**
	 * A stuck-recovery threshold computed -15 minutes ago must be in the past
	 * relative to current_time('mysql') so recovery triggers on time.
	 */
	public function test_recovery_threshold_is_in_past_relative_to_local_now(): void {
		$this->stub_non_utc_timezone();

		$now_local        = current_time( 'mysql' );
		$timeout_seconds  = self::FIXED_TIMESTAMP - ( 15 * 60 );
		$timeout_local    = mskd_local_time_from_timestamp( $timeout_seconds );

		$this->assertLessThan( $now_local, $timeout_local );
	}
}
