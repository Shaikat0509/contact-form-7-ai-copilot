<?php
/**
 * Renders the Olmbox admin page's outer shell: sidebar, topbar, and
 * routing to whichever section is active.
 *
 * @package CF7AIC\Admin
 */

namespace CF7AIC\Admin;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CF7AIC\Database\SubmissionsRepository;

/**
 * Class AdminPage
 *
 * The single page callback registered with WordPress. WordPress's own
 * admin bar and left admin menu are left fully intact; this class only
 * renders a self-contained shell inside the normal wp-admin content area —
 * a dark sidebar (Dashboard / AI Inbox / Settings, plus the current user)
 * and a topbar, with the active section's body delegated to
 * {@see DashboardPage}, {@see InboxView}, {@see SubmissionDetailPage}
 * (when an `id` is present within the AI Inbox section), or
 * {@see SettingsPage}.
 */
final class AdminPage {

	/**
	 * Valid top-level section slugs.
	 *
	 * @var string[]
	 */
	private const SECTIONS = array( 'dashboard', 'submissions', 'settings' );

	/**
	 * Dashboard section renderer.
	 *
	 * @var DashboardPage
	 */
	private DashboardPage $dashboard_page;

	/**
	 * AI Inbox list section renderer.
	 *
	 * @var InboxView
	 */
	private InboxView $inbox_view;

	/**
	 * Submission review screen renderer.
	 *
	 * @var SubmissionDetailPage
	 */
	private SubmissionDetailPage $detail_page;

	/**
	 * Settings section renderer.
	 *
	 * @var SettingsPage
	 */
	private SettingsPage $settings_page;

	/**
	 * Submissions log repository, used here only for the AI Inbox sidebar
	 * badge count.
	 *
	 * @var SubmissionsRepository
	 */
	private SubmissionsRepository $submissions;

	/**
	 * Constructor.
	 *
	 * @param DashboardPage         $dashboard_page Dashboard section renderer.
	 * @param InboxView             $inbox_view     AI Inbox list section renderer.
	 * @param SubmissionDetailPage  $detail_page    Submission review screen renderer.
	 * @param SettingsPage          $settings_page  Settings section renderer.
	 * @param SubmissionsRepository $submissions    Submissions log repository.
	 */
	public function __construct(
		DashboardPage $dashboard_page,
		InboxView $inbox_view,
		SubmissionDetailPage $detail_page,
		SettingsPage $settings_page,
		SubmissionsRepository $submissions
	) {
		$this->dashboard_page = $dashboard_page;
		$this->inbox_view     = $inbox_view;
		$this->detail_page    = $detail_page;
		$this->settings_page  = $settings_page;
		$this->submissions    = $submissions;
	}

	/**
	 * Renders the page.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( Menu::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'olmbox-ai-inbox-for-contact-form-7' ) );
		}

		$section              = $this->get_current_section();
		$submission_id        = $this->get_current_submission_id();
		$settings_tab         = $this->settings_page->get_current_tab();
		[ $title, $subtitle ] = $this->get_title_and_subtitle( $section, $submission_id, $settings_tab );
		?>
		<div class="wrap cf7aic-wrap">
			<?php
			/*
			 * WordPress core moves every admin_notices-hooked notice (ours
			 * and any other active plugin's) to right after the first
			 * heading — or this marker, if present — inside .wrap (see
			 * wp-admin/js/common.js). Our own <h1> sits deep inside the
			 * custom topbar below, so without this marker, core's JS drills
			 * in and inserts other plugins' notices into the middle of our
			 * layout. This keeps them at the top instead. It renders
			 * nothing visible (WordPress's own admin CSS sets
			 * `.wp-header-end { visibility: hidden; }`).
			 */
			?>
			<hr class="wp-header-end" />
			<?php $this->render_saved_notice(); ?>

			<div class="cf7aic-shell">
				<?php $this->render_sidebar( $section, $settings_tab ); ?>

				<div class="cf7aic-main">
					<div class="cf7aic-topbar">
						<div class="cf7aic-topbar__heading">
							<h1><?php echo esc_html( $title ); ?></h1>
							<?php if ( '' !== $subtitle ) : ?>
								<p class="cf7aic-topbar__subtitle"><?php echo esc_html( $subtitle ); ?></p>
							<?php endif; ?>
						</div>
					</div>

