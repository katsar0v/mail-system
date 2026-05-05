<?php
/**
 * List Provider Service
 *
 * Provides hooks for third-party plugins to register automated/external lists.
 *
 * @package MSKD
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load the List Subscriber DAO.
require_once MSKD_PLUGIN_DIR . 'includes/models/class-mskd-list-subscriber.php';

/**
 * Class MSKD_List_Provider
 *
 * Handles list retrieval with support for external/automated lists via hooks.
 *
 * Third-party plugins can register lists using the 'mskd_register_external_lists' filter.
 * External lists are non-editable and provide their own subscriber data.
 *
 * @since 1.1.0
 */
class MSKD_List_Provider {

	/**
	 * Cache key for external lists.
	 *
	 * @var string
	 */
	const EXTERNAL_LISTS_CACHE_KEY = 'mskd_external_lists';

	/**
	 * Cache expiration in seconds.
	 *
	 * @var int
	 */
	const CACHE_EXPIRATION = 300;

	/**
	 * Get all lists (database + external).
	 *
	 * @since 1.1.0
	 *
	 * @return array Array of list objects with 'source' property indicating origin.
	 */
	public static function get_all_lists() {
		$database_lists = self::get_database_lists();
		$external_lists = self::get_external_lists();

		return array_merge( $database_lists, $external_lists );
	}

	/**
	 * Get lists from the database.
	 *
	 * @since 1.1.0
	 *
	 * @return array Array of database list objects.
	 */
	public static function get_database_lists() {
		global $wpdb;

		$lists = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is hardcoded and safe.
			"SELECT * FROM {$wpdb->prefix}mskd_lists ORDER BY name ASC"
		);

		if ( ! $lists ) {
			return array();
		}

		// Add metadata for database lists.
		foreach ( $lists as $list_obj ) {
			$list_obj->source      = 'database';
			$list_obj->is_editable = true;
			$list_obj->provider    = null;
		}

