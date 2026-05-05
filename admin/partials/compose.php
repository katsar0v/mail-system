<?php
/**
 * Compose email page
 *
 * @package MSKD
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

// Load the List Provider service.
require_once MSKD_PLUGIN_DIR . 'includes/services/class-mskd-list-provider.php';

use MSKD\Services\Template_Service;

// Get all lists (database + external).
$lists = MSKD_List_Provider::get_all_lists();

// Get pre-selected list IDs from URL parameter (supports both single and multiple).
$preselected_list_ids = array();
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce not needed, this is view state from URL, not form submission.
if ( isset( $_GET['list_id'] ) ) {
	$list_id_param = sanitize_text_field( wp_unslash( $_GET['list_id'] ) );
	// Support comma-separated list IDs.
	$raw_ids = array_map( 'trim', explode( ',', $list_id_param ) );
	foreach ( $raw_ids as $raw_id ) {
		// Validate that the list exists (works for both numeric and ext_* IDs).
		$list = MSKD_List_Provider::get_list( $raw_id );
		if ( $list ) {
			$preselected_list_ids[] = $list->id;
		}
	}
}

// Get template if specified.
$selected_template = null;
$prefilled_subject = '';
$prefilled_content = '';
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce not needed, this is view state from URL, not form submission.
if ( isset( $_GET['template_id'] ) ) {
	$template_service = new Template_Service();
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce not needed, this is view state from URL, not form submission.
	$selected_template = $template_service->get_by_id( intval( $_GET['template_id'] ) );
	if ( $selected_template ) {
		$prefilled_subject = $selected_template->subject;
		$prefilled_content = $selected_template->content;
	}
}

// Get minimum datetime for picker (now + 10 minutes, rounded to nearest 10 min).
$wp_timezone     = wp_timezone();
$now             = new DateTime( 'now', $wp_timezone );
$minutes         = (int) $now->format( 'i' );
$rounded_minutes = ceil( ( $minutes + 1 ) / 10 ) * 10;
if ( $rounded_minutes >= 60 ) {
	$now->modify( '+1 hour' );
	$rounded_minutes = 0;
}
$now->setTime( (int) $now->format( 'H' ), $rounded_minutes, 0 );
$min_datetime = $now->format( 'Y-m-d\TH:i' );
?>

<div class="wrap mskd-wrap">
	<h1><?php esc_html_e( 'New campaign', 'mail-system' ); ?></h1>

	<?php settings_errors( 'mskd_messages' ); ?>

	<!-- Editor type selection -->
	<div class="mskd-editor-toggle" style="margin-bottom: 20px; padding: 15px; background: #fff; border: 1px solid #c3c4c7; border-radius: 4px;">
		<p style="margin: 0 0 10px; font-weight: 600;"><?php esc_html_e( 'Choose how to compose your email:', 'mail-system' ); ?></p>
		<div style="display: flex; gap: 15px; flex-wrap: wrap;">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-templates' ) ); ?>" class="button button-secondary" style="display: inline-flex; align-items: center; gap: 8px;">
				<span class="dashicons dashicons-layout" style="margin: 0;"></span>
				<?php esc_html_e( 'Use a Template', 'mail-system' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-visual-editor' ) ); ?>" class="button button-primary" style="display: inline-flex; align-items: center; gap: 8px;">
				<span class="dashicons dashicons-welcome-widgets-menus" style="margin: 0;"></span>
				<?php esc_html_e( 'Open Visual Editor', 'mail-system' ); ?>
			</a>
		</div>
		<p class="description" style="margin-top: 10px;">
			<?php esc_html_e( 'Or use the standard editor below to compose your email with HTML.', 'mail-system' ); ?>
		</p>
	</div>

	<div class="mskd-form-wrap mskd-compose-form">
		<form method="post" action="">
			<?php wp_nonce_field( 'mskd_send_email', 'mskd_nonce' ); ?>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="mskd-lists-select"><?php esc_html_e( 'Send to lists', 'mail-system' ); ?> *</label>
					</th>
					<td>
						<?php if ( ! empty( $lists ) ) : ?>
							<select name="lists[]" id="mskd-lists-select" class="mskd-slimselect-lists" multiple required>
								<?php foreach ( $lists as $list ) : ?>
									<?php
									$subscriber_count = MSKD_List_Provider::get_list_active_subscriber_count( $list );
									$is_external      = 'external' === $list->source;
									$is_preselected   = in_array( $list->id, $preselected_list_ids, true );
									/* translators: %d: number of subscribers in the list */
									$badge = $is_external ? ' [' . __( 'Automated', 'mail-system' ) . ']' : '';
									?>
									<option value="<?php echo esc_attr( $list->id ); ?>" 
											data-subscribers="<?php echo esc_attr( $subscriber_count ); ?>"
											data-external="<?php echo esc_attr( $is_external ? '1' : '0' ); ?>"
											<?php selected( $is_preselected ); ?>>
										<?php
										/* translators: %d: subscriber count */
										echo esc_html( $list->name . $badge . ' (' . sprintf( __( '%d subscribers', 'mail-system' ), $subscriber_count ) . ')' );
										?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description" style="margin-top: 8px;">
								<?php esc_html_e( 'Start typing to search. You can select multiple lists.', 'mail-system' ); ?>
							</p>
						<?php else : ?>
							<p class="description">
								<?php esc_html_e( 'No lists created.', 'mail-system' ); ?>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-lists&action=add' ) ); ?>">
									<?php esc_html_e( 'Create list', 'mail-system' ); ?>
								</a>
							</p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="subject"><?php esc_html_e( 'Subject', 'mail-system' ); ?> *</label>
					</th>
					<td>
						<input type="text" name="subject" id="subject" class="large-text" required value="<?php echo esc_attr( $prefilled_subject ); ?>">
						<?php if ( $selected_template ) : ?>
							<p class="description">
								<?php
								printf(
									/* translators: %s: template name */
									esc_html__( 'Using template: %s', 'mail-system' ),
									'<strong>' . esc_html( $selected_template->name ) . '</strong>'
								);
								?>
							</p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="body"><?php esc_html_e( 'Content', 'mail-system' ); ?> *</label>
					</th>
					<td>
						<?php
						wp_editor(
							$prefilled_content,
							'body',
							array(
								'textarea_name' => 'body',
								'textarea_rows' => 15,
								'media_buttons' => true,
								'teeny'         => false,
								'quicktags'     => true,
							)
						);
						?>
						<p class="description">
							<?php esc_html_e( 'Available placeholders:', 'mail-system' ); ?>
							<code>{first_name}</code>, <code>{last_name}</code>, <code>{email}</code>, <code>{unsubscribe_link}</code>
						</p>
					</td>
				</tr>

				<!-- Custom From Email -->
				<tr>
					<th scope="row">
						<label for="use_custom_from"><?php esc_html_e( 'Sender Email', 'mail-system' ); ?></label>
					</th>
					<td>
						<fieldset>
							<label>
								<input type="radio" name="use_custom_from" value="default" checked>
								<?php esc_html_e( 'Use default sender', 'mail-system' ); ?>
								<span class="description">
									<?php
									$default_from  = get_option( 'mskd_settings', array() );
									$default_email = ! empty( $default_from['from_email'] ) ? $default_from['from_email'] : get_bloginfo( 'admin_email' );
									printf(
									/* translators: %s: default email address */
										esc_html__( '(%s)', 'mail-system' ),
										esc_html( $default_email )
									);
									?>
								</span>
							</label>
							<br>
							<label>
								<input type="radio" name="use_custom_from" value="custom">
								<?php esc_html_e( 'Use custom sender', 'mail-system' ); ?>
							</label>
						</fieldset>
						
						<div id="custom_from_fields" style="display: none; margin-top: 10px;">
							<table class="widefat" style="width: auto;">
								<tr>
									<th style="width: 120px;">
										<label for="from_email"><?php esc_html_e( 'From Email', 'mail-system' ); ?> *</label>
									</th>
									<td>
										<input type="email" name="from_email" id="from_email" class="regular-text"
												placeholder="<?php esc_attr_e( 'sender@example.com', 'mail-system' ); ?>">
										<p class="description">
											<?php esc_html_e( 'Email address that will appear as the sender of this campaign.', 'mail-system' ); ?>
										</p>
									</td>
								</tr>
								<tr>
									<th>
										<label for="from_name"><?php esc_html_e( 'From Name', 'mail-system' ); ?></label>
									</th>
									<td>
										<input type="text" name="from_name" id="from_name" class="regular-text"
												placeholder="<?php esc_attr_e( 'Sender Name', 'mail-system' ); ?>">
										<p class="description">
											<?php esc_html_e( 'Display name for the sender (optional).', 'mail-system' ); ?>
										</p>
									</td>
								</tr>
							</table>
						</div>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="schedule_type"><?php esc_html_e( 'Scheduling', 'mail-system' ); ?></label>
					</th>
					<td>
						<select name="schedule_type" id="schedule_type" class="mskd-schedule-type">
							<option value="now"><?php esc_html_e( 'Send now', 'mail-system' ); ?></option>
							<option value="absolute"><?php esc_html_e( 'Specific date and time', 'mail-system' ); ?></option>
							<option value="relative"><?php esc_html_e( 'After a set time', 'mail-system' ); ?></option>
						</select>
						
						<div class="mskd-schedule-absolute" style="display: none; margin-top: 10px;">
							<input type="datetime-local"
									name="scheduled_datetime"
									id="scheduled_datetime"
									class="mskd-datetime-picker"
									value="<?php echo esc_attr( $min_datetime ); ?>"
									min="<?php echo esc_attr( $min_datetime ); ?>"
									step="600">
							<p class="description">
								<?php
								printf(
									/* translators: %s: timezone string */
									esc_html__( 'Timezone: %s. Select time in 10-minute intervals.', 'mail-system' ),
									'<strong>' . esc_html( wp_timezone_string() ) . '</strong>'
								);
								?>
								<br>
								<?php
								$current_time = new DateTime( 'now', $wp_timezone );
								printf(
									/* translators: %s: current server time in H:i format */
									esc_html__( 'Current server time: %s', 'mail-system' ),
									'<strong>' . esc_html( $current_time->format( 'H:i' ) ) . '</strong>'
								);
								?>
							</p>
						</div>
						
						<div class="mskd-schedule-relative" style="display: none; margin-top: 10px;">
							<input type="number"
									name="delay_value"
									id="delay_value"
									class="small-text"
									value="1"
									min="1"
									max="999">
								<select name="delay_unit" id="delay_unit">
									<option value="minutes"><?php esc_html_e( 'minutes', 'mail-system' ); ?></option>
									<option value="hours" selected><?php esc_html_e( 'hours', 'mail-system' ); ?></option>
									<option value="days"><?php esc_html_e( 'days', 'mail-system' ); ?></option>
								</select>
								<p class="description"><?php esc_html_e( 'Emails will be sent after the specified time.', 'mail-system' ); ?></p>
						</div>
					</td>
				</tr>
			</table>

			<p class="submit">
				<input type="submit" name="mskd_send_email" class="button button-primary button-large mskd-submit-btn" 
						value="<?php esc_attr_e( 'Add to queue', 'mail-system' ); ?>"
						data-send-now="<?php esc_attr_e( 'Add to queue', 'mail-system' ); ?>"
						data-schedule="<?php esc_attr_e( 'Schedule sending', 'mail-system' ); ?>">
			</p>
		</form>
	</div>