					<div class="cf7aic-section-body">
						<?php if ( 'dashboard' === $section ) : ?>
							<?php $this->dashboard_page->render_body(); ?>
						<?php elseif ( 'submissions' === $section && $submission_id > 0 ) : ?>
							<?php $this->detail_page->render_body( $submission_id ); ?>
						<?php elseif ( 'submissions' === $section ) : ?>
							<?php $this->inbox_view->render_body(); ?>
						<?php else : ?>
							<?php $this->settings_page->render_body(); ?>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders the dark sidebar: brand, primary nav (Dashboard / AI Inbox
	 * with a "needs review" badge / expandable Settings group built from
	 * {@see SettingsPage::TABS}), and the current user at the bottom.
	 *
	 * @param string $section      Active top-level section.
	 * @param string $settings_tab Active Settings tab (meaningful only when `$section` is "settings").
	 *
	 * @return void
	 */
	private function render_sidebar( string $section, string $settings_tab ): void {
		$needs_review = $this->submissions->count_by_status( SubmissionsRepository::STATUS_NEW );
		$user         = wp_get_current_user();
		?>
		<aside class="cf7aic-sidebar">
			<div class="cf7aic-sidebar__brand">
				<span class="cf7aic-sidebar__logo dashicons dashicons-email-alt2" aria-hidden="true"></span>
				<span class="cf7aic-sidebar__brand-text"><?php esc_html_e( 'Olmbox', 'olmbox-ai-inbox-for-contact-form-7' ); ?></span>
			</div>

			<nav class="cf7aic-sidebar__nav" aria-label="<?php esc_attr_e( 'Olmbox sections', 'olmbox-ai-inbox-for-contact-form-7' ); ?>">
				<a
					href="<?php echo esc_url( Menu::url( array( 'section' => 'dashboard' ) ) ); ?>"
					class="cf7aic-sidebar__item <?php echo 'dashboard' === $section ? 'cf7aic-sidebar__item--active' : ''; ?>"
					<?php echo 'dashboard' === $section ? 'aria-current="page"' : ''; ?>
				>
					<span class="dashicons dashicons-dashboard" aria-hidden="true"></span>
					<?php esc_html_e( 'Dashboard', 'olmbox-ai-inbox-for-contact-form-7' ); ?>
				</a>

				<a
					href="<?php echo esc_url( Menu::url( array( 'section' => 'submissions' ) ) ); ?>"
					class="cf7aic-sidebar__item <?php echo 'submissions' === $section ? 'cf7aic-sidebar__item--active' : ''; ?>"
					<?php echo 'submissions' === $section ? 'aria-current="page"' : ''; ?>
				>
					<span class="dashicons dashicons-email-alt" aria-hidden="true"></span>
					<?php esc_html_e( 'AI Inbox', 'olmbox-ai-inbox-for-contact-form-7' ); ?>
					<?php if ( $needs_review > 0 ) : ?>
						<span class="cf7aic-sidebar__badge"><?php echo esc_html( (string) $needs_review ); ?></span>
					<?php endif; ?>
				</a>

				<details class="cf7aic-sidebar__group" <?php echo 'settings' === $section ? 'open' : ''; ?>>
					<summary class="cf7aic-sidebar__item cf7aic-sidebar__item--group <?php echo 'settings' === $section ? 'cf7aic-sidebar__item--active' : ''; ?>">
						<span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>
						<?php esc_html_e( 'Settings', 'olmbox-ai-inbox-for-contact-form-7' ); ?>
					</summary>
					<div class="cf7aic-sidebar__subitems">
						<?php
						foreach ( SettingsPage::TABS as $slug => $partial ) :
							$tab_url = Menu::url(
								array(
									'section' => 'settings',
									'tab'     => $slug,
								)
							);
							?>
							<a
								href="<?php echo esc_url( $tab_url ); ?>"
								class="cf7aic-sidebar__subitem <?php echo ( 'settings' === $section && $slug === $settings_tab ) ? 'cf7aic-sidebar__subitem--active' : ''; ?>"
								<?php echo ( 'settings' === $section && $slug === $settings_tab ) ? 'aria-current="page"' : ''; ?>
							>
								<?php echo esc_html( $this->settings_page->get_tab_label( $slug ) ); ?>
							</a>
						<?php endforeach; ?>
					</div>
				</details>
			</nav>

			<div class="cf7aic-sidebar__footer">
				<a
					class="cf7aic-sidebar__rate-link"
					href="https://wordpress.org/support/plugin/olmbox-ai-inbox-for-contact-form-7/reviews/#new-post"
					target="_blank"
					rel="noopener noreferrer"
				>
					<span class="dashicons dashicons-star-filled" aria-hidden="true"></span>
					<?php esc_html_e( 'Rate Us', 'olmbox-ai-inbox-for-contact-form-7' ); ?>
				</a>

				<details class="cf7aic-sidebar__user">
					<summary class="cf7aic-sidebar__user-summary">
						<?php echo get_avatar( $user->ID, 28, '', '', array( 'class' => 'cf7aic-sidebar__avatar' ) ); ?>
						<span class="cf7aic-sidebar__user-name"><?php echo esc_html( $user->display_name ); ?></span>
					</summary>
					<div class="cf7aic-sidebar__user-menu">
						<a href="<?php echo esc_url( get_edit_profile_url( $user->ID ) ); ?>"><?php esc_html_e( 'Edit Profile', 'olmbox-ai-inbox-for-contact-form-7' ); ?></a>
						<a href="<?php echo esc_url( wp_logout_url( Menu::url() ) ); ?>"><?php esc_html_e( 'Log Out', 'olmbox-ai-inbox-for-contact-form-7' ); ?></a>
					</div>
				</details>
			</div>
		</aside>
		<?php
	}

