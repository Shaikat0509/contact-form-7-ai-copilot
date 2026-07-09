<?php
/**
 * Central read/write access point for all plugin settings.
 *
 * @package CF7AIC\Settings
 */

namespace CF7AIC\Settings;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CF7AIC\Helpers\Encryption;

/**
 * Class Repository
 *
 * Owns the shape, defaults, and sanitization rules for every settings
 * group. Each group is stored as a single serialized option so reading a
 * whole tab's worth of settings costs one `get_option()` call.
 */
final class Repository {

	/**
	 * Option name for general settings.
	 *
	 * @var string
	 */
	private const OPTION_GENERAL = 'cf7aic_general';

	/**
	 * Option name for AI provider settings.
	 *
	 * @var string
	 */
	private const OPTION_PROVIDER = 'cf7aic_provider';

	/**
	 * Option name for the system prompt.
	 *
	 * @var string
	 */
	private const OPTION_PROMPT = 'cf7aic_prompt';

	/**
	 * Default system prompt, used when none has been configured and when
	 * the admin clicks "Reset" on the Prompt tab.
	 *
	 * @var string
	 */
	public const DEFAULT_SYSTEM_PROMPT = 'You are a professional customer support assistant.';

	/**
	 * Maximum accepted length, in characters, for the system prompt.
	 *
	 * @var int
	 */
	public const PROMPT_MAX_LENGTH = 2000;

	/**
	 * Supported AI providers, keyed by their internal slug.
	 *
	 * Single source of truth for the AI Provider tab's dropdown and for
	 * the provider factory introduced in a later phase.
	 *
	 * @var array<string, string>
	 */
	public const PROVIDERS = array(
		'openai'     => 'OpenAI',
		'anthropic'  => 'Anthropic',
		'gemini'     => 'Google Gemini',
		'openrouter' => 'OpenRouter',
	);

