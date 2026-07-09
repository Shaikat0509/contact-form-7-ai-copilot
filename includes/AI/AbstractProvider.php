<?php
/**
 * Shared HTTP plumbing for AI provider implementations.
 *
 * @package CF7AIC\AI
 */

namespace CF7AIC\AI;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CF7AIC\Interfaces\AIProviderInterface;

/**
 * Class AbstractProvider
 *
 * Every concrete provider (OpenAI, Anthropic, Gemini, OpenRouter) speaks
 * a slightly different JSON dialect over HTTP, but the transport concerns
 * — timeouts, TLS verification, `WP_Error` handling, JSON decoding, and
 * pulling a human-readable message out of an error response — are
 * identical. This class owns those so each concrete provider only needs
 * to know its own endpoint and payload shape.
 */
abstract class AbstractProvider implements AIProviderInterface {

	/**
	 * The plaintext API key (already decrypted by the caller).
	 *
	 * @var string
	 */
	protected string $api_key;

	/**
	 * The model identifier to use for requests.
	 *
	 * @var string
	 */
	protected string $model;

	/**
	 * Constructor.
	 *
	 * @param string $api_key Plaintext API key.
	 * @param string $model   Model identifier.
	 */
	public function __construct( string $api_key, string $model ) {
		$this->api_key = trim( $api_key );
		$this->model   = trim( $model );
	}

	/**
	 * Ensures an API key has been configured before any network request
	 * is attempted. Used by operations that don't require a model, such
	 * as {@see AIProviderInterface::list_models()}.
	 *
	 * @throws ProviderException If no API key is configured.
	 *
	 * @return void
	 */
	protected function require_api_key(): void {
		if ( '' === $this->api_key ) {
			throw new ProviderException(
				esc_html__( 'No API key has been configured for this provider.', 'cf7-ai-copilot' )
			);
		}
	}

	/**
	 * Ensures an API key and model have been configured before any
	 * network request is attempted.
	 *
	 * @throws ProviderException If either is missing.
	 *
	 * @return void
	 */
	protected function require_configured(): void {
		$this->require_api_key();

		if ( '' === $this->model ) {
			throw new ProviderException(
				esc_html__( 'No model has been configured for this provider.', 'cf7-ai-copilot' )
			);
		}
	}

	/**
	 * Performs an HTTP request and returns a normalized result.
	 *
	 * Only throws on a transport-level failure (DNS, TLS, timeout, etc.).
	 * A non-2xx HTTP status is returned normally so callers — which have
	 * different tolerances for what counts as "success" between a
	 * connection test and a real generation request — can decide how to
	 * react.
	 *
	 * @param string $url  Absolute request URL.
	 * @param array  $args Arguments passed to `wp_remote_request()`. `timeout`
	 *                     defaults to 20 seconds if not provided.
	 *
	 * @throws ProviderException On a transport-level failure.
	 *
	 * @return array{code: int, body: array<string, mixed>, raw: string}
	 */
	protected function request( string $url, array $args ): array {
		$args['timeout']   = $args['timeout'] ?? 20;
		$args['sslverify'] = true;

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			throw new ProviderException(
				sprintf(
					/* translators: %s: underlying transport error message */
					esc_html__( 'Could not reach the AI provider: %s', 'cf7-ai-copilot' ),
					esc_html( $response->get_error_message() )
				)
			);
		}

		$raw_body = wp_remote_retrieve_body( $response );
		$decoded  = json_decode( $raw_body, true );

		return array(
			'code' => (int) wp_remote_retrieve_response_code( $response ),
			'body' => is_array( $decoded ) ? $decoded : array(),
			'raw'  => $raw_body,
		);
	}

	/**
	 * Attempts to pull a human-readable error message out of a decoded
	 * JSON error response, falling back to a generic message with the
	 * HTTP status code.
	 *
	 * @param array<string, mixed> $body Decoded JSON response body.
	 * @param int                  $code HTTP response status code.
	 *
	 * @return string
	 */
	protected function extract_error_message( array $body, int $code ): string {
		$message = $body['error']['message'] ?? $body['error'] ?? $body['message'] ?? null;

		if ( is_string( $message ) && '' !== $message ) {
			return $message;
		}

		return sprintf(
			/* translators: %d: HTTP status code */
			esc_html__( 'The provider returned an unexpected response (HTTP %d).', 'cf7-ai-copilot' ),
			$code
		);
	}

	/**
	 * Builds a successful {@see AIProviderInterface::test_connection()} result.
	 *
	 * @param string $message Success message.
	 *
	 * @return array{success: bool, message: string}
	 */
	protected function success( string $message ): array {
		return array(
			'success' => true,
			'message' => $message,
		);
	}

	/**
	 * Builds a failed {@see AIProviderInterface::test_connection()} result.
	 *
	 * @param string $message Failure message.
	 *
	 * @return array{success: bool, message: string}
	 */
	protected function failure( string $message ): array {
		return array(
			'success' => false,
			'message' => $message,
		);
	}
}
