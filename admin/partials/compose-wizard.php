<?php
/**
 * Compose email wizard - Multi-step email creation
 *
 * Step 1: Choose template or start from scratch
 * Step 2: Edit content in visual editor or HTML editor
 * Step 3: Configure recipients, scheduling, and send
 *
 * @package MSKD
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

// Load required services.
require_once MSKD_PLUGIN_DIR . 'includes/services/class-mskd-list-provider.php';

use MSKD\Services\Template_Service;

// Get all lists (database + external).
$lists = MSKD_List_Provider::get_all_lists();

// Get all templates.
$template_service     = new Template_Service();
$templates            = $template_service->get_all(
	array(
		'orderby' => 'type',
		'order'   => 'ASC',
	)
);
$predefined_templates = array_filter(
	$templates,
	function ( $t ) {
		return 'predefined' === $t->type;
	}
);
$custom_templates     = array_filter(
	$templates,
	function ( $t ) {
		return 'custom' === $t->type;
	}
);

// Get current step from URL or default to 1.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only step parameter.
$current_step = isset( $_GET['step'] ) ? intval( $_GET['step'] ) : 1;
$current_step = max( 1, min( 3, $current_step ) );

// Get session data.
$session_key  = 'mskd_compose_wizard_' . get_current_user_id();
$session_data = get_transient( $session_key );
if ( ! is_array( $session_data ) ) {
	$session_data = array(
		'template_id'   => 0,
		'use_visual'    => false,
		'subject'       => '',
		'content'       => '',
		'json_content'  => '',
		'lists'         => array(),
		'schedule_type' => 'now',
	);
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

	<!-- Wizard Steps Navigation -->
	<div class="mskd-wizard-steps">
		<div class="mskd-wizard-step <?php echo $current_step >= 1 ? 'mskd-wizard-step-active' : ''; ?> <?php echo $current_step > 1 ? 'mskd-wizard-step-completed' : ''; ?>">
			<span class="mskd-wizard-step-number">1</span>
			<span class="mskd-wizard-step-label"><?php esc_html_e( 'Choose Template', 'mail-system' ); ?></span>
		</div>
		<div class="mskd-wizard-step-connector <?php echo $current_step > 1 ? 'mskd-wizard-step-connector-active' : ''; ?>"></div>
		<div class="mskd-wizard-step <?php echo $current_step >= 2 ? 'mskd-wizard-step-active' : ''; ?> <?php echo $current_step > 2 ? 'mskd-wizard-step-completed' : ''; ?>">
			<span class="mskd-wizard-step-number">2</span>
			<span class="mskd-wizard-step-label"><?php esc_html_e( 'Edit Content', 'mail-system' ); ?></span>
		</div>
		<div class="mskd-wizard-step-connector <?php echo $current_step > 2 ? 'mskd-wizard-step-connector-active' : ''; ?>"></div>
		<div class="mskd-wizard-step <?php echo 3 === $current_step ? 'mskd-wizard-step-active' : ''; ?>">
			<span class="mskd-wizard-step-number">3</span>
			<span class="mskd-wizard-step-label"><?php esc_html_e( 'Send', 'mail-system' ); ?></span>
		</div>
	</div>

	<?php if ( 1 === $current_step ) : ?>
		<!-- STEP 1: Choose Template -->
		<div class="mskd-wizard-content">
			<div class="mskd-wizard-card">
				<h2><?php esc_html_e( 'How would you like to create your email?', 'mail-system' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Choose a starting point for your email campaign.', 'mail-system' ); ?></p>

				<form method="post" action="">
					<?php wp_nonce_field( 'mskd_compose_wizard', 'mskd_wizard_nonce' ); ?>
					<input type="hidden" name="template_choice" id="template_choice" value="scratch">
					<input type="hidden" name="template_id" id="selected_template_id" value="0">

					<div class="mskd-template-choices">
						<!-- Start from scratch -->
						<div class="mskd-template-choice" data-choice="scratch">
							<div class="mskd-template-choice-icon">
								<span class="dashicons dashicons-edit"></span>
							</div>
							<div class="mskd-template-choice-content">
								<h3><?php esc_html_e( 'Start from Scratch', 'mail-system' ); ?></h3>
								<p><?php esc_html_e( 'Create your email using the HTML editor with full control over the code.', 'mail-system' ); ?></p>
							</div>
							<span class="mskd-template-choice-check dashicons dashicons-yes-alt"></span>
						</div>

						<!-- Use Visual Editor -->
						<div class="mskd-template-choice" data-choice="visual">
							<div class="mskd-template-choice-icon mskd-template-choice-icon-primary">
								<span class="dashicons dashicons-welcome-widgets-menus"></span>
							</div>
							<div class="mskd-template-choice-content">
								<h3><?php esc_html_e( 'Visual Editor', 'mail-system' ); ?></h3>
								<p><?php esc_html_e( 'Build your email with our drag-and-drop visual editor. No coding required.', 'mail-system' ); ?></p>
							</div>
							<span class="mskd-template-choice-check dashicons dashicons-yes-alt"></span>
						</div>

						<!-- Use a Template -->
						<div class="mskd-template-choice" data-choice="template">
							<div class="mskd-template-choice-icon mskd-template-choice-icon-secondary">
								<span class="dashicons dashicons-layout"></span>
							</div>
							<div class="mskd-template-choice-content">
								<h3><?php esc_html_e( 'Use a Template', 'mail-system' ); ?></h3>
								<p><?php esc_html_e( 'Start with one of your saved templates and customize it.', 'mail-system' ); ?></p>
							</div>
							<span class="mskd-template-choice-check dashicons dashicons-yes-alt"></span>
						</div>
					</div>

					<!-- Template Selection (shown when "Use a Template" is selected) -->
					<div class="mskd-template-selection" style="display: none;">
						<h3><?php esc_html_e( 'Select a Template', 'mail-system' ); ?></h3>
						
						<?php if ( ! empty( $templates ) ) : ?>
							<div class="mskd-template-selection-grid">
								<?php foreach ( $templates as $template ) : ?>
									<div class="mskd-template-select-card" data-template-id="<?php echo esc_attr( $template->id ); ?>">
										<div class="mskd-template-select-preview">
											<?php if ( ! empty( $template->thumbnail ) ) : ?>
												<img src="<?php echo esc_url( $template->thumbnail ); ?>" alt="<?php echo esc_attr( $template->name ); ?>">
											<?php else : ?>
												<div class="mskd-template-placeholder">
													<span class="dashicons dashicons-email-alt"></span>
												</div>
											<?php endif; ?>
										</div>
										<div class="mskd-template-select-info">
											<h4><?php echo esc_html( $template->name ); ?></h4>
											<span class="mskd-template-badge mskd-template-badge-<?php echo esc_attr( $template->type ); ?>">
												<?php echo 'predefined' === $template->type ? esc_html__( 'Predefined', 'mail-system' ) : esc_html__( 'Custom', 'mail-system' ); ?>
											</span>
										</div>
										<span class="mskd-template-select-check dashicons dashicons-yes-alt"></span>
									</div>
								<?php endforeach; ?>
							</div>
						<?php else : ?>
							<p class="mskd-no-templates-msg">
								<?php esc_html_e( 'No templates available.', 'mail-system' ); ?>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-templates&action=add' ) ); ?>"><?php esc_html_e( 'Create your first template', 'mail-system' ); ?></a>
							</p>
						<?php endif; ?>
					</div>

					<div class="mskd-wizard-actions">
						<button type="submit" name="mskd_wizard_step1" class="button button-primary button-hero">
							<?php esc_html_e( 'Continue', 'mail-system' ); ?>
							<span class="dashicons dashicons-arrow-right-alt"></span>
						</button>
					</div>
				</form>
			</div>
		</div>

	<?php elseif ( 2 === $current_step ) : ?>
		<!-- STEP 2: Edit Content -->
		<div class="mskd-wizard-content">
			<?php if ( $session_data['use_visual'] ) : ?>
				<!-- Visual Editor Mode - Redirect to visual editor with return URL -->
				<div class="mskd-wizard-card">
					<h2><?php esc_html_e( 'Edit Your Email', 'mail-system' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Use the visual editor to design your email. Click the button below to open the editor.', 'mail-system' ); ?></p>
					
					<div class="mskd-visual-editor-launch">
						<div class="mskd-visual-editor-preview">
							<span class="dashicons dashicons-welcome-widgets-menus"></span>
							<p><?php esc_html_e( 'The visual editor will open in a new window. When you save your template, you can continue to the next step.', 'mail-system' ); ?></p>
						</div>
						
						<?php
						$editor_url = admin_url( 'admin.php?page=mskd-visual-editor' );
						if ( $session_data['template_id'] > 0 ) {
							$editor_url = add_query_arg( 'template_id', $session_data['template_id'], $editor_url );
						}
						$editor_url = add_query_arg( 'return_to', 'compose_wizard', $editor_url );
						// Add campaign mode flag to prevent saving as template.
						$editor_url = add_query_arg( 'campaign_mode', '1', $editor_url );
						?>
						
						<a href="<?php echo esc_url( $editor_url ); ?>" class="button button-primary button-hero" target="_blank">
							<span class="dashicons dashicons-external"></span>
							<?php esc_html_e( 'Open Visual Editor', 'mail-system' ); ?>
						</a>
					</div>
					
					<div class="mskd-wizard-actions">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-compose&step=1' ) ); ?>" class="button">
							<span class="dashicons dashicons-arrow-left-alt"></span>
							<?php esc_html_e( 'Back', 'mail-system' ); ?>
						</a>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-compose&step=3' ) ); ?>" class="button button-primary button-hero">
							<?php esc_html_e( 'Continue to Send Settings', 'mail-system' ); ?>
							<span class="dashicons dashicons-arrow-right-alt"></span>
						</a>
					</div>
				</div>
			<?php else : ?>
				<!-- HTML Editor Mode -->
				<div class="mskd-wizard-card mskd-wizard-card-wide">
					<h2><?php esc_html_e( 'Edit Your Email Content', 'mail-system' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Write your email content using the editor below.', 'mail-system' ); ?></p>

					<form method="post" action="">
						<?php wp_nonce_field( 'mskd_compose_wizard', 'mskd_wizard_nonce' ); ?>

						<div class="mskd-form-fields">
							<div class="mskd-form-row">
								<div class="mskd-form-label">
									<label for="subject"><?php esc_html_e( 'Subject', 'mail-system' ); ?> *</label>
								</div>
								<div class="mskd-form-field">
									<input type="text" name="subject" id="subject" class="large-text" required
											value="<?php echo esc_attr( $session_data['subject'] ); ?>">
								</div>
							</div>
							<div class="mskd-form-row">
								<div class="mskd-form-label">
									<label for="body"><?php esc_html_e( 'Content', 'mail-system' ); ?> *</label>
								</div>
								<div class="mskd-form-field">
									<?php
									wp_editor(
										$session_data['content'],
										'body',
										array(
											'textarea_name' => 'body',
											'textarea_rows' => 20,
											'media_buttons' => true,
											'teeny'     => false,
											'quicktags' => true,
										)
									);
									?>
									<p class="description">
										<?php esc_html_e( 'Available placeholders:', 'mail-system' ); ?>
										<code>{first_name}</code>, <code>{last_name}</code>, <code>{email}</code>, <code>{unsubscribe_link}</code>
									</p>
								</div>
							</div>
						</div>

						<div class="mskd-wizard-actions">
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-compose&step=1' ) ); ?>" class="button">
								<span class="dashicons dashicons-arrow-left-alt"></span>
								<?php esc_html_e( 'Back', 'mail-system' ); ?>
							</a>
							<button type="submit" name="mskd_wizard_step2" class="button button-primary button-hero">
								<?php esc_html_e( 'Continue', 'mail-system' ); ?>
								<span class="dashicons dashicons-arrow-right-alt"></span>
							</button>
						</div>
					</form>
				</div>
			<?php endif; ?>
		</div>

	<?php elseif ( 3 === $current_step ) : ?>
		<!-- STEP 3: Recipients, Scheduling & Send -->
		<?php
		// Check if we have content to send.
		$has_content = ! empty( $session_data['content'] );
		// Also check json_content for visual editor mode.
		if ( ! $has_content && $session_data['use_visual'] && ! empty( $session_data['json_content'] ) ) {
			$has_content = true;
		}
		// Fallback to template content if using an existing template.
		if ( ! $has_content && $session_data['use_visual'] && $session_data['template_id'] > 0 ) {
			$template    = $template_service->get_by_id( $session_data['template_id'] );
			$has_content = $template && ! empty( $template->content );
		}
		?>
		<div class="mskd-wizard-content">
			<div class="mskd-wizard-card mskd-wizard-card-wide">
				<h2><?php esc_html_e( 'Configure & Send', 'mail-system' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Select recipients, set scheduling options, and send your email.', 'mail-system' ); ?></p>

				<form method="post" action="">
					<?php wp_nonce_field( 'mskd_send_email', 'mskd_nonce' ); ?>
					
					<!-- Pass through content from session -->
					<input type="hidden" name="wizard_mode" value="1">

					<div class="mskd-form-fields">
						<!-- Subject (editable) -->
						<div class="mskd-form-row">
							<div class="mskd-form-label">
								<label for="subject"><?php esc_html_e( 'Subject', 'mail-system' ); ?> *</label>
							</div>
							<div class="mskd-form-field">
								<input type="text" name="subject" id="subject" class="large-text" required
										value="<?php echo esc_attr( $session_data['subject'] ); ?>">
							</div>
						</div>
						
						<!-- Content preview -->
						<div class="mskd-form-row">
							<div class="mskd-form-label">
								<label><?php esc_html_e( 'Content', 'mail-system' ); ?></label>
							</div>
							<div class="mskd-form-field">
								<?php if ( ! empty( $session_data['content'] ) ) : ?>
									<div class="mskd-content-preview">
										<div class="mskd-content-preview-header">
											<span class="dashicons dashicons-visibility"></span>
											<?php esc_html_e( 'Content Preview (with header & footer)', 'mail-system' ); ?>
											<a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-compose&step=2' ) ); ?>" class="mskd-edit-link">
											<span class="dashicons dashicons-edit"></span>
											<?php esc_html_e( 'Edit', 'mail-system' ); ?>
										</a>
									</div>
									<div class="mskd-content-preview-body">
										<iframe
											class="mskd-email-preview-iframe"
											data-content="<?php echo esc_attr( $session_data['content'] ); ?>"
											name="mskd_preview_compose"
											style="width: 100%; height: 300px; border: 1px solid #ddd; border-radius: 4px; background: #fff;"
											sandbox="allow-same-origin"
											title="<?php esc_attr_e( 'Email content preview', 'mail-system' ); ?>"
										></iframe>
									</div>
								</div>
								<input type="hidden" name="body" value="<?php echo esc_attr( $session_data['content'] ); ?>">
								<?php elseif ( $session_data['use_visual'] && $session_data['template_id'] > 0 ) : ?>
									<?php
									$template = $template_service->get_by_id( $session_data['template_id'] );
									if ( $template ) :
										?>
										<div class="mskd-content-preview">
											<div class="mskd-content-preview-header">
												<span class="dashicons dashicons-layout"></span>
												<?php
												/* translators: %s: template name */
												printf( esc_html__( 'Using template: %s', 'mail-system' ), '<strong>' . esc_html( $template->name ) . '</strong>' );
												?>
											</div>
										</div>
										<input type="hidden" name="body" value="<?php echo esc_attr( $template->content ); ?>">
									<?php endif; ?>
								<?php else : ?>
									<p class="description" style="color: #d63638;">
										<?php esc_html_e( 'No content set. Please go back to Step 2 to add content.', 'mail-system' ); ?>
									</p>
								<?php endif; ?>
							</div>
						</div>

						<!-- Recipients -->
						<div class="mskd-form-row">
							<div class="mskd-form-label">
								<label for="mskd-lists-select"><?php esc_html_e( 'Send to lists', 'mail-system' ); ?> *</label>
							</div>
							<div class="mskd-form-field">
								<?php if ( ! empty( $lists ) ) : ?>
									<select name="lists[]" id="mskd-lists-select" class="mskd-slimselect-lists" multiple>
										<?php foreach ( $lists as $list ) : ?>
											<?php
											$subscriber_count = MSKD_List_Provider::get_list_active_subscriber_count( $list );
											$is_external      = 'external' === $list->source;
											$badge            = $is_external ? ' [' . esc_html__( 'Automated', 'mail-system' ) . ']' : '';
											?>
											<option value="<?php echo esc_attr( $list->id ); ?>"
													data-subscribers="<?php echo esc_attr( $subscriber_count ); ?>"
													data-external="<?php echo esc_attr( $is_external ? '1' : '0' ); ?>">
												<?php
												/* translators: %d: subscriber count */
												echo esc_html( $list->name . $badge . ' (' . sprintf( esc_html__( '%d subscribers', 'mail-system' ), $subscriber_count ) . ')' );
												?>
											</option>
										<?php endforeach; ?>
									</select>
									<p class="description" style="margin-top: 8px;">
										<?php esc_html_e( 'Start typing to search. You can select multiple lists.', 'mail-system' ); ?>
									</p>
								<?php else : ?>
									<p class="description" style="color: #d63638;">
										<?php esc_html_e( 'No lists available.', 'mail-system' ); ?>
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-lists&action=add' ) ); ?>">
											<?php esc_html_e( 'Create a list', 'mail-system' ); ?>
										</a>
									</p>
								<?php endif; ?>
							</div>
						</div>

						<!-- Bcc -->
						<div class="mskd-form-row">
							<div class="mskd-form-label">
								<label for="bcc"><?php esc_html_e( 'Bcc (Optional)', 'mail-system' ); ?></label>
							</div>
							<div class="mskd-form-field">
								<input type="text" name="bcc" id="bcc" class="large-text" placeholder="<?php esc_attr_e( 'email1@example.com, email2@example.com', 'mail-system' ); ?>">
								<p class="description">
									<?php esc_html_e( 'Enter one or more email addresses separated by commas to receive a blind carbon copy of this campaign. Bcc recipients are hidden from other recipients.', 'mail-system' ); ?>
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
													placeholder="<?php esc_attr_e( 'sender@example.com', 'mail-system' ); ?>">
											<p class="description">
												<?php esc_html_e( 'Email address that will appear as the sender of this campaign.', 'mail-system' ); ?>
											</p>
										</div>
									</div>
									<div class="mskd-nested-form-row">
										<div class="mskd-nested-form-label">
											<label for="from_name"><?php esc_html_e( 'From Name', 'mail-system' ); ?></label>
										</div>
										<div class="mskd-nested-form-field">
											<input type="text" name="from_name" id="from_name" class="regular-text"
													placeholder="<?php esc_attr_e( 'Sender Name', 'mail-system' ); ?>">
											<p class="description">
												<?php esc_html_e( 'Display name for the sender (optional).', 'mail-system' ); ?>
											</p>
										</div>
									</div>
								</div>
							</div>
						</div>

						<!-- Scheduling -->
						<div class="mskd-form-row">
							<div class="mskd-form-label">
								<label for="schedule_type"><?php esc_html_e( 'Scheduling', 'mail-system' ); ?></label>
							</div>
							<div class="mskd-form-field">
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
							</div>
						</div>
					</div>

					<div class="mskd-wizard-actions">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-compose&step=2' ) ); ?>" class="button">
							<span class="dashicons dashicons-arrow-left-alt"></span>
							<?php esc_html_e( 'Back', 'mail-system' ); ?>
						</a>
						<button type="submit" name="mskd_send_email" class="button button-primary button-hero mskd-submit-btn"
								data-send-now="<?php esc_attr_e( 'Send Now', 'mail-system' ); ?>"
								data-schedule="<?php esc_attr_e( 'Schedule Sending', 'mail-system' ); ?>"
								<?php echo ! $has_content ? 'disabled' : ''; ?>>
							<span class="dashicons dashicons-email"></span>
							<?php esc_html_e( 'Send Now', 'mail-system' ); ?>
						</button>
					</div>
				</form>
			</div>
		</div>
	<?php endif; ?>
