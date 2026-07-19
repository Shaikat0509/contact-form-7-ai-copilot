<?php
/**
 * Uninstall routine for Olmbox AI Inbox for Contact Form 7.
 *
 * Executed by WordPress when the plugin is deleted from the Plugins screen
 * (never on simple deactivation). WordPress guarantees `WP_UNINSTALL_PLUGIN`
 * is defined and that this file runs inside the WordPress context.
 *
 * @package CF7AIC
 * @link    https://developer.wordpress.org/plugins/plugin-basics/uninstall-methods/
 */

// Security check — must be invoked by WordPress core during plugin deletion.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

define( 'CF7AIC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CF7AIC_INCLUDES_DIR', CF7AIC_PLUGIN_DIR . 'includes/' );

require_once CF7AIC_INCLUDES_DIR . 'Helpers/Autoloader.php';
\CF7AIC\Helpers\Autoloader::register();

\CF7AIC\Helpers\Uninstaller::uninstall();