	/**
	 * Returns the page title and subtitle for the active section, for the topbar.
	 *
	 * @param string $section       Active top-level section.
	 * @param int    $submission_id Active submission ID (0 if none), meaningful only within "submissions".
	 * @param string $settings_tab  Active Settings tab, meaningful only within "settings".
	 *
	 * @return array{0: string, 1: string} Title and subtitle (subtitle may be an empty string).
	 */
	private function get_title_and_subtitle( string $section, int $submission_id, string $settings_tab ): array {
		if ( 'dashboard' === $section ) {
			return array(
				__( 'Dashboard', 'olmbox-ai-inbox-for-contact-form-7' ),
				__( 'An overview of your Olmbox activity.', 'olmbox-ai-inbox-for-contact-form-7' ),
			);
		}

		if ( 'submissions' === $section && $submission_id > 0 ) {
			return array( __( 'Submission Review', 'olmbox-ai-inbox-for-contact-form-7' ), '' );
		}

		if ( 'submissions' === $section ) {
			return array(
				__( 'AI Inbox', 'olmbox-ai-inbox-for-contact-form-7' ),
				__( 'Review AI-drafted summaries and suggested replies before anything is sent.', 'olmbox-ai-inbox-for-contact-form-7' ),
			);
		}

		return array( $this->settings_page->get_tab_label( $settings_tab ), '' );
	}

	/**
	 * Returns the requested top-level section, falling back to "dashboard"
	 * if missing or unrecognized.
	 *
	 * @return string
	 */
	private function get_current_section(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only section navigation, no state change.
		$requested = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : 'dashboard';

		return in_array( $requested, self::SECTIONS, true ) ? $requested : 'dashboard';
	}

	/**
	 * Returns the requested submission ID, if any, from the AI Inbox section.
	 *
	 * @return int
	 */
	private function get_current_submission_id(): int {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page routing, no state change.
		return isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
	}

	/**
	 * Prints a one-time "settings saved" notice after a redirect from
	 * {@see SettingsController}.
	 *
	 * @return void
	 */
	private function render_saved_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display flag, no state change.
		if ( empty( $_GET['updated'] ) ) {
			return;
		}

		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html__( 'Settings saved.', 'olmbox-ai-inbox-for-contact-form-7' )
		);
	}
}
