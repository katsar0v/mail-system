<?php
/**
 * Subscriber Service
 *
 * Handles all subscriber-related database operations.
 *
 * @package MSKD\Services
 * @since   1.1.0
 */

namespace MSKD\Services;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Subscriber_Service
 *
 * Service layer for subscriber CRUD operations.
 */
class Subscriber_Service {

	/**
	 * Subscriber source: internal (form signup).
	 */
	const SOURCE_INTERNAL = 'internal';

	/**
	 * Subscriber source: external (from list provider callbacks).
	 */
	const SOURCE_EXTERNAL = 'external';

	/**
	 * Subscriber source: one-time email recipient.
	 */
	const SOURCE_ONE_TIME = 'one_time';

	/**
	 * WordPress database instance.
	 *
	 * @var \wpdb
	 */
	private $wpdb;

	/**
	 * Subscribers table name.
	 *
	 * @var string
	 */
	private $table;

	/**
	 * Subscriber-list pivot table name.
	 *
	 * @var string
	 */
	private $pivot_table;

	/**
	 * Queue table name.
	 *
	 * @var string
	 */
	private $queue_table;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb        = $wpdb;
		$this->table       = $wpdb->prefix . 'mskd_subscribers';
		$this->pivot_table = $wpdb->prefix . 'mskd_subscriber_list';
		$this->queue_table = $wpdb->prefix . 'mskd_queue';
	}

	/**
	 * Get all subscribers with optional filtering and pagination.
	 *
	 * @param array $args {
	 *     Optional. Arguments for filtering subscribers.
	 *
	 *     @type string $status   Filter by status (active, inactive, unsubscribed).
	 *     @type int    $list_id  Filter by list ID.
	 *     @type string $search   Search term for email, first_name, or last_name.
	 *     @type int    $per_page Number of results per page. Default 20.
	 *     @type int    $page     Current page number. Default 1.
	 *     @type string $orderby  Column to order by. Default 'created_at'.
	 *     @type string $order    Order direction (ASC or DESC). Default 'DESC'.
	 * }
	 * @return array {
	 *     @type array $items      Array of subscriber objects.
	 *     @type int   $total      Total number of subscribers matching criteria.
	 *     @type int   $pages      Total number of pages.
	 * }
	 */
	public function get_all( array $args = array() ): array {
		$defaults = array(
			'status'   => '',
			'list_id'  => 0,
			'search'   => '',
			'per_page' => 20,
			'page'     => 1,
			'orderby'  => 'created_at',
			'order'    => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		// Build WHERE clause.
		$where  = array( '1=1' );
		$values = array();

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 's.status = %s';
			$values[] = $args['status'];
		}

		if ( ! empty( $args['search'] ) ) {
			$where[]  = '(s.email LIKE %s OR s.first_name LIKE %s OR s.last_name LIKE %s)';
			$search   = '%' . $this->wpdb->esc_like( $args['search'] ) . '%';
			$values[] = $search;
			$values[] = $search;
			$values[] = $search;
		}

		// Handle list filtering with JOIN.
		$join = '';
		if ( ! empty( $args['list_id'] ) ) {
			$join     = "INNER JOIN {$this->pivot_table} sl ON s.id = sl.subscriber_id";
			$where[]  = 'sl.list_id = %d';
			$values[] = $args['list_id'];
		}

		$where_sql = implode( ' AND ', $where );

		// Validate orderby to prevent SQL injection.
		$allowed_orderby = array( 'id', 'email', 'first_name', 'last_name', 'status', 'created_at' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
		$order           = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		// Get total count.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is hardcoded and safe.
		$count_sql = "SELECT COUNT(DISTINCT s.id) FROM {$this->table} s {$join} WHERE {$where_sql}";
		if ( ! empty( $values ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Using prepare with interpolated table name is necessary here.
			$count_sql = $this->wpdb->prepare( $count_sql, $values );
		}
		$total = (int) $this->wpdb->get_var( $count_sql );

		// Calculate pagination.
		$per_page = max( 1, (int) $args['per_page'] );
		$page     = max( 1, (int) $args['page'] );
		$pages    = ceil( $total / $per_page );
		$offset   = ( $page - 1 ) * $per_page;

		// Get items.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is hardcoded and safe.
		$sql = "SELECT DISTINCT s.* FROM {$this->table} s {$join} WHERE {$where_sql} ORDER BY s.{$orderby} {$order} LIMIT %d OFFSET %d";

		$query_values   = $values;
		$query_values[] = $per_page;
		$query_values[] = $offset;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Using prepare with interpolated table name is necessary here.
		$items = $this->wpdb->get_results( $this->wpdb->prepare( $sql, $query_values ) );

		return array(
			'items' => $items ? $items : array(),
			'total' => $total,
			'pages' => $pages,
		);
	}

	/**
	 * Get a subscriber by ID.
	 *
	 * @param int $id Subscriber ID.
	 * @return object|null Subscriber object or null if not found.
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
	 * Get a subscriber by email.
	 *
	 * @param string $email Subscriber email.
	 * @return object|null Subscriber object or null if not found.
	 */
	public function get_by_email( string $email ): ?object {
		return $this->wpdb->get_row(
			$this->wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is hardcoded and safe.
				"SELECT * FROM {$this->table} WHERE email = %s",
				$email
			)
		);
	}

	/**
	 * Get a subscriber by unsubscribe token.
	 *
	 * @param string $token Unsubscribe token.
	 * @return object|null Subscriber object or null if not found.
	 */
	public function get_by_token( string $token ): ?object {
		if ( empty( $token ) || strlen( $token ) !== 32 ) {
			return null;
		}

		return $this->wpdb->get_row(
			$this->wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is hardcoded and safe.
				"SELECT * FROM {$this->table} WHERE unsubscribe_token = %s",
				$token
			)
		);
	}

	/**
	 * Get or create a subscriber by email.
	 *
	 * If subscriber exists, returns existing record (preserves current status).
	 * If not, creates new subscriber with generated token.
	 *
	 * @param string $email      Email address.
	 * @param string $first_name First name (optional).
	 * @param string $last_name  Last name (optional).
	 * @param string $source     Source: 'internal', 'external', or 'one_time'.
	 * @return object|null Subscriber object or null on failure.
	 */
	public function get_or_create( string $email, string $first_name = '', string $last_name = '', string $source = self::SOURCE_INTERNAL ): ?object {
		$email = sanitize_email( $email );

		if ( empty( $email ) || ! is_email( $email ) ) {
			return null;
		}

		// Check if subscriber already exists.
		$existing = $this->get_by_email( $email );

		if ( $existing ) {
			return $existing;
		}

		// Create new subscriber with source.
		$subscriber_id = $this->create(
			array(
				'email'      => $email,
				'first_name' => $first_name,
				'last_name'  => $last_name,
				'status'     => 'active',
				'source'     => $source,
			)
		);

		if ( ! $subscriber_id ) {
			return null;
		}

		return $this->get_by_id( $subscriber_id );
	}

	/**
	 * Check if an email is unsubscribed.
	 *
	 * @param string $email Email address.
	 * @return bool True if unsubscribed, false otherwise.
	 */
	public function is_unsubscribed( string $email ): bool {
		$subscriber = $this->get_by_email( $email );

		if ( ! $subscriber ) {
			return false;
		}

		return 'unsubscribed' === $subscriber->status;
	}

	/**
	 * Create a new subscriber.
	 *
	 * @param array $data {
	 *     Subscriber data.
	 *
	 *     @type string $email      Required. Subscriber email.
	 *     @type string $first_name Optional. First name.
	 *     @type string $last_name  Optional. Last name.
	 *     @type string $status     Optional. Status (active, inactive, unsubscribed). Default 'active'.
	 *     @type string $source     Optional. Source (internal, external, one_time). Default 'internal'.
	 * }
	 * @return int|false Subscriber ID on success, false on failure.
	 */
	public function create( array $data ) {
		$defaults = array(
			'email'      => '',
			'first_name' => '',
			'last_name'  => '',
			'status'     => 'active',
			'source'     => self::SOURCE_INTERNAL,
		);

		$data = wp_parse_args( $data, $defaults );

		// Validate source.
		$valid_sources = array( self::SOURCE_INTERNAL, self::SOURCE_EXTERNAL, self::SOURCE_ONE_TIME );
		if ( ! in_array( $data['source'], $valid_sources, true ) ) {
			$data['source'] = self::SOURCE_INTERNAL;
		}

		// Generate unsubscribe token.
		$token = wp_generate_password( 32, false );

		$result = $this->wpdb->insert(
			$this->table,
			array(
				'email'             => $data['email'],
				'first_name'        => $data['first_name'],
				'last_name'         => $data['last_name'],
				'status'            => $data['status'],
				'source'            => $data['source'],
				'unsubscribe_token' => $token,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( $result ) {
			return $this->wpdb->insert_id;
		}

		return false;
	}

	/**
	 * Update a subscriber.
	 *
	 * @param int   $id   Subscriber ID.
	 * @param array $data Data to update (email, first_name, last_name, status).
	 * @return bool True on success, false on failure.
	 */
	public function update( int $id, array $data ): bool {
		$allowed_fields = array( 'email', 'first_name', 'last_name', 'status' );
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
	 * Delete a subscriber.
	 *
	 * @param int $id Subscriber ID.
	 * @return bool True on success, false on failure.
	 */
	public function delete( int $id ): bool {
		// Delete from pivot table.
		$this->wpdb->delete(
			$this->pivot_table,
			array( 'subscriber_id' => $id ),
			array( '%d' )
		);

		// Delete pending queue items.
		$this->wpdb->delete(
			$this->queue_table,
			array(
				'subscriber_id' => $id,
				'status'        => 'pending',
			),
			array( '%d', '%s' )
		);

		// Delete subscriber.
		$result = $this->wpdb->delete(
			$this->table,
			array( 'id' => $id ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Get lists for a subscriber.
	 *
	 * @param int $subscriber_id Subscriber ID.
	 * @return array Array of list IDs.
	 */
	public function get_lists( int $subscriber_id ): array {
		$results = $this->wpdb->get_col(
			$this->wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is hardcoded and safe.
				"SELECT list_id FROM {$this->pivot_table} WHERE subscriber_id = %d",
				$subscriber_id
			)
		);

		return array_map( 'intval', $results ? $results : array() );
	}

	/**
	 * Get lists with names for multiple subscribers.
	 *
	 * Efficiently fetches list details for multiple subscribers in a single query.
	 *
	 * @param array $subscriber_ids Array of subscriber IDs.
	 * @return array Associative array: subscriber_id => array of list objects (with id and name).
	 */
	public function batch_get_lists( array $subscriber_ids ): array {
		if ( empty( $subscriber_ids ) ) {
			return array();
		}

		$subscriber_ids = array_map( 'intval', $subscriber_ids );
		$subscriber_ids = array_filter(
			$subscriber_ids,
			function ( $id ) {
				return $id > 0;
			}
		);

		if ( empty( $subscriber_ids ) ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $subscriber_ids ), '%d' ) );

		// Get list assignments with list names in a single query.
		$results = $this->wpdb->get_results(
			$this->wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Table names hardcoded, using splat operator.
				"SELECT sl.subscriber_id, l.id, l.name FROM {$this->pivot_table} sl INNER JOIN {$this->wpdb->prefix}mskd_lists l ON sl.list_id = l.id WHERE sl.subscriber_id IN ({$placeholders}) ORDER BY l.name ASC",
				...$subscriber_ids
			)
		);

		// Group results by subscriber_id.
		$grouped = array();
		foreach ( $results as $row ) {
			$sub_id = (int) $row->subscriber_id;
			if ( ! isset( $grouped[ $sub_id ] ) ) {
				$grouped[ $sub_id ] = array();
			}
			$grouped[ $sub_id ][] = (object) array(
				'id'   => (int) $row->id,
				'name' => $row->name,
			);
		}

		return $grouped;
	}


	/**
	 * Sync subscriber's list associations.
	 *
	 * Removes all existing associations and adds the new ones.
	 *
	 * @param int   $subscriber_id Subscriber ID.
	 * @param array $list_ids      Array of list IDs to associate.
	 * @return void
	 */
	public function sync_lists( int $subscriber_id, array $list_ids ): void {
		// Remove all existing associations.
		$this->wpdb->delete(
			$this->pivot_table,
			array( 'subscriber_id' => $subscriber_id ),
			array( '%d' )
		);

		// Add new associations.
		foreach ( $list_ids as $list_id ) {
			$this->wpdb->insert(
				$this->pivot_table,
				array(
					'subscriber_id' => $subscriber_id,
					'list_id'       => (int) $list_id,
				),
				array( '%d', '%d' )
			);
		}
	}

	/**
	 * Check if an email already exists.
	 *
	 * @param string   $email      Email to check.
	 * @param int|null $exclude_id Optional. Subscriber ID to exclude from check.
	 * @return bool True if email exists, false otherwise.
	 */
	public function email_exists( string $email, ?int $exclude_id = null ): bool {
		if ( null !== $exclude_id ) {
			$exists = $this->wpdb->get_var(
				$this->wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is hardcoded and safe.
					"SELECT id FROM {$this->table} WHERE email = %s AND id != %d",
					$email,
					$exclude_id
				)
			);
		} else {
			$exists = $this->wpdb->get_var(
				$this->wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is hardcoded and safe.
					"SELECT id FROM {$this->table} WHERE email = %s",
					$email
				)
			);
		}

		return (bool) $exists;
	}

	/**
	 * Get total subscriber count.
	 *
	 * @param string $status Optional. Filter by status.
	 * @return int Total count.
	 */
	public function count( string $status = '' ): int {
		if ( ! empty( $status ) ) {
			return (int) $this->wpdb->get_var(
				$this->wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is hardcoded and safe.
					"SELECT COUNT(*) FROM {$this->table} WHERE status = %s",
					$status
				)
			);
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is hardcoded and safe.
		return (int) $this->wpdb->get_var( "SELECT COUNT(*) FROM {$this->table}" );
	}

	/**
	 * Delete all inactive (unconfirmed) and unsubscribed subscribers.
	 *
	 * Deletes every subscriber with `inactive` or `unsubscribed` status, along with their
	 * list associations and queued items. Returns the number of deleted rows.
	 *
	 * @return int Number of subscribers deleted.
	 */
	public function delete_inactive(): int {
		// Fetch IDs of all inactive and unsubscribed subscribers.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin bulk-delete, caching not applicable.
		$ids = $this->wpdb->get_col(
			$this->wpdb->prepare(
				"SELECT id FROM {$this->table} WHERE status IN (%s, %s)",
				'inactive',
				'unsubscribed'
			)
		);

		if ( empty( $ids ) ) {
			return 0;
		}

		$deleted = 0;
		foreach ( $ids as $id ) {
			if ( $this->delete( (int) $id ) ) {
				++$deleted;
			}
		}

		return $deleted;
	}

	/**
	 * Truncate all subscribers (for admin use).
	 *
	 * @return bool True on success.
	 */
	public function truncate_all(): bool {
		// First truncate the pivot table.
		$this->wpdb->query( "TRUNCATE TABLE {$this->pivot_table}" );

		// Then truncate the subscribers table.
		$this->wpdb->query( "TRUNCATE TABLE {$this->table}" );

		return true;
	}

	/**
	 * Batch assign lists to multiple subscribers.
	 *
	 * Adds the specified lists to all provided subscribers.
	 * Existing list assignments are preserved (additive operation).
	 *
	 * @param array $subscriber_ids Array of subscriber IDs.
	 * @param array $list_ids       Array of list IDs to assign.
	 * @return array {
	 *     @type int   $success Number of subscribers updated successfully.
	 *     @type int   $failed  Number of subscribers that failed.
	 *     @type array $errors  Array of error messages for failed assignments.
	 * }
	 */
	public function batch_assign_lists( array $subscriber_ids, array $list_ids ): array {
		$result = array(
			'success' => 0,
			'failed'  => 0,
			'errors'  => array(),
		);

		// Validate inputs.
		if ( empty( $subscriber_ids ) || empty( $list_ids ) ) {
			return $result;
		}

		// Sanitize IDs.
		$subscriber_ids = array_map( 'intval', $subscriber_ids );
		$list_ids       = array_map( 'intval', $list_ids );

		// Filter out invalid IDs.
		$subscriber_ids = array_filter(
			$subscriber_ids,
			function ( $id ) {
				return $id > 0;
			}
		);
		$list_ids       = array_filter(
			$list_ids,
			function ( $id ) {
				return $id > 0;
			}
		);

		if ( empty( $subscriber_ids ) || empty( $list_ids ) ) {
			return $result;
		}

		foreach ( $subscriber_ids as $subscriber_id ) {
			// Check if subscriber exists.
			$subscriber = $this->get_by_id( $subscriber_id );
			if ( ! $subscriber ) {
				++$result['failed'];
				$result['errors'][] = sprintf(
					/* translators: %d: subscriber ID */
					__( 'Subscriber ID %d not found.', 'mail-system' ),
					$subscriber_id
				);
				continue;
			}

			// Get current lists for the subscriber.
			$current_lists = $this->get_lists( $subscriber_id );

			// Merge with new lists (avoid duplicates).
			$new_lists = array_unique( array_merge( $current_lists, $list_ids ) );

			// Sync lists.
			$this->sync_lists( $subscriber_id, $new_lists );

			++$result['success'];
		}

		return $result;
	}

	/**
	 * Batch remove lists from multiple subscribers.
	 *
	 * Removes the specified lists from all provided subscribers.
	 *
	 * @param array $subscriber_ids Array of subscriber IDs.
	 * @param array $list_ids       Array of list IDs to remove.
	 * @return array {
	 *     @type int   $success Number of subscribers updated successfully.
	 *     @type int   $failed  Number of subscribers that failed.
	 *     @type array $errors  Array of error messages for failed operations.
	 * }
	 */
	public function batch_remove_lists( array $subscriber_ids, array $list_ids ): array {
		$result = array(
			'success' => 0,
			'failed'  => 0,
			'errors'  => array(),
		);

		// Validate inputs.
		if ( empty( $subscriber_ids ) || empty( $list_ids ) ) {
			return $result;
		}

		// Sanitize IDs.
		$subscriber_ids = array_map( 'intval', $subscriber_ids );
		$list_ids       = array_map( 'intval', $list_ids );

		// Filter out invalid IDs.
		$subscriber_ids = array_filter(
			$subscriber_ids,
			function ( $id ) {
				return $id > 0;
			}
		);
		$list_ids       = array_filter(
			$list_ids,
			function ( $id ) {
				return $id > 0;
			}
		);

		if ( empty( $subscriber_ids ) || empty( $list_ids ) ) {
			return $result;
		}

		foreach ( $subscriber_ids as $subscriber_id ) {
			// Check if subscriber exists.
			$subscriber = $this->get_by_id( $subscriber_id );
			if ( ! $subscriber ) {
				++$result['failed'];
				$result['errors'][] = sprintf(
					/* translators: %d: subscriber ID */
					__( 'Subscriber ID %d not found.', 'mail-system' ),
					$subscriber_id
				);
				continue;
			}

			// Get current lists for the subscriber.
			$current_lists = $this->get_lists( $subscriber_id );

			// Remove specified lists.
			$new_lists = array_diff( $current_lists, $list_ids );

			// Sync lists.
			$this->sync_lists( $subscriber_id, $new_lists );

			++$result['success'];
		}

		return $result;
	}

	/**
	 * Batch get or create subscribers by emails.
	 *
	 * For each email, if subscriber exists, returns existing record (preserves current status).
	 * If not, creates new subscriber with generated token.
	 *
	 * @param array $emails_data Array of email data with email, first_name, last_name, source.
	 * @return array Array of subscriber objects indexed by email.
	 */
	public function batch_get_or_create( array $emails_data ): array {
		if ( empty( $emails_data ) ) {
			return array();
		}

		$emails = array();
		foreach ( $emails_data as $data ) {
			$email = sanitize_email( $data['email'] ?? '' );
			if ( ! empty( $email ) && is_email( $email ) ) {
				$emails[] = $email;
			}
		}

		if ( empty( $emails ) ) {
			return array();
		}

		// Get existing subscribers in a single query.
		$placeholders         = implode( ',', array_fill( 0, count( $emails ), '%s' ) );
		$existing_subscribers = $this->wpdb->get_results(
			$this->wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Table name hardcoded, using splat operator.
				"SELECT * FROM {$this->table} WHERE email IN ({$placeholders})",
				...$emails
			)
		);

		// Index existing subscribers by email for quick lookup.
		$existing_by_email = array();
		foreach ( $existing_subscribers as $subscriber ) {
			$existing_by_email[ $subscriber->email ] = $subscriber;
		}

		$result          = array();
		$new_subscribers = array();

		foreach ( $emails_data as $data ) {
			$email      = sanitize_email( $data['email'] ?? '' );
			$first_name = $data['first_name'] ?? '';
			$last_name  = $data['last_name'] ?? '';
			$source     = $data['source'] ?? self::SOURCE_INTERNAL;

			if ( empty( $email ) || ! is_email( $email ) ) {
				continue;
			}

			// Return existing subscriber if found.
			if ( isset( $existing_by_email[ $email ] ) ) {
				$result[ $email ] = $existing_by_email[ $email ];
				continue;
			}

			// Prepare for batch creation.
			$new_subscribers[] = array(
				'email'      => $email,
				'first_name' => $first_name,
				'last_name'  => $last_name,
				'status'     => 'active',
				'source'     => $source,
			);
		}

		// Batch create new subscribers if any.
		if ( ! empty( $new_subscribers ) ) {
			$created_ids = $this->batch_create( $new_subscribers );
			foreach ( $created_ids as $i => $id ) {
				if ( $id ) {
					$email            = $new_subscribers[ $i ]['email'];
					$result[ $email ] = $this->get_by_id( $id );
				}
			}
		}

		return $result;
	}

	/**
	 * Batch create subscribers.
	 *
	 * @param array $subscribers_data Array of subscriber data arrays.
	 * @return array Array of created subscriber IDs (false for failed creations).
	 */
	public function batch_create( array $subscribers_data ): array {
		if ( empty( $subscribers_data ) ) {
			return array();
		}

		$ids = array();
		foreach ( $subscribers_data as $data ) {
			$defaults = array(
				'email'      => '',
				'first_name' => '',
				'last_name'  => '',
				'status'     => 'active',
				'source'     => self::SOURCE_INTERNAL,
			);

			$data = wp_parse_args( $data, $defaults );

			// Validate source.
			$valid_sources = array( self::SOURCE_INTERNAL, self::SOURCE_EXTERNAL, self::SOURCE_ONE_TIME );
			if ( ! in_array( $data['source'], $valid_sources, true ) ) {
				$data['source'] = self::SOURCE_INTERNAL;
			}

			// Generate unsubscribe token.
			$token = wp_generate_password( 32, false );

			$result = $this->wpdb->insert(
				$this->table,
				array(
					'email'             => $data['email'],
					'first_name'        => $data['first_name'],
					'last_name'         => $data['last_name'],
					'status'            => $data['status'],
					'source'            => $data['source'],
					'unsubscribe_token' => $token,
				),
				array( '%s', '%s', '%s', '%s', '%s', '%s' )
			);

			$ids[] = $result ? $this->wpdb->insert_id : false;
		}

		return $ids;
	}

	/**
	 * Batch get subscribers by IDs.
	 *
	 * @param array $ids Array of subscriber IDs.
	 * @return array Array of subscriber objects indexed by ID.
	 */
	public function batch_get_by_ids( array $ids ): array {
		if ( empty( $ids ) ) {
			return array();
		}

		$ids = array_map( 'intval', $ids );
		$ids = array_filter(
			$ids,
			function ( $id ) {
				return $id > 0;
			}
		);

		if ( empty( $ids ) ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		$subscribers = $this->wpdb->get_results(
			$this->wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Table name hardcoded, using splat operator.
				"SELECT * FROM {$this->table} WHERE id IN ({$placeholders})",
				...$ids
			)
		);

		$result = array();
		foreach ( $subscribers as $subscriber ) {
			$result[ $subscriber->id ] = $subscriber;
		}

		return $result;
	}

	/**
	 * Batch update status for multiple subscribers.
	 *
	 * @param array  $subscriber_ids Array of subscriber IDs.
	 * @param string $status         Target status (active or inactive).
	 * @return array {
	 *     @type int   $success Number of subscribers updated successfully.
	 *     @type int   $failed  Number of subscribers that failed.
	 *     @type array $errors  Array of error messages for failed updates.
	 * }
	 */
	public function batch_update_status( array $subscriber_ids, string $status ): array {
		$result = array(
			'success' => 0,
			'failed'  => 0,
			'errors'  => array(),
		);

		$valid_statuses = array( 'active', 'inactive' );
		if ( ! in_array( $status, $valid_statuses, true ) ) {
			$result['errors'][] = __( 'Invalid status.', 'mail-system' );
			return $result;
		}

		if ( empty( $subscriber_ids ) ) {
			return $result;
		}

		// Sanitize and validate IDs.
		$subscriber_ids = array_map( 'intval', $subscriber_ids );
		$subscriber_ids = array_filter(
			$subscriber_ids,
			function ( $id ) {
				return $id > 0;
			}
		);

		if ( empty( $subscriber_ids ) ) {
			return $result;
		}

		foreach ( $subscriber_ids as $subscriber_id ) {
			$subscriber = $this->get_by_id( $subscriber_id );
			if ( ! $subscriber ) {
				++$result['failed'];
				$result['errors'][] = sprintf(
					/* translators: %d: subscriber ID */
					__( 'Subscriber ID %d not found.', 'mail-system' ),
					$subscriber_id
				);
				continue;
			}

			if ( $this->update( $subscriber_id, array( 'status' => $status ) ) ) {
				++$result['success'];
			} else {
				++$result['failed'];
				$result['errors'][] = sprintf(
					/* translators: %d: subscriber ID */
					__( 'Failed to update subscriber ID %d', 'mail-system' ),
					$subscriber_id
				);
			}
		}

		return $result;
	}

	/**
	 * Batch delete subscribers.
	 *
	 * Deletes multiple subscribers at once.
	 *
	 * @param array $subscriber_ids Array of subscriber IDs to delete.
	 * @return array {
	 *     @type int   $success Number of subscribers deleted successfully.
	 *     @type int   $failed  Number of subscribers that failed to delete.
	 *     @type array $errors  Array of error messages for failed deletions.
	 * }
	 */
	public function batch_delete( array $subscriber_ids ): array {
		$result = array(
			'success' => 0,
			'failed'  => 0,
			'errors'  => array(),
		);

		if ( empty( $subscriber_ids ) ) {
			return $result;
		}

		// Sanitize and validate IDs.
		$subscriber_ids = array_map( 'intval', $subscriber_ids );
		$subscriber_ids = array_filter(
			$subscriber_ids,
			function ( $id ) {
				return $id > 0;
			}
		);

		if ( empty( $subscriber_ids ) ) {
			return $result;
		}

		// Delete each subscriber.
		foreach ( $subscriber_ids as $subscriber_id ) {
			$deleted = $this->delete( $subscriber_id );

			if ( $deleted ) {
				++$result['success'];
			} else {
				++$result['failed'];
				$result['errors'][] = sprintf(
					/* translators: %d: subscriber ID */
					__( 'Failed to delete subscriber ID %d', 'mail-system' ),
					$subscriber_id
				);
			}
		}

		return $result;
	}
}
