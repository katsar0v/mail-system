<?php
/**
 * Admin Import/Export Controller
 *
 * Handles import/export-related admin actions and page rendering.
 *
 * @package MSKD\Admin
 * @since   1.2.0
 */

namespace MSKD\Admin;

use MSKD\Services\Import_Export_Service;
use MSKD\Services\List_Service;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Admin_Import_Export
 *
 * Controller for import/export admin page.
 */
class Admin_Import_Export {

	/**
	 * Import/Export service instance.
	 *
	 * @var Import_Export_Service
	 */
	private $service;

	/**
	 * List service instance.
	 *
	 * @var List_Service
	 */
	private $list_service;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->service      = new Import_Export_Service();
		$this->list_service = new List_Service();
	}

	/**
	 * Handle import/export-related actions.
	 *
	 * @return void
	 */
	public function handle_actions(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Handle export request.
		if ( isset( $_POST['mskd_export'], $_POST['mskd_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mskd_nonce'] ) ), 'mskd_export' ) ) {
			$this->handle_export();
		}

		// Handle import request.
		if ( isset( $_POST['mskd_import'], $_POST['mskd_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mskd_nonce'] ) ), 'mskd_import' ) ) {
			$this->handle_import();
		}
	}

	/**
	 * Handle export action.
	 *
	 * Called after nonce verification in handle_actions().
	 *
	 * @return void
	 */
	private function handle_export(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions().
		$type = isset( $_POST['export_type'] ) ? sanitize_text_field( wp_unslash( $_POST['export_type'] ) ) : 'subscribers';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions().
		$format = isset( $_POST['export_format'] ) ? sanitize_text_field( wp_unslash( $_POST['export_format'] ) ) : 'csv';

		// Validate format.
		if ( ! in_array( $format, array( 'csv', 'json' ), true ) ) {
			$format = 'csv';
		}

		$args = array();

		// Get filter options for subscribers.
		if ( 'subscribers' === $type ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions().
			if ( ! empty( $_POST['export_list_id'] ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions().
				$args['list_id'] = intval( $_POST['export_list_id'] );
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions().
			if ( ! empty( $_POST['export_status'] ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions().
				$args['status'] = sanitize_text_field( wp_unslash( $_POST['export_status'] ) );
			}
		}

		// Generate export content.
		if ( 'subscribers' === $type ) {
			$content  = $this->service->export_subscribers_csv( $args );
			$filename = 'subscribers-' . gmdate( 'Y-m-d' ) . '.csv';
			$mime     = 'text/csv';
		} else {
			$content  = $this->service->export_lists_csv();
			$filename = 'lists-' . gmdate( 'Y-m-d' ) . '.csv';
			$mime     = 'text/csv';
		}

		// Send file download.
		header( 'Content-Type: ' . $mime . '; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . strlen( $content ) );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// Clean output buffer.
		while ( ob_get_level() ) {
			ob_end_clean();
		}

		// Direct output is safe here: this is a binary file download (CSV)
		// with appropriate Content-Type and Content-Disposition headers set above.
		// The content is generated internally by the service, not from user input.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Binary file download, content is generated internally.
		echo $content;
		exit;
	}

	/**
	 * Handle import action.
	 *
	 * Called after nonce verification in handle_actions().
	 *
	 * @return void
	 */
	private function handle_import(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions().
		$type = isset( $_POST['import_type'] ) ? sanitize_text_field( wp_unslash( $_POST['import_type'] ) ) : 'subscribers';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions().
		// Force CSV format.
		$format = 'csv';

		// Check for uploaded file.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions().
		if ( ! isset( $_FILES['import_file'] ) || empty( $_FILES['import_file']['name'] ) ) {
			add_settings_error(
				'mskd_messages',
				'mskd_error',
				__( 'Please select a file to import.', 'mail-system' ),
				'error'
			);
			return;
		}

		// Validate file. The service validates file type, size, and MIME type.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified in handle_actions(). File array is validated by service.
		$validation = $this->service->validate_import_file( $_FILES['import_file'], $format );

		if ( ! $validation['valid'] ) {
			add_settings_error(
				'mskd_messages',
				'mskd_error',
				$validation['error'],
				'error'
			);
			return;
		}

		$file_path = $validation['path'];

		// Parse file.
		if ( 'subscribers' === $type ) {
			$parsed = $this->service->parse_subscribers_csv( $file_path );
		} else {
			$parsed = $this->service->parse_lists_csv( $file_path );
		}

		if ( ! $parsed['valid'] ) {
			add_settings_error(
				'mskd_messages',
				'mskd_error',
				$parsed['error'],
				'error'
			);
			return;
		}

		// Show validation errors as warnings.
		if ( ! empty( $parsed['errors'] ) ) {
			foreach ( array_slice( $parsed['errors'], 0, 5 ) as $error ) {
				add_settings_error(
					'mskd_messages',
					'mskd_warning',
					$error,
					'warning'
				);
			}
			if ( count( $parsed['errors'] ) > 5 ) {
				add_settings_error(
					'mskd_messages',
					'mskd_warning',
					sprintf(
						/* translators: %d: number of additional errors */
						__( '... and %d more validation errors.', 'mail-system' ),
						count( $parsed['errors'] ) - 5
					),
					'warning'
				);
			}
		}

		// No valid rows to import.
		if ( empty( $parsed['rows'] ) ) {
			add_settings_error(
				'mskd_messages',
				'mskd_error',
				__( 'No valid records found to import.', 'mail-system' ),
				'error'
			);
			return;
		}

		// Import options.
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_actions().
		$target_list_ids = array();
		if ( ! empty( $_POST['target_lists'] ) && is_array( $_POST['target_lists'] ) ) {
			$target_list_ids = array_map( 'intval', $_POST['target_lists'] );
			$target_list_ids = array_filter( $target_list_ids ); // Remove zeros.
		}

		$options = array(
			'update_existing' => isset( $_POST['update_existing'] ) && '1' === $_POST['update_existing'],
			'assign_lists'    => isset( $_POST['assign_lists'] ) && '1' === $_POST['assign_lists'],
			'target_list_ids' => $target_list_ids,
			'skip_existing'   => true,
		);
		// phpcs:enable

		// Perform import.
		if ( 'subscribers' === $type ) {
			$result = $this->service->import_subscribers( $parsed['rows'], $options );

			// Build success message.
			$message_parts = array();
			if ( $result['imported'] > 0 ) {
				$message_parts[] = sprintf(
					/* translators: %d: number of imported subscribers */
					_n(
						'%d subscriber imported.',
						'%d subscribers imported.',
						$result['imported'],
						'mail-system'
					),
					$result['imported']
				);
			}
			if ( $result['updated'] > 0 ) {
				$message_parts[] = sprintf(
					/* translators: %d: number of updated subscribers */
					_n(
						'%d subscriber updated.',
						'%d subscribers updated.',
						$result['updated'],
						'mail-system'
					),
					$result['updated']
				);
			}
			if ( $result['skipped'] > 0 ) {
				$message_parts[] = sprintf(
					/* translators: %d: number of skipped subscribers */
					_n(
						'%d subscriber skipped (already exists).',
						'%d subscribers skipped (already exist).',
						$result['skipped'],
						'mail-system'
					),
					$result['skipped']
				);
			}

			if ( ! empty( $message_parts ) ) {
				add_settings_error(
					'mskd_messages',
					'mskd_success',
					implode( ' ', $message_parts ),
					'success'
				);
			}
		} else {
			$result = $this->service->import_lists( $parsed['rows'], $options );

			// Build success message.
			$message_parts = array();
			if ( $result['imported'] > 0 ) {
				$message_parts[] = sprintf(
					/* translators: %d: number of imported lists */
					_n(
						'%d list imported.',
						'%d lists imported.',
						$result['imported'],
						'mail-system'
					),
					$result['imported']
				);
			}
			if ( $result['skipped'] > 0 ) {
				$message_parts[] = sprintf(
					/* translators: %d: number of skipped lists */
					_n(
						'%d list skipped (already exists).',
						'%d lists skipped (already exist).',
						$result['skipped'],
						'mail-system'
					),
					$result['skipped']
				);
			}

			if ( ! empty( $message_parts ) ) {
				add_settings_error(
					'mskd_messages',
					'mskd_success',
					implode( ' ', $message_parts ),
					'success'
				);
			}
		}

		// Show import errors.
		if ( ! empty( $result['errors'] ) ) {
			foreach ( array_slice( $result['errors'], 0, 5 ) as $error ) {
				add_settings_error(
					'mskd_messages',
					'mskd_error',
					$error,
					'error'
				);
			}
		}
	}

	/**
	 * Render the import/export page.
	 *
	 * @return void
	 */
	public function render(): void {
		include MSKD_PLUGIN_DIR . 'admin/partials/import-export.php';
	}

	/**
	 * Get the service instance.
	 *
	 * @return Import_Export_Service
	 */
	public function get_service(): Import_Export_Service {
		return $this->service;
	}
}
