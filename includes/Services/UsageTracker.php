<?php
/**
 * Tracks and enforces the monthly AI generation quota.
 *
 * @package CF7AIC\Services
 */

namespace CF7AIC\Services;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class UsageTracker
 *
 * The free plan allows a fixed number of AI generations per calendar
 * month (see `CF7AIC_MONTHLY_LIMIT`). Usage is stored as a single option
 * containing the month it applies to and a running count; the count is
 * reset lazily — the first time this class is used in a new month — so
 * no cron job is required to keep it accurate.
 */
final class UsageTracker {

	/**
	 * Option name for usage tracking.
	 *
	 * @var string
	 */
	private const OPTION_USAGE = 'cf7aic_usage';

	/**
	 * Rough average tokens (prompt + completion) per AI Copilot analysis
	 * call, used only to show a ballpark "Estimated token usage" figure
	 * on the Usage tab. The plugin does not meter real per-call token
	 * counts from provider responses, so this is deliberately labeled as
	 * an estimate rather than presented as exact usage.
	 *
	 * @var int
	 */
	private const AVERAGE_TOKENS_PER_GENERATION = 800;

	/**
	 * Returns the monthly generation limit.
	 *
	 * @return int
	 */
	public function get_limit(): int {
		return (int) CF7AIC_MONTHLY_LIMIT;
	}

	/**
	 * Returns the number of AI generations used so far this month.
	 *
	 * @return int
	 */
	public function get_count(): int {
		return $this->get_current_state()['count'];
	}

	/**
	 * Returns the number of AI generations remaining this month.
	 *
	 * @return int
	 */
	public function get_remaining(): int {
		return max( 0, $this->get_limit() - $this->get_count() );
	}

	/**
	 * Returns whether the monthly quota has been reached.
	 *
	 * @return bool
	 */
	public function is_limit_reached(): bool {
		return $this->get_count() >= $this->get_limit();
	}

	/**
	 * Records one AI generation against the current month's quota.
	 *
	 * Callers are expected to check {@see self::is_limit_reached()} first;
	 * this method does not itself refuse to increment past the limit, so
	 * usage figures remain accurate even if called concurrently.
	 *
	 * @return void
	 */
	public function increment(): void {
		$state = $this->get_current_state();

		++$state['count'];

		update_option( self::OPTION_USAGE, $state, false );
	}

	/**
	 * Returns a rough estimate of tokens used this month, based on a fixed
	 * average tokens-per-generation figure — not real per-call metering.
	 *
	 * @return int
	 */
	public function get_estimated_tokens(): int {
		return $this->get_count() * self::AVERAGE_TOKENS_PER_GENERATION;
	}

	/**
	 * Returns the date usage will next reset (the first day of next month),
	 * formatted using the site's configured date format.
	 *
	 * @return string
	 */
	public function get_reset_date(): string {
		$first_of_next_month = strtotime( 'first day of next month', current_time( 'timestamp' ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested

		return wp_date( get_option( 'date_format', 'F j, Y' ), $first_of_next_month );
	}

	/**
	 * Returns the current month's usage state, resetting it first if the
	 * stored state belongs to a previous month.
	 *
	 * @return array{month: string, count: int}
	 */
	private function get_current_state(): array {
		$current_month = current_time( 'Y-m' );

		$stored = get_option( self::OPTION_USAGE, array() );

		if ( ! is_array( $stored ) || ( $stored['month'] ?? '' ) !== $current_month ) {
			$stored = array(
				'month' => $current_month,
				'count' => 0,
			);

			update_option( self::OPTION_USAGE, $stored, false );
		}

		$stored['count'] = (int) ( $stored['count'] ?? 0 );

		return $stored;
	}
}
