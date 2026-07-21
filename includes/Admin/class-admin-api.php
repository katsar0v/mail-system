<?php
/**
 * Admin API Access Controller
 *
 * Handles the "API Access" admin page where administrators create and revoke JWT
 * bearer tokens for the REST API. Generated tokens are displayed exactly once.
 *
 * @package MSKD\Admin
 * @since   1.9.0
 */

namespace MSKD\Admin;

use MSKD\Api\Token_Service;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Admin_Api
 */
class Admin_Api {

	/**
	 * Token service.
	 *
	 * @var Token_Service
	 */
	private $token_service;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->token_service = new Token_Service();
	}

	/**
	 * Handle create/delete actions on the API Access page.
	 *
	 * @return void
	 */
	public function handle_actions(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Create token.
		if ( isset( $_POST['mskd_api_create_token'], $_POST['mskd_api_nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mskd_api_nonce'] ) ), 'mskd_api_create_token' ) ) {
			$this->handle_create_token();
		}

		// Delete (revoke) token.
		if ( isset( $_POST['mskd_api_delete_token'], $_POST['mskd_api_nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mskd_api_nonce'] ) ), 'mskd_api_delete_token' ) ) {
			$this->handle_delete_token();
		}
	}

	/**
	 * Create a new token from the submitted form.
	 *
	 * @return void
	 */
	private function handle_create_token(): void {
		$name = isset( $_POST['token_name'] ) ? sanitize_text_field( wp_unslash( $_POST['token_name'] ) ) : '';

		$scopes = array();
		if ( isset( $_POST['scopes'] ) && is_array( $_POST['scopes'] ) ) {
			$scopes = array_map( 'sanitize_text_field', wp_unslash( $_POST['scopes'] ) );
		}

		$ttl_days = isset( $_POST['ttl_days'] ) ? (int) $_POST['ttl_days'] : 0;

		$result = $this->token_service->create_token( $name, $scopes, $ttl_days, get_current_user_id() );

		if ( is_wp_error( $result ) ) {
			add_settings_error( 'mskd_messages', 'mskd_error', $result->get_error_message(), 'error' );
			return;
		}

		// Stash the one-time token so it can be shown once after the redirect, then discarded.
		set_transient( 'mskd_api_new_token_' . get_current_user_id(), $result, 60 );
		wp_safe_redirect( admin_url( 'admin.php?page=mskd-api&token_created=1' ) );
		exit;
	}

	/**
	 * Delete (revoke) a token.
	 *
	 * @return void
	 */
	private function handle_delete_token(): void {
		$id = isset( $_POST['token_id'] ) ? (int) $_POST['token_id'] : 0;

		if ( $id > 0 ) {
			$this->token_service->delete_token( $id );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=mskd-api&token_deleted=1' ) );
		exit;
	}

	/**
	 * Render the API Access page.
	 *
	 * @return void
	 */
	public function render(): void {
		$token_service = $this->token_service;

		$transient_key = 'mskd_api_new_token_' . get_current_user_id();
		$new_token     = get_transient( $transient_key );
		if ( $new_token ) {
			delete_transient( $transient_key );
		}

		$tokens = $token_service->list_tokens();

		include MSKD_PLUGIN_DIR . 'admin/partials/api-access.php';
	}

	/**
	 * Get the token service instance.
	 *
	 * @return Token_Service
	 */
	public function get_token_service(): Token_Service {
		return $this->token_service;
	}
}
