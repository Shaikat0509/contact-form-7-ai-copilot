<?php
/**
 * Tests for the 1.x -> 2.x schema migration.
 *
 * @package CF7AIC
 */

use CF7AIC\Database\Installer;
use CF7AIC\Database\SubmissionsRepository;

/**
 * The riskiest migration in the plugin: `status` meant "AI generation
 * outcome" in 1.x and means "review workflow state" in 2.x. The column is
 * renamed to `ai_status` by hand before dbDelta() repurposes the name,
 * because dbDelta can add and alter columns but cannot rename them.
 *
 * Getting this wrong on a real install silently relabels every historical
 * row, so it is worth testing against an actual 1.x-shaped table rather
 * than trusting the code by inspection.
 *
 * These tests perform DDL, which implicitly commits and so escapes the
 * per-test transaction WP_UnitTestCase relies on. The table is therefore
 * rebuilt explicitly in tear_down.
 */
class InstallerMigrationTest extends WP_UnitTestCase {

	/**
	 * Option name tracking the applied schema version.
	 *
	 * @var string
	 */
	const VERSION_OPTION = 'cf7aic_submissions_schema_version';

	/**
	 * Fully-qualified submissions table name.
	 *
	 * @var string
	 */
	private $table;

	/**
	 * Sets up a 1.x-shaped table containing rows to migrate.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		global $wpdb;

		$this->table = $wpdb->prefix . Installer::TABLE;

		$this->drop_table();
		$this->create_v1_table();

		delete_option( self::VERSION_OPTION );
		delete_option( 'cf7aic_show_migration_notice' );
	}

	/**
	 * Restores the current schema for subsequent tests.
	 *
	 * @return void
	 */
	public function tear_down() {
		$this->drop_table();
		Installer::create_table();

		parent::tear_down();
	}

	/**
	 * Drops the submissions table.
	 *
	 * @return void
	 */
	private function drop_table() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- test fixture teardown against the plugin's own table.
		$wpdb->query( "DROP TABLE IF EXISTS {$this->table}" );
	}

	/**
	 * Recreates the 1.x schema: a single `status` column holding the AI
	 * generation outcome, and no `ai_status` column at all.
	 *
	 * @return void
	 */
	private function create_v1_table() {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- test fixture reproducing a historical schema.
		$wpdb->query(
			"CREATE TABLE {$this->table} (
				id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				form_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
				form_title VARCHAR(255) NOT NULL DEFAULT '',
				visitor_name VARCHAR(255) NOT NULL DEFAULT '',
				visitor_email VARCHAR(255) NOT NULL DEFAULT '',
				submitted_data LONGTEXT NOT NULL,
				status VARCHAR(30) NOT NULL DEFAULT '',
				provider VARCHAR(50) NOT NULL DEFAULT '',
				model VARCHAR(100) NOT NULL DEFAULT '',
				ai_summary LONGTEXT NULL,
				ai_reply LONGTEXT NULL,
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id)
			)"
		);

		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$this->table}
					( form_id, form_title, visitor_email, submitted_data, status, provider, model, ai_summary )
				VALUES
					( 1, 'Contact form 1', %s, %s, 'success', 'openai', 'gpt-4o-mini', 'A summary.' ),
					( 1, 'Contact form 1', %s, %s, 'failed', 'openai', 'gpt-4o-mini', NULL )",
				'dana@example.test',
				'{"your-message":"Where is my order?"}',
				'priya@example.test',
				'{"your-message":"Delete my account."}'
			)
		);
		// phpcs:enable
	}

	/**
	 * Fetches a column's values, ordered by id.
	 *
	 * @param string $column Column name (must be a literal, never input).
	 *
	 * @return array<int, string>
	 */
	private function column( $column ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- test assertion; $column is a literal from the test itself.
		return $wpdb->get_col( "SELECT {$column} FROM {$this->table} ORDER BY id" );
	}

	/**
	 * The historical AI outcome must survive under its new column name.
	 *
	 * @return void
	 */
	public function test_ai_outcome_is_preserved_under_the_new_column() {
		Installer::maybe_migrate();

		$this->assertSame(
			array( 'success', 'failed' ),
			$this->column( 'ai_status' ),
			'1.x status values should be copied into ai_status.'
		);
	}

	/**
	 * Every pre-existing row predates the review workflow, so all of them
	 * should land in `new` — not inherit a meaningless workflow state from
	 * the old column.
	 *
	 * @return void
	 */
	public function test_existing_rows_reset_to_the_initial_workflow_state() {
		Installer::maybe_migrate();

		$this->assertSame(
			array( SubmissionsRepository::STATUS_NEW, SubmissionsRepository::STATUS_NEW ),
			$this->column( 'status' )
		);
	}

	/**
	 * The 2.x columns the review workflow depends on should exist after
	 * dbDelta has run.
	 *
	 * @return void
	 */
	public function test_review_workflow_columns_are_added() {
		global $wpdb;

		Installer::maybe_migrate();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- test assertion against the plugin's own table.
		$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$this->table}" );

		foreach ( array( 'ai_status', 'reviewed_by', 'reviewed_at', 'reply_sent_at', 'reply_sent_by', 'confidence', 'priority', 'category' ) as $expected ) {
			$this->assertContains( $expected, $columns );
		}
	}

	/**
	 * Existing installs get a one-time notice, because "AI replies are no
	 * longer sent automatically" is a behaviour change they did not ask
	 * for and cannot infer from the UI.
	 *
	 * @return void
	 */
	public function test_existing_install_is_flagged_for_the_migration_notice() {
		Installer::maybe_migrate();

		$this->assertSame( '1', get_option( 'cf7aic_show_migration_notice' ) );
	}

	/**
	 * Re-running the migration must not relabel already-migrated data —
	 * maybe_migrate() runs on every plugins_loaded.
	 *
	 * @return void
	 */
	public function test_migration_is_idempotent() {
		Installer::maybe_migrate();

		// Simulate real post-migration activity before the second run.
		$repository = new SubmissionsRepository();
		$user_id    = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$ids        = $this->column( 'id' );
		$repository->mark_reviewed( (int) $ids[0], $user_id );

		delete_option( self::VERSION_OPTION );
		Installer::maybe_migrate();

		$this->assertSame(
			array( 'success', 'failed' ),
			$this->column( 'ai_status' ),
			'A second run should not overwrite ai_status.'
		);
		$this->assertSame(
			SubmissionsRepository::STATUS_REVIEWED,
			$this->column( 'status' )[0],
			'A second run should not reset a row that has since been reviewed.'
		);
	}

	/**
	 * Once current, the migration is a single get_option() and does no
	 * database work — it runs on every request.
	 *
	 * @return void
	 */
	public function test_migration_short_circuits_once_current() {
		global $wpdb;

		Installer::maybe_migrate();

		$queries_before = $wpdb->num_queries;
		Installer::maybe_migrate();

		$this->assertSame(
			$queries_before,
			$wpdb->num_queries,
			'An up-to-date install should issue no further queries (the option is cached).'
		);
	}
}
