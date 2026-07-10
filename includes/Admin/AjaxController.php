<?php
/**
 * Handles the admin page's AJAX requests.
 *
 * @package CF7AIC\Admin
 */

namespace CF7AIC\Admin;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CF7AIC\AI\ProviderException;
use CF7AIC\AI\ProviderFactory;
use CF7AIC\Database\SubmissionsRepository;
use CF7AIC\Services\ReplyService;
use CF7AIC\Settings\Repository;

/**
 * Class AjaxController
 *
 * Every action here is admin-only and nonce-verified:
 *  - Test Connection / List Models: AI Provider tab helpers (see below).
 *  - Send Reply / Save Draft / Mark Reviewed / Archive / Delete: the AI
 *    Inbox review workflow. Send Reply is the only action anywhere in
 *    the plugin that emails a visitor, and only ever in direct response
 *    to this explicit administrator action.
 *
 * Each handler repeats its own capability + nonce check inline (rather
 * than delegating to a shared private method) so the check is always
 * visible in the same scope as the `$_POST` access it protects.
 */
final class AjaxController {

	/**
	 * Shared nonce action name for every AJAX request this controller handles.
	 *
	 * @var string
	 */
	public const NONCE_ACTION = 'cf7aic_admin_ajax';

	/**
	 * Settings repository.
	 *
	 * @var Repository
	 */
	private Repository $repository;

	/**
	 * Submissions log repository.
	 *
	 * @var SubmissionsRepository
	 */
	private SubmissionsRepository $submissions;

	/**
	 * Reply sending service.
	 *
	 * @var ReplyService
	 */
	private ReplyService $reply_service;

	/**
	 * Constructor.
	 *
	 * @param Repository            $repository    Settings repository.
	 * @param SubmissionsRepository $submissions   Submissions log repository.
	 * @param ReplyService          $reply_service Reply sending service.
	 */
	public function __construct( Repository $repository, SubmissionsRepository $submissions, ReplyService $reply_service ) {
		$this->repository    = $repository;
		$this->submissions   = $submissions;
		$this->reply_service = $reply_service;
	}

