<?php
/**
 * View: Usage tab.
 *
 * Expects `$repository` (CF7AIC\Settings\Repository) and `$usage_tracker`
 * (CF7AIC\Services\UsageTracker) in scope, provided by
 * CF7AIC\Admin\SettingsPage::render().
 *
 * @package CF7AIC
 *
 * @var \CF7AIC\Settings\Repository   $repository
 * @var \CF7AIC\Services\UsageTracker $usage_tracker
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- local to this template partial, `require`-d by CF7AIC\Admin\SettingsPage::render_body() into its own local scope; never accessed directly (blocked by the ABSPATH guard above), so these are not real WordPress globals.
$provider_settings = $repository->get_provider();
$provider_label    = \CF7AIC\Settings\Repository::PROVIDERS[ $provider_settings['provider'] ] ?? $provider_settings['provider'];

$limit     = $usage_tracker->get_limit();
$count     = $usage_tracker->get_count();
$remaining = $usage_tracker->get_remaining();
$percent   = $limit > 0 ? min( 100, (int) round( ( $count / $limit ) * 100 ) ) : 0;
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals
?>
<div class="cf7aic-stats-grid">
	<div class="cf7aic-stat-card">
		<span class="cf7aic-stat-card__label"><?php esc_html_e( 'Monthly limit', 'cf7-ai-copilot' ); ?></span>
		<span class="cf7aic-stat-card__value"><?php echo esc_html( (string) $limit ); ?></span>
	</div>
	<div class="cf7aic-stat-card">
		<span class="cf7aic-stat-card__label"><?php esc_html_e( 'Remaining', 'cf7-ai-copilot' ); ?></span>
		<span class="cf7aic-stat-card__value"><?php echo esc_html( (string) $remaining ); ?></span>
	</div>
	<div class="cf7aic-stat-card">
		<span class="cf7aic-stat-card__label"><?php esc_html_e( 'Provider', 'cf7-ai-copilot' ); ?></span>
		<span class="cf7aic-stat-card__value"><?php echo esc_html( $provider_label ); ?></span>
	</div>
	<div class="cf7aic-stat-card">
		<span class="cf7aic-stat-card__label"><?php esc_html_e( 'Model', 'cf7-ai-copilot' ); ?></span>
		<span class="cf7aic-stat-card__value"><?php echo esc_html( '' !== $provider_settings['model'] ? $provider_settings['model'] : '—' ); ?></span>
	</div>
	<div class="cf7aic-stat-card">
		<span class="cf7aic-stat-card__label"><?php esc_html_e( 'Resets on', 'cf7-ai-copilot' ); ?></span>
		<span class="cf7aic-stat-card__value"><?php echo esc_html( $usage_tracker->get_reset_date() ); ?></span>
	</div>
	<div class="cf7aic-stat-card">
		<span class="cf7aic-stat-card__label"><?php esc_html_e( 'Estimated Token Usage', 'cf7-ai-copilot' ); ?></span>
		<span class="cf7aic-stat-card__value"><?php echo esc_html( number_format_i18n( $usage_tracker->get_estimated_tokens() ) ); ?></span>
	</div>
</div>

<p>
	<?php
	printf(
		/* translators: 1: generations used this month, 2: monthly limit */
		esc_html__( '%1$d of %2$d AI generations used this month', 'cf7-ai-copilot' ),
		(int) $count,
		(int) $limit
	);
	?>
</p>
<div class="cf7aic-progress-bar" role="progressbar" aria-valuenow="<?php echo esc_attr( (string) $percent ); ?>" aria-valuemin="0" aria-valuemax="100">
	<div class="cf7aic-progress-bar__fill" style="width: <?php echo esc_attr( (string) $percent ); ?>%;"></div>
</div>

<?php if ( $usage_tracker->is_limit_reached() ) : ?>
	<div class="notice notice-warning inline">
		<p><?php esc_html_e( 'You have reached your monthly AI generation limit. AI features are paused until the next reset date above; Contact Form 7 itself continues to work normally.', 'cf7-ai-copilot' ); ?></p>
	</div>
<?php endif; ?>
