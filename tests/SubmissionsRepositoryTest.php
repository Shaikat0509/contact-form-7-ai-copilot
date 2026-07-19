<?php
/**
 * Tests for the submissions log repository.
 *
 * @package CF7AIC
 */

use CF7AIC\Database\SubmissionsRepository;

/**
 * Covers the review workflow's state transitions and the retention cap.
 *
 * The cap is not just an implementation detail: the plugin's privacy copy
 * tells users only the most recent 200 submissions are kept, so a broken
 * prune would make a published claim untrue.
 */
class SubmissionsRepositoryTest extends WP_UnitTestCase {

	/**
	 * Repository under test.
	 *
	 * @var SubmissionsRepository
	 */
	private $repository;

	/**
	 * Sets up the repository before each test.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$this->repository = new SubmissionsRepository();
	}

	/**
	 * Builds a valid log entry, overridable per test.
	 *
	 * @param array<string, mixed> $overrides Fields to override.
	 *
	 * @return array<string, mixed>
	 */
	private function entry( array $overrides = array() ) {
		return array_merge(
			array(
				'form_id'           => 1,
				'form_title'        => 'Contact form 1',
				'visitor_name'      => 'Dana Whitfield',
				'visitor_email'     => 'dana@example.test',
				'visitor_phone'     => '',
				'submitted_data'    => array( 'your-message' => 'Where is my order?' ),
				'provider'          => 'openai',
				'model'             => 'gpt-4o-mini',
				'ai_status'         => SubmissionsRepository::AI_STATUS_SUCCESS,
				'ai_summary'        => 'Customer asking about a late order.',
				'ai_reply'          => 'Sorry about the delay.',
				'category'          => 'support',
				'priority'          => 'high',
				'confidence'        => 91,
				'confidence_reason' => 'Explicit order reference.',
				'error_message'     => null,
			),
			$overrides
		);
	}

	/**
	 * A new row always starts in the review workflow's initial state,
	 * regardless of how the AI generation itself went.
	 *
	 * @return void
	 */
	public function test_insert_always_starts_as_new() {
		$id = $this->repository->insert( $this->entry() );
		$this->assertGreaterThan( 0, $id );

		$row = $this->repository->get( $id );
		$this->assertSame( SubmissionsRepository::STATUS_NEW, $row['status'] );
		$this->assertSame( SubmissionsRepository::AI_STATUS_SUCCESS, $row['ai_status'] );
	}

	/**
	 * A failed AI generation still produces a row — the submission itself
	 * must never be lost because the provider was unreachable.
	 *
	 * @return void
	 */
	public function test_failed_generation_still_records_the_submission() {
		$id = $this->repository->insert(
			$this->entry(
				array(
					'ai_status'     => SubmissionsRepository::AI_STATUS_FAILED,
					'ai_summary'    => null,
					'ai_reply'      => null,
					'category'      => '',
					'priority'      => '',
					'confidence'    => null,
					'error_message' => 'Could not reach the AI provider.',
				)
			)
		);

		$row = $this->repository->get( $id );

		$this->assertSame( SubmissionsRepository::STATUS_NEW, $row['status'] );
		$this->assertSame( SubmissionsRepository::AI_STATUS_FAILED, $row['ai_status'] );
		$this->assertNull( $row['confidence'] );
		$this->assertNotEmpty( $row['error_message'] );
		// The visitor's own message is what must survive a provider outage.
		$this->assertSame( 'Where is my order?', $row['submitted_data']['your-message'] );
	}

	/**
	 * Submitted data round-trips through JSON encoding intact, including
	 * characters that would break a naive escape.
	 *
	 * get() decodes the column back into an array, so callers never see
	 * the stored JSON — that decode is part of what is asserted here.
	 *
	 * @return void
	 */
	public function test_submitted_data_round_trips() {
		$id = $this->repository->insert(
			$this->entry(
				array(
					'submitted_data' => array(
						'your-message' => 'Quote: "it\'s broken" — 100% & <b>bold</b>',
						'your-name'    => 'Åsa Ñuñez',
					),
				)
			)
		);

		$row = $this->repository->get( $id );

		$this->assertIsArray( $row['submitted_data'], 'get() should decode the stored JSON.' );
		$this->assertSame( 'Quote: "it\'s broken" — 100% & <b>bold</b>', $row['submitted_data']['your-message'] );
		$this->assertSame( 'Åsa Ñuñez', $row['submitted_data']['your-name'] );
	}

