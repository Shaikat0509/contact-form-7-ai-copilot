<?php
/**
 * Turns a Contact Form 7 submission into AI-ready prompt text.
 *
 * @package CF7AIC\CF7
 */

namespace CF7AIC\CF7;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CF7AIC\Services\ClassificationService;

/**
 * Class PromptBuilder
 *
 * Contact Form 7 does not track a structured "label" per field separately
 * from the surrounding form HTML, so field names are prettified (e.g.
 * `your-message` becomes "Your Message") rather than guessed at by a
 * fixed field-name convention — this works for any form layout.
 */
final class PromptBuilder {

	/**
	 * Form tag base types that never carry meaningful text content and
	 * are excluded from the formatted submission (file uploads, spam
	 * countermeasures).
	 *
	 * @var string[]
	 */
	private const SKIP_BASETYPES = array( 'file', 'file*', 'acceptance', 'quiz', 'captchar', 'captchac' );

	/**
	 * Form tag base types that carry a phone number.
	 *
	 * @var string[]
	 */
	private const PHONE_BASETYPES = array( 'tel' );

	/**
	 * Formats a submission's posted data into a readable "Label: value"
	 * text block suitable for inclusion in an AI prompt.
	 *
	 * @param \WPCF7_ContactForm $contact_form The form that was submitted.
	 * @param \WPCF7_Submission  $submission   The current submission.
	 *
	 * @return string
	 */
	public static function format_submission( \WPCF7_ContactForm $contact_form, \WPCF7_Submission $submission ): string {
		$labels = self::get_content_field_labels( $contact_form );
		$posted = $submission->get_posted_data();

		$lines = array();

		foreach ( $posted as $name => $value ) {
			if ( ! isset( $labels[ $name ] ) ) {
				continue;
			}

			if ( is_array( $value ) ) {
				$value = implode( ', ', array_map( 'strval', $value ) );
			}

			$value = trim( (string) $value );

			if ( '' === $value ) {
				continue;
			}

			$lines[] = $labels[ $name ] . ': ' . $value;
		}

		return implode( "\n", $lines );
	}

	/**
	 * Finds the first submitted, validly-formatted email address from any
	 * `email`-type field on the form.
	 *
	 * @param \WPCF7_ContactForm $contact_form The form that was submitted.
	 * @param \WPCF7_Submission  $submission   The current submission.
	 *
	 * @return string|null
	 */
	public static function find_visitor_email( \WPCF7_ContactForm $contact_form, \WPCF7_Submission $submission ): ?string {
		$posted = $submission->get_posted_data();

		foreach ( $contact_form->scan_form_tags() as $tag ) {
			if ( 'email' !== $tag->basetype || empty( $posted[ $tag->name ] ) ) {
				continue;
			}

			$value = $posted[ $tag->name ];
			$value = is_array( $value ) ? reset( $value ) : $value;
			$value = trim( (string) $value );

			if ( is_email( $value ) ) {
				return $value;
			}
		}

		return null;
	}

	/**
	 * Finds the first submitted value from any `tel`-type field on the form.
	 *
	 * @param \WPCF7_ContactForm $contact_form The form that was submitted.
	 * @param \WPCF7_Submission  $submission   The current submission.
	 *
	 * @return string|null
	 */
	public static function find_visitor_phone( \WPCF7_ContactForm $contact_form, \WPCF7_Submission $submission ): ?string {
		$posted = $submission->get_posted_data();

		foreach ( $contact_form->scan_form_tags() as $tag ) {
			if ( ! in_array( $tag->basetype, self::PHONE_BASETYPES, true ) || empty( $posted[ $tag->name ] ) ) {
				continue;
			}

			$value = $posted[ $tag->name ];
			$value = is_array( $value ) ? reset( $value ) : $value;
			$value = trim( (string) $value );

			if ( '' !== $value ) {
				return $value;
			}
		}

		return null;
	}

	/**
	 * Guesses the visitor's display name from any field whose name looks
	 * like it holds one (`your-name`, `full-name`, `first-name`, etc.) —
	 * Contact Form 7 has no dedicated "name" field type the way it does
	 * for `email` and `tel`, so this is a naming-convention heuristic
	 * rather than a structural one.
	 *
	 * @param \WPCF7_ContactForm $contact_form The form that was submitted.
	 * @param \WPCF7_Submission  $submission   The current submission.
	 *
	 * @return string|null
	 */
	public static function find_visitor_name( \WPCF7_ContactForm $contact_form, \WPCF7_Submission $submission ): ?string {
		$posted = $submission->get_posted_data();

		foreach ( $contact_form->scan_form_tags() as $tag ) {
			if ( 'text' !== $tag->basetype ) {
				continue;
			}

			if ( 1 !== preg_match( '/name/i', $tag->name ) || empty( $posted[ $tag->name ] ) ) {
				continue;
			}

			$value = $posted[ $tag->name ];
			$value = is_array( $value ) ? reset( $value ) : $value;
			$value = trim( (string) $value );

			if ( '' !== $value ) {
				return $value;
			}
		}

		return null;
	}

	/**
	 * Builds the single combined instruction + data prompt used to
	 * generate every AI Inbox field (summary, suggested reply, category,
	 * priority, confidence, reasoning) in one request.
	 *
	 * @param string $submission_text Formatted submission, from {@see self::format_submission()}.
	 *
	 * @return string
	 */
	public static function build_analysis_prompt( string $submission_text ): string {
		$categories = implode( ', ', array_keys( ClassificationService::CATEGORIES ) );
		$priorities = implode( ', ', array_keys( ClassificationService::PRIORITIES ) );

		return "A visitor submitted the following contact form. Analyze it and respond with ONLY a single valid JSON object — no markdown code fences, no commentary before or after — with exactly these keys:\n\n"
			. "{\n"
			. "  \"summary\": a short 1-2 sentence internal summary of the request,\n"
			. "  \"suggested_reply\": a professional email reply that thanks the visitor, acknowledges their specific inquiry, answers using only the information provided below (never invent facts or make promises), and politely asks for any missing information needed to help them further — body text only, no subject line,\n"
			. "  \"category\": exactly one of [{$categories}],\n"
			. "  \"priority\": exactly one of [{$priorities}],\n"
			. "  \"confidence\": an integer from 0 to 100 representing how confident you are that you understood the customer's request,\n"
			. "  \"reasoning\": a short explanation (1-2 sentences) covering why you chose that category, priority, and confidence\n"
			. "}\n\n"
			. "Submission:\n" . $submission_text;
	}

	/**
	 * Turns a raw field name (e.g. `your-message`) into a readable label
	 * (e.g. "Your Message"). Used both when a live form tag is available
	 * and, on the Submission Details screen, when only the stored field
	 * name from a historical submission is available.
	 *
	 * @param string $name Raw field name.
	 *
	 * @return string
	 */
	public static function prettify_field_name( string $name ): string {
		return ucwords( str_replace( array( '-', '_' ), ' ', $name ) );
	}

	/**
	 * Builds a map of form tag name => prettified label for every field
	 * that can carry meaningful text content.
	 *
	 * @param \WPCF7_ContactForm $contact_form The form to scan.
	 *
	 * @return array<string, string>
	 */
	private static function get_content_field_labels( \WPCF7_ContactForm $contact_form ): array {
		$labels = array();

		foreach ( $contact_form->scan_form_tags() as $tag ) {
			if ( '' === $tag->name || in_array( $tag->basetype, self::SKIP_BASETYPES, true ) ) {
				continue;
			}

			$labels[ $tag->name ] = self::prettify_field_name( $tag->name );
		}

		return $labels;
	}
}
