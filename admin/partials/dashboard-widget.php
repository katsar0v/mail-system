<?php
/**
 * Dashboard widget template
 *
 * Displays queue statistics in the WordPress dashboard.
 *
 * @package MSKD
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="mskd-dashboard-widget">
	<div class="mskd-widget-stats">
		<div class="mskd-widget-stat mskd-widget-stat-pending">
			<span class="mskd-widget-stat-number"><?php echo esc_html( $pending ); ?></span>
			<span class="mskd-widget-stat-label"><?php esc_html_e( 'Pending', 'mail-system' ); ?></span>
		</div>
		<div class="mskd-widget-stat mskd-widget-stat-sent">
			<span class="mskd-widget-stat-number"><?php echo esc_html( $sent ); ?></span>
			<span class="mskd-widget-stat-label"><?php esc_html_e( 'Sent', 'mail-system' ); ?></span>
		</div>
		<div class="mskd-widget-stat mskd-widget-stat-failed">
			<span class="mskd-widget-stat-number"><?php echo esc_html( $failed ); ?></span>
			<span class="mskd-widget-stat-label"><?php esc_html_e( 'Failed', 'mail-system' ); ?></span>
		</div>
	</div>

	<div class="mskd-widget-cron">
		<?php if ( $last_cron_run ) : ?>
			<p>
				<strong><?php esc_html_e( 'Last cron run:', 'mail-system' ); ?></strong>
				<?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_cron_run ) ); ?>
			</p>
		<?php else : ?>
			<p class="mskd-widget-cron-never">
				<?php esc_html_e( 'Cron has not run yet.', 'mail-system' ); ?>
			</p>
		<?php endif; ?>
	</div>

	<p class="mskd-widget-link">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-queue' ) ); ?>" class="button button-secondary">
			<?php esc_html_e( 'View Queue', 'mail-system' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=mskd-dashboard' ) ); ?>" class="button button-primary">
			<?php esc_html_e( 'Dashboard', 'mail-system' ); ?>
		</a>
	</p>
</div>
