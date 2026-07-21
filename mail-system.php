<?php
/**
 * Plugin Name:       Mail System by Katsarov Design
 * Plugin URI:        https://katsarov.design/plugins/mail-system
 * Description:       Email newsletter management system with subscribers, lists, and sending queue.
 * Version:           1.1.3
 * Requires at least: 5.0
 * Requires PHP:      7.4
 * Author:            Katsarov Design
 * Author URI:        https://katsarov.design
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       mail-system
 * Domain Path:       /languages
 *
 * @package Mail_System_by_Katsarov_Design
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin constants
 */
define( 'MSKD_VERSION', '1.1.3' );
define( 'MSKD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MSKD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MSKD_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'MSKD_BATCH_SIZE', 10 ); // Number of emails to send per minute.

/**
 * Plugin autoloader.
 *
 * This autoloader handles all plugin classes without requiring Composer.
 * It supports both legacy MSKD_ prefixed classes and PSR-4 namespaced classes.
 *
 * Composer autoloader is only used for development (tests, coding standards).
 * The plugin works completely without the vendor/ directory.
 *
 * Class patterns handled:
 * - MSKD_* (legacy): MSKD_Admin, MSKD_Cron_Handler, etc.
 * - MSKD\Admin\* (PSR-4): MSKD\Admin\Admin, MSKD\Admin\Admin_Email, etc.
 * - MSKD\Services\* (PSR-4): MSKD\Services\List_Service, etc.
 * - MSKD\Application\* (PSR-4): MSKD\Application\Campaign_Service, etc.
 * - MSKD\Api\* (PSR-4): MSKD\Api\Rest_Controller, MSKD\Api\Token_Service, etc.
 */
spl_autoload_register(
	function ( $class_name ) {
		// Handle PSR-4 namespaced classes (MSKD\*).
		if ( strpos( $class_name, 'MSKD\\' ) === 0 ) {
				// Skip test classes - they require Composer autoloader (dev only).
			if ( strpos( $class_name, 'MSKD\\Tests\\' ) === 0 ) {
				return;
			}

			// Convert namespace to file path.
			// MSKD\Admin\Admin_Email -> includes/Admin/class-admin-email.php.
			// MSKD\Services\List_Service -> includes/services/class-list-service.php.
			// MSKD\Traits\Email_Header_Footer -> includes/traits/trait-email-header-footer.php.
			$relative_class = substr( $class_name, 5 ); // Remove 'MSKD\' prefix.
			$parts          = explode( '\\', $relative_class );

			// Get class name (last part) and namespace parts.
			$class_name     = array_pop( $parts );
			$namespace_path = implode( '/', $parts );

			// Convert class name to file name (Admin_Email -> admin-email).
			$file_name = strtolower( str_replace( '_', '-', $class_name ) );

			// Determine file prefix based on namespace (trait- for Traits namespace, class- otherwise).
			$file_prefix = ( 'Traits' === $namespace_path ) ? 'trait-' : 'class-';

			// Lowercase the namespace path for services and traits directory.
			$dir_path = $namespace_path;
			if ( 'Traits' === $namespace_path || 'Services' === $namespace_path ) {
				$dir_path = strtolower( $namespace_path );
			}

			$file = MSKD_PLUGIN_DIR . 'includes/' . $dir_path . '/' . $file_prefix . $file_name . '.php';

			if ( file_exists( $file ) ) {
				require_once $file;
				return;
			}
		}

		// Handle legacy MSKD_ prefixed classes.
		if ( strpos( $class_name, 'MSKD_' ) === 0 ) {
			// Convert class name to file path.
			$class_name = str_replace( 'MSKD_', '', $class_name );
			$class_name = strtolower( str_replace( '_', '-', $class_name ) );

			// Possible file locations for legacy classes.
			$locations = array(
				MSKD_PLUGIN_DIR . 'includes/class-mskd-' . $class_name . '.php',
				MSKD_PLUGIN_DIR . 'includes/models/class-mskd-' . $class_name . '.php',
				MSKD_PLUGIN_DIR . 'includes/services/class-mskd-' . $class_name . '.php',
				MSKD_PLUGIN_DIR . 'admin/class-' . $class_name . '.php',
				MSKD_PLUGIN_DIR . 'public/class-mskd-' . $class_name . '.php',
			);

			foreach ( $locations as $file ) {
				if ( file_exists( $file ) ) {
					require_once $file;
					return;
				}
			}
		}
	}
);

/**
 * Load Composer autoloader if available (development only).
 *
 * This is only needed for:
 * - Running tests (PHPUnit, Brain Monkey)
 * - Coding standards checks (PHPCS)
 * - Other dev dependencies
 *
 * The plugin works without this in production.
 */
if ( file_exists( MSKD_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once MSKD_PLUGIN_DIR . 'vendor/autoload.php';
}

/**
 * Plugin activation hook
 */
register_activation_hook( __FILE__, array( 'MSKD_Activator', 'activate' ) );

/**
 * Plugin deactivation hook
 */
register_deactivation_hook( __FILE__, array( 'MSKD_Deactivator', 'deactivate' ) );

/**
 * Load plugin textdomain for translations
 */
function mskd_load_textdomain() {
	load_plugin_textdomain(
		'mail-system',
		false,
		dirname( MSKD_PLUGIN_BASENAME ) . '/languages'
	);
}
add_action( 'init', 'mskd_load_textdomain' );

/**
 * Initialize the plugin
 */
function mskd_init() {
	// Load required files.
	require_once MSKD_PLUGIN_DIR . 'includes/class-activator.php';
	require_once MSKD_PLUGIN_DIR . 'includes/class-mskd-deactivator.php';

	// Check for database upgrades.
	MSKD_Activator::maybe_upgrade();

	// Initialize admin.
	if ( is_admin() ) {
		require_once MSKD_PLUGIN_DIR . 'admin/class-mskd-admin.php';
		$admin = new MSKD_Admin();
		$admin->init();
	}

	// Initialize public.
	require_once MSKD_PLUGIN_DIR . 'public/class-mskd-public.php';
	$public = new MSKD_Public();
	$public->init();

	// Initialize cron handler.
	require_once MSKD_PLUGIN_DIR . 'includes/services/class-mskd-cron-handler.php';
	$cron = new MSKD_Cron_Handler();
	$cron->init();

	// Register the JWT-authenticated REST API.
	$rest_controller = new \MSKD\Api\Rest_Controller();
	add_action( 'rest_api_init', array( $rest_controller, 'register_routes' ) );
}
add_action( 'plugins_loaded', 'mskd_init' );

/**
 * Register custom cron schedules
 *
 * @param array $schedules Existing cron schedules.
 * @return array Modified cron schedules.
 */
function mskd_cron_schedules( $schedules ) {
	$schedules['mskd_every_minute'] = array(
		'interval' => 60,
		'display'  => esc_html__( 'Every minute', 'mail-system' ),
	);
	return $schedules;
}
add_filter( 'cron_schedules', 'mskd_cron_schedules' );

/**
 * Normalize a timestamp to have 00 seconds.
 *
 * This ensures all scheduled events and times are on clean minute boundaries.
 *
 * @param int|null $timestamp Unix timestamp. If null, uses current time.
 * @return int Unix timestamp with seconds set to 00.
 */
function mskd_normalize_timestamp( $timestamp = null ) {
	if ( null === $timestamp ) {
		$timestamp = time();
	}
	// Round down to the start of the current minute (remove seconds).
	return (int) ( floor( $timestamp / 60 ) * 60 );
}

/**
 * Get current time in MySQL format with seconds set to 00.
 *
 * @return string MySQL datetime string with 00 seconds.
 */
function mskd_current_time_normalized() {
	$wp_timezone = wp_timezone();
	$now         = new DateTime( 'now', $wp_timezone );
	$now->setTime( (int) $now->format( 'H' ), (int) $now->format( 'i' ), 0 );
	return $now->format( 'Y-m-d H:i:s' );
}

/**
 * Convert a Unix timestamp to a MySQL datetime string in the WordPress timezone.
 *
 * Unlike gmdate(), which always formats in UTC, this renders the given absolute
 * timestamp using the WordPress Settings → General timezone, keeping all stored
 * datetimes on a single, site-local convention regardless of the server/DB timezone.
 *
 * @param int $timestamp Unix timestamp (absolute).
 * @return string MySQL datetime string in the WordPress timezone.
 */
function mskd_local_time_from_timestamp( $timestamp ) {
	$wp_timezone = wp_timezone();
	$date        = new DateTime( '@' . (int) $timestamp );
	$date->setTimezone( $wp_timezone );
	return $date->format( 'Y-m-d H:i:s' );
}

/**
 * Sanitize HTML content for email templates.
 *
 * Unlike wp_kses_post(), this function allows email-specific HTML tags
 * including <style>, <head>, <body>, and full document structure needed
 * for MJML-generated email templates.
 *
 * @param string $content The HTML content to sanitize.
 * @return string Sanitized HTML content.
 */
function mskd_kses_email( $content ) {
	// Define allowed HTML tags for email templates.
	// This includes all standard email HTML elements plus style tags.
	$allowed_tags = array(
		// Document structure.
		'html'       => array(
			'lang'  => true,
			'xmlns' => true,
		),
		'head'       => array(),
		'title'      => array(),
		'meta'       => array(
			'charset'    => true,
			'name'       => true,
			'content'    => true,
			'http-equiv' => true,
		),
		'style'      => array(
			'type' => true,
		),
		'body'       => array(
			'style' => true,
			'class' => true,
		),
		// Text formatting.
		'div'        => array(
			'style' => true,
			'class' => true,
			'id'    => true,
			'align' => true,
		),
		'span'       => array(
			'style' => true,
			'class' => true,
		),
		'p'          => array(
			'style' => true,
			'class' => true,
			'align' => true,
		),
		'br'         => array(),
		'hr'         => array(
			'style' => true,
		),
		'h1'         => array(
			'style' => true,
			'class' => true,
			'align' => true,
		),
		'h2'         => array(
			'style' => true,
			'class' => true,
			'align' => true,
		),
		'h3'         => array(
			'style' => true,
			'class' => true,
			'align' => true,
		),
		'h4'         => array(
			'style' => true,
			'class' => true,
			'align' => true,
		),
		'strong'     => array(
			'style' => true,
		),
		'b'          => array(
			'style' => true,
		),
		'em'         => array(
			'style' => true,
		),
		'i'          => array(
			'style' => true,
		),
		'u'          => array(
			'style' => true,
		),
		'a'          => array(
			'href'   => true,
			'target' => true,
			'style'  => true,
			'class'  => true,
			'title'  => true,
			'rel'    => true,
		),
		// Tables (essential for email layout).
		'table'      => array(
			'style'       => true,
			'class'       => true,
			'width'       => true,
			'border'      => true,
			'cellpadding' => true,
			'cellspacing' => true,
			'align'       => true,
			'bgcolor'     => true,
			'role'        => true,
		),
		'thead'      => array(
			'style' => true,
		),
		'tbody'      => array(
			'style' => true,
		),
		'tr'         => array(
			'style' => true,
			'class' => true,
			'align' => true,
		),
		'td'         => array(
			'style'   => true,
			'class'   => true,
			'width'   => true,
			'height'  => true,
			'align'   => true,
			'valign'  => true,
			'bgcolor' => true,
			'colspan' => true,
			'rowspan' => true,
		),
		'th'         => array(
			'style'   => true,
			'class'   => true,
			'width'   => true,
			'align'   => true,
			'valign'  => true,
			'colspan' => true,
			'rowspan' => true,
		),
		// Images.
		'img'        => array(
			'src'    => true,
			'alt'    => true,
			'width'  => true,
			'height' => true,
			'style'  => true,
			'class'  => true,
			'border' => true,
			'title'  => true,
		),
		// Lists.
		'ul'         => array(
			'style' => true,
			'class' => true,
		),
		'ol'         => array(
			'style' => true,
			'class' => true,
		),
		'li'         => array(
			'style' => true,
			'class' => true,
		),
		// Other elements.
		'blockquote' => array(
			'style' => true,
		),
		'center'     => array(
			'style' => true,
		),
		'font'       => array(
			'color' => true,
			'face'  => true,
			'size'  => true,
			'style' => true,
		),
		// Comments are preserved.
	);

	// Apply KSES filtering with email-specific allowed tags.
	return wp_kses( $content, $allowed_tags );
}

/**
 * Encrypt a string using WordPress salts.
 *
 * Uses AES-256-CBC encryption with WordPress AUTH_KEY and SECURE_AUTH_KEY as the key.
 * This provides better security than base64 encoding for sensitive data like SMTP passwords.
 *
 * @param string $value The value to encrypt.
 * @return string|false Encrypted value in base64 format, or false on failure.
 */
function mskd_encrypt( $value ) {
	if ( empty( $value ) ) {
		return '';
	}

	// Check if openssl extension is available.
	if ( ! function_exists( 'openssl_encrypt' ) ) {
		// Fallback to base64 if openssl not available (legacy compatibility).
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Fallback when encryption unavailable.
		return base64_encode( $value );
	}

	$cipher = 'aes-256-cbc';

	// Use WordPress salts as encryption key.
	if ( defined( 'AUTH_KEY' ) && defined( 'SECURE_AUTH_KEY' ) ) {
		$key = hash( 'sha256', AUTH_KEY . SECURE_AUTH_KEY, true );
	} else {
		// Fallback if constants not defined.
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Fallback when salts unavailable.
		return base64_encode( $value );
	}

	// Generate a random initialization vector.
	$iv_length = openssl_cipher_iv_length( $cipher );

	// Use random_bytes() for PHP 7.0+ (cryptographically secure).
	// Falls back to openssl_random_pseudo_bytes for PHP 5.x compatibility.
	if ( function_exists( 'random_bytes' ) ) {
		try {
			$iv = random_bytes( $iv_length );
		} catch ( \Exception $e ) {
			// Fallback to openssl if random_bytes fails.
			$iv = openssl_random_pseudo_bytes( $iv_length );
		}
	} else {
		$iv = openssl_random_pseudo_bytes( $iv_length );
	}

	// Encrypt the value.
	$encrypted = openssl_encrypt( $value, $cipher, $key, 0, $iv );

	if ( false === $encrypted ) {
		return false;
	}

	// Combine encoded IV and encrypted data, then base64 encode.
	// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Required for encryption format.
	return base64_encode( base64_encode( $iv ) . '::' . $encrypted );
}

/**
 * Decrypt a string encrypted with mskd_encrypt().
 *
 * @param string $value The encrypted value to decrypt.
 * @return string|false Decrypted value, or false on failure.
 */
function mskd_decrypt( $value ) {
	if ( empty( $value ) ) {
		return '';
	}

	// Check if openssl extension is available.
	if ( ! function_exists( 'openssl_decrypt' ) ) {
		// Fallback to base64 decode if openssl not available (legacy compatibility).
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Fallback when encryption unavailable.
		return base64_decode( $value );
	}

	$cipher = 'aes-256-cbc';

	// Use WordPress salts as encryption key.
	if ( defined( 'AUTH_KEY' ) && defined( 'SECURE_AUTH_KEY' ) ) {
		$key = hash( 'sha256', AUTH_KEY . SECURE_AUTH_KEY, true );
	} else {
		// Fallback if constants not defined.
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Fallback when salts unavailable.
		return base64_decode( $value );
	}

	// Base64 decode the value with strict mode to detect new format vs legacy.
	// Strict mode will return false for invalid base64, triggering legacy fallback.
	// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Required for encryption format.
	$decoded = base64_decode( $value, true );

	if ( false === $decoded ) {
		// Might be legacy base64-only format, try direct decode without strict mode.
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Legacy compatibility.
		return base64_decode( $value );
	}

	// Split IV and encrypted data.
	$parts = explode( '::', $decoded, 2 );

	// Validate IV length to prevent potential issues.
	$iv_length = openssl_cipher_iv_length( $cipher );
	$iv        = false;
	$encrypted = '';

	if ( count( $parts ) === 2 ) {
		list( $encoded_iv, $encrypted ) = $parts;

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Required for encryption format.
		$iv = base64_decode( $encoded_iv, true );
		if ( false === $iv || strlen( $iv ) !== $iv_length ) {
			$iv = false;
		}
	}

	if ( false === $iv && strlen( $decoded ) > $iv_length + 2 && '::' === substr( $decoded, $iv_length, 2 ) ) {
		$iv        = substr( $decoded, 0, $iv_length );
		$encrypted = substr( $decoded, $iv_length + 2 );
	}

	if ( false === $iv ) {
		// Invalid IV length, treat as legacy format.
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Legacy compatibility.
		return base64_decode( $value );
	}

	// Decrypt the value.
	$decrypted = openssl_decrypt( $encrypted, $cipher, $key, 0, $iv );

	if ( false === $decrypted ) {
		return false;
	}

	return $decrypted;
}
