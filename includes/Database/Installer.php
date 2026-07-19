<?php
/**
 * Creates, upgrades, and removes the submissions log table.
 *
 * @package CF7AIC\Database
 */

namespace CF7AIC\Database;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Installer
 *
 * Owns the one custom table this plugin uses: a log of AI-processed
 * Contact Form 7 submissions, shown on the AI Inbox admin screen.
 */
final class Installer {

	/**
	 * The name of the submissions table, without the site's table prefix.
	 *
	 * @var string
	 */
	public const TABLE = 'cf7aic_submissions';

	/**
	 * Current schema version. Bumping this triggers {@see self::maybe_migrate()}
	 * to re-run on the next `plugins_loaded`.
	 *
	 * @var string
	 */
	private const SCHEMA_VERSION = '2.0.1';

	/**
	 * Option name tracking which schema version has been applied.
	 *
	 * @var string
	 */
	private const VERSION_OPTION = 'cf7aic_submissions_schema_version';

	/**
	 * Runs the schema migration if the installed version is out of date.
	 *
	 * Safe to call on every `plugins_loaded` — it is a single cheap
	 * `get_option()` call once already up to date. Handles three cases:
	 * a brand new install (no table yet), an install still on the 1.x
	 * schema (single `status` column meaning "AI generation outcome"),
	 * and an already-migrated install (no-op).
	 *
	 * @return void
	 */
	public static function maybe_migrate(): void {
		if ( self::SCHEMA_VERSION === get_option( self::VERSION_OPTION, '' ) ) {
			return;
		}

		global $wpdb;

		$table        = $wpdb->prefix . self::TABLE;
		$table_exists = self::table_exists( $table );

		if ( $table_exists ) {
			self::migrate_v1_status_column( $table );
		}

		self::create_table();

		if ( $table_exists ) {
			// Every row that existed before this migration predates the
			// review workflow, so none of it has been reviewed/replied to yet.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is $wpdb->prefix + a hardcoded class constant, never user input; one-time schema migration, not a cacheable data read.
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table} SET status = %s WHERE status NOT IN ( 'new', 'reviewed', 'replied', 'archived' )", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- $table is $wpdb->prefix + a hardcoded class constant, never user input.
					'new'
				)
			);

			/**
			 * Fires once, after existing submissions data has been migrated
			 * to the review-workflow schema.
			 *
			 * @since 1.0.0
			 */
			do_action( 'cf7aic_submissions_migrated' );

			// A flash-style flag: shown once on the next admin page load
			// (see Plugin::maybe_show_migration_notice()), then cleared.
			// Existing installs need to know Olmbox no longer sends
			// anything automatically — that's a behavior change, not just
			// a schema detail.
			update_option( 'cf7aic_show_migration_notice', '1', false );
		}

		update_option( self::VERSION_OPTION, self::SCHEMA_VERSION, false );
	}

	/**
	 * Creates (or upgrades, via `dbDelta`'s additive diffing) the
	 * submissions table to the current schema.
	 *
	 * @return void
	 */
	public static function create_table(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name      = $wpdb->prefix . self::TABLE;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			form_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			form_title VARCHAR(255) NOT NULL DEFAULT '',
			visitor_name VARCHAR(255) NOT NULL DEFAULT '',
			visitor_email VARCHAR(255) NOT NULL DEFAULT '',
			visitor_phone VARCHAR(50) NOT NULL DEFAULT '',
			submitted_data LONGTEXT NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'new',
			ai_status VARCHAR(30) NOT NULL DEFAULT '',
			provider VARCHAR(50) NOT NULL DEFAULT '',
			model VARCHAR(100) NOT NULL DEFAULT '',
			ai_summary LONGTEXT NULL,
			ai_reply LONGTEXT NULL,
			category VARCHAR(50) NOT NULL DEFAULT '',
			priority VARCHAR(20) NOT NULL DEFAULT '',
			confidence TINYINT(3) UNSIGNED NULL,
			confidence_reason TEXT NULL,
			error_message TEXT NULL,
			reviewed_by BIGINT(20) UNSIGNED NULL,
			reviewed_at DATETIME NULL,
			reply_sent_at DATETIME NULL,
			reply_sent_by BIGINT(20) UNSIGNED NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NULL,
			PRIMARY KEY (id),
			KEY form_id (form_id),
			KEY created_at (created_at),
			KEY status (status),
			KEY priority (priority),
			KEY category (category)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Drops the submissions table entirely.
	 *
	 * Called only from uninstall.php.
	 *
	 * @return void
	 */
	public static function drop_table(): void {
		global $wpdb;

		$table_name = $wpdb->prefix . self::TABLE;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table_name is $wpdb->prefix + a hardcoded class constant, never user input; table names cannot be passed as prepare() placeholders.
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
	}

	/**
	 * Whether the given fully-qualified table already exists.
	 *
	 * @param string $table Fully-qualified (prefixed) table name.
	 *
	 * @return bool
	 */
	private static function table_exists( string $table ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time schema check, not a data query.
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
	}

	/**
	 * Preserves the 1.x schema's `status` column — which meant "AI
	 * generation outcome" (success/partial/failed/...) — under its new
	 * name `ai_status`, before `create_table()` repurposes `status` itself
	 * to mean "review workflow state". `dbDelta()` can add and alter
	 * columns but cannot rename or move data between them, so this one
	 * step has to run as a plain query first.
	 *
	 * @param string $table Fully-qualified (prefixed) table name.
	 *
	 * @return void
	 */
	private static function migrate_v1_status_column( string $table ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is $wpdb->prefix + a hardcoded class constant, never user input.
		$existing_columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );

		if ( in_array( 'ai_status', $existing_columns, true ) || ! in_array( 'status', $existing_columns, true ) ) {
			// Either already migrated, or this is a table shape we don't
			// recognize (e.g. already-current schema) — nothing to do.
			return;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is $wpdb->prefix + a hardcoded class constant, never user input.
		$wpdb->query( "ALTER TABLE {$table} ADD COLUMN ai_status VARCHAR(30) NOT NULL DEFAULT '' AFTER visitor_email" );
		$wpdb->query( "UPDATE {$table} SET ai_status = status" );
		// phpcs:enable
	}
}
