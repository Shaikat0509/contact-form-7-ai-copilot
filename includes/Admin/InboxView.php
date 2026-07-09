<?php
/**
 * Renders the AI Inbox: a filterable, badge-based submissions list.
 *
 * @package CF7AIC\Admin
 */

namespace CF7AIC\Admin;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CF7AIC\Database\SubmissionsRepository;
use CF7AIC\Services\ClassificationService;
use CF7AIC\Services\ConfidenceService;

/**
 * Class InboxView
 *
 * Lists every submission the plugin has processed for the configured
 * form (most recent first), with status/priority/category/confidence
 * badges and filters. Clicking a row opens {@see SubmissionDetailPage} —
 * a full review screen, not a popup, since the review workflow has too
 * much content (customer info, original message, AI analysis, an
 * editable reply, five actions) for a modal to hold comfortably.
 */
final class InboxView {

	/**
	 * Rows shown per page.
	 *
	 * @var int
	 */
	private const PER_PAGE = 20;

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
	 * Renders the Inbox list, its filter bar, and pagination.
	 *
	 * @return void
	 */
	public function render_body(): void {
		$filters = $this->get_current_filters();
		$page    = $this->get_current_page();

		$data  = $this->submissions->get_paginated( $page, self::PER_PAGE, $filters );
		$items = $data['items'];

		$this->render_filter_bar( $filters );
		?>
		<div class="cf7aic-card">
			<?php if ( empty( $items ) ) : ?>
				<div class="cf7aic-empty-state">
					<span class="dashicons dashicons-email-alt" aria-hidden="true"></span>
					<p>
						<?php if ( $this->has_active_filters( $filters ) ) : ?>
							<?php esc_html_e( 'No submissions match these filters.', 'cf7-ai-copilot' ); ?>
						<?php else : ?>
							<?php esc_html_e( 'No submissions yet. Once AI Copilot is enabled for your chosen form, they will appear here for review.', 'cf7-ai-copilot' ); ?>
						<?php endif; ?>
					</p>
				</div>
			<?php else : ?>
				<table class="cf7aic-table widefat">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Status', 'cf7-ai-copilot' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Name', 'cf7-ai-copilot' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Email', 'cf7-ai-copilot' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Form', 'cf7-ai-copilot' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Date', 'cf7-ai-copilot' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Priority', 'cf7-ai-copilot' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Category', 'cf7-ai-copilot' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Confidence', 'cf7-ai-copilot' ); ?></th>
							<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'cf7-ai-copilot' ); ?></span></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $items as $item ) : ?>
							<?php
							$detail_url = Menu::url(
								array(
									'section' => 'submissions',
									'id'      => $item['id'],
								)
							);
							?>
							<tr>
								<td data-label="<?php esc_attr_e( 'Status', 'cf7-ai-copilot' ); ?>">
									<?php $this->render_status_badge( $item['status'], $item['ai_status'] ); ?>
								</td>
								<td data-label="<?php esc_attr_e( 'Name', 'cf7-ai-copilot' ); ?>">
									<a href="<?php echo esc_url( $detail_url ); ?>">
										<?php echo esc_html( '' !== $item['visitor_name'] ? $item['visitor_name'] : '—' ); ?>
									</a>
								</td>
								<td data-label="<?php esc_attr_e( 'Email', 'cf7-ai-copilot' ); ?>"><?php echo esc_html( '' !== $item['visitor_email'] ? $item['visitor_email'] : '—' ); ?></td>
								<td data-label="<?php esc_attr_e( 'Form', 'cf7-ai-copilot' ); ?>"><?php echo esc_html( $item['form_title'] ); ?></td>
								<td data-label="<?php esc_attr_e( 'Date', 'cf7-ai-copilot' ); ?>">
									<?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( (string) $item['created_at'] ) ) ); ?>
								</td>
								<td data-label="<?php esc_attr_e( 'Priority', 'cf7-ai-copilot' ); ?>">
									<?php if ( '' !== $item['priority'] ) : ?>
										<span class="cf7aic-badge cf7aic-badge--priority-<?php echo esc_attr( $item['priority'] ); ?>">
											<?php echo esc_html( ClassificationService::priority_label( $item['priority'] ) ); ?>
										</span>
									<?php else : ?>
										&mdash;
									<?php endif; ?>
								</td>
								<td data-label="<?php esc_attr_e( 'Category', 'cf7-ai-copilot' ); ?>">
									<?php echo '' !== $item['category'] ? esc_html( ClassificationService::category_label( $item['category'] ) ) : '&mdash;'; ?>
								</td>
								<td data-label="<?php esc_attr_e( 'Confidence', 'cf7-ai-copilot' ); ?>">
									<?php $this->render_confidence( $item['confidence'] ); ?>
								</td>
								<td data-label="">
									<a href="<?php echo esc_url( $detail_url ); ?>" class="button">
										<?php esc_html_e( 'Review', 'cf7-ai-copilot' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<?php $this->render_pagination( $page, $data['total_pages'], $filters ); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Renders the status filter bar (status, priority, category, date
	 * range, search).
	 *
	 * @param array<string, string> $filters Currently active filters.
	 *
	 * @return void
	 */
	private function render_filter_bar( array $filters ): void {
		?>
		<form method="get" class="cf7aic-filter-bar">
			<input type="hidden" name="page" value="<?php echo esc_attr( Menu::PAGE_SLUG ); ?>" />
			<input type="hidden" name="section" value="submissions" />

			<select name="status" aria-label="<?php esc_attr_e( 'Filter by status', 'cf7-ai-copilot' ); ?>">
				<option value=""><?php esc_html_e( 'All statuses', 'cf7-ai-copilot' ); ?></option>
				<?php foreach ( $this->status_labels() as $slug => $label ) : ?>
					<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $filters['status'], $slug ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>

			<select name="priority" aria-label="<?php esc_attr_e( 'Filter by priority', 'cf7-ai-copilot' ); ?>">
				<option value=""><?php esc_html_e( 'All priorities', 'cf7-ai-copilot' ); ?></option>
				<?php foreach ( ClassificationService::PRIORITIES as $slug => $label ) : ?>
					<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $filters['priority'], $slug ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>

			<select name="category" aria-label="<?php esc_attr_e( 'Filter by category', 'cf7-ai-copilot' ); ?>">
				<option value=""><?php esc_html_e( 'All categories', 'cf7-ai-copilot' ); ?></option>
				<?php foreach ( ClassificationService::CATEGORIES as $slug => $label ) : ?>
					<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $filters['category'], $slug ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>

			<input
				type="date"
				name="date_from"
				value="<?php echo esc_attr( $filters['date_from'] ); ?>"
				aria-label="<?php esc_attr_e( 'From date', 'cf7-ai-copilot' ); ?>"
			/>
			<input
				type="date"
				name="date_to"
				value="<?php echo esc_attr( $filters['date_to'] ); ?>"
				aria-label="<?php esc_attr_e( 'To date', 'cf7-ai-copilot' ); ?>"
			/>

			<input
				type="search"
				name="search"
				value="<?php echo esc_attr( $filters['search'] ); ?>"
				placeholder="<?php esc_attr_e( 'Search name, email, message…', 'cf7-ai-copilot' ); ?>"
				aria-label="<?php esc_attr_e( 'Search submissions', 'cf7-ai-copilot' ); ?>"
			/>

			<button type="submit" class="button"><?php esc_html_e( 'Filter', 'cf7-ai-copilot' ); ?></button>
			<?php if ( $this->has_active_filters( $filters ) ) : ?>
				<a href="<?php echo esc_url( Menu::url( array( 'section' => 'submissions' ) ) ); ?>" class="button-link">
					<?php esc_html_e( 'Clear filters', 'cf7-ai-copilot' ); ?>
				</a>
			<?php endif; ?>
		</form>
		<?php
	}

	/**
	 * Renders pagination links, preserving active filters.
	 *
	 * @param int                   $current     Current page number.
	 * @param int                   $total_pages Total number of pages.
	 * @param array<string, string> $filters     Currently active filters.
	 *
	 * @return void
	 */
	private function render_pagination( int $current, int $total_pages, array $filters ): void {
		if ( $total_pages <= 1 ) {
			return;
		}
		?>
		<nav class="cf7aic-pagination" aria-label="<?php esc_attr_e( 'Inbox pagination', 'cf7-ai-copilot' ); ?>">
			<?php
			for ( $i = 1; $i <= $total_pages; $i++ ) :
				$page_url = Menu::url(
					array_merge(
						array_filter( $filters ),
						array(
							'section' => 'submissions',
							'paged'   => $i,
						)
					)
				);
				?>
				<a
					href="<?php echo esc_url( $page_url ); ?>"
					class="cf7aic-pagination__link <?php echo $i === $current ? 'cf7aic-pagination__link--active' : ''; ?>"
				>
					<?php echo esc_html( (string) $i ); ?>
				</a>
			<?php endfor; ?>
		</nav>
		<?php
	}

	/**
	 * Renders the workflow status badge, falling back to describing the
	 * AI outcome when a row was never picked up for review (no key, quota
	 * reached, generation failed).
	 *
	 * @param string $status    Workflow status.
	 * @param string $ai_status AI generation outcome.
	 *
	 * @return void
	 */
	private function render_status_badge( string $status, string $ai_status ): void {
		if ( SubmissionsRepository::STATUS_NEW === $status && SubmissionsRepository::AI_STATUS_SUCCESS !== $ai_status ) {
			printf(
				'<span class="cf7aic-badge cf7aic-badge--%1$s">%2$s</span>',
				esc_attr( $ai_status ),
				esc_html( $this->ai_status_labels()[ $ai_status ] ?? $ai_status )
			);

			return;
		}

		printf(
			'<span class="cf7aic-badge cf7aic-badge--status-%1$s">%2$s</span>',
			esc_attr( $status ),
			esc_html( $this->status_labels()[ $status ] ?? $status )
		);
	}

	/**
	 * Renders the confidence percentage, with a low-confidence warning
	 * badge when applicable.
	 *
	 * @param int|string|null $confidence Stored confidence value.
	 *
	 * @return void
	 */
	private function render_confidence( $confidence ): void {
		if ( null === $confidence || '' === $confidence ) {
			echo '&mdash;';

			return;
		}

		$confidence = (int) $confidence;

		printf( '%d%%', esc_html( $confidence ) );

		if ( ConfidenceService::is_low( $confidence ) ) {
			printf(
				' <span class="cf7aic-badge cf7aic-badge--low-confidence">%s</span>',
				esc_html__( 'Low Confidence', 'cf7-ai-copilot' )
			);
		}
	}

	/**
	 * Returns the currently active filters from the request, all sanitized.
	 *
	 * @return array<string, string>
	 */
	private function get_current_filters(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filtering/search, no state change.
		return array(
			'status'    => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '',
			'priority'  => isset( $_GET['priority'] ) ? sanitize_key( wp_unslash( $_GET['priority'] ) ) : '',
			'category'  => isset( $_GET['category'] ) ? sanitize_key( wp_unslash( $_GET['category'] ) ) : '',
			'date_from' => isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '',
			'date_to'   => isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '',
			'search'    => isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '',
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Returns the current page number from the request.
	 *
	 * @return int
	 */
	private function get_current_page(): int {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination, no state change.
		return max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 );
	}

	/**
	 * Whether any filter is currently active.
	 *
	 * @param array<string, string> $filters Currently active filters.
	 *
	 * @return bool
	 */
	private function has_active_filters( array $filters ): bool {
		return '' !== implode( '', $filters );
	}

	/**
	 * Workflow status labels.
	 *
	 * @return array<string, string>
	 */
	private function status_labels(): array {
		return array(
			SubmissionsRepository::STATUS_NEW      => __( 'New', 'cf7-ai-copilot' ),
			SubmissionsRepository::STATUS_REVIEWED => __( 'Reviewed', 'cf7-ai-copilot' ),
			SubmissionsRepository::STATUS_REPLIED  => __( 'Replied', 'cf7-ai-copilot' ),
			SubmissionsRepository::STATUS_ARCHIVED => __( 'Archived', 'cf7-ai-copilot' ),
		);
	}

	/**
	 * AI generation outcome labels, used when a row has no review status
	 * yet worth showing (nothing was generated to review).
	 *
	 * @return array<string, string>
	 */
	private function ai_status_labels(): array {
		return array(
			SubmissionsRepository::AI_STATUS_NO_API_KEY    => __( 'No API Key', 'cf7-ai-copilot' ),
			SubmissionsRepository::AI_STATUS_QUOTA_REACHED => __( 'Quota Reached', 'cf7-ai-copilot' ),
			SubmissionsRepository::AI_STATUS_FAILED        => __( 'AI Failed', 'cf7-ai-copilot' ),
		);
	}
}
