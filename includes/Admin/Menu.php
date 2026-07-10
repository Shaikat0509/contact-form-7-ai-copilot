<?php
/**
 * Registers the admin menu and enqueues page-scoped assets.
 *
 * @package CF7AIC\Admin
 */

namespace CF7AIC\Admin;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Menu
 *
 * Adds the "AI Copilot" submenu under Contact Form 7, and makes sure the
 * plugin's CSS/JS are only ever loaded on that one admin page.
 */
final class Menu {

	/**
	 * The slug used for this plugin's settings page.
	 *
	 * @var string
	 */
	public const PAGE_SLUG = 'shaikat-ai-inbox-for-contact-form-7';

	/**
	 * The parent menu slug (Contact Form 7's own top-level menu).
	 *
	 * @var string
	 */
	private const PARENT_SLUG = 'wpcf7';

	/**
	 * The capability required to view and manage these settings.
	 *
	 * @var string
	 */
	public const CAPABILITY = 'manage_options';

	/**
	 * The page hook suffix returned by add_submenu_page(), used to scope
	 * asset loading to this page only.
	 *
	 * @var string
	 */
	private string $page_hook = '';

	/**
	 * The top-level admin page renderer.
	 *
	 * @var AdminPage
	 */
	private AdminPage $admin_page;

	/**
	 * Constructor.
	 *
	 * @param AdminPage $admin_page The top-level admin page renderer.
	 */
	public function __construct( AdminPage $admin_page ) {
		$this->admin_page = $admin_page;
	}

	/**
	 * Registers WordPress hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_dashboard_assets' ) );
	}

	/**
	 * Registers the submenu page under Contact Form 7.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		$this->page_hook = add_submenu_page(
			self::PARENT_SLUG,
			__( 'AI Copilot', 'shaikat-ai-inbox-for-contact-form-7' ),
			__( 'AI Copilot', 'shaikat-ai-inbox-for-contact-form-7' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( $this->admin_page, 'render' )
		);
	}

	/**
	 * Enqueues the plugin's admin CSS and JS — only on its own settings page.
	 *
	 * @param string $hook The current admin page's hook suffix.
	 *
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		if ( '' === $this->page_hook || $hook !== $this->page_hook ) {
			return;
		}

		wp_enqueue_style(
			'cf7aic-admin',
			CF7AIC_PLUGIN_URL . 'admin/assets/css/admin.css',
			array(),
			CF7AIC_VERSION
		);

		wp_enqueue_script(
			'cf7aic-admin',
			CF7AIC_PLUGIN_URL . 'admin/assets/js/admin.js',
			array(),
			CF7AIC_VERSION,
			true
		);

		wp_localize_script(
			'cf7aic-admin',
			'cf7aicAdmin',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'inboxUrl' => self::url( array( 'section' => 'submissions' ) ),
				'nonce'    => wp_create_nonce( AjaxController::NONCE_ACTION ),
				'actions'  => array(
					'testConnection' => 'cf7aic_test_connection',
					'listModels'     => 'cf7aic_list_models',
					'sendReply'      => 'cf7aic_send_reply',
					'saveDraft'      => 'cf7aic_save_draft',
					'markReviewed'   => 'cf7aic_mark_reviewed',
					'archive'        => 'cf7aic_archive',
					'delete'         => 'cf7aic_delete_submission',
				),
				'strings'  => array(
					'testing'       => __( 'Testing connection…', 'shaikat-ai-inbox-for-contact-form-7' ),
					'genericError'  => __( 'Something went wrong. Please try again.', 'shaikat-ai-inbox-for-contact-form-7' ),
					'loadingModels' => __( 'Loading models…', 'shaikat-ai-inbox-for-contact-form-7' ),
					'loadModels'    => __( 'Load Models', 'shaikat-ai-inbox-for-contact-form-7' ),
					'saving'        => __( 'Saving…', 'shaikat-ai-inbox-for-contact-form-7' ),
					'sending'       => __( 'Sending…', 'shaikat-ai-inbox-for-contact-form-7' ),
					'confirmDelete' => __( 'Permanently delete this submission? This cannot be undone.', 'shaikat-ai-inbox-for-contact-form-7' ),
				),
			)
		);
	}

	/**
	 * Enqueues just the plugin's CSS (badges, widget layout) on the main
	 * WordPress Dashboard — needed for the AI Copilot dashboard widget.
	 * No JS/localized data here; the widget has no interactive behavior.
	 *
	 * @param string $hook The current admin page's hook suffix.
	 *
	 * @return void
	 */
	public function enqueue_dashboard_assets( string $hook ): void {
		if ( 'index.php' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'cf7aic-admin',
			CF7AIC_PLUGIN_URL . 'admin/assets/css/admin.css',
			array(),
			CF7AIC_VERSION
		);
	}

	/**
	 * Builds a URL to this plugin's admin page, optionally with extra
	 * query args (e.g. `section`, `tab`, `paged`).
	 *
	 * @param array $args Additional query args to merge in.
	 *
	 * @return string
	 */
	public static function url( array $args = array() ): string {
		return add_query_arg(
			array_merge( array( 'page' => self::PAGE_SLUG ), $args ),
			admin_url( 'admin.php' )
		);
	}
}
