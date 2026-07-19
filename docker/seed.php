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

$repository = new \CF7AIC\Database\SubmissionsRepository();

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

$fixtures = array(
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
		'priority'          => 'normal',
		// Deliberately below the 60% threshold so the low-confidence flag renders.
		'confidence'        => 34,
		'confidence_reason' => 'Message is short and ambiguous; the requested product is not identified.',
	),
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
