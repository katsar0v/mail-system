<?php
/**
 * One-Time Email page
 *
 * @package MSKD
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get preserved form data (if any error occurred).
$form_data          = isset( $form_data ) ? $form_data : array();
$recipient_email    = isset( $form_data['recipient_email'] ) ? esc_attr( $form_data['recipient_email'] ) : '';
$recipient_name     = isset( $form_data['recipient_name'] ) ? esc_attr( $form_data['recipient_name'] ) : '';
$subject_value      = isset( $form_data['subject'] ) ? esc_attr( $form_data['subject'] ) : '';
$body_value         = isset( $form_data['body'] ) ? $form_data['body'] : '';
$schedule_type      = isset( $form_data['schedule_type'] ) ? esc_attr( $form_data['schedule_type'] ) : 'now';
$scheduled_datetime = isset( $form_data['scheduled_datetime'] ) ? esc_attr( $form_data['scheduled_datetime'] ) : '';
$delay_value        = isset( $form_data['delay_value'] ) ? intval( $form_data['delay_value'] ) : 1;
$delay_unit         = isset( $form_data['delay_unit'] ) ? esc_attr( $form_data['delay_unit'] ) : 'hours';

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
$min_datetime     = $now->format( 'Y-m-d\TH:i' );
$default_datetime = $scheduled_datetime ? $scheduled_datetime : $min_datetime;
?>

<div class="wrap mskd-wrap">
	<h1><?php esc_html_e( 'One-time email', 'mail-system' ); ?></h1>

	<?php settings_errors( 'mskd_messages' ); ?>

	<p class="description">
		<?php esc_html_e( 'Send a one-time email directly to a specific recipient. The email can be sent immediately or scheduled for later.', 'mail-system' ); ?>
	</p>

	<div class="mskd-form-wrap mskd-one-time-email-form">
		<form method="post" action="">
			<?php wp_nonce_field( 'mskd_send_one_time_email', 'mskd_nonce' ); ?>

			<div class="mskd-form-fields">
				<div class="mskd-form-row">
					<div class="mskd-form-label">
						<label for="recipient_email"><?php esc_html_e( 'Recipient email', 'mail-system' ); ?> *</label>
					</div>
					<div class="mskd-form-field">
						<input type="email" name="recipient_email" id="recipient_email" class="regular-text" value="<?php echo esc_attr( $recipient_email ); ?>" required>
						<p class="description"><?php esc_html_e( 'The email address to which the message will be sent.', 'mail-system' ); ?></p>
					</div>
				</div>
				<div class="mskd-form-row">
					<div class="mskd-form-label">
						<label for="recipient_name"><?php esc_html_e( 'Recipient name', 'mail-system' ); ?></label>
					</div>
					<div class="mskd-form-field">
						<input type="text" name="recipient_name" id="recipient_name" class="regular-text" value="<?php echo esc_attr( $recipient_name ); ?>">
						<p class="description"><?php esc_html_e( 'Recipient name (optional). Can be used in content with {recipient_name}.', 'mail-system' ); ?></p>
					</div>
				</div>
				<div class="mskd-form-row">
					<div class="mskd-form-label">
						<label for="subject"><?php esc_html_e( 'Subject', 'mail-system' ); ?> *</label>
					</div>
					<div class="mskd-form-field">
						<input type="text" name="subject" id="subject" class="large-text" value="<?php echo esc_attr( $subject_value ); ?>" required>
					</div>
				</div>
				<div class="mskd-form-row">
					<div class="mskd-form-label">
						<label for="body"><?php esc_html_e( 'Content', 'mail-system' ); ?> *</label>
					</div>
					<div class="mskd-form-field">
						<?php
						wp_editor(
							$body_value,
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
							<code>{recipient_name}</code>, <code>{recipient_email}</code>
						</p>
					</div>
				</div>
				<div class="mskd-form-row">
					<div class="mskd-form-label">
						<label for="bcc"><?php esc_html_e( 'Bcc (Optional)', 'mail-system' ); ?></label>
					</div>
					<div class="mskd-form-field">
						<input type="text" name="bcc" id="bcc" class="large-text" value="<?php echo isset( $form_data['bcc'] ) ? esc_attr( $form_data['bcc'] ) : ''; ?>" placeholder="<?php esc_attr_e( 'email1@example.com, email2@example.com', 'mail-system' ); ?>">
						<p class="description">
							<?php esc_html_e( 'Enter one or more email addresses separated by commas to receive a blind carbon copy of this email. Bcc recipients are hidden from the main recipient.', 'mail-system' ); ?>
						</p>
					</div>
				</div>

				<!-- Custom From Email -->
				<div class="mskd-form-row">
					<div class="mskd-form-label">
						<label for="use_custom_from"><?php esc_html_e( 'Sender Email', 'mail-system' ); ?></label>
					</div>
					<div class="mskd-form-field">
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
						
						<div id="custom_from_fields" class="mskd-custom-from-fields" style="display: none;">
							<div class="mskd-nested-form-row">
								<div class="mskd-nested-form-label">
									<label for="from_email"><?php esc_html_e( 'From Email', 'mail-system' ); ?> *</label>
								</div>
								<div class="mskd-nested-form-field">
									<input type="email" name="from_email" id="from_email" class="regular-text"
											placeholder="<?php esc_attr_e( 'sender@example.com', 'mail-system' ); ?>"
											value="<?php echo isset( $form_data['from_email'] ) ? esc_attr( $form_data['from_email'] ) : ''; ?>">
									<p class="description">
										<?php esc_html_e( 'Email address that will appear as the sender of this email.', 'mail-system' ); ?>
									</p>
								</div>
							</div>
							<div class="mskd-nested-form-row">
								<div class="mskd-nested-form-label">
									<label for="from_name"><?php esc_html_e( 'From Name', 'mail-system' ); ?></label>
								</div>
								<div class="mskd-nested-form-field">
									<input type="text" name="from_name" id="from_name" class="regular-text"
											placeholder="<?php esc_attr_e( 'Sender Name', 'mail-system' ); ?>"
											value="<?php echo isset( $form_data['from_name'] ) ? esc_attr( $form_data['from_name'] ) : ''; ?>">
									<p class="description">
										<?php esc_html_e( 'Display name for the sender (optional).', 'mail-system' ); ?>
									</p>
								</div>
							</div>
						</div>
					</div>
				</div>

				<div class="mskd-form-row">
					<div class="mskd-form-label">
						<label for="schedule_type"><?php esc_html_e( 'Scheduling', 'mail-system' ); ?></label>
					</div>
					<div class="mskd-form-field">
						<select name="schedule_type" id="schedule_type" class="mskd-schedule-type">
							<option value="now" <?php selected( $schedule_type, 'now' ); ?>><?php esc_html_e( 'Send now', 'mail-system' ); ?></option>
							<option value="absolute" <?php selected( $schedule_type, 'absolute' ); ?>><?php esc_html_e( 'Specific date and time', 'mail-system' ); ?></option>
							<option value="relative" <?php selected( $schedule_type, 'relative' ); ?>><?php esc_html_e( 'After a set time', 'mail-system' ); ?></option>
						</select>
						
						<div class="mskd-schedule-absolute" style="display: none; margin-top: 10px;">
							<input type="datetime-local"
									name="scheduled_datetime"
									id="scheduled_datetime"
									class="mskd-datetime-picker"
									value="<?php echo esc_attr( $default_datetime ); ?>"
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
									/* translators: %s: current time */
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
									value="<?php echo esc_attr( $delay_value ); ?>"
									min="1"
									max="999">
							<select name="delay_unit" id="delay_unit">
								<option value="minutes" <?php selected( $delay_unit, 'minutes' ); ?>><?php esc_html_e( 'minutes', 'mail-system' ); ?></option>
								<option value="hours" <?php selected( $delay_unit, 'hours' ); ?>><?php esc_html_e( 'hours', 'mail-system' ); ?></option>
								<option value="days" <?php selected( $delay_unit, 'days' ); ?>><?php esc_html_e( 'days', 'mail-system' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'The email will be sent after the specified time.', 'mail-system' ); ?></p>
						</div>
					</div>
				</div>
			</div>

			<p class="submit">
				<input type="submit" name="mskd_send_one_time_email" class="button button-primary button-large mskd-submit-btn" 
						value="<?php esc_attr_e( 'Send now', 'mail-system' ); ?>" 
						data-send-now="<?php esc_attr_e( 'Send now', 'mail-system' ); ?>"
						data-schedule="<?php esc_attr_e( 'Schedule sending', 'mail-system' ); ?>">
			</p>
		</form>
	</div>

	<div class="mskd-info-box">
		<h3><?php esc_html_e( 'Information', 'mail-system' ); ?></h3>
		<ul>
			<li><?php esc_html_e( 'One-time emails can be sent immediately or scheduled for later.', 'mail-system' ); ?></li>
			<li><?php esc_html_e( 'They are not saved as templates and do not repeat.', 'mail-system' ); ?></li>
			<li><?php esc_html_e( 'All sent one-time emails are logged in the audit history.', 'mail-system' ); ?></li>
			<li><?php esc_html_e( 'Scheduled emails can be cancelled from the Queue page.', 'mail-system' ); ?></li>
			<li><?php esc_html_e( 'Suitable for: account activation, notifications, event reminders.', 'mail-system' ); ?></li>
		</ul>
	</div>

