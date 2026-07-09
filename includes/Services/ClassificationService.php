<?php
/**
 * Normalizes AI-assigned category and priority values.
 *
 * @package CF7AIC\Services
 */

namespace CF7AIC\Services;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ClassificationService
 *
 * Owns the fixed set of categories and priorities the AI is asked to
 * choose from (also the single source of truth {@see \CF7AIC\CF7\PromptBuilder}
 * uses to tell the model what's valid). Never calls the AI itself — it
 * only validates/normalizes whatever {@see AIService} already parsed out
 * of the one combined analysis call, so classification never costs a
 * second API request.
 */
final class ClassificationService {

	/**
	 * Allowed categories, keyed by their internal slug.
	 *
	 * @var array<string, string>
	 */
	public const CATEGORIES = array(
		'sales'           => 'Sales',
		'support'         => 'Support',
		'billing'         => 'Billing',
		'technical'       => 'Technical',
		'general'         => 'General',
		'partnership'     => 'Partnership',
		'job_application' => 'Job Application',
		'other'           => 'Other',
	);

	/**
	 * Allowed priorities, keyed by their internal slug.
	 *
	 * @var array<string, string>
	 */
	public const PRIORITIES = array(
		'high'   => 'High',
		'medium' => 'Medium',
		'low'    => 'Low',
	);

	/**
	 * Fallback category used when the AI's response is missing or invalid.
	 *
	 * @var string
	 */
	public const DEFAULT_CATEGORY = 'other';

	/**
	 * Fallback priority used when the AI's response is missing or invalid.
	 *
	 * @var string
	 */
	public const DEFAULT_PRIORITY = 'medium';

	/**
	 * Normalizes a raw category value to a known slug.
	 *
	 * @param string $category Raw value from the AI response.
	 *
	 * @return string One of the keys of {@see self::CATEGORIES}.
	 */
	public static function normalize_category( string $category ): string {
		$key = sanitize_key( $category );

		return array_key_exists( $key, self::CATEGORIES ) ? $key : self::DEFAULT_CATEGORY;
	}

	/**
	 * Normalizes a raw priority value to a known slug.
	 *
	 * @param string $priority Raw value from the AI response.
	 *
	 * @return string One of the keys of {@see self::PRIORITIES}.
	 */
	public static function normalize_priority( string $priority ): string {
		$key = sanitize_key( $priority );

		return array_key_exists( $key, self::PRIORITIES ) ? $key : self::DEFAULT_PRIORITY;
	}

	/**
	 * Returns the human-readable label for a category slug.
	 *
	 * @param string $category Category slug.
	 *
	 * @return string
	 */
	public static function category_label( string $category ): string {
		return self::CATEGORIES[ $category ] ?? self::CATEGORIES[ self::DEFAULT_CATEGORY ];
	}

	/**
	 * Returns the human-readable label for a priority slug.
	 *
	 * @param string $priority Priority slug.
	 *
	 * @return string
	 */
	public static function priority_label( string $priority ): string {
		return self::PRIORITIES[ $priority ] ?? self::PRIORITIES[ self::DEFAULT_PRIORITY ];
	}
}
