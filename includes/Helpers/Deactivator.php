<?php
/**
 * Handles plugin deactivation.
 *
 * @package CF7AIC\Helpers
 */

namespace CF7AIC\Helpers;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Deactivator
 *
 * Runs once when the plugin is deactivated. Deactivation never deletes
 * plugin data — that only happens in uninstall.php, and only for data
 * the plugin itself created. See {@see Uninstaller}.
 */
final class Deactivator {

	/**
	 * Runs the deactivation routine.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		/**
		 * Fires after Olmbox has been deactivated.
		 *
		 * Reserved for future phases that may need to clear scheduled
		 * events or transient state on deactivation.
		 *
		 * @since 1.0.0
		 */
		do_action( 'cf7aic_deactivated' );
	}
}
