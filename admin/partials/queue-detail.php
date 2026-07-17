<?php
/**
 * Queue Campaign Detail page - Shows individual emails for a campaign
 *
 * @package MSKD
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

$campaign_id = isset( $_GET['campaign_id'] ) ? intval( $_GET['campaign_id'] ) : 0;

// Get campaign data.
$campaign = $wpdb->get_row(
	$wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}mskd_campaigns WHERE id = %d",
		$campaign_id
	)
);

if ( ! $campaign ) {
	echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__( 'Campaign not found.', 'mail-system' ) . '</p></div></div>';
	return;
}

// Pagination.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only parameter for pagination.
$per_page     = 50;
$current_page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
$offset       = ( $current_page - 1 ) * $per_page;

// Filter by status.
$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
$where         = $wpdb->prepare( ' WHERE q.campaign_id = %d', $campaign_id );
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only parameter for filtering.
if ( $status_filter ) {
	$where .= $wpdb->prepare( ' AND q.status = %s', $status_filter );
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only analytics filter.
$opened_filter = isset( $_GET['engagement'] ) && 'opened' === sanitize_text_field( wp_unslash( $_GET['engagement'] ) );
if ( $opened_filter ) {
	$where .= ' AND q.opened_at IS NOT NULL';
}

// Get queue stats for this campaign.
$queue_stats = $wpdb->get_row(
	$wpdb->prepare(
		"SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
        SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
	        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
	        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
	        SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) as opened,
	        SUM(open_count) as open_count
    FROM {$wpdb->prefix}mskd_queue
    WHERE campaign_id = %d",
		$campaign_id
	)
);

$total_count      = $queue_stats->total ?? 0;
$pending_count    = $queue_stats->pending ?? 0;
$processing_count = $queue_stats->processing ?? 0;
$sent_count       = $queue_stats->sent ?? 0;
$failed_count     = $queue_stats->failed ?? 0;
$cancelled_count  = $queue_stats->cancelled ?? 0;
$opened_count     = $queue_stats->opened ?? 0;
$open_count       = $queue_stats->open_count ?? 0;
$open_rate        = $sent_count > 0 ? round( ( $opened_count / $sent_count ) * 100, 1 ) : 0;

// Get total count for current filter.
$total_items = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}mskd_queue q" . $where );
$total_pages = ceil( $total_items / $per_page );

// Get queue items for this campaign.
$queue_items = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT q.*, s.email, s.first_name, s.last_name 
    FROM {$wpdb->prefix}mskd_queue q
    LEFT JOIN {$wpdb->prefix}mskd_subscribers s ON q.subscriber_id = s.id"
		. $where .
		' ORDER BY q.id ASC LIMIT %d OFFSET %d',
		$per_page,
		$offset
	)
);

// Calculate progress.
$completed        = $sent_count + $failed_count + $cancelled_count;
$progress_percent = $total_count > 0 ? round( ( $completed / $total_count ) * 100 ) : 0;

// Decode list IDs.
$list_ids   = $campaign->list_ids ? json_decode( $campaign->list_ids, true ) : array();
$list_names = array();
if ( ! empty( $list_ids ) ) {
	require_once MSKD_PLUGIN_DIR . 'includes/services/class-mskd-list-provider.php';
	foreach ( $list_ids as $list_id ) {
		$list = MSKD_List_Provider::get_list( $list_id );
		if ( $list ) {
			$list_names[] = $list->name;
		}
	}
}

$can_cancel = in_array( $campaign->status, array( 'pending', 'processing' ), true );
?>

<div class="wrap mskd-wrap">
	<h1>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-queue' ) ); ?>" class="page-title-action" style="margin-right: 10px;">
			← <?php _e( 'Back to Queue', 'mail-system' ); ?>
		</a>
		<?php printf( __( 'Campaign #%d', 'mail-system' ), $campaign->id ); ?>
	</h1>

	<?php settings_errors( 'mskd_messages' ); ?>

	<!-- Campaign Info Card -->
	<div class="mskd-campaign-info-card">
		<div class="mskd-campaign-header">
			<h2><?php echo esc_html( $campaign->subject ); ?></h2>
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
			<?php if ( $campaign->type === 'one_time' ) : ?>
				<span class="mskd-badge mskd-badge-onetime"><?php _e( 'One-time', 'mail-system' ); ?></span>
			<?php else : ?>
				<span class="mskd-badge mskd-badge-campaign"><?php _e( 'Campaign', 'mail-system' ); ?></span>
			<?php endif; ?>
		</div>
		
		<div class="mskd-campaign-meta">
			<div class="mskd-campaign-meta-item">
				<strong><?php _e( 'Created:', 'mail-system' ); ?></strong>
				<?php echo esc_html( date_i18n( 'd.m.Y H:i', strtotime( $campaign->created_at ) ) ); ?>
			</div>
			<div class="mskd-campaign-meta-item">
				<strong><?php _e( 'Scheduled:', 'mail-system' ); ?></strong>
				<?php echo esc_html( date_i18n( 'd.m.Y H:i', strtotime( $campaign->scheduled_at ) ) ); ?>
			</div>
			<?php if ( $campaign->completed_at ) : ?>
				<div class="mskd-campaign-meta-item">
					<strong><?php _e( 'Completed:', 'mail-system' ); ?></strong>
					<?php echo esc_html( date_i18n( 'd.m.Y H:i', strtotime( $campaign->completed_at ) ) ); ?>
				</div>
			<?php endif; ?>
			<?php if ( ! empty( $list_names ) ) : ?>
				<div class="mskd-campaign-meta-item">
					<strong><?php _e( 'Lists:', 'mail-system' ); ?></strong>
					<?php echo esc_html( implode( ', ', $list_names ) ); ?>
				</div>
			<?php endif; ?>
			<?php if ( ! empty( $campaign->bcc ) ) : ?>
				<div class="mskd-campaign-meta-item">
					<strong><?php _e( 'BCC:', 'mail-system' ); ?></strong>
					<?php echo esc_html( $campaign->bcc ); ?>
				</div>
			<?php endif; ?>
		</div>

		<div class="mskd-campaign-progress">
			<div class="mskd-progress-bar mskd-progress-bar-large">
				<div class="mskd-progress-bar-inner" style="width: <?php echo esc_attr( $progress_percent ); ?>%;"></div>
			</div>
			<div class="mskd-campaign-stats">
				<span class="mskd-stat-total">
					<strong><?php echo esc_html( $total_count ); ?></strong> <?php _e( 'total', 'mail-system' ); ?>
				</span>
				<span class="mskd-stat-sent">
					✓ <strong><?php echo esc_html( $sent_count ); ?></strong> <?php _e( 'sent', 'mail-system' ); ?>
				</span>
				<span class="mskd-stat-opened">
					◉ <strong><?php echo esc_html( $opened_count ); ?></strong> <?php _e( 'opened', 'mail-system' ); ?>
					<small>
						(<?php echo esc_html( $open_rate ); ?>%;
						<?php
						printf(
							/* translators: %d: tracking pixel load count */
							esc_html__( 'Loads: %d', 'mail-system' ),
							$open_count
						);
						?>
						)
					</small>
				</span>
				<?php if ( $failed_count > 0 ) : ?>
					<span class="mskd-stat-failed">
						✗ <strong><?php echo esc_html( $failed_count ); ?></strong> <?php _e( 'failed', 'mail-system' ); ?>
					</span>
				<?php endif; ?>
				<?php if ( $pending_count > 0 || $processing_count > 0 ) : ?>
					<span class="mskd-stat-pending">
						⏳ <strong><?php echo esc_html( $pending_count + $processing_count ); ?></strong> <?php _e( 'pending', 'mail-system' ); ?>
					</span>
				<?php endif; ?>
				<?php if ( $cancelled_count > 0 ) : ?>
					<span class="mskd-stat-cancelled">
						⊘ <strong><?php echo esc_html( $cancelled_count ); ?></strong> <?php _e( 'cancelled', 'mail-system' ); ?>
					</span>
				<?php endif; ?>
			</div>
		</div>

		<p class="description">
			<?php esc_html_e( 'Open tracking is approximate: image blocking can miss opens, while email privacy proxies and prefetching can record an open before the recipient reads the message.', 'mail-system' ); ?>
		</p>

		<?php if ( $can_cancel ) : ?>
			<div class="mskd-campaign-actions">
				<a href="
				<?php
				echo wp_nonce_url(
					admin_url( 'admin.php?page=mskd-queue&action=cancel_campaign&id=' . $campaign->id ),
					'cancel_campaign_' . $campaign->id
				);
				?>
				" 
					class="button button-secondary mskd-cancel-btn"
					onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to cancel this campaign? All pending emails will be cancelled.', 'mail-system' ); ?>');">
					<?php _e( 'Cancel Campaign', 'mail-system' ); ?>
				</a>
			</div>
		<?php endif; ?>
	</div>

	<!-- Email Content Accordion -->
	<div class="mskd-email-content-accordion">
		<button type="button" class="mskd-accordion-toggle" aria-expanded="false" aria-controls="mskd-email-content">
			<span class="mskd-accordion-icon dashicons dashicons-email-alt"></span>
			<span class="mskd-accordion-title"><?php esc_html_e( 'Email Content', 'mail-system' ); ?></span>
			<span class="mskd-accordion-arrow dashicons dashicons-arrow-down-alt2"></span>
		</button>
		<div id="mskd-email-content" class="mskd-accordion-content" style="display: none;" aria-hidden="true">
			<div class="mskd-email-meta">
				<div class="mskd-email-subject">
					<span class="mskd-email-label"><?php esc_html_e( 'Subject:', 'mail-system' ); ?></span>
					<span class="mskd-email-value"><?php echo esc_html( $campaign->subject ); ?></span>
				</div>
			</div>
			<div class="mskd-email-body">
				<div class="mskd-email-body-header">
					<span class="dashicons dashicons-visibility"></span>
					<?php esc_html_e( 'Email Preview (with header & footer)', 'mail-system' ); ?>
				</div>
				<div class="mskd-email-body-preview">
					<iframe class="mskd-email-iframe mskd-campaign-preview-iframe" data-campaign-id="<?php echo esc_attr( $campaign->id ); ?>" name="preview_campaign_<?php echo esc_attr( $campaign->id ); ?>" sandbox="allow-same-origin" title="<?php esc_attr_e( 'Email Preview', 'mail-system' ); ?>"></iframe>
				</div>
			</div>
		</div>
	</div>

	<!-- Filters -->
	<ul class="subsubsub">
		<li>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-queue&action=view&campaign_id=' . $campaign_id ) ); ?>" 
				class="<?php echo empty( $status_filter ) && ! $opened_filter ? 'current' : ''; ?>">
				<?php _e( 'All', 'mail-system' ); ?>
				<span class="count">(<?php echo esc_html( $total_count ); ?>)</span>
			</a> |
		</li>
		<li>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-queue&action=view&campaign_id=' . $campaign_id . '&status=pending' ) ); ?>"
				class="<?php echo $status_filter === 'pending' ? 'current' : ''; ?>">
				<?php _e( 'Pending', 'mail-system' ); ?>
				<span class="count">(<?php echo esc_html( $pending_count ); ?>)</span>
			</a> |
		</li>
		<li>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-queue&action=view&campaign_id=' . $campaign_id . '&status=sent' ) ); ?>"
				class="<?php echo 'sent' === $status_filter && ! $opened_filter ? 'current' : ''; ?>">
				<?php _e( 'Sent', 'mail-system' ); ?>
				<span class="count">(<?php echo esc_html( $sent_count ); ?>)</span>
			</a> |
		</li>
		<li>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-queue&action=view&campaign_id=' . $campaign_id . '&engagement=opened' ) ); ?>"
				class="<?php echo $opened_filter ? 'current' : ''; ?>">
				<?php _e( 'Opened', 'mail-system' ); ?>
				<span class="count">(<?php echo esc_html( $opened_count ); ?>)</span>
			</a> |
		</li>
		<li>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-queue&action=view&campaign_id=' . $campaign_id . '&status=failed' ) ); ?>"
				class="<?php echo $status_filter === 'failed' ? 'current' : ''; ?>">
				<?php _e( 'Failed', 'mail-system' ); ?>
				<span class="count">(<?php echo esc_html( $failed_count ); ?>)</span>
			</a> |
		</li>
		<li>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-queue&action=view&campaign_id=' . $campaign_id . '&status=cancelled' ) ); ?>"
				class="<?php echo $status_filter === 'cancelled' ? 'current' : ''; ?>">
				<?php _e( 'Cancelled', 'mail-system' ); ?>
				<span class="count">(<?php echo esc_html( $cancelled_count ); ?>)</span>
			</a>
		</li>
	</ul>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th scope="col" style="width: 50px;"><?php _e( 'ID', 'mail-system' ); ?></th>
				<th scope="col"><?php _e( 'Recipient', 'mail-system' ); ?></th>
				<th scope="col" style="width: 100px;"><?php _e( 'Status', 'mail-system' ); ?></th>
				<th scope="col" style="width: 80px;"><?php _e( 'Attempts', 'mail-system' ); ?></th>
				<th scope="col" style="width: 140px;"><?php _e( 'Sent', 'mail-system' ); ?></th>
				<th scope="col" style="width: 160px;"><?php _e( 'Opened', 'mail-system' ); ?></th>
				<th scope="col"><?php _e( 'Error', 'mail-system' ); ?></th>
				<th scope="col" style="width: 80px;"><?php _e( 'Actions', 'mail-system' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( ! empty( $queue_items ) ) : ?>
				<?php foreach ( $queue_items as $item ) : ?>
					<?php
					$item_can_cancel = in_array( $item->status, array( 'pending', 'processing' ), true );

					// Get recipient info
					$external_data = null;
					if ( $item->subscriber_id == MSKD_Admin::ONE_TIME_EMAIL_SUBSCRIBER_ID && ! empty( $item->subscriber_data ) ) {
						$external_data = json_decode( $item->subscriber_data );
					}
					?>
					<tr>
						<td><?php echo esc_html( $item->id ); ?></td>
						<td>
							<?php if ( $external_data && ! empty( $external_data->email ) ) : ?>
								<?php echo esc_html( $external_data->email ); ?>
								<?php if ( ! empty( $external_data->first_name ) || ! empty( $external_data->last_name ) ) : ?>
									<br><small><?php echo esc_html( trim( ( $external_data->first_name ?? '' ) . ' ' . ( $external_data->last_name ?? '' ) ) ); ?></small>
								<?php endif; ?>
								<span class="mskd-external-badge"><?php _e( 'External', 'mail-system' ); ?></span>
							<?php elseif ( $item->subscriber_id == MSKD_Admin::ONE_TIME_EMAIL_SUBSCRIBER_ID ) : ?>
								<em class="mskd-one-time-email"><?php _e( 'One-time email', 'mail-system' ); ?></em>
							<?php elseif ( $item->email ) : ?>
								<?php echo esc_html( $item->email ); ?>
								<?php if ( $item->first_name || $item->last_name ) : ?>
									<br><small><?php echo esc_html( trim( $item->first_name . ' ' . $item->last_name ) ); ?></small>
								<?php endif; ?>
							<?php else : ?>
								<em><?php _e( 'Deleted subscriber', 'mail-system' ); ?></em>
							<?php endif; ?>
						</td>
						<td>
							<span class="mskd-status mskd-status-<?php echo esc_attr( $item->status ); ?>">
								<?php
								$item_statuses = array(
									'pending'    => __( 'Pending', 'mail-system' ),
									'processing' => __( 'Processing', 'mail-system' ),
									'sent'       => __( 'Sent', 'mail-system' ),
									'failed'     => __( 'Failed', 'mail-system' ),
									'cancelled'  => __( 'Cancelled', 'mail-system' ),
								);
								echo esc_html( $item_statuses[ $item->status ] ?? $item->status );
								?>
							</span>
						</td>
						<td><?php echo esc_html( $item->attempts ); ?></td>
						<td>
							<?php echo $item->sent_at ? esc_html( date_i18n( 'd.m.Y H:i', strtotime( $item->sent_at ) ) ) : '—'; ?>
						</td>
						<td>
							<?php if ( $item->opened_at ) : ?>
								<?php echo esc_html( date_i18n( 'd.m.Y H:i', strtotime( $item->opened_at ) ) ); ?>
								<br><small>
									<?php
									printf(
										/* translators: %d: tracking pixel load count */
										esc_html__( 'Loads: %d', 'mail-system' ),
										(int) $item->open_count
									);
									?>
								</small>
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
						<td>
							<?php if ( $item->error_message ) : ?>
								<small class="mskd-error-msg"><?php echo esc_html( $item->error_message ); ?></small>
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
						<td>
							<?php if ( $item_can_cancel ) : ?>
								<a href="
								<?php
								echo wp_nonce_url(
									admin_url( 'admin.php?page=mskd-queue&action=cancel_queue_item&id=' . $item->id . '&return_campaign=' . $campaign_id ),
									'cancel_queue_item_' . $item->id
								);
								?>
								" 
									class="mskd-delete-link mskd-cancel-link"
									title="<?php esc_attr_e( 'Cancel', 'mail-system' ); ?>">
									<?php _e( 'Cancel', 'mail-system' ); ?>
								</a>
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php else : ?>
				<tr>
					<td colspan="8"><?php _e( 'No emails in this campaign.', 'mail-system' ); ?></td>
				</tr>
			<?php endif; ?>
		</tbody>
	</table>

	<!-- Pagination -->
	<?php if ( $total_pages > 1 ) : ?>
		<div class="tablenav bottom">
			<div class="tablenav-pages">
				<?php
				$base_url = admin_url( 'admin.php?page=mskd-queue&action=view&campaign_id=' . $campaign_id );
				if ( $status_filter ) {
					$base_url .= '&status=' . $status_filter;
				}
				if ( $opened_filter ) {
					$base_url .= '&engagement=opened';
				}
				echo paginate_links(
					array(
						'base'      => add_query_arg( 'paged', '%#%', $base_url ),
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
