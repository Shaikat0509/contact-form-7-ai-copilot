<?php
/**
 * Seeds the AI Inbox with representative submissions.
 *
 * Dev tooling for the Docker environment only — never shipped, and it
 * writes through SubmissionsRepository rather than raw SQL so that
 * running it also exercises the insert path.
 *
 * Usage: ./docker/wp.sh seed
 *
 * Covers each `ai_status` in the vocabulary plus a below-threshold
 * confidence score, because those are exactly the branches the Inbox and
 * detail views render differently and that an empty table cannot show.
 *
 * @package CF7AIC
 */

// Run under WP-CLI only; there is no ABSPATH guard case for a CLI script.
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 1 );
}

$repository    = new \CF7AIC\Database\SubmissionsRepository();
$usage_tracker = new \CF7AIC\Services\UsageTracker();

$form_id    = 0;
$form_title = 'Contact form 1';
$forms      = get_posts(
	array(
		'post_type'      => 'wpcf7_contact_form',
		'posts_per_page' => 1,
		'fields'         => 'ids',
	)
);

if ( ! empty( $forms ) ) {
	$form_id    = (int) $forms[0];
	$form_title = get_the_title( $form_id );
}

/*
 * Ordered oldest first: the Inbox lists newest first, so the two failure
 * fixtures live at the top of this array in order to sort to the bottom
 * of the list. They still need to exist — they drive the error branches
 * of the Inbox and detail views — but they should not be the first thing
 * shown, in the UI or in a directory screenshot.
 */
