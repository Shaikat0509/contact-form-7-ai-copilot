<?php
/**
 * Routes a successful Contact Form 7 submission into AI analysis.
 *
 * @package CF7AIC\CF7
 */

namespace CF7AIC\CF7;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CF7AIC\Services\SubmissionService;
use CF7AIC\Settings\Repository;

/**
 * Class SubmissionHandler
 *
 * A thin adapter between Contact Form 7's hooks and {@see SubmissionService}
 * — it holds no business logic of its own. Hooked on `wpcf7_mail_sent`
 * (fired only after CF7 has already sent its own mail successfully),
 * rather than `wpcf7_before_send_mail`, because this plugin no longer
 * needs to run *before* the send: it never mutates CF7's mail properties
 * and never sends anything itself at submission time, so there's nothing
 * to do before CF7's own send completes. Running after also means a
 * submission blocked by another anti-spam plugin never reaches AI
 * analysis at all.
 */
final class SubmissionHandler {

	/**
	 * Settings repository.
	 *
	 * @var Repository
	 */
	private Repository $repository;

	/**
	 * Submission orchestration service.
	 *
	 * @var SubmissionService
	 */
	private SubmissionService $submission_service;

	/**
	 * Constructor.
	 *
	 * @param Repository        $repository         Settings repository.
	 * @param SubmissionService $submission_service Submission orchestration service.
	 */
	public function __construct( Repository $repository, SubmissionService $submission_service ) {
		$this->repository         = $repository;
		$this->submission_service = $submission_service;
	}

	/**
	 * Registers WordPress hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wpcf7_mail_sent', array( $this, 'handle' ) );
	}

	/**
	 * Handles the `wpcf7_mail_sent` action.
	 *
	 * @param \WPCF7_ContactForm $contact_form The form that was submitted.
	 *
	 * @return void
	 */
	public function handle( $contact_form ): void {
		if ( ! $contact_form instanceof \WPCF7_ContactForm ) {
			return;
		}

		$general = $this->repository->get_general();

		if ( ! $general['enabled'] || 0 === $general['form_id'] || $general['form_id'] !== $contact_form->id() ) {
			return;
		}

		$submission = \WPCF7_Submission::get_instance();

		if ( ! $submission instanceof \WPCF7_Submission ) {
			return;
		}

		$this->submission_service->process( $contact_form, $submission );
	}
}