		return $lists;
	}

	/**
	 * Get external/automated lists registered via filter.
	 *
	 * Third-party plugins can register lists using the 'mskd_register_external_lists' filter.
	 *
	 * Each external list should be an array with the following keys:
	 * - 'id' (string|int) - Unique identifier for the list (required, prefixed with 'ext_')
	 * - 'name' (string) - Display name for the list (required)
	 * - 'description' (string) - Description of the list (optional)
	 * - 'provider' (string) - Name of the plugin/provider (optional)
	 * - 'subscriber_callback' (callable) - Callback that returns subscriber IDs or emails (optional)
	 *
	 * @since 1.1.0
	 *
	 * @return array Array of external list objects.
	 */
	public static function get_external_lists() {
		/**
		 * Filter to register external/automated lists.
		 *
		 * @since 1.1.0
		 *
		 * @param array $external_lists Array of external list definitions.
		 *                              Each list should have: id, name, description (optional),
		 *                              provider (optional), subscriber_callback (optional).
		 */
		$external_lists = apply_filters( 'mskd_register_external_lists', array() );

		if ( ! is_array( $external_lists ) ) {
			return array();
		}

		$formatted_lists = array();

		foreach ( $external_lists as $list_data ) {
			$formatted_list = self::format_external_list( $list_data );
			if ( $formatted_list ) {
				$formatted_lists[] = $formatted_list;
			}
		}

		return $formatted_lists;
	}

	/**
	 * Format and validate external list data.
	 *
	 * @since 1.1.0
	 *
	 * @param array $list_data Raw list data from filter.
	 * @return object|null Formatted list object or null if invalid.
	 */
	private static function format_external_list( $list_data ) {
		// Validate required fields.
		if ( ! isset( $list_data['id'] ) || ! isset( $list_data['name'] ) ) {
			return null;
		}

		$list = new stdClass();

		// External list IDs are prefixed with 'ext_' to avoid collision with database IDs.
		$list->id                  = 'ext_' . sanitize_key( $list_data['id'] );
		$list->name                = sanitize_text_field( $list_data['name'] );
		$list->description         = isset( $list_data['description'] ) ? sanitize_textarea_field( $list_data['description'] ) : '';
		$list->created_at          = null; // External lists don't have a creation date.
		$list->source              = 'external';
		$list->is_editable         = false;
		$list->provider            = isset( $list_data['provider'] ) ? sanitize_text_field( $list_data['provider'] ) : __( 'External', 'mail-system' );
		$list->subscriber_callback = isset( $list_data['subscriber_callback'] ) && is_callable( $list_data['subscriber_callback'] )
			? $list_data['subscriber_callback']
			: null;

		return $list;
	}

	/**
	 * Get a single list by ID.
	 *
	 * @since 1.1.0
	 *
	 * @param string|int $list_id List ID (numeric for database, 'ext_*' for external).
	 * @return object|null List object or null if not found.
	 */
	public static function get_list( $list_id ) {
		// Check if it's an external list.
		if ( is_string( $list_id ) && strpos( $list_id, 'ext_' ) === 0 ) {
			$external_lists = self::get_external_lists();
			foreach ( $external_lists as $list ) {
				if ( $list->id === $list_id ) {
					return $list;
				}
			}
			return null;
		}

		// Database list.
		global $wpdb;
		$list = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is hardcoded and safe.
				"SELECT * FROM {$wpdb->prefix}mskd_lists WHERE id = %d",
				intval( $list_id )
			)
		);

		if ( $list ) {
			$list->source      = 'database';
			$list->is_editable = true;
			$list->provider    = null;
		}

		return $list;
	}

	/**
	 * Check if a list is editable.
	 *
	 * Database lists are editable by default.
	 * External lists are not editable.
	 *
	 * @since 1.1.0
	 *
	 * @param string|int $list_id List ID.
	 * @return bool True if editable, false otherwise.
	 */
	public static function is_list_editable( $list_id ) {
		// External lists are never editable.
		if ( is_string( $list_id ) && strpos( $list_id, 'ext_' ) === 0 ) {
			return false;
		}

		/**
		 * Filter whether a database list is editable.
		 *
		 * @since 1.1.0
		 *
		 * @param bool       $is_editable Whether the list is editable. Default true for database lists.
		 * @param string|int $list_id     The list ID.
		 */
		return apply_filters( 'mskd_list_is_editable', true, $list_id );
	}

	/**
	 * Get subscriber count for a list.
	 *
	 * For database lists, counts from the pivot table.
	 * For external lists, uses the subscriber callback if provided.
	 *
	 * @since 1.1.0
	 *
	 * @param object $list_obj List object.
	 * @return int Subscriber count.
	 */
	public static function get_list_subscriber_count( $list_obj ) {
		if ( 'external' === $list_obj->source ) {
			if ( isset( $list_obj->subscriber_callback ) && is_callable( $list_obj->subscriber_callback ) ) {
				$subscribers = call_user_func( $list_obj->subscriber_callback );
				return is_array( $subscribers ) ? count( $subscribers ) : 0;
			}
			return 0;
		}

		// Database list.
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is hardcoded and safe.
				"SELECT COUNT(*) FROM {$wpdb->prefix}mskd_subscriber_list WHERE list_id = %d",
				intval( $list_obj->id )
			)
		);
	}

	/**
	 * Get active subscriber count for a list.
	 *
	 * @since 1.1.0
	 *
	 * @param object $list_obj List object.
	 * @return int Active subscriber count.
	 */
	public static function get_list_active_subscriber_count( $list_obj ) {
		if ( 'external' === $list_obj->source ) {
			return self::get_external_list_active_count( $list_obj );
		}

		// Database list.
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names are hardcoded and safe.
				"SELECT COUNT(*) FROM {$wpdb->prefix}mskd_subscriber_list sl
				INNER JOIN {$wpdb->prefix}mskd_subscribers s ON sl.subscriber_id = s.id
				WHERE sl.list_id = %d AND s.status = 'active'",
				intval( $list_obj->id )
			)
		);
	}

	/**
	 * Get active subscriber count for an external list.
	 *
	 * The subscriber_callback must return an array of subscriber arrays,
	 * each with at least an 'email' key.
	 *
	 * @since 1.1.0
	 *
	 * @param object $list External list object.
	 * @return int Active subscriber count.
	 */
	private static function get_external_list_active_count( $list ) {
		if ( ! isset( $list->subscriber_callback ) || ! is_callable( $list->subscriber_callback ) ) {
			return 0;
		}

		$result = call_user_func( $list->subscriber_callback );

		if ( ! MSKD_List_Subscriber::is_valid_callback_result( $result ) ) {
			return 0;
		}

		$subscribers = MSKD_List_Subscriber::from_callback_result( $result );

		return count( $subscribers );
	}

	/**
	 * Get active subscriber IDs for a list.
	 *
	 * @since 1.1.0
	 *
	 * @param object|string|int $list_obj List object or list ID.
	 * @return array Array of subscriber IDs.
	 */
	public static function get_list_subscriber_ids( $list_obj ) {
		if ( ! is_object( $list_obj ) ) {
			$list_obj = self::get_list( $list_obj );
		}

		if ( ! $list_obj ) {
			return array();
		}

		if ( 'external' === $list_obj->source ) {
			return self::get_external_list_subscriber_ids( $list_obj );
		}

		// Database list.
		global $wpdb;
		return $wpdb->get_col(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names are hardcoded and safe.
				"SELECT DISTINCT s.id FROM {$wpdb->prefix}mskd_subscribers s
				INNER JOIN {$wpdb->prefix}mskd_subscriber_list sl ON s.id = sl.subscriber_id
				WHERE sl.list_id = %d AND s.status = 'active'",
				intval( $list_obj->id )
			)
		);
	}

	/**
	 * Get subscriber IDs for an external list.
	 *
	 * Since external lists return subscriber arrays (not database IDs),
	 * this method returns unique identifiers based on email addresses.
	 *
	 * @since 1.1.0
	 *
	 * @param object $list_obj External list object.
	 * @return array Array of email addresses as identifiers.
	 */
	private static function get_external_list_subscriber_ids( $list_obj ) {
		if ( ! isset( $list_obj->subscriber_callback ) || ! is_callable( $list_obj->subscriber_callback ) ) {
			return array();
		}

		$result = call_user_func( $list_obj->subscriber_callback );

		if ( ! MSKD_List_Subscriber::is_valid_callback_result( $result ) ) {
			return array();
		}

		$subscribers = MSKD_List_Subscriber::from_callback_result( $result );

		return array_map(
			function ( MSKD_List_Subscriber $sub ) {
				return $sub->get_email();
			},
			$subscribers
		);
	}

	/**
	 * Check if a list exists.
	 *
	 * @since 1.1.0
	 *
	 * @param string|int $list_id List ID.
	 * @return bool True if exists, false otherwise.
	 */
	public static function list_exists( $list_id ) {
		return self::get_list( $list_id ) !== null;
	}

	/**
	 * Invalidate external lists cache.
	 *
	 * Call this when external list data may have changed.
	 *
	 * @since 1.1.0
	 */
	public static function invalidate_cache() {
		delete_transient( self::EXTERNAL_LISTS_CACHE_KEY );
	}

	// =========================================================================
	// External Subscribers Support
	// =========================================================================

	/**
	 * Get all subscribers (database + external).
	 *
	 * @since 1.2.0
	 *
	 * @param array $args Optional. Query arguments.
	 *                    - 'status' (string) Filter by status.
	 *                    - 'per_page' (int) Number of results per page.
	 *                    - 'page' (int) Page number.
	 *                    - 'include_external' (bool) Include external subscribers. Default true.
	 * @return array Array of subscriber objects with 'source' property indicating origin.
	 */
	public static function get_all_subscribers( $args = array() ) {
		$defaults = array(
			'status'           => '',
			'per_page'         => 20,
			'page'             => 1,
			'include_external' => true,
		);
		$args     = wp_parse_args( $args, $defaults );

		$database_subscribers = self::get_database_subscribers( $args );

		if ( ! $args['include_external'] ) {
			return $database_subscribers;
		}

		$external_subscribers = self::get_external_subscribers( $args );

		return array_merge( $database_subscribers, $external_subscribers );
	}

	/**
	 * Get subscribers from the database.
	 *
	 * @since 1.2.0
	 *
	 * @param array $args Query arguments.
	 * @return array Array of database subscriber objects.
	 */
	public static function get_database_subscribers( $args = array() ) {
		global $wpdb;

		$where  = '';
		$params = array();

		if ( ! empty( $args['status'] ) ) {
			$where    = 'WHERE status = %s';
			$params[] = $args['status'];
		}

		$offset   = ( max( 1, intval( $args['page'] ) ) - 1 ) * intval( $args['per_page'] );
		$params[] = intval( $args['per_page'] );
		$params[] = $offset;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is hardcoded and safe.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is hardcoded and safe.
		$query = "SELECT * FROM {$wpdb->prefix}mskd_subscribers {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d";

		$subscribers = $wpdb->get_results( $wpdb->prepare( $query, $params ) );

		if ( ! $subscribers ) {
			return array();
		}

		// Add metadata for database subscribers.
		foreach ( $subscribers as $subscriber ) {
			$subscriber->source      = 'database';
			$subscriber->is_editable = true;
			$subscriber->provider    = null;
		}

		return $subscribers;
	}

	/**
	 * Get external subscribers registered via filter.
	 *
	 * Third-party plugins can register subscribers using the 'mskd_register_external_subscribers' filter.
	 *
	 * Each external subscriber should be an array with the following keys:
	 * - 'id' (string|int) - Unique identifier (required, will be prefixed with 'ext_')
	 * - 'email' (string) - Email address (required)
	 * - 'first_name' (string) - First name (optional)
	 * - 'last_name' (string) - Last name (optional)
	 * - 'status' (string) - Status: active, inactive (optional, default: active)
	 * - 'provider' (string) - Name of the plugin/provider (optional)
	 * - 'lists' (array) - Array of list IDs this subscriber belongs to (optional)
	 *
	 * @since 1.2.0
	 *
	 * @param array $args Query arguments (status filter applied).
	 * @return array Array of external subscriber objects.
	 */
	public static function get_external_subscribers( $args = array() ) {
		$formatted_subscribers = array();
		$seen_emails           = array();

		// 1. Get subscribers from external lists' subscriber_callback functions.
		$external_lists = self::get_external_lists();
		foreach ( $external_lists as $list ) {
			if ( ! isset( $list->subscriber_callback ) || ! is_callable( $list->subscriber_callback ) ) {
				continue;
			}

			$result = call_user_func( $list->subscriber_callback );

			if ( ! MSKD_List_Subscriber::is_valid_callback_result( $result ) ) {
				continue;
			}

			$subscribers = MSKD_List_Subscriber::from_callback_result( $result );

			foreach ( $subscribers as $sub ) {
				$email = $sub->get_email();

				// Avoid duplicates across lists.
				if ( isset( $seen_emails[ $email ] ) ) {
					// Add this list to the existing subscriber's lists.
					foreach ( $formatted_subscribers as $existing ) {
						if ( $existing->email === $email ) {
							$existing->lists[] = $list->id;
							break;
						}
					}
					continue;
				}

				$seen_emails[ $email ] = true;

				// Convert MSKD_List_Subscriber to formatted subscriber object.
				$subscriber                    = $sub->to_object();
				$subscriber->id                = 'ext_' . sanitize_key( $email );
				$subscriber->status            = 'active'; // External list subscribers are considered active.
				$subscriber->created_at        = null;
				$subscriber->provider          = $list->provider;
				$subscriber->lists             = array( $list->id );
				$subscriber->unsubscribe_token = 'ext_' . md5( $email . wp_salt() );

				// Apply status filter if set.
				if ( ! empty( $args['status'] ) && $subscriber->status !== $args['status'] ) {
					continue;
				}

				$formatted_subscribers[] = $subscriber;
			}
		}

		// 2. Also get subscribers from the direct filter (for backwards compatibility).
		/**
		 * Filter to register external subscribers.
		 *
		 * @since 1.2.0
		 *
		 * @param array $external_subscribers Array of external subscriber definitions.
		 *                                    Each subscriber should have: id, email, first_name (optional),
		 *                                    last_name (optional), status (optional), provider (optional).
		 * @param array $args                 Query arguments passed to get_all_subscribers().
		 */
		$filter_subscribers = apply_filters( 'mskd_register_external_subscribers', array(), $args );

		if ( is_array( $filter_subscribers ) ) {
			foreach ( $filter_subscribers as $subscriber_data ) {
				// Avoid duplicates.
				if ( isset( $subscriber_data['email'] ) && isset( $seen_emails[ $subscriber_data['email'] ] ) ) {
					continue;
				}

				$formatted_subscriber = self::format_external_subscriber( $subscriber_data );
				if ( $formatted_subscriber ) {
					// Apply status filter if set.
					if ( ! empty( $args['status'] ) && $formatted_subscriber->status !== $args['status'] ) {
						continue;
					}
					$seen_emails[ $formatted_subscriber->email ] = true;
					$formatted_subscribers[]                     = $formatted_subscriber;
				}
			}
		}

		return $formatted_subscribers;
	}

	/**
	 * Format and validate external subscriber data.
	 *
	 * @since 1.2.0
	 *
	 * @param array $subscriber_data Raw subscriber data from filter.
	 * @return object|null Formatted subscriber object or null if invalid.
	 */
	private static function format_external_subscriber( $subscriber_data ) {
		// Validate required fields.
		if ( ! isset( $subscriber_data['id'] ) || ! isset( $subscriber_data['email'] ) ) {
			return null;
		}

		if ( ! is_email( $subscriber_data['email'] ) ) {
			return null;
		}

		$subscriber = new stdClass();

		// External subscriber IDs are prefixed with 'ext_' to avoid collision with database IDs.
		$subscriber->id          = 'ext_' . sanitize_key( $subscriber_data['id'] );
		$subscriber->email       = sanitize_email( $subscriber_data['email'] );
		$subscriber->first_name  = isset( $subscriber_data['first_name'] ) ? sanitize_text_field( $subscriber_data['first_name'] ) : '';
		$subscriber->last_name   = isset( $subscriber_data['last_name'] ) ? sanitize_text_field( $subscriber_data['last_name'] ) : '';
		$subscriber->status      = isset( $subscriber_data['status'] ) && in_array( $subscriber_data['status'], array( 'active', 'inactive', 'unsubscribed' ), true )
			? $subscriber_data['status']
			: 'active';
		$subscriber->created_at  = null; // External subscribers don't have a creation date.
		$subscriber->source      = 'external';
		$subscriber->is_editable = false;
		$subscriber->provider    = isset( $subscriber_data['provider'] ) ? sanitize_text_field( $subscriber_data['provider'] ) : __( 'External', 'mail-system' );
		$subscriber->lists       = isset( $subscriber_data['lists'] ) && is_array( $subscriber_data['lists'] ) ? $subscriber_data['lists'] : array();

		// Generate a temporary unsubscribe token for external subscribers.
		$subscriber->unsubscribe_token = 'ext_' . md5( $subscriber->email . wp_salt() );

		return $subscriber;
	}

	/**
	 * Get a single subscriber by ID.
	 *
	 * @since 1.2.0
	 *
	 * @param string|int $subscriber_id Subscriber ID (numeric for database, 'ext_*' for external).
	 * @return object|null Subscriber object or null if not found.
	 */
	public static function get_subscriber( $subscriber_id ) {
		// Check if it's an external subscriber.
		if ( self::is_external_id( $subscriber_id ) ) {
			$external_subscribers = self::get_external_subscribers();
			foreach ( $external_subscribers as $subscriber ) {
				if ( $subscriber->id === $subscriber_id ) {
					return $subscriber;
				}
			}
			return null;
		}

		// Database subscriber.
		global $wpdb;
		$subscriber = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}mskd_subscribers WHERE id = %d",
				intval( $subscriber_id )
			)
		);

		if ( $subscriber ) {
			$subscriber->source      = 'database';
			$subscriber->is_editable = true;
			$subscriber->provider    = null;
		}

		return $subscriber;
	}

	/**
	 * Check if an ID is external (not from database).
	 *
	 * @since 1.2.0
	 *
	 * @param string|int $id ID to check.
	 * @return bool True if external, false otherwise.
	 */
	public static function is_external_id( $id ) {
		return is_string( $id ) && strpos( $id, 'ext_' ) === 0;
	}

	/**
	 * Check if a subscriber is editable.
	 *
	 * Database subscribers are editable by default.
	 * External subscribers are not editable.
	 *
	 * @since 1.2.0
	 *
	 * @param string|int $subscriber_id Subscriber ID.
	 * @return bool True if editable, false otherwise.
	 */
	public static function is_subscriber_editable( $subscriber_id ) {
		// External subscribers are never editable.
		if ( self::is_external_id( $subscriber_id ) ) {
			return false;
		}

		/**
		 * Filter whether a database subscriber is editable.
		 *
		 * @since 1.2.0
		 *
		 * @param bool       $is_editable   Whether the subscriber is editable. Default true for database subscribers.
		 * @param string|int $subscriber_id The subscriber ID.
		 */
		return apply_filters( 'mskd_subscriber_is_editable', true, $subscriber_id );
	}

	/**
	 * Get total subscriber count (database + external).
	 *
	 * @since 1.2.0
	 *
	 * @param string $status Optional. Filter by status.
	 * @return int Total subscriber count.
	 */
	public static function get_total_subscriber_count( $status = '' ) {
		$db_count       = self::get_database_subscriber_count( $status );
		$external_count = count( self::get_external_subscribers( array( 'status' => $status ) ) );

		return $db_count + $external_count;
	}

	/**
	 * Get database subscriber count.
	 *
	 * @since 1.2.0
	 *
	 * @param string $status Optional. Filter by status.
	 * @return int Database subscriber count.
	 */
	public static function get_database_subscriber_count( $status = '' ) {
		global $wpdb;

		$where = '';
		if ( ! empty( $status ) ) {
			$where = $wpdb->prepare( 'WHERE status = %s', $status );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is hardcoded and safe.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching -- Simple count query, no caching needed.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is hardcoded and safe.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching -- Simple count query, no caching needed.
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}mskd_subscribers {$where}" );
	}

	/**
	 * Get subscribers for an external list with full data.
	 *
	 * For external lists, returns complete subscriber objects (not just IDs).
	 * This is useful when queuing emails to external subscribers who may not exist
	 * in the mskd_subscribers table.
	 *
	 * @since 1.2.0
	 *
	 * @param object|string|int $list_obj List object or list ID.
	 * @param int|null          $limit Optional. Maximum number of subscribers to return. Default null (no limit).
	 * @return array Array of subscriber objects with email, first_name, last_name, etc.
	 */
	public static function get_list_subscribers_full( $list_obj, $limit = null ) {
		if ( ! is_object( $list_obj ) ) {
			$list_obj = self::get_list( $list_obj );
		}

		if ( ! $list_obj ) {
			return array();
		}

		// For database lists, get full subscriber data.
		if ( 'database' === $list_obj->source ) {
			global $wpdb;

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Using prepare with interpolated table name is necessary here.
			$sql = $wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names are hardcoded and safe.
				"SELECT s.* FROM {$wpdb->prefix}mskd_subscribers s
				INNER JOIN {$wpdb->prefix}mskd_subscriber_list sl ON s.id = sl.subscriber_id
				WHERE sl.list_id = %d AND s.status = 'active'",
				intval( $list_obj->id )
			);

			if ( null !== $limit && $limit > 0 ) {
				$sql .= $wpdb->prepare( ' LIMIT %d', $limit );
			}

			$subscribers = $wpdb->get_results( $sql );

			foreach ( $subscribers as $sub ) {
				$sub->source      = 'database';
				$sub->is_editable = true;
			}

			return $subscribers;
		}

		// For external lists, call the subscriber callback.
		if ( ! isset( $list_obj->subscriber_callback ) || ! is_callable( $list_obj->subscriber_callback ) ) {
			return array();
		}

		$result = call_user_func( $list_obj->subscriber_callback );

		if ( ! MSKD_List_Subscriber::is_valid_callback_result( $result ) ) {
			return array();
		}

		$subscribers = MSKD_List_Subscriber::from_callback_result( $result );

		// Apply limit if specified.
		if ( null !== $limit && $limit > 0 ) {
			$subscribers = array_slice( $subscribers, 0, $limit );
		}

		// Convert MSKD_List_Subscriber objects to stdClass for compatibility.
		return array_map(
			function ( MSKD_List_Subscriber $sub ) {
				return $sub->to_object();
			},
			$subscribers
		);
	}
}
