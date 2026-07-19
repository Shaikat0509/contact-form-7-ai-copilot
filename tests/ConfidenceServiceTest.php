<?php
/**
 * Tests for the AI confidence normalizer.
 *
 * @package CF7AIC
 */

use CF7AIC\Services\ConfidenceService;

/**
 * The confidence column is TINYINT UNSIGNED, so an out-of-range or
 * non-numeric value from the model is a write error waiting to happen.
 * normalize() is the only thing standing between the two.
 */
class ConfidenceServiceTest extends WP_UnitTestCase {

	/**
	 * Values the model is expected to return pass through intact.
	 *
	 * @return void
	 */
	public function test_in_range_values_pass_through() {
		$this->assertSame( 0, ConfidenceService::normalize( 0 ) );
		$this->assertSame( 60, ConfidenceService::normalize( 60 ) );
		$this->assertSame( 100, ConfidenceService::normalize( 100 ) );
	}

	/**
	 * Models return confidence as a fraction, a float, or a numeric string
	 * depending on the prompt and provider.
	 *
	 * @return void
	 */
	public function test_numeric_strings_and_floats_are_coerced() {
		$this->assertSame( 82, ConfidenceService::normalize( '82' ) );
		$this->assertSame( 83, ConfidenceService::normalize( 82.6 ) );
		$this->assertSame( 82, ConfidenceService::normalize( 82.4 ) );
	}

	/**
	 * Out-of-range values are clamped rather than allowed to overflow the
	 * TINYINT column.
	 *
	 * @return void
	 */
	public function test_out_of_range_values_are_clamped() {
		$this->assertSame( 100, ConfidenceService::normalize( 9001 ) );
		$this->assertSame( 0, ConfidenceService::normalize( -20 ) );
		$this->assertSame( 100, ConfidenceService::normalize( 255 ) );
	}

	/**
	 * Non-numeric input becomes 0 — treated as "no confidence expressed"
	 * rather than throwing.
	 *
	 * @return void
	 */
	public function test_non_numeric_input_becomes_zero() {
		$this->assertSame( 0, ConfidenceService::normalize( 'very high' ) );
		$this->assertSame( 0, ConfidenceService::normalize( null ) );
		$this->assertSame( 0, ConfidenceService::normalize( array( 90 ) ) );
	}

	/**
	 * The low-confidence flag drives a visible warning in the Inbox, so
	 * the boundary matters.
	 *
	 * @return void
	 */
	public function test_low_confidence_boundary_is_exclusive() {
		$threshold = ConfidenceService::LOW_CONFIDENCE_THRESHOLD;

		$this->assertTrue( ConfidenceService::is_low( $threshold - 1 ) );
		$this->assertFalse( ConfidenceService::is_low( $threshold ) );
		$this->assertFalse( ConfidenceService::is_low( $threshold + 1 ) );
	}
}
