<?php
/**
 * OpenRouter provider implementation.
 *
 * @package CF7AIC\AI
 */

namespace CF7AIC\AI;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OpenRouterProvider
 *
 * Talks to the OpenRouter API, which exposes an OpenAI-compatible chat
 * completions endpoint in front of many underlying models.
 *
 * @link https://openrouter.ai/docs
 */
final class OpenRouterProvider extends AbstractProvider {

	/**
	 * Base API URL.
	 *
	 * @var string
	 */
	private const API_BASE = 'https://openrouter.ai/api/v1';

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
		return 'openrouter';
	}

	/**
	 * {@inheritDoc}
	 *
	 * OpenRouter's key-info endpoint validates the API key itself; it has
	 * no per-model lookup, so unlike the other providers this cannot also
	 * confirm the configured model exists.
	 */
	public function test_connection(): array {
		try {
			$this->require_configured();
		} catch ( ProviderException $e ) {
			return $this->failure( $e->getMessage() );
		}

		try {
			$result = $this->request(
				self::API_BASE . '/auth/key',
				array(
					'method'  => 'GET',
					'headers' => $this->headers(),
				)
			);
		} catch ( ProviderException $e ) {
			return $this->failure( $e->getMessage() );
		}

		if ( 200 === $result['code'] ) {
			return $this->success(
				__( 'API key is valid. Model availability is not verified by this test — double-check the model slug on the Help tab.', 'shaikat-ai-inbox-for-contact-form-7' )
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
	 * @throws ProviderException If the request fails or OpenRouter returns an error.
	 */
	public function generate( string $system_prompt, string $user_prompt ): string {
		$this->require_configured();

		$result = $this->request(
			self::API_BASE . '/chat/completions',
			array(
				'method'  => 'POST',
				'headers' => $this->headers(),
				'body'    => wp_json_encode(
					array(
						'model'      => $this->model,
						'messages'   => array(
							array(
								'role'    => 'system',
								'content' => $system_prompt,
							),
							array(
								'role'    => 'user',
								'content' => $user_prompt,
							),
						),
						'max_tokens' => self::MAX_TOKENS,
					)
				),
			)
		);

		if ( 200 !== $result['code'] ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- escaped once, at its point of display (Admin\Notices::render() or JSON consumed via textContent), not raw HTML here.
			throw new ProviderException( $this->extract_error_message( $result['body'], $result['code'] ) );
		}

		$content = $result['body']['choices'][0]['message']['content'] ?? '';

		if ( ! is_string( $content ) || '' === trim( $content ) ) {
			throw new ProviderException(
				esc_html__( 'OpenRouter returned an empty response.', 'shaikat-ai-inbox-for-contact-form-7' )
			);
		}

		return trim( $content );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws ProviderException If the request fails or OpenRouter returns an error.
	 */
	public function list_models(): array {
		$this->require_api_key();

		$result = $this->request(
			self::API_BASE . '/models',
			array(
				'method'  => 'GET',
				'headers' => $this->headers(),
			)
		);

		if ( 200 !== $result['code'] ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- escaped once, at its point of display (Admin\Notices::render() or JSON consumed via textContent), not raw HTML here.
			throw new ProviderException( $this->extract_error_message( $result['body'], $result['code'] ) );
		}

		$data = $result['body']['data'] ?? array();

		if ( ! is_array( $data ) ) {
			return array();
		}

		$models = array();

		foreach ( $data as $model ) {
			$id = (string) ( $model['id'] ?? '' );

			if ( '' === $id ) {
				continue;
			}

			$models[] = array(
				'id'    => $id,
				'label' => (string) ( $model['name'] ?? $id ),
			);
		}

		return $models;
	}

	/**
	 * Returns the standard request headers for OpenRouter.
	 *
	 * @return array<string, string>
	 */
	private function headers(): array {
		return array(
			'Authorization' => 'Bearer ' . $this->api_key,
			'Content-Type'  => 'application/json',
		);
	}
}
