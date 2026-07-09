<?php
/**
 * Normalizes the AI's confidence score.
 *
 * @package CF7AIC\Services
 */

namespace CF7AIC\Services;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ConfidenceService
 *
 * Clamps and interprets the confidence score already present in the one
 * combined AI analysis response — never calls the AI itself.
 */
final class ConfidenceService {

	/**
	 * Confidence scores below this value are flagged as "Low Confidence"
	 * and should prompt closer human review before sending.
	 *
	 * @var int
	 */
	public const LOW_CONFIDENCE_THRESHOLD = 60;

	/**
	 * Normalizes a raw confidence value to an integer between 0 and 100.
	 *
	 * @param mixed $confidence Raw value from the AI response.
	 *
	 * @return int
	 */
	public static function normalize( $confidence ): int {
		$value = is_numeric( $confidence ) ? (int) round( (float) $confidence ) : 0;

		return max( 0, min( 100, $value ) );
	}

	/**
	 * Whether a (already-normalized) confidence score counts as low.
	 *
	 * @param int $confidence Normalized confidence score (0–100).
	 *
	 * @return bool
	 */
	public static function is_low( int $confidence ): bool {
		return $confidence < self::LOW_CONFIDENCE_THRESHOLD;
	}
}
