<?php
/**
 * Admin Templates Controller
 *
 * Handles template-related admin actions and page rendering.
 *
 * @package MSKD\Admin
 * @since   1.3.0
 */

namespace MSKD\Admin;

use MSKD\Services\Template_Service;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Admin_Templates
 *
 * Controller for templates admin page.
 */
class Admin_Templates {

	/**
	 * Template service instance.
	 *
	 * @var Template_Service
	 */
	private $service;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->service = new Template_Service();
	}

	/**
	 * Handle template-related actions.
	 *
	 * @return void
	 */
	public function handle_actions(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Handle add template.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce checked on line 52.
		if ( isset( $_POST['mskd_add_template'] ) && isset( $_POST['mskd_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mskd_nonce'] ) ), 'mskd_add_template' ) ) {
			$this->handle_add();
		}

		// Handle edit template.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce checked on line 57.
		if ( isset( $_POST['mskd_edit_template'] ) && isset( $_POST['mskd_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mskd_nonce'] ) ), 'mskd_edit_template' ) ) {
			$this->handle_edit();
		}

		// Handle delete template.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified in the if condition below.
		if ( isset( $_GET['action'] ) && 'delete_template' === sanitize_text_field( wp_unslash( $_GET['action'] ) ) && isset( $_GET['id'] ) && isset( $_GET['_wpnonce'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified below.
			$id = intval( $_GET['id'] );
			if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'delete_template_' . $id ) ) {
				$this->handle_delete( $id );
			}
		}

		// Handle duplicate template.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified in the if condition below.
		if ( isset( $_GET['action'] ) && 'duplicate_template' === sanitize_text_field( wp_unslash( $_GET['action'] ) ) && isset( $_GET['id'] ) && isset( $_GET['_wpnonce'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified below.
			$id = intval( $_GET['id'] );
			if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'duplicate_template_' . $id ) ) {
				$this->handle_duplicate( $id );
			}
		}
	}

	/**
	 * Handle add template action.
	 *
	 * @return void
	 */
	private function handle_add(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions() before calling this method.
		$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions() before calling this method.
		$subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
		// Email HTML content must be preserved exactly (including <style> tags for MJML output).
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- Admin-only, nonce-verified email content in handle_actions().
		$content = isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : '';

		// Validate name.
		if ( empty( $name ) ) {
			add_settings_error(
				'mskd_messages',
				'mskd_error',
				__( 'Template name is required.', 'mail-system' ),
				'error'
			);
			return;
		}

		// Create template.
		$template_id = $this->service->create(
			array(
				'name'    => $name,
				'subject' => $subject,
				'content' => $content,
				'type'    => 'custom',
				'status'  => 'active',
			)
		);

		if ( $template_id ) {
			add_settings_error(
				'mskd_messages',
				'mskd_success',
				__( 'Template added successfully.', 'mail-system' ),
				'success'
			);
			wp_safe_redirect( admin_url( 'admin.php?page=mskd-templates' ) );
			exit;
		} else {
			add_settings_error(
				'mskd_messages',
				'mskd_error',
				__( 'Error adding template.', 'mail-system' ),
				'error'
			);
		}
	}

	/**
	 * Handle edit template action.
	 *
	 * @return void
	 */
	private function handle_edit(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions() before calling this method.
		$id = isset( $_POST['template_id'] ) ? intval( $_POST['template_id'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions() before calling this method.
		$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions() before calling this method.
		$subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
		// Email HTML content must be preserved exactly (including <style> tags for MJML output).
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- Admin-only, nonce-verified email content in handle_actions().
		$content = isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : '';

		// Validate name.
		if ( empty( $name ) ) {
			add_settings_error(
				'mskd_messages',
				'mskd_error',
				__( 'Template name is required.', 'mail-system' ),
				'error'
			);
			return;
		}

		// Get template to check type.
		$template = $this->service->get_by_id( $id );
		if ( ! $template ) {
			add_settings_error(
				'mskd_messages',
				'mskd_error',
				__( 'Template not found.', 'mail-system' ),
				'error'
			);
			return;
		}

		// Update template.
		$this->service->update(
			$id,
			array(
				'name'    => $name,
				'subject' => $subject,
				'content' => $content,
			)
		);

		add_settings_error(
			'mskd_messages',
			'mskd_success',
			__( 'Template updated successfully.', 'mail-system' ),
			'success'
		);

		wp_safe_redirect( admin_url( 'admin.php?page=mskd-templates' ) );
		exit;
	}

	/**
	 * Handle delete template action.
	 *
	 * @param int $id Template ID.
	 * @return void
	 */
	private function handle_delete( int $id ): void {
		// Get template to check type.
		$template = $this->service->get_by_id( $id );
		if ( ! $template ) {
			add_settings_error(
				'mskd_messages',
				'mskd_error',
				__( 'Template not found.', 'mail-system' ),
				'error'
			);
			wp_safe_redirect( admin_url( 'admin.php?page=mskd-templates' ) );
			exit;
		}

		// Prevent deletion of predefined templates.
		if ( 'predefined' === $template->type ) {
			add_settings_error(
				'mskd_messages',
				'mskd_error',
				__( 'Predefined templates cannot be deleted.', 'mail-system' ),
				'error'
			);
			wp_safe_redirect( admin_url( 'admin.php?page=mskd-templates' ) );
			exit;
		}

		$this->service->delete( $id );

		add_settings_error(
			'mskd_messages',
			'mskd_success',
			__( 'Template deleted successfully.', 'mail-system' ),
			'success'
		);

		wp_safe_redirect( admin_url( 'admin.php?page=mskd-templates' ) );
		exit;
	}

	/**
	 * Handle duplicate template action.
	 *
	 * @param int $id Template ID.
	 * @return void
	 */
	private function handle_duplicate( int $id ): void {
		$new_id = $this->service->duplicate( $id );

		if ( $new_id ) {
			add_settings_error(
				'mskd_messages',
				'mskd_success',
				__( 'Template duplicated successfully.', 'mail-system' ),
				'success'
			);
		} else {
			add_settings_error(
				'mskd_messages',
				'mskd_error',
				__( 'Error duplicating template.', 'mail-system' ),
				'error'
			);
		}

		wp_safe_redirect( admin_url( 'admin.php?page=mskd-templates' ) );
		exit;
	}

	/**
	 * Render the templates page.
	 *
	 * @return void
	 */
	public function render(): void {
		// Install default templates if needed.
		$this->service->install_defaults();

		include MSKD_PLUGIN_DIR . 'admin/partials/templates.php';
	}

	/**
	 * Get the service instance.
	 *
	 * @return Template_Service
	 */
	public function get_service(): Template_Service {
		return $this->service;
	}
}
