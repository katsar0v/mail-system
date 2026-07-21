<?php
/**
 * Plugin Activator
 *
 * @package MSKD
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MSKD_Activator
 *
 * Handles plugin activation tasks including database table creation
 */
class MSKD_Activator {

	/**
	 * Database version for tracking schema updates
	 */
	const DB_VERSION = '1.9.0';

	/**
	 * Activate the plugin
	 */
	public static function activate() {
		self::create_tables();
		self::schedule_cron();
		self::set_default_options();

		// Store database version.
		update_option( 'mskd_db_version', self::DB_VERSION );

		// Flush rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Check and perform database upgrades if needed
	 */
	public static function maybe_upgrade() {
		$installed_version = get_option( 'mskd_db_version', '1.0.0' );

		if ( version_compare( $installed_version, self::DB_VERSION, '<' ) ) {
			self::upgrade( $installed_version );
			update_option( 'mskd_db_version', self::DB_VERSION );
		}
	}

	/**
	 * Perform database upgrades based on current version
	 *
	 * @param string $from_version The version being upgraded from.
	 */
	private static function upgrade( $from_version ) {
		global $wpdb;

		// Upgrade from 1.0.0 to 1.1.0: Add subscriber_data column to queue table.
		if ( version_compare( $from_version, '1.1.0', '<' ) ) {
			$table_queue = $wpdb->prefix . 'mskd_queue';

			// Check if column exists.
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is hardcoded and safe.
			$column_exists = $wpdb->get_results(
				$wpdb->prepare(
					"SHOW COLUMNS FROM {$table_queue} LIKE %s",
					'subscriber_data'
				)
			);

			if ( empty( $column_exists ) ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
				$wpdb->query(
					"ALTER TABLE {$table_queue} ADD COLUMN subscriber_data text DEFAULT NULL AFTER subscriber_id"
				);
			}
		}

		// Upgrade from 1.1.0 to 1.2.0: Add campaigns table and campaign_id to queue.
		if ( version_compare( $from_version, '1.2.0', '<' ) ) {
			self::create_campaigns_table();

			$table_queue = $wpdb->prefix . 'mskd_queue';

			// Add campaign_id column to queue table.
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is hardcoded and safe.
			$column_exists = $wpdb->get_results(
				$wpdb->prepare(
					"SHOW COLUMNS FROM {$table_queue} LIKE %s",
					'campaign_id'
				)
			);

			if ( empty( $column_exists ) ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
				$wpdb->query(
					"ALTER TABLE {$table_queue} ADD COLUMN campaign_id bigint(20) UNSIGNED DEFAULT NULL AFTER id"
				);
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
				$wpdb->query(
					"ALTER TABLE {$table_queue} ADD KEY campaign_id (campaign_id)"
				);
			}
		}

		// Upgrade from 1.2.0 to 1.3.0: Add templates table.
		if ( version_compare( $from_version, '1.3.0', '<' ) ) {
			self::create_templates_table();
		}

		// Upgrade from 1.3.0 to 1.4.0: Add source column and opt_in_token to subscribers table.
		if ( version_compare( $from_version, '1.4.0', '<' ) ) {
			self::add_subscriber_source_column();
			self::migrate_orphaned_queue_items();

			$table_subscribers = $wpdb->prefix . 'mskd_subscribers';

			// Check if opt_in_token column exists.
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is hardcoded and safe.
			$column_exists = $wpdb->get_results(
				$wpdb->prepare(
					"SHOW COLUMNS FROM {$table_subscribers} LIKE %s",
					'opt_in_token'
				)
			);

			if ( empty( $column_exists ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
				$wpdb->query(
					"ALTER TABLE {$table_subscribers} ADD COLUMN opt_in_token varchar(64) DEFAULT NULL AFTER unsubscribe_token"
				);
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
				$wpdb->query(
					"ALTER TABLE {$table_subscribers} ADD KEY opt_in_token (opt_in_token)"
				);
			}
		}

		// Upgrade from 1.4.0 to 1.5.0: Add bcc column to campaigns table.
		if ( version_compare( $from_version, '1.5.0', '<' ) ) {
			$table_campaigns = $wpdb->prefix . 'mskd_campaigns';

			// Check if bcc column exists.
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is hardcoded and safe.
			$column_exists = $wpdb->get_results(
				$wpdb->prepare(
					"SHOW COLUMNS FROM {$table_campaigns} LIKE %s",
					'bcc'
				)
			);

			if ( empty( $column_exists ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange -- Required for upgrade.
				$wpdb->query(
					"ALTER TABLE {$table_campaigns} ADD COLUMN bcc text DEFAULT NULL AFTER list_ids"
				);
			}

			// Check if bcc_sent column exists.
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is hardcoded and safe.
			$column_exists = $wpdb->get_results(
				$wpdb->prepare(
					"SHOW COLUMNS FROM {$table_campaigns} LIKE %s",
					'bcc_sent'
				)
			);

			if ( empty( $column_exists ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange -- Required for upgrade.
				$wpdb->query(
					"ALTER TABLE {$table_campaigns} ADD COLUMN bcc_sent tinyint(1) DEFAULT 0 AFTER bcc"
				);
			}
		}

		// Upgrade from 1.5.0 to 1.6.0: Add per-campaign from email columns
		if ( version_compare( $from_version, '1.6.0', '<' ) ) {
			$table_campaigns = $wpdb->prefix . 'mskd_campaigns';

			// Check if from_email column exists.
			$from_email_exists = $wpdb->get_results(
				$wpdb->prepare(
					"SHOW COLUMNS FROM {$table_campaigns} LIKE %s",
					'from_email'
				)
			);

			if ( empty( $from_email_exists ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange -- Required for upgrade.
				$wpdb->query(
					"ALTER TABLE {$table_campaigns} ADD COLUMN from_email varchar(255) DEFAULT NULL AFTER bcc_sent"
				);
				$wpdb->query(
					"ALTER TABLE {$table_campaigns} ADD COLUMN from_name varchar(255) DEFAULT NULL AFTER from_email"
				);
				$wpdb->query(
					"ALTER TABLE {$table_campaigns} ADD INDEX from_email (from_email)"
				);
			}
		}

		// Upgrade through 1.8.0: Add per-recipient open and click analytics fields.
		if ( version_compare( $from_version, '1.8.0', '<' ) ) {
			// dbDelta adds missing analytics columns, tables, and indexes while preserving queue data.
			self::create_tables();
		}

		// Upgrade to 1.9.0: JWT REST API support, queue-claiming safety, and schema drift repair.
		if ( version_compare( $from_version, '1.9.0', '<' ) ) {
			self::upgrade_to_190();
		}
	}

	/**
	 * Upgrade routine for schema version 1.9.0.
	 *
	 * Repairs campaign schema drift (from_email/from_name on installs created from the
	 * buggy fresh-install schema), adds the queue-claiming safety fields, extends the
	 * queue status enum with `cancelled`, and creates the API tokens table.
	 */
	private static function upgrade_to_190() {
		global $wpdb;

		$table_queue     = $wpdb->prefix . 'mskd_queue';
		$table_campaigns = $wpdb->prefix . 'mskd_campaigns';

		// Ensure the campaigns table carries per-campaign sender columns even on installs
		// that were created from the previously-incomplete fresh-install schema.
		self::maybe_add_column( $table_campaigns, 'bcc', 'ADD COLUMN bcc text DEFAULT NULL AFTER list_ids' );
		self::maybe_add_column( $table_campaigns, 'bcc_sent', 'ADD COLUMN bcc_sent tinyint(1) DEFAULT 0 AFTER bcc' );
		self::maybe_add_column( $table_campaigns, 'from_email', 'ADD COLUMN from_email varchar(255) DEFAULT NULL AFTER bcc_sent' );
		self::maybe_add_column( $table_campaigns, 'from_name', 'ADD COLUMN from_name varchar(255) DEFAULT NULL AFTER from_email' );

		// Extend the queue status enum with `cancelled` (dbDelta cannot alter enum members).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is hardcoded and safe.
		$wpdb->query(
			"ALTER TABLE {$table_queue} MODIFY COLUMN status enum('pending','processing','sent','failed','cancelled') DEFAULT 'pending'"
		);

		// Add the processing timestamp used for atomic claiming and stuck-job recovery.
		self::maybe_add_column( $table_queue, 'processing_started_at', 'ADD COLUMN processing_started_at datetime DEFAULT NULL AFTER sent_at' );

		// Add a composite index that backs the claim query (status + due time).
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is hardcoded and safe.
		$index_exists = $wpdb->get_results(
			$wpdb->prepare( "SHOW INDEX FROM {$table_queue} WHERE Key_name = %s", 'status_scheduled' )
		);
		if ( empty( $index_exists ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is hardcoded and safe.
			$wpdb->query( "ALTER TABLE {$table_queue} ADD KEY status_scheduled (status, scheduled_at)" );
		}

		// Create the API tokens table.
		self::create_api_tokens_table();
	}

	/**
	 * Add a column to a table only when it does not already exist.
	 *
	 * @param string $table       Fully-qualified table name.
	 * @param string $column      Column name to check.
	 * @param string $alter_clause The ALTER TABLE clause (without the table name), e.g. "ADD COLUMN foo int".
	 */
	private static function maybe_add_column( $table, $column, $alter_clause ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is hardcoded and safe.
		$exists = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", $column ) );

		if ( empty( $exists ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table and clause are internal, hardcoded, and safe.
			$wpdb->query( "ALTER TABLE {$table} {$alter_clause}" );
		}
	}

	/**
	 * Create database tables
	 */
	private static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Subscribers table.
		$table_subscribers = $wpdb->prefix . 'mskd_subscribers';
		$sql_subscribers   = "CREATE TABLE $table_subscribers (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            email varchar(255) NOT NULL,
            first_name varchar(100) DEFAULT '',
            last_name varchar(100) DEFAULT '',
            status enum('active','inactive','unsubscribed') DEFAULT 'active',
            source enum('internal','external','one_time') DEFAULT 'internal',
            unsubscribe_token varchar(64) NOT NULL,
            opt_in_token varchar(64) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY email (email),
            KEY status (status),
            KEY source (source),
            KEY unsubscribe_token (unsubscribe_token),
            KEY opt_in_token (opt_in_token)
        ) $charset_collate;";

		// Lists table.
		$table_lists = $wpdb->prefix . 'mskd_lists';
		$sql_lists   = "CREATE TABLE $table_lists (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

		// Subscriber-List pivot table.
		$table_subscriber_list = $wpdb->prefix . 'mskd_subscriber_list';
		$sql_subscriber_list   = "CREATE TABLE $table_subscriber_list (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            subscriber_id bigint(20) UNSIGNED NOT NULL,
            list_id bigint(20) UNSIGNED NOT NULL,
            subscribed_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY subscriber_list (subscriber_id, list_id),
            KEY subscriber_id (subscriber_id),
            KEY list_id (list_id)
        ) $charset_collate;";

		// Campaigns table (groups emails by send operation).
		$table_campaigns = $wpdb->prefix . 'mskd_campaigns';
		$sql_campaigns   = "CREATE TABLE $table_campaigns (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            subject varchar(255) NOT NULL,
            body longtext NOT NULL,
            list_ids text DEFAULT NULL,
            bcc text DEFAULT NULL,
            bcc_sent tinyint(1) DEFAULT 0,
            from_email varchar(255) DEFAULT NULL,
            from_name varchar(255) DEFAULT NULL,
            type enum('campaign','one_time') DEFAULT 'campaign',
            total_recipients int(11) DEFAULT 0,
            status enum('pending','processing','completed','cancelled') DEFAULT 'pending',
            scheduled_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY scheduled_at (scheduled_at),
            KEY type (type),
            KEY from_email (from_email)
        ) $charset_collate;";

		// Queue table.
		$table_queue = $wpdb->prefix . 'mskd_queue';
		$sql_queue   = "CREATE TABLE $table_queue (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            campaign_id bigint(20) UNSIGNED DEFAULT NULL,
            subscriber_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
            subscriber_data text DEFAULT NULL,
            subject varchar(255) NOT NULL,
            body longtext NOT NULL,
            status enum('pending','processing','sent','failed','cancelled') DEFAULT 'pending',
            scheduled_at datetime DEFAULT CURRENT_TIMESTAMP,
            sent_at datetime DEFAULT NULL,
            processing_started_at datetime DEFAULT NULL,
            tracking_token varchar(64) DEFAULT NULL,
			click_token varchar(64) DEFAULT NULL,
            opened_at datetime DEFAULT NULL,
            open_count int(11) UNSIGNED DEFAULT 0,
            attempts int(11) DEFAULT 0,
            error_message text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY campaign_id (campaign_id),
            KEY subscriber_id (subscriber_id),
            KEY status (status),
            KEY scheduled_at (scheduled_at),
            KEY status_scheduled (status, scheduled_at),
            UNIQUE KEY tracking_token (tracking_token),
			UNIQUE KEY click_token (click_token),
            KEY opened_at (opened_at)
        ) $charset_collate;";

		// Per-recipient and per-link click aggregates. Exact redirect targets are
		// carried only in signed links; the table stores a query-free display URL.
		$table_clicks = $wpdb->prefix . 'mskd_clicks';
		$sql_clicks   = "CREATE TABLE $table_clicks (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			queue_id bigint(20) UNSIGNED NOT NULL,
			campaign_id bigint(20) UNSIGNED DEFAULT NULL,
			link_index smallint(5) UNSIGNED NOT NULL,
			display_url varchar(500) NOT NULL,
			first_clicked_at datetime NOT NULL,
			last_clicked_at datetime NOT NULL,
			click_count int(11) UNSIGNED DEFAULT 1,
			PRIMARY KEY (id),
			UNIQUE KEY queue_link (queue_id, link_index),
			KEY campaign_id (campaign_id),
			KEY link_index (link_index),
			KEY first_clicked_at (first_clicked_at),
			KEY last_clicked_at (last_clicked_at)
		) $charset_collate;";

		// Templates table.
		$table_templates = $wpdb->prefix . 'mskd_templates';
		$sql_templates   = "CREATE TABLE $table_templates (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            subject varchar(255) DEFAULT '',
            content longtext NOT NULL,
            json_content longtext DEFAULT NULL,
            thumbnail varchar(500) DEFAULT '',
            type enum('predefined','custom') DEFAULT 'custom',
            status enum('active','inactive') DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY type (type),
            KEY status (status)
        ) $charset_collate;";

		// API tokens table (JWT authentication for the REST API).
		$table_api_tokens = $wpdb->prefix . 'mskd_api_tokens';
		$sql_api_tokens   = "CREATE TABLE $table_api_tokens (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(191) NOT NULL,
            jti_hash char(64) NOT NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            scopes varchar(255) NOT NULL DEFAULT '',
            expires_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            last_used_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY jti_hash (jti_hash),
            KEY user_id (user_id),
            KEY expires_at (expires_at)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( $sql_subscribers );
		dbDelta( $sql_lists );
		dbDelta( $sql_subscriber_list );
		dbDelta( $sql_campaigns );
		dbDelta( $sql_queue );
		dbDelta( $sql_clicks );
		dbDelta( $sql_templates );
		dbDelta( $sql_api_tokens );
	}

