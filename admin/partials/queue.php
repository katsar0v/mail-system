<?php
/**
 * Queue page - Shows campaigns (grouped emails) overview
 *
 * @package MSKD
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

// Check if viewing a specific campaign detail.
$action      = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : '';
$campaign_id = isset( $_GET['campaign_id'] ) ? intval( $_GET['campaign_id'] ) : 0;
$view        = isset( $_GET['view'] ) ? sanitize_text_field( $_GET['view'] ) : '';

// If viewing campaign details, include the detail partial.
if ( $action === 'view' && $campaign_id > 0 ) {
	include MSKD_PLUGIN_DIR . 'admin/partials/queue-detail.php';
	return;
}

// If viewing legacy emails (without campaign_id).
if ( $view === 'legacy' ) {
	include MSKD_PLUGIN_DIR . 'admin/partials/queue-legacy.php';
	return;
}

// Pagination.
$per_page     = 20;
$current_page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
$offset       = ( $current_page - 1 ) * $per_page;

// Filter by status or type.
$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';
$type_filter   = isset( $_GET['type'] ) ? sanitize_text_field( $_GET['type'] ) : '';

// Build WHERE clause for campaigns.
$where = ' WHERE 1=1';
if ( $status_filter ) {
	$where .= $wpdb->prepare( ' AND c.status = %s', $status_filter );
}
if ( $type_filter === 'one-time' ) {
	$where .= " AND c.type = 'one_time'";
} elseif ( $type_filter === 'campaign' ) {
	$where .= " AND c.type = 'campaign'";
} elseif ( $type_filter === 'scheduled' ) {
	$where .= $wpdb->prepare( " AND c.status = 'pending' AND c.scheduled_at > %s", current_time( 'mysql' ) );
}

// Get campaign counts.
$campaign_counts  = $wpdb->get_row(
	$wpdb->prepare(
		"SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
            SUM(CASE WHEN type = 'one_time' THEN 1 ELSE 0 END) as one_time,
            SUM(CASE WHEN type = 'campaign' THEN 1 ELSE 0 END) as campaigns,
            SUM(CASE WHEN status = 'pending' AND scheduled_at > %s THEN 1 ELSE 0 END) as scheduled
        FROM {$wpdb->prefix}mskd_campaigns",
		current_time( 'mysql' )
	)
);
$total_count      = $campaign_counts->total ?? 0;
$pending_count    = $campaign_counts->pending ?? 0;
$processing_count = $campaign_counts->processing ?? 0;
$completed_count  = $campaign_counts->completed ?? 0;
$cancelled_count  = $campaign_counts->cancelled ?? 0;
$one_time_count   = $campaign_counts->one_time ?? 0;
$campaign_count   = $campaign_counts->campaigns ?? 0;
$scheduled_count  = $campaign_counts->scheduled ?? 0;

// Get total count for current filter.
$total_items = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}mskd_campaigns c" . $where );
$total_pages = ceil( $total_items / $per_page );

// Get campaigns with aggregated queue stats.
$campaigns = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT 
        c.*,
        COALESCE(q.sent_count, 0) as sent_count,
        COALESCE(q.failed_count, 0) as failed_count,
	        COALESCE(q.pending_count, 0) as pending_count,
	        COALESCE(q.processing_count, 0) as processing_count,
	        COALESCE(q.cancelled_count, 0) as cancelled_count,
	        COALESCE(q.opened_count, 0) as opened_count,
	        COALESCE(q.open_count, 0) as open_count
    FROM {$wpdb->prefix}mskd_campaigns c
    LEFT JOIN (
        SELECT 
            campaign_id,
            SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_count,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count,
	            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
	            SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing_count,
	            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count,
	            SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) as opened_count,
	            SUM(open_count) as open_count
        FROM {$wpdb->prefix}mskd_queue
        WHERE campaign_id IS NOT NULL
        GROUP BY campaign_id
    ) q ON c.id = q.campaign_id"
		. $where .
		' ORDER BY c.created_at DESC LIMIT %d OFFSET %d',
		$per_page,
		$offset
	)
);

// Also check for orphan queue items (without campaign_id) for backwards compatibility.
$orphan_count = $wpdb->get_var(
	"SELECT COUNT(*) FROM {$wpdb->prefix}mskd_queue WHERE campaign_id IS NULL"
);

// Next cron run.
$next_cron = wp_next_scheduled( 'mskd_process_queue' );

// Get configured emails per minute from settings.
$settings          = get_option( 'mskd_settings', array() );
$emails_per_minute = isset( $settings['emails_per_minute'] ) ? absint( $settings['emails_per_minute'] ) : MSKD_BATCH_SIZE;
?>

<div class="wrap mskd-wrap">
	<h1><?php esc_html_e( 'Sending queue', 'mail-system' ); ?></h1>

	<?php settings_errors( 'mskd_messages' ); ?>

	<!-- Queue Status -->
	<div class="mskd-queue-status">
		<p>
			<strong><?php esc_html_e( 'Next run:', 'mail-system' ); ?></strong>
			<?php if ( $next_cron ) : ?>
				<?php echo date_i18n( 'd.m.Y H:i:s', $next_cron ); ?>
			<?php else : ?>
				<?php esc_html_e( 'Not scheduled', 'mail-system' ); ?>
			<?php endif; ?>
			&nbsp;|&nbsp;
			<strong><?php esc_html_e( 'Speed:', 'mail-system' ); ?></strong>
			<?php printf( __( '%d emails/min', 'mail-system' ), $emails_per_minute ); ?>
		</p>
	</div>

	<!-- Filters -->
	<ul class="subsubsub">
		<li>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-queue' ) ); ?>" 
				class="<?php echo empty( $status_filter ) && empty( $type_filter ) ? 'current' : ''; ?>">
				<?php esc_html_e( 'All', 'mail-system' ); ?>
				<span class="count">(<?php echo esc_html( $total_count ); ?>)</span>
			</a> |
		</li>
		<li>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-queue&type=scheduled' ) ); ?>"
				class="<?php echo $type_filter === 'scheduled' ? 'current' : ''; ?>">
				<?php esc_html_e( 'Scheduled', 'mail-system' ); ?>
				<span class="count">(<?php echo esc_html( $scheduled_count ); ?>)</span>
			</a> |
		</li>
		<li>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-queue&status=pending' ) ); ?>"
				class="<?php echo $status_filter === 'pending' ? 'current' : ''; ?>">
				<?php esc_html_e( 'Pending', 'mail-system' ); ?>
				<span class="count">(<?php echo esc_html( $pending_count ); ?>)</span>
			</a> |
		</li>
		<li>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-queue&status=processing' ) ); ?>"
				class="<?php echo $status_filter === 'processing' ? 'current' : ''; ?>">
				<?php esc_html_e( 'Processing', 'mail-system' ); ?>
				<span class="count">(<?php echo esc_html( $processing_count ); ?>)</span>
			</a> |
		</li>
		<li>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-queue&status=completed' ) ); ?>"
				class="<?php echo $status_filter === 'completed' ? 'current' : ''; ?>">
				<?php esc_html_e( 'Completed', 'mail-system' ); ?>
				<span class="count">(<?php echo esc_html( $completed_count ); ?>)</span>
			</a> |
		</li>
		<li>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-queue&status=cancelled' ) ); ?>"
				class="<?php echo $status_filter === 'cancelled' ? 'current' : ''; ?>">
				<?php esc_html_e( 'Cancelled', 'mail-system' ); ?>
				<span class="count">(<?php echo esc_html( $cancelled_count ); ?>)</span>
			</a> |
		</li>
		<li>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-queue&type=one-time' ) ); ?>"
				class="<?php echo $type_filter === 'one-time' ? 'current' : ''; ?>">
				<?php esc_html_e( 'One-time', 'mail-system' ); ?>
				<span class="count">(<?php echo esc_html( $one_time_count ); ?>)</span>
			</a> |
		</li>
		<li>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-queue&type=campaign' ) ); ?>"
				class="<?php echo $type_filter === 'campaign' ? 'current' : ''; ?>">
				<?php esc_html_e( 'Campaigns', 'mail-system' ); ?>
				<span class="count">(<?php echo esc_html( $campaign_count ); ?>)</span>
			</a>
		</li>
	</ul>

	<?php if ( $orphan_count > 0 ) : ?>
		<div class="notice notice-info inline" style="margin-top: 15px;">
			<p>
				<?php
				printf(
					__( 'There are %d emails from before the campaign system was introduced.', 'mail-system' ),
					$orphan_count
				);
				?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-queue&view=legacy' ) ); ?>">
					<?php esc_html_e( 'View legacy emails', 'mail-system' ); ?>
				</a>
			</p>
		</div>
	<?php endif; ?>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th scope="col" style="width: 50px;"><?php esc_html_e( 'ID', 'mail-system' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Subject', 'mail-system' ); ?></th>
				<th scope="col" style="width: 100px;"><?php esc_html_e( 'Type', 'mail-system' ); ?></th>
				<th scope="col" style="width: 100px;"><?php esc_html_e( 'Recipients', 'mail-system' ); ?></th>
				<th scope="col" style="width: 180px;"><?php esc_html_e( 'Progress', 'mail-system' ); ?></th>
				<th scope="col" style="width: 130px;"><?php esc_html_e( 'Opens', 'mail-system' ); ?></th>
				<th scope="col" style="width: 100px;"><?php esc_html_e( 'Status', 'mail-system' ); ?></th>
				<th scope="col" style="width: 140px;"><?php esc_html_e( 'Created', 'mail-system' ); ?></th>
				<th scope="col" style="width: 140px;"><?php esc_html_e( 'Scheduled for', 'mail-system' ); ?></th>
				<th scope="col" style="width: 120px;"><?php esc_html_e( 'Actions', 'mail-system' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( ! empty( $campaigns ) ) : ?>
				<?php foreach ( $campaigns as $campaign ) : ?>
					<?php
					// Check if campaign is scheduled for the future
					$scheduled_timestamp = strtotime( $campaign->scheduled_at );
					$is_future_scheduled = $scheduled_timestamp > current_time( 'timestamp' );
					$can_cancel          = in_array( $campaign->status, array( 'pending', 'processing' ), true );

					// Calculate progress
					$total            = intval( $campaign->total_recipients );
					$sent             = intval( $campaign->sent_count );
					$failed           = intval( $campaign->failed_count );
					$pending          = intval( $campaign->pending_count );
					$processing       = intval( $campaign->processing_count );
					$cancelled        = intval( $campaign->cancelled_count );
					$opened           = intval( $campaign->opened_count );
					$open_count       = intval( $campaign->open_count );
					$completed        = $sent + $failed + $cancelled;
					$progress_percent = $total > 0 ? round( ( $completed / $total ) * 100 ) : 0;
					$open_rate        = $sent > 0 ? round( ( $opened / $sent ) * 100, 1 ) : 0;
					?>
					<tr>
						<td><?php echo esc_html( $campaign->id ); ?></td>
						<td>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-queue&action=view&campaign_id=' . $campaign->id ) ); ?>">
								<strong><?php echo esc_html( $campaign->subject ); ?></strong>
							</a>
						</td>
						<td>
							<?php if ( $campaign->type === 'one_time' ) : ?>
								<span class="mskd-badge mskd-badge-onetime"><?php esc_html_e( 'One-time', 'mail-system' ); ?></span>
							<?php else : ?>
								<span class="mskd-badge mskd-badge-campaign"><?php esc_html_e( 'Campaign', 'mail-system' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<strong><?php echo esc_html( $total ); ?></strong>
						</td>
						<td>
							<div class="mskd-progress-bar">
								<div class="mskd-progress-bar-inner" style="width: <?php echo esc_attr( $progress_percent ); ?>%;"></div>
							</div>
							<small>
								<span class="mskd-stat-sent" title="<?php esc_attr_e( 'Sent', 'mail-system' ); ?>">✓ <?php echo esc_html( $sent ); ?></span>
								<?php if ( $failed > 0 ) : ?>
									<span class="mskd-stat-failed" title="<?php esc_attr_e( 'Failed', 'mail-system' ); ?>">✗ <?php echo esc_html( $failed ); ?></span>
								<?php endif; ?>
								<?php if ( $pending > 0 || $processing > 0 ) : ?>
									<span class="mskd-stat-pending" title="<?php esc_attr_e( 'Pending', 'mail-system' ); ?>">⏳ <?php echo esc_html( $pending + $processing ); ?></span>
								<?php endif; ?>
							</small>
						</td>
						<td>
							<strong><?php echo esc_html( $opened ); ?></strong>
							<small>(<?php echo esc_html( $open_rate ); ?>%)</small>
							<?php if ( $open_count > $opened ) : ?>
								<br><small title="<?php esc_attr_e( 'Total tracking pixel loads', 'mail-system' ); ?>">
									<?php
									printf(
										/* translators: %d: tracking pixel load count */
										esc_html__( 'Loads: %d', 'mail-system' ),
										$open_count
									);
									?>
								</small>
							<?php endif; ?>
						</td>
						<td>
							<span class="mskd-status mskd-status-<?php echo esc_attr( $campaign->status ); ?>">
								<?php
								$statuses = array(
									'pending'    => __( 'Pending', 'mail-system' ),
									'processing' => __( 'Processing', 'mail-system' ),
									'completed'  => __( 'Completed', 'mail-system' ),
									'cancelled'  => __( 'Cancelled', 'mail-system' ),
								);
								echo esc_html( $statuses[ $campaign->status ] ?? $campaign->status );
								?>
							</span>
							<?php if ( $is_future_scheduled && $campaign->status === 'pending' ) : ?>
								<br><small class="mskd-scheduled-badge"><?php esc_html_e( 'Scheduled', 'mail-system' ); ?></small>
							<?php endif; ?>
						</td>
						<td>
							<?php echo esc_html( date_i18n( 'd.m.Y H:i', strtotime( $campaign->created_at ) ) ); ?>
						</td>
						<td>
							<?php echo esc_html( date_i18n( 'd.m.Y H:i', $scheduled_timestamp ) ); ?>
							<?php if ( $is_future_scheduled && $campaign->status === 'pending' ) : ?>
								<br><small class="mskd-time-remaining">
									<?php
									$diff = $scheduled_timestamp - current_time( 'timestamp' );
									if ( $diff < 3600 ) {
										printf( __( 'in %d min.', 'mail-system' ), ceil( $diff / 60 ) );
									} elseif ( $diff < 86400 ) {
										printf( __( 'in %d h.', 'mail-system' ), ceil( $diff / 3600 ) );
									} else {
										printf( __( 'in %d days', 'mail-system' ), ceil( $diff / 86400 ) );
									}
									?>
								</small>
							<?php endif; ?>
						</td>
						<td>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-queue&action=view&campaign_id=' . $campaign->id ) ); ?>" 
								class="button button-small"
								title="<?php esc_attr_e( 'View details', 'mail-system' ); ?>">
								<?php esc_html_e( 'Details', 'mail-system' ); ?>
							</a>
							<?php if ( $can_cancel ) : ?>
								<a href="
								<?php
								echo wp_nonce_url(
									admin_url( 'admin.php?page=mskd-queue&action=cancel_campaign&id=' . $campaign->id ),
									'cancel_campaign_' . $campaign->id
								);
								?>
								" 
									class="mskd-delete-link mskd-cancel-link"
									title="<?php esc_attr_e( 'Cancel campaign', 'mail-system' ); ?>">
									<?php esc_html_e( 'Cancel', 'mail-system' ); ?>
								</a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php else : ?>
				<tr>
					<td colspan="10"><?php esc_html_e( 'No campaigns in queue.', 'mail-system' ); ?></td>
				</tr>
			<?php endif; ?>
		</tbody>
	</table>

	<!-- Pagination -->
	<?php if ( $total_pages > 1 ) : ?>
		<div class="tablenav bottom">
			<div class="tablenav-pages">
				<?php
				echo paginate_links(
					array(
						'base'      => add_query_arg( 'paged', '%#%' ),
						'format'    => '',
						'prev_text' => __( '&laquo;', 'mail-system' ),
						'next_text' => __( '&raquo;', 'mail-system' ),
						'total'     => $total_pages,
						'current'   => $current_page,
					)
				);
				?>
			</div>
		</div>
	<?php endif; ?>
</div>
