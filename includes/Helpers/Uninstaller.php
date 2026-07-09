<?php
/**
 * Handles complete removal of plugin data on uninstall.
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
 * Class Uninstaller
 *
 * Called only from uninstall.php (never from deactivation). Removes every
 * option, transient, and the submissions log table the plugin created.
 */
final class Uninstaller {

	/**
	 * Removes all plugin data.
	 *
	 * @return void
	 */
	public static function uninstall(): void {
		global $wpdb;

		DatabaseInstaller::drop_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( 'cf7aic_' ) . '%'
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_cf7aic_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_cf7aic_' ) . '%'
			)
		);

		wp_cache_flush();
	}
}
