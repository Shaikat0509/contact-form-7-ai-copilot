<?php
/**
 * Stores and retrieves the AI Inbox submissions log.
 *
 * @package CF7AIC\Database
 */

namespace CF7AIC\Database;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SubmissionsRepository
 *
 * One row is written per Contact Form 7 submission to the configured
 * form, regardless of whether AI generation succeeded — so the AI Inbox
 * always shows what happened, including why AI was skipped (missing
 * key, quota reached) or failed.
 *
 * Each row carries two independent status values:
 *  - `ai_status`: whether AI generation itself succeeded (see the
 *    `AI_STATUS_*` constants) — set once, at insert time.
 *  - `status`: where the row sits in the human review workflow (see the
 *    `STATUS_*` constants) — starts at `new` and moves forward as the
 *    administrator reviews, sends, or archives it.
 *
 * The log is capped at {@see self::MAX_ROWS} most recent entries; older
 * rows are pruned automatically on insert so it can never grow without
 * bound on a busy site.
 */
final class SubmissionsRepository {

	/**
	 * Row has not yet been reviewed by an administrator.
	 *
	 * @var string
	 */
	public const STATUS_NEW = 'new';

	/**
	 * An administrator has looked at the row but not replied.
	 *
	 * @var string
	 */
	public const STATUS_REVIEWED = 'reviewed';

	/**
	 * A reply has been sent to the visitor.
	 *
	 * @var string
	 */
	public const STATUS_REPLIED = 'replied';

	/**
	 * The row has been archived (no reply intended).
	 *
	 * @var string
	 */
	public const STATUS_ARCHIVED = 'archived';

	/**
	 * Every enabled AI feature was generated successfully.
	 *
	 * @var string
	 */
	public const AI_STATUS_SUCCESS = 'success';

	/**
	 * Some AI generation succeeded and some failed.
	 *
	 * @var string
	 */
	public const AI_STATUS_PARTIAL = 'partial';

	/**
	 * AI generation was attempted and failed entirely.
	 *
	 * @var string
	 */
	public const AI_STATUS_FAILED = 'failed';

	/**
	 * No API key was configured, so no generation was attempted.
	 *
	 * @var string
	 */
	public const AI_STATUS_NO_API_KEY = 'no_api_key';

	/**
	 * Maximum number of rows retained. Older rows are pruned on insert.
	 *
	 * @var int
	 */
	private const MAX_ROWS = 200;

	/**
	 * Returns the fully-qualified table name.
	 *
	 * @return string
	 */
	private function table(): string {
		global $wpdb;

		return $wpdb->prefix . Installer::TABLE;
	}

