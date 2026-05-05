<?php
/**
 * Template Service
 *
 * Handles all email template-related database operations.
 *
 * @package MSKD\Services
 * @since   1.3.0
 */

namespace MSKD\Services;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Template_Service
 *
 * Service layer for email template CRUD operations.
 */
class Template_Service {

	/**
	 * WordPress database instance.
	 *
	 * @var \wpdb
	 */
	private $wpdb;

	/**
	 * Templates table name.
	 *
	 * @var string
	 */
	private $table;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb  = $wpdb;
		$this->table = $wpdb->prefix . 'mskd_templates';
	}

	/**
	 * Get all templates.
	 *
	 * @param array $args {
	 *     Optional. Arguments for filtering templates.
	 *
	 *     @type string $orderby Column to order by. Default 'name'.
	 *     @type string $order   Order direction (ASC or DESC). Default 'ASC'.
	 *     @type string $type    Filter by type (predefined, custom). Default empty.
	 *     @type string $status  Filter by status (active, inactive). Default empty.
	 * }
	 * @return array Array of template objects.
	 */
	public function get_all( array $args = array() ): array {
		$defaults = array(
			'orderby' => 'name',
			'order'   => 'ASC',
			'type'    => '',
			'status'  => '',
		);

		$args = wp_parse_args( $args, $defaults );

		// Validate orderby to prevent SQL injection.
		$allowed_orderby = array( 'id', 'name', 'type', 'status', 'created_at', 'updated_at' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'name';
		$order           = 'DESC' === strtoupper( $args['order'] ) ? 'DESC' : 'ASC';

		$where  = array();
		$values = array();

		if ( ! empty( $args['type'] ) ) {
			$where[]  = 'type = %s';
			$values[] = $args['type'];
		}

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$values[] = $args['status'];
		}

		$where_clause = '';
		if ( ! empty( $where ) ) {
			$where_clause = 'WHERE ' . implode( ' AND ', $where );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is hardcoded and safe.
		$query = "SELECT * FROM {$this->table} {$where_clause} ORDER BY {$orderby} {$order}";

		if ( ! empty( $values ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Using prepare with interpolated table name is necessary here.
			$query = $this->wpdb->prepare( $query, ...$values );
		}

		$results = $this->wpdb->get_results( $query );

		return $results ? $results : array();
	}

	/**
	 * Get a template by ID.
	 *
	 * @param int $id Template ID.
	 * @return object|null Template object or null if not found.
	 */
	public function get_by_id( int $id ): ?object {
		return $this->wpdb->get_row(
			$this->wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is hardcoded and safe.
				"SELECT * FROM {$this->table} WHERE id = %d",
				$id
			)
		);
	}

	/**
	 * Get a template by name.
	 *
	 * @param string $name Template name.
	 * @return object|null Template object or null if not found.
	 */
	public function get_by_name( string $name ): ?object {
		return $this->wpdb->get_row(
			$this->wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is hardcoded and safe.
				"SELECT * FROM {$this->table} WHERE name = %s",
				$name
			)
		);
	}

	/**
	 * Create a new template.
	 *
	 * @param array $data {
	 *     Template data.
	 *
	 *     @type string $name         Required. Template name.
	 *     @type string $subject      Optional. Email subject.
	 *     @type string $content      Required. HTML content.
	 *     @type string $json_content Optional. JSON content for the editor.
	 *     @type string $thumbnail    Optional. Thumbnail URL.
	 *     @type string $type         Optional. Template type (predefined, custom).
	 *     @type string $status       Optional. Template status (active, inactive).
	 * }
	 * @return int|false Template ID on success, false on failure.
	 */
	public function create( array $data ) {
		$defaults = array(
			'name'         => '',
			'subject'      => '',
			'content'      => '',
			'json_content' => null,
			'thumbnail'    => '',
			'type'         => 'custom',
			'status'       => 'active',
		);

		$data = wp_parse_args( $data, $defaults );

		$result = $this->wpdb->insert(
			$this->table,
			array(
				'name'         => $data['name'],
				'subject'      => $data['subject'],
				'content'      => $data['content'],
				'json_content' => $data['json_content'],
				'thumbnail'    => $data['thumbnail'],
				'type'         => $data['type'],
				'status'       => $data['status'],
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( $result ) {
			return $this->wpdb->insert_id;
		}

		return false;
	}

	/**
	 * Update a template.
	 *
	 * @param int   $id   Template ID.
	 * @param array $data Data to update.
	 * @return bool True on success, false on failure.
	 */
	public function update( int $id, array $data ): bool {
		$allowed_fields = array( 'name', 'subject', 'content', 'json_content', 'thumbnail', 'type', 'status' );
		$update_data    = array();
		$format         = array();

		foreach ( $allowed_fields as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$update_data[ $field ] = $data[ $field ];
				$format[]              = '%s';
			}
		}

		if ( empty( $update_data ) ) {
			return false;
		}

		$result = $this->wpdb->update(
			$this->table,
			$update_data,
			array( 'id' => $id ),
			$format,
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Delete a template.
	 *
	 * @param int $id Template ID.
	 * @return bool True on success, false on failure.
	 */
	public function delete( int $id ): bool {
		$result = $this->wpdb->delete(
			$this->table,
			array( 'id' => $id ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Get total template count.
	 *
	 * @param string $type   Optional. Filter by type.
	 * @param string $status Optional. Filter by status.
	 * @return int Total count.
	 */
	public function count( string $type = '', string $status = '' ): int {
		$where  = array();
		$values = array();

		if ( ! empty( $type ) ) {
			$where[]  = 'type = %s';
			$values[] = $type;
		}

		if ( ! empty( $status ) ) {
			$where[]  = 'status = %s';
			$values[] = $status;
		}

		$where_clause = '';
		if ( ! empty( $where ) ) {
			$where_clause = 'WHERE ' . implode( ' AND ', $where );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is hardcoded and safe.
		$query = "SELECT COUNT(*) FROM {$this->table} {$where_clause}";

		if ( ! empty( $values ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Using prepare with interpolated table name is necessary here.
			$query = $this->wpdb->prepare( $query, ...$values );
		}

		return (int) $this->wpdb->get_var( $query );
	}

	/**
	 * Duplicate a template.
	 *
	 * @param int $id Template ID to duplicate.
	 * @return int|false New template ID on success, false on failure.
	 */
	public function duplicate( int $id ) {
		$template = $this->get_by_id( $id );

		if ( ! $template ) {
			return false;
		}

		$copy_suffix = __( ' (Copy)', 'mail-system' );
		$new_name    = $template->name . $copy_suffix;

		return $this->create(
			array(
				'name'         => $new_name,
				'subject'      => $template->subject,
				'content'      => $template->content,
				'json_content' => $template->json_content,
				'thumbnail'    => $template->thumbnail,
				'type'         => 'custom', // Duplicates are always custom.
				'status'       => $template->status,
			)
		);
	}

	/**
	 * Get predefined templates.
	 *
	 * @return array Array of predefined template objects.
	 */
	public function get_predefined(): array {
		return $this->get_all(
			array(
				'type'   => 'predefined',
				'status' => 'active',
			)
		);
	}

	/**
	 * Get custom templates.
	 *
	 * @return array Array of custom template objects.
	 */
	public function get_custom(): array {
		return $this->get_all(
			array(
				'type' => 'custom',
			)
		);
	}

	/**
	 * Get active templates (both predefined and custom).
	 *
	 * @return array Array of active template objects.
	 */
	public function get_active(): array {
		return $this->get_all(
			array(
				'status' => 'active',
			)
		);
	}

	/**
	 * Install default predefined templates.
	 *
	 * @return void
	 */
	public function install_defaults(): void {
		// Check if predefined templates already exist.
		if ( $this->count( 'predefined' ) > 0 ) {
			return;
		}

		$default_templates = $this->get_default_templates();

		foreach ( $default_templates as $template ) {
			$this->create( $template );
		}
	}

	/**
	 * Get default predefined templates data.
	 *
	 * @return array Array of default template data.
	 */
	private function get_default_templates(): array {
		return array(
			array(
				'name'         => __( 'Blank Template', 'mail-system' ),
				'subject'      => '',
				'content'      => '',
				'json_content' => null,
				'thumbnail'    => '',
				'type'         => 'predefined',
				'status'       => 'active',
			),
			array(
				'name'         => __( 'Newsletter', 'mail-system' ),
				'subject'      => __( 'Your Newsletter Title', 'mail-system' ),
				'content'      => $this->get_newsletter_template_html(),
				'json_content' => null,
				'thumbnail'    => '',
				'type'         => 'predefined',
				'status'       => 'active',
			),
			array(
				'name'         => __( 'Welcome Email', 'mail-system' ),
				'subject'      => __( 'Welcome to {site_name}!', 'mail-system' ),
				'content'      => $this->get_welcome_template_html(),
				'json_content' => null,
				'thumbnail'    => '',
				'type'         => 'predefined',
				'status'       => 'active',
			),
			array(
				'name'         => __( 'Promotional', 'mail-system' ),
				'subject'      => __( 'Special Offer Just for You!', 'mail-system' ),
				'content'      => $this->get_promotional_template_html(),
				'json_content' => null,
				'thumbnail'    => '',
				'type'         => 'predefined',
				'status'       => 'active',
			),
		);
	}

	/**
	 * Get newsletter template HTML.
	 *
	 * @return string Template HTML.
	 */
	private function get_newsletter_template_html(): string {
		return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #f4f4f4;">
        <tr>
            <td align="center" style="padding: 40px 0;">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="background-color: #ffffff; border-radius: 8px;">
                    <tr>
                        <td style="padding: 40px 30px; text-align: center; background-color: #2271b1; border-radius: 8px 8px 0 0;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 28px;">Newsletter Title</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 40px 30px;">
                            <p style="color: #333333; font-size: 16px; line-height: 1.6;">Hello {first_name},</p>
                            <p style="color: #333333; font-size: 16px; line-height: 1.6;">Your newsletter content goes here. Share your latest news, updates, and valuable content with your subscribers.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 0 30px 40px;">
                            <p style="color: #666666; font-size: 14px; line-height: 1.6;">Best regards,<br>Your Team</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 20px 30px; background-color: #f8f8f8; border-radius: 0 0 8px 8px; text-align: center;">
                            <p style="color: #999999; font-size: 12px; margin: 0;">{unsubscribe_link}</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
	}

	/**
	 * Get welcome email template HTML.
	 *
	 * @return string Template HTML.
	 */
	private function get_welcome_template_html(): string {
		return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #f4f4f4;">
        <tr>
            <td align="center" style="padding: 40px 0;">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="background-color: #ffffff; border-radius: 8px;">
                    <tr>
                        <td style="padding: 40px 30px; text-align: center; background-color: #00a32a; border-radius: 8px 8px 0 0;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 28px;">Welcome!</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 40px 30px;">
                            <p style="color: #333333; font-size: 16px; line-height: 1.6;">Hello {first_name},</p>
                            <p style="color: #333333; font-size: 16px; line-height: 1.6;">Thank you for joining us! We are excited to have you as part of our community.</p>
                            <p style="color: #333333; font-size: 16px; line-height: 1.6;">Here is what you can expect from us:</p>
                            <ul style="color: #333333; font-size: 16px; line-height: 1.8;">
                                <li>Regular updates and news</li>
                                <li>Exclusive content</li>
                                <li>Special offers</li>
                            </ul>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 0 30px 40px;">
                            <p style="color: #666666; font-size: 14px; line-height: 1.6;">Welcome aboard!<br>Your Team</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 20px 30px; background-color: #f8f8f8; border-radius: 0 0 8px 8px; text-align: center;">
                            <p style="color: #999999; font-size: 12px; margin: 0;">{unsubscribe_link}</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
	}

	/**
	 * Get promotional template HTML.
	 *
	 * @return string Template HTML.
	 */
	private function get_promotional_template_html(): string {
		return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #f4f4f4;">
        <tr>
            <td align="center" style="padding: 40px 0;">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="background-color: #ffffff; border-radius: 8px;">
                    <tr>
                        <td style="padding: 40px 30px; text-align: center; background-color: #d63638; border-radius: 8px 8px 0 0;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 28px;">Special Offer!</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 40px 30px; text-align: center;">
                            <p style="color: #333333; font-size: 18px; line-height: 1.6;">Hello {first_name},</p>
                            <p style="color: #333333; font-size: 16px; line-height: 1.6;">We have a special offer just for you!</p>
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 30px auto;">
                                <tr>
                                    <td style="background-color: #d63638; padding: 15px 40px; border-radius: 5px;">
                                        <a href="#" style="color: #ffffff; text-decoration: none; font-size: 18px; font-weight: bold;">Shop Now</a>
                                    </td>
                                </tr>
                            </table>
                            <p style="color: #666666; font-size: 14px;">Offer expires soon. Don\'t miss out!</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 20px 30px; background-color: #f8f8f8; border-radius: 0 0 8px 8px; text-align: center;">
                            <p style="color: #999999; font-size: 12px; margin: 0;">{unsubscribe_link}</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
	}
}
