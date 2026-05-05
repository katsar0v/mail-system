<?php
/**
 * Opt-in confirmation success page
 *
 * @package MSKD
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php esc_html_e( 'Subscription Confirmed', 'mail-system' ); ?> - <?php bloginfo( 'name' ); ?></title>
	<style>
		body {
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
			background: #f1f1f1;
			margin: 0;
			padding: 0;
			display: flex;
			justify-content: center;
			align-items: center;
			min-height: 100vh;
		}
		.mskd-confirm-box {
			background: #fff;
			padding: 40px;
			border-radius: 8px;
			box-shadow: 0 2px 10px rgba(0,0,0,0.1);
			text-align: center;
			max-width: 400px;
		}
		.mskd-confirm-box h1 {
			color: #1d2327;
			font-size: 24px;
			margin: 0 0 20px;
		}
		.mskd-confirm-box p {
			color: #50575e;
			font-size: 16px;
			margin: 0 0 20px;
		}
		.mskd-confirm-box .mskd-checkmark {
			font-size: 48px;
			color: #00a32a;
			margin-bottom: 20px;
		}
		.mskd-confirm-box a {
			color: #2271b1;
			text-decoration: none;
		}
		.mskd-confirm-box a:hover {
			text-decoration: underline;
		}
	</style>
</head>
<body>
	<div class="mskd-confirm-box">
		<div class="mskd-checkmark">✓</div>
		<h1><?php esc_html_e( 'Subscription Confirmed!', 'mail-system' ); ?></h1>
		<p><?php esc_html_e( 'Thank you for confirming your subscription. You will now receive our emails.', 'mail-system' ); ?></p>
		<p>
			<a href="<?php echo esc_url( home_url() ); ?>">
				<?php esc_html_e( 'Back to site', 'mail-system' ); ?>
			</a>
		</p>
	</div>
</body>
</html>
