<?php
/**
 * Renders the plugin's own Dashboard section: an at-a-glance overview of
 * Olmbox activity, distinct from the native WordPress Dashboard widget.
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
use CF7AIC\Settings\Repository;

/**
 * Class DashboardPage
 *
 * Pulls together data that already exists elsewhere (submissions log,
 * monthly usage, provider settings) into stat cards, a hand-rolled inline
 * SVG usage chart, an AI Provider status card, a Recent Submissions
 * mini-table, and a few footer tip cards. No external chart library, no
 * new tracking system, and no live API calls are triggered by viewing
 * this page — the provider card only ever reflects the result of the
 * last manual "Test Connection" click.
 */
final class DashboardPage {

	/**
	 * Number of trailing days (including today) shown on the usage chart.
	 *
	 * @var int
	 */
	private const CHART_DAYS = 30;

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
	 * Settings repository.
	 *
	 * @var Repository
	 */
	private Repository $repository;

	/**
	 * Constructor.
	 *
	 * @param SubmissionsRepository $submissions   Submissions log repository.
	 * @param UsageTracker          $usage_tracker Usage tracker.
	 * @param Repository            $repository    Settings repository.
	 */
	public function __construct( SubmissionsRepository $submissions, UsageTracker $usage_tracker, Repository $repository ) {
		$this->submissions   = $submissions;
		$this->usage_tracker = $usage_tracker;
		$this->repository    = $repository;
	}

	/**
	 * Renders the Dashboard section body.
	 *
	 * @return void
	 */
	public function render_body(): void {
		$this->render_stat_cards();

		echo '<div class="cf7aic-dash-row">';
		$this->render_usage_chart_card();
		$this->render_provider_card();
		echo '</div>';

		echo '<div class="cf7aic-dash-row">';
		$this->render_recent_table_card();
		echo '</div>';

		$this->render_tip_cards();
	}

