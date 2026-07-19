<?php
/**
 * Renders the tabbed Settings section of the Olmbox admin page.
 *
 * @package CF7AIC\Admin
 */

namespace CF7AIC\Admin;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CF7AIC\Services\UsageTracker;
use CF7AIC\Settings\Repository;

/**
 * Class SettingsPage
 *
 * Purely responsible for output: it validates which tab was requested and
 * includes the matching view partial. All persistence lives in
 * {@see SettingsController}. Rendered inside {@see AdminPage}, which owns
 * the page's outer chrome and navigation — including the Settings tab
 * list, built from {@see self::TABS} so the whitelist is never duplicated.
 */
final class SettingsPage {

	/**
	 * Map of valid tab slugs to their view partial file names.
	 *
	 * Using an explicit whitelist (rather than building a path from the
	 * requested tab directly) means a crafted `tab` query argument can
	 * never cause a different file to be included.
	 *
	 * @var array<string, string>
	 */
	public const TABS = array(
		'general'  => 'tab-general.php',
		'provider' => 'tab-provider.php',
		'prompt'   => 'tab-prompt.php',
		'usage'    => 'tab-usage.php',
		'help'     => 'tab-help.php',
	);

	/**
	 * Settings repository.
	 *
	 * @var Repository
	 */
	private Repository $repository;

	/**
	 * Usage tracker.
	 *
	 * @var UsageTracker
	 */
	private UsageTracker $usage_tracker;

	/**
	 * Constructor.
	 *
	 * @param Repository   $repository    Settings repository.
	 * @param UsageTracker $usage_tracker Usage tracker.
	 */
	public function __construct( Repository $repository, UsageTracker $usage_tracker ) {
		$this->repository    = $repository;
		$this->usage_tracker = $usage_tracker;
	}

	/**
	 * Renders the active tab's view partial. The tab nav itself lives in
	 * {@see AdminPage}'s sidebar, built from {@see self::TABS}.
	 *
	 * @return void
	 */
	public function render_body(): void {
		$tab = $this->get_current_tab();

		// Made available to the included view partial.
		$repository    = $this->repository;
		$usage_tracker = $this->usage_tracker;

		?>
		<div class="cf7aic-tab-panel">
			<?php require CF7AIC_PLUGIN_DIR . 'admin/views/' . self::TABS[ $tab ]; ?>
		</div>
		<?php
	}

	/**
	 * Returns the requested tab slug, falling back to "general" if missing
	 * or unrecognized.
	 *
	 * @return string
	 */
	public function get_current_tab(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab navigation, no state change.
		$requested = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';

		return isset( self::TABS[ $requested ] ) ? $requested : 'general';
	}

	/**
	 * Returns the human-readable label for a tab slug, for use by the
	 * sidebar nav this class no longer renders itself.
	 *
	 * @param string $slug Tab slug.
	 *
	 * @return string
	 */
	public function get_tab_label( string $slug ): string {
		$labels = array(
			'general'  => __( 'General', 'olmbox-ai-inbox-for-contact-form-7' ),
			'provider' => __( 'AI Provider', 'olmbox-ai-inbox-for-contact-form-7' ),
			'prompt'   => __( 'Prompt', 'olmbox-ai-inbox-for-contact-form-7' ),
			'usage'    => __( 'Usage', 'olmbox-ai-inbox-for-contact-form-7' ),
			'help'     => __( 'Help', 'olmbox-ai-inbox-for-contact-form-7' ),
		);

		return $labels[ $slug ] ?? $slug;
	}
}