<script>
jQuery(document).ready(function($) {
	// Schedule type toggle
	$('#schedule_type').on('change', function() {
		var value = $(this).val();
		$('.mskd-schedule-absolute, .mskd-schedule-relative').hide();
		
		if (value === 'absolute') {
			$('.mskd-schedule-absolute').show();
		} else if (value === 'relative') {
			$('.mskd-schedule-relative').show();
		}
		
		// Update button text
		var $btn = $('.mskd-submit-btn');
		if (value === 'now') {
			$btn.val($btn.data('send-now'));
		} else {
			$btn.val($btn.data('schedule'));
		}
	});

	// Custom from email toggle
	$('input[name="use_custom_from"]').on('change', function() {
		var value = $(this).val();
		if (value === 'custom') {
			$('#custom_from_fields').slideDown();
			$('#from_email').prop('required', true);
		} else {
			$('#custom_from_fields').slideUp();
			$('#from_email').prop('required', false);
			$('#from_email, #from_name').val('');
		}
	});

	// Form validation
	$('form').on('submit', function(e) {
		var useCustom = $('input[name="use_custom_from"]:checked').val();
		if (useCustom === 'custom') {
			var fromEmail = $('#from_email').val().trim();
			if (!fromEmail || !isValidEmail(fromEmail)) {
				e.preventDefault();
				alert('<?php esc_html_e( 'Please enter a valid sender email address.', 'mail-system' ); ?>');
				$('#from_email').focus();
				return false;
			}
		}
	});

	function isValidEmail(email) {
		return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
	}
});
</script>
</div>