$fixtures = array(
	array(
		'visitor_name'      => 'Priya Raman',
		'visitor_email'     => 'priya@example.test',
		'message'           => 'Please delete my account and any data you hold about me.',
		'ai_status'         => \CF7AIC\Database\SubmissionsRepository::AI_STATUS_FAILED,
		'ai_summary'        => null,
		'ai_reply'          => null,
		'category'          => '',
		'priority'          => '',
		'confidence'        => null,
		'confidence_reason' => null,
		'error_message'     => 'Could not reach the AI provider: cURL error 28: Operation timed out.',
	),
	array(
		'visitor_name'      => 'Sam Okonkwo',
		'visitor_email'     => 'sam@example.test',
		'message'           => 'Do you offer volume pricing for 50+ seats?',
		'ai_status'         => \CF7AIC\Database\SubmissionsRepository::AI_STATUS_NO_API_KEY,
		'ai_summary'        => null,
		'ai_reply'          => null,
		'category'          => '',
		'priority'          => '',
		'confidence'        => null,
		'confidence_reason' => null,
		'error_message'     => 'No API key has been configured.',
	),
	array(
		'visitor_name'      => 'Marisol Reyes',
		'visitor_email'     => 'marisol@example.test',
		'message'           => 'We are a team of 40 and currently on the starter plan. What does moving to the business tier involve, and can we keep our existing integrations?',
		'ai_status'         => \CF7AIC\Database\SubmissionsRepository::AI_STATUS_SUCCESS,
		'ai_summary'        => 'Existing starter-plan customer with a 40-person team asking what upgrading to the business tier involves and whether integrations carry over.',
		'ai_reply'          => "Hi Marisol,\n\nThanks for reaching out. Moving from starter to business is handled in place — your account keeps its history and every existing integration continues to work, so there is no migration step on your side.\n\nFor a team of 40 the business tier also adds shared inboxes and audit logs, which tend to matter at that size. I am happy to walk through it on a short call if that would help.\n\nBest regards",
		'category'          => 'sales',
		'priority'          => 'high',
		'confidence'        => 94,
		'confidence_reason' => 'Clear upgrade intent, explicit team size, and a specific question about integrations.',
		'workflow'          => 'replied',
	),
	array(
		'visitor_name'      => 'Aleksander Nowak',
		'visitor_email'     => 'a.nowak@example.test',
		'message'           => 'The export button on the reports page does nothing when I click it. Firefox 141, Windows 11. It worked last week.',
		'ai_status'         => \CF7AIC\Database\SubmissionsRepository::AI_STATUS_SUCCESS,
		'ai_summary'        => 'Report export button unresponsive on Firefox 141 / Windows 11; worked previously, so likely a recent regression.',
		'ai_reply'          => "Hi Aleksander,\n\nThank you for the report, and sorry for the trouble. A browser and OS plus \"it worked last week\" is exactly what we need to narrow this down, so that is genuinely helpful.\n\nI have passed this to the team to reproduce on Firefox 141. I will follow up as soon as I have something concrete.\n\nBest regards",
		'category'          => 'technical',
		'priority'          => 'high',
		'confidence'        => 88,
		'confidence_reason' => 'Specific, reproducible bug report with browser, OS, and a regression window.',
		'workflow'          => 'reviewed',
	),
	array(
		'visitor_name'      => 'Hannah Boateng',
		'visitor_email'     => 'hannah.b@example.test',
		'message'           => 'Invoice INV-2291 was charged twice this month. Could you check and refund the duplicate?',
		'ai_status'         => \CF7AIC\Database\SubmissionsRepository::AI_STATUS_SUCCESS,
		'ai_summary'        => 'Customer reports a duplicate charge on invoice INV-2291 and is requesting a refund.',
		'ai_reply'          => "Hi Hannah,\n\nThanks for flagging this, and apologies for the duplicate charge. I can see both entries against INV-2291 and have queued the second one for refund — it usually clears back to the original payment method within five working days.\n\nI will confirm here once it has been processed.\n\nBest regards",
		'category'          => 'billing',
		'priority'          => 'high',
		'confidence'        => 92,
		'confidence_reason' => 'Explicit invoice reference and an unambiguous refund request.',
	),
	array(
		'visitor_name'      => 'Ravi Chandran',
		'visitor_email'     => 'ravi@example.test',
		'message'           => 'Hi — I run a WordPress agency in Bristol and we would like to talk about a reseller arrangement for our client sites.',
		'ai_status'         => \CF7AIC\Database\SubmissionsRepository::AI_STATUS_SUCCESS,
		'ai_summary'        => 'Agency owner in Bristol enquiring about a reseller arrangement covering their client sites.',
		'ai_reply'          => "Hi Ravi,\n\nThanks for getting in touch. We do work with agencies on reseller terms, and the arrangement usually depends on how many client sites you expect to cover and whether you want billing consolidated.\n\nCould you share a rough number of sites? I can then send over the terms that fit best.\n\nBest regards",
		'category'          => 'partnership',
		'priority'          => 'medium',
		'confidence'        => 86,
		'confidence_reason' => 'Clear partnership enquiry, though the scale of the arrangement is unstated.',
	),
	array(
		'visitor_name'      => 'Elena Fischer',
		'visitor_email'     => 'elena.fischer@example.test',
		'message'           => 'I saw the senior support engineer opening on your careers page. Is it still open, and is it remote within the EU?',
		'ai_status'         => \CF7AIC\Database\SubmissionsRepository::AI_STATUS_SUCCESS,
		'ai_summary'        => 'Candidate asking whether the senior support engineer role is still open and whether it is EU-remote.',
		'ai_reply'          => "Hi Elena,\n\nThanks for your interest — the senior support engineer role is still open, and it is remote for anyone based in the EU.\n\nApplications go through the form linked at the bottom of the careers page. Do send any questions my way in the meantime.\n\nBest regards",
		'category'          => 'job_application',
		'priority'          => 'low',
		'confidence'        => 90,
		'confidence_reason' => 'Directly references a named role from the careers page.',
		'workflow'          => 'reviewed',
	),
	array(
		'visitor_name'      => 'Dana Whitfield',
		'visitor_email'     => 'dana@example.test',
		'message'           => 'My order #4821 was supposed to arrive on Tuesday and it still has not shown up. Can you tell me where it is?',
		'ai_status'         => \CF7AIC\Database\SubmissionsRepository::AI_STATUS_SUCCESS,
		'ai_summary'        => 'Customer is asking about a late delivery for order #4821, expected Tuesday.',
		'ai_reply'          => "Hi Dana,\n\nThanks for getting in touch, and sorry your order has not arrived yet. I have looked up #4821 and it is currently with the courier. I will chase this and follow up today with a tracking update.\n\nBest regards",
		'category'          => 'support',
		'priority'          => 'high',
		'confidence'        => 91,
		'confidence_reason' => 'Message states an explicit order number and a clear delivery complaint.',
	),
	array(
		'visitor_name'      => 'Toby Ferrero',
		'visitor_email'     => 'toby@example.test',
		'message'           => 'hey do you do the thing for the bigger plan? asking for our team',
		'ai_status'         => \CF7AIC\Database\SubmissionsRepository::AI_STATUS_SUCCESS,
		'ai_summary'        => 'Vague enquiry, possibly about upgrading to a larger plan for a team.',
		'ai_reply'          => "Hi Toby,\n\nHappy to help — could you tell me a little more about what your team needs? That way I can point you at the right plan.\n\nBest regards",
		'category'          => 'sales',
		'priority'          => 'medium',
		// Deliberately below the 60% threshold so the low-confidence flag renders.
		'confidence'        => 34,
		'confidence_reason' => 'Message is short and ambiguous; the requested product is not identified.',
	),
);

