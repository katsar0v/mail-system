<?php
/**
 * Uninstall script
 *
 * This file runs when the plugin is deleted from WordPress.
 * It removes all plugin data from the database.
 *
 * @package MSKD
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Delete plugin options.
delete_option( 'mskd_settings' );
delete_option( 'mskd_db_version' );
delete_option( 'mskd_total_campaigns_created' );
delete_option( 'mskd_share_notice_dismissed' );
delete_option( 'mskd_last_cron_run' );

// Drop custom tables.
$tables = array(
	$wpdb->prefix . 'mskd_api_tokens',
	$wpdb->prefix . 'mskd_subscriber_list',
	$wpdb->prefix . 'mskd_clicks',
	$wpdb->prefix . 'mskd_queue',
	$wpdb->prefix . 'mskd_campaigns',
	$wpdb->prefix . 'mskd_templates',
	$wpdb->prefix . 'mskd_subscribers',
	$wpdb->prefix . 'mskd_lists',
);

foreach ( $tables as $table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table cleanup during uninstall, no caching needed.
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is hardcoded and safe.
	$wpdb->query( "DROP TABLE IF EXISTS $table" );
}

// Clear scheduled cron events.
wp_clear_scheduled_hook( 'mskd_process_queue' );
