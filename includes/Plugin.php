<?php
/**
 * Root plugin class.
 *
 * Wires together all subsystems (settings, AI providers, CF7 submission
 * handling) and drives the plugin's request lifecycle.
 *
 * @package CF7AIC
 */

namespace CF7AIC;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CF7AIC\Admin\AdminPage;
use CF7AIC\Admin\AjaxController;
use CF7AIC\Admin\DashboardPage;
use CF7AIC\Admin\DashboardWidget;
use CF7AIC\Admin\InboxView;
use CF7AIC\Admin\Menu;
use CF7AIC\Admin\Notices;
use CF7AIC\Admin\SettingsController;
use CF7AIC\Admin\SettingsPage;
use CF7AIC\Admin\SubmissionDetailPage;
use CF7AIC\CF7\SubmissionHandler;
use CF7AIC\Database\Installer as DatabaseInstaller;
use CF7AIC\Database\SubmissionsRepository;
use CF7AIC\Services\AIService;
use CF7AIC\Services\ReplyService;
use CF7AIC\Services\SubmissionService;
use CF7AIC\Services\UsageTracker;
use CF7AIC\Settings\Repository;

/**
 * Class Plugin
 *
 * Main plugin orchestrator, instantiated once from the bootstrap file on
 * the `plugins_loaded` hook.
 */
final class Plugin {

	/**
	 * The single plugin instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Admin notice queue.
	 *
	 * @var Notices
	 */
	private Notices $notices;

	/**
	 * Whether Contact Form 7 is active and usable.
	 *
	 * @var bool
	 */
	private bool $cf7_available = false;

	/**
	 * Private constructor — use {@see self::get_instance()}.
	 */
	private function __construct() {
		$this->notices = new Notices();
	}

	/**
	 * Returns the singleton plugin instance, creating it on first call.
	 *
	 * @return Plugin
	 */
	public static function get_instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Boots the plugin.
	 *
	 * Called on `plugins_loaded`, after every other active plugin
	 * (including Contact Form 7) has already registered its hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->load_textdomain();

		if ( is_admin() ) {
			$this->notices->init();
		}

		$this->check_dependencies();

		if ( ! $this->cf7_available ) {
			return;
		}

		// Cheap no-op once already up to date; catches installs that were
		// upgraded in place without a deactivate/reactivate cycle.
		DatabaseInstaller::maybe_migrate();

		if ( is_admin() ) {
			$this->maybe_show_migration_notice();
		}

		$repository         = new Repository();
		$usage_tracker      = new UsageTracker();
		$submissions        = new SubmissionsRepository();
		$submission_service = new SubmissionService( $repository, $usage_tracker, $submissions, new AIService() );

		// Registered unconditionally, NOT nested under the is_admin() branch
		// below: Contact Form 7 submissions are processed either through
		// admin-ajax.php (where is_admin() is true) or, when AJAX is
		// disabled, through a normal frontend POST (where it is false).
		// Gating this on is_admin() would silently break the non-AJAX path.
		( new SubmissionHandler( $repository, $submission_service ) )->init();

		if ( is_admin() ) {
			$this->init_admin( $repository, $usage_tracker, $submissions );
		}

		/**
		 * Fires once CF7 AI Copilot has confirmed Contact Form 7 is present
		 * and finished its own bootstrap.
		 *
		 * @since 1.0.0
		 */
		do_action( 'cf7aic_loaded' );
	}

	/**
	 * Wires up the admin page: registers the menu, the top-level page
	 * renderer (Submissions / Settings), and the form/AJAX handlers.
	 *
	 * Only called in wp-admin, and only once Contact Form 7 has been
	 * confirmed active — the submenu's parent page (`wpcf7`) does not
	 * exist otherwise.
	 *
	 * @param Repository            $repository    Settings repository.
	 * @param UsageTracker          $usage_tracker Usage tracker.
	 * @param SubmissionsRepository $submissions   Submissions log repository.
	 *
	 * @return void
	 */
	private function init_admin( Repository $repository, UsageTracker $usage_tracker, SubmissionsRepository $submissions ): void {
		$reply_service = new ReplyService( $submissions );

		$dashboard_page = new DashboardPage( $submissions, $usage_tracker, $repository );
		$settings_page  = new SettingsPage( $repository, $usage_tracker );
		$inbox_view     = new InboxView( $submissions );
		$detail_page    = new SubmissionDetailPage( $submissions );
		$admin_page     = new AdminPage( $dashboard_page, $inbox_view, $detail_page, $settings_page, $submissions );

		( new Menu( $admin_page ) )->init();
		( new SettingsController( $repository ) )->init();
		( new AjaxController( $repository, $submissions, $reply_service ) )->init();
		( new DashboardWidget( $submissions, $usage_tracker ) )->init();
	}

	/**
	 * Shows a one-time notice after an existing install's submissions data
	 * has been migrated to the review-workflow schema — a "flash message"
	 * flag set by {@see DatabaseInstaller::maybe_migrate()}, since that
	 * migration can run on any request (including a frontend one), not
	 * only when an admin happens to be looking.
	 *
	 * @return void
	 */
	private function maybe_show_migration_notice(): void {
		if ( ! get_option( 'cf7aic_show_migration_notice' ) ) {
			return;
		}

		delete_option( 'cf7aic_show_migration_notice' );

		$this->notices->add_success(
			__( 'AI Copilot has been updated: it no longer sends AI replies automatically. Review and send suggested replies from the new AI Inbox instead.', 'cf7-ai-copilot' )
		);
	}

	/**
	 * Loads the plugin's translation files.
	 *
	 * @return void
	 */
	private function load_textdomain(): void {
		load_plugin_textdomain(
			'cf7-ai-copilot',
			false,
			dirname( CF7AIC_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Verifies that Contact Form 7 is installed and active.
	 *
	 * Sets {@see self::$cf7_available} and queues an admin notice on failure.
	 * The plugin never fatals or breaks other functionality when CF7 is
	 * missing — it simply stays dormant.
	 *
	 * @return void
	 */
	private function check_dependencies(): void {
		if ( ! defined( 'WPCF7_VERSION' ) ) {
			$this->notices->add_error(
				__( 'This plugin requires Contact Form 7 to be installed and active.', 'cf7-ai-copilot' )
			);

			return;
		}

		$this->cf7_available = true;
	}

	/**
	 * Returns whether Contact Form 7 was detected and is usable.
	 *
	 * @return bool
	 */
	public function is_cf7_available(): bool {
		return $this->cf7_available;
	}
}
