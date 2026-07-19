<?php
/**
 * Tests for at-rest API key encryption.
 *
 * @package CF7AIC
 */

use CF7AIC\Helpers\Encryption;

/**
 * A silent failure here means either a plaintext API key in wp_options or
 * a key the provider classes can no longer read. Neither surfaces in the
 * UI as anything more specific than "connection failed".
 */
class EncryptionTest extends WP_UnitTestCase {

	/**
	 * A realistic key survives a round trip.
	 *
	 * @return void
	 */
	public function test_round_trip_returns_the_original() {
		$plaintext = 'sk-proj-abc123DEF456ghi789JKL012mno345PQR';

		$this->assertSame( $plaintext, Encryption::decrypt( Encryption::encrypt( $plaintext ) ) );
	}

	/**
	 * The stored value must not contain the plaintext.
	 *
	 * @return void
	 */
	public function test_ciphertext_does_not_leak_plaintext() {
		$plaintext  = 'sk-proj-abc123DEF456ghi789JKL012mno345PQR';
		$ciphertext = Encryption::encrypt( $plaintext );

		$this->assertNotSame( $plaintext, $ciphertext );
		$this->assertStringNotContainsString( $plaintext, $ciphertext );
		$this->assertStringNotContainsString( 'abc123DEF456', $ciphertext );
	}

	/**
	 * A random IV per call means the same key encrypts to different
	 * ciphertext each time, so equal stored values cannot be correlated.
	 *
	 * @return void
	 */
	public function test_encryption_is_not_deterministic() {
		$plaintext = 'sk-proj-abc123DEF456ghi789JKL012mno345PQR';

		$first  = Encryption::encrypt( $plaintext );
		$second = Encryption::encrypt( $plaintext );

		$this->assertNotSame( $first, $second, 'A fresh IV should be used per call.' );
		$this->assertSame( $plaintext, Encryption::decrypt( $first ) );
		$this->assertSame( $plaintext, Encryption::decrypt( $second ) );
	}

	/**
	 * An empty key is "no key configured", not something to encrypt.
	 *
	 * @return void
	 */
	public function test_empty_input_round_trips_as_empty() {
		$this->assertSame( '', Encryption::encrypt( '' ) );
		$this->assertSame( '', Encryption::decrypt( '' ) );
	}

	/**
	 * Corrupt or foreign ciphertext returns an empty string rather than
	 * raising — the documented behaviour when a site's salts change.
	 *
	 * @return void
	 */
	public function test_undecryptable_input_returns_empty_string() {
		$this->assertSame( '', Encryption::decrypt( 'not-base64-at-all!!' ) );
		$this->assertSame( '', Encryption::decrypt( base64_encode( 'too-short' ) ) );
	}

	/**
	 * Unicode and long keys are handled, since OpenRouter keys in
	 * particular are considerably longer than OpenAI's.
	 *
	 * @return void
	 */
	public function test_handles_long_and_unicode_values() {
		$long = str_repeat( 'k', 512 );
		$this->assertSame( $long, Encryption::decrypt( Encryption::encrypt( $long ) ) );

		$unicode = 'clé-secrète-🔑';
		$this->assertSame( $unicode, Encryption::decrypt( Encryption::encrypt( $unicode ) ) );
	}
}