<style>
/* Div-based Form Fields - Table-like alignment */
.mskd-one-time-email-form .mskd-form-fields {
	margin-top: 16px;
	display: table;
	width: 100%;
	border-collapse: collapse;
}

.mskd-one-time-email-form .mskd-form-row {
	display: table-row;
}

.mskd-one-time-email-form .mskd-form-row:not(:last-child) .mskd-form-label,
.mskd-one-time-email-form .mskd-form-row:not(:last-child) .mskd-form-field {
	border-bottom: 1px solid #f0f0f1;
}

.mskd-one-time-email-form .mskd-form-label {
	display: table-cell;
	width: 200px;
	padding: 16px 20px 16px 0;
	vertical-align: top;
}

.mskd-one-time-email-form .mskd-form-label label {
	font-weight: 600;
	color: #1d2327;
	font-size: 14px;
}

.mskd-one-time-email-form .mskd-form-field {
	display: table-cell;
	padding: 16px 0;
	vertical-align: top;
}

.mskd-one-time-email-form .mskd-form-field .description {
	margin-top: 8px;
	color: #646970;
	font-size: 13px;
}

/* Custom From Fields (nested div form) */
.mskd-one-time-email-form .mskd-custom-from-fields {
	background: #f8f9fa;
	border: 1px solid #c3c4c7;
	border-radius: 4px;
	padding: 16px;
	margin-top: 12px;
}

