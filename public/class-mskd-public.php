<?php
/**
 * Public Class
 *
 * @package MSKD
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use MSKD\Traits\Email_Header_Footer;

/**
 * Class MSKD_Public
 *
 * Handles public-facing functionality
 */
class MSKD_Public {

	use Email_Header_Footer;

	/**
	 * Initialize public hooks
	 */
	public function init() {
		add_action( 'init', array( $this, 'handle_unsubscribe' ) );
		add_action( 'init', array( $this, 'handle_opt_in_confirmation' ) );
		add_action( 'template_redirect', array( $this, 'handle_click_tracking' ), 0 );
		add_action( 'template_redirect', array( $this, 'handle_open_tracking' ), 0 );
		add_shortcode( 'mskd_subscribe_form', array( $this, 'subscribe_form_shortcode' ) );
		add_action( 'wp_ajax_mskd_subscribe', array( $this, 'ajax_subscribe' ) );
		add_action( 'wp_ajax_nopriv_mskd_subscribe', array( $this, 'ajax_subscribe' ) );
	}

	/**
	 * Validate a signed email click, record GET requests, and redirect.
	 *
	 * HEAD requests resolve and redirect without changing analytics. Other
	 * methods, malformed payloads, and unknown recipient tokens are rejected.
	 *
	 * @return void
	 */
	public function handle_click_tracking() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Signed public tracking payload authenticates the request.
		if ( ! isset( $_GET['mskd_track_click'] ) ) {
			return;
		}

