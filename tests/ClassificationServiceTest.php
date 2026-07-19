<?php
/**
 * Tests for the AI classification normalizer.
 *
 * @package CF7AIC
 */

use CF7AIC\Services\ClassificationService;

/**
 * The model returns free-form text for category and priority, so every
 * value reaching the database passes through here first. These tests pin
 * the guarantee the rest of the plugin relies on: the return value is
 * always a key of the corresponding vocabulary, whatever the model said.
 */
class ClassificationServiceTest extends WP_UnitTestCase {

	/**
	 * Casing and surrounding whitespace from the model are tolerated.
	 *
	 * @return void
	 */
	public function test_normalizes_case_and_whitespace() {
		$this->assertSame( 'support', ClassificationService::normalize_category( '  SUPPORT  ' ) );
		$this->assertSame( 'high', ClassificationService::normalize_priority( 'High' ) );
	}

	/**
	 * Every declared vocabulary key survives a round trip unchanged.
	 *
	 * @return void
	 */
	public function test_every_declared_category_is_stable() {
		foreach ( array_keys( ClassificationService::CATEGORIES ) as $slug ) {
			$this->assertSame( $slug, ClassificationService::normalize_category( $slug ) );
		}

		foreach ( array_keys( ClassificationService::PRIORITIES ) as $slug ) {
			$this->assertSame( $slug, ClassificationService::normalize_priority( $slug ) );
		}
	}

	/**
	 * Anything unrecognized falls back rather than reaching the database.
	 *
	 * @return void
	 */
	public function test_unknown_values_fall_back_to_defaults() {
		$this->assertSame( ClassificationService::DEFAULT_CATEGORY, ClassificationService::normalize_category( 'urgent-refund' ) );
		$this->assertSame( ClassificationService::DEFAULT_CATEGORY, ClassificationService::normalize_category( '' ) );
		$this->assertSame( ClassificationService::DEFAULT_PRIORITY, ClassificationService::normalize_priority( 'CRITICAL' ) );
		$this->assertSame( ClassificationService::DEFAULT_PRIORITY, ClassificationService::normalize_priority( '' ) );
	}

	/**
	 * A model returning JSON injection or markup cannot smuggle a value
	 * through: sanitize_key() strips it and the whitelist rejects it.
	 *
	 * @return void
	 */
	public function test_hostile_input_is_rejected() {
		$this->assertSame( ClassificationService::DEFAULT_CATEGORY, ClassificationService::normalize_category( '<script>alert(1)</script>' ) );
		$this->assertSame( ClassificationService::DEFAULT_PRIORITY, ClassificationService::normalize_priority( "high'; DROP TABLE wp_posts; --" ) );
	}

	/**
	 * `job_application` is the only multi-word category slug, and its
	 * label — "Job Application" — is the form a model most often echoes
	 * back instead of the slug it was asked for. Separators must fold to
	 * underscores, since sanitize_key() alone would strip the space and
	 * yield "jobapplication", silently classifying the submission as
	 * "other".
	 *
	 * @return void
	 */
	public function test_multiword_label_resolves_to_its_slug() {
		foreach ( array( 'Job Application', 'job application', 'job-application', 'Job  Application', ' job_application ' ) as $variant ) {
			$this->assertSame(
				'job_application',
				ClassificationService::normalize_category( $variant ),
				sprintf( 'Variant "%s" should resolve to job_application.', $variant )
			);
		}
	}

	/**
	 * Folding separators must not make unrelated values collide into a
	 * real slug — only genuine label forms should resolve.
	 *
	 * @return void
	 */
	public function test_separator_folding_does_not_overmatch() {
		$this->assertSame( 'other', ClassificationService::normalize_category( 'job applications pipeline' ) );
		$this->assertSame( 'other', ClassificationService::normalize_category( 'not a category' ) );
	}

	/**
	 * Every human-readable label should resolve to the slug it labels —
	 * the model is given both and may return either.
	 *
	 * @return void
	 */
	public function test_every_label_resolves_to_its_own_slug() {
		foreach ( ClassificationService::CATEGORIES as $slug => $label ) {
			$this->assertSame( $slug, ClassificationService::normalize_category( $label ) );
		}

		foreach ( ClassificationService::PRIORITIES as $slug => $label ) {
			$this->assertSame( $slug, ClassificationService::normalize_priority( $label ) );
		}
	}
}
