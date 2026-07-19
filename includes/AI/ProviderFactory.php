<?php
/**
 * Instantiates the configured AI provider.
 *
 * @package CF7AIC\AI
 */

namespace CF7AIC\AI;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CF7AIC\Interfaces\AIProviderInterface;
use CF7AIC\Settings\Repository;

/**
 * Class ProviderFactory
 *
 * The single place that maps a provider slug to its implementing class.
 * Adding a new provider in the future means adding one `case` here (and
 * one new class implementing {@see AIProviderInterface}) — nothing else
 * in the plugin needs to change.
 */
final class ProviderFactory {

	/**
	 * Creates a provider instance for the given slug.
	 *
	 * @param string $provider Provider slug (see {@see Repository::PROVIDERS}).
	 * @param string $api_key  Plaintext API key.
	 * @param string $model    Model identifier.
	 *
	 * @throws ProviderException If the provider slug is not recognized.
	 *
	 * @return AIProviderInterface
	 */
	public static function make( string $provider, string $api_key, string $model ): AIProviderInterface {
		switch ( $provider ) {
			case 'openai':
				return new OpenAIProvider( $api_key, $model );

			case 'anthropic':
				return new AnthropicProvider( $api_key, $model );

			case 'gemini':
				return new GeminiProvider( $api_key, $model );

			case 'openrouter':
				return new OpenRouterProvider( $api_key, $model );

			default:
				throw new ProviderException(
					sprintf(
						/* translators: %s: unrecognized provider slug */
						esc_html__( 'Unknown AI provider: %s', 'olmbox-ai-inbox-for-contact-form-7' ),
						esc_html( $provider )
					)
				);
		}
	}

	/**
	 * Creates a provider instance from the currently saved settings.
	 *
	 * @param Repository $repository Settings repository.
	 *
	 * @throws ProviderException If the saved provider slug is not recognized.
	 *
	 * @return AIProviderInterface
	 */
	public static function make_from_settings( Repository $repository ): AIProviderInterface {
		$settings = $repository->get_provider();

		return self::make( $settings['provider'], $settings['api_key'], $settings['model'] );
	}
}
