<?php
/**
 * SMTP Mailer Service
 *
 * Handles sending emails via SMTP using PHPMailer.
 *
 * @package MSKD
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MSKD_SMTP_Mailer
 *
 * Provides SMTP email sending functionality with proper error handling and logging.
 */
class MSKD_SMTP_Mailer {

	/**
	 * SMTP settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Last error message.
	 *
	 * @var string
	 */
	private $last_error = '';

	/**
	 * Debug log.
	 *
	 * @var array
	 */
	private $debug_log = array();

	/**
	 * Constructor.
	 *
	 * @param array $settings SMTP settings array.
	 */
	public function __construct( $settings = array() ) {
		if ( empty( $settings ) ) {
			$settings = get_option( 'mskd_settings', array() );
		}
		$this->settings = $settings;
	}

	/**
	 * Check if mailer can send emails (always true - uses PHP mail as fallback).
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return true;
	}

	/**
	 * Check if SMTP is specifically enabled and configured.
	 *
	 * @return bool
	 */
	public function is_smtp_enabled() {
		return ! empty( $this->settings['smtp_enabled'] )
			&& ! empty( $this->settings['smtp_host'] );
	}

	/**
	 * Get the last error message.
	 *
	 * @return string
	 */
	public function get_last_error() {
		return $this->last_error;
	}

	/**
	 * Get the debug log.
	 *
	 * @return array
	 */
	public function get_debug_log() {
		return $this->debug_log;
	}

	/**
	 * Send an email via SMTP.
	 *
	 * @param string $to          Recipient email address.
	 * @param string $subject     Email subject.
	 * @param string $body        Email body (HTML).
	 * @param array  $headers     Optional. Additional headers.
	 * @param string $from_email  Optional. Custom sender email address.
	 * @param string $from_name   Optional. Custom sender name.
	 * @return bool True on success, false on failure.
	 */
	public function send( $to, $subject, $body, $headers = array(), $from_email = null, $from_name = null ) {
		$this->last_error = '';
		$this->debug_log  = array();

		// Create a local PHPMailer instance to avoid conflicts with global instance.
		require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
		require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
		require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';

		$mailer = new PHPMailer\PHPMailer\PHPMailer( true );

		try {
			// Configure mailer - use SMTP if configured, otherwise use PHP mail.
			if ( $this->is_smtp_enabled() ) {
				$mailer->isSMTP();
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer property.
				$mailer->Host = $this->settings['smtp_host'];
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer property.
				$mailer->Port = ! empty( $this->settings['smtp_port'] ) ? (int) $this->settings['smtp_port'] : 587;
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer property.
				$mailer->SMTPSecure = $this->get_smtp_secure();
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer property.
				$mailer->SMTPAuth = ! empty( $this->settings['smtp_auth'] );

				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer property.
				if ( $mailer->SMTPAuth ) {
					// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer property.
					$mailer->Username = ! empty( $this->settings['smtp_username'] ) ? $this->settings['smtp_username'] : '';
					// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer property.
					$mailer->Password = ! empty( $this->settings['smtp_password'] ) ? mskd_decrypt( $this->settings['smtp_password'] ) : '';
				}
			} else {
				// Use PHP's mail() function as fallback.
				$mailer->isMail();
			}

			// Set sender with fallback to global settings.
			$final_from_email = ! empty( $from_email ) ? $from_email :
				( ! empty( $this->settings['from_email'] ) ? $this->settings['from_email'] : get_bloginfo( 'admin_email' ) );
			$final_from_name  = ! empty( $from_name ) ? $from_name :
				( ! empty( $this->settings['from_name'] ) ? $this->settings['from_name'] : get_bloginfo( 'name' ) );

			$mailer->setFrom( $final_from_email, $final_from_name );

			// Set reply-to (use custom from email if provided, otherwise use global setting).
			$reply_to = ! empty( $from_email ) ? $from_email :
				( ! empty( $this->settings['reply_to'] ) ? $this->settings['reply_to'] : $final_from_email );
			$mailer->addReplyTo( $reply_to );

			// Set recipient.
			$mailer->addAddress( $to );

			// Set email content.
			$mailer->isHTML( true );
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer property.
			$mailer->CharSet = 'UTF-8';
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer property.
			$mailer->Subject = $subject;
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer property.
			$mailer->Body = $body;
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer property.
			$mailer->AltBody = wp_strip_all_tags( $body );

			// Process additional headers.
			$this->process_headers( $mailer, $headers );

			// Enable debug mode for logging (internal only).
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer property.
			$mailer->SMTPDebug = 0;
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer property.
			$mailer->Debugoutput = function ( $str, $level ) {
				$this->debug_log[] = array(
					'level'   => $level,
					'message' => $str,
					'time'    => current_time( 'mysql' ),
				);
			};

			// Attempt to send.
			$result = $mailer->send();

			if ( $result ) {
				$this->log_success( $to, $subject );
			}

			return $result;

		} catch ( PHPMailer\PHPMailer\Exception $e ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer property.
			$this->log_error( $mailer->ErrorInfo );
			return false;
		} catch ( Exception $e ) {
			$this->log_error( $e->getMessage() );
			return false;
		}
	}

