<?php
/**
 * Handles saving the AI Copilot settings form submissions.
 *
 * @package CF7AIC\Admin
 */

namespace CF7AIC\Admin;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CF7AIC\Settings\Repository;

/**
 * Class SettingsController
 *
 * Receives POSTs from the three editable tabs (General, AI Provider,
 * Prompt) via `admin-post.php`, verifies capability and nonce, sanitizes
 * raw input, delegates persistence to {@see Repository}, and redirects
 * back to the originating tab.
 *
 * Each handler repeats its own capability + nonce check inline (rather
 * than delegating to a shared private method) so the check is always
 * visible in the same scope as the `$_POST` access it protects.
 */
final class SettingsController {

	/**
	 * Settings repository.
	 *
	 * @var Repository
	 */
	private Repository $repository;

	/**
	 * Constructor.
	 *
	 * @param Repository $repository Settings repository.
	 */
	public function __construct( Repository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Registers WordPress hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_post_cf7aic_save_general', array( $this, 'handle_save_general' ) );
		add_action( 'admin_post_cf7aic_save_provider', array( $this, 'handle_save_provider' ) );
		add_action( 'admin_post_cf7aic_save_prompt', array( $this, 'handle_save_prompt' ) );
	}

	/**
	 * Handles the General tab form submission.
	 *
	 * @return void
	 */
	public function handle_save_general(): void {
		if ( ! current_user_can( Menu::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to change these settings.', 'olmbox-ai-inbox-for-contact-form-7' ) );
		}

		check_admin_referer( 'cf7aic_save_general', 'cf7aic_nonce' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- array of integer-like strings; each is passed through absint() below, so wp_unslash() would be a no-op.
		$raw_form_ids = isset( $_POST['form_ids'] ) && is_array( $_POST['form_ids'] ) ? $_POST['form_ids'] : array();

		$this->repository->save_general(
			array(
				'enabled'  => isset( $_POST['enabled'] ),
				'form_ids' => array_map( 'absint', $raw_form_ids ),
			)
		);

		$this->redirect_to( 'general' );
	}

	/**
	 * Handles the AI Provider tab form submission.
	 *
	 * @return void
	 */
	public function handle_save_provider(): void {
		if ( ! current_user_can( Menu::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to change these settings.', 'olmbox-ai-inbox-for-contact-form-7' ) );
		}

		check_admin_referer( 'cf7aic_save_provider', 'cf7aic_nonce' );

		$this->repository->save_provider(
			array(
				'provider' => isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : '',
				'api_key'  => isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '',
				'model'    => isset( $_POST['model'] ) ? sanitize_text_field( wp_unslash( $_POST['model'] ) ) : '',
			)
		);

		$this->redirect_to( 'provider' );
	}

	/**
	 * Handles the Prompt tab form submission.
	 *
	 * @return void
	 */
	public function handle_save_prompt(): void {
		if ( ! current_user_can( Menu::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to change these settings.', 'olmbox-ai-inbox-for-contact-form-7' ) );
		}

		check_admin_referer( 'cf7aic_save_prompt', 'cf7aic_nonce' );

		$this->repository->save_prompt(
			array(
				'system_prompt' => isset( $_POST['system_prompt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['system_prompt'] ) ) : '',
			)
		);

		$this->redirect_to( 'prompt' );
	}

	/**
	 * Redirects back to the given settings tab with a success flag.
	 *
	 * @param string $tab Tab slug.
	 *
	 * @return void
	 */
	private function redirect_to( string $tab ): void {
		wp_safe_redirect(
			Menu::url(
				array(
					'section' => 'settings',
					'tab'     => $tab,
					'updated' => '1',
				)
			)
		);
		exit;
	}
}
