<?php
/**
 * View: AI Provider tab.
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

$provider    = $repository->get_provider();
$masked_key  = $repository->get_masked_api_key();
$has_api_key = '' !== $masked_key;
?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php wp_nonce_field( 'cf7aic_save_provider', 'cf7aic_nonce' ); ?>
	<input type="hidden" name="action" value="cf7aic_save_provider" />

	<table class="form-table" role="presentation">
		<tbody>
			<tr>
				<th scope="row">
					<label for="cf7aic_provider"><?php esc_html_e( 'Provider', 'cf7-ai-copilot' ); ?></label>
				</th>
				<td>
					<select id="cf7aic_provider" name="provider">
						<?php foreach ( \CF7AIC\Settings\Repository::PROVIDERS as $slug => $label ) : ?>
							<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $provider['provider'], $slug ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="cf7aic_api_key"><?php esc_html_e( 'API Key', 'cf7-ai-copilot' ); ?></label>
				</th>
				<td>
					<input
						type="password"
						id="cf7aic_api_key"
						name="api_key"
						class="regular-text"
						autocomplete="off"
						placeholder="<?php echo esc_attr( $has_api_key ? __( 'Leave blank to keep the current key', 'cf7-ai-copilot' ) : __( 'Enter your API key', 'cf7-ai-copilot' ) ); ?>"
					/>
					<p class="description">
						<?php if ( $has_api_key ) : ?>
							<?php
							printf(
								/* translators: %s: masked API key, e.g. ••••••••••••••••••••1234 */
								esc_html__( 'Currently set: %s', 'cf7-ai-copilot' ),
								esc_html( $masked_key )
							);
							?>
						<?php else : ?>
							<?php esc_html_e( 'No API key has been set yet. AI features stay off until one is saved here.', 'cf7-ai-copilot' ); ?>
						<?php endif; ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="cf7aic_model"><?php esc_html_e( 'Model', 'cf7-ai-copilot' ); ?></label>
				</th>
				<td>
					<div class="cf7aic-model-row">
						<select
							id="cf7aic_model"
							name="model"
							data-current-model="<?php echo esc_attr( $provider['model'] ); ?>"
						>
							<?php if ( '' !== $provider['model'] ) : ?>
								<option value="<?php echo esc_attr( $provider['model'] ); ?>" selected="selected">
									<?php echo esc_html( $provider['model'] ); ?>
								</option>
							<?php else : ?>
								<option value=""><?php esc_html_e( '— Load models to choose one —', 'cf7-ai-copilot' ); ?></option>
							<?php endif; ?>
						</select>
						<button
							type="button"
							id="cf7aic-load-models"
							class="button"
							data-has-api-key="<?php echo esc_attr( $has_api_key ? '1' : '0' ); ?>"
						>
							<?php esc_html_e( 'Load Models', 'cf7-ai-copilot' ); ?>
						</button>
					</div>
					<p class="description" id="cf7aic-model-status"></p>
				</td>
			</tr>
		</tbody>
	</table>

	<?php submit_button( __( 'Save Changes', 'cf7-ai-copilot' ) ); ?>
</form>

<p>
	<button type="button" id="cf7aic-test-connection" class="button">
		<?php esc_html_e( 'Test Connection', 'cf7-ai-copilot' ); ?>
	</button>
	<span id="cf7aic-test-connection-result" class="cf7aic-test-result" role="status" aria-live="polite"></span>
</p>
