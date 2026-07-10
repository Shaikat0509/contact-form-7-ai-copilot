<?php
/**
 * Renders the full submission review screen.
 *
 * @package CF7AIC\Admin
 */

namespace CF7AIC\Admin;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CF7AIC\CF7\PromptBuilder;
use CF7AIC\Database\SubmissionsRepository;
use CF7AIC\Services\ClassificationService;
use CF7AIC\Services\ConfidenceService;

/**
 * Class SubmissionDetailPage
 *
 * The AI Inbox's review screen: customer info, the original message
 * exactly as submitted, the AI summary, an editable suggested reply, the
 * full AI analysis (category/priority/confidence/reasoning), and the
 * five actions (Save Draft, Send Reply, Mark Reviewed, Archive, Delete).
 * Nothing on this page sends anything without an explicit click — see
 * {@see \CF7AIC\Services\ReplyService}.
 */
final class SubmissionDetailPage {

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
	 * Renders the review screen for one submission.
	 *
	 * @param int $id Row ID.
	 *
	 * @return void
	 */
	public function render_body( int $id ): void {
		$entry = $this->submissions->get( $id );

		$back_url = Menu::url( array( 'section' => 'submissions' ) );

		if ( null === $entry ) {
			?>
			<div class="cf7aic-card">
				<p><?php esc_html_e( 'This submission could not be found. It may have been deleted.', 'shaikat-ai-inbox-for-contact-form-7' ); ?></p>
				<p><a href="<?php echo esc_url( $back_url ); ?>" class="button">&larr; <?php esc_html_e( 'Back to AI Inbox', 'shaikat-ai-inbox-for-contact-form-7' ); ?></a></p>
			</div>
			<?php

			return;
		}

		?>
		<p class="cf7aic-back-link">
			<a href="<?php echo esc_url( $back_url ); ?>">&larr; <?php esc_html_e( 'Back to AI Inbox', 'shaikat-ai-inbox-for-contact-form-7' ); ?></a>
		</p>

		<div class="cf7aic-detail-grid" data-submission-id="<?php echo esc_attr( (string) $entry['id'] ); ?>">
			<div class="cf7aic-detail-main">
				<?php
				$this->render_customer_info( $entry );
				$this->render_original_message( $entry );
				$this->render_ai_summary( $entry );
				$this->render_suggested_reply( $entry );
				?>
			</div>
			<div class="cf7aic-detail-sidebar">
				<?php
				$this->render_ai_analysis( $entry );
				$this->render_actions( $entry );
				?>
			</div>
		</div>

		<?php $this->render_send_confirm_dialog(); ?>
		<?php
	}

	/**
	 * Renders the Customer Information card.
	 *
	 * @param array<string, mixed> $entry Submission row.
	 *
	 * @return void
	 */
	private function render_customer_info( array $entry ): void {
		?>
		<div class="cf7aic-card">
			<h2><?php esc_html_e( 'Customer Information', 'shaikat-ai-inbox-for-contact-form-7' ); ?></h2>
			<dl class="cf7aic-detail-list">
				<dt><?php esc_html_e( 'Name', 'shaikat-ai-inbox-for-contact-form-7' ); ?></dt>
				<dd><?php echo esc_html( '' !== $entry['visitor_name'] ? $entry['visitor_name'] : '—' ); ?></dd>

				<dt><?php esc_html_e( 'Email', 'shaikat-ai-inbox-for-contact-form-7' ); ?></dt>
				<dd><?php echo esc_html( '' !== $entry['visitor_email'] ? $entry['visitor_email'] : '—' ); ?></dd>

				<?php if ( ! empty( $entry['visitor_phone'] ) ) : ?>
					<dt><?php esc_html_e( 'Phone', 'shaikat-ai-inbox-for-contact-form-7' ); ?></dt>
					<dd><?php echo esc_html( $entry['visitor_phone'] ); ?></dd>
				<?php endif; ?>

				<dt><?php esc_html_e( 'Form', 'shaikat-ai-inbox-for-contact-form-7' ); ?></dt>
				<dd><?php echo esc_html( $entry['form_title'] ); ?></dd>

				<dt><?php esc_html_e( 'Submitted', 'shaikat-ai-inbox-for-contact-form-7' ); ?></dt>
				<dd>
					<?php
					echo esc_html(
						wp_date(
							get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
							strtotime( (string) $entry['created_at'] )
						)
					);
					?>
				</dd>
			</dl>
		</div>
		<?php
	}