</div>

<style>
/* Wizard Steps Navigation */
.mskd-wizard-steps {
	display: flex;
	align-items: center;
	justify-content: center;
	margin: 24px 0 32px;
	padding: 20px;
	background: #fff;
	border: 1px solid #c3c4c7;
	border-radius: 8px;
}

.mskd-wizard-step {
	display: flex;
	align-items: center;
	gap: 10px;
	padding: 10px 16px;
	border-radius: 8px;
	opacity: 0.5;
	transition: all 0.2s ease;
}

.mskd-wizard-step-active {
	opacity: 1;
	background: #f0f6fc;
}

.mskd-wizard-step-completed {
	opacity: 1;
}

.mskd-wizard-step-number {
	display: flex;
	align-items: center;
	justify-content: center;
	width: 32px;
	height: 32px;
	border-radius: 50%;
	background: #c3c4c7;
	color: #fff;
	font-weight: 600;
	font-size: 14px;
}

.mskd-wizard-step-active .mskd-wizard-step-number {
	background: #2271b1;
}

.mskd-wizard-step-completed .mskd-wizard-step-number {
	background: #00a32a;
}

.mskd-wizard-step-label {
	font-size: 14px;
	font-weight: 500;
	color: #50575e;
}

.mskd-wizard-step-active .mskd-wizard-step-label {
	color: #1d2327;
}

