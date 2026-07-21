<?php
/**
 * API Access page
 *
 * Renders JWT token creation and revocation for the REST API.
 *
 * @package MSKD
 *
 * @var \MSKD\Api\Token_Service $token_service Token service instance.
 * @var array|false             $new_token     One-time token payload to display, if any.
 * @var array<int,object>       $tokens        Existing token records.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use MSKD\Api\Token_Service;

$mskd_scope_labels = array(
	Token_Service::SCOPE_READ  => __( 'Read (list lookup, campaign status)', 'mail-system' ),
	Token_Service::SCOPE_WRITE => __( 'Write (schedule and cancel campaigns)', 'mail-system' ),
);

$mskd_ttl_labels = array(
	30  => __( '30 days', 'mail-system' ),
	90  => __( '90 days', 'mail-system' ),
	365 => __( '365 days', 'mail-system' ),
	0   => __( 'Never expires', 'mail-system' ),
);

$mskd_api_base = esc_url( rest_url( \MSKD\Api\Rest_Controller::NAMESPACE ) );
?>
<div class="wrap mskd-wrap">
	<h1><?php esc_html_e( 'API Access', 'mail-system' ); ?></h1>

	<?php settings_errors( 'mskd_messages' ); ?>

	<p class="description">
		<?php esc_html_e( 'Create JSON Web Tokens (JWT) to authenticate REST API requests, for example to schedule newsletters from an external system.', 'mail-system' ); ?>
	</p>
	<p>
		<?php
		printf(
			/* translators: %s: REST API base URL */
			esc_html__( 'Base URL: %s', 'mail-system' ),
			'<code>' . esc_html( $mskd_api_base ) . '</code>'
		);
		?>
	</p>

	<?php if ( is_array( $new_token ) && ! empty( $new_token['jwt'] ) ) : ?>
		<div class="notice notice-success">
			<p><strong><?php esc_html_e( 'Token created', 'mail-system' ); ?></strong></p>
			<p><?php esc_html_e( 'Copy this token now. For your security it is shown only once and cannot be retrieved later.', 'mail-system' ); ?></p>
			<p>
				<textarea readonly rows="4" class="large-text code" onclick="this.select();"><?php echo esc_textarea( $new_token['jwt'] ); ?></textarea>
			</p>
			<p class="description">
				<?php esc_html_e( 'Send it as an HTTP header:', 'mail-system' ); ?>
				<code>Authorization: Bearer &lt;token&gt;</code>
			</p>
		</div>
	<?php endif; ?>

	<h2><?php esc_html_e( 'Create a new token', 'mail-system' ); ?></h2>
	<form method="post" action="">
		<?php wp_nonce_field( 'mskd_api_create_token', 'mskd_api_nonce' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="mskd-token-name"><?php esc_html_e( 'Token name', 'mail-system' ); ?></label></th>
				<td>
					<input name="token_name" id="mskd-token-name" type="text" class="regular-text" required
						placeholder="<?php esc_attr_e( 'e.g. Newsletter scheduler', 'mail-system' ); ?>" />
					<p class="description"><?php esc_html_e( 'A label to help you recognize this token later.', 'mail-system' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Scopes', 'mail-system' ); ?></th>
				<td>
					<fieldset>
						<?php foreach ( Token_Service::available_scopes() as $mskd_scope ) : ?>
							<label style="display:block;margin-bottom:6px;">
								<input type="checkbox" name="scopes[]" value="<?php echo esc_attr( $mskd_scope ); ?>"
									<?php checked( Token_Service::SCOPE_READ === $mskd_scope ); ?> />
								<code><?php echo esc_html( $mskd_scope ); ?></code>
								&mdash; <?php echo esc_html( $mskd_scope_labels[ $mskd_scope ] ?? '' ); ?>
							</label>
						<?php endforeach; ?>
					</fieldset>
					<p class="description"><?php esc_html_e( 'Write access requires the read scope for the corresponding lookups.', 'mail-system' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="mskd-token-ttl"><?php esc_html_e( 'Expiry', 'mail-system' ); ?></label></th>
				<td>
					<select name="ttl_days" id="mskd-token-ttl">
						<?php foreach ( Token_Service::ttl_choices() as $mskd_ttl ) : ?>
							<option value="<?php echo esc_attr( (string) $mskd_ttl ); ?>" <?php selected( 90, $mskd_ttl ); ?>>
								<?php echo esc_html( $mskd_ttl_labels[ $mskd_ttl ] ?? (string) $mskd_ttl ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
		</table>
		<p class="submit">
			<button type="submit" name="mskd_api_create_token" value="1" class="button button-primary">
				<?php esc_html_e( 'Generate token', 'mail-system' ); ?>
			</button>
		</p>
	</form>

	<h2><?php esc_html_e( 'Active tokens', 'mail-system' ); ?></h2>
	<?php if ( empty( $tokens ) ) : ?>
		<p><?php esc_html_e( 'No API tokens have been created yet.', 'mail-system' ); ?></p>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Name', 'mail-system' ); ?></th>
					<th><?php esc_html_e( 'Scopes', 'mail-system' ); ?></th>
					<th><?php esc_html_e( 'Expires', 'mail-system' ); ?></th>
					<th><?php esc_html_e( 'Created', 'mail-system' ); ?></th>
					<th><?php esc_html_e( 'Last used', 'mail-system' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'mail-system' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $tokens as $mskd_token ) : ?>
					<tr>
						<td><?php echo esc_html( $mskd_token->name ); ?></td>
						<td><code><?php echo esc_html( str_replace( ',', ', ', (string) $mskd_token->scopes ) ); ?></code></td>
						<td><?php echo esc_html( empty( $mskd_token->expires_at ) ? __( 'Never', 'mail-system' ) : $mskd_token->expires_at ); ?></td>
						<td><?php echo esc_html( (string) $mskd_token->created_at ); ?></td>
						<td><?php echo esc_html( empty( $mskd_token->last_used_at ) ? __( 'Never', 'mail-system' ) : $mskd_token->last_used_at ); ?></td>
						<td>
							<form method="post" action="" onsubmit="return confirm('<?php echo esc_js( __( 'Revoke this token? Applications using it will stop working immediately.', 'mail-system' ) ); ?>');">
								<?php wp_nonce_field( 'mskd_api_delete_token', 'mskd_api_nonce' ); ?>
								<input type="hidden" name="token_id" value="<?php echo esc_attr( (string) $mskd_token->id ); ?>" />
								<button type="submit" name="mskd_api_delete_token" value="1" class="button button-link-delete">
									<?php esc_html_e( 'Revoke', 'mail-system' ); ?>
								</button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