	/**
	 * Renders the Original Message card, exactly as submitted.
	 *
	 * @param array<string, mixed> $entry Submission row.
	 *
	 * @return void
	 */
	private function render_original_message( array $entry ): void {
		?>
		<div class="cf7aic-card">
			<h2><?php esc_html_e( 'Original Message', 'shaikat-ai-inbox-for-contact-form-7' ); ?></h2>
			<dl class="cf7aic-detail-list">
				<?php foreach ( $entry['submitted_data'] as $key => $value ) : ?>
					<?php
					if ( is_array( $value ) ) {
						$value = implode( ', ', array_map( 'strval', $value ) );
					}

					$value = trim( (string) $value );

					if ( '' === $value ) {
						continue;
					}
					?>
					<dt><?php echo esc_html( PromptBuilder::prettify_field_name( (string) $key ) ); ?></dt>
					<dd><?php echo wp_kses_post( nl2br( esc_html( $value ) ) ); ?></dd>
				<?php endforeach; ?>
			</dl>
		</div>
		<?php
	}

	/**
	 * Renders the AI Summary card, if a summary was generated.
	 *
	 * @param array<string, mixed> $entry Submission row.
	 *
	 * @return void
	 */
	private function render_ai_summary( array $entry ): void {
		if ( empty( $entry['ai_summary'] ) ) {
			return;
		}
		?>
		<div class="cf7aic-card">
			<h2><?php esc_html_e( 'AI Summary', 'shaikat-ai-inbox-for-contact-form-7' ); ?></h2>
			<p class="cf7aic-detail-text"><?php echo esc_html( $entry['ai_summary'] ); ?></p>
		</div>
		<?php
	}