	/**
	 * Test SMTP connection.
	 *
	 * @param string $from_email Optional. Custom sender email to test with.
	 * @return array Result array with 'success' and 'message' keys.
	 */
	public function test_connection( $from_email = null ) {
		$this->last_error = '';
		$this->debug_log  = array();

		// Check if SMTP is enabled.
		if ( ! $this->is_smtp_enabled() ) {
			return array(
				'success' => false,
				'message' => __( 'SMTP is not enabled or configured. Please fill in SMTP settings.', 'mail-system' ),
			);
		}

		// Validate from_email before proceeding (use custom or global).
		$final_from_email = ! empty( $from_email ) ? $from_email :
			( ! empty( $this->settings['from_email'] ) ? $this->settings['from_email'] : get_bloginfo( 'admin_email' ) );
		if ( ! is_email( $final_from_email ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid sender email address.', 'mail-system' ),
			);
		}

		// Use WordPress PHPMailer.
		require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
		require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
		require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';

		$mailer = new PHPMailer\PHPMailer\PHPMailer( true );

		try {
			// Configure SMTP.
			$mailer->isSMTP();
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer property.
			$mailer->Host = $this->settings['smtp_host'];
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer property.
			$mailer->Port = ! empty( $this->settings['smtp_port'] ) ? (int) $this->settings['smtp_port'] : 587;
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer property.
			$mailer->SMTPSecure = $this->get_smtp_secure();
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer property.
			$mailer->SMTPAuth = ! empty( $this->settings['smtp_auth'] );
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer property.
			$mailer->Timeout = 15;

			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer property.
			if ( $mailer->SMTPAuth ) {
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer property.
				$mailer->Username = ! empty( $this->settings['smtp_username'] ) ? $this->settings['smtp_username'] : '';
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer property.
				$mailer->Password = ! empty( $this->settings['smtp_password'] ) ? mskd_decrypt( $this->settings['smtp_password'] ) : '';
			}

			// Set sender using the validated email.
			$from_name = ! empty( $this->settings['from_name'] ) ? $this->settings['from_name'] : get_bloginfo( 'name' );
			$mailer->setFrom( $final_from_email, $from_name );

			// Set test recipient.
			$to = get_bloginfo( 'admin_email' );
			$mailer->addAddress( $to );

			// Set test email content.
			$mailer->isHTML( true );
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer property.
			$mailer->CharSet = 'UTF-8';
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer property.
			$mailer->Subject = sprintf(
				/* translators: %s: Site name */
				__( '[%s] SMTP Test Email', 'mail-system' ),
				get_bloginfo( 'name' )
			);
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer property.
			$mailer->Body = sprintf(
				/* translators: %1$s: Current time, %2$s: SMTP host, %3$s: SMTP port */
				__( '<h2>SMTP Test Email</h2><p>This email confirms that SMTP settings are working correctly.</p><p><strong>Time:</strong> %1$s</p><p><strong>SMTP server:</strong> %2$s:%3$s</p>', 'mail-system' ),
				current_time( 'mysql' ),
				$this->settings['smtp_host'],
				$this->settings['smtp_port']
			);
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer property.
			$mailer->AltBody = wp_strip_all_tags( $mailer->Body );

			// Try to send.
			$result = $mailer->send();

			if ( $result ) {
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer property.
				$this->log_success( $to, $mailer->Subject );
				return array(
					'success' => true,
					'message' => sprintf(
						/* translators: %s: Admin email address */
						__( 'Test email sent successfully to %s!', 'mail-system' ),
						$to
					),
				);
			}

			return array(
				'success' => false,
				'message' => __( 'Failed to send test email.', 'mail-system' ),
			);

		} catch ( PHPMailer\PHPMailer\Exception $e ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer property.
			$this->log_error( $mailer->ErrorInfo );
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: Error message */
					__( 'SMTP error: %s', 'mail-system' ),
					// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer property.
					$mailer->ErrorInfo
				),
			);
		} catch ( Exception $e ) {
			$this->log_error( $e->getMessage() );
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: Error message */
					__( 'Error: %s', 'mail-system' ),
					$e->getMessage()
				),
			);
		}
	}

	/**
	 * Get SMTP secure setting.
	 *
	 * @return string PHPMailer secure type.
	 */
	private function get_smtp_secure() {
		$security = ! empty( $this->settings['smtp_security'] ) ? $this->settings['smtp_security'] : '';

		switch ( $security ) {
			case 'ssl':
				return PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
			case 'tls':
				return PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
			default:
				return '';
		}
	}

	/**
	 * Process additional headers.
	 *
	 * @param PHPMailer\PHPMailer\PHPMailer $phpmailer PHPMailer instance.
	 * @param array                         $headers   Headers array.
	 */
	private function process_headers( $phpmailer, $headers ) {
		if ( empty( $headers ) || ! is_array( $headers ) ) {
			return;
		}

		foreach ( $headers as $header ) {
			if ( is_string( $header ) && strpos( $header, ':' ) !== false ) {
				list( $name, $value ) = explode( ':', $header, 2 );
				$name                 = trim( $name );
				$value                = trim( $value );

				// Handle Bcc specially using PHPMailer's addBCC method.
				if ( 'bcc' === strtolower( $name ) ) {
					// Validate email address before adding.
					if ( is_email( $value ) ) {
						$phpmailer->addBCC( $value );
					}
					continue;
				}

				// Handle Cc specially using PHPMailer's addCC method.
				if ( 'cc' === strtolower( $name ) ) {
					// Validate email address before adding.
					if ( is_email( $value ) ) {
						$phpmailer->addCC( $value );
					}
					continue;
				}

				// Skip other headers that PHPMailer handles.
				if ( in_array( strtolower( $name ), array( 'from', 'to', 'reply-to', 'content-type' ), true ) ) {
					continue;
				}

				$phpmailer->addCustomHeader( $name, $value );
			}
		}
	}

	/**
	 * Log successful email send.
	 *
	 * @param string $to      Recipient.
	 * @param string $subject Subject.
	 */
	private function log_success( $to, $subject ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional debug logging when WP_DEBUG is enabled.
			error_log(
				sprintf(
					'[MSKD SMTP] Email sent successfully to %s: %s',
					$to,
					$subject
				)
			);
		}
	}

	/**
	 * Log an error.
	 *
	 * @param string $message Error message.
	 */
	private function log_error( $message ) {
		$this->last_error = $message;

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional debug logging when WP_DEBUG is enabled.
			error_log( sprintf( '[MSKD SMTP] Error: %s', $message ) );
		}
	}
}