		$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
		if ( ! in_array( $request_method, array( 'GET', 'HEAD' ), true ) ) {
			status_header( 405 );
			nocache_headers();
			header( 'Allow: GET, HEAD' );
			exit;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- All fields are authenticated by the HMAC below.
		$token       = is_string( $_GET['mskd_track_click'] ) ? strtolower( sanitize_text_field( wp_unslash( $_GET['mskd_track_click'] ) ) ) : '';
		$link_value  = isset( $_GET['link'] ) && is_string( $_GET['link'] ) ? sanitize_text_field( wp_unslash( $_GET['link'] ) ) : '';
		$encoded_url = isset( $_GET['url'] ) && is_string( $_GET['url'] ) ? sanitize_text_field( wp_unslash( $_GET['url'] ) ) : '';
		$signature   = isset( $_GET['sig'] ) && is_string( $_GET['sig'] ) ? strtolower( sanitize_text_field( wp_unslash( $_GET['sig'] ) ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$link_index = ctype_digit( $link_value ) ? (int) $link_value : 0;

		$tracking_service = new \MSKD\Services\Email_Tracking_Service();
		$destination      = $tracking_service->resolve_click(
			$token,
			$link_index,
			$encoded_url,
			$signature,
			'GET' === $request_method
		);

		if ( false === $destination ) {
			status_header( 400 );
			nocache_headers();
			header( 'Content-Type: text/plain; charset=UTF-8' );
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain translated text response with a fixed content type.
			echo esc_html__( 'Invalid tracking link.', 'mail-system' );
			exit;
		}

		nocache_headers();
		header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0', true );
		header( 'Referrer-Policy: no-referrer' );
		wp_redirect( $destination, 302 );
		exit;
	}

	/**
	 * Record an email open and return a transparent 1x1 GIF.
	 *
	 * Unknown tokens receive the same response so the endpoint does not reveal
	 * whether a queue record exists.
	 *
	 * @return void
	 */
	public function handle_open_tracking() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The random recipient token authenticates this public image request.
		if ( ! isset( $_GET['mskd_track_open'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Type checked and sanitized immediately below.
		$raw_token = wp_unslash( $_GET['mskd_track_open'] );
		$token     = is_string( $raw_token ) ? strtolower( sanitize_text_field( $raw_token ) ) : '';

		$tracking_service = new \MSKD\Services\Email_Tracking_Service();
		$tracking_service->record_open( $token );

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Static transparent GIF payload.
		$pixel = base64_decode( 'R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==' );

		status_header( 200 );
		nocache_headers();
		header( 'Content-Type: image/gif' );
		header( 'Content-Length: ' . strlen( $pixel ) );
		header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0', true );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Binary transparent GIF response.
		echo $pixel;
		exit;
	}

	/**
	 * Enqueue public assets (called only when shortcode is used)
	 */
	private function enqueue_assets() {
		wp_enqueue_style(
			'mskd-public-style',
			MSKD_PLUGIN_URL . 'public/css/public-style.css',
			array(),
			MSKD_VERSION
		);

		// Add custom styling colors.
		$this->add_custom_styles();

		wp_enqueue_script(
			'mskd-public-script',
			MSKD_PLUGIN_URL . 'public/js/public-script.js',
			array( 'jquery' ),
			MSKD_VERSION,
			true
		);

		// Get settings for localization.
		$settings = get_option( 'mskd_settings', array() );

		wp_localize_script(
			'mskd-public-script',
			'mskd_public',
			array(
				'ajax_url'            => admin_url( 'admin-ajax.php' ),
				'nonce'               => wp_create_nonce( 'mskd_public_nonce' ),
				'enable_ga4_tracking' => isset( $settings['enable_ga4_tracking'] ) ? (bool) $settings['enable_ga4_tracking'] : false,
				'strings'             => array(
					'subscribing' => esc_html__( 'Subscribing...', 'mail-system' ),
					'success'     => esc_html__( 'Successfully subscribed!', 'mail-system' ),
					'error'       => esc_html__( 'An error occurred. Please try again.', 'mail-system' ),
				),
			)
		);
	}

	/**
	 * Add custom inline styles based on plugin settings.
	 */
	private function add_custom_styles() {
		$settings = get_option( 'mskd_settings', array() );

		$highlight_color   = isset( $settings['highlight_color'] ) ? $settings['highlight_color'] : '#2271b1';
		$button_text_color = isset( $settings['button_text_color'] ) ? $settings['button_text_color'] : '#ffffff';

		// Sanitize colors to prevent XSS.
		$highlight_color   = sanitize_hex_color( $highlight_color );
		$button_text_color = sanitize_hex_color( $button_text_color );

		// Fallback if sanitization returns empty (invalid color).
		if ( empty( $highlight_color ) ) {
			$highlight_color = '#2271b1';
		}
		if ( empty( $button_text_color ) ) {
			$button_text_color = '#ffffff';
		}

		// Calculate hover color (darker by 20 RGB steps).
		$hover_color = $this->adjust_brightness( $highlight_color, -20 );

		$custom_css = "
			.mskd-input:focus {
				border-color: {$highlight_color};
			}
			.mskd-submit-btn {
				background: {$highlight_color};
				color: {$button_text_color};
			}
			.mskd-submit-btn:hover {
				background: {$hover_color};
			}
		";

		wp_add_inline_style( 'mskd-public-style', $custom_css );
	}

	/**
	 * Adjust the brightness of a hex color.
	 *
	 * @param string $hex    The hex color code (e.g., '#ffffff', 'ffffff', '#fff', or 'fff').
	 * @param int    $steps  Steps to adjust (-255 to 255). Negative = darker, positive = lighter.
	 * @return string The adjusted hex color, or original if invalid.
	 */
	private function adjust_brightness( $hex, $steps ) {
		// Remove # if present.
		$hex = ltrim( $hex, '#' );

		// Handle 3-character shorthand hex (e.g., 'fff' -> 'ffffff').
		if ( preg_match( '/^[0-9A-Fa-f]{3}$/', $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		// Validate hex format (must be exactly 6 hex characters).
		if ( ! preg_match( '/^[0-9A-Fa-f]{6}$/', $hex ) ) {
			return '#' . $hex; // Return as-is if invalid.
		}

		// Parse hex values.
		$r = hexdec( substr( $hex, 0, 2 ) );
		$g = hexdec( substr( $hex, 2, 2 ) );
		$b = hexdec( substr( $hex, 4, 2 ) );

		// Adjust values.
		$r = max( 0, min( 255, $r + $steps ) );
		$g = max( 0, min( 255, $g + $steps ) );
		$b = max( 0, min( 255, $b + $steps ) );

		return sprintf( '#%02x%02x%02x', $r, $g, $b );
	}

	/**
	 * Handle unsubscribe requests
	 */
	public function handle_unsubscribe() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public unsubscribe link does not require nonce, uses token validation.
		if ( ! isset( $_GET['mskd_unsubscribe'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public unsubscribe link does not require nonce, uses token validation.
		$token = sanitize_text_field( wp_unslash( $_GET['mskd_unsubscribe'] ) );

		if ( empty( $token ) ) {
			return;
		}

		// Validate token length (tokens are 32 characters).
		if ( strlen( $token ) !== 32 || ! ctype_alnum( $token ) ) {
			wp_die(
				esc_html__( 'Invalid unsubscribe link.', 'mail-system' ),
				esc_html__( 'Error', 'mail-system' ),
				array( 'response' => 400 )
			);
		}

		// Rate limiting: check transient for this IP.
		if ( ! isset( $_SERVER['REMOTE_ADDR'] ) ) {
			wp_die(
				esc_html__( 'Unable to verify request.', 'mail-system' ),
				esc_html__( 'Error', 'mail-system' ),
				array( 'response' => 400 )
			);
		}
		$ip_hash        = md5( sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) );
		$rate_limit_key = 'mskd_unsubscribe_' . $ip_hash;
		$attempts       = get_transient( $rate_limit_key );

		if ( false !== $attempts && 10 <= $attempts ) {
			wp_die(
				esc_html__( 'Too many attempts. Please try again in 5 minutes.', 'mail-system' ),
				esc_html__( 'Error', 'mail-system' ),
				array( 'response' => 429 )
			);
		}

		// Increment attempts.
		set_transient( $rate_limit_key, ( $attempts ? $attempts + 1 : 1 ), 5 * MINUTE_IN_SECONDS );

		global $wpdb;

		$subscriber = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}mskd_subscribers WHERE unsubscribe_token = %s",
				$token
			)
		);

		if ( ! $subscriber ) {
			wp_die(
				esc_html__( 'Invalid unsubscribe link.', 'mail-system' ),
				esc_html__( 'Error', 'mail-system' ),
				array( 'response' => 400 )
			);
		}

		// Update subscriber status.
		$wpdb->update(
			$wpdb->prefix . 'mskd_subscribers',
			array( 'status' => 'unsubscribed' ),
			array( 'id' => $subscriber->id ),
			array( '%s' ),
			array( '%d' )
		);

		// Show unsubscribe confirmation page.
		include MSKD_PLUGIN_DIR . 'public/partials/unsubscribe.php';
		exit;
	}

	/**
	 * Handle opt-in confirmation requests.
	 *
	 * Processes confirmation links clicked from opt-in emails to activate subscribers.
	 */
	public function handle_opt_in_confirmation() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public confirmation link does not require nonce.
		if ( ! isset( $_GET['mskd_confirm'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public confirmation link does not require nonce.
		$token = sanitize_text_field( wp_unslash( $_GET['mskd_confirm'] ) );

		if ( empty( $token ) ) {
			return;
		}

		// Validate token length (tokens are 32 characters).
		if ( 32 !== strlen( $token ) || ! ctype_alnum( $token ) ) {
			wp_die(
				esc_html__( 'Invalid confirmation link.', 'mail-system' ),
				esc_html__( 'Error', 'mail-system' ),
				array( 'response' => 400 )
			);
		}

		// Rate limiting: check transient for this IP.
		if ( ! isset( $_SERVER['REMOTE_ADDR'] ) ) {
			wp_die(
				esc_html__( 'Unable to verify request.', 'mail-system' ),
				esc_html__( 'Error', 'mail-system' ),
				array( 'response' => 400 )
			);
		}
		$ip_hash        = md5( sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) );
		$rate_limit_key = 'mskd_confirm_' . $ip_hash;
		$attempts       = get_transient( $rate_limit_key );

		if ( false !== $attempts && 10 <= $attempts ) {
			wp_die(
				esc_html__( 'Too many attempts. Please try again in 5 minutes.', 'mail-system' ),
				esc_html__( 'Error', 'mail-system' ),
				array( 'response' => 429 )
			);
		}

		// Increment attempts.
		set_transient( $rate_limit_key, ( $attempts ? $attempts + 1 : 1 ), 5 * MINUTE_IN_SECONDS );

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query needed for opt-in token lookup.
		$subscriber = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}mskd_subscribers WHERE opt_in_token = %s",
				$token
			)
		);

		if ( ! $subscriber ) {
			wp_die(
				esc_html__( 'Invalid confirmation link.', 'mail-system' ),
				esc_html__( 'Error', 'mail-system' ),
				array( 'response' => 400 )
			);
		}

		// Check if already active (idempotent - allow multiple clicks).
		if ( 'active' === $subscriber->status ) {
			// Already confirmed, show success page anyway.
			include MSKD_PLUGIN_DIR . 'public/partials/opt-in-confirm.php';
			exit;
		}

		// Update subscriber status to active and clear opt-in token.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query needed for status update.
		$wpdb->update(
			$wpdb->prefix . 'mskd_subscribers',
			array(
				'status'       => 'active',
				'opt_in_token' => null,
			),
			array( 'id' => $subscriber->id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		// Show confirmation success page.
		include MSKD_PLUGIN_DIR . 'public/partials/opt-in-confirm.php';
		exit;
	}

	/**
	 * Subscribe form shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function subscribe_form_shortcode( $atts ) {
		// Enqueue assets only when shortcode is used.
		$this->enqueue_assets();

		$atts = shortcode_atts(
			array(
				'list_id' => 0,
				'title'   => esc_html__( 'Subscribe', 'mail-system' ),
			),
			$atts
		);

		// Fetch all settings for use in template (show_name_field setting is used in subscribe-form.php)
		$settings = get_option( 'mskd_settings', array() );

		ob_start();
		include MSKD_PLUGIN_DIR . 'public/partials/subscribe-form.php';
		return ob_get_clean();
	}

	/**
	 * AJAX subscribe handler
	 *
	 * Implements double opt-in workflow:
	 * 1. Creates new subscribers with 'inactive' status.
	 * 2. Sends opt-in confirmation email.
	 * 3. Subscriber must click confirmation link to become active.
	 */
	public function ajax_subscribe() {
		check_ajax_referer( 'mskd_public_nonce', 'nonce' );

		global $wpdb;

		$email      = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$first_name = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
		$last_name  = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
		$list_id    = isset( $_POST['list_id'] ) ? intval( $_POST['list_id'] ) : 0;

		if ( ! is_email( $email ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Please enter a valid email address.', 'mail-system' ),
				)
			);
		}

		// Check if subscriber exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query needed for subscriber lookup.
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}mskd_subscribers WHERE email = %s",
				$email
			)
		);

		if ( $existing ) {
			// Handle existing subscriber based on status.
			if ( 'unsubscribed' === $existing->status ) {
				// Reactivate if unsubscribed - generate new opt-in token and send confirmation.
				$opt_in_token = wp_generate_password( 32, false );

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query needed for status update.
				$result = $wpdb->update(
					$wpdb->prefix . 'mskd_subscribers',
					array(
						'status'       => 'inactive',
						'opt_in_token' => $opt_in_token,
						'first_name'   => $first_name ? $first_name : $existing->first_name,
						'last_name'    => $last_name ? $last_name : $existing->last_name,
					),
					array( 'id' => $existing->id ),
					array( '%s', '%s', '%s', '%s' ),
					array( '%d' )
				);

				if ( false === $result ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional logging for debugging database failures.
					error_log( 'MSKD: Failed to update subscriber. DB error: ' . $wpdb->last_error );
					wp_send_json_error(
						array(
							'message' => esc_html__( 'An error occurred. Please try again later.', 'mail-system' ),
						)
					);
				}

				$subscriber_id     = $existing->id;
				$recipient_name    = $first_name ? $first_name : $existing->first_name;
				$recipient_surname = $last_name ? $last_name : $existing->last_name;

				// Send opt-in confirmation email.
				$this->send_opt_in_email( $email, $recipient_name, $recipient_surname, $opt_in_token, $existing->unsubscribe_token ?? '' );
			} elseif ( 'inactive' === $existing->status ) {
				// Already inactive, resend confirmation email with existing token if valid.
				// Only generate new token if existing one is empty.
				if ( ! empty( $existing->opt_in_token ) ) {
					$opt_in_token = $existing->opt_in_token;
				} else {
					$opt_in_token = wp_generate_password( 32, false );

					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query needed for token update.
					$result = $wpdb->update(
						$wpdb->prefix . 'mskd_subscribers',
						array(
							'opt_in_token' => $opt_in_token,
							'first_name'   => $first_name ? $first_name : $existing->first_name,
							'last_name'    => $last_name ? $last_name : $existing->last_name,
						),
						array( 'id' => $existing->id ),
						array( '%s', '%s', '%s' ),
						array( '%d' )
					);

					if ( false === $result ) {
						// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional logging for debugging database failures.
						error_log( 'MSKD: Failed to update subscriber token. DB error: ' . $wpdb->last_error );
						wp_send_json_error(
							array(
								'message' => esc_html__( 'An error occurred. Please try again later.', 'mail-system' ),
							)
						);
					}
				}

				$subscriber_id     = $existing->id;
				$recipient_name    = $first_name ? $first_name : $existing->first_name;
				$recipient_surname = $last_name ? $last_name : $existing->last_name;

				// Resend opt-in confirmation email.
				$this->send_opt_in_email( $email, $recipient_name, $recipient_surname, $opt_in_token, $existing->unsubscribe_token ?? '' );
			} else {
				// Already active - return early with message.
				wp_send_json_success(
					array(
						'message' => esc_html__( 'You are already subscribed!', 'mail-system' ),
					)
				);
			}
		} else {
			// Create new subscriber with inactive status.
			$unsubscribe_token = wp_generate_password( 32, false );
			$opt_in_token      = wp_generate_password( 32, false );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Direct query needed for subscriber insert.
			$result = $wpdb->insert(
				$wpdb->prefix . 'mskd_subscribers',
				array(
					'email'             => $email,
					'first_name'        => $first_name,
					'last_name'         => $last_name,
					'status'            => 'inactive',
					'unsubscribe_token' => $unsubscribe_token,
					'opt_in_token'      => $opt_in_token,
				),
				array( '%s', '%s', '%s', '%s', '%s', '%s' )
			);

			if ( false === $result ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional logging for debugging database failures.
				error_log( 'MSKD: Failed to insert subscriber. DB error: ' . $wpdb->last_error );
				wp_send_json_error(
					array(
						'message' => esc_html__( 'An error occurred. Please try again later.', 'mail-system' ),
					)
				);
			}

			$subscriber_id = $wpdb->insert_id;

			// Send opt-in confirmation email.
			$this->send_opt_in_email( $email, $first_name, $last_name, $opt_in_token, $unsubscribe_token );
		}

		// Add to list if specified.
		if ( $list_id && $subscriber_id ) {
			// Check if already in list.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query needed for list lookup.
			$in_list = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}mskd_subscriber_list 
                WHERE subscriber_id = %d AND list_id = %d",
					$subscriber_id,
					$list_id
				)
			);

			if ( ! $in_list ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Direct query needed for list insert.
				$wpdb->insert(
					$wpdb->prefix . 'mskd_subscriber_list',
					array(
						'subscriber_id' => $subscriber_id,
						'list_id'       => $list_id,
					),
					array( '%d', '%d' )
				);
			}
		}

		// Return success message indicating confirmation email was sent.
		if ( $existing && 'active' === $existing->status ) {
			wp_send_json_success(
				array(
					'message' => esc_html__( 'You are already subscribed!', 'mail-system' ),
				)
			);
		} else {
			wp_send_json_success(
				array(
					'message' => esc_html__( 'Please check your email to confirm your subscription.', 'mail-system' ),
				)
			);
		}
	}

	/**
	 * Send opt-in confirmation email.
	 *
	 * @param string $email        Subscriber email address.
	 * @param string $first_name   Subscriber first name.
	 * @param string $last_name    Subscriber last name.
	 * @param string $opt_in_token Opt-in confirmation token.
	 * @param string $unsubscribe_token Subscriber unsubscribe token.
	 */
	private function send_opt_in_email( $email, $first_name, $last_name, $opt_in_token, $unsubscribe_token ) {
		$confirm_url = add_query_arg(
			array(
				'mskd_confirm' => $opt_in_token,
			),
			home_url()
		);

		$site_name = get_bloginfo( 'name' );

		$subject = sprintf(
			/* translators: %s: Site name */
			esc_html__( 'Confirm your subscription to %s', 'mail-system' ),
			$site_name
		);

		// Use SMTP mailer to respect configured from address and sender name.
		require_once MSKD_PLUGIN_DIR . 'includes/services/class-mskd-smtp-mailer.php';
		$settings    = get_option( 'mskd_settings', array() );
		$body        = $this->get_opt_in_email_body( $email, $first_name, $last_name, $confirm_url, $site_name, $settings, $unsubscribe_token );
		$smtp_mailer = new MSKD_SMTP_Mailer( $settings );

		// Send email using SMTP mailer (respects from_email and from_name from settings).
		$mail_sent = $smtp_mailer->send( $email, $subject, $body );
		if ( ! $mail_sent ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional logging for debugging email failures.
			error_log( 'MSKD: Failed to send opt-in confirmation email to ' . $email . '. Error: ' . $smtp_mailer->get_last_error() );
		}
	}

	/**
	 * Build the opt-in confirmation email body.
	 *
	 * @param string $email       Subscriber email address.
	 * @param string $first_name  Subscriber first name.
	 * @param string $last_name   Subscriber last name.
	 * @param string $confirm_url Opt-in confirmation URL.
	 * @param string $site_name   Site name.
	 * @param array  $settings    Plugin settings.
	 * @param string $unsubscribe_token Subscriber unsubscribe token.
	 * @return string HTML email body with configured header/footer applied.
	 */
	private function get_opt_in_email_body( $email, $first_name, $last_name, $confirm_url, $site_name, array $settings, $unsubscribe_token ) {
		$greeting = $first_name
			/* translators: %s: Subscriber first name */
			? sprintf( esc_html__( 'Hello %s,', 'mail-system' ), $first_name )
			: esc_html__( 'Hello,', 'mail-system' );

		$body = sprintf(
			/* translators: 1: Greeting, 2: Site name, 3: Confirmation URL, 4: Site name */
			__(
				'%1$s

Thank you for subscribing to %2$s!

Please click the link below to confirm your subscription:

%3$s

If you did not request this subscription, you can safely ignore this email.

Best regards,
%4$s',
				'mail-system'
			),
			$greeting,
			$site_name,
			$confirm_url,
			$site_name
		);

		$body = $this->format_plain_text_email_body( $body, $confirm_url );

		$body = $this->apply_header_footer( $body, $settings );

		return $this->replace_opt_in_email_placeholders( $body, $email, $first_name, $last_name, $unsubscribe_token );
	}

	/**
	 * Replace header/footer placeholders for opt-in confirmation emails.
	 *
	 * @param string $content           Email content.
	 * @param string $email             Subscriber email address.
	 * @param string $first_name        Subscriber first name.
	 * @param string $last_name         Subscriber last name.
	 * @param string $unsubscribe_token Subscriber unsubscribe token.
	 * @return string Email content with placeholders replaced.
	 */
	private function replace_opt_in_email_placeholders( $content, $email, $first_name, $last_name, $unsubscribe_token ) {
		$unsubscribe_url  = '';
		$unsubscribe_link = '';

		if ( ! empty( $unsubscribe_token ) ) {
			$unsubscribe_url  = add_query_arg(
				array(
					'mskd_unsubscribe' => $unsubscribe_token,
				),
				home_url()
			);
			$unsubscribe_link = '<a href="' . esc_url( $unsubscribe_url ) . '">' . esc_html__( 'Unsubscribe', 'mail-system' ) . '</a>';
		}

		$placeholders = array(
			'{first_name}'       => esc_html( $first_name ),
			'{last_name}'        => esc_html( $last_name ),
			'{email}'            => esc_html( $email ),
			'{unsubscribe_link}' => $unsubscribe_link,
			'{unsubscribe_url}'  => esc_url( $unsubscribe_url ),
		);

		return str_replace( array_keys( $placeholders ), array_values( $placeholders ), $content );
	}

	/**
	 * Format a translated plain-text email body as safe HTML paragraphs.
	 *
	 * @param string $body        Plain-text email body.
	 * @param string $confirm_url Opt-in confirmation URL.
	 * @return string HTML email body.
	 */
	private function format_plain_text_email_body( $body, $confirm_url ) {
		$escaped_body        = esc_html( $body );
		$escaped_confirm_url = esc_html( $confirm_url );
		$confirm_link        = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( $confirm_url ),
			$escaped_confirm_url
		);

		$escaped_body = str_replace( $escaped_confirm_url, $confirm_link, $escaped_body );
		$paragraphs   = preg_split( '/\R{2,}/', trim( $escaped_body ) );
		$html         = '';

		foreach ( $paragraphs as $paragraph ) {
			$paragraph = trim( $paragraph );

			if ( '' === $paragraph ) {
				continue;
			}

			$html .= '<p>' . nl2br( $paragraph ) . '</p>';
		}

		return $html;
	}
}
