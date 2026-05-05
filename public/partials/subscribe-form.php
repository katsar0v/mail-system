<?php
/**
 * Subscribe form template
 *
 * @package MSKD
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="mskd-subscribe-form-wrap">
	<?php if ( ! empty( $atts['title'] ) ) : ?>
		<h3 class="mskd-form-title"><?php echo esc_html( $atts['title'] ); ?></h3>
	<?php endif; ?>

	<form class="mskd-subscribe-form" method="post">
		<input type="hidden" name="list_id" value="<?php echo esc_attr( $atts['list_id'] ); ?>">
		
		<?php if ( ! isset( $settings['show_name_field'] ) || ! empty( $settings['show_name_field'] ) ) : ?>
		<div class="mskd-form-row">
			<input type="text" name="first_name" 
					placeholder="<?php esc_attr_e( 'Name', 'mail-system' ); ?>" 
					class="mskd-input">
		</div>
		<?php endif; ?>

		<div class="mskd-form-row">
			<input type="email" name="email" required
					placeholder="<?php esc_attr_e( 'Email *', 'mail-system' ); ?>" 
					class="mskd-input">
		</div>

		<div class="mskd-form-row">
			<button type="submit" class="mskd-submit-btn"><?php esc_html_e( 'Subscribe', 'mail-system' ); ?></button>
		</div>

		<div class="mskd-form-message" style="display: none;"></div>
	</form>
</div>
