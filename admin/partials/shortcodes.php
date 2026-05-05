<?php
/**
 * Shortcodes page
 *
 * Displays available shortcodes for embedding subscription forms.
 *
 * @package MSKD
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load the List Service to get all lists with error handling.
$lists = array();
try {
	$list_service = new \MSKD\Services\List_Service();
	$lists        = $list_service->get_all();
} catch ( \Exception $e ) {
	// If service fails, show empty list (graceful degradation).
	$lists = array();
}
?>

<div class="wrap mskd-wrap">
	<h1><?php esc_html_e( 'Shortcodes', 'mail-system' ); ?></h1>

	<p class="description">
		<?php esc_html_e( 'Copy any shortcode below and paste it into your page or post to display the subscription form.', 'mail-system' ); ?>
	</p>

	<div class="mskd-shortcodes-wrap">
		<!-- General subscription form -->
		<div class="mskd-card">
			<h2><?php esc_html_e( 'General Subscription Form', 'mail-system' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Subscribe without assigning to a specific list.', 'mail-system' ); ?>
			</p>
			<div class="mskd-shortcode-box">
				<code class="mskd-shortcode-code" id="shortcode-general">[mskd_subscribe_form]</code>
				<button type="button" class="button mskd-copy-btn" data-target="shortcode-general">
					<?php esc_html_e( 'Copy', 'mail-system' ); ?>
				</button>
			</div>
		</div>

		<?php if ( ! empty( $lists ) ) : ?>
			<h2 class="mskd-section-title"><?php esc_html_e( 'List-specific Forms', 'mail-system' ); ?></h2>

			<?php foreach ( $lists as $list ) : ?>
				<?php
				$shortcode    = sprintf(
					'[mskd_subscribe_form list_id="%d" title="%s"]',
					absint( $list->id ),
					esc_attr( $list->name )
				);
				$shortcode_id = 'shortcode-list-' . absint( $list->id );
				?>
				<div class="mskd-card">
					<h3><?php echo esc_html( $list->name ); ?></h3>
					<?php if ( ! empty( $list->description ) ) : ?>
						<p class="description"><?php echo esc_html( $list->description ); ?></p>
					<?php endif; ?>
					<div class="mskd-shortcode-box">
						<code class="mskd-shortcode-code" id="<?php echo esc_attr( $shortcode_id ); ?>"><?php echo esc_html( $shortcode ); ?></code>
						<button type="button" class="button mskd-copy-btn" data-target="<?php echo esc_attr( $shortcode_id ); ?>">
							<?php esc_html_e( 'Copy', 'mail-system' ); ?>
						</button>
					</div>
				</div>
			<?php endforeach; ?>
		<?php else : ?>
			<div class="mskd-card">
				<p>
					<?php
					printf(
						/* translators: %s: URL to create a new list */
						esc_html__( 'No lists available yet. %s to create subscription forms for specific lists.', 'mail-system' ),
						'<a href="' . esc_url( admin_url( 'admin.php?page=mskd-lists&action=add' ) ) . '">' . esc_html__( 'Create a list', 'mail-system' ) . '</a>'
					);
					?>
				</p>
			</div>
		<?php endif; ?>

		<!-- Shortcode attributes reference -->
		<div class="mskd-card mskd-info-card">
			<h2><?php esc_html_e( 'Shortcode Attributes', 'mail-system' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Attribute', 'mail-system' ); ?></th>
						<th><?php esc_html_e( 'Description', 'mail-system' ); ?></th>
						<th><?php esc_html_e( 'Example', 'mail-system' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><code>list_id</code></td>
						<td><?php esc_html_e( 'The ID of the list to subscribe to. If omitted, subscribers are added without a list.', 'mail-system' ); ?></td>
						<td><code>list_id="1"</code></td>
					</tr>
					<tr>
						<td><code>title</code></td>
						<td><?php esc_html_e( 'The title displayed above the form. Defaults to "Subscribe".', 'mail-system' ); ?></td>
						<td><code>title="Join Our Newsletter"</code></td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
</div>