	/**
	 * Inserts a new log row and prunes old rows beyond the retention cap.
	 *
	 * Expected keys in `$entry`: `form_id` (int), `form_title` (string),
	 * `visitor_email` (string), `visitor_phone` (string), `submitted_data`
	 * (array), `ai_status` (string), `provider` (string), `model` (string),
	 * `ai_summary` (string|null), `ai_reply` (string|null), `category`
	 * (string), `priority` (string), `confidence` (int|null),
	 * `confidence_reason` (string|null), `error_message` (string|null).
	 * Always inserted with the review workflow `status` set to `new`.
	 *
	 * @param array<string, mixed> $entry Log entry data.
	 *
	 * @return int The new row's ID, or 0 on failure.
	 */
	public function insert( array $entry ): int {
		global $wpdb;

		$now = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table; there is no WP API for it, and a write is never cached.
		$inserted = $wpdb->insert(
			$this->table(),
			array(
				'form_id'           => (int) $entry['form_id'],
				'form_title'        => (string) $entry['form_title'],
				'visitor_name'      => (string) ( $entry['visitor_name'] ?? '' ),
				'visitor_email'     => (string) $entry['visitor_email'],
				'visitor_phone'     => (string) ( $entry['visitor_phone'] ?? '' ),
				'submitted_data'    => wp_json_encode( $entry['submitted_data'] ),
				'status'            => self::STATUS_NEW,
				'ai_status'         => (string) $entry['ai_status'],
				'provider'          => (string) $entry['provider'],
				'model'             => (string) $entry['model'],
				'ai_summary'        => $entry['ai_summary'] ?? null,
				'ai_reply'          => $entry['ai_reply'] ?? null,
				'category'          => (string) ( $entry['category'] ?? '' ),
				'priority'          => (string) ( $entry['priority'] ?? '' ),
				'confidence'        => isset( $entry['confidence'] ) ? (int) $entry['confidence'] : null,
				'confidence_reason' => $entry['confidence_reason'] ?? null,
				'error_message'     => $entry['error_message'] ?? null,
				'created_at'        => $now,
				'updated_at'        => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return 0;
		}

		$this->prune();

		return (int) $wpdb->insert_id;
	}

	/**
	 * Returns a filtered, paginated page of log entries, most recent first.
	 *
	 * @param int                  $page     1-indexed page number.
	 * @param int                  $per_page Rows per page.
	 * @param array<string, mixed> $filters  Optional filters: `status`, `priority`,
	 *                                       `category` (exact match), `date_from`,
	 *                                       `date_to` (Y-m-d), `search` (matched
	 *                                       against form title, visitor email/phone,
	 *                                       and submitted data).
	 *
	 * @return array{items: array<int, array<string, mixed>>, total: int, total_pages: int}
	 */
	public function get_paginated( int $page = 1, int $per_page = 20, array $filters = array() ): array {
		global $wpdb;

		$page     = max( 1, $page );
		$per_page = max( 1, $per_page );
		$offset   = ( $page - 1 ) * $per_page;
		$table    = $this->table();

		[$where_sql, $where_values] = $this->build_where( $filters );

		// $table/$where_sql are built from hardcoded fragments and a fixed
		// column whitelist in build_where(); every user-supplied value is
		// always passed through $where_values/$per_page/$offset as
		// prepare() placeholders. The sniffs below can't statically verify
		// that $where_sql's placeholder count always matches $where_values,
		// since both are only known at runtime.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter
		if ( empty( $where_values ) ) {
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		} else {
			$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} {$where_sql}", $where_values ) );
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, form_id, form_title, visitor_name, visitor_email, visitor_phone, status, ai_status, category, priority, confidence, created_at
				FROM {$table}
				{$where_sql}
				ORDER BY id DESC
				LIMIT %d OFFSET %d",
				array_merge( $where_values, array( $per_page, $offset ) )
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter

		return array(
			'items'       => is_array( $rows ) ? $rows : array(),
			'total'       => $total,
			'total_pages' => (int) ceil( $total / $per_page ),
		);
	}

	/**
	 * Builds a `WHERE` clause and its matching prepare() values from a set
	 * of filters. Column names are drawn from a fixed whitelist — only the
	 * values are ever user-supplied, and those are always parameterized.
	 *
	 * @param array<string, mixed> $filters See {@see self::get_paginated()}.
	 *
	 * @return array{0: string, 1: array<int, mixed>}
	 */
	private function build_where( array $filters ): array {
		global $wpdb;

		$clauses = array();
		$values  = array();

		if ( ! empty( $filters['status'] ) ) {
			$clauses[] = 'status = %s';
			$values[]  = (string) $filters['status'];
		}

		if ( ! empty( $filters['priority'] ) ) {
			$clauses[] = 'priority = %s';
			$values[]  = (string) $filters['priority'];
		}

		if ( ! empty( $filters['category'] ) ) {
			$clauses[] = 'category = %s';
			$values[]  = (string) $filters['category'];
		}

		if ( ! empty( $filters['date_from'] ) ) {
			$clauses[] = 'created_at >= %s';
			$values[]  = $filters['date_from'] . ' 00:00:00';
		}

		if ( ! empty( $filters['date_to'] ) ) {
			$clauses[] = 'created_at <= %s';
			$values[]  = $filters['date_to'] . ' 23:59:59';
		}

		if ( ! empty( $filters['search'] ) ) {
			$like      = '%' . $wpdb->esc_like( (string) $filters['search'] ) . '%';
			$clauses[] = '( visitor_name LIKE %s OR visitor_email LIKE %s OR visitor_phone LIKE %s OR submitted_data LIKE %s )';
			array_push( $values, $like, $like, $like, $like );
		}

		if ( empty( $clauses ) ) {
			return array( '', array() );
		}

		return array( 'WHERE ' . implode( ' AND ', $clauses ), $values );
	}

	/**
	 * Returns a single full log entry, including the submitted data and
	 * any AI-generated content.
	 *
	 * @param int $id Row ID.
	 *
	 * @return array<string, mixed>|null
	 */
	public function get( int $id ): ?array {
		global $wpdb;

		$table = $this->table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is $wpdb->prefix + a hardcoded class constant, never user input; table names cannot be passed as prepare() placeholders. Custom table; not cached since a row can change between requests (edited/reviewed/replied).
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d",
				$id
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		if ( ! is_array( $row ) ) {
			return null;
		}

		$decoded               = json_decode( (string) $row['submitted_data'], true );
		$row['submitted_data'] = is_array( $decoded ) ? $decoded : array();

		return $row;
	}

	/**
	 * Persists an edited reply without changing the review workflow status.
	 *
	 * @param int    $id    Row ID.
	 * @param string $reply New reply text.
	 *
	 * @return bool
	 */
	public function save_draft_reply( int $id, string $reply ): bool {
		return $this->update(
			$id,
			array(
				'ai_reply'   => $reply,
				'updated_at' => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Marks a row as reviewed, if it hasn't progressed further already.
	 *
	 * @param int $id      Row ID.
	 * @param int $user_id ID of the reviewing administrator.
	 *
	 * @return bool
	 */
	public function mark_reviewed( int $id, int $user_id ): bool {
		global $wpdb;

		$table = $this->table();
		$now   = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is $wpdb->prefix + a hardcoded class constant, never user input; custom table, not cached since it's a write.
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET status = %s, reviewed_by = %d, reviewed_at = %s, updated_at = %s WHERE id = %d AND status = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				self::STATUS_REVIEWED,
				$user_id,
				$now,
				$now,
				$id,
				self::STATUS_NEW
			)
		);

		// Still record who/when even if the row had already moved past "new".
		if ( 0 === $updated ) {
			return $this->update(
				$id,
				array(
					'reviewed_by' => $user_id,
					'reviewed_at' => $now,
					'updated_at'  => $now,
				)
			);
		}

		return false !== $updated;
	}

	/**
	 * Records that a reply was sent, moving the row to `replied`.
	 *
	 * @param int    $id        Row ID.
	 * @param string $reply     The exact text that was sent (persisted as
	 *                          the row's reply, capturing any last edits).
	 * @param int    $user_id   ID of the administrator who sent it.
	 *
	 * @return bool
	 */
	public function record_reply_sent( int $id, string $reply, int $user_id ): bool {
		$now = current_time( 'mysql' );

		return $this->update(
			$id,
			array(
				'ai_reply'      => $reply,
				'status'        => self::STATUS_REPLIED,
				'reply_sent_at' => $now,
				'reply_sent_by' => $user_id,
				'updated_at'    => $now,
			)
		);
	}

	/**
	 * Archives a row.
	 *
	 * @param int $id Row ID.
	 *
	 * @return bool
	 */
	public function archive( int $id ): bool {
		return $this->update(
			$id,
			array(
				'status'     => self::STATUS_ARCHIVED,
				'updated_at' => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Permanently deletes a row.
	 *
	 * @param int $id Row ID.
	 *
	 * @return bool
	 */
	public function delete( int $id ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table; there is no WP API for it, and a delete is never cached.
		return false !== $wpdb->delete( $this->table(), array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Counts rows currently in a given workflow status.
	 *
	 * @param string $status One of the `STATUS_*` constants.
	 *
	 * @return int
	 */
	public function count_by_status( string $status ): int {
		global $wpdb;

		$table = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is $wpdb->prefix + a hardcoded class constant, never user input; custom table, changes on every review action so not cached.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", $status ) );
	}

	/**
	 * Counts rows in a given workflow status, created on or after a given
	 * MySQL datetime (e.g. "replied this month").
	 *
	 * @param string $status One of the `STATUS_*` constants.
	 * @param string $since  MySQL-formatted datetime.
	 *
	 * @return int
	 */
	public function count_by_status_since( string $status, string $since ): int {
		global $wpdb;

		$table = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is $wpdb->prefix + a hardcoded class constant, never user input; custom table, changes on every review action so not cached.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE status = %s AND created_at >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$status,
				$since
			)
		);
	}

	/**
	 * Returns the number of successful AI analyses per calendar day, for
	 * every day from `$since` to today — used to draw the Dashboard's
	 * usage chart from data that is already stored, with no separate
	 * tracking system.
	 *
	 * @param string $since MySQL-formatted date (Y-m-d) to start counting from.
	 *
	 * @return array<string, int> Map of `Y-m-d` date => count. Every day in
	 *                            the range is present, even if the count is 0.
	 */
	public function get_daily_ai_counts( string $since ): array {
		global $wpdb;

		$table = $this->table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is $wpdb->prefix + a hardcoded class constant, never user input; table names cannot be passed as prepare() placeholders. Custom table; the Dashboard chart needs a live count, not a cached one.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(created_at) AS day, COUNT(*) AS total
				FROM {$table}
				WHERE ai_status = %s AND created_at >= %s
				GROUP BY DATE(created_at)
				ORDER BY day ASC",
				self::AI_STATUS_SUCCESS,
				$since
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		$by_day = array();
		foreach ( (array) $rows as $row ) {
			$by_day[ $row['day'] ] = (int) $row['total'];
		}

		// Fill in every day in the range, including days with zero activity,
		// so the chart has a continuous, evenly-spaced series to plot.
		$counts  = array();
		$cursor  = new \DateTimeImmutable( $since );
		$today   = new \DateTimeImmutable( current_time( 'Y-m-d' ) );
		$one_day = new \DateInterval( 'P1D' );

		while ( $cursor <= $today ) {
			$day            = $cursor->format( 'Y-m-d' );
			$counts[ $day ] = $by_day[ $day ] ?? 0;
			$cursor         = $cursor->add( $one_day );
		}

		return $counts;
	}

	/**
	 * Counts rows created on or after a given MySQL datetime.
	 *
	 * @param string $since MySQL-formatted datetime.
	 *
	 * @return int
	 */
	public function count_created_since( string $since ): int {
		global $wpdb;

		$table = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is $wpdb->prefix + a hardcoded class constant, never user input; custom table, needs a live count for the Dashboard/widget.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE created_at >= %s", $since ) );
	}

	/**
	 * Returns the most recently created rows (summary columns only).
	 *
	 * @param int $limit Maximum number of rows.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_recent( int $limit = 5 ): array {
		global $wpdb;

		$table = $this->table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is $wpdb->prefix + a hardcoded class constant, never user input; custom table, needs a live list for the Dashboard/widget.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, form_title, visitor_name, visitor_email, status, priority, category, confidence, ai_summary, created_at FROM {$table} ORDER BY id DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$limit
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Applies a partial column update to one row.
	 *
	 * @param int                  $id     Row ID.
	 * @param array<string, mixed> $fields Column => value pairs to update.
	 *
	 * @return bool
	 */
	private function update( int $id, array $fields ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table; there is no WP API for it, and a write is never cached.
		return false !== $wpdb->update( $this->table(), $fields, array( 'id' => $id ) );
	}

	/**
	 * Deletes rows beyond the retention cap, keeping the most recent ones.
	 *
	 * @return void
	 */
	private function prune(): void {
		global $wpdb;

		$table = $this->table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is $wpdb->prefix + a hardcoded class constant, never user input; table names cannot be passed as prepare() placeholders.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE id NOT IN (
					SELECT id FROM ( SELECT id FROM {$table} ORDER BY id DESC LIMIT %d ) AS keep_ids
				)",
				self::MAX_ROWS
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
	}
}
