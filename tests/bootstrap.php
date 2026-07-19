<?php
/**
 * PHPUnit bootstrap for the WordPress integration test suite.
 *
 * Runs inside the Docker container (see docker/wp.sh test), where
 * WordPress core lives at /var/www/html and the core test library has
 * been installed by docker/install-wp-tests.sh.
 *
 * @package CF7AIC
 */

$cf7aic_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $cf7aic_tests_dir ) {
	$cf7aic_tests_dir = '/var/www/html/wp-tests-lib';
}

if ( ! file_exists( $cf7aic_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find the WordPress test library at {$cf7aic_tests_dir}.\n";
	echo "Run ./docker/install-wp-tests.sh first.\n";
	exit( 1 );
}

require_once $cf7aic_tests_dir . '/includes/functions.php';

/**
 * Loads Contact Form 7 and then this plugin, in that order.
 *
 * CF7 first because this plugin's bootstrap checks for WPCF7_VERSION and
 * stays dormant without it — loading them the other way round would give
 * every test a plugin that deliberately did nothing.
 */
tests_add_filter(
	'muplugins_loaded',
	static function () {
		$cf7 = WP_PLUGIN_DIR . '/contact-form-7/wp-contact-form-7.php';

		if ( file_exists( $cf7 ) ) {
			require_once $cf7;
		}

		require_once dirname( __DIR__ ) . '/cf7-ai-copilot.php';
	}
);

require $cf7aic_tests_dir . '/includes/bootstrap.php';

/*
 * Create the plugin's custom table once, here, rather than per test.
 *
 * WP_UnitTestCase wraps each test in a transaction and rolls it back, so
 * rows written by a test disappear afterwards — but CREATE TABLE is DDL
 * and triggers an implicit commit, which would break that isolation if
 * it ran inside a test.
 */
\CF7AIC\Database\Installer::create_table();
