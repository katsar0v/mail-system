<?php
/**
 * Lists page
 *
 * @package MSKD
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

// Load the List Provider service.
require_once MSKD_PLUGIN_DIR . 'includes/services/class-mskd-list-provider.php';

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified in form handler, this just determines view state.
$current_action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list';
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified in form handler, this just determines view state.
$list_id = isset( $_GET['id'] ) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : '';

// Get list for editing (only database lists are editable).
$list = null;
if ( 'edit' === $current_action && $list_id ) {
	// Check if this is an external list (not editable).
	if ( 0 === strpos( $list_id, 'ext_' ) ) {
		wp_safe_redirect( admin_url( 'admin.php?page=mskd-lists' ) );
		exit;
	}
	$list = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}mskd_lists WHERE id = %d",
			intval( $list_id )
		)
	);
}
?>

<div class="wrap mskd-wrap">
	<h1>
		<?php esc_html_e( 'Lists', 'mail-system' ); ?>
		<?php if ( 'list' === $current_action ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-lists&action=add' ) ); ?>" class="page-title-action">
				<?php esc_html_e( 'Add new', 'mail-system' ); ?>
			</a>
		<?php endif; ?>
	</h1>

	<?php settings_errors( 'mskd_messages' ); ?>

	<?php if ( 'add' === $current_action || 'edit' === $current_action ) : ?>
		<!-- Add/Edit Form -->
		<div class="mskd-form-wrap">
			<h2><?php echo 'add' === $current_action ? esc_html__( 'Add list', 'mail-system' ) : esc_html__( 'Edit list', 'mail-system' ); ?></h2>

			<form method="post" action="">
				<?php wp_nonce_field( 'add' === $current_action ? 'mskd_add_list' : 'mskd_edit_list', 'mskd_nonce' ); ?>

				<?php if ( 'edit' === $current_action ) : ?>
					<input type="hidden" name="list_id" value="<?php echo esc_attr( $list_id ); ?>">
				<?php endif; ?>

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="name"><?php esc_html_e( 'List name', 'mail-system' ); ?> *</label>
						</th>
						<td>
							<input type="text" name="name" id="name" class="regular-text" required
								value="<?php echo $list ? esc_attr( $list->name ) : ''; ?>">
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="description"><?php esc_html_e( 'Description', 'mail-system' ); ?></label>
						</th>
						<td>
							<textarea name="description" id="description" class="large-text" rows="4"><?php echo $list ? esc_textarea( $list->description ) : ''; ?></textarea>
						</td>
					</tr>
				</table>

				<p class="submit">
					<input type="submit" name="<?php echo 'add' === $current_action ? 'mskd_add_list' : 'mskd_edit_list'; ?>"
						class="button button-primary"
						value="<?php echo 'add' === $current_action ? esc_attr__( 'Add list', 'mail-system' ) : esc_attr__( 'Save changes', 'mail-system' ); ?>">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-lists' ) ); ?>" class="button">
						<?php esc_html_e( 'Cancel', 'mail-system' ); ?>
					</a>
				</p>
			</form>
		</div>

	<?php else : ?>
		<!-- Lists Table -->
		<?php
		// Get all lists (database + external).
		$all_lists = MSKD_List_Provider::get_all_lists();
		?>

		<table class="wp-list-table widefat fixed striped mskd-lists-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Name', 'mail-system' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Subscribers', 'mail-system' ); ?></th>
					<th scope="col" class="mskd-shortcode-col"><?php esc_html_e( 'Shortcode', 'mail-system' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Actions', 'mail-system' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! empty( $all_lists ) ) : ?>
					<?php foreach ( $all_lists as $item ) : ?>
						<?php
						$subscriber_count = MSKD_List_Provider::get_list_subscriber_count( $item );
						$is_external      = 'external' === $item->source;

						// Get subscribers for tooltip (limit to 11 to check if there are more).
						$subscribers_for_tooltip = array();
						if ( $subscriber_count > 0 ) {
							$subscribers_for_tooltip = MSKD_List_Provider::get_list_subscribers_full( $item, 11 );
						}
						$show_more           = count( $subscribers_for_tooltip ) > 10;
						$subscribers_display = array_slice( $subscribers_for_tooltip, 0, 10 );
						?>
						<tr<?php echo $is_external ? ' class="mskd-external-list"' : ''; ?>>
							<td>
								<?php
								$max_name_length = 35;
								$display_name    = $item->name;
								$name_truncated  = false;
								if ( mb_strlen( $display_name ) > $max_name_length ) {
									$display_name   = mb_substr( $display_name, 0, $max_name_length ) . '...';
									$name_truncated = true;
								}
								?>
								<strong<?php echo $name_truncated ? ' title="' . esc_attr( $item->name ) . '"' : ''; ?>><?php echo esc_html( $display_name ); ?></strong>
								<?php if ( $is_external ) : ?>
									<span class="mskd-badge mskd-badge-external" title="<?php esc_attr_e( 'Automated list from external plugin', 'mail-system' ); ?>">
										<?php esc_html_e( 'Automated', 'mail-system' ); ?>
									</span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( $subscriber_count > 0 ) : ?>
									<span class="mskd-subscriber-count">
										<?php echo esc_html( $subscriber_count ); ?>
										<div class="mskd-subscriber-tooltip">
											<div class="mskd-subscriber-tooltip__title">
												<?php esc_html_e( 'Subscribers', 'mail-system' ); ?>
											</div>
											<ul class="mskd-subscriber-tooltip__list">
												<?php foreach ( $subscribers_display as $sub ) : ?>
													<li class="mskd-subscriber-tooltip__item">
														<span class="mskd-subscriber-tooltip__email"><?php echo esc_html( $sub->email ); ?></span>
														<?php
														$name = trim( ( $sub->first_name ?? '' ) . ' ' . ( $sub->last_name ?? '' ) );
														if ( $name ) :
															?>
															<span class="mskd-subscriber-tooltip__name"><?php echo esc_html( $name ); ?></span>
														<?php endif; ?>
													</li>
												<?php endforeach; ?>
											</ul>
											<?php if ( $show_more ) : ?>
												<div class="mskd-subscriber-tooltip__more">
													<?php
													printf(
														/* translators: %d: number of additional subscribers */
														esc_html__( '... and %d more', 'mail-system' ),
														esc_html( $subscriber_count - 10 )
													);
													?>
												</div>
											<?php endif; ?>
										</div>
									</span>
								<?php else : ?>
									<span class="mskd-subscriber-count-empty">0</span>
								<?php endif; ?>
							</td>
							<td class="mskd-shortcode-col">
								<?php if ( ! $is_external ) : ?>
									<?php
									$shortcode    = sprintf( '[mskd_subscribe_form list_id="%d"]', absint( $item->id ) );
									$shortcode_id = 'shortcode-list-' . absint( $item->id );
									?>
									<div class="mskd-shortcode-inline">
										<code class="mskd-shortcode-code" id="<?php echo esc_attr( $shortcode_id ); ?>"><?php echo esc_html( $shortcode ); ?></code>
										<button type="button" class="mskd-copy-btn mskd-copy-icon-btn" data-target="<?php echo esc_attr( $shortcode_id ); ?>" title="<?php esc_attr_e( 'Copy shortcode', 'mail-system' ); ?>">
											<span class="dashicons dashicons-clipboard"></span>
										</button>
									</div>
								<?php else : ?>
									<span class="mskd-shortcode-na" title="<?php esc_attr_e( 'Automated lists do not have subscription forms', 'mail-system' ); ?>">—</span>
								<?php endif; ?>
							</td>
							<td>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-compose&list_id=' . rawurlencode( $item->id ) ) ); ?>">
									<?php esc_html_e( 'Send email', 'mail-system' ); ?>
								</a>
								<?php if ( $item->is_editable ) : ?>
									| <a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-lists&action=edit&id=' . $item->id ) ); ?>">
										<?php esc_html_e( 'Edit', 'mail-system' ); ?>
									</a> |
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=mskd-lists&action=delete_list&id=' . $item->id ), 'delete_list_' . $item->id ) ); ?>"
										class="mskd-delete-link" style="color: #a00;">
										<?php esc_html_e( 'Delete', 'mail-system' ); ?>
									</a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr>
						<td colspan="4"><?php esc_html_e( 'No lists created.', 'mail-system' ); ?></td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
