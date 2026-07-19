<?php
/**
 * Registers the "Olmbox" WordPress dashboard widget.
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
use CF7AIC\Services\UsageTracker;

/**
 * Class DashboardWidget
 *
 * A quick at-a-glance summary on the main WordPress Dashboard: how many
 * submissions are waiting for review, how many replies have gone out,
 * this month's AI usage, and a handful of the most recent submissions —
 * with a link straight into the AI Inbox.
 */
final class DashboardWidget {

	/**
	 * Submissions log repository.
	 *
	 * @var SubmissionsRepository
	 */
	private SubmissionsRepository $submissions;

	/**
	 * Usage tracker.
	 *
	 * @var UsageTracker
	 */
	private UsageTracker $usage_tracker;

	/**
	 * Constructor.
	 *
	 * @param SubmissionsRepository $submissions   Submissions log repository.
	 * @param UsageTracker          $usage_tracker Usage tracker.
	 */
	public function __construct( SubmissionsRepository $submissions, UsageTracker $usage_tracker ) {
		$this->submissions   = $submissions;
		$this->usage_tracker = $usage_tracker;
	}

	/**
	 * Registers WordPress hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_dashboard_setup', array( $this, 'register_widget' ) );
	}

	/**
	 * Registers the dashboard widget, only for users who can manage it.
	 *
	 * @return void
	 */
	public function register_widget(): void {
		if ( ! current_user_can( Menu::CAPABILITY ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'cf7aic_dashboard_widget',
			__( 'Olmbox', 'olmbox-ai-inbox-for-contact-form-7' ),
			array( $this, 'render' )
		);
	}

	/**
	 * Renders the widget body.
	 *
	 * @return void
	 */
	public function render(): void {
		$new_today    = $this->submissions->count_created_since( gmdate( 'Y-m-d 00:00:00' ) );
		$needs_review = $this->submissions->count_by_status( SubmissionsRepository::STATUS_NEW );
		$replied      = $this->submissions->count_by_status( SubmissionsRepository::STATUS_REPLIED );
		$recent       = $this->submissions->get_recent( 5 );

		$usage_count = $this->usage_tracker->get_count();
		?>
		<div class="cf7aic-widget-stats">
			<div class="cf7aic-widget-stat">
				<span class="cf7aic-widget-stat__value"><?php echo esc_html( (string) $new_today ); ?></span>
				<span class="cf7aic-widget-stat__label"><?php esc_html_e( 'New Today', 'olmbox-ai-inbox-for-contact-form-7' ); ?></span>
			</div>
			<div class="cf7aic-widget-stat">
				<span class="cf7aic-widget-stat__value"><?php echo esc_html( (string) $needs_review ); ?></span>
				<span class="cf7aic-widget-stat__label"><?php esc_html_e( 'Needs Review', 'olmbox-ai-inbox-for-contact-form-7' ); ?></span>
			</div>
			<div class="cf7aic-widget-stat">
				<span class="cf7aic-widget-stat__value"><?php echo esc_html( (string) $replied ); ?></span>
				<span class="cf7aic-widget-stat__label"><?php esc_html_e( 'Replies Sent', 'olmbox-ai-inbox-for-contact-form-7' ); ?></span>
			</div>
			<div class="cf7aic-widget-stat">
				<span class="cf7aic-widget-stat__value"><?php echo esc_html( (string) $usage_count ); ?></span>
				<span class="cf7aic-widget-stat__label"><?php esc_html_e( 'AI Analyses This Month', 'olmbox-ai-inbox-for-contact-form-7' ); ?></span>
			</div>
		</div>

		<?php if ( empty( $recent ) ) : ?>
			<p><?php esc_html_e( 'No submissions yet.', 'olmbox-ai-inbox-for-contact-form-7' ); ?></p>
		<?php else : ?>
			<ul class="cf7aic-widget-list">
				<?php
				foreach ( $recent as $item ) :
					$item_url = Menu::url(
						array(
							'section' => 'submissions',
							'id'      => $item['id'],
						)
					);
					$label    = '' !== $item['visitor_name'] ? $item['visitor_name'] : ( '' !== $item['visitor_email'] ? $item['visitor_email'] : __( '(unknown)', 'olmbox-ai-inbox-for-contact-form-7' ) );
					?>
					<li>
						<a href="<?php echo esc_url( $item_url ); ?>"><?php echo esc_html( $label ); ?></a>
						<?php if ( '' !== $item['priority'] ) : ?>
							<span class="cf7aic-badge cf7aic-badge--priority-<?php echo esc_attr( $item['priority'] ); ?>">
								<?php echo esc_html( ClassificationService::priority_label( $item['priority'] ) ); ?>
							</span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<p class="cf7aic-widget-footer">
			<a href="<?php echo esc_url( Menu::url( array( 'section' => 'submissions' ) ) ); ?>" class="button">
				<?php esc_html_e( 'Open AI Inbox', 'olmbox-ai-inbox-for-contact-form-7' ); ?>
			</a>
		</p>
		<?php
	}
}
