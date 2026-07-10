<?php
/**
 * Runs the single combined AI analysis call for a submission.
 *
 * @package CF7AIC\Services
 */

namespace CF7AIC\Services;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CF7AIC\AI\ProviderException;
use CF7AIC\CF7\PromptBuilder;
use CF7AIC\Interfaces\AIProviderInterface;

/**
 * Class AIService
 *
 * Makes exactly one AI request per submission — the model is asked to
 * return a single JSON object covering the summary, suggested reply,
 * category, priority, confidence, and reasoning all at once, so
 * generating five pieces of insight never costs more than one API call.
 */
final class AIService {

	/**
	 * Analyzes a submission with a single AI request.
	 *
	 * @param AIProviderInterface $provider        The configured AI provider.
	 * @param string              $system_prompt   The admin-configured system prompt.
	 * @param string              $submission_text Formatted submission text, from
	 *                                              {@see PromptBuilder::format_submission()}.
	 *
	 * @throws ProviderException If the request fails, or the response cannot be
	 *                           parsed into a usable summary or reply.
	 *
	 * @return array{
	 *     summary: string,
	 *     suggested_reply: string,
	 *     category: string,
	 *     priority: string,
	 *     confidence: int,
	 *     confidence_reason: string
	 * }
	 */
	public function analyze( AIProviderInterface $provider, string $system_prompt, string $submission_text ): array {
		$raw  = $provider->generate( $system_prompt, PromptBuilder::build_analysis_prompt( $submission_text ) );
		$data = $this->parse_response( $raw );

		$summary = trim( (string) ( $data['summary'] ?? '' ) );
		$reply   = trim( (string) ( $data['suggested_reply'] ?? '' ) );

		if ( '' === $summary && '' === $reply ) {
			throw new ProviderException(
				esc_html__( 'The AI response did not contain a usable summary or suggested reply.', 'shaikat-ai-inbox-for-contact-form-7' )
			);
		}

		return array(
			'summary'           => $summary,
			'suggested_reply'   => $reply,
			'category'          => ClassificationService::normalize_category( (string) ( $data['category'] ?? '' ) ),
			'priority'          => ClassificationService::normalize_priority( (string) ( $data['priority'] ?? '' ) ),
			'confidence'        => ConfidenceService::normalize( $data['confidence'] ?? 0 ),
			'confidence_reason' => trim( (string) ( $data['reasoning'] ?? '' ) ),
		);
	}

	/**
	 * Parses the model's raw text response into an associative array.
	 *
	 * Strips a leading/trailing markdown code fence if the model wrapped
	 * its JSON in one despite being asked not to — this is common enough
	 * across providers to handle defensively rather than fail on it.
	 *
	 * @param string $raw Raw text returned by the provider.
	 *
	 * @throws ProviderException If the response is not valid JSON.
	 *
	 * @return array<string, mixed>
	 */
	private function parse_response( string $raw ): array {
		$cleaned = trim( $raw );
		$cleaned = (string) preg_replace( '/^```(?:json)?\s*/i', '', $cleaned );
		$cleaned = (string) preg_replace( '/\s*```$/', '', trim( $cleaned ) );

		$decoded = json_decode( trim( $cleaned ), true );

		if ( ! is_array( $decoded ) ) {
			throw new ProviderException(
				esc_html__( 'The AI response was not valid JSON.', 'shaikat-ai-inbox-for-contact-form-7' )
			);
		}

		return $decoded;
	}
}
