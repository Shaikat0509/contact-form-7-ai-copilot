<?php
/**
 * Plugin Name: Olmbox dev mail routing
 * Description: Routes all outbound mail to Mailpit. Local Docker environment only — never shipped.
 *
 * Loaded as a must-use plugin in the Docker verification environment so
 * wp_mail() succeeds. That matters more than it looks: Contact Form 7
 * only fires `wpcf7_mail_sent` after its own mail send succeeds, and
 * that hook is the entry point to this plugin's entire submission path.
 * With no mail transport, CF7 reports mail_failed, the hook never fires,
 * and no submission is ever logged — so the plugin appears broken when
 * it is behaving exactly as designed.
 *
 * @package CF7AIC
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'phpmailer_init',
	static function ( $phpmailer ) {
		$phpmailer->isSMTP();
		$phpmailer->Host     = 'mailpit';
		$phpmailer->Port     = 1025;
		$phpmailer->SMTPAuth = false;
		$phpmailer->SMTPAutoTLS = false;
	}
);