.mskd-wizard-step-connector {
	width: 60px;
	height: 2px;
	background: #c3c4c7;
	margin: 0 8px;
}

.mskd-wizard-step-connector-active {
	background: #00a32a;
}

/* Wizard Content */
.mskd-wizard-content {
	max-width: 900px;
	margin: 0 auto;
}

.mskd-wizard-card {
	background: #fff;
	border: 1px solid #c3c4c7;
	border-radius: 8px;
	padding: 32px;
	box-shadow: 0 1px 3px rgba(0,0,0,.04);
}

.mskd-wizard-card-wide {
	max-width: 100%;
}

.mskd-wizard-card h2 {
	margin: 0 0 8px;
	font-size: 20px;
}

.mskd-wizard-card > .description {
	margin-bottom: 24px;
	color: #50575e;
}

/* Template Choices */
.mskd-template-choices {
	display: flex;
	flex-direction: column;
	gap: 12px;
	margin-bottom: 24px;
}

.mskd-template-choice {
	display: flex;
	align-items: center;
	gap: 16px;
	padding: 20px;
	border: 2px solid #c3c4c7;
	border-radius: 8px;
	cursor: pointer;
	transition: all 0.2s ease;
	position: relative;
}

.mskd-template-choice:hover {
	border-color: #2271b1;
	background: #f0f6fc;
}

