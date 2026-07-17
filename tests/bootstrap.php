<?php
/**
 * PHPUnit Bootstrap File
 *
 * @package MSKD\Tests
 */

// Composer autoloader.
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Load Brain Monkey.
require_once dirname( __DIR__ ) . '/vendor/antecedent/patchwork/Patchwork.php';

// Load base TestCase class.
require_once __DIR__ . '/Unit/class-testcase.php';

// Define WordPress constants for testing.
define( 'ABSPATH', '/tmp/wordpress/' );
define( 'WPINC', 'wp-includes' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'MSKD_VERSION', '1.0.0' );
define( 'MSKD_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'MSKD_PLUGIN_URL', 'https://example.com/wp-content/plugins/mail-system/' );
define( 'MSKD_PLUGIN_BASENAME', 'mail-system/mail-system.php' );
define( 'MSKD_BATCH_SIZE', 10 );

if ( ! function_exists( 'wp_get_environment_type' ) ) {
	/**
	 * Return the test WordPress environment type.
	 *
	 * @return string
	 */
	function wp_get_environment_type() {
		return $GLOBALS['mskd_test_environment_type'] ?? 'production';
	}
}

// Register plugin autoloader for traits and namespaced classes.
spl_autoload_register(
	function ( $class_name ) {
		// Handle PSR-4 namespaced classes (MSKD\*)
		if ( strpos( $class_name, 'MSKD\\' ) === 0 ) {
			// Skip test classes - they are handled by Composer autoloader.
			if ( strpos( $class_name, 'MSKD\\Tests\\' ) === 0 ) {
				return;
			}

			// Convert namespace to file path.
			$relative_class = substr( $class_name, 5 ); // Remove 'MSKD\' prefix.
			$parts          = explode( '\\', $relative_class );

			// Get class name (last part) and namespace parts.
			$class_name     = array_pop( $parts );
			$namespace_path = implode( '/', $parts );

			// Convert class name to file name (Admin_Email -> admin-email).
			$file_name = strtolower( str_replace( '_', '-', $class_name ) );

			// Determine file prefix based on namespace (trait- for Traits namespace, class- otherwise).
			$file_prefix = ( 'Traits' === $namespace_path ) ? 'trait-' : 'class-';

			// Lowercase the namespace path for traits directory (traits vs Traits).
			$dir_path = ( 'Traits' === $namespace_path ) ? strtolower( $namespace_path ) : $namespace_path;
			$file     = MSKD_PLUGIN_DIR . 'includes/' . $dir_path . '/' . $file_prefix . $file_name . '.php';

			if ( file_exists( $file ) ) {
				require_once $file;
				return;
			}
		}
	}
);

// Create mock directory structure for PHPMailer.
$phpmailer_dir = '/tmp/wordpress/wp-includes/PHPMailer';
if ( ! is_dir( $phpmailer_dir ) ) {
// phpcs:ignore WordPress.WP.AlternativeFunctions.mkdir_mkdir -- Required for test setup.
	mkdir( $phpmailer_dir, 0777, true );
}

// Create mock directory for wp-admin/includes.
$wp_admin_includes_dir = '/tmp/wordpress/wp-admin/includes';
if ( ! is_dir( $wp_admin_includes_dir ) ) {
// phpcs:ignore WordPress.WP.AlternativeFunctions.mkdir_mkdir -- Required for test setup.
	mkdir( $wp_admin_includes_dir, 0777, true );
}

// Create mock upgrade.php with dbDelta function stub.
if ( ! file_exists( $wp_admin_includes_dir . '/upgrade.php' ) ) {
// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Required for test setup.
	file_put_contents(
		$wp_admin_includes_dir . '/upgrade.php',
		'<?php
// Mock upgrade.php for testing
if ( ! function_exists( "dbDelta" ) ) {
    function dbDelta( $queries = "", $execute = true ) {
        return array();
    }
}
'
	);
}

// Create mock PHPMailer classes for testing.
if ( ! file_exists( $phpmailer_dir . '/PHPMailer.php' ) ) {
// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Required for test setup.
	file_put_contents(
		$phpmailer_dir . '/PHPMailer.php',
		'<?php
namespace PHPMailer\PHPMailer;
class PHPMailer {
    public $Host = "";
    public $Port = 587;
    public $SMTPSecure = "";
    public $SMTPAuth = false;
    public $Username = "";
    public $Password = "";
    public $CharSet = "UTF-8";
    public function __construct($exceptions = null) {}
    public function isSMTP() {}
    public function isMail() {}
    public function setFrom($address, $name = "") {}
    public function addReplyTo($address, $name = "") {}
    public function addAddress($address, $name = "") {}
    public function isHTML($isHtml = true) {}
    public function send() { return true; }
}
'
	);
}

if ( ! file_exists( $phpmailer_dir . '/SMTP.php' ) ) {
// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Required for test setup.
	file_put_contents(
		$phpmailer_dir . '/SMTP.php',
		'<?php
namespace PHPMailer\PHPMailer;
class SMTP {}
'
	);
}

if ( ! file_exists( $phpmailer_dir . '/Exception.php' ) ) {
	file_put_contents(
		$phpmailer_dir . '/Exception.php',
		'<?php
namespace PHPMailer\PHPMailer;
class Exception extends \Exception {}
'
	);
}