	/**
	 * Marking reviewed records who did it, which the detail screen shows.
	 *
	 * @return void
	 */
	public function test_mark_reviewed_records_the_reviewer() {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$id      = $this->repository->insert( $this->entry() );

		$this->assertTrue( $this->repository->mark_reviewed( $id, $user_id ) );

		$row = $this->repository->get( $id );
		$this->assertSame( SubmissionsRepository::STATUS_REVIEWED, $row['status'] );
		$this->assertSame( $user_id, (int) $row['reviewed_by'] );
		$this->assertNotEmpty( $row['reviewed_at'] );
	}

	/**
	 * Saving a draft edits the suggested reply without advancing the
	 * workflow — the admin has not sent anything yet.
	 *
	 * @return void
	 */
	public function test_saving_a_draft_does_not_change_status() {
		$id = $this->repository->insert( $this->entry() );

		$this->assertTrue( $this->repository->save_draft_reply( $id, 'My edited reply.' ) );

		$row = $this->repository->get( $id );
		$this->assertSame( 'My edited reply.', $row['ai_reply'] );
		$this->assertSame( SubmissionsRepository::STATUS_NEW, $row['status'] );
		$this->assertEmpty( $row['reply_sent_at'] );
	}

	/**
	 * Recording a sent reply is the only transition into `replied`, and it
	 * stamps who sent it and when.
	 *
	 * @return void
	 */
	public function test_recording_a_sent_reply_stamps_sender_and_time() {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$id      = $this->repository->insert( $this->entry() );

		$this->assertTrue( $this->repository->record_reply_sent( $id, 'Final reply text.', $user_id ) );

		$row = $this->repository->get( $id );
		$this->assertSame( SubmissionsRepository::STATUS_REPLIED, $row['status'] );
		$this->assertSame( 'Final reply text.', $row['ai_reply'] );
		$this->assertSame( $user_id, (int) $row['reply_sent_by'] );
		$this->assertNotEmpty( $row['reply_sent_at'] );
	}

	/**
	 * Deleting removes the row outright; there is no soft delete.
	 *
	 * @return void
	 */
	public function test_delete_removes_the_row() {
		$id = $this->repository->insert( $this->entry() );

		$this->assertTrue( $this->repository->delete( $id ) );
		$this->assertNull( $this->repository->get( $id ) );
	}

	/**
	 * Reading a row that does not exist returns null rather than raising.
	 *
	 * @return void
	 */
	public function test_getting_a_missing_row_returns_null() {
		$this->assertNull( $this->repository->get( 999999 ) );
	}

	/**
	 * The badge count in the sidebar is driven by this, and must count
	 * only rows still awaiting review.
	 *
	 * @return void
	 */
	public function test_count_by_status_tracks_transitions() {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$first   = $this->repository->insert( $this->entry() );
		$this->repository->insert( $this->entry() );

		$this->assertSame( 2, $this->repository->count_by_status( SubmissionsRepository::STATUS_NEW ) );

		$this->repository->mark_reviewed( $first, $user_id );

		$this->assertSame( 1, $this->repository->count_by_status( SubmissionsRepository::STATUS_NEW ) );
		$this->assertSame( 1, $this->repository->count_by_status( SubmissionsRepository::STATUS_REVIEWED ) );
	}

	/**
	 * Filtering backs the Inbox's status/priority controls.
	 *
	 * @return void
	 */
	public function test_pagination_filters_by_priority() {
		$this->repository->insert( $this->entry( array( 'priority' => 'high' ) ) );
		$this->repository->insert( $this->entry( array( 'priority' => 'low' ) ) );
		$this->repository->insert( $this->entry( array( 'priority' => 'low' ) ) );

		$result = $this->repository->get_paginated( 1, 20, array( 'priority' => 'low' ) );

		$this->assertSame( 2, $result['total'] );
		$this->assertCount( 2, $result['items'] );

		foreach ( $result['items'] as $item ) {
			$this->assertSame( 'low', $item['priority'] );
		}
	}

	/**
	 * The retention cap is a published privacy claim, not just cleanup:
	 * the plugin tells users only the most recent 200 rows are kept.
	 *
	 * Inserts past the cap and asserts both that the total stops growing
	 * and that it is the *oldest* row that was discarded.
	 *
	 * @return void
	 */
	public function test_retention_cap_prunes_oldest_rows() {
		global $wpdb;

		$table = $wpdb->prefix . \CF7AIC\Database\Installer::TABLE;

		$first_id = $this->repository->insert( $this->entry( array( 'visitor_email' => 'oldest@example.test' ) ) );

		for ( $i = 0; $i < 205; $i++ ) {
			$this->repository->insert( $this->entry() );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- test assertion against the plugin's own table.
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

		$this->assertLessThanOrEqual( 200, $total, 'Retention cap should bound the table size.' );
		$this->assertNull( $this->repository->get( $first_id ), 'The oldest row should be the one pruned.' );
	}
}