.mskd-one-time-email-form .mskd-nested-form-row {
	display: table;
	width: 100%;
	padding: 12px 0;
	border-bottom: 1px solid #e9ecef;
}

.mskd-one-time-email-form .mskd-nested-form-row:first-child {
	padding-top: 0;
}

.mskd-one-time-email-form .mskd-nested-form-row:last-child {
	border-bottom: none;
	padding-bottom: 0;
}

.mskd-one-time-email-form .mskd-nested-form-label {
	display: table-cell;
	width: 120px;
	padding-right: 16px;
	vertical-align: top;
	padding-top: 6px;
}

.mskd-one-time-email-form .mskd-nested-form-label label {
	font-weight: 600;
	color: #1d2327;
	font-size: 13px;
}

.mskd-one-time-email-form .mskd-nested-form-field {
	display: table-cell;
	vertical-align: top;
}

.mskd-one-time-email-form .mskd-nested-form-field .description {
	margin-top: 6px;
	font-style: italic;
	color: #646970;
	font-size: 12px;
}

@media screen and (max-width: 782px) {
	.mskd-one-time-email-form .mskd-form-fields {
		display: block;
	}
	
	.mskd-one-time-email-form .mskd-form-row {
		display: block;
		padding: 16px 0;
		border-bottom: 1px solid #f0f0f1;
	}
	
	.mskd-one-time-email-form .mskd-form-row:last-child {
		border-bottom: none;
	}
	
	.mskd-one-time-email-form .mskd-form-row .mskd-form-label,
	.mskd-one-time-email-form .mskd-form-row .mskd-form-field {
		border-bottom: none !important;
	}
	
	.mskd-one-time-email-form .mskd-form-label {
		display: block;
		width: auto;
		padding: 0 0 8px 0;
	}
	
	.mskd-one-time-email-form .mskd-form-field {
		display: block;
		padding: 0;
	}
	
	.mskd-one-time-email-form .mskd-nested-form-row {
		display: block;
	}
	
	.mskd-one-time-email-form .mskd-nested-form-label {
		display: block;
		width: auto;
		padding: 0 0 6px 0;
	}
	
	.mskd-one-time-email-form .mskd-nested-form-field {
		display: block;
	}
}
</style>

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