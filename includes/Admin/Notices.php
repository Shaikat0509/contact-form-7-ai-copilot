<?php
/**
 * Admin notice queue.
 *
 * A single, reusable place for any part of the plugin to queue a
 * dismissible-style admin notice. Rendered once on `admin_notices`.
 *
 * @package CF7AIC\Admin
 */

namespace CF7AIC\Admin;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Notices
 *
 * Collects notices during the request and prints them on `admin_notices`.
 * Instantiated once by the root Plugin class.
 */
final class Notices {

	/**
	 * Queued notices.
	 *
	 * Each entry is an array with keys `message` (string, already escaped
	 * for output) and `type` (one of 'error', 'warning', 'success', 'info').
	 *
	 * @var array<int, array{message: string, type: string}>
	 */
	private array $notices = array();

	/**
	 * Registers the `admin_notices` rendering hook.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_notices', array( $this, 'render' ) );
	}

	/**
	 * Queues an error notice.
	 *
	 * @param string $message Notice text (plain text; will be escaped on output).
	 *
	 * @return void
	 */
	public function add_error( string $message ): void {
		$this->queue( $message, 'error' );
	}

	/**
	 * Queues a warning notice.
	 *
	 * @param string $message Notice text (plain text; will be escaped on output).
	 *
	 * @return void
	 */
	public function add_warning( string $message ): void {
		$this->queue( $message, 'warning' );
	}

	/**
	 * Queues a success notice.
	 *
	 * @param string $message Notice text (plain text; will be escaped on output).
	 *
	 * @return void
	 */
	public function add_success( string $message ): void {
		$this->queue( $message, 'success' );
	}

	/**
	 * Adds a notice to the internal queue.
	 *
	 * @param string $message Notice text.
	 * @param string $type    One of 'error', 'warning', 'success', 'info'.
	 *
	 * @return void
	 */
	private function queue( string $message, string $type ): void {
		$this->notices[] = array(
			'message' => $message,
			'type'    => $type,
		);
	}

	/**
	 * Prints all queued notices as standard WordPress admin notice markup.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( empty( $this->notices ) ) {
			return;
		}

		foreach ( $this->notices as $notice ) {
			printf(
				'<div class="notice notice-%1$s"><p><strong>%2$s</strong> %3$s</p></div>',
				esc_attr( $notice['type'] ),
				esc_html__( 'Contact Form 7 AI Copilot:', 'cf7-ai-copilot' ),
				esc_html( $notice['message'] )
			);
		}
	}
}
