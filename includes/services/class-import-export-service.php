<?php
/**
 * Import/Export Service
 *
 * Handles import and export of subscribers and lists in CSV format.
 *
 * @package MSKD\Services
 * @since   1.2.0
 */

namespace MSKD\Services;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Import_Export_Service
 *
 * Service layer for importing and exporting subscribers and lists.
 */
class Import_Export_Service {

	/**
	 * Supported export formats.
	 *
	 * @var array
	 */
	const SUPPORTED_FORMATS = array( 'csv' );

	/**
	 * CSV delimiter.
	 *
	 * @var string
	 */
	const CSV_DELIMITER = ',';

	/**
	 * CSV enclosure.
	 *
	 * @var string
	 */
	const CSV_ENCLOSURE = '"';

	/**
	 * Maximum file size for imports (5MB).
	 *
	 * @var int
	 */
	const MAX_IMPORT_SIZE = 5242880;

	/**
	 * Subscriber service instance.
	 *
	 * @var Subscriber_Service
	 */
	private $subscriber_service;

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
		$this->subscriber_service = new Subscriber_Service();
		$this->list_service       = new List_Service();
	}

	// =========================================================================
	// Export Methods
	// =========================================================================

	/**
	 * Export subscribers to CSV format.
	 *
	 * @param array $args {
	 *     Optional. Export arguments.
	 *
	 *     @type int    $list_id Filter by list ID.
	 *     @type string $status  Filter by subscriber status.
	 * }
	 * @return string CSV content.
	 */
	public function export_subscribers_csv( array $args = array() ): string {
		$subscribers = $this->get_subscribers_for_export( $args );

		// Define CSV headers.
		$headers = array(
			'email',
			'first_name',
			'last_name',
			'status',
			'lists',
			'created_at',
		);

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen -- Using temp stream for CSV generation.
		$output = fopen( 'php://temp', 'r+' );

		// Write UTF-8 BOM for Excel compatibility.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Using temp stream for CSV generation.
		fwrite( $output, "\xEF\xBB\xBF" );

		// Write headers.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv -- Using temp stream for CSV generation.
		fputcsv( $output, $headers, self::CSV_DELIMITER, self::CSV_ENCLOSURE );

		// Write data rows.
		foreach ( $subscribers as $subscriber ) {
			$lists      = $this->subscriber_service->get_lists( (int) $subscriber->id );
			$list_names = array();
			foreach ( $lists as $list_id ) {
				$list = $this->list_service->get_by_id( (int) $list_id );
				if ( $list ) {
					$list_names[] = $this->sanitize_csv_value( $list->name );
				}
			}

			$row = array(
				$this->sanitize_csv_value( $subscriber->email ),
				$this->sanitize_csv_value( $subscriber->first_name ),
				$this->sanitize_csv_value( $subscriber->last_name ),
				$subscriber->status,
				implode( ';', $list_names ),
				$subscriber->created_at,
			);

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv -- Using temp stream for CSV generation.
			fputcsv( $output, $row, self::CSV_DELIMITER, self::CSV_ENCLOSURE );
		}

		rewind( $output );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Using temp stream for CSV generation.
		$csv = stream_get_contents( $output );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Using temp stream for CSV generation.
		fclose( $output );

		return $csv;
	}

	/**
	 * Export lists to CSV format.
	 *
	 * @return string CSV content.
	 */
	public function export_lists_csv(): string {
		$lists = $this->list_service->get_all_with_counts();

		$headers = array(
			'name',
			'description',
			'subscriber_count',
			'created_at',
		);

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen -- Using temp stream for CSV generation.
		$output = fopen( 'php://temp', 'r+' );

		// Write UTF-8 BOM for Excel compatibility.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Using temp stream for CSV generation.
		fwrite( $output, "\xEF\xBB\xBF" );

		// Write headers.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv -- Using temp stream for CSV generation.
		fputcsv( $output, $headers, self::CSV_DELIMITER, self::CSV_ENCLOSURE );

		foreach ( $lists as $list ) {
			$row = array(
				$this->sanitize_csv_value( $list->name ),
				$this->sanitize_csv_value( $list->description ),
				$list->subscriber_count,
				$list->created_at,
			);

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputcsv -- Using temp stream for CSV generation.
			fputcsv( $output, $row, self::CSV_DELIMITER, self::CSV_ENCLOSURE );
		}

		rewind( $output );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Using temp stream for CSV generation.
		$csv = stream_get_contents( $output );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Using temp stream for CSV generation.
		fclose( $output );

		return $csv;
	}

	/**
	 * Get subscribers for export with optional filtering.
	 *
	 * @param array $args Export arguments.
	 * @return array Array of subscriber objects.
	 */
	private function get_subscribers_for_export( array $args ): array {
		$query_args = array(
			'per_page' => PHP_INT_MAX, // Get all subscribers.
			'page'     => 1,
		);

		if ( ! empty( $args['status'] ) ) {
			$query_args['status'] = $args['status'];
		}

		if ( ! empty( $args['list_id'] ) ) {
			$query_args['list_id'] = (int) $args['list_id'];
		}

		$result = $this->subscriber_service->get_all( $query_args );

		return $result['items'];
	}

	// =========================================================================
	// Import Methods
	// =========================================================================

	/**
	 * Validate an uploaded import file.
	 *
	 * @param array  $file   The $_FILES array element.
	 * @param string $format Expected format (csv or json).
	 * @return array {
	 *     @type bool   $valid   Whether the file is valid.
	 *     @type string $error   Error message if invalid.
	 *     @type string $path    File path if valid.
	 * }
	 */
	public function validate_import_file( array $file, string $format ): array {
		// Check for upload errors.
		if ( UPLOAD_ERR_OK !== $file['error'] ) {
			return array(
				'valid' => false,
				'error' => $this->get_upload_error_message( $file['error'] ),
			);
		}

		// Check file size.
		if ( $file['size'] > self::MAX_IMPORT_SIZE ) {
			return array(
				'valid' => false,
				'error' => __( 'File size exceeds the maximum limit of 5MB.', 'mail-system' ),
			);
		}

		// Check file extension.
		$extension = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
		if ( $extension !== $format ) {
			return array(
				'valid' => false,
				'error' => sprintf(
					/* translators: %s: expected format */
					__( 'Invalid file format. Expected %s file.', 'mail-system' ),
					strtoupper( $format )
				),
			);
		}

		// Validate MIME type.
		$allowed_mimes = array(
			'csv' => array( 'text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel' ),
		);

		$finfo = new \finfo( FILEINFO_MIME_TYPE );
		$mime  = $finfo->file( $file['tmp_name'] );

		if ( ! in_array( $mime, $allowed_mimes[ $format ], true ) ) {
			return array(
				'valid' => false,
				'error' => __( 'Invalid file type. The file content does not match the expected format.', 'mail-system' ),
			);
		}

		return array(
			'valid' => true,
			'path'  => $file['tmp_name'],
		);
	}

	/**
	 * Parse and validate CSV file for subscriber import.
	 *
	 * @param string $file_path Path to the CSV file.
	 * @return array {
	 *     @type bool   $valid    Whether the file is valid.
	 *     @type string $error    Error message if invalid.
	 *     @type array  $rows     Parsed rows if valid.
	 *     @type array  $headers  CSV headers.
	 *     @type int    $total    Total number of rows.
	 *     @type array  $errors   Row-level validation errors.
	 * }
	 */
	public function parse_subscribers_csv( string $file_path ): array {
		$handle = fopen( $file_path, 'r' );

		if ( ! $handle ) {
			return array(
				'valid' => false,
				'error' => __( 'Could not read the file.', 'mail-system' ),
			);
		}

		// Skip UTF-8 BOM if present.
		$bom = fread( $handle, 3 );
		if ( "\xEF\xBB\xBF" !== $bom ) {
			rewind( $handle );
		}

		// Read headers.
		$headers = fgetcsv( $handle, 0, self::CSV_DELIMITER, self::CSV_ENCLOSURE );

		if ( ! $headers ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Reading uploaded file for validation.
			fclose( $handle );
			return array(
				'valid' => false,
				'error' => __( 'Could not read CSV headers.', 'mail-system' ),
			);
		}

		// Normalize headers.
		$headers = array_map( 'strtolower', array_map( 'trim', $headers ) );

		// Check for required 'email' column.
		if ( ! in_array( 'email', $headers, true ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Reading uploaded file for validation.
			fclose( $handle );
			return array(
				'valid' => false,
				'error' => __( 'The CSV file must contain an "email" column.', 'mail-system' ),
			);
		}

		$rows       = array();
		$errors     = array();
		$row_number = 1;

		// phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition -- Standard CSV reading pattern.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fgetcsv -- Reading uploaded file for validation.
		while ( false !== ( $row = fgetcsv( $handle, 0, self::CSV_DELIMITER, self::CSV_ENCLOSURE ) ) ) {
			++$row_number;

			// Skip empty rows.
			if ( count( $row ) === 1 && empty( $row[0] ) ) {
				continue;
			}

			// Map row to associative array.
			$data = array();
			foreach ( $headers as $index => $header ) {
				$data[ $header ] = isset( $row[ $index ] ) ? trim( $row[ $index ] ) : '';
			}

			// Validate email.
			if ( empty( $data['email'] ) || ! is_email( $data['email'] ) ) {
				$errors[] = sprintf(
					/* translators: 1: row number, 2: email value */
					__( 'Row %1$d: Invalid email address "%2$s".', 'mail-system' ),
					$row_number,
					$data['email'] ?? ''
				);
				continue;
			}

			// Validate status if provided.
			if ( ! empty( $data['status'] ) ) {
				$allowed_statuses = array( 'active', 'inactive', 'unsubscribed' );
				if ( ! in_array( strtolower( $data['status'] ), $allowed_statuses, true ) ) {
					$data['status'] = 'active';
				} else {
					$data['status'] = strtolower( $data['status'] );
				}
			} else {
				$data['status'] = 'active';
			}

			$rows[] = $data;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Reading uploaded file for validation.
		fclose( $handle );

		return array(
			'valid'   => true,
			'headers' => $headers,
			'rows'    => $rows,
			'total'   => count( $rows ),
			'errors'  => $errors,
		);
	}

	/**
	 * Import subscribers from parsed data.
	 *
	 * @param array $rows   Parsed subscriber rows.
	 * @param array $options {
	 *     Import options.
	 *
	 *     @type bool $update_existing Whether to update existing subscribers.
	 *     @type bool $assign_lists    Whether to assign subscribers to lists.
	 * }
	 * @return array {
	 *     @type int   $imported Number of imported subscribers.
	 *     @type int   $updated  Number of updated subscribers.
	 *     @type int   $skipped  Number of skipped subscribers.
	 *     @type array $errors   Import errors.
	 * }
	 */
	public function import_subscribers( array $rows, array $options = array() ): array {
		$defaults = array(
			'update_existing' => false,
			'assign_lists'    => true,
			'target_list_ids' => array(),
		);
		$options  = wp_parse_args( $options, $defaults );

		// Validate target list IDs if provided.
		$valid_target_list_ids = array();
		if ( ! empty( $options['target_list_ids'] ) ) {
			foreach ( $options['target_list_ids'] as $list_id ) {
				$list = $this->list_service->get_by_id( (int) $list_id );
				if ( $list ) {
					$valid_target_list_ids[] = (int) $list_id;
				}
			}
		}

		$imported = 0;
		$updated  = 0;
		$skipped  = 0;
		$errors   = array();

		foreach ( $rows as $index => $row ) {
			$email = sanitize_email( $row['email'] );

			// Check if subscriber exists.
			$existing = $this->subscriber_service->get_by_email( $email );

			if ( $existing ) {
				if ( $options['update_existing'] ) {
					// Update existing subscriber.
					$update_data = array();

					if ( ! empty( $row['first_name'] ) ) {
						$update_data['first_name'] = sanitize_text_field( $row['first_name'] );
					}
					if ( ! empty( $row['last_name'] ) ) {
						$update_data['last_name'] = sanitize_text_field( $row['last_name'] );
					}
					if ( ! empty( $row['status'] ) ) {
						$update_data['status'] = sanitize_text_field( $row['status'] );
					}

					if ( ! empty( $update_data ) ) {
						$this->subscriber_service->update( (int) $existing->id, $update_data );
					}

					// Sync lists: use target lists if provided, otherwise use CSV lists.
					if ( ! empty( $valid_target_list_ids ) ) {
						// Replace existing lists with target lists (consistent with new subscriber behavior).
						$this->subscriber_service->sync_lists( (int) $existing->id, $valid_target_list_ids );
					} elseif ( $options['assign_lists'] && ! empty( $row['lists'] ) ) {
						$list_ids = $this->get_list_ids_from_names( $row['lists'] );
						if ( ! empty( $list_ids ) ) {
							// Merge with existing lists.
							$existing_lists = $this->subscriber_service->get_lists( (int) $existing->id );
							$merged_lists   = array_unique( array_merge( $existing_lists, $list_ids ) );
							$this->subscriber_service->sync_lists( (int) $existing->id, $merged_lists );
						}
					}

					++$updated;
				} else {
					++$skipped;
				}
				continue;
			}

			// Create new subscriber.
			$subscriber_data = array(
				'email'      => $email,
				'first_name' => isset( $row['first_name'] ) ? sanitize_text_field( $row['first_name'] ) : '',
				'last_name'  => isset( $row['last_name'] ) ? sanitize_text_field( $row['last_name'] ) : '',
				'status'     => isset( $row['status'] ) ? sanitize_text_field( $row['status'] ) : 'active',
			);

			$subscriber_id = $this->subscriber_service->create( $subscriber_data );

			if ( ! $subscriber_id ) {
				$errors[] = sprintf(
					/* translators: %s: email address */
					__( 'Failed to import subscriber: %s', 'mail-system' ),
					$email
				);
				continue;
			}

			// Assign lists: use target lists if provided, otherwise use CSV lists.
			if ( ! empty( $valid_target_list_ids ) ) {
				$this->subscriber_service->sync_lists( $subscriber_id, $valid_target_list_ids );
			} elseif ( $options['assign_lists'] && ! empty( $row['lists'] ) ) {
				$list_ids = $this->get_list_ids_from_names( $row['lists'] );
				if ( ! empty( $list_ids ) ) {
					$this->subscriber_service->sync_lists( $subscriber_id, $list_ids );
				}
			}

			++$imported;
		}

		return array(
			'imported' => $imported,
			'updated'  => $updated,
			'skipped'  => $skipped,
			'errors'   => $errors,
		);
	}

	/**
	 * Parse and validate CSV file for list import.
	 *
	 * @param string $file_path Path to the CSV file.
	 * @return array Parse result with valid, headers, rows, total, errors.
	 */
	public function parse_lists_csv( string $file_path ): array {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen -- Reading uploaded file for validation.
		$handle = fopen( $file_path, 'r' );

		if ( ! $handle ) {
			return array(
				'valid' => false,
				'error' => __( 'Could not read the file.', 'mail-system' ),
			);
		}

		// Skip UTF-8 BOM if present.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- Reading uploaded file for validation.
		$bom = fread( $handle, 3 );
		if ( "\xEF\xBB\xBF" !== $bom ) {
			rewind( $handle );
		}

		// Read headers.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fgetcsv -- Reading uploaded file for validation.
		$headers = fgetcsv( $handle, 0, self::CSV_DELIMITER, self::CSV_ENCLOSURE );

		if ( ! $headers ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Reading uploaded file for validation.
			fclose( $handle );
			return array(
				'valid' => false,
				'error' => __( 'Could not read CSV headers.', 'mail-system' ),
			);
		}

		// Normalize headers.
		$headers = array_map( 'strtolower', array_map( 'trim', $headers ) );

		// Check for required 'name' column.
		if ( ! in_array( 'name', $headers, true ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Reading uploaded file for validation.
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Reading uploaded file for validation.
			fclose( $handle );
			return array(
				'valid' => false,
				'error' => __( 'The CSV file must contain a "name" column.', 'mail-system' ),
			);
		}

		$rows       = array();
		$errors     = array();
		$row_number = 1;

		// phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition -- Standard CSV reading pattern.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fgetcsv -- Reading uploaded file for validation.
		while ( false !== ( $row = fgetcsv( $handle, 0, self::CSV_DELIMITER, self::CSV_ENCLOSURE ) ) ) {
			++$row_number;

			// Skip empty rows.
			if ( count( $row ) === 1 && empty( $row[0] ) ) {
				continue;
			}

			// Map row to associative array.
			$data = array();
			foreach ( $headers as $index => $header ) {
				$data[ $header ] = isset( $row[ $index ] ) ? trim( $row[ $index ] ) : '';
			}

			// Validate name.
			if ( empty( $data['name'] ) ) {
				$errors[] = sprintf(
					/* translators: %d: row number */
					__( 'Row %d: List name is required.', 'mail-system' ),
					$row_number
				);
				continue;
			}

			$rows[] = $data;
		}

		fclose( $handle );

		return array(
			'valid'   => true,
			'headers' => $headers,
			'rows'    => $rows,
			'total'   => count( $rows ),
			'errors'  => $errors,
		);
	}

	/**
	 * Import lists from parsed data.
	 *
	 * @param array $rows   Parsed list rows.
	 * @param array $options {
	 *     Import options.
	 *
	 *     @type bool $skip_existing Whether to skip existing lists (by name).
	 * }
	 * @return array Import result.
	 */
	public function import_lists( array $rows, array $options = array() ): array {
		$defaults = array(
			'skip_existing' => true,
		);
		$options  = wp_parse_args( $options, $defaults );

		$imported = 0;
		$skipped  = 0;
		$errors   = array();

		foreach ( $rows as $row ) {
			$name = sanitize_text_field( $row['name'] );

			// Check if list exists.
			$existing = $this->list_service->get_by_name( $name );

			if ( $existing ) {
				if ( $options['skip_existing'] ) {
					++$skipped;
					continue;
				}
			}

			// Create new list.
			$list_data = array(
				'name'        => $name,
				'description' => isset( $row['description'] ) ? sanitize_textarea_field( $row['description'] ) : '',
			);

			$list_id = $this->list_service->create( $list_data );

			if ( ! $list_id ) {
				$errors[] = sprintf(
					/* translators: %s: list name */
					__( 'Failed to import list: %s', 'mail-system' ),
					$name
				);
				continue;
			}

			++$imported;
		}

		return array(
			'imported' => $imported,
			'skipped'  => $skipped,
			'errors'   => $errors,
		);
	}

	// =========================================================================
	// Helper Methods
	// =========================================================================

	/**
	 * Sanitize a value for safe CSV export.
	 *
	 * Prevents CSV formula injection by prefixing dangerous leading characters
	 * with a single quote. In spreadsheet applications, values starting with
	 * '=', '+', '-', '@', tab, or carriage return can execute formulas when opened.
	 *
	 * @param string|null $value The value to sanitize.
	 * @return string Sanitized value safe for CSV export.
	 */
	private function sanitize_csv_value( $value ): string {
		// Ensure we have a string to work with.
		if ( null === $value || '' === $value ) {
			return '';
		}

		$value = (string) $value;

		// Characters that can trigger formula execution in spreadsheet applications.
		$dangerous_chars = array( '=', '+', '-', '@', "\t", "\r", "\n", '|' );

		// Check if the value starts with a dangerous character.
		if ( isset( $value[0] ) && in_array( $value[0], $dangerous_chars, true ) ) {
			// Prefix with single quote to prevent formula execution.
			return "'" . $value;
		}

		return $value;
	}

	/**
	 * Get list IDs from a semicolon-separated string of list names.
	 *
	 * Creates lists if they don't exist.
	 *
	 * @param string $lists_string Semicolon-separated list names.
	 * @return array Array of list IDs.
	 */
	private function get_list_ids_from_names( string $lists_string ): array {
		$list_names = array_filter( array_map( 'trim', explode( ';', $lists_string ) ) );
		$list_ids   = array();

		foreach ( $list_names as $name ) {
			$list = $this->list_service->get_by_name( $name );

			if ( $list ) {
				$list_ids[] = (int) $list->id;
			} else {
				// Create the list if it doesn't exist.
				$new_list_id = $this->list_service->create( array( 'name' => $name ) );
				if ( $new_list_id ) {
					$list_ids[] = $new_list_id;
				}
			}
		}

		return $list_ids;
	}

	/**
	 * Get human-readable upload error message.
	 *
	 * @param int $error_code PHP upload error code.
	 * @return string Error message.
	 */
	private function get_upload_error_message( int $error_code ): string {
		$messages = array(
			UPLOAD_ERR_INI_SIZE   => __( 'File exceeds the maximum upload size.', 'mail-system' ),
			UPLOAD_ERR_FORM_SIZE  => __( 'File exceeds the maximum form size.', 'mail-system' ),
			UPLOAD_ERR_PARTIAL    => __( 'File was only partially uploaded.', 'mail-system' ),
			UPLOAD_ERR_NO_FILE    => __( 'No file was uploaded.', 'mail-system' ),
			UPLOAD_ERR_NO_TMP_DIR => __( 'Missing temporary folder.', 'mail-system' ),
			UPLOAD_ERR_CANT_WRITE => __( 'Failed to write file to disk.', 'mail-system' ),
			UPLOAD_ERR_EXTENSION  => __( 'A PHP extension stopped the file upload.', 'mail-system' ),
		);

		return $messages[ $error_code ] ?? __( 'Unknown upload error.', 'mail-system' );
	}
}
