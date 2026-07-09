<?php
/**
 * View: Help tab.
 *
 * Static documentation only — no settings are read or written here.
 *
 * @package CF7AIC
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="cf7aic-help">

	<h2><?php esc_html_e( 'How it works', 'cf7-ai-copilot' ); ?></h2>
	<p>
		<?php esc_html_e( 'When a visitor submits your chosen Contact Form 7 form, this plugin sends it to your configured AI provider, which analyzes it and drafts a summary, a suggested reply, a category, a priority, and a confidence score. All of this appears as a new entry in your AI Inbox — nothing is ever emailed automatically. Review the suggestion, edit the reply if you like, and click "Send Reply" only when you are ready. Nothing is sent to any AI provider until you save a valid API key on the AI Provider tab.', 'cf7-ai-copilot' ); ?>
	</p>

	<h2><?php esc_html_e( 'Getting an API key', 'cf7-ai-copilot' ); ?></h2>
	<ul>
		<li>
			<strong><?php esc_html_e( 'OpenAI', 'cf7-ai-copilot' ); ?></strong>
			&mdash;
			<a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener noreferrer">platform.openai.com/api-keys</a>
		</li>
		<li>
			<strong><?php esc_html_e( 'Anthropic', 'cf7-ai-copilot' ); ?></strong>
			&mdash;
			<a href="https://console.anthropic.com/settings/keys" target="_blank" rel="noopener noreferrer">console.anthropic.com/settings/keys</a>
		</li>
		<li>
			<strong><?php esc_html_e( 'Google Gemini', 'cf7-ai-copilot' ); ?></strong>
			&mdash;
			<a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener noreferrer">aistudio.google.com/app/apikey</a>
		</li>
		<li>
			<strong><?php esc_html_e( 'OpenRouter', 'cf7-ai-copilot' ); ?></strong>
			&mdash;
			<a href="https://openrouter.ai/keys" target="_blank" rel="noopener noreferrer">openrouter.ai/keys</a>
		</li>
	</ul>

	<h2><?php esc_html_e( 'Supported model names (examples)', 'cf7-ai-copilot' ); ?></h2>
	<p><?php esc_html_e( 'Enter the exact model identifier from your provider\'s documentation into the Model field on the AI Provider tab. A few common examples:', 'cf7-ai-copilot' ); ?></p>
	<ul>
		<li><strong>OpenAI:</strong> <code>gpt-4o-mini</code>, <code>gpt-4o</code></li>
		<li><strong>Anthropic:</strong> <code>claude-sonnet-5</code>, <code>claude-haiku-4-5</code></li>
		<li><strong>Google Gemini:</strong> <code>gemini-2.5-flash</code>, <code>gemini-2.5-pro</code></li>
		<li><strong>OpenRouter:</strong> any model slug listed on <a href="https://openrouter.ai/models" target="_blank" rel="noopener noreferrer">openrouter.ai/models</a>, e.g. <code>openai/gpt-4o-mini</code></li>
	</ul>
	<p class="description">
		<?php esc_html_e( 'Provider pricing and available models change over time — always confirm the current model name with your provider.', 'cf7-ai-copilot' ); ?>
	</p>

	<h2><?php esc_html_e( 'Free plan limits', 'cf7-ai-copilot' ); ?></h2>
	<p>
		<?php esc_html_e( 'The free plan applies AI to a single Contact Form 7 form and allows up to 20 AI generations per calendar month. If the limit is reached, AI features pause until the next month — Contact Form 7 itself is never affected.', 'cf7-ai-copilot' ); ?>
	</p>

	<h2><?php esc_html_e( 'Privacy', 'cf7-ai-copilot' ); ?></h2>
	<p>
		<?php esc_html_e( 'The text a visitor submits to your chosen form is sent only to the AI provider you configure, solely to generate the summary, suggested reply, and classification shown in the AI Inbox. This plugin does not collect analytics or telemetry, and does not send any data to its author.', 'cf7-ai-copilot' ); ?>
	</p>

</div>
