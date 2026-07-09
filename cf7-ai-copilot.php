<?php
/**
 * Plugin Name:       Contact Form 7 AI Copilot
 * Plugin URI:        https://wordpress.org/plugins/cf7-ai-copilot/
 * Description:       Adds an AI Inbox to a single Contact Form 7 form: every submission gets an AI-drafted summary, suggested reply, category, priority, and confidence score for you to review and send — nothing is ever emailed automatically. Uses your own OpenAI, Anthropic, Gemini, or OpenRouter API key.
 * Version:           2.0.0
 * Requires at least: 6.8
 * Requires PHP:      8.1
 * Author:            CF7 AI Copilot Contributors
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       cf7-ai-copilot
 * Domain Path:       /languages
 *
 * @package CF7AIC
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Minimum PHP version guard.
 *
 * Runs before anything else is loaded so a fatal parse error on older PHP
 * (e.g. from typed properties or enums used elsewhere in the plugin) can
 * never happen — this file only uses syntax valid back to PHP 7.0.
 */
if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
	add_action(
		'admin_notices',
		static function () {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %s: required PHP version */
						__( 'Contact Form 7 AI Copilot requires PHP %s or higher. Please contact your host to upgrade PHP.', 'cf7-ai-copilot' ),
						'8.1'
					)
				)
			);
		}
	);

	return;
}

/**
 * Minimum WordPress version guard.
 */
if ( version_compare( $GLOBALS['wp_version'], '6.8', '<' ) ) {
	add_action(
		'admin_notices',
		static function () {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %s: required WordPress version */
						__( 'Contact Form 7 AI Copilot requires WordPress %s or higher. Please update WordPress.', 'cf7-ai-copilot' ),
						'6.8'
					)
				)
			);
		}
	);

	return;
}

/**
 * Plugin constants.
 */
define( 'CF7AIC_VERSION', '2.0.0' );
define( 'CF7AIC_PLUGIN_FILE', __FILE__ );
define( 'CF7AIC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CF7AIC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CF7AIC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'CF7AIC_INCLUDES_DIR', CF7AIC_PLUGIN_DIR . 'includes/' );
define( 'CF7AIC_TEMPLATES_DIR', CF7AIC_PLUGIN_DIR . 'templates/' );
define( 'CF7AIC_MONTHLY_LIMIT', 20 );

/**
 * Autoloader bootstrap.
 */
require_once CF7AIC_INCLUDES_DIR . 'Helpers/Autoloader.php';
\CF7AIC\Helpers\Autoloader::register();

/**
 * Activation hook.
 */
register_activation_hook(
	__FILE__,
	static function () {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		\CF7AIC\Helpers\Activator::activate();
	}
);

/**
 * Deactivation hook.
 */
register_deactivation_hook(
	__FILE__,
	static function () {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		\CF7AIC\Helpers\Deactivator::deactivate();
	}
);

/**
 * Boots the plugin once all active plugins (including Contact Form 7)
 * have registered their own hooks.
 */
add_action(
	'plugins_loaded',
	static function () {
		\CF7AIC\Plugin::get_instance()->init();
	}
);

/**
 * Returns the main plugin instance.
 *
 * @return \CF7AIC\Plugin
 */
function cf7aic(): \CF7AIC\Plugin {
	return \CF7AIC\Plugin::get_instance();
}
