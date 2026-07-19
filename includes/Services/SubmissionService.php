<?php
/**
 * Orchestrates AI analysis and storage for one Contact Form 7 submission.
 *
 * @package CF7AIC\Services
 */

namespace CF7AIC\Services;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CF7AIC\AI\ProviderException;
use CF7AIC\AI\ProviderFactory;
use CF7AIC\CF7\PromptBuilder;
use CF7AIC\Database\SubmissionsRepository;
use CF7AIC\Settings\Repository;

/**
 * Class SubmissionService
 *
 * The only business logic Contact Form 7 integration needs: run one AI
 * analysis call and store the result. This class never sends mail — a
 * row is always just logged with `status = new`, and only an explicit
 * "Send Reply" action from the AI Inbox (handled by {@see ReplyService})
 * ever emails the visitor.
 */
final class SubmissionService {

	/**
	 * Settings repository.
	 *
	 * @var Repository
	 */
	private Repository $repository;

	/**
	 * Usage tracker.
	 *
	 * @var UsageTracker
	 */
	private UsageTracker $usage_tracker;

	/**
	 * Submissions log repository.
	 *
	 * @var SubmissionsRepository
	 */
	private SubmissionsRepository $submissions;

	/**
	 * AI analysis service.
	 *
	 * @var AIService
	 */
	private AIService $ai_service;

	/**
	 * Constructor.
	 *
	 * @param Repository            $repository    Settings repository.
	 * @param UsageTracker          $usage_tracker Usage tracker.
	 * @param SubmissionsRepository $submissions   Submissions log repository.
	 * @param AIService             $ai_service    AI analysis service.
	 */
	public function __construct( Repository $repository, UsageTracker $usage_tracker, SubmissionsRepository $submissions, AIService $ai_service ) {
		$this->repository    = $repository;
		$this->usage_tracker = $usage_tracker;
		$this->submissions   = $submissions;
		$this->ai_service    = $ai_service;
	}

	/**
	 * Processes one submission end to end and always records exactly one
	 * row in the Submissions log — whether AI analysis succeeded, failed,
	 * or was never attempted (missing key).
	 *
	 * @param \WPCF7_ContactForm $contact_form The form that was submitted.
	 * @param \WPCF7_Submission  $submission   The current submission.
	 *
	 * @return void
	 */
	public function process( \WPCF7_ContactForm $contact_form, \WPCF7_Submission $submission ): void {
		$provider_settings = $this->repository->get_provider();

		$base = array(
			'form_id'           => $contact_form->id(),
			'form_title'        => $contact_form->title(),
			'visitor_name'      => PromptBuilder::find_visitor_name( $contact_form, $submission ) ?? '',
			'visitor_email'     => PromptBuilder::find_visitor_email( $contact_form, $submission ) ?? '',
			'visitor_phone'     => PromptBuilder::find_visitor_phone( $contact_form, $submission ) ?? '',
			'submitted_data'    => $submission->get_posted_data(),
			'provider'          => $provider_settings['provider'],
			'model'             => $provider_settings['model'],
			'ai_summary'        => null,
			'ai_reply'          => null,
			'category'          => '',
			'priority'          => '',
			'confidence'        => null,
			'confidence_reason' => null,
			'error_message'     => null,
		);

		if ( ! $this->repository->has_api_key() ) {
			$this->submissions->insert(
				array_merge(
					$base,
					array(
						'ai_status'     => SubmissionsRepository::AI_STATUS_NO_API_KEY,
						'error_message' => __( 'No API key has been configured.', 'olmbox-ai-inbox-for-contact-form-7' ),
					)
				)
			);

			return;
		}

		try {
			$provider = ProviderFactory::make(
				$provider_settings['provider'],
				$provider_settings['api_key'],
				$provider_settings['model']
			);

			$analysis = $this->ai_service->analyze(
				$provider,
				$this->repository->get_prompt()['system_prompt'],
				PromptBuilder::format_submission( $contact_form, $submission )
			);

			$this->usage_tracker->increment();

			$this->submissions->insert(
				array_merge(
					$base,
					array(
						'ai_status'         => SubmissionsRepository::AI_STATUS_SUCCESS,
						'ai_summary'        => $analysis['summary'],
						'ai_reply'          => $analysis['suggested_reply'],
						'category'          => $analysis['category'],
						'priority'          => $analysis['priority'],
						'confidence'        => $analysis['confidence'],
						'confidence_reason' => $analysis['confidence_reason'],
					)
				)
			);
		} catch ( ProviderException $e ) {
			$this->log_error( $e, 'analysis' );

			$this->submissions->insert(
				array_merge(
					$base,
					array(
						'ai_status'     => SubmissionsRepository::AI_STATUS_FAILED,
						'error_message' => $e->getMessage(),
					)
				)
			);
		}
	}

	/**
	 * Logs an AI failure without ever interrupting the CF7 submission flow.
	 *
	 * Writes to the PHP error log only when the site owner has explicitly
	 * enabled `WP_DEBUG_LOG` — nothing is written by default. Also fires
	 * an action hook so a future version, or the site owner's own code,
	 * can attach real observability without this plugin needing its own
	 * logging UI.
	 *
	 * @param \Throwable $e       The caught exception.
	 * @param string     $context Short label identifying where the failure occurred.
	 *
	 * @return void
	 */
	private function log_error( \Throwable $e, string $context ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- intentionally gated behind WP_DEBUG_LOG; the free plan has no custom logging UI.
			error_log( sprintf( '[CF7 AI Copilot] %s: %s', $context, $e->getMessage() ) );
		}

		/**
		 * Fires whenever an AI generation step fails.
		 *
		 * @since 1.0.0
		 *
		 * @param \Throwable $e       The caught exception.
		 * @param string     $context Short label identifying where the failure occurred.
		 */
		do_action( 'cf7aic_ai_error', $e, $context );
	}
}
