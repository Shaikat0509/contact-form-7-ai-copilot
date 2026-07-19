<?php
/**
 * View: General tab.
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

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- local to this template partial, `require`-d by CF7AIC\Admin\SettingsPage::render_body() into its own local scope (including the $form loop variable below); never accessed directly (blocked by the ABSPATH guard above), so these are not real WordPress globals.
$general = $repository->get_general();

$forms = get_posts(
	array(
		'post_type'      => 'wpcf7_contact_form',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
	)
);
?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php wp_nonce_field( 'cf7aic_save_general', 'cf7aic_nonce' ); ?>
	<input type="hidden" name="action" value="cf7aic_save_general" />

	<?php if ( empty( $forms ) ) : ?>
		<div class="notice notice-warning inline">
			<p>
				<?php esc_html_e( 'No Contact Form 7 forms were found. Create a form first, then come back here to enable AI for it.', 'olmbox-ai-inbox-for-contact-form-7' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<table class="form-table" role="presentation">
		<tbody>
			<tr>
				<th scope="row">
					<label for="cf7aic_enabled"><?php esc_html_e( 'Enable AI Copilot', 'olmbox-ai-inbox-for-contact-form-7' ); ?></label>
				</th>
				<td>
					<label>
						<input
							type="checkbox"
							id="cf7aic_enabled"
							name="enabled"
							value="1"
							<?php checked( $general['enabled'] ); ?>
						/>
						<?php esc_html_e( 'Analyze new submissions to the forms checked below and add them to the AI Inbox for your review.', 'olmbox-ai-inbox-for-contact-form-7' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'The AI never sends anything automatically — every suggested reply waits in the AI Inbox until you review and send it yourself.', 'olmbox-ai-inbox-for-contact-form-7' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<?php esc_html_e( 'Contact Form 7 forms', 'olmbox-ai-inbox-for-contact-form-7' ); ?>
				</th>
				<td>
					<fieldset>
						<legend class="screen-reader-text">
							<?php esc_html_e( 'Contact Form 7 forms', 'olmbox-ai-inbox-for-contact-form-7' ); ?>
						</legend>
						<?php foreach ( $forms as $form ) : ?>
							<label class="cf7aic-form-checkbox">
								<input
									type="checkbox"
									name="form_ids[]"
									value="<?php echo esc_attr( (string) $form->ID ); ?>"
									<?php checked( in_array( $form->ID, $general['form_ids'], true ) ); ?>
								/>
								<?php echo esc_html( $form->post_title ); ?>
							</label>
						<?php endforeach; ?>
					</fieldset>
					<p class="description">
						<?php esc_html_e( 'Choose which forms should get AI analysis — any number of forms, including all of them.', 'olmbox-ai-inbox-for-contact-form-7' ); ?>
					</p>
				</td>
			</tr>
		</tbody>
	</table>

	<?php submit_button( __( 'Save Changes', 'olmbox-ai-inbox-for-contact-form-7' ) ); ?>
</form>
<?php // phpcs:enable WordPress.NamingConventions.PrefixAllGlobals ?>
