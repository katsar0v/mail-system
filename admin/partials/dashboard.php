<?php
/**
 * Dashboard page
 *
 * @package MSKD
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

// Get subscriber statistics in a single query.
$subscriber_stats     = $wpdb->get_row(
	"SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive,
        SUM(CASE WHEN status = 'unsubscribed' THEN 1 ELSE 0 END) as unsubscribed
    FROM {$wpdb->prefix}mskd_subscribers"
);
$total_subscribers    = $subscriber_stats->total ?? 0;
$active_subscribers   = $subscriber_stats->active ?? 0;
$inactive_subscribers = $subscriber_stats->inactive ?? 0;
$unsubscribed         = $subscriber_stats->unsubscribed ?? 0;

// Get list count.
$total_lists = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}mskd_lists" );

// Get queue statistics in a single query.
$queue_stats     = $wpdb->get_row(
	$wpdb->prepare(
		"SELECT 
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
            SUM(CASE WHEN subscriber_id = %d THEN 1 ELSE 0 END) as one_time
        FROM {$wpdb->prefix}mskd_queue",
		MSKD_Admin::ONE_TIME_EMAIL_SUBSCRIBER_ID
	)
);
$pending_emails  = $queue_stats->pending ?? 0;
$sent_emails     = $queue_stats->sent ?? 0;
$failed_emails   = $queue_stats->failed ?? 0;
$one_time_emails = $queue_stats->one_time ?? 0;

// Get next cron run.
$next_cron = wp_next_scheduled( 'mskd_process_queue' );

// Get configured emails per minute from settings.
$settings          = get_option( 'mskd_settings', array() );
$emails_per_minute = isset( $settings['emails_per_minute'] ) ? absint( $settings['emails_per_minute'] ) : MSKD_BATCH_SIZE;
?>

<div class="wrap mskd-wrap">
	<h1><?php esc_html_e( 'Dashboard - Mail System', 'mail-system' ); ?></h1>

	<?php settings_errors( 'mskd_messages' ); ?>

	<div class="mskd-dashboard">
		<!-- Quick Actions -->
		<div class="mskd-quick-actions">
			<h2><?php esc_html_e( 'Quick Actions', 'mail-system' ); ?></h2>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-subscribers&action=add' ) ); ?>" class="button button-primary">
				<?php esc_html_e( 'Add subscriber', 'mail-system' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-lists&action=add' ) ); ?>" class="button button-primary">
				<?php esc_html_e( 'Add list', 'mail-system' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-compose' ) ); ?>" class="button button-primary">
				<?php esc_html_e( 'New campaign', 'mail-system' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-one-time-email' ) ); ?>" class="button button-secondary">
				<?php esc_html_e( 'One-time email', 'mail-system' ); ?>
			</a>
		</div>

		<!-- Queue Stats Summary Card -->
		<div class="mskd-queue-stats-card">
			<div class="mskd-widget-stats">
				<div class="mskd-widget-stat mskd-widget-stat-pending">
					<span class="mskd-widget-stat-number"><?php echo esc_html( $pending_emails ); ?></span>
					<span class="mskd-widget-stat-label"><?php esc_html_e( 'Pending', 'mail-system' ); ?></span>
				</div>
				<div class="mskd-widget-stat mskd-widget-stat-sent">
					<span class="mskd-widget-stat-number"><?php echo esc_html( $sent_emails ); ?></span>
					<span class="mskd-widget-stat-label"><?php esc_html_e( 'Sent', 'mail-system' ); ?></span>
				</div>
				<div class="mskd-widget-stat mskd-widget-stat-failed">
					<span class="mskd-widget-stat-number"><?php echo esc_html( $failed_emails ); ?></span>
					<span class="mskd-widget-stat-label"><?php esc_html_e( 'Failed', 'mail-system' ); ?></span>
				</div>
			</div>
			<div class="mskd-widget-cron">
				<?php
				$last_cron_run = get_option( 'mskd_last_cron_run', 0 );
				if ( $last_cron_run ) :
					?>
					<p>
						<strong><?php esc_html_e( 'Last cron run:', 'mail-system' ); ?></strong>
						<?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_cron_run ) ); ?>
					</p>
				<?php else : ?>
					<p class="mskd-widget-cron-never">
						<?php esc_html_e( 'Cron has not run yet.', 'mail-system' ); ?>
					</p>
				<?php endif; ?>
			</div>
		</div>

		<div class="mskd-stats-grid">
			<!-- Subscribers Stats -->
			<div class="mskd-stat-box">
				<h3><?php esc_html_e( 'Subscribers', 'mail-system' ); ?></h3>
				<div class="mskd-stat-number"><?php echo esc_html( $total_subscribers ); ?></div>
				<div class="mskd-stat-details">
					<?php /* translators: %d: number of active subscribers */ ?>
					<span class="mskd-status-active"><?php printf( esc_html__( 'Active: %d', 'mail-system' ), esc_html( $active_subscribers ) ); ?></span>
					<?php /* translators: %d: number of inactive subscribers */ ?>
					<span class="mskd-status-inactive"><?php printf( esc_html__( 'Inactive: %d', 'mail-system' ), esc_html( $inactive_subscribers ) ); ?></span>
					<?php /* translators: %d: number of unsubscribed subscribers */ ?>
					<span class="mskd-status-unsubscribed"><?php printf( esc_html__( 'Unsubscribed: %d', 'mail-system' ), esc_html( $unsubscribed ) ); ?></span>
				</div>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-subscribers' ) ); ?>" class="button">
					<?php esc_html_e( 'View all', 'mail-system' ); ?>
				</a>
			</div>

			<!-- Lists Stats -->
			<div class="mskd-stat-box">
				<h3><?php esc_html_e( 'Lists', 'mail-system' ); ?></h3>
				<div class="mskd-stat-number"><?php echo esc_html( $total_lists ); ?></div>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-lists' ) ); ?>" class="button">
					<?php esc_html_e( 'Manage', 'mail-system' ); ?>
				</a>
			</div>

			<!-- Queue Stats -->
			<div class="mskd-stat-box">
				<h3><?php esc_html_e( 'Queue', 'mail-system' ); ?></h3>
				<div class="mskd-stat-number"><?php echo esc_html( $pending_emails ); ?></div>
				<div class="mskd-stat-details">
					<?php /* translators: %d: number of pending emails */ ?>
					<span class="mskd-status-pending"><?php printf( esc_html__( 'Pending: %d', 'mail-system' ), esc_html( $pending_emails ) ); ?></span>
					<?php /* translators: %d: number of sent emails */ ?>
					<span class="mskd-status-sent"><?php printf( esc_html__( 'Sent: %d', 'mail-system' ), esc_html( $sent_emails ) ); ?></span>
					<?php /* translators: %d: number of failed emails */ ?>
					<span class="mskd-status-failed"><?php printf( esc_html__( 'Failed: %d', 'mail-system' ), esc_html( $failed_emails ) ); ?></span>
					<?php /* translators: %d: number of one-time emails */ ?>
					<span class="mskd-status-one-time"><?php printf( esc_html__( 'One-time: %d', 'mail-system' ), esc_html( $one_time_emails ) ); ?></span>
				</div>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-queue' ) ); ?>" class="button">
					<?php esc_html_e( 'View queue', 'mail-system' ); ?>
				</a>
			</div>

			<!-- Cron Status -->
			<div class="mskd-stat-box">
				<h3><?php esc_html_e( 'Cron status', 'mail-system' ); ?></h3>
				<?php if ( $next_cron ) : ?>
					<div class="mskd-stat-info">
						<span class="mskd-cron-active"><?php esc_html_e( 'Active', 'mail-system' ); ?></span>
					<?php
					/* translators: %s: next cron scheduled time */
					printf( esc_html__( 'Next run: %s', 'mail-system' ), '<p>' . esc_html( date_i18n( 'd.m.Y H:i:s', $next_cron ) ) . '</p>' );
					?>
					</div>
				<?php else : ?>
					<div class="mskd-stat-info">
						<span class="mskd-cron-inactive"><?php esc_html_e( 'Inactive', 'mail-system' ); ?></span>
					</div>
				<?php endif; ?>
				<p class="description">
					<?php
					/* translators: %d: number of emails per minute */
					printf( esc_html__( 'Sending: %d emails/min', 'mail-system' ), esc_html( $emails_per_minute ) );
					?>
				</p>
			</div>
		</div>
	</div>
</div>