	/**
	 * Renders the row of four KPI stat cards.
	 *
	 * @return void
	 */
	private function render_stat_cards(): void {
		$new_today    = $this->submissions->count_created_since( gmdate( 'Y-m-d 00:00:00' ) );
		$needs_review = $this->submissions->count_by_status( SubmissionsRepository::STATUS_NEW );
		$replied      = $this->submissions->count_by_status_since( SubmissionsRepository::STATUS_REPLIED, gmdate( 'Y-m-01' ) );
		$usage_count  = $this->usage_tracker->get_count();
		?>
		<div class="cf7aic-stats-grid">
			<div class="cf7aic-stat-card">
				<span class="cf7aic-stat-card__label"><?php esc_html_e( 'New Today', 'olmbox-ai-inbox-for-contact-form-7' ); ?></span>
				<span class="cf7aic-stat-card__value"><?php echo esc_html( (string) $new_today ); ?></span>
			</div>
			<div class="cf7aic-stat-card">
				<span class="cf7aic-stat-card__label"><?php esc_html_e( 'Needs Review', 'olmbox-ai-inbox-for-contact-form-7' ); ?></span>
				<span class="cf7aic-stat-card__value"><?php echo esc_html( (string) $needs_review ); ?></span>
			</div>
			<div class="cf7aic-stat-card">
				<span class="cf7aic-stat-card__label"><?php esc_html_e( 'Replied This Month', 'olmbox-ai-inbox-for-contact-form-7' ); ?></span>
				<span class="cf7aic-stat-card__value"><?php echo esc_html( (string) $replied ); ?></span>
			</div>
			<div class="cf7aic-stat-card">
				<span class="cf7aic-stat-card__label"><?php esc_html_e( 'AI Analyses This Month', 'olmbox-ai-inbox-for-contact-form-7' ); ?></span>
				<span class="cf7aic-stat-card__value"><?php echo esc_html( (string) $usage_count ); ?></span>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders the Usage Overview card: an inline SVG line/area chart of
	 * successful AI analyses per day, over the last {@see self::CHART_DAYS} days.
	 *
	 * @return void
	 */
	private function render_usage_chart_card(): void {
		$since  = gmdate( 'Y-m-d', strtotime( '-' . ( self::CHART_DAYS - 1 ) . ' days' ) );
		$counts = $this->submissions->get_daily_ai_counts( $since );
		?>
		<div class="cf7aic-card cf7aic-chart-card">
			<h2><?php esc_html_e( 'Usage Overview', 'olmbox-ai-inbox-for-contact-form-7' ); ?></h2>
			<p class="cf7aic-chart-card__subtitle">
				<?php esc_html_e( 'Successful AI analyses per day, last 30 days.', 'olmbox-ai-inbox-for-contact-form-7' ); ?>
			</p>
			<?php echo $this->build_line_chart_svg( $counts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built entirely from hardcoded SVG markup and numeric coordinates computed in this class; no user input reaches this string. ?>
		</div>
		<?php
	}

	/**
	 * Renders the AI Provider status card. Reflects only the result of the
	 * last manual "Test Connection" click on the AI Provider settings tab —
	 * viewing this card never itself triggers a live API call.
	 *
	 * @return void
	 */
	private function render_provider_card(): void {
		$provider     = $this->repository->get_provider();
		$has_api_key  = $this->repository->has_api_key();
		$label        = Repository::PROVIDERS[ $provider['provider'] ] ?? $provider['provider'];
		$provider_url = Menu::url(
			array(
				'section' => 'settings',
				'tab'     => 'provider',
			)
		);
		?>
		<div class="cf7aic-card cf7aic-provider-card">
			<h2><?php esc_html_e( 'AI Provider', 'olmbox-ai-inbox-for-contact-form-7' ); ?></h2>
			<dl class="cf7aic-detail-list">
				<dt><?php esc_html_e( 'Provider', 'olmbox-ai-inbox-for-contact-form-7' ); ?></dt>
				<dd><?php echo esc_html( $label ); ?></dd>

				<dt><?php esc_html_e( 'Model', 'olmbox-ai-inbox-for-contact-form-7' ); ?></dt>
				<dd><?php echo esc_html( '' !== $provider['model'] ? $provider['model'] : '—' ); ?></dd>

				<dt><?php esc_html_e( 'API Key', 'olmbox-ai-inbox-for-contact-form-7' ); ?></dt>
				<dd>
					<?php if ( $has_api_key ) : ?>
						<span class="cf7aic-badge cf7aic-badge--success"><?php esc_html_e( 'Configured', 'olmbox-ai-inbox-for-contact-form-7' ); ?></span>
					<?php else : ?>
						<span class="cf7aic-badge cf7aic-badge--failed"><?php esc_html_e( 'Not Set', 'olmbox-ai-inbox-for-contact-form-7' ); ?></span>
					<?php endif; ?>
				</dd>

				<dt><?php esc_html_e( 'Last Test', 'olmbox-ai-inbox-for-contact-form-7' ); ?></dt>
				<dd>
					<?php if ( '' === $provider['last_tested_at'] ) : ?>
						&mdash;
					<?php elseif ( true === $provider['last_test_success'] ) : ?>
						<span class="cf7aic-badge cf7aic-badge--success"><?php esc_html_e( 'Connected', 'olmbox-ai-inbox-for-contact-form-7' ); ?></span>
					<?php else : ?>
						<span class="cf7aic-badge cf7aic-badge--failed"><?php esc_html_e( 'Failed', 'olmbox-ai-inbox-for-contact-form-7' ); ?></span>
					<?php endif; ?>
					<?php if ( '' !== $provider['last_tested_at'] ) : ?>
						<span class="description">
							<?php
							echo esc_html(
								wp_date(
									get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
									strtotime( $provider['last_tested_at'] )
								)
							);
							?>
						</span>
					<?php endif; ?>
				</dd>
			</dl>
			<p><a href="<?php echo esc_url( $provider_url ); ?>" class="button"><?php esc_html_e( 'Manage Provider', 'olmbox-ai-inbox-for-contact-form-7' ); ?></a></p>
		</div>
		<?php
	}

	/**
	 * Renders the Recent Submissions mini-table card.
	 *
	 * @return void
	 */
	private function render_recent_table_card(): void {
		$recent = $this->submissions->get_recent( 5 );
		?>
		<div class="cf7aic-card cf7aic-recent-card">
			<h2><?php esc_html_e( 'Recent Submissions', 'olmbox-ai-inbox-for-contact-form-7' ); ?></h2>
			<?php if ( empty( $recent ) ) : ?>
				<p><?php esc_html_e( 'No submissions yet.', 'olmbox-ai-inbox-for-contact-form-7' ); ?></p>
			<?php else : ?>
				<table class="cf7aic-table cf7aic-table--compact widefat">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Name', 'olmbox-ai-inbox-for-contact-form-7' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Summary', 'olmbox-ai-inbox-for-contact-form-7' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Priority', 'olmbox-ai-inbox-for-contact-form-7' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Date', 'olmbox-ai-inbox-for-contact-form-7' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $recent as $item ) : ?>
							<?php
							$detail_url = Menu::url(
								array(
									'section' => 'submissions',
									'id'      => $item['id'],
								)
							);
							$name       = '' !== $item['visitor_name'] ? $item['visitor_name'] : ( '' !== $item['visitor_email'] ? $item['visitor_email'] : __( '(unknown)', 'olmbox-ai-inbox-for-contact-form-7' ) );
							$summary    = '' !== (string) $item['ai_summary'] ? $item['ai_summary'] : '—';
							?>
							<tr>
								<td data-label="<?php esc_attr_e( 'Name', 'olmbox-ai-inbox-for-contact-form-7' ); ?>">
									<a href="<?php echo esc_url( $detail_url ); ?>"><?php echo esc_html( $name ); ?></a>
								</td>
								<td data-label="<?php esc_attr_e( 'Summary', 'olmbox-ai-inbox-for-contact-form-7' ); ?>" class="cf7aic-table__truncate">
									<?php echo esc_html( $summary ); ?>
								</td>
								<td data-label="<?php esc_attr_e( 'Priority', 'olmbox-ai-inbox-for-contact-form-7' ); ?>">
									<?php if ( '' !== $item['priority'] ) : ?>
										<span class="cf7aic-badge cf7aic-badge--priority-<?php echo esc_attr( $item['priority'] ); ?>">
											<?php echo esc_html( ClassificationService::priority_label( $item['priority'] ) ); ?>
										</span>
									<?php else : ?>
										&mdash;
									<?php endif; ?>
								</td>
								<td data-label="<?php esc_attr_e( 'Date', 'olmbox-ai-inbox-for-contact-form-7' ); ?>">
									<?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( (string) $item['created_at'] ) ) ); ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
			<p><a href="<?php echo esc_url( Menu::url( array( 'section' => 'submissions' ) ) ); ?>" class="button"><?php esc_html_e( 'Open AI Inbox', 'olmbox-ai-inbox-for-contact-form-7' ); ?></a></p>
		</div>
		<?php
	}

	/**
	 * Renders the three footer tip/action cards.
	 *
	 * @return void
	 */
	private function render_tip_cards(): void {
		$provider_url = Menu::url(
			array(
				'section' => 'settings',
				'tab'     => 'provider',
			)
		);
		$prompt_url   = Menu::url(
			array(
				'section' => 'settings',
				'tab'     => 'prompt',
			)
		);
		?>
		<div class="cf7aic-dash-row cf7aic-dash-row--tips">
			<div class="cf7aic-card cf7aic-tip-card">
				<span class="dashicons dashicons-admin-network" aria-hidden="true"></span>
				<h3><?php esc_html_e( 'Set up your AI Provider', 'olmbox-ai-inbox-for-contact-form-7' ); ?></h3>
				<p><?php esc_html_e( 'Add your API key and pick a model so new submissions get an AI summary and suggested reply.', 'olmbox-ai-inbox-for-contact-form-7' ); ?></p>
				<a href="<?php echo esc_url( $provider_url ); ?>"><?php esc_html_e( 'Go to AI Provider', 'olmbox-ai-inbox-for-contact-form-7' ); ?> &rarr;</a>
			</div>
			<div class="cf7aic-card cf7aic-tip-card">
				<span class="dashicons dashicons-edit" aria-hidden="true"></span>
				<h3><?php esc_html_e( 'Customize your prompt', 'olmbox-ai-inbox-for-contact-form-7' ); ?></h3>
				<p><?php esc_html_e( 'Tune the base system prompt to match your tone, policies, and the kind of replies you want drafted.', 'olmbox-ai-inbox-for-contact-form-7' ); ?></p>
				<a href="<?php echo esc_url( $prompt_url ); ?>"><?php esc_html_e( 'Edit Prompt', 'olmbox-ai-inbox-for-contact-form-7' ); ?> &rarr;</a>
			</div>
			<div class="cf7aic-card cf7aic-tip-card">
				<span class="dashicons dashicons-star-filled" aria-hidden="true"></span>
				<h3><?php esc_html_e( 'Enjoying Olmbox?', 'olmbox-ai-inbox-for-contact-form-7' ); ?></h3>
				<p><?php esc_html_e( 'A quick review on WordPress.org helps other site owners find this plugin.', 'olmbox-ai-inbox-for-contact-form-7' ); ?></p>
				<a href="https://wordpress.org/support/plugin/olmbox-ai-inbox-for-contact-form-7/reviews/#new-post" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Rate Us', 'olmbox-ai-inbox-for-contact-form-7' ); ?> &rarr;
				</a>
			</div>
		</div>
		<?php
	}

	/**
	 * Builds the inline SVG line/area chart markup for the usage chart card.
	 *
	 * @param array<string, int> $counts Map of `Y-m-d` date => count, one entry per day, in order.
	 *
	 * @return string Raw SVG markup (not user-controlled; safe to echo unescaped).
	 */
	private function build_line_chart_svg( array $counts ): string {
		$width  = 560;
		$height = 160;
		$pad    = 10;

		$values = array_values( $counts );
		$max    = max( 1, ...$values );
		$n      = count( $values );
		$step   = $n > 1 ? ( $width - 2 * $pad ) / ( $n - 1 ) : 0;

		$points = array();
		foreach ( $values as $i => $value ) {
			$x        = $pad + $i * $step;
			$y        = $height - $pad - ( ( $value / $max ) * ( $height - 2 * $pad ) );
			$points[] = round( $x, 1 ) . ',' . round( $y, 1 );
		}

		$line_points = implode( ' ', $points );
		$area_points = $line_points . ' ' . ( $width - $pad ) . ',' . ( $height - $pad ) . ' ' . $pad . ',' . ( $height - $pad );

		$circles = '';
		foreach ( $points as $point ) {
			list( $x, $y ) = explode( ',', $point );
			$circles      .= sprintf( '<circle cx="%s" cy="%s" r="2.5" class="cf7aic-chart-svg__point" />', $x, $y );
		}

		return sprintf(
			'<svg class="cf7aic-chart-svg" viewBox="0 0 %1$d %2$d" preserveAspectRatio="none" role="img" aria-label="%3$s">'
				. '<polygon points="%4$s" class="cf7aic-chart-svg__area" />'
				. '<polyline points="%5$s" class="cf7aic-chart-svg__line" />'
				. '%6$s'
				. '</svg>',
			$width,
			$height,
			esc_attr__( 'Daily AI analyses over the last 30 days', 'olmbox-ai-inbox-for-contact-form-7' ),
			esc_attr( $area_points ),
			esc_attr( $line_points ),
			$circles
		);
	}
}
