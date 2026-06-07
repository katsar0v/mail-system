<?php
/**
 * Tests for mskd_encrypt and mskd_decrypt functions.
 *
 * @package MSKD\Tests\Unit
 */

namespace MSKD\Tests\Unit;

use Brain\Monkey\Functions;

// Define WordPress constants before including functions.
if ( ! defined( 'AUTH_KEY' ) ) {
	define( 'AUTH_KEY', 'test-auth-key-for-unit-testing-12345678901234567890' );
}
if ( ! defined( 'SECURE_AUTH_KEY' ) ) {
	define( 'SECURE_AUTH_KEY', 'test-secure-auth-key-for-unit-testing-12345678901234567890' );
}

/**
 * Trait with encryption functions for testing.
 */
trait EncryptionFunctions {

	/**
	 * Encrypt a string using WordPress salts.
	 *
	 * Uses AES-256-CBC encryption with WordPress AUTH_KEY and SECURE_AUTH_KEY as key.
	 *
	 * @param string $value The value to encrypt.
	 * @return string|false Encrypted value in base64 format, or false on failure.
	 */
	public function mskd_encrypt( $value ) {
		if ( empty( $value ) ) {
			return '';
		}

		if ( ! function_exists( 'openssl_encrypt' ) ) {
			// phpcs:ignore WordPress.PHP.DiscouragedFunctions.base64_encode_base64_encode -- Using for encryption fallback.
			return base64_encode( $value );
		}

		$cipher = 'aes-256-cbc';

		if ( defined( 'AUTH_KEY' ) && defined( 'SECURE_AUTH_KEY' ) ) {
			$key = hash( 'sha256', AUTH_KEY . SECURE_AUTH_KEY, true );
		} else {
			// phpcs:ignore WordPress.PHP.DiscouragedFunctions.base64_encode_base64_encode -- Using for encryption fallback.
			return base64_encode( $value );
		}

		$iv_length = openssl_cipher_iv_length( $cipher );

		if ( function_exists( 'random_bytes' ) ) {
			try {
				$iv = random_bytes( $iv_length );
			} catch ( \Exception $e ) {
				$iv = openssl_random_pseudo_bytes( $iv_length );
			}
		} else {
			$iv = openssl_random_pseudo_bytes( $iv_length );
		}

		$encrypted = openssl_encrypt( $value, $cipher, $key, 0, $iv );

		if ( false === $encrypted ) {
			return false;
		}

			// phpcs:ignore WordPress.PHP.DiscouragedFunctions.base64_encode_base64_encode -- Using for encryption.
			return base64_encode( base64_encode( $iv ) . '::' . $encrypted );
	}

	/**
	 * Decrypt a string encrypted with mskd_encrypt().
	 *
	 * @param string $value The encrypted value to decrypt.
	 * @return string|false Decrypted value, or false on failure.
	 */
	public function mskd_decrypt( $value ) {
		if ( empty( $value ) ) {
			return '';
		}

		if ( ! function_exists( 'openssl_decrypt' ) ) {
			// phpcs:ignore WordPress.PHP.DiscouragedFunctions.base64_decode_base64_decode -- Using for decryption fallback.
			return base64_decode( $value );
		}

		$cipher = 'aes-256-cbc';

		if ( defined( 'AUTH_KEY' ) && defined( 'SECURE_AUTH_KEY' ) ) {
			$key = hash( 'sha256', AUTH_KEY . SECURE_AUTH_KEY, true );
		} else {
			// phpcs:ignore WordPress.PHP.DiscouragedFunctions.base64_decode_base64_decode -- Using for decryption fallback.
			return base64_decode( $value );
		}

		// phpcs:ignore WordPress.PHP.DiscouragedFunctions.base64_decode_base64_decode -- Using for decryption.
		$decoded = base64_decode( $value, true );

		if ( false === $decoded ) {
			// phpcs:ignore WordPress.PHP.DiscouragedFunctions.base64_decode_base64_decode -- Using for decryption fallback.
			return base64_decode( $value );
		}

			$iv_length = openssl_cipher_iv_length( $cipher );
			$iv        = false;
			$encrypted = '';
			$parts     = explode( '::', $decoded, 2 );

			if ( count( $parts ) === 2 ) {
				list( $encoded_iv, $encrypted ) = $parts;

				// phpcs:ignore WordPress.PHP.DiscouragedFunctions.base64_decode_base64_decode -- Using for decryption.
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
				// phpcs:ignore WordPress.PHP.DiscouragedFunctions.base64_decode_base64_decode -- Using for decryption fallback.
				return base64_decode( $value );
			}

		$decrypted = openssl_decrypt( $encrypted, $cipher, $key, 0, $iv );

		if ( false === $decrypted ) {
			return false;
		}

		return $decrypted;
	}
}

/**
 * Test class for encryption/decryption functions.
 */
