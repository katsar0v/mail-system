<?php
/**
 * Subscribers page
 *
 * @package MSKD
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

// Load the List Provider service.
require_once MSKD_PLUGIN_DIR . 'includes/services/class-mskd-list-provider.php';

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce not needed for reading view state.
$current_action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list';
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce not needed for reading view state.
$subscriber_id = isset( $_GET['id'] ) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : '';

// Get all lists for dropdown (database + external).
$lists = MSKD_List_Provider::get_all_lists();

// Get subscriber for editing (only database subscribers are editable).
$subscriber       = null;
$subscriber_lists = array();
if ( 'edit' === $current_action && $subscriber_id ) {
	// Check if this is an external subscriber (not editable).
	if ( MSKD_List_Provider::is_external_id( $subscriber_id ) ) {
		wp_redirect( admin_url( 'admin.php?page=mskd-subscribers' ) );
		exit;
	}
	$subscriber       = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}mskd_subscribers WHERE id = %d",
			intval( $subscriber_id )
		)
	);
	$subscriber_lists = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT list_id FROM {$wpdb->prefix}mskd_subscriber_list WHERE subscriber_id = %d",
			intval( $subscriber_id )
		)
	);
}
?>

<div class="wrap mskd-wrap">
	<h1>
		<?php esc_html_e( 'Subscribers', 'mail-system-by-katsarov-design' ); ?>
		<?php if ( 'list' === $current_action ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-subscribers&action=add' ) ); ?>" class="page-title-action">
				<?php esc_html_e( 'Add new', 'mail-system-by-katsarov-design' ); ?>
			</a>
		<?php endif; ?>
	</h1>

	<?php settings_errors( 'mskd_messages' ); ?>

	<?php if ( 'add' === $current_action || 'edit' === $current_action ) : ?>
		<!-- Add/Edit Form -->
		<div class="mskd-form-wrap">
			<h2><?php echo esc_html( 'add' === $current_action ? esc_html__( 'Add subscriber', 'mail-system-by-katsarov-design' ) : esc_html__( 'Edit subscriber', 'mail-system-by-katsarov-design' ) ); ?></h2>
			
			<form method="post" action="">
				<?php wp_nonce_field( 'add' === $current_action ? 'mskd_add_subscriber' : 'mskd_edit_subscriber', 'mskd_nonce' ); ?>
				
				<?php if ( 'edit' === $current_action ) : ?>
					<input type="hidden" name="subscriber_id" value="<?php echo esc_attr( $subscriber_id ); ?>">
				<?php endif; ?>

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="email"><?php esc_html_e( 'Email', 'mail-system-by-katsarov-design' ); ?> *</label>
						</th>
						<td>
							<input type="email" name="email" id="email" class="regular-text" required
									value="<?php echo $subscriber ? esc_attr( $subscriber->email ) : ''; ?>">
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="first_name"><?php esc_html_e( 'First name', 'mail-system-by-katsarov-design' ); ?></label>
						</th>
						<td>
							<input type="text" name="first_name" id="first_name" class="regular-text"
									value="<?php echo $subscriber ? esc_attr( $subscriber->first_name ) : ''; ?>">
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="last_name"><?php esc_html_e( 'Last name', 'mail-system-by-katsarov-design' ); ?></label>
						</th>
						<td>
							<input type="text" name="last_name" id="last_name" class="regular-text"
									value="<?php echo $subscriber ? esc_attr( $subscriber->last_name ) : ''; ?>">
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="status"><?php esc_html_e( 'Status', 'mail-system-by-katsarov-design' ); ?></label>
						</th>
						<td>
							<select name="status" id="status">
								<option value="active" <?php selected( $subscriber ? $subscriber->status : 'active', 'active' ); ?>>
									<?php esc_html_e( 'Active', 'mail-system-by-katsarov-design' ); ?>
								</option>
								<option value="inactive" <?php selected( $subscriber ? $subscriber->status : '', 'inactive' ); ?>>
									<?php esc_html_e( 'Inactive', 'mail-system-by-katsarov-design' ); ?>
								</option>
								<option value="unsubscribed" <?php selected( $subscriber ? $subscriber->status : '', 'unsubscribed' ); ?>>
									<?php esc_html_e( 'Unsubscribed', 'mail-system-by-katsarov-design' ); ?>
								</option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label><?php esc_html_e( 'Lists', 'mail-system-by-katsarov-design' ); ?></label>
						</th>
						<td>
							<?php
							// Only show database lists for subscriber assignment (external lists manage their own subscribers).
							$database_lists = array_filter(
								$lists,
								function ( $list_item ) {
									return 'database' === $list_item->source;
								}
							);
							?>
							<?php if ( ! empty( $database_lists ) ) : ?>
								<?php foreach ( $database_lists as $list ) : ?>
									<label style="display: block; margin-bottom: 5px;">
										<input type="checkbox" name="lists[]" value="<?php echo esc_attr( $list->id ); ?>"
												<?php checked( in_array( (string) $list->id, array_map( 'strval', $subscriber_lists ), true ) ); ?>>
										<?php echo esc_html( $list->name ); ?>
									</label>
								<?php endforeach; ?>
							<?php else : ?>
								<p class="description"><?php esc_html_e( 'No lists created.', 'mail-system-by-katsarov-design' ); ?></p>
							<?php endif; ?>
							<?php
							// Show external lists as info (not selectable).
							$external_lists = array_filter(
								$lists,
								function ( $list_item ) {
									return 'external' === $list_item->source;
								}
							);
							if ( ! empty( $external_lists ) ) :
								?>
								<p class="description" style="margin-top: 10px;">
									<?php esc_html_e( 'Automated lists (membership managed by external plugins):', 'mail-system-by-katsarov-design' ); ?>
									<?php
									$external_names = array_map(
										function ( $list ) {
											return esc_html( $list->name ) . ' (' . esc_html( $list->provider ) . ')';
										},
										$external_lists
									);
									echo esc_html( implode( ', ', $external_names ) );
									?>
								</p>
							<?php endif; ?>
						</td>
					</tr>
				</table>

				<p class="submit">
					<input type="submit" name="<?php echo 'add' === $current_action ? 'mskd_add_subscriber' : 'mskd_edit_subscriber'; ?>" 
							class="button button-primary" 
							value="<?php echo 'add' === $current_action ? esc_attr__( 'Add subscriber', 'mail-system-by-katsarov-design' ) : esc_attr__( 'Save changes', 'mail-system-by-katsarov-design' ); ?>">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-subscribers' ) ); ?>" class="button">
						<?php esc_html_e( 'Cancel', 'mail-system-by-katsarov-design' ); ?>
					</a>
				</p>
			</form>
		</div>

	<?php else : ?>
		<!-- Subscribers List -->
		<?php
		// Pagination.
		$items_per_page = 20;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce not needed for reading pagination.
		$current_page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;

		// Filter by status.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce not needed for reading status filter.
		$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';

		// Get total counts for proper pagination.
		$db_count      = MSKD_List_Provider::get_database_subscriber_count( $status_filter );
		$external_subs = MSKD_List_Provider::get_external_subscribers( array( 'status' => $status_filter ) );
		$ext_count     = count( $external_subs );
		$total_items   = $db_count + $ext_count;
		$total_pages   = ceil( $total_items / $items_per_page );
		$has_external  = $ext_count > 0;

		// Calculate pagination across database + external subscribers.
		$offset          = ( $current_page - 1 ) * $items_per_page;
		$all_subscribers = array();

		if ( $offset < $db_count ) {
			// We need some database subscribers for this page.
			$db_subscribers  = MSKD_List_Provider::get_database_subscribers(
				array(
					'status'   => $status_filter,
					'per_page' => $items_per_page,
					'page'     => $current_page,
				)
			);
			$all_subscribers = $db_subscribers;
			$remaining       = $items_per_page - count( $db_subscribers );

			// If we have room left on this page, add external subscribers.
			if ( $remaining > 0 && $ext_count > 0 ) {
				$all_subscribers = array_merge( $all_subscribers, array_slice( $external_subs, 0, $remaining ) );
			}
		} else {
			// We've passed all database subscribers, show external only.
			$ext_offset = $offset - $db_count;
			if ( $ext_offset < $ext_count ) {
				$all_subscribers = array_slice( $external_subs, $ext_offset, $items_per_page );
			}
		}

		// Get database lists for batch action dropdown.
		// Get list assignments for all subscribers on this page.
		$subscriber_service   = new \MSKD\Services\Subscriber_Service();
		$subscriber_ids       = array_filter( array_map( fn( $s ) => isset( $s->id ) && ! isset( $s->source ) ? (int) $s->id : null, $all_subscribers ) );
		$subscriber_lists_map = ! empty( $subscriber_ids ) ? $subscriber_service->batch_get_lists( $subscriber_ids ) : array();

		$database_lists = array_filter(
			$lists,
			function ( $list_item ) {
				return 'database' === $list_item->source;
			}
		);

		// Calculate stats for the statistics box.
		$total_subs_count    = MSKD_List_Provider::get_total_subscriber_count();
		$active_subs_count   = MSKD_List_Provider::get_total_subscriber_count( 'active' );
		$inactive_subs_count = MSKD_List_Provider::get_total_subscriber_count( 'inactive' );
		$unsub_subs_count    = MSKD_List_Provider::get_total_subscriber_count( 'unsubscribed' );
		?>

		<!-- Statistics Box -->
		<div class="mskd-subscribers-stats">
			<div class="mskd-stat-card">
				<span class="mskd-stat-label"><?php esc_html_e( 'Total Subscribers', 'mail-system-by-katsarov-design' ); ?></span>
				<span class="mskd-stat-value"><?php echo esc_html( $total_subs_count ); ?></span>
			</div>
			<div class="mskd-stat-card">
				<span class="mskd-stat-label"><?php esc_html_e( 'Active', 'mail-system-by-katsarov-design' ); ?></span>
				<span class="mskd-stat-value mskd-text-active"><?php echo esc_html( $active_subs_count ); ?></span>
			</div>
			<div class="mskd-stat-card">
				<span class="mskd-stat-label"><?php esc_html_e( 'Inactive', 'mail-system-by-katsarov-design' ); ?></span>
				<span class="mskd-stat-value mskd-text-inactive"><?php echo esc_html( $inactive_subs_count ); ?></span>
			</div>
			<div class="mskd-stat-card">
				<span class="mskd-stat-label"><?php esc_html_e( 'Unsubscribed', 'mail-system-by-katsarov-design' ); ?></span>
				<span class="mskd-stat-value mskd-text-unsubscribed"><?php echo esc_html( $unsub_subs_count ); ?></span>
			</div>
		</div>

		<!-- Filters -->
		<ul class="subsubsub">
			<li>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-subscribers' ) ); ?>" 
					class="<?php echo empty( $status_filter ) ? 'current' : ''; ?>">
					<?php esc_html_e( 'All', 'mail-system-by-katsarov-design' ); ?>
				</a> |
			</li>
			<li>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-subscribers&status=active' ) ); ?>"
					class="<?php echo 'active' === $status_filter ? 'current' : ''; ?>">
					<?php esc_html_e( 'Active', 'mail-system-by-katsarov-design' ); ?>
				</a> |
			</li>
			<li>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-subscribers&status=inactive' ) ); ?>"
					class="<?php echo 'inactive' === $status_filter ? 'current' : ''; ?>">
					<?php esc_html_e( 'Inactive', 'mail-system-by-katsarov-design' ); ?>
				</a> |
			</li>
			<li>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-subscribers&status=unsubscribed' ) ); ?>"
					class="<?php echo 'unsubscribed' === $status_filter ? 'current' : ''; ?>">
					<?php esc_html_e( 'Unsubscribed', 'mail-system-by-katsarov-design' ); ?>
				</a>
			</li>
		</ul>

		<!-- Bulk Actions -->
		<?php if ( ! empty( $database_lists ) ) : ?>
		<div class="tablenav top mskd-bulk-actions-bar">
			<div class="alignleft actions bulkactions mskd-bulk-actions-row">
				<select name="mskd_bulk_action" id="mskd-bulk-action" class="mskd-bulk-action-select">
					<option value=""><?php esc_html_e( 'Bulk actions', 'mail-system-by-katsarov-design' ); ?></option>
					<option value="assign_lists"><?php esc_html_e( 'Add to lists', 'mail-system-by-katsarov-design' ); ?></option>
					<option value="remove_lists"><?php esc_html_e( 'Remove from lists', 'mail-system-by-katsarov-design' ); ?></option>
					<option value="delete"><?php esc_html_e( 'Delete', 'mail-system-by-katsarov-design' ); ?></option>
				</select>

				<div id="mskd-bulk-list-wrapper" class="mskd-bulk-list-wrapper" style="display: none;">
					<select name="mskd_bulk_list_ids[]" id="mskd-bulk-list-ids" class="mskd-slimselect-bulk-lists" multiple="multiple">
						<?php foreach ( $database_lists as $list ) : ?>
							<option value="<?php echo esc_attr( $list->id ); ?>">
								<?php echo esc_html( $list->name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<button type="button" id="mskd-bulk-apply" class="button action" style="display: none;">
					<?php esc_html_e( 'Apply', 'mail-system-by-katsarov-design' ); ?>
				</button>
				
				<span id="mskd-bulk-result" class="mskd-bulk-result"></span>
			</div>
			<div class="alignleft mskd-selected-count" style="margin-left: 10px; line-height: 30px;">
				<span id="mskd-selected-count">0</span> <?php esc_html_e( 'selected', 'mail-system-by-katsarov-design' ); ?>
			</div>
		</div>
		<?php endif; ?>

		<table class="wp-list-table widefat fixed striped mskd-subscribers-table">
			<thead>
				<tr>
					<?php if ( ! empty( $database_lists ) ) : ?>
					<td id="cb" class="manage-column column-cb check-column">
						<input type="checkbox" id="mskd-select-all" />
					</td>
					<?php endif; ?>
					<th scope="col"><?php esc_html_e( 'Email', 'mail-system-by-katsarov-design' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Status', 'mail-system-by-katsarov-design' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Date', 'mail-system-by-katsarov-design' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Lists', 'mail-system-by-katsarov-design' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Actions', 'mail-system-by-katsarov-design' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! empty( $all_subscribers ) ) : ?>
					<?php foreach ( $all_subscribers as $sub ) : ?>
						<?php
						$is_external = isset( $sub->source ) && 'external' === $sub->source;
						$is_editable = isset( $sub->is_editable ) ? $sub->is_editable : true;
						?>
						<tr<?php echo $is_external ? ' class="mskd-external-list"' : ''; ?>>
							<?php if ( ! empty( $database_lists ) ) : ?>
							<th scope="row" class="check-column">
								<?php if ( $is_editable && ! $is_external ) : ?>
									<input type="checkbox" name="mskd_subscriber_ids[]" class="mskd-subscriber-checkbox" value="<?php echo esc_attr( $sub->id ); ?>" />
								<?php endif; ?>
							</th>
							<?php endif; ?>
							<td>
								<strong><?php echo esc_html( $sub->email ); ?></strong>
								<?php if ( $is_external ) : ?>
									<span class="mskd-badge mskd-badge-external" title="<?php esc_attr_e( 'External subscriber from plugin', 'mail-system-by-katsarov-design' ); ?>">
										<?php esc_html_e( 'External', 'mail-system-by-katsarov-design' ); ?>
									</span>
								<?php endif; ?>
							</td>
							<td>
								<span class="mskd-status mskd-status-<?php echo esc_attr( $sub->status ); ?>">
									<?php
									$statuses = array(
										'active'       => esc_html__( 'Active', 'mail-system-by-katsarov-design' ),
										'inactive'     => esc_html__( 'Inactive', 'mail-system-by-katsarov-design' ),
										'unsubscribed' => esc_html__( 'Unsubscribed', 'mail-system-by-katsarov-design' ),
									);
									echo esc_html( $statuses[ $sub->status ] ?? $sub->status );
									?>
								</span>
							</td>
							<td>
								<?php if ( isset( $sub->created_at ) && $sub->created_at ) : ?>
									<?php echo esc_html( date_i18n( 'd.m.Y', strtotime( $sub->created_at ) ) ); ?>
								<?php else : ?>
									<span class="mskd-readonly-text">—</span>
								<?php endif; ?>
							</td>
							<td class="mskd-lists-column">
								<?php
								$lists_for_sub = $subscriber_lists_map[ $sub->id ] ?? array();
								if ( ! empty( $lists_for_sub ) ) :
									foreach ( $lists_for_sub as $list_item ) :
										?>
										<div class="mskd-list-badge"><?php echo esc_html( $list_item->name ); ?></div>
										<?php
									endforeach;
								else :
									?>
									<span class="mskd-text-muted">—</span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( $is_editable ) : ?>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-subscribers&action=edit&id=' . $sub->id ) ); ?>">
										<?php esc_html_e( 'Edit', 'mail-system-by-katsarov-design' ); ?>
									</a> |
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=mskd-subscribers&action=delete_subscriber&id=' . $sub->id ), 'delete_subscriber_' . $sub->id ) ); ?>" 
										class="mskd-delete-link" style="color: #a00;">
										<?php esc_html_e( 'Delete', 'mail-system-by-katsarov-design' ); ?>
									</a>
								<?php else : ?>
									<span class="mskd-readonly-text" title="<?php esc_attr_e( 'External subscribers cannot be edited', 'mail-system-by-katsarov-design' ); ?>">
										<?php esc_html_e( 'Read-only', 'mail-system-by-katsarov-design' ); ?>
									</span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr>
						<td colspan="<?php echo ! empty( $database_lists ) ? '6' : '5'; ?>"><?php esc_html_e( 'No subscribers found.', 'mail-system-by-katsarov-design' ); ?></td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>

		<!-- Pagination -->
		<?php if ( $total_pages > 1 ) : ?>
			<div class="tablenav bottom">
				<div class="tablenav-pages">
					<?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- paginate_links returns safe HTML.
					echo paginate_links(
						array(
							'base'      => add_query_arg( 'paged', '%#%' ),
							'format'    => '',
							'prev_text' => esc_html__( '&laquo;', 'mail-system-by-katsarov-design' ),
							'next_text' => esc_html__( '&raquo;', 'mail-system-by-katsarov-design' ),
							'total'     => $total_pages,
							'current'   => $current_page,
						)
					);
					?>
				</div>
			</div>
		<?php endif; ?>
		
		<?php if ( $has_external ) : ?>
			<p class="description" style="margin-top: 15px;">
				<span class="dashicons dashicons-info" style="color: #0073aa;"></span>
				<?php esc_html_e( 'External subscribers are managed by third-party plugins and appear as read-only.', 'mail-system-by-katsarov-design' ); ?>
			</p>
		<?php endif; ?>
	<?php endif; ?>
</div>
