<?php
/**
 * Email Preview iframe Tests
 *
 * @package MSKD\Tests\Unit
 */

namespace MSKD\Tests\Unit;

use Brain\Monkey\Functions;

/**
 * Class EmailPreviewIframeTest
 *
 * Tests preview iframe markup used by the admin UI.
 */
class EmailPreviewIframeTest extends TestCase {

	/**
	 * Mock user ID used for compose wizard session keys.
	 */
	private const MOCK_USER_ID = 7;

	/**
	 * Test queue detail renders a stable iframe target name.
	 */
	public function test_queue_detail_renders_campaign_preview_iframe_name(): void {
		$wpdb = $this->setup_wpdb_mock();

		$campaign               = new \stdClass();
		$campaign->id           = 22;
		$campaign->subject      = 'Preview Subject';
		$campaign->status       = 'completed';
		$campaign->type         = 'campaign';
		$campaign->created_at   = '2026-07-08 12:00:00';
		$campaign->scheduled_at = '2026-07-08 13:00:00';
		$campaign->completed_at = '2026-07-08 14:00:00';
		$campaign->list_ids     = '';
		$campaign->bcc          = '';

		$queue_stats             = new \stdClass();
		$queue_stats->total      = 1;
		$queue_stats->pending    = 0;
		$queue_stats->processing = 0;
		$queue_stats->sent       = 1;
		$queue_stats->failed     = 0;
		$queue_stats->cancelled  = 0;

		$wpdb->shouldReceive( 'get_row' )
			->once()
			->andReturn( $campaign );
		$wpdb->shouldReceive( 'get_row' )
			->once()
			->andReturn( $queue_stats );
		$wpdb->shouldReceive( 'get_var' )
			->once()
			->andReturn( 1 );
		$wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( array() );

		Functions\stubs(
			array(
				'settings_errors' => null,
				'date_i18n'       => function ( $format, $timestamp ) {
					return $timestamp;
				},
				'esc_html_e'      => function ( $text, $domain = 'default' ) {
					echo $text;
				},
				'esc_attr_e'      => function ( $text, $domain = 'default' ) {
					echo $text;
				},
				'wp_nonce_url'    => function ( $actionurl ) {
					return $actionurl;
				},
			)
		);

		$_GET['campaign_id'] = 22;

		ob_start();
		include MSKD_PLUGIN_DIR . 'admin/partials/queue-detail.php';
		$output = ob_get_clean();

		unset( $_GET['campaign_id'] );

		$this->assertStringContainsString( 'class="mskd-email-iframe mskd-campaign-preview-iframe"', $output );
		$this->assertStringContainsString( 'name="preview_campaign_22"', $output );
	}

	/**
	 * Test compose wizard renders a stable iframe target name.
	 */
	public function test_compose_wizard_renders_preview_iframe_name(): void {
		$wpdb = $this->setup_wpdb_mock();

		$wpdb->shouldReceive( 'get_results' )
			->twice()
			->andReturn( array(), array() );

		Functions\stubs(
			array(
				'apply_filters'    => function ( $hook, $value ) {
					return $value;
				},
				'get_current_user_id' => function () {
					return self::MOCK_USER_ID;
				},
				'get_transient'    => function () {
					return array(
						'template_id'   => 0,
						'use_visual'    => false,
						'subject'       => 'Compose Subject',
						'content'       => '<p>Preview content</p>',
						'json_content'  => '',
						'lists'         => array(),
						'schedule_type' => 'now',
					);
				},
				'settings_errors'  => null,
				'wp_nonce_field'   => null,
				'esc_html_e'       => function ( $text, $domain = 'default' ) {
					echo $text;
				},
				'esc_attr_e'       => function ( $text, $domain = 'default' ) {
					echo $text;
				},
			)
		);

		$_GET['step'] = 3;

		ob_start();
		include MSKD_PLUGIN_DIR . 'admin/partials/compose-wizard.php';
		$output = ob_get_clean();

		unset( $_GET['step'] );

		$this->assertStringContainsString( 'class="mskd-email-preview-iframe"', $output );
		$this->assertStringContainsString( 'name="mskd_preview_compose"', $output );
	}
}
