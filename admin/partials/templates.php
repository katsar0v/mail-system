<?php
/**
 * Templates page
 *
 * @package MSKD
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use MSKD\Services\Template_Service;

$template_service = new Template_Service();

// Get action from URL.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified in form handler, this just determines view state.
$current_action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list';

// Get template for edit.
$editing_template = null;
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified in form handler, this just determines view state.
if ( 'edit' === $current_action && isset( $_GET['id'] ) ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified in form handler, this just determines view state.
	$editing_template = $template_service->get_by_id( intval( $_GET['id'] ) );
}

// Get all templates.
$templates = $template_service->get_all(
	array(
		'orderby' => 'type',
		'order'   => 'ASC',
	)
);

// Separate predefined and custom templates.
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
?>

<div class="wrap mskd-wrap">
	<h1>
		<?php esc_html_e( 'Email Templates', 'mail-system' ); ?>
		<?php if ( 'list' === $current_action ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-templates&action=add' ) ); ?>" class="page-title-action">
				<?php esc_html_e( 'Add New', 'mail-system' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-visual-editor' ) ); ?>" class="page-title-action" style="background: #2271b1; color: #fff; border-color: #2271b1;">
				<span class="dashicons dashicons-welcome-widgets-menus" style="vertical-align: middle; font-size: 16px; line-height: 1; margin-right: 4px;"></span>
				<?php esc_html_e( 'Visual Editor', 'mail-system' ); ?>
			</a>
		<?php endif; ?>
	</h1>

	<?php settings_errors( 'mskd_messages' ); ?>

	<?php if ( 'add' === $current_action || 'edit' === $current_action ) : ?>
		<!-- Add/Edit Form -->
		<div class="mskd-form-wrap mskd-template-form">
			<h2>
				<?php
				if ( 'add' === $current_action ) {
					esc_html_e( 'Add new template', 'mail-system' );
				} else {
					esc_html_e( 'Edit template', 'mail-system' );
				}
				?>
			</h2>

			<form method="post" action="">
				<?php
				$nonce_action = 'add' === $current_action ? 'mskd_add_template' : 'mskd_edit_template';
				wp_nonce_field( $nonce_action, 'mskd_nonce' );
				?>
				
				<?php if ( $editing_template ) : ?>
					<input type="hidden" name="template_id" value="<?php echo esc_attr( $editing_template->id ); ?>">
				<?php endif; ?>

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="name"><?php esc_html_e( 'Template name', 'mail-system' ); ?> *</label>
						</th>
						<td>
							<input type="text" name="name" id="name" class="regular-text" required
								value="<?php echo $editing_template ? esc_attr( $editing_template->name ) : ''; ?>">
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="subject"><?php esc_html_e( 'Default subject', 'mail-system' ); ?></label>
						</th>
						<td>
							<input type="text" name="subject" id="subject" class="large-text"
								value="<?php echo $editing_template ? esc_attr( $editing_template->subject ) : ''; ?>">
							<p class="description">
								<?php esc_html_e( 'Optional. This subject will be pre-filled when using this template.', 'mail-system' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="content"><?php esc_html_e( 'Content', 'mail-system' ); ?></label>
						</th>
						<td>
							<?php
							$content = $editing_template ? $editing_template->content : '';
							wp_editor(
								$content,
								'content',
								array(
									'textarea_name' => 'content',
									'textarea_rows' => 20,
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
				</table>

				<p class="submit">
					<?php if ( 'add' === $current_action ) : ?>
						<input type="submit" name="mskd_add_template" class="button button-primary" value="<?php esc_attr_e( 'Add template', 'mail-system' ); ?>">
					<?php else : ?>
						<input type="submit" name="mskd_edit_template" class="button button-primary" value="<?php esc_attr_e( 'Save changes', 'mail-system' ); ?>">
					<?php endif; ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-templates' ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'mail-system' ); ?></a>
				</p>
			</form>
		</div>

	<?php else : ?>
		<!-- Templates List -->
		
		<?php if ( ! empty( $predefined_templates ) ) : ?>
			<div class="mskd-templates-section">
				<h2><?php esc_html_e( 'Predefined Templates', 'mail-system' ); ?></h2>
				<p class="description"><?php esc_html_e( 'These templates are provided by the system. You can duplicate them to create custom versions.', 'mail-system' ); ?></p>
				
				<div class="mskd-templates-grid">
					<?php foreach ( $predefined_templates as $template ) : ?>
						<div class="mskd-template-card mskd-template-predefined">
							<div class="mskd-template-preview">
								<?php if ( ! empty( $template->thumbnail ) ) : ?>
									<img src="<?php echo esc_url( $template->thumbnail ); ?>" alt="<?php echo esc_attr( $template->name ); ?>">
								<?php else : ?>
									<div class="mskd-template-placeholder">
										<span class="dashicons dashicons-email-alt"></span>
									</div>
								<?php endif; ?>
							</div>
							<div class="mskd-template-info">
								<h3><?php echo esc_html( $template->name ); ?></h3>
								<span class="mskd-template-badge mskd-template-badge-predefined"><?php esc_html_e( 'Predefined', 'mail-system' ); ?></span>
							</div>
							<div class="mskd-template-actions">
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=mskd-templates&action=duplicate_template&id=' . $template->id ), 'duplicate_template_' . $template->id ) ); ?>" class="button button-secondary" title="<?php esc_attr_e( 'Duplicate', 'mail-system' ); ?>">
									<span class="dashicons dashicons-admin-page"></span>
									<?php esc_html_e( 'Duplicate', 'mail-system' ); ?>
								</a>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-compose&template_id=' . $template->id ) ); ?>" class="button button-primary" title="<?php esc_attr_e( 'Use template', 'mail-system' ); ?>">
									<?php esc_html_e( 'Use', 'mail-system' ); ?>
								</a>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>

		<div class="mskd-templates-section">
			<h2><?php esc_html_e( 'Custom Templates', 'mail-system' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Templates you have created. You can edit, duplicate, or delete these.', 'mail-system' ); ?></p>
			
			<?php if ( ! empty( $custom_templates ) ) : ?>
				<div class="mskd-templates-grid">
					<?php foreach ( $custom_templates as $template ) : ?>
						<div class="mskd-template-card mskd-template-custom">
							<div class="mskd-template-preview">
								<?php if ( ! empty( $template->thumbnail ) ) : ?>
									<img src="<?php echo esc_url( $template->thumbnail ); ?>" alt="<?php echo esc_attr( $template->name ); ?>">
								<?php else : ?>
									<div class="mskd-template-placeholder">
										<span class="dashicons dashicons-email-alt"></span>
									</div>
								<?php endif; ?>
							</div>
							<div class="mskd-template-info">
								<h3><?php echo esc_html( $template->name ); ?></h3>
								<span class="mskd-template-badge mskd-template-badge-custom"><?php esc_html_e( 'Custom', 'mail-system' ); ?></span>
								<span class="mskd-template-date">
									<?php
									/* translators: %s: date */
									printf( esc_html__( 'Created: %s', 'mail-system' ), esc_html( wp_date( get_option( 'date_format' ), strtotime( $template->created_at ) ) ) );
									?>
								</span>
							</div>
							<div class="mskd-template-actions">
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-visual-editor&template_id=' . $template->id ) ); ?>" class="button button-secondary" title="<?php esc_attr_e( 'Edit with Visual Editor', 'mail-system' ); ?>" style="background: #2271b1; color: #fff; border-color: #2271b1;">
									<span class="dashicons dashicons-welcome-widgets-menus"></span>
								</a>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-templates&action=edit&id=' . $template->id ) ); ?>" class="button button-secondary" title="<?php esc_attr_e( 'Edit HTML', 'mail-system' ); ?>">
									<span class="dashicons dashicons-editor-code"></span>
								</a>
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=mskd-templates&action=duplicate_template&id=' . $template->id ), 'duplicate_template_' . $template->id ) ); ?>" class="button button-secondary" title="<?php esc_attr_e( 'Duplicate', 'mail-system' ); ?>">
									<span class="dashicons dashicons-admin-page"></span>
								</a>
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=mskd-templates&action=delete_template&id=' . $template->id ), 'delete_template_' . $template->id ) ); ?>" class="button button-secondary mskd-delete-link" title="<?php esc_attr_e( 'Delete', 'mail-system' ); ?>">
									<span class="dashicons dashicons-trash"></span>
								</a>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-compose&template_id=' . $template->id ) ); ?>" class="button button-primary" title="<?php esc_attr_e( 'Use template', 'mail-system' ); ?>">
									<?php esc_html_e( 'Use', 'mail-system' ); ?>
								</a>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<p class="mskd-no-templates">
					<?php esc_html_e( 'No custom templates yet.', 'mail-system' ); ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-templates&action=add' ) ); ?>"><?php esc_html_e( 'Create your first template', 'mail-system' ); ?></a>
				</p>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
