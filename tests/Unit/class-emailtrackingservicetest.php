<?php
/**
 * Email Tracking Service Tests
 *
 * @package MSKD\Tests\Unit
 */

namespace MSKD\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;
use MSKD\Services\Email_Tracking_Service;

/**
 * Class EmailTrackingServiceTest
 */
class EmailTrackingServiceTest extends TestCase {

	/**
	 * Tracking service instance.
	 *
	 * @var Email_Tracking_Service
	 */
	private $service;

	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->setup_wpdb_mock();
		$this->service = new Email_Tracking_Service();

		Functions\when( 'esc_url' )->alias(
			function ( $url ) {
				return $url;
			}
		);
	}

	/**
	 * Tokens are unpredictable, unique, and safe to use in URLs.
	 */
	public function test_generate_token_returns_unique_hex_tokens(): void {
		$first  = $this->service->generate_token();
		$second = $this->service->generate_token();

		$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $first );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $second );
		$this->assertNotSame( $first, $second );
	}

	/**
	 * A pixel is inserted before the closing body tag and is not duplicated.
	 */
	public function test_append_tracking_pixel_inserts_pixel_once(): void {
		$token = str_repeat( 'a', 64 );
		$body  = '<html><body><p>Hello</p></body></html>';

		$tracked = $this->service->append_tracking_pixel( $body, $token );

		$this->assertStringContainsString( '?mskd_track_open=' . $token, $tracked );
		$this->assertStringContainsString( 'width="1" height="1"', $tracked );
		$this->assertLessThan( strpos( $tracked, '</body>' ), strpos( $tracked, $token ) );
		$this->assertSame( 1, substr_count( $tracked, $token ) );
		$this->assertSame( $tracked, $this->service->append_tracking_pixel( $tracked, $token ) );
	}

	/**
	 * Invalid tokens do not alter email content.
	 */
	public function test_append_tracking_pixel_rejects_invalid_token(): void {
		$body = '<p>Hello</p>';

		$this->assertSame( $body, $this->service->append_tracking_pixel( $body, 'not-a-token' ) );
	}

	/**
	 * Eligible anchors are rewritten without changing unrelated markup or links.
	 */
	public function test_rewrite_links_tracks_only_eligible_destinations(): void {
		$token = str_repeat( 'd', 64 );
		$body  = '<p><a class="button" href="https://shop.example/path?campaign=7&amp;user=42#details">Buy</a>'
			. '<a href="mailto:help@example.com">Email</a>'
			. '<a href="tel:+359123">Call</a>'
			. '<a href="sms:+359123">Text</a>'
			. '<a href="#section">Jump</a>'
			. '<a href="data:text/plain,hello">Data</a>'
			. '<a href="javascript:alert(1)">Script</a>'
			. '<a href="https://example.com/?mskd_unsubscribe=secret">Unsubscribe</a>'
			. '<a href="https://example.com/?mskd_confirm=secret">Confirm</a>'
			. '<a href="https://example.com/?mskd_track_open=secret">Pixel</a>'
			. '<a data-mskd-no-track href="https://private.example/?user=42">Private</a>'
			. '<img src="https://images.example/pixel.gif"></p>';

		$rewritten = $this->service->rewrite_links( $body, $token );

		$this->assertSame( 1, substr_count( $rewritten, 'mskd_track_click=' ) );
		$this->assertStringContainsString( 'class="button"', $rewritten );
		$this->assertStringContainsString( 'href="mailto:help@example.com"', $rewritten );
		$this->assertStringContainsString( 'href="tel:+359123"', $rewritten );
		$this->assertStringContainsString( 'href="sms:+359123"', $rewritten );
		$this->assertStringContainsString( 'href="#section"', $rewritten );
		$this->assertStringContainsString( 'href="data:text/plain,hello"', $rewritten );
		$this->assertStringContainsString( 'href="javascript:alert(1)"', $rewritten );
		$this->assertStringContainsString( 'mskd_unsubscribe=secret', $rewritten );
		$this->assertStringContainsString( 'mskd_confirm=secret', $rewritten );
		$this->assertStringContainsString( 'mskd_track_open=secret', $rewritten );
		$this->assertStringContainsString( 'href="https://private.example/?user=42"', $rewritten );
		$this->assertStringContainsString( 'src="https://images.example/pixel.gif"', $rewritten );

		preg_match( '/href="([^"]*mskd_track_click=[^"]+)"/', $rewritten, $match );
		parse_str( wp_parse_url( html_entity_decode( $match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' ), PHP_URL_QUERY ), $query );
		$decoded = base64_decode( strtr( $query['url'], '-_', '+/' ) . str_repeat( '=', ( 4 - strlen( $query['url'] ) % 4 ) % 4 ) );

		$this->assertSame( 'https://shop.example/path?campaign=7&user=42#details', $decoded );
		$this->assertSame( '1', (string) $query['link'] );
	}

	/**
	 * Repeated destinations retain separate, stable positions and single quotes.
	 */
	public function test_rewrite_links_assigns_stable_indexes_to_repeated_links(): void {
		$token = str_repeat( '4', 64 );
		$body  = "<a href='https://example.org/same'>First</a><broken><a href=\"https://example.org/same\">Second</a>";

		$rewritten = $this->service->rewrite_links( $body, $token );

		$this->assertSame( 2, substr_count( $rewritten, 'mskd_track_click=' ) );
		$this->assertStringContainsString( 'link=1', $rewritten );
		$this->assertStringContainsString( 'link=2', $rewritten );
		$this->assertStringContainsString( '<broken>', $rewritten );
	}

	/**
	 * Invalid tokens and already tracked links leave the body unchanged.
	 */
	public function test_rewrite_links_is_idempotent_and_rejects_invalid_token(): void {
		$body    = '<a href="https://example.org/article">Read</a>';
		$token   = str_repeat( 'e', 64 );
		$tracked = $this->service->rewrite_links( $body, $token );

		$this->assertSame( $body, $this->service->rewrite_links( $body, 'invalid' ) );
		$this->assertSame( $tracked, $this->service->rewrite_links( $tracked, $token ) );
	}

	/**
	 * A valid signed GET records an aggregate click and infers an open.
	 */
	public function test_resolve_click_records_aggregate_and_inferred_open(): void {
		$token       = str_repeat( 'f', 64 );
		$destination = 'https://docs.example.com/guide?subscriber=42#start';
		$query       = $this->get_click_query( $this->service->create_click_url( $token, 2, $destination ) );

		$this->wpdb->shouldReceive( 'get_row' )
			->once()
			->with( Mockery::on( fn( $sql ) => false !== strpos( $sql, "status IN ('processing', 'sent')" ) && false !== strpos( $sql, $token ) ) )
			->andReturn( (object) array( 'id' => 15, 'campaign_id' => 9 ) );

		$this->wpdb->shouldReceive( 'query' )
			->once()
			->with(
				Mockery::on(
					function ( $sql ) {
						return false !== strpos( $sql, 'INSERT INTO wp_mskd_clicks' )
							&& false !== strpos( $sql, 'ON DUPLICATE KEY UPDATE' )
							&& false !== strpos( $sql, 'click_count = click_count + 1' )
							&& false !== strpos( $sql, 'https://docs.example.com/' )
							&& false === strpos( $sql, '/guide' )
							&& false === strpos( $sql, 'subscriber=42' );
					}
				)
			)
			->andReturn( 1 );

		$this->wpdb->shouldReceive( 'query' )
			->once()
			->with( Mockery::on( fn( $sql ) => false !== strpos( $sql, 'opened_at = COALESCE(opened_at' ) && false !== strpos( $sql, 'WHERE id = 15' ) ) )
			->andReturn( 1 );

		$this->assertSame(
			$destination,
			$this->service->resolve_click( $token, 2, $query['url'], $query['sig'], true )
		);
	}

	/**
	 * HEAD resolves the target but never changes analytics.
	 */
	public function test_resolve_click_head_does_not_record(): void {
		$token       = str_repeat( '1', 64 );
		$destination = 'https://example.net/news';
		$query       = $this->get_click_query( $this->service->create_click_url( $token, 1, $destination ) );

		$this->wpdb->shouldReceive( 'get_row' )
			->once()
			->andReturn( (object) array( 'id' => 3, 'campaign_id' => 4 ) );
		$this->wpdb->shouldReceive( 'query' )->never();

		$this->assertSame(
			$destination,
			$this->service->resolve_click( $token, 1, $query['url'], $query['sig'], false )
		);
	}

	/**
	 * Modified destinations fail before any database lookup or redirect.
	 */
	public function test_resolve_click_rejects_tampered_payload(): void {
		$token = str_repeat( '2', 64 );
		$query = $this->get_click_query( $this->service->create_click_url( $token, 1, 'https://example.net/original' ) );

		$query['url'] = rtrim( strtr( base64_encode( 'https://evil.example/changed' ), '+/', '-_' ), '=' );
		$this->wpdb->shouldReceive( 'get_row' )->never();
		$this->wpdb->shouldReceive( 'query' )->never();

		$this->assertFalse( $this->service->resolve_click( $token, 1, $query['url'], $query['sig'], true ) );
	}

	/**
	 * Correctly signed but unknown queue tokens cannot be used as open redirects.
	 */
	public function test_resolve_click_rejects_unknown_queue_token(): void {
		$token       = str_repeat( '3', 64 );
		$destination = 'https://example.net/known';
		$query       = $this->get_click_query( $this->service->create_click_url( $token, 1, $destination ) );

		$this->wpdb->shouldReceive( 'get_row' )->once()->andReturn( null );
		$this->wpdb->shouldReceive( 'query' )->never();

		$this->assertFalse( $this->service->resolve_click( $token, 1, $query['url'], $query['sig'], true ) );
	}

	/**
	 * A valid event preserves the first-open timestamp and increments load count.
	 */
	public function test_record_open_updates_matching_sent_item(): void {
		$token = str_repeat( 'b', 64 );

		$this->wpdb->shouldReceive( 'query' )
			->once()
			->with(
				Mockery::on(
					function ( $query ) use ( $token ) {
						return false !== strpos( $query, 'opened_at = COALESCE(opened_at' )
							&& false !== strpos( $query, 'open_count = open_count + 1' )
							&& false !== strpos( $query, "status IN ('processing', 'sent')" )
							&& false !== strpos( $query, $token );
					}
				)
			)
			->andReturn( 1 );

		$this->assertTrue( $this->service->record_open( $token ) );
	}

	/**
	 * Invalid tokens are discarded without touching the database.
	 */
	public function test_record_open_rejects_invalid_token(): void {
		$this->wpdb->shouldReceive( 'query' )->never();

		$this->assertFalse( $this->service->record_open( 'invalid' ) );
	}

	/**
	 * Unknown valid tokens produce no analytics update.
	 */
	public function test_record_open_returns_false_for_unknown_token(): void {
		$this->wpdb->shouldReceive( 'query' )->once()->andReturn( 0 );

		$this->assertFalse( $this->service->record_open( str_repeat( 'c', 64 ) ) );
	}

	/**
	 * Parse query parameters from a generated click URL.
	 *
	 * @param string $url Generated URL.
	 * @return array<string,string>
	 */
	private function get_click_query( string $url ): array {
		$query = array();
		parse_str( wp_parse_url( $url, PHP_URL_QUERY ), $query );
		return $query;
	}
}