	/**
	 * Create the API tokens table (used for upgrades).
	 */
	private static function create_api_tokens_table() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$table_api_tokens = $wpdb->prefix . 'mskd_api_tokens';
		$sql_api_tokens   = "CREATE TABLE $table_api_tokens (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(191) NOT NULL,
            jti_hash char(64) NOT NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            scopes varchar(255) NOT NULL DEFAULT '',
            expires_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            last_used_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY jti_hash (jti_hash),
            KEY user_id (user_id),
            KEY expires_at (expires_at)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_api_tokens );
	}

	/**
	 * Create campaigns table (used for upgrades)
	 */
	private static function create_campaigns_table() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$table_campaigns = $wpdb->prefix . 'mskd_campaigns';
		$sql_campaigns   = "CREATE TABLE $table_campaigns (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            subject varchar(255) NOT NULL,
            body longtext NOT NULL,
            list_ids text DEFAULT NULL,
            bcc text DEFAULT NULL,
            bcc_sent tinyint(1) DEFAULT 0,
            type enum('campaign','one_time') DEFAULT 'campaign',
            total_recipients int(11) DEFAULT 0,
            status enum('pending','processing','completed','cancelled') DEFAULT 'pending',
            scheduled_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY scheduled_at (scheduled_at),
            KEY type (type)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_campaigns );
	}

	/**
	 * Create templates table (used for upgrades)
	 */
	private static function create_templates_table() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$table_templates = $wpdb->prefix . 'mskd_templates';
		$sql_templates   = "CREATE TABLE $table_templates (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            subject varchar(255) DEFAULT '',
            content longtext NOT NULL,
            json_content longtext DEFAULT NULL,
            thumbnail varchar(500) DEFAULT '',
            type enum('predefined','custom') DEFAULT 'custom',
            status enum('active','inactive') DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY type (type),
            KEY status (status)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_templates );
	}

	/**
	 * Add source column to subscribers table (used for upgrades)
	 *
	 * Tracks subscriber origin: internal (form signup), external (from callbacks), one_time (one-time emails).
	 */
	private static function add_subscriber_source_column() {
		global $wpdb;

		$table_subscribers = $wpdb->prefix . 'mskd_subscribers';

		// Check if column exists.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is hardcoded and safe.
		$column_exists = $wpdb->get_results(
			$wpdb->prepare(
				"SHOW COLUMNS FROM {$table_subscribers} LIKE %s",
				'source'
			)
		);

		if ( empty( $column_exists ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query(
				"ALTER TABLE {$table_subscribers} ADD COLUMN source enum('internal','external','one_time') DEFAULT 'internal' AFTER status"
			);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query(
				"ALTER TABLE {$table_subscribers} ADD KEY source (source)"
			);
		}
	}

	/**
	 * Migrate orphaned queue items with subscriber_id = 0.
	 *
	 * Creates subscriber records from subscriber_data JSON and updates queue items.
	 */
	private static function migrate_orphaned_queue_items() {
		global $wpdb;

		$queue_table = $wpdb->prefix . 'mskd_queue';

		// Get pending queue items with subscriber_id = 0 that have subscriber_data.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is hardcoded and safe.
		$orphaned_items = $wpdb->get_results(
			"SELECT id, subscriber_data FROM {$queue_table} WHERE subscriber_id = 0 AND subscriber_data IS NOT NULL AND status = 'pending'"
		);

		if ( empty( $orphaned_items ) ) {
			return;
		}

		$subscribers_table = $wpdb->prefix . 'mskd_subscribers';

		foreach ( $orphaned_items as $item ) {
			$data = json_decode( $item->subscriber_data, true );

			if ( ! $data || empty( $data['email'] ) || ! is_email( $data['email'] ) ) {
				continue;
			}

			$email      = sanitize_email( $data['email'] );
			$first_name = isset( $data['first_name'] ) ? sanitize_text_field( $data['first_name'] ) : '';
			$last_name  = isset( $data['last_name'] ) ? sanitize_text_field( $data['last_name'] ) : '';
			$source     = isset( $data['source'] ) && 'one-time-email' === $data['source'] ? 'one_time' : 'external';

			// Check if subscriber already exists.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is hardcoded and safe.
			$existing = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$subscribers_table} WHERE email = %s",
					$email
				)
			);

			if ( $existing ) {
				$subscriber_id = (int) $existing;
			} else {
				// Create new subscriber.
				$token = wp_generate_password( 32, false );

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->insert(
					$subscribers_table,
					array(
						'email'             => $email,
						'first_name'        => $first_name,
						'last_name'         => $last_name,
						'status'            => 'active',
						'source'            => $source,
						'unsubscribe_token' => $token,
					),
					array( '%s', '%s', '%s', '%s', '%s', '%s' )
				);

				$subscriber_id = $wpdb->insert_id;
			}

			if ( $subscriber_id ) {
				// Update queue item with the new subscriber_id.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update(
					$queue_table,
					array( 'subscriber_id' => $subscriber_id ),
					array( 'id' => $item->id ),
					array( '%d' ),
					array( '%d' )
				);
			}
		}
	}

	/**
	 * Schedule cron events.
	 */
	private static function schedule_cron() {
		if ( ! wp_next_scheduled( 'mskd_process_queue' ) ) {
			// Schedule at the start of the next minute (00 seconds).
			$next_minute = mskd_normalize_timestamp( time() + 60 );
			wp_schedule_event( $next_minute, 'mskd_every_minute', 'mskd_process_queue' );
		}
	}

	/**
	 * Set default plugin options
	 */
	private static function set_default_options() {
		$defaults = array(
			'from_name'         => get_bloginfo( 'name' ),
			'from_email'        => get_bloginfo( 'admin_email' ),
			'reply_to'          => get_bloginfo( 'admin_email' ),
			'emails_per_minute' => MSKD_BATCH_SIZE,
		);

		if ( ! get_option( 'mskd_settings' ) ) {
			update_option( 'mskd_settings', $defaults );
		}
	}
}
