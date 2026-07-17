<?php
/**
 * Admin Notices Tests
 *
 * @package MSKD\Tests\Unit
 */

namespace MSKD\Tests\Unit;

use Brain\Monkey\Functions;

/**
 * Class AdminNoticesTest
 *
 * Tests local-environment admin notice visibility.
 */
class AdminNoticesTest extends TestCase {

	/**
	 * Load the notices controller.
	 */
	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'apply_filters' )->alias(
			function ( $hook, $is_local ) {
				return $is_local;
			}
		);

		require_once \MSKD_PLUGIN_DIR . 'includes/Admin/class-admin-notices.php';
	}

	/**
	 * Test the local-environment warning is visible to administrators on plugin pages.
	 */
	public function test_local_environment_notice_is_visible_to_administrators(): void {
		$GLOBALS['mskd_test_environment_type'] = 'local';
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'get_current_screen' )->justReturn(
			(object) array( 'id' => 'mskd-dashboard' )
		);

		$notices = new \MSKD\Admin\Admin_Notices();

		ob_start();
		$notices->show_local_environment_notice();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'notice-warning', $output );
		$this->assertStringContainsString( 'Mail System does not send email in local environments.', $output );
	}
}
