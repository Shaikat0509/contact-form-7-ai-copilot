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

$count = $usage_tracker->get_count();
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals
?>
<p class="description">
	<?php esc_html_e( 'These figures are purely informational — there is no cap on AI analysis. It always runs as long as an API key is configured.', 'shaikat-ai-inbox-for-contact-form-7' ); ?>
</p>
<div class="cf7aic-stats-grid">
	<div class="cf7aic-stat-card">
		<span class="cf7aic-stat-card__label"><?php esc_html_e( 'AI Analyses This Month', 'shaikat-ai-inbox-for-contact-form-7' ); ?></span>
		<span class="cf7aic-stat-card__value"><?php echo esc_html( (string) $count ); ?></span>
	</div>
	<div class="cf7aic-stat-card">
		<span class="cf7aic-stat-card__label"><?php esc_html_e( 'Provider', 'shaikat-ai-inbox-for-contact-form-7' ); ?></span>
		<span class="cf7aic-stat-card__value"><?php echo esc_html( $provider_label ); ?></span>
	</div>
	<div class="cf7aic-stat-card">
		<span class="cf7aic-stat-card__label"><?php esc_html_e( 'Model', 'shaikat-ai-inbox-for-contact-form-7' ); ?></span>
		<span class="cf7aic-stat-card__value"><?php echo esc_html( '' !== $provider_settings['model'] ? $provider_settings['model'] : '—' ); ?></span>
	</div>
	<div class="cf7aic-stat-card">
		<span class="cf7aic-stat-card__label"><?php esc_html_e( 'Count Resets On', 'shaikat-ai-inbox-for-contact-form-7' ); ?></span>
		<span class="cf7aic-stat-card__value"><?php echo esc_html( $usage_tracker->get_reset_date() ); ?></span>
	</div>
	<div class="cf7aic-stat-card">
		<span class="cf7aic-stat-card__label"><?php esc_html_e( 'Estimated Token Usage', 'shaikat-ai-inbox-for-contact-form-7' ); ?></span>
		<span class="cf7aic-stat-card__value"><?php echo esc_html( number_format_i18n( $usage_tracker->get_estimated_tokens() ) ); ?></span>
	</div>
</div>
