<?php
/**
 * View: Prompt tab.
 *
 * Expects `$repository` (CF7AIC\Settings\Repository) in scope, provided by
 * CF7AIC\Admin\SettingsPage::render().
 *
 * @package CF7AIC
 *
 * @var \CF7AIC\Settings\Repository $repository
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$prompt     = $repository->get_prompt();
$max_length = \CF7AIC\Settings\Repository::PROMPT_MAX_LENGTH;
$default    = \CF7AIC\Settings\Repository::DEFAULT_SYSTEM_PROMPT;
?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php wp_nonce_field( 'cf7aic_save_prompt', 'cf7aic_nonce' ); ?>
	<input type="hidden" name="action" value="cf7aic_save_prompt" />

	<table class="form-table" role="presentation">
		<tbody>
			<tr>
				<th scope="row">
					<label for="cf7aic_system_prompt"><?php esc_html_e( 'System prompt', 'cf7-ai-copilot' ); ?></label>
				</th>
				<td>
					<textarea
						id="cf7aic_system_prompt"
						name="system_prompt"
						rows="6"
						class="large-text"
						maxlength="<?php echo esc_attr( (string) $max_length ); ?>"
						data-default-prompt="<?php echo esc_attr( $default ); ?>"
						data-max-length="<?php echo esc_attr( (string) $max_length ); ?>"
					><?php echo esc_textarea( $prompt['system_prompt'] ); ?></textarea>
					<p class="description">
						<span id="cf7aic-prompt-counter" class="cf7aic-char-counter"></span>
						&mdash;
						<?php esc_html_e( 'This instructs the AI how to behave. It is combined with the visitor\'s submitted message only — no other data is sent.', 'cf7-ai-copilot' ); ?>
					</p>
					<p>
						<button type="button" id="cf7aic-reset-prompt" class="button">
							<?php esc_html_e( 'Reset to Default', 'cf7-ai-copilot' ); ?>
						</button>
					</p>
				</td>
			</tr>
		</tbody>
	</table>

	<?php submit_button( __( 'Save Changes', 'cf7-ai-copilot' ) ); ?>
</form>