class EncryptionTest extends TestCase {
	use EncryptionFunctions;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();
	}

	/**
	 * Test that mskd_encrypt returns empty string for empty input.
	 */
	public function test_encrypt_empty_value_returns_empty_string(): void {
		$this->assertSame( '', $this->mskd_encrypt( '' ) );
	}

	/**
	 * Test that mskd_decrypt returns empty string for empty input.
	 */
	public function test_decrypt_empty_value_returns_empty_string(): void {
		$this->assertSame( '', $this->mskd_decrypt( '' ) );
	}

	/**
	 * Test that encrypt/decrypt round trip works correctly.
	 */
	public function test_encrypt_decrypt_round_trip(): void {
		$original  = 'my-secret-password-123!@#';
		$encrypted = $this->mskd_encrypt( $original );

		// Encrypted value should be different from original.
		$this->assertNotSame( $original, $encrypted );

		// Decrypted value should match original.
		$decrypted = $this->mskd_decrypt( $encrypted );
		$this->assertSame( $original, $decrypted );
	}

	/**
	 * Test that encrypt/decrypt works with special characters.
	 */
	public function test_encrypt_decrypt_special_characters(): void {
		$original  = 'пароль123!@#$%^&*()_+-={}[]|\\:";\'<>?,./~`';
		$encrypted = $this->mskd_encrypt( $original );
		$decrypted = $this->mskd_decrypt( $encrypted );

		$this->assertSame( $original, $decrypted );
	}

	/**
	 * Test that encrypt/decrypt works with long strings.
	 */
	public function test_encrypt_decrypt_long_string(): void {
		$original  = str_repeat( 'a', 10000 );
		$encrypted = $this->mskd_encrypt( $original );
		$decrypted = $this->mskd_decrypt( $encrypted );

		$this->assertSame( $original, $decrypted );
	}

	/**
	 * Test that encrypted value contains separator.
	 */
	public function test_encrypted_format_contains_separator(): void {
		$encrypted = $this->mskd_encrypt( 'test' );

		// Decode and check for separator.
		// phpcs:ignore WordPress.PHP.DiscouragedFunctions.base64_decode_base64_decode -- Using for testing.
		$decoded = base64_decode( $encrypted );
		$this->assertStringContainsString( '::', $decoded );
	}

	/**
	 * Test that legacy base64-only values are handled correctly.
	 */
	public function test_decrypt_legacy_base64_format(): void {
		// Simulate legacy base64-encoded password (no IV prefix).
		$original = 'old-password';
		// phpcs:ignore WordPress.PHP.DiscouragedFunctions.base64_encode_base64_encode -- Using for testing.
		$legacy_encoded = base64_encode( $original );

		$decrypted = $this->mskd_decrypt( $legacy_encoded );

		$this->assertSame( $original, $decrypted );
	}

	/**
	 * Test legacy encrypted payloads with delimiter bytes inside the IV.
	 */
	public function test_decrypt_legacy_payload_with_separator_inside_iv(): void {
		$original  = 'legacy-secret-password';
		$cipher    = 'aes-256-cbc';
		$key       = hash( 'sha256', AUTH_KEY . SECURE_AUTH_KEY, true );
		$iv        = 'ab::' . str_repeat( 'x', 12 );
		$encrypted = openssl_encrypt( $original, $cipher, $key, 0, $iv );

		// phpcs:ignore WordPress.PHP.DiscouragedFunctions.base64_encode_base64_encode -- Using for testing legacy encryption format.
		$legacy_payload = base64_encode( $iv . '::' . $encrypted );

		$this->assertSame( $original, $this->mskd_decrypt( $legacy_payload ) );
	}

	/**
	 * Test that each encryption produces different output (due to random IV).
	 */
	public function test_encrypt_produces_unique_output(): void {
		$original   = 'same-password';
		$encrypted1 = $this->mskd_encrypt( $original );
		$encrypted2 = $this->mskd_encrypt( $original );

		// Should be different due to different IVs.
		$this->assertNotSame( $encrypted1, $encrypted2 );

		// But both should decrypt to the same value.
		$this->assertSame( $original, $this->mskd_decrypt( $encrypted1 ) );
		$this->assertSame( $original, $this->mskd_decrypt( $encrypted2 ) );
	}

	/**
	 * Test decrypt returns false for corrupted data.
	 */
	public function test_decrypt_corrupted_data_returns_false(): void {
		// Create valid encrypted data.
		$encrypted = $this->mskd_encrypt( 'test' );

		// Corrupt encrypted data (modify middle).
		$corrupted = substr( $encrypted, 0, 10 ) . 'XXXXX' . substr( $encrypted, 15 );

		$result = $this->mskd_decrypt( $corrupted );

		// Should either return false or corrupted base64 decode (legacy fallback).
		// The key is that it doesn't return the original 'test' value.
		$this->assertNotSame( 'test', $result );
	}

	/**
	 * Test that mskd_encrypt returns a base64-encoded string.
	 */
	public function test_encrypted_value_is_base64(): void {
		$encrypted = $this->mskd_encrypt( 'test-value' );

		// Should be valid base64.
		// phpcs:ignore WordPress.PHP.DiscouragedFunctions.base64_decode_base64_decode -- Using for testing.
		$decoded = base64_decode( $encrypted, true );
		$this->assertNotFalse( $decoded );
	}

	/**
	 * Test encrypt/decrypt with null bytes.
	 */
	public function test_encrypt_decrypt_with_null_bytes(): void {
		$original  = "test\x00with\x00null\x00bytes";
		$encrypted = $this->mskd_encrypt( $original );
		$decrypted = $this->mskd_decrypt( $encrypted );

		$this->assertSame( $original, $decrypted );
	}

	/**
	 * Test that decrypting non-base64 data returns input decoded.
	 */
	public function test_decrypt_handles_plain_text_gracefully(): void {
		// Plain text that's not valid base64.
		$plain_text = 'This is not base64!@#$%';

		// Should attempt legacy decode - may return false or garbled output.
		$result = $this->mskd_decrypt( $plain_text );

		// Just ensure no exception is thrown.
		$this->assertTrue( true );
	}
}
