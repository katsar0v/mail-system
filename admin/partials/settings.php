<?php
/**
 * Settings page
 *
 * @package MSKD
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings   = get_option( 'mskd_settings', array() );
$from_name  = isset( $settings['from_name'] ) ? $settings['from_name'] : get_bloginfo( 'name' );
$from_email = isset( $settings['from_email'] ) ? $settings['from_email'] : get_bloginfo( 'admin_email' );
$reply_to   = isset( $settings['reply_to'] ) ? $settings['reply_to'] : get_bloginfo( 'admin_email' );

// Sending settings.
$emails_per_minute = isset( $settings['emails_per_minute'] ) ? absint( $settings['emails_per_minute'] ) : MSKD_BATCH_SIZE;

// Email template settings.
$email_header = isset( $settings['email_header'] ) ? $settings['email_header'] : '';
$email_footer = isset( $settings['email_footer'] ) ? $settings['email_footer'] : '';

// SMTP Settings.
$smtp_enabled  = isset( $settings['smtp_enabled'] ) ? (bool) $settings['smtp_enabled'] : false;
$smtp_host     = isset( $settings['smtp_host'] ) ? $settings['smtp_host'] : '';
$smtp_port     = isset( $settings['smtp_port'] ) ? $settings['smtp_port'] : '587';
$smtp_security = isset( $settings['smtp_security'] ) ? $settings['smtp_security'] : 'tls';
$smtp_auth     = isset( $settings['smtp_auth'] ) ? (bool) $settings['smtp_auth'] : true;
$smtp_username = isset( $settings['smtp_username'] ) ? $settings['smtp_username'] : '';
// phpcs:ignore WordPress.PHP.DiscouragedFunctions.base64_decode -- Used for SMTP password obfuscation, not for code obfuscation.
$smtp_password = isset( $settings['smtp_password'] ) ? base64_decode( $settings['smtp_password'] ) : '';

// Styling settings.
$highlight_color   = isset( $settings['highlight_color'] ) ? $settings['highlight_color'] : '#2271b1';
$button_text_color = isset( $settings['button_text_color'] ) ? $settings['button_text_color'] : '#ffffff';

// Subscription Form Settings.
$show_name_field     = isset( $settings['show_name_field'] ) ? (bool) $settings['show_name_field'] : true;
$enable_ga4_tracking = isset( $settings['enable_ga4_tracking'] ) ? (bool) $settings['enable_ga4_tracking'] : false;
?>

<div class="wrap mskd-wrap">
	<h1><?php esc_html_e( 'Settings', 'mail-system-by-katsarov-design' ); ?></h1>

	<?php settings_errors( 'mskd_messages' ); ?>

	<div class="mskd-form-wrap">
		<form method="post" action="">
			<?php wp_nonce_field( 'mskd_save_settings', 'mskd_nonce' ); ?>

			<h2><?php esc_html_e( 'Sender settings', 'mail-system-by-katsarov-design' ); ?></h2>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="from_name"><?php esc_html_e( 'Sender Name', 'mail-system-by-katsarov-design' ); ?></label>
					</th>
					<td>
						<input type="text" name="from_name" id="from_name" class="regular-text"
								value="<?php echo esc_attr( $from_name ); ?>">
						<p class="description"><?php esc_html_e( 'The name that will appear as sender.', 'mail-system-by-katsarov-design' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="from_email"><?php esc_html_e( 'Sender email', 'mail-system-by-katsarov-design' ); ?></label>
					</th>
					<td>
						<input type="email" name="from_email" id="from_email" class="regular-text"
								value="<?php echo esc_attr( $from_email ); ?>">
						<p class="description"><?php esc_html_e( 'The email from which messages will be sent.', 'mail-system-by-katsarov-design' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="reply_to"><?php esc_html_e( 'Reply to', 'mail-system-by-katsarov-design' ); ?></label>
					</th>
					<td>
						<input type="email" name="reply_to" id="reply_to" class="regular-text"
								value="<?php echo esc_attr( $reply_to ); ?>">
						<p class="description"><?php esc_html_e( 'Email for replies from recipients.', 'mail-system-by-katsarov-design' ); ?></p>
					</td>
				</tr>
			</table>

			<hr>

			<h2><?php esc_html_e( 'Sending settings', 'mail-system-by-katsarov-design' ); ?></h2>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="emails_per_minute"><?php esc_html_e( 'Emails per minute', 'mail-system-by-katsarov-design' ); ?></label>
					</th>
					<td>
						<input type="number" name="emails_per_minute" id="emails_per_minute" class="small-text"
								value="<?php echo esc_attr( $emails_per_minute ); ?>"
								min="1" max="1000">
						<p class="description"><?php esc_html_e( 'Maximum number of emails to send per minute. Higher values may exceed your hosting provider limits.', 'mail-system-by-katsarov-design' ); ?></p>
					</td>
				</tr>
			</table>

			<hr>

			<h2><?php esc_html_e( 'Subscription Form', 'mail-system-by-katsarov-design' ); ?></h2>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="show_name_field"><?php esc_html_e( 'Show Name Field', 'mail-system-by-katsarov-design' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="show_name_field" id="show_name_field" value="1"
								<?php checked( $show_name_field ); ?>>
							<?php esc_html_e( 'Display the "Name" field in the subscription form', 'mail-system-by-katsarov-design' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'If unchecked, only the email field will be shown.', 'mail-system-by-katsarov-design' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="enable_ga4_tracking"><?php esc_html_e( 'Enable GA4 Conversion Tracking', 'mail-system-by-katsarov-design' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="enable_ga4_tracking" id="enable_ga4_tracking" value="1"
								<?php checked( $enable_ga4_tracking ); ?>>
							<?php esc_html_e( 'Fire Google Analytics 4 conversion events on successful subscription', 'mail-system-by-katsarov-design' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'When enabled, a "generate_lead" event will be sent to Google Analytics when a user successfully subscribes. Make sure you have GA4 or GTM installed on your site.', 'mail-system-by-katsarov-design' ); ?></p>
					</td>
				</tr>
			</table>

			<hr>

			<h2><?php esc_html_e( 'Email Template Settings', 'mail-system-by-katsarov-design' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Configure custom header and footer that will be added to all outgoing emails. Supports HTML and template variables.', 'mail-system-by-katsarov-design' ); ?></p>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="email_header"><?php esc_html_e( 'Email Header', 'mail-system-by-katsarov-design' ); ?></label>
					</th>
					<td>
						<textarea name="email_header" id="email_header" class="large-text code" rows="6"><?php echo esc_textarea( $email_header ); ?></textarea>
						<p class="description"><?php esc_html_e( 'HTML content to prepend to all emails. Leave empty to disable.', 'mail-system-by-katsarov-design' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="email_footer"><?php esc_html_e( 'Email Footer', 'mail-system-by-katsarov-design' ); ?></label>
					</th>
					<td>
						<textarea name="email_footer" id="email_footer" class="large-text code" rows="6"><?php echo esc_textarea( $email_footer ); ?></textarea>
						<p class="description"><?php esc_html_e( 'HTML content to append to all emails. Leave empty to disable.', 'mail-system-by-katsarov-design' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<?php esc_html_e( 'Available Variables', 'mail-system-by-katsarov-design' ); ?>
					</th>
					<td>
						<p class="description">
							<code>{first_name}</code> - <?php esc_html_e( 'Subscriber first name', 'mail-system-by-katsarov-design' ); ?><br>
							<code>{last_name}</code> - <?php esc_html_e( 'Subscriber last name', 'mail-system-by-katsarov-design' ); ?><br>
							<code>{email}</code> - <?php esc_html_e( 'Subscriber email address', 'mail-system-by-katsarov-design' ); ?><br>
							<code>{unsubscribe_link}</code> - <?php esc_html_e( 'Clickable unsubscribe link', 'mail-system-by-katsarov-design' ); ?><br>
							<code>{unsubscribe_url}</code> - <?php esc_html_e( 'Raw unsubscribe URL', 'mail-system-by-katsarov-design' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<hr>

			<h2><?php esc_html_e( 'Styling', 'mail-system-by-katsarov-design' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Customize the appearance of subscription forms and the unsubscribe page.', 'mail-system-by-katsarov-design' ); ?></p>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="highlight_color"><?php esc_html_e( 'Highlight Color', 'mail-system-by-katsarov-design' ); ?></label>
					</th>
					<td>
						<input type="color" name="highlight_color" id="highlight_color" class="mskd-color-picker"
								value="<?php echo esc_attr( $highlight_color ); ?>">
						<input type="text" id="highlight_color_text" class="small-text mskd-color-text"
								value="<?php echo esc_attr( $highlight_color ); ?>" maxlength="7" pattern="^#[0-9A-Fa-f]{6}$">
						<p class="description"><?php esc_html_e( 'Primary color for buttons, links, and focus states.', 'mail-system-by-katsarov-design' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="button_text_color"><?php esc_html_e( 'Button Text Color', 'mail-system-by-katsarov-design' ); ?></label>
					</th>
					<td>
						<input type="color" name="button_text_color" id="button_text_color" class="mskd-color-picker"
								value="<?php echo esc_attr( $button_text_color ); ?>">
						<input type="text" id="button_text_color_text" class="small-text mskd-color-text"
								value="<?php echo esc_attr( $button_text_color ); ?>" maxlength="7" pattern="^#[0-9A-Fa-f]{6}$">
						<p class="description"><?php esc_html_e( 'Text color for buttons.', 'mail-system-by-katsarov-design' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<?php esc_html_e( 'Preview', 'mail-system-by-katsarov-design' ); ?>
					</th>
					<td>
						<div class="mskd-styling-preview">
							<div class="mskd-preview-box">
								<p class="mskd-preview-label"><?php esc_html_e( 'Button preview:', 'mail-system-by-katsarov-design' ); ?></p>
								<button type="button" id="mskd-preview-button" class="mskd-preview-btn">
									<?php esc_html_e( 'Subscribe', 'mail-system-by-katsarov-design' ); ?>
								</button>
								<p class="mskd-preview-label"><?php esc_html_e( 'Link preview:', 'mail-system-by-katsarov-design' ); ?></p>
								<a href="#" id="mskd-preview-link" class="mskd-preview-link" onclick="return false;">
									<?php esc_html_e( 'Back to site', 'mail-system-by-katsarov-design' ); ?>
								</a>
							</div>
						</div>
					</td>
				</tr>
			</table>

			<hr>

			<h2><?php esc_html_e( 'SMTP Settings', 'mail-system-by-katsarov-design' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Configure an SMTP server for more reliable email sending.', 'mail-system-by-katsarov-design' ); ?></p>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="smtp_enabled"><?php esc_html_e( 'Enable SMTP', 'mail-system-by-katsarov-design' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="smtp_enabled" id="smtp_enabled" value="1"
								<?php checked( $smtp_enabled ); ?>>
							<?php esc_html_e( 'Use SMTP for sending emails', 'mail-system-by-katsarov-design' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'When enabled, emails will be sent via the specified SMTP server instead of wp_mail().', 'mail-system-by-katsarov-design' ); ?></p>
					</td>
				</tr>
				<tr class="mskd-smtp-setting">
					<th scope="row">
						<label for="smtp_host"><?php esc_html_e( 'SMTP Host', 'mail-system-by-katsarov-design' ); ?></label>
					</th>
					<td>
						<input type="text" name="smtp_host" id="smtp_host" class="regular-text"
								value="<?php echo esc_attr( $smtp_host ); ?>" 
								placeholder="smtp.example.com">
						<p class="description"><?php esc_html_e( 'SMTP server address (e.g. smtp.gmail.com, smtp.mailgun.org).', 'mail-system-by-katsarov-design' ); ?></p>
					</td>
				</tr>
				<tr class="mskd-smtp-setting">
					<th scope="row">
						<label for="smtp_port"><?php esc_html_e( 'SMTP Port', 'mail-system-by-katsarov-design' ); ?></label>
					</th>
					<td>
						<input type="number" name="smtp_port" id="smtp_port" class="small-text"
								value="<?php echo esc_attr( $smtp_port ); ?>" 
								min="1" max="65535">
						<p class="description"><?php esc_html_e( 'Standard ports: 25, 465 (SSL), 587 (TLS/StartTLS).', 'mail-system-by-katsarov-design' ); ?></p>
					</td>
				</tr>
				<tr class="mskd-smtp-setting">
					<th scope="row">
						<label for="smtp_security"><?php esc_html_e( 'Encryption', 'mail-system-by-katsarov-design' ); ?></label>
					</th>
					<td>
						<select name="smtp_security" id="smtp_security">
							<option value="" <?php selected( $smtp_security, '' ); ?>><?php esc_html_e( 'No encryption', 'mail-system-by-katsarov-design' ); ?></option>
							<option value="ssl" <?php selected( $smtp_security, 'ssl' ); ?>>SSL</option>
							<option value="tls" <?php selected( $smtp_security, 'tls' ); ?>>TLS (StartTLS)</option>
						</select>
						<p class="description"><?php esc_html_e( 'It is recommended to use TLS or SSL for a secure connection.', 'mail-system-by-katsarov-design' ); ?></p>
					</td>
				</tr>
				<tr class="mskd-smtp-setting">
					<th scope="row">
						<label for="smtp_auth"><?php esc_html_e( 'SMTP Authentication', 'mail-system-by-katsarov-design' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="smtp_auth" id="smtp_auth" value="1"
								<?php checked( $smtp_auth ); ?>>
							<?php esc_html_e( 'Use authentication (username and password)', 'mail-system-by-katsarov-design' ); ?>
						</label>
					</td>
				</tr>
				<tr class="mskd-smtp-setting mskd-smtp-auth-setting">
					<th scope="row">
						<label for="smtp_username"><?php esc_html_e( 'SMTP Username', 'mail-system-by-katsarov-design' ); ?></label>
					</th>
					<td>
						<input type="text" name="smtp_username" id="smtp_username" class="regular-text"
								value="<?php echo esc_attr( $smtp_username ); ?>" 
								autocomplete="off">
						<p class="description"><?php esc_html_e( 'Usually this is your email address or username.', 'mail-system-by-katsarov-design' ); ?></p>
					</td>
				</tr>
				<tr class="mskd-smtp-setting mskd-smtp-auth-setting">
					<th scope="row">
						<label for="smtp_password"><?php esc_html_e( 'SMTP Password', 'mail-system-by-katsarov-design' ); ?></label>
					</th>
					<td>
						<input type="password" name="smtp_password" id="smtp_password" class="regular-text"
								value="<?php echo esc_attr( $smtp_password ); ?>" 
								autocomplete="new-password">
						<p class="description"><?php esc_html_e( 'For Gmail, use App Password instead of the regular password.', 'mail-system-by-katsarov-design' ); ?></p>
					</td>
				</tr>
				<tr class="mskd-smtp-setting">
					<th scope="row">
						<label><?php esc_html_e( 'Connection test', 'mail-system-by-katsarov-design' ); ?></label>
					</th>
					<td>
						<button type="button" id="mskd-smtp-test" class="button button-secondary">
							<?php esc_html_e( 'Send test email', 'mail-system-by-katsarov-design' ); ?>
						</button>
						<span id="mskd-smtp-test-result"></span>
						<p class="description"><?php esc_html_e( 'Sends a test email to the administrator email.', 'mail-system-by-katsarov-design' ); ?></p>
					</td>
				</tr>
			</table>

			<hr>

			<h2><?php esc_html_e( 'System information', 'mail-system-by-katsarov-design' ); ?></h2>

			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Plugin version', 'mail-system-by-katsarov-design' ); ?></th>
					<td><code><?php echo esc_html( MSKD_VERSION ); ?></code></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Database version', 'mail-system-by-katsarov-design' ); ?></th>
					<td>
						<?php
						$current_db_version  = get_option( 'mskd_db_version', '1.0.0' );
						$required_db_version = MSKD_Activator::DB_VERSION;
						$is_db_up_to_date    = version_compare( $current_db_version, $required_db_version, '>=' );
						?>
						<span class="mskd-db-version-status <?php echo $is_db_up_to_date ? 'mskd-db-up-to-date' : 'mskd-db-outdated'; ?>">
							<?php
							if ( $is_db_up_to_date ) {
								echo '<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>';
								echo '<span class="screen-reader-text">' . esc_html__( 'Database is up to date', 'mail-system-by-katsarov-design' ) . '</span>';
							} else {
								echo '<span class="dashicons dashicons-warning" aria-hidden="true"></span>';
								echo '<span class="screen-reader-text">' . esc_html__( 'Database needs update', 'mail-system-by-katsarov-design' ) . '</span>';
							}
							?>
						</span>
						<code><?php echo esc_html( $current_db_version ); ?></code>
						<?php
						if ( ! $is_db_up_to_date ) {
							echo ' / <code>' . esc_html( $required_db_version ) . '</code>';
							echo '<p class="description">' . esc_html__( 'Database schema needs update. Please deactivate and reactivate the plugin.', 'mail-system-by-katsarov-design' ) . '</p>';
						}
						?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Sending method', 'mail-system-by-katsarov-design' ); ?></th>
					<td>
						<?php if ( $smtp_enabled && ! empty( $smtp_host ) ) : ?>
							<span class="mskd-smtp-active"><?php esc_html_e( 'SMTP', 'mail-system-by-katsarov-design' ); ?></span>
							<code><?php echo esc_html( $smtp_host . ':' . $smtp_port ); ?></code>
						<?php else : ?>
							<span class="mskd-smtp-inactive"><?php esc_html_e( 'wp_mail() (default)', 'mail-system-by-katsarov-design' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Sending speed', 'mail-system-by-katsarov-design' ); ?></th>
					<td>
						<code><?php echo esc_html( $emails_per_minute ); ?> <?php esc_html_e( 'emails/minute', 'mail-system-by-katsarov-design' ); ?></code>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'WP-Cron status', 'mail-system-by-katsarov-design' ); ?></th>
					<td>
						<?php if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) : ?>
							<span class="mskd-cron-disabled"><?php esc_html_e( 'Disabled (using system cron)', 'mail-system-by-katsarov-design' ); ?></span>
						<?php else : ?>
							<span class="mskd-cron-enabled"><?php esc_html_e( 'Active (visitor-based)', 'mail-system-by-katsarov-design' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Next run', 'mail-system-by-katsarov-design' ); ?></th>
					<td>
						<?php
						$next_cron = wp_next_scheduled( 'mskd_process_queue' );
						if ( $next_cron ) {
							echo esc_html( date_i18n( 'd.m.Y H:i:s', $next_cron ) );
						} else {
							esc_html_e( 'Not scheduled', 'mail-system-by-katsarov-design' );
						}
						?>
					</td>
				</tr>
			</table>

			<p class="submit">
				<input type="submit" name="mskd_save_settings" class="button button-primary"
						value="<?php esc_attr_e( 'Save settings', 'mail-system-by-katsarov-design' ); ?>">
			</p>
		</form>

		<hr>

		<h2 class="mskd-danger-heading"><?php esc_html_e( 'Danger Zone', 'mail-system-by-katsarov-design' ); ?></h2>
		<p class="description mskd-danger-description"><?php esc_html_e( 'These actions are irreversible. Use with caution.', 'mail-system-by-katsarov-design' ); ?></p>

		<table class="form-table mskd-danger-zone">
			<tr>
				<th scope="row">
					<label><?php esc_html_e( 'Truncate Subscribers', 'mail-system-by-katsarov-design' ); ?></label>
				</th>
				<td>
					<button type="button" id="mskd-truncate-subscribers" class="button mskd-button-danger">
						<?php esc_html_e( 'Delete all subscribers', 'mail-system-by-katsarov-design' ); ?>
						</button>
						<span id="mskd-truncate-subscribers-result"></span>
						<p class="description"><?php esc_html_e( 'Permanently deletes all subscribers and their list associations.', 'mail-system-by-katsarov-design' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label><?php esc_html_e( 'Truncate Lists', 'mail-system-by-katsarov-design' ); ?></label>
				</th>
				<td>
					<button type="button" id="mskd-truncate-lists" class="button mskd-button-danger">
						<?php esc_html_e( 'Delete all lists', 'mail-system-by-katsarov-design' ); ?>
						</button>
						<span id="mskd-truncate-lists-result"></span>
						<p class="description"><?php esc_html_e( 'Permanently deletes all mailing lists and subscriber associations.', 'mail-system-by-katsarov-design' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label><?php esc_html_e( 'Truncate Campaigns', 'mail-system-by-katsarov-design' ); ?></label>
				</th>
				<td>
					<button type="button" id="mskd-truncate-queue" class="button mskd-button-danger">
						<?php esc_html_e( 'Delete all campaigns', 'mail-system-by-katsarov-design' ); ?>
						</button>
						<span id="mskd-truncate-queue-result"></span>
						<p class="description"><?php esc_html_e( 'Permanently deletes all campaigns and their queued emails (pending, processing, sent, and failed).', 'mail-system-by-katsarov-design' ); ?></p>
				</td>
			</tr>
		</table>
	</div>
</div>