	/**
	 * Returns the general settings, merged with defaults.
	 *
	 * There is deliberately only one on/off switch (`enabled`) — since AI
	 * analysis is one combined request that always produces the summary,
	 * suggested reply, and classification together, there is no
	 * independent "reply only" or "summary only" mode to toggle.
	 *
	 * @return array{enabled: bool, form_id: int}
	 */
	public function get_general(): array {
		$defaults = array(
			'enabled' => false,
			'form_id' => 0,
		);

		$stored = get_option( self::OPTION_GENERAL, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return array_merge( $defaults, $stored );
	}

	/**
	 * Validates and saves the general settings.
	 *
	 * @param array{enabled: bool, form_id: int} $input Raw input.
	 *
	 * @return void
	 */
	public function save_general( array $input ): void {
		$form_id = absint( $input['form_id'] ?? 0 );

		// A form can only be selected if it actually exists as a CF7 form.
		if ( $form_id > 0 && 'wpcf7_contact_form' !== get_post_type( $form_id ) ) {
			$form_id = 0;
		}

		$settings = array(
			'enabled' => ! empty( $input['enabled'] ),
			'form_id' => $form_id,
		);

		update_option( self::OPTION_GENERAL, $settings, true );
	}

	/**
	 * Returns the AI provider settings, merged with defaults.
	 *
	 * The API key is returned already decrypted for internal use (e.g. by
	 * the AI provider classes). Never echo this value to the page — use
	 * {@see self::get_masked_api_key()} for display.
	 *
	 * `last_tested_at`/`last_test_success` reflect the last time the admin
	 * clicked "Test Connection" — they are never updated by a background
	 * or automatic check, so displaying them never costs an API call.
	 *
	 * @return array{provider: string, api_key: string, model: string, last_tested_at: string, last_test_success: bool|null}
	 */
	public function get_provider(): array {
		$defaults = array(
			'provider'          => 'openai',
			'api_key'           => '',
			'model'             => '',
			'last_tested_at'    => '',
			'last_test_success' => null,
		);

		$stored = get_option( self::OPTION_PROVIDER, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$settings = array_merge( $defaults, $stored );

		$settings['api_key'] = Encryption::decrypt( (string) $settings['api_key'] );

		return $settings;
	}

	/**
	 * Returns a display-safe, masked representation of the stored API key.
	 *
	 * @return string Empty string if no key is stored, otherwise a fixed
	 *                masked placeholder that never reveals key material.
	 */
	public function get_masked_api_key(): string {
		$settings = $this->get_provider();

		if ( '' === $settings['api_key'] ) {
			return '';
		}

		return str_repeat( '•', 20 ) . substr( $settings['api_key'], -4 );
	}

	/**
	 * Validates and saves the AI provider settings.
	 *
	 * @param array{provider: string, api_key: string, model: string} $input Raw input. An empty
	 *                                                                       `api_key` leaves the
	 *                                                                       previously stored key
	 *                                                                       untouched, so re-saving
	 *                                                                       the model or provider
	 *                                                                       never wipes it.
	 *
	 * @return void
	 */
	public function save_provider( array $input ): void {
		$provider = isset( $input['provider'] ) ? (string) $input['provider'] : '';
		if ( ! array_key_exists( $provider, self::PROVIDERS ) ) {
			$provider = 'openai';
		}

		$model    = isset( $input['model'] ) ? trim( (string) $input['model'] ) : '';
		$new_key  = isset( $input['api_key'] ) ? trim( (string) $input['api_key'] ) : '';
		$existing = $this->get_provider();
		$api_key  = '' === $new_key ? $existing['api_key'] : $new_key;

		$settings = array(
			'provider'          => $provider,
			'api_key'           => Encryption::encrypt( $api_key ),
			'model'             => $model,
			'last_tested_at'    => $existing['last_tested_at'],
			'last_test_success' => $existing['last_test_success'],
		);

		update_option( self::OPTION_PROVIDER, $settings, true );
	}

	/**
	 * Records the outcome of a "Test Connection" click. Called only from
	 * the AJAX handler that runs the test — never automatically — so
	 * displaying this data elsewhere never triggers a live API call.
	 *
	 * @param bool $success Whether the test succeeded.
	 *
	 * @return void
	 */
	public function record_provider_test( bool $success ): void {
		$settings                      = $this->get_provider();
		$settings['api_key']           = Encryption::encrypt( $settings['api_key'] );
		$settings['last_tested_at']    = current_time( 'mysql' );
		$settings['last_test_success'] = $success;

		update_option( self::OPTION_PROVIDER, $settings, true );
	}

	/**
	 * Returns whether an API key has been configured.
	 *
	 * @return bool
	 */
	public function has_api_key(): bool {
		return '' !== $this->get_provider()['api_key'];
	}

	/**
	 * Returns the prompt settings, merged with defaults.
	 *
	 * @return array{system_prompt: string}
	 */
	public function get_prompt(): array {
		$stored = get_option( self::OPTION_PROMPT, array() );

		if ( ! is_array( $stored ) || empty( $stored['system_prompt'] ) ) {
			return array( 'system_prompt' => self::DEFAULT_SYSTEM_PROMPT );
		}

		return array( 'system_prompt' => (string) $stored['system_prompt'] );
	}

	/**
	 * Validates and saves the system prompt.
	 *
	 * @param array{system_prompt: string} $input Raw input.
	 *
	 * @return void
	 */
	public function save_prompt( array $input ): void {
		$prompt = isset( $input['system_prompt'] ) ? trim( (string) $input['system_prompt'] ) : '';

		if ( '' === $prompt ) {
			$prompt = self::DEFAULT_SYSTEM_PROMPT;
		}

		if ( function_exists( 'mb_substr' ) ) {
			$prompt = mb_substr( $prompt, 0, self::PROMPT_MAX_LENGTH );
		} else {
			$prompt = substr( $prompt, 0, self::PROMPT_MAX_LENGTH );
		}

		update_option( self::OPTION_PROMPT, array( 'system_prompt' => $prompt ), true );
	}
}