	/**
	 * Registers WordPress hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_ajax_cf7aic_test_connection', array( $this, 'handle_test_connection' ) );
		add_action( 'wp_ajax_cf7aic_list_models', array( $this, 'handle_list_models' ) );
		add_action( 'wp_ajax_cf7aic_send_reply', array( $this, 'handle_send_reply' ) );
		add_action( 'wp_ajax_cf7aic_save_draft', array( $this, 'handle_save_draft' ) );
		add_action( 'wp_ajax_cf7aic_mark_reviewed', array( $this, 'handle_mark_reviewed' ) );
		add_action( 'wp_ajax_cf7aic_archive', array( $this, 'handle_archive' ) );
		add_action( 'wp_ajax_cf7aic_delete_submission', array( $this, 'handle_delete_submission' ) );
	}

	/**
	 * Handles the "Test Connection" AJAX request.
	 *
	 * @return void
	 */
	public function handle_test_connection(): void {
		if ( ! current_user_can( Menu::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'shaikat-ai-inbox-for-contact-form-7' ) ), 403 );
		}

		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed. Please reload the page and try again.', 'shaikat-ai-inbox-for-contact-form-7' ) ), 403 );
		}

		$provider = isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : '';

		if ( ! array_key_exists( $provider, Repository::PROVIDERS ) ) {
			wp_send_json_error( array( 'message' => __( 'Please choose a valid provider.', 'shaikat-ai-inbox-for-contact-form-7' ) ) );
		}

		$model         = isset( $_POST['model'] ) ? sanitize_text_field( wp_unslash( $_POST['model'] ) ) : '';
		$submitted_key = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';
		$api_key       = $this->resolve_api_key( $submitted_key );

		try {
			$provider_instance = ProviderFactory::make( $provider, $api_key, $model );
		} catch ( ProviderException $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
			return;
		}

		$result = $provider_instance->test_connection();

		$this->repository->record_provider_test( $result['success'] );

		if ( $result['success'] ) {
			wp_send_json_success( array( 'message' => $result['message'] ) );
		}

		wp_send_json_error( array( 'message' => $result['message'] ) );
	}

	/**
	 * Handles the "List Models" AJAX request, used to populate the Model
	 * dropdown on the AI Provider tab once a key is present.
	 *
	 * @return void
	 */
	public function handle_list_models(): void {
		if ( ! current_user_can( Menu::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'shaikat-ai-inbox-for-contact-form-7' ) ), 403 );
		}

		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed. Please reload the page and try again.', 'shaikat-ai-inbox-for-contact-form-7' ) ), 403 );
		}

		$provider = isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : '';

		if ( ! array_key_exists( $provider, Repository::PROVIDERS ) ) {
			wp_send_json_error( array( 'message' => __( 'Please choose a valid provider.', 'shaikat-ai-inbox-for-contact-form-7' ) ) );
		}

		$submitted_key = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';
		$api_key       = $this->resolve_api_key( $submitted_key );

		try {
			$provider_instance = ProviderFactory::make( $provider, $api_key, '' );
			$models            = $provider_instance->list_models();
		} catch ( ProviderException $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
			return;
		}

		if ( empty( $models ) ) {
			wp_send_json_error( array( 'message' => __( 'No models were returned by the provider.', 'shaikat-ai-inbox-for-contact-form-7' ) ) );
		}

		wp_send_json_success( array( 'models' => $models ) );
	}

	/**
	 * Handles the "Send Reply" AJAX request — the only action in the
	 * plugin that emails a visitor, and only in direct response to this
	 * explicit administrator click.
	 *
	 * @return void
	 */
	public function handle_send_reply(): void {
		if ( ! current_user_can( Menu::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'shaikat-ai-inbox-for-contact-form-7' ) ), 403 );
		}

		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed. Please reload the page and try again.', 'shaikat-ai-inbox-for-contact-form-7' ) ), 403 );
		}

		$id    = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$reply = isset( $_POST['reply'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reply'] ) ) : '';

		$entry = $this->submissions->get( $id );

		if ( null === $entry ) {
			wp_send_json_error( array( 'message' => __( 'That submission could not be found.', 'shaikat-ai-inbox-for-contact-form-7' ) ) );
			return;
		}

		$result = $this->reply_service->send( $entry, $reply, get_current_user_id() );

		if ( $result['success'] ) {
			wp_send_json_success( array( 'message' => $result['message'] ) );
		}

		wp_send_json_error( array( 'message' => $result['message'] ) );
	}

	/**
	 * Handles the "Save Draft" AJAX request.
	 *
	 * @return void
	 */
	public function handle_save_draft(): void {
		if ( ! current_user_can( Menu::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'shaikat-ai-inbox-for-contact-form-7' ) ), 403 );
		}

		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed. Please reload the page and try again.', 'shaikat-ai-inbox-for-contact-form-7' ) ), 403 );
		}

		$id    = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$reply = isset( $_POST['reply'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reply'] ) ) : '';

		if ( null === $this->submissions->get( $id ) ) {
			wp_send_json_error( array( 'message' => __( 'That submission could not be found.', 'shaikat-ai-inbox-for-contact-form-7' ) ) );
			return;
		}

		if ( $this->submissions->save_draft_reply( $id, $reply ) ) {
			wp_send_json_success( array( 'message' => __( 'Draft saved.', 'shaikat-ai-inbox-for-contact-form-7' ) ) );
		}

		wp_send_json_error( array( 'message' => __( 'Could not save the draft.', 'shaikat-ai-inbox-for-contact-form-7' ) ) );
	}

	/**
	 * Handles the "Mark Reviewed" AJAX request.
	 *
	 * @return void
	 */
	public function handle_mark_reviewed(): void {
		if ( ! current_user_can( Menu::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'shaikat-ai-inbox-for-contact-form-7' ) ), 403 );
		}

		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed. Please reload the page and try again.', 'shaikat-ai-inbox-for-contact-form-7' ) ), 403 );
		}

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

		if ( null === $this->submissions->get( $id ) ) {
			wp_send_json_error( array( 'message' => __( 'That submission could not be found.', 'shaikat-ai-inbox-for-contact-form-7' ) ) );
			return;
		}

		$this->submissions->mark_reviewed( $id, get_current_user_id() );

		wp_send_json_success( array( 'message' => __( 'Marked as reviewed.', 'shaikat-ai-inbox-for-contact-form-7' ) ) );
	}

	/**
	 * Handles the "Archive" AJAX request.
	 *
	 * @return void
	 */
	public function handle_archive(): void {
		if ( ! current_user_can( Menu::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'shaikat-ai-inbox-for-contact-form-7' ) ), 403 );
		}

		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed. Please reload the page and try again.', 'shaikat-ai-inbox-for-contact-form-7' ) ), 403 );
		}

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

		if ( null === $this->submissions->get( $id ) ) {
			wp_send_json_error( array( 'message' => __( 'That submission could not be found.', 'shaikat-ai-inbox-for-contact-form-7' ) ) );
			return;
		}

		$this->submissions->archive( $id );

		wp_send_json_success( array( 'message' => __( 'Archived.', 'shaikat-ai-inbox-for-contact-form-7' ) ) );
	}

	/**
	 * Handles the "Delete" AJAX request. Permanently removes the row.
	 *
	 * @return void
	 */
	public function handle_delete_submission(): void {
		if ( ! current_user_can( Menu::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'shaikat-ai-inbox-for-contact-form-7' ) ), 403 );
		}

		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed. Please reload the page and try again.', 'shaikat-ai-inbox-for-contact-form-7' ) ), 403 );
		}

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

		if ( null === $this->submissions->get( $id ) ) {
			wp_send_json_error( array( 'message' => __( 'That submission could not be found.', 'shaikat-ai-inbox-for-contact-form-7' ) ) );
			return;
		}

		$this->submissions->delete( $id );

		wp_send_json_success( array( 'message' => __( 'Deleted.', 'shaikat-ai-inbox-for-contact-form-7' ) ) );
	}

	/**
	 * Resolves the API key to use for a request: whatever was submitted in
	 * the form, or if that field was left blank, whatever is already saved
	 * — mirroring the Save button's "don't wipe an unrelated field" behavior.
	 *
	 * @param string $submitted_key The already-sanitized, already-unslashed
	 *                              `api_key` field from the request.
	 *
	 * @return string
	 */
	private function resolve_api_key( string $submitted_key ): string {
		return '' === $submitted_key ? $this->repository->get_provider()['api_key'] : $submitted_key;
	}
}