.mskd-template-choice.selected {
	border-color: #2271b1;
	background: #f0f6fc;
}

.mskd-template-choice-icon {
	display: flex;
	align-items: center;
	justify-content: center;
	width: 48px;
	height: 48px;
	border-radius: 8px;
	background: #f0f0f1;
	flex-shrink: 0;
}

.mskd-template-choice-icon .dashicons {
	font-size: 24px;
	width: 24px;
	height: 24px;
	color: #50575e;
}

.mskd-template-choice-icon-primary {
	background: #2271b1;
}

.mskd-template-choice-icon-primary .dashicons {
	color: #fff;
}

.mskd-template-choice-icon-secondary {
	background: #00a32a;
}

.mskd-template-choice-icon-secondary .dashicons {
	color: #fff;
}

.mskd-template-choice-content {
	flex: 1;
}

.mskd-template-choice-content h3 {
	margin: 0 0 4px;
	font-size: 15px;
}

.mskd-template-choice-content p {
	margin: 0;
	color: #50575e;
	font-size: 13px;
}

.mskd-template-choice-check {
	display: none;
	color: #2271b1;
	font-size: 24px;
}

.mskd-template-choice.selected .mskd-template-choice-check {
	display: block;
}

/* Template Selection Grid */
.mskd-template-selection {
	margin-top: 24px;
	padding-top: 24px;
	border-top: 1px solid #c3c4c7;
}

