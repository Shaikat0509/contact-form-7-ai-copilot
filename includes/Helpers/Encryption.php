<?php
/**
 * Symmetric encryption helper for at-rest secrets (API keys).
 *
 * @package CF7AIC\Helpers
 */

namespace CF7AIC\Helpers;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Encryption
 *
 * Encrypts/decrypts short secrets (API keys) before they are stored in the
 * `wp_options` table.
 *
 * Threat model: this protects against casual exposure of the options table
 * on its own — a database backup that leaks, a read-only SQL disclosure
 * bug, a support engineer with DB-only access. It does NOT protect against
 * an attacker who can also read `wp-config.php` on the same server, since
 * the encryption key is derived from a WordPress salt defined there. That
 * is the same bar the vast majority of WordPress plugins hold API keys
 * to, and is consistent with "never expose keys in the frontend" rather
 * than a claim of protection against full server compromise.
 */
final class Encryption {

	/**
	 * Cipher method used for encryption.
	 *
	 * @var string
	 */
	private const CIPHER = 'aes-256-cbc';

	/**
	 * Encrypts a plaintext string for storage.
	 *
	 * @param string $plaintext The value to encrypt. An empty string is
	 *                          returned unchanged (nothing to protect).
	 *
	 * @return string Base64-encoded ciphertext (IV prepended), or an empty
	 *                string if the input was empty or encryption failed.
	 */
	public static function encrypt( string $plaintext ): string {
		if ( '' === $plaintext ) {
			return '';
		}

		$iv_length = openssl_cipher_iv_length( self::CIPHER );
		if ( false === $iv_length ) {
			return '';
		}

		$iv = openssl_random_pseudo_bytes( $iv_length );

		$ciphertext = openssl_encrypt( $plaintext, self::CIPHER, self::encryption_key(), OPENSSL_RAW_DATA, $iv );
		if ( false === $ciphertext ) {
			return '';
		}

		return base64_encode( $iv . $ciphertext ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- storage encoding, not obfuscation.
	}

	/**
	 * Decrypts a value previously produced by {@see self::encrypt()}.
	 *
	 * @param string $encrypted Base64-encoded ciphertext (IV prepended).
	 *
	 * @return string The original plaintext, or an empty string if the
	 *                input was empty or could not be decrypted (e.g. the
	 *                site's salts changed since it was encrypted).
	 */
	public static function decrypt( string $encrypted ): string {
		if ( '' === $encrypted ) {
			return '';
		}

		$iv_length = openssl_cipher_iv_length( self::CIPHER );
		if ( false === $iv_length ) {
			return '';
		}

		$raw = base64_decode( $encrypted, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- storage encoding, not obfuscation.
		if ( false === $raw || strlen( $raw ) <= $iv_length ) {
			return '';
		}

		$iv         = substr( $raw, 0, $iv_length );
		$ciphertext = substr( $raw, $iv_length );

		$plaintext = openssl_decrypt( $ciphertext, self::CIPHER, self::encryption_key(), OPENSSL_RAW_DATA, $iv );

		return false === $plaintext ? '' : $plaintext;
	}

	/**
	 * Derives a 256-bit encryption key from the site's WordPress auth salt.
	 *
	 * @return string Raw 32-byte key.
	 */
	private static function encryption_key(): string {
		return hash( 'sha256', wp_salt( 'auth' ), true );
	}
}
