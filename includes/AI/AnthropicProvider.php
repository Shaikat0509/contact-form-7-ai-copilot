<?php
/**
 * Anthropic provider implementation.
 *
 * @package CF7AIC\AI
 */

namespace CF7AIC\AI;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AnthropicProvider
 *
 * Talks to the Anthropic Messages API.
 *
 * @link https://docs.anthropic.com/en/api/messages
 */
final class AnthropicProvider extends AbstractProvider {

	/**
	 * Base API URL.
	 *
	 * @var string
	 */
	private const API_BASE = 'https://api.anthropic.com/v1';

	/**
	 * API version header value required by Anthropic.
	 *
	 * @var string
	 */
	private const API_VERSION = '2023-06-01';

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
		return 'anthropic';
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
				self::API_BASE . '/models/' . rawurlencode( $this->model ),
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
				sprintf(
					/* translators: %s: model identifier */
					__( 'Connected successfully. Model "%s" is available.', 'olmbox-ai-inbox-for-contact-form-7' ),
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
	 * @throws ProviderException If the request fails or Anthropic returns an error.
	 */
	public function generate( string $system_prompt, string $user_prompt ): string {
		$this->require_configured();

		$result = $this->request(
			self::API_BASE . '/messages',
			array(
				'method'  => 'POST',
				'headers' => $this->headers(),
				'body'    => wp_json_encode(
					array(
						'model'      => $this->model,
						'system'     => $system_prompt,
						'messages'   => array(
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

		$content = $result['body']['content'][0]['text'] ?? '';

		if ( ! is_string( $content ) || '' === trim( $content ) ) {
			throw new ProviderException(
				esc_html__( 'Anthropic returned an empty response.', 'olmbox-ai-inbox-for-contact-form-7' )
			);
		}

		return trim( $content );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws ProviderException If the request fails or Anthropic returns an error.
	 */
	public function list_models(): array {
		$this->require_api_key();

		$result = $this->request(
			self::API_BASE . '/models?limit=1000',
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
				'label' => (string) ( $model['display_name'] ?? $id ),
			);
		}

		return $models;
	}

	/**
	 * Returns the standard request headers for Anthropic.
	 *
	 * @return array<string, string>
	 */
	private function headers(): array {
		return array(
			'x-api-key'         => $this->api_key,
			'anthropic-version' => self::API_VERSION,
			'content-type'      => 'application/json',
		);
	}
}