.mskd-template-selection h3 {
	margin: 0 0 16px;
	font-size: 16px;
}

.mskd-template-selection-grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
	gap: 16px;
}

.mskd-template-select-card {
	border: 2px solid #c3c4c7;
	border-radius: 8px;
	overflow: hidden;
	cursor: pointer;
	transition: all 0.2s ease;
	position: relative;
}

.mskd-template-select-card:hover {
	border-color: #2271b1;
}

.mskd-template-select-card.selected {
	border-color: #2271b1;
	box-shadow: 0 0 0 1px #2271b1;
}

.mskd-template-select-preview {
	height: 120px;
	background: #f8f9fa;
	display: flex;
	align-items: center;
	justify-content: center;
}

.mskd-template-select-preview img {
	width: 100%;
	height: 100%;
	object-fit: cover;
}

.mskd-template-select-info {
	padding: 12px;
}

.mskd-template-select-info h4 {
	margin: 0 0 8px;
	font-size: 13px;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.mskd-template-select-check {
	display: none;
	position: absolute;
	top: 8px;
	right: 8px;
	background: #2271b1;
	color: #fff;
	border-radius: 50%;
	font-size: 20px;
}

.mskd-template-select-card.selected .mskd-template-select-check {
	display: block;
}

/* Wizard Actions */
.mskd-wizard-actions {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-top: 24px;
	padding-top: 24px;
	border-top: 1px solid #c3c4c7;
}

.mskd-wizard-actions .button {
	display: inline-flex;
	align-items: center;
	gap: 6px;
}

.mskd-wizard-actions .button .dashicons {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	font-size: 16px;
	width: 16px;
	height: 16px;
	line-height: 1;
}

.mskd-wizard-actions .button-hero {
	display: inline-flex;
	align-items: center;
	gap: 8px;
}

.mskd-wizard-actions .button.button-hero .dashicons {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	font-size: 18px;
	width: 18px;
	height: 18px;
	line-height: 1;
}

/* Visual Editor Launch */
.mskd-visual-editor-launch {
	text-align: center;
	padding: 40px 20px;
	background: #f8f9fa;
	border-radius: 8px;
	margin-bottom: 24px;
}

.mskd-visual-editor-launch .button-hero {
	display: inline-flex;
	align-items: center;
	gap: 8px;
}

.mskd-visual-editor-launch .button.button-hero .dashicons {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	font-size: 18px;
	width: 18px;
	height: 18px;
	line-height: 1;
}

.mskd-visual-editor-preview {
	margin-bottom: 24px;
}

.mskd-visual-editor-preview .dashicons {
	font-size: 64px;
	width: 64px;
	height: 64px;
	color: #2271b1;
	margin-bottom: 16px;
}

.mskd-visual-editor-preview p {
	max-width: 400px;
	margin: 0 auto;
	color: #50575e;
}

/* Content Preview */
.mskd-content-preview {
	background: #f8f9fa;
	border: 1px solid #c3c4c7;
	border-radius: 4px;
	overflow: hidden;
}

.mskd-content-preview-header {
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 12px 16px;
	background: #e9ecef;
	font-size: 13px;
	font-weight: 500;
}

.mskd-content-preview-header .dashicons {
	font-size: 16px;
	width: 16px;
	height: 16px;
	color: #50575e;
}

.mskd-edit-link {
	margin-left: auto;
	display: inline-flex;
	align-items: center;
	gap: 4px;
	text-decoration: none;
	font-size: 12px;
}

.mskd-content-preview-body {
	padding: 16px;
	font-size: 13px;
	color: #50575e;
	line-height: 1.6;
}

/* Div-based Form Fields */
.mskd-form-fields {
	margin-top: 16px;
}

.mskd-form-row {
	display: flex;
	flex-wrap: wrap;
	padding: 16px 0;
	border-bottom: 1px solid #f0f0f1;
}

.mskd-form-row:last-child {
	border-bottom: none;
}

.mskd-form-label {
	flex: 0 0 200px;
	padding-right: 20px;
	padding-top: 8px;
}

.mskd-form-label label {
	font-weight: 600;
	color: #1d2327;
	font-size: 14px;
}

.mskd-form-field {
	flex: 1;
	min-width: 0;
}

.mskd-form-field .description {
	margin-top: 8px;
	color: #646970;
	font-size: 13px;
}

/* Custom From Fields (nested div form) */
.mskd-custom-from-fields {
	background: #f8f9fa;
	border: 1px solid #c3c4c7;
	border-radius: 4px;
	padding: 16px;
	margin-top: 12px;
}

.mskd-nested-form-row {
	display: flex;
	flex-wrap: wrap;
	padding: 12px 0;
	border-bottom: 1px solid #e9ecef;
}

.mskd-nested-form-row:first-child {
	padding-top: 0;
}

.mskd-nested-form-row:last-child {
	border-bottom: none;
	padding-bottom: 0;
}

.mskd-nested-form-label {
	flex: 0 0 120px;
	padding-right: 16px;
	padding-top: 6px;
}

.mskd-nested-form-label label {
	font-weight: 600;
	color: #1d2327;
	font-size: 13px;
}

.mskd-nested-form-field {
	flex: 1;
	min-width: 0;
}

.mskd-nested-form-field .description {
	margin-top: 6px;
	font-style: italic;
	color: #646970;
	font-size: 12px;
}

@media screen and (max-width: 782px) {
	.mskd-form-row {
		flex-direction: column;
	}
	
	.mskd-form-label {
		flex: none;
		padding-right: 0;
		padding-bottom: 8px;
	}
	
	.mskd-nested-form-row {
		flex-direction: column;
	}
	
	.mskd-nested-form-label {
		flex: none;
		padding-right: 0;
		padding-bottom: 6px;
	}
	.mskd-wizard-steps {
		flex-wrap: wrap;
		gap: 12px;
	}
	
	.mskd-wizard-step-connector {
		display: none;
	}
	
	.mskd-template-selection-grid {
		grid-template-columns: 1fr;
	}
	
	.mskd-wizard-actions {
		flex-direction: column;
		gap: 12px;
	}
	
	.mskd-wizard-actions .button {
		width: 100%;
		text-align: center;
		justify-content: center;
	}
}
</style>

<script>
jQuery(document).ready(function($) {
	// Template choice selection
	$('.mskd-template-choice').on('click', function() {
		$('.mskd-template-choice').removeClass('selected');
		$(this).addClass('selected');
		$('#template_choice').val($(this).data('choice'));
		
		// Show/hide template selection
		if ($(this).data('choice') === 'template') {
			$('.mskd-template-selection').slideDown();
		} else {
			$('.mskd-template-selection').slideUp();
			$('#selected_template_id').val('0');
			$('.mskd-template-select-card').removeClass('selected');
		}
	});
	
	// Template card selection
	$('.mskd-template-select-card').on('click', function() {
		$('.mskd-template-select-card').removeClass('selected');
		$(this).addClass('selected');
		$('#selected_template_id').val($(this).data('template-id'));
	});
	
	// Select first choice by default
	$('.mskd-template-choice').first().trigger('click');
	
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
			$btn.html('<span class="dashicons dashicons-email"></span> ' + $btn.data('send-now'));
		} else {
			$btn.html('<span class="dashicons dashicons-calendar-alt"></span> ' + $btn.data('schedule'));
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
		// Only validate recipients on step 3 (where the list select exists)
		var $listsSelect = $('#mskd-lists-select');
		if ($listsSelect.length > 0) {
			var lists = $listsSelect.val();
			if (!lists || lists.length === 0) {
				e.preventDefault();
				alert('<?php echo esc_js( __( 'Please select at least one list.', 'mail-system' ) ); ?>');
				return false;
			}
		}

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
