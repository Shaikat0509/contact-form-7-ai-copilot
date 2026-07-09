<?php
/**
 * Common contract every AI provider must implement.
 *
 * @package CF7AIC\Interfaces
 */

namespace CF7AIC\Interfaces;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CF7AIC\AI\ProviderException;

/**
 * Interface AIProviderInterface
 *
 * Implemented once per supported AI service (OpenAI, Anthropic, Gemini,
 * OpenRouter). Adding a new provider in the future means implementing
 * this interface and registering it in `CF7AIC\AI\ProviderFactory` —
 * nothing else in the plugin needs to change.
 */
interface AIProviderInterface {

	/**
	 * Returns this provider's internal slug (matches
	 * `CF7AIC\Settings\Repository::PROVIDERS` keys).
	 *
	 * @return string
	 */
	public function get_slug(): string;

	/**
	 * Verifies the configured API key and model are valid by making the
	 * cheapest possible authenticated request the provider's API offers
	 * (never a billed generation request).
	 *
	 * @return array{success: bool, message: string}
	 */
	public function test_connection(): array;

	/**
	 * Generates text from a system prompt and a user prompt.
	 *
	 * @param string $system_prompt Instructions describing how the AI should behave.
	 * @param string $user_prompt   The content to respond to.
	 *
	 * @return string The generated text.
	 *
	 * @throws ProviderException If the request could not be completed or the
	 *                           provider returned an error.
	 */
	public function generate( string $system_prompt, string $user_prompt ): string;

	/**
	 * Lists the models available to the configured API key, for
	 * populating the Model dropdown on the AI Provider tab.
	 *
	 * @return array<int, array{id: string, label: string}>
	 *
	 * @throws ProviderException If the request could not be completed or the
	 *                           provider returned an error.
	 */
	public function list_models(): array;
}
