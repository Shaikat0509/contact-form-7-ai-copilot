<?php
/**
 * Google Gemini provider implementation.
 *
 * @package CF7AIC\AI
 */

namespace CF7AIC\AI;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GeminiProvider
 *
 * Talks to the Google Gemini `generateContent` API.
 *
 * @link https://ai.google.dev/api/generate-content
 */
final class GeminiProvider extends AbstractProvider {

	/**
	 * Base API URL.
	 *
	 * @var string
	 */
	private const API_BASE = 'https://generativelanguage.googleapis.com/v1beta';

	/**
	 * Maximum tokens requested per generation.
	 *
	 * @var int
	 */
	private const MAX_TOKENS = 600;

	/**
	 * {@inheritDoc}
	 */
	public function get_slug(): string {
		return 'gemini';
	}

	/**
	 * {@inheritDoc}
	 */
	public function test_connection(): array {
		try {
			$this->require_configured();
		} catch ( ProviderException $e ) {
			return $this->failure( $e->getMessage() );
		}

		try {
			$result = $this->request(
				self::API_BASE . '/models/' . rawurlencode( $this->model ) . '?key=' . rawurlencode( $this->api_key ),
				array( 'method' => 'GET' )
			);
		} catch ( ProviderException $e ) {
			return $this->failure( $e->getMessage() );
		}

		if ( 200 === $result['code'] ) {
			return $this->success(
				sprintf(
					/* translators: %s: model identifier */
					__( 'Connected successfully. Model "%s" is available.', 'cf7-ai-copilot' ),
					$this->model
				)
			);
		}

		return $this->failure( $this->extract_error_message( $result['body'], $result['code'] ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $system_prompt Instructions describing how the AI should behave.
	 * @param string $user_prompt   The content to respond to.
	 *
	 * @throws ProviderException If the request fails or Gemini returns an error.
	 */
	public function generate( string $system_prompt, string $user_prompt ): string {
		$this->require_configured();

		$result = $this->request(
			self::API_BASE . '/models/' . rawurlencode( $this->model ) . ':generateContent?key=' . rawurlencode( $this->api_key ),
			array(
				'method'  => 'POST',
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'contents'          => array(
							array(
								'role'  => 'user',
								'parts' => array( array( 'text' => $user_prompt ) ),
							),
						),
						'systemInstruction' => array(
							'parts' => array( array( 'text' => $system_prompt ) ),
						),
						'generationConfig'  => array(
							'maxOutputTokens' => self::MAX_TOKENS,
						),
					)
				),
			)
		);

		if ( 200 !== $result['code'] ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- escaped once, at its point of display (Admin\Notices::render() or JSON consumed via textContent), not raw HTML here.
			throw new ProviderException( $this->extract_error_message( $result['body'], $result['code'] ) );
		}

		$content = $result['body']['candidates'][0]['content']['parts'][0]['text'] ?? '';

		if ( ! is_string( $content ) || '' === trim( $content ) ) {
			throw new ProviderException(
				esc_html__( 'Gemini returned an empty response.', 'cf7-ai-copilot' )
			);
		}

		return trim( $content );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws ProviderException If the request fails or Gemini returns an error.
	 */
	public function list_models(): array {
		$this->require_api_key();

		$result = $this->request(
			self::API_BASE . '/models?pageSize=1000&key=' . rawurlencode( $this->api_key ),
			array( 'method' => 'GET' )
		);

		if ( 200 !== $result['code'] ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- escaped once, at its point of display (Admin\Notices::render() or JSON consumed via textContent), not raw HTML here.
			throw new ProviderException( $this->extract_error_message( $result['body'], $result['code'] ) );
		}

		$data = $result['body']['models'] ?? array();

		if ( ! is_array( $data ) ) {
			return array();
		}

		$models = array();

		foreach ( $data as $model ) {
			$methods = $model['supportedGenerationMethods'] ?? array();

			if ( ! is_array( $methods ) || ! in_array( 'generateContent', $methods, true ) ) {
				continue;
			}

			$name = (string) ( $model['name'] ?? '' );
			$id   = preg_replace( '#^models/#', '', $name );

			if ( '' === $id ) {
				continue;
			}

			$models[] = array(
				'id'    => $id,
				'label' => (string) ( $model['displayName'] ?? $id ),
			);
		}

		return $models;
	}
}
