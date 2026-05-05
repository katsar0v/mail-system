<?php
/**
 * Visual Editor Controller
 *
 * Handles visual email editor page and AJAX actions.
 *
 * @package MSKD\Admin
 * @since   1.4.0
 */

namespace MSKD\Admin;

use MSKD\Services\Template_Service;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Admin_Visual_Editor
 *
 * Controller for the visual email editor.
 */
class Admin_Visual_Editor {

	/**
	 * Template service instance.
	 *
	 * @var Template_Service
	 */
	private $template_service;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->template_service = new Template_Service();
	}

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		// AJAX handlers.
		add_action( 'wp_ajax_mskd_save_visual_editor', array( $this, 'ajax_save' ) );
		add_action( 'wp_ajax_mskd_save_campaign_content', array( $this, 'ajax_save_campaign_content' ) );
		add_action( 'wp_ajax_mskd_upload_editor_image', array( $this, 'ajax_upload_image' ) );

		// Intercept visual editor page early to render full-screen.
		add_action( 'admin_init', array( $this, 'maybe_render_fullscreen' ), 1 );
	}

	/**
	 * Check if we're on the visual editor page and render full-screen.
	 *
	 * @return void
	 */
	public function maybe_render_fullscreen(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Page access check only.
		if ( ! isset( $_GET['page'] ) || 'mskd-visual-editor' !== $_GET['page'] ) {
			return;
		}

		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'mail-system' ) );
		}

		// Render the full-screen editor and exit.
		$this->render_fullscreen();
		exit;
	}

	/**
	 * Render the full-screen visual editor.
	 *
	 * @return void
	 */
	private function render_fullscreen(): void {
		// Get template ID from URL if editing existing template.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only template loading.
		$template_id = isset( $_GET['template_id'] ) ? intval( $_GET['template_id'] ) : 0;
		$template    = null;

		if ( $template_id > 0 ) {
			$template = $this->template_service->get_by_id( $template_id );
		}

		// Determine return URL based on context.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only parameter.
		$return_to = isset( $_GET['return_to'] ) ? sanitize_text_field( $_GET['return_to'] ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only parameter.
		$campaign_mode = isset( $_GET['campaign_mode'] ) && '1' === $_GET['campaign_mode'];
		$return_url    = admin_url( 'admin.php?page=mskd-templates' );

		if ( 'compose_wizard' === $return_to ) {
			$return_url = admin_url( 'admin.php?page=mskd-compose&step=3' );
		}

		// Prepare editor configuration.
		$editor_config = array(
			'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
			'nonce'        => wp_create_nonce( 'mskd_visual_editor' ),
			'templateId'   => $template_id,
			'jsonContent'  => $template && $template->json_content ? $template->json_content : '',
			'htmlContent'  => $template ? $template->content : '',
			'subject'      => $template ? $template->subject : '',
			'templateName' => $template ? $template->name : '',
			'returnUrl'    => $return_url,
			'campaignMode' => $campaign_mode,
			'saveAction'   => $campaign_mode ? 'mskd_save_campaign_content' : 'mskd_save_visual_editor',
			'strings'      => array(
				'save'             => $campaign_mode ? __( 'Save & Continue', 'mail-system' ) : __( 'Save', 'mail-system' ),
				'saving'           => __( 'Saving...', 'mail-system' ),
				'saved'            => __( 'Saved!', 'mail-system' ),
				'error'            => __( 'Error saving template', 'mail-system' ),
				'exportHtml'       => __( 'Export HTML', 'mail-system' ),
				'cancel'           => __( 'Back', 'mail-system' ),
				'untitledTemplate' => __( 'Untitled Template', 'mail-system' ),
			),
		);

		// Check if built files exist.
		$js_exists  = file_exists( MSKD_PLUGIN_DIR . 'admin/js/editor/visual-editor.js' );
		$css_exists = file_exists( MSKD_PLUGIN_DIR . 'admin/js/editor/visual-editor.css' );

		if ( ! $js_exists ) {
			wp_die(
				esc_html__( 'Visual Editor Not Available. The visual editor needs to be built. Please run: cd admin/editor && npm install && npm run build', 'mail-system' ),
				esc_html__( 'Visual Editor Error', 'mail-system' )
			);
		}

		// Output the full-screen editor HTML.
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html( $template ? $template->name : __( 'New Template', 'mail-system' ) ); ?> - <?php bloginfo( 'name' ); ?></title>
		<?php if ( $css_exists ) : ?>
	<link rel="stylesheet" href="<?php echo esc_url( MSKD_PLUGIN_URL . 'admin/js/editor/visual-editor.css?ver=' . MSKD_VERSION ); ?>">
	<?php endif; ?>
	<style>
		/* Reset styles for full-screen editor */
		*, *::before, *::after {
			box-sizing: border-box;
		}
		html, body {
			margin: 0;
			padding: 0;
			height: 100%;
			width: 100%;
			overflow: hidden;
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
			font-size: 14px;
			line-height: 1.4;
		}
		#mskd-visual-editor-root {
			height: 100vh;
			width: 100vw;
			position: fixed;
			top: 0;
			left: 0;
			z-index: 999999;
		}
		.mskd-editor-loading {
			display: flex;
			align-items: center;
			justify-content: center;
			height: 100vh;
			background: #f0f0f1;
		}
		.mskd-editor-loading-inner {
			text-align: center;
		}
		.mskd-editor-spinner {
			width: 40px;
			height: 40px;
			border: 4px solid #2271b1;
			border-top-color: transparent;
			border-radius: 50%;
			animation: mskd-spin 1s linear infinite;
			margin: 0 auto 20px;
		}
		@keyframes mskd-spin {
			0% { transform: rotate(0deg); }
			100% { transform: rotate(360deg); }
		}
	</style>
</head>
<body>
	<div id="mskd-visual-editor-root">
		<div class="mskd-editor-loading">
			<div class="mskd-editor-loading-inner">
				<div class="mskd-editor-spinner"></div>
				<p><?php esc_html_e( 'Loading email editor...', 'mail-system' ); ?></p>
			</div>
		</div>
	</div>
	
	<script>
		window.mskdEditorConfig = <?php echo wp_json_encode( $editor_config ); ?>;
	</script>
	<script src="<?php echo esc_url( MSKD_PLUGIN_URL . 'admin/js/editor/visual-editor.js?ver=' . MSKD_VERSION ); ?>"></script>
</body>
</html>
		<?php
	}

	/**
	 * Render placeholder for menu registration (actual render is via maybe_render_fullscreen).
	 *
	 * @return void
	 */
	public function render(): void {
		// This should not be called as maybe_render_fullscreen() intercepts first.
		// But keep as fallback just in case.
		echo '<div class="wrap"><p>';
		esc_html_e( 'Redirecting to Visual Editor...', 'mail-system' );
		echo '</p></div>';
		echo '<script>window.location.href = "' . esc_url( admin_url( 'admin.php?page=mskd-visual-editor' ) ) . '";</script>';
	}

	/**
	 * AJAX handler for saving template from visual editor.
	 *
	 * @return void
	 */
	public function ajax_save(): void {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'mskd_visual_editor' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'mail-system' ) ) );
		}

		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'mail-system' ) ) );
		}

		// Get data.
		$template_id = isset( $_POST['template_id'] ) ? intval( $_POST['template_id'] ) : 0;
		$subject     = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
		// Email HTML content from visual editor must be preserved exactly as generated by MJML.
		// Security is ensured by nonce verification and capability check above.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Admin-only, nonce-verified, machine-generated MJML output.
		$content      = isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : '';
		$json_content = isset( $_POST['json_content'] ) ? sanitize_text_field( wp_unslash( $_POST['json_content'] ) ) : '';

		// Validate JSON content.
		if ( ! empty( $json_content ) ) {
			$decoded = json_decode( $json_content );
			if ( null === $decoded && JSON_ERROR_NONE !== json_last_error() ) {
				wp_send_json_error( array( 'message' => __( 'Invalid JSON content.', 'mail-system' ) ) );
			}
		}

		$data = array(
			'subject'      => $subject,
			'content'      => $content,
			'json_content' => $json_content,
		);

		if ( $template_id > 0 ) {
			// Update existing template.
			$template = $this->template_service->get_by_id( $template_id );
			if ( ! $template ) {
				wp_send_json_error( array( 'message' => __( 'Template not found.', 'mail-system' ) ) );
			}

			$result = $this->template_service->update( $template_id, $data );
			if ( $result ) {
				wp_send_json_success(
					array(
						'message'  => __( 'Template saved successfully.', 'mail-system' ),
						'redirect' => false,
					)
				);
			} else {
				wp_send_json_error( array( 'message' => __( 'Failed to save template.', 'mail-system' ) ) );
			}
		} else {
			// Create new template.
			$data['name']   = $subject ?: __( 'Untitled Template', 'mail-system' );
			$data['type']   = 'custom';
			$data['status'] = 'active';

			$new_id = $this->template_service->create( $data );
			if ( $new_id ) {
				wp_send_json_success(
					array(
						'message'     => __( 'Template created successfully.', 'mail-system' ),
						'template_id' => $new_id,
						'redirect'    => true,
					)
				);
			} else {
				wp_send_json_error( array( 'message' => __( 'Failed to create template.', 'mail-system' ) ) );
			}
		}
	}

	/**
	 * AJAX handler for saving campaign content from visual editor.
	 *
	 * This saves content to the compose wizard session instead of creating a template.
	 *
	 * @return void
	 */
	public function ajax_save_campaign_content(): void {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'mskd_visual_editor' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'mail-system' ) ) );
		}

		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'mail-system' ) ) );
		}

		// Get data.
		$subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
		// Email HTML content from visual editor must be preserved exactly as generated by MJML.
		// Security is ensured by nonce verification and capability check above.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Admin-only, nonce-verified, machine-generated MJML output.
		$content      = isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : '';
		$json_content = isset( $_POST['json_content'] ) ? sanitize_text_field( wp_unslash( $_POST['json_content'] ) ) : '';

		// Validate JSON content.
		if ( ! empty( $json_content ) ) {
			$decoded = json_decode( $json_content );
			if ( null === $decoded && JSON_ERROR_NONE !== json_last_error() ) {
				wp_send_json_error( array( 'message' => __( 'Invalid JSON content.', 'mail-system' ) ) );
			}
		}

		// Get session data.
		$session_key  = 'mskd_compose_wizard_' . get_current_user_id();
		$session_data = get_transient( $session_key );
		if ( ! is_array( $session_data ) ) {
			$session_data = array(
				'template_id'   => 0,
				'use_visual'    => true,
				'subject'       => '',
				'content'       => '',
				'json_content'  => '',
				'lists'         => array(),
				'schedule_type' => 'now',
			);
		}

		// Update session with visual editor content.
		$session_data['subject']      = $subject;
		$session_data['content']      = $content;
		$session_data['json_content'] = $json_content;
		$session_data['use_visual']   = true;

		// Save session.
		set_transient( $session_key, $session_data, HOUR_IN_SECONDS );

		wp_send_json_success(
			array(
				'message'  => __( 'Content saved successfully.', 'mail-system' ),
				'redirect' => true,
			)
		);
	}

	/**
	 * AJAX handler for uploading images in the visual editor.
	 *
	 * @return void
	 */
	public function ajax_upload_image(): void {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'mskd_visual_editor' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'mail-system' ) ) );
		}

		// Check permissions.
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'mail-system' ) ) );
		}

		// Check if file was uploaded.
		if ( ! isset( $_FILES['file'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file uploaded.', 'mail-system' ) ) );
		}

		// Handle the upload using WordPress media functions.
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$attachment_id = media_handle_upload( 'file', 0 );

		if ( is_wp_error( $attachment_id ) ) {
			wp_send_json_error( array( 'message' => $attachment_id->get_error_message() ) );
		}

		$url = wp_get_attachment_url( $attachment_id );

		wp_send_json_success( array( 'url' => $url ) );
	}
}