$created = 0;

foreach ( $fixtures as $fixture ) {
	$id = $repository->insert(
		array(
			'form_id'           => $form_id,
			'form_title'        => $form_title,
			'visitor_name'      => $fixture['visitor_name'],
			'visitor_email'     => $fixture['visitor_email'],
			'visitor_phone'     => '',
			'submitted_data'    => array(
				'your-name'    => $fixture['visitor_name'],
				'your-email'   => $fixture['visitor_email'],
				'your-message' => $fixture['message'],
			),
			'provider'          => 'openai',
			'model'             => 'gpt-4o-mini',
			'ai_status'         => $fixture['ai_status'],
			'ai_summary'        => $fixture['ai_summary'],
			'ai_reply'          => $fixture['ai_reply'],
			'category'          => $fixture['category'],
			'priority'          => $fixture['priority'],
			'confidence'        => $fixture['confidence'],
			'confidence_reason' => $fixture['confidence_reason'],
			'error_message'     => $fixture['error_message'] ?? null,
		)
	);

	if ( 0 === $id ) {
		WP_CLI::warning( sprintf( 'Insert failed for %s', $fixture['visitor_email'] ) );
		continue;
	}

	// insert() always starts a row at `new`, so anything further along the
	// review workflow has to be advanced through the same methods the
	// admin UI calls — which also exercises those transitions.
	$workflow = $fixture['workflow'] ?? 'new';
	$actor    = get_users(
		array(
			'role'    => 'administrator',
			'number'  => 1,
			'fields'  => 'ID',
			'orderby' => 'ID',
		)
	);
	$actor_id = $actor ? (int) $actor[0] : 1;

	if ( 'reviewed' === $workflow ) {
		$repository->mark_reviewed( $id, $actor_id );
	} elseif ( 'replied' === $workflow ) {
		$repository->record_reply_sent( $id, (string) $fixture['ai_reply'], $actor_id );
	}

	// A successful analysis is what the usage tracker counts, so seeding
	// rows without incrementing it leaves the Usage tab reading zero
	// against an Inbox full of completed analyses.
	if ( \CF7AIC\Database\SubmissionsRepository::AI_STATUS_SUCCESS === $fixture['ai_status'] ) {
		$usage_tracker->increment();
	}

	++$created;
}

WP_CLI::success(
	sprintf(
		'Seeded %d submissions (form_id=%d). Needing review: %d.',
		$created,
		$form_id,
		$repository->count_by_status( \CF7AIC\Database\SubmissionsRepository::STATUS_NEW )
	)
);