	/**
	 * Renders the editable AI Suggested Reply card, with Save Draft and
	 * Send Reply.
	 *
	 * @param array<string, mixed> $entry Submission row.
	 *
	 * @return void
	 */
	private function render_suggested_reply( array $entry ): void {
		$has_email = ! empty( $entry['visitor_email'] );
		?>
		<div class="cf7aic-card">
			<h2><?php esc_html_e( 'AI Suggested Reply', 'shaikat-ai-inbox-for-contact-form-7' ); ?></h2>

			<?php if ( ! $has_email ) : ?>
				<div class="notice notice-warning inline">
					<p><?php esc_html_e( 'No visitor email address was found on this submission, so a reply cannot be sent from here.', 'shaikat-ai-inbox-for-contact-form-7' ); ?></p>
				</div>
			<?php endif; ?>

			<textarea
				id="cf7aic-reply-textarea"
				rows="10"
				class="large-text"
			><?php echo esc_textarea( (string) ( $entry['ai_reply'] ?? '' ) ); ?></textarea>

			<p class="cf7aic-actions-row">
				<button type="button" id="cf7aic-save-draft" class="button"><?php esc_html_e( 'Save Draft', 'shaikat-ai-inbox-for-contact-form-7' ); ?></button>
				<button
					type="button"
					id="cf7aic-send-reply"
					class="button button-primary"
					<?php disabled( ! $has_email ); ?>
				>
					<?php esc_html_e( 'Send Reply', 'shaikat-ai-inbox-for-contact-form-7' ); ?>
				</button>
				<span id="cf7aic-reply-status" class="cf7aic-test-result" role="status" aria-live="polite"></span>
			</p>

			<?php if ( SubmissionsRepository::STATUS_REPLIED === $entry['status'] && ! empty( $entry['reply_sent_at'] ) ) : ?>
				<p class="description">
					<?php
					printf(
						/* translators: %s: date/time the reply was sent */
						esc_html__( 'Sent on %s', 'shaikat-ai-inbox-for-contact-form-7' ),
						esc_html(
							wp_date(
								get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
								strtotime( (string) $entry['reply_sent_at'] )
							)
						)
					);
					?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Renders the AI Analysis card (category, priority, confidence, reasoning).
	 *
	 * @param array<string, mixed> $entry Submission row.
	 *
	 * @return void
	 */
	private function render_ai_analysis( array $entry ): void {
		?>
		<div class="cf7aic-card">
			<h2><?php esc_html_e( 'AI Analysis', 'shaikat-ai-inbox-for-contact-form-7' ); ?></h2>

			<?php if ( '' === $entry['category'] && '' === $entry['priority'] && null === $entry['confidence'] ) : ?>
				<p class="description"><?php esc_html_e( 'No AI analysis is available for this submission.', 'shaikat-ai-inbox-for-contact-form-7' ); ?></p>
				<?php if ( ! empty( $entry['error_message'] ) ) : ?>
					<p class="cf7aic-detail-text cf7aic-detail-text--error"><?php echo esc_html( $entry['error_message'] ); ?></p>
				<?php endif; ?>
			<?php else : ?>
				<dl class="cf7aic-detail-list">
					<dt><?php esc_html_e( 'Category', 'shaikat-ai-inbox-for-contact-form-7' ); ?></dt>
					<dd><?php echo esc_html( ClassificationService::category_label( $entry['category'] ) ); ?></dd>

					<dt><?php esc_html_e( 'Priority', 'shaikat-ai-inbox-for-contact-form-7' ); ?></dt>
					<dd>
						<span class="cf7aic-badge cf7aic-badge--priority-<?php echo esc_attr( $entry['priority'] ); ?>">
							<?php echo esc_html( ClassificationService::priority_label( $entry['priority'] ) ); ?>
						</span>
					</dd>

					<dt><?php esc_html_e( 'Confidence', 'shaikat-ai-inbox-for-contact-form-7' ); ?></dt>
					<dd>
						<?php echo esc_html( (string) $entry['confidence'] ); ?>%
						<?php if ( ConfidenceService::is_low( (int) $entry['confidence'] ) ) : ?>
							<span class="cf7aic-badge cf7aic-badge--low-confidence">
								<?php esc_html_e( 'Low Confidence — human review suggested', 'shaikat-ai-inbox-for-contact-form-7' ); ?>
							</span>
						<?php endif; ?>
					</dd>
				</dl>

				<?php if ( ! empty( $entry['confidence_reason'] ) ) : ?>
					<h3><?php esc_html_e( 'Reasoning', 'shaikat-ai-inbox-for-contact-form-7' ); ?></h3>
					<p class="cf7aic-detail-text"><?php echo esc_html( $entry['confidence_reason'] ); ?></p>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Renders the Actions card (Mark Reviewed, Archive, Delete) and the
	 * review/reply audit trail.
	 *
	 * @param array<string, mixed> $entry Submission row.
	 *
	 * @return void
	 */
	private function render_actions( array $entry ): void {
		?>
		<div class="cf7aic-card">
			<h2><?php esc_html_e( 'Actions', 'shaikat-ai-inbox-for-contact-form-7' ); ?></h2>
			<p class="cf7aic-actions-column">
				<button
					type="button"
					id="cf7aic-mark-reviewed"
					class="button"
					<?php disabled( SubmissionsRepository::STATUS_NEW !== $entry['status'] ); ?>
				>
					<?php esc_html_e( 'Mark Reviewed', 'shaikat-ai-inbox-for-contact-form-7' ); ?>
				</button>
				<button
					type="button"
					id="cf7aic-archive"
					class="button"
					<?php disabled( SubmissionsRepository::STATUS_ARCHIVED === $entry['status'] ); ?>
				>
					<?php esc_html_e( 'Archive', 'shaikat-ai-inbox-for-contact-form-7' ); ?>
				</button>
				<button type="button" id="cf7aic-delete" class="button button-link-delete">
					<?php esc_html_e( 'Delete', 'shaikat-ai-inbox-for-contact-form-7' ); ?>
				</button>
			</p>

			<?php if ( ! empty( $entry['reviewed_by'] ) ) : ?>
				<p class="description">
					<?php
					$user = get_userdata( (int) $entry['reviewed_by'] );
					printf(
						/* translators: 1: user display name, 2: date reviewed */
						esc_html__( 'Reviewed by %1$s on %2$s', 'shaikat-ai-inbox-for-contact-form-7' ),
						esc_html( $user ? $user->display_name : __( 'Unknown user', 'shaikat-ai-inbox-for-contact-form-7' ) ),
						esc_html( wp_date( get_option( 'date_format' ), strtotime( (string) $entry['reviewed_at'] ) ) )
					);
					?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Renders the "Send Reply?" confirmation dialog shell (native
	 * `<dialog>`, populated/opened entirely by JavaScript).
	 *
	 * @return void
	 */
	private function render_send_confirm_dialog(): void {
		?>
		<dialog id="cf7aic-send-confirm-dialog" class="cf7aic-dialog cf7aic-dialog--small">
			<form method="dialog" class="cf7aic-dialog__form">
				<div class="cf7aic-dialog__header">
					<h2><?php esc_html_e( 'Send Reply?', 'shaikat-ai-inbox-for-contact-form-7' ); ?></h2>
					<button type="submit" class="cf7aic-dialog__close" aria-label="<?php esc_attr_e( 'Close', 'shaikat-ai-inbox-for-contact-form-7' ); ?>">&times;</button>
				</div>
				<div class="cf7aic-dialog__body">
					<p><?php esc_html_e( 'You are about to send this AI-assisted reply. You may edit it before sending.', 'shaikat-ai-inbox-for-contact-form-7' ); ?></p>
				</div>
				<div class="cf7aic-dialog__footer">
					<button type="submit" class="button"><?php esc_html_e( 'Cancel', 'shaikat-ai-inbox-for-contact-form-7' ); ?></button>
					<button type="button" id="cf7aic-confirm-send" class="button button-primary">
						<?php esc_html_e( 'Send', 'shaikat-ai-inbox-for-contact-form-7' ); ?>
					</button>
				</div>
			</form>
		</dialog>
		<?php
	}
}
