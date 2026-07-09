<?php
/**
 * Handles plugin activation.
 *
 * @package CF7AIC\Helpers
 */

namespace CF7AIC\Helpers;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CF7AIC\Database\Installer as DatabaseInstaller;

/**
 * Class Activator
 *
 * Runs once when the plugin is activated. Settings are stored as plain
 * WordPress options, created lazily (with sane defaults) by the classes
 * that own them — the only thing activation provisions is the
 * submissions log table and bookkeeping metadata.
 */
final class Activator {

	/**
	 * Runs the activation routine.
	 *
	 * @return void
	 */
	public static function activate(): void {
		DatabaseInstaller::maybe_migrate();

		// Track the version that last ran activation, and when the plugin
		// was first activated. Never overwritten on subsequent activations.
		add_option( 'cf7aic_activated_at', current_time( 'mysql' ), '', false );
		update_option( 'cf7aic_version', CF7AIC_VERSION, false );
	}
}
