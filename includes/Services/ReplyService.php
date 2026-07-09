<?php
/**
 * Sends an administrator-approved reply to a visitor.
 *
 * @package CF7AIC\Services
 */

namespace CF7AIC\Services;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CF7AIC\Database\SubmissionsRepository;

/**
 * Class ReplyService
 *
 * The only place in the plugin that ever emails a visitor — and only
 * ever in direct response to an administrator explicitly clicking
 * "Send Reply" in the AI Inbox after reviewing (and optionally editing)
 * the AI's suggestion. Nothing here runs automatically at submission time.
 */
final class ReplyService {

	/**
	 * Submissions log repository.
	 *
	 * @var SubmissionsRepository
	 */
	private SubmissionsRepository $submissions;

	/**
	 * Constructor.
	 *
	 * @param SubmissionsRepository $submissions Submissions log repository.
	 */
	public function __construct( SubmissionsRepository $submissions ) {
		$this->submissions = $submissions;
	}

	/**
	 * Sends a reply for a given submission row and records it.
	 *
	 * @param array<string, mixed> $entry      The full submission row, from
	 *                                         {@see SubmissionsRepository::get()}.
	 * @param string               $reply_text The (possibly edited) reply text to send.
	 * @param int                  $user_id    ID of the administrator sending it.
	 *
	 * @return array{success: bool, message: string}
	 */
	public function send( array $entry, string $reply_text, int $user_id ): array {
		$to = trim( (string) ( $entry['visitor_email'] ?? '' ) );

		if ( '' === $to || ! is_email( $to ) ) {
			return array(
				'success' => false,
				'message' => __( 'This submission has no valid visitor email address to reply to.', 'cf7-ai-copilot' ),
			);
		}

		if ( '' === trim( $reply_text ) ) {
			return array(
				'success' => false,
				'message' => __( 'The reply cannot be empty.', 'cf7-ai-copilot' ),
			);
		}

		$subject = sprintf(
			/* translators: %s: site name */
			__( 'Re: Your message to %s', 'cf7-ai-copilot' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
		);

		$mail_error    = null;
		$capture_error = static function ( \WP_Error $error ) use ( &$mail_error ): void {
			$mail_error = $error->get_error_message();
		};

		add_action( 'wp_mail_failed', $capture_error );
		$sent = wp_mail( $to, $subject, $reply_text, array( 'Content-Type: text/plain; charset=UTF-8' ) );
		remove_action( 'wp_mail_failed', $capture_error );

		if ( ! $sent ) {
			return array(
				'success' => false,
				'message' => $mail_error ?? __( 'wp_mail() reported failure. Check your site\'s outgoing mail / SMTP configuration.', 'cf7-ai-copilot' ),
			);
		}

		$this->submissions->record_reply_sent( (int) $entry['id'], $reply_text, $user_id );

		return array(
			'success' => true,
			'message' => __( 'Reply sent.', 'cf7-ai-copilot' ),
		);
	}
}
