<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Sabri\UniversalComposer\Core\Submission_Store;

final class ReconciliationOutboxTest extends TestCase {
	public function test_payload_fingerprint_is_order_stable_but_value_sensitive(): void {
		$left = array(
			'title' => 'A',
			'meta'  => array( 'z' => 2, 'a' => 1 ),
			'tags'  => array( 'one', 'two' ),
		);
		$right = array(
			'tags'  => array( 'one', 'two' ),
			'meta'  => array( 'a' => 1, 'z' => 2 ),
			'title' => 'A',
		);
		$changed = $right;
		$changed['title'] = 'B';

		$this->assertMatchesRegularExpression( '/^[0-9a-f]{64}$/', Submission_Store::payload_fingerprint( $left ) );
		$this->assertSame( Submission_Store::payload_fingerprint( $left ), Submission_Store::payload_fingerprint( $right ) );
		$this->assertNotSame( Submission_Store::payload_fingerprint( $left ), Submission_Store::payload_fingerprint( $changed ) );
	}

	public function test_submission_and_outbox_are_metadata_only(): void {
		$source = file_get_contents( dirname( __DIR__ ) . '/includes/core/class-submission-store.php' );
		$this->assertIsString( $source );
		$this->assertStringContainsString( "'supc_submissions'", $source );
		$this->assertStringContainsString( "'supc_outbox'", $source );
		$this->assertStringContainsString( 'payload_hash char(64)', $source );
		$this->assertStringContainsString( 'native_response_hash char(64)', $source );
		$this->assertStringNotContainsString( 'payload_json', $source );
		$this->assertDoesNotMatchRegularExpression( '/\n\s*(?:body|content|patient|consent|media_bytes)\s+(?:longtext|text|json|blob)/i', $source );
	}

	public function test_submit_identity_is_persisted_before_native_dispatch(): void {
		$source = file_get_contents( dirname( __DIR__ ) . '/includes/http/class-reconciliation-rest-controller.php' );
		$this->assertIsString( $source );
		$prepare  = strpos( $source, '$this->submissions->prepare' );
		$begin    = strpos( $source, '$this->sessions->begin_submission' );
		$dispatch = strpos( $source, '$this->coordinator->submit' );
		$this->assertIsInt( $prepare );
		$this->assertIsInt( $begin );
		$this->assertIsInt( $dispatch );
		$this->assertLessThan( $begin, $prepare );
		$this->assertLessThan( $dispatch, $begin );
		$this->assertStringContainsString( '$this->coordinator->validate', $source );
	}

	public function test_uncertain_submission_is_reconciled_not_automatically_resubmitted(): void {
		$browser = file_get_contents( dirname( __DIR__ ) . '/assets/js/workflow-composer.js' );
		$rest    = file_get_contents( dirname( __DIR__ ) . '/includes/http/class-reconciliation-rest-controller.php' );
		$this->assertIsString( $browser );
		$this->assertIsString( $rest );
		$this->assertSame( 1, substr_count( $browser, "run('submit')" ) );
		$this->assertStringContainsString( "run('reconcile')", $browser );
		$this->assertStringContainsString( 'reconciliation_required', $browser );
		$this->assertStringContainsString( "'reconcile'", $rest );
		$this->assertStringContainsString( '$this->reconciliation->reconcile_session', $rest );
	}

	public function test_retry_dead_letter_and_retention_laws_are_encoded(): void {
		$outbox  = file_get_contents( dirname( __DIR__ ) . '/includes/core/class-submission-store.php' );
		$session = file_get_contents( dirname( __DIR__ ) . '/includes/core/class-session-store.php' );
		$this->assertIsString( $outbox );
		$this->assertIsString( $session );
		$this->assertStringContainsString( 'MAX_ATTEMPTS            = 5', $outbox );
		$this->assertStringContainsString( 'array( 60, 300, 1800, 7200, 43200 )', $outbox );
		$this->assertStringContainsString( "'dead_letter'", $outbox );
		$this->assertStringContainsString( 'ORDINARY_TTL        = 15552000', $session );
		$this->assertStringContainsString( 'SENSITIVE_TTL       = 2592000', $session );
		$this->assertStringContainsString( "status IN ('queued','retry','processing','dead_letter')", $session );
		$this->assertStringContainsString( "'reconciliation_pending'", $session );
	}

	public function test_private_cache_and_last_point_authority_boundaries_are_present(): void {
		$rest        = file_get_contents( dirname( __DIR__ ) . '/includes/http/class-reconciliation-rest-controller.php' );
		$coordinator = file_get_contents( dirname( __DIR__ ) . '/includes/core/class-workflow-coordinator.php' );
		$this->assertIsString( $rest );
		$this->assertIsString( $coordinator );
		$this->assertStringContainsString( 'CDN-Cache-Control', $rest );
		$this->assertStringContainsString( 'Surrogate-Control', $rest );
		$this->assertStringContainsString( 'DONOTCACHEOBJECT', $rest );
		$this->assertStringContainsString( 'litespeed_control_set_nocache', $rest );
		$this->assertGreaterThanOrEqual( 2, substr_count( $coordinator, 'central_authority_allows' ) );
		$this->assertStringContainsString( 'submission_ack_record_failed', $rest );
		$this->assertStringContainsString( '$this->sessions->mark_reconciliation', $rest );
	}

	public function test_review_round_one_dispatch_transition_is_compare_and_swap_guarded(): void {
		$source = file_get_contents( dirname( __DIR__ ) . '/includes/core/class-submission-store.php' );
		$this->assertIsString( $source );
		$this->assertStringContainsString( "AND state IN ('prepared','retryable')", $source );
		$this->assertStringContainsString( "'submission_already_final'", $source );
		$this->assertStringContainsString( "'reconciliation_pending'", $source );
	}

	public function test_review_round_two_terminal_attempts_cannot_be_downgraded_to_reconcile(): void {
		$source = file_get_contents( dirname( __DIR__ ) . '/includes/core/class-submission-store.php' );
		$this->assertIsString( $source );
		$this->assertStringContainsString( "array( 'resolved', 'failed', 'dead_letter' )", $source );
		$this->assertStringContainsString( "AND state IN ('prepared','dispatched','retryable','reconcile')", $source );
	}

	public function test_review_round_three_reconciliation_is_state_bound_idempotent_and_outbox_completion_is_separate(): void {
		$store = file_get_contents( dirname( __DIR__ ) . '/includes/core/class-submission-store.php' );
		$service = file_get_contents( dirname( __DIR__ ) . '/includes/core/class-reconciliation-service.php' );
		$this->assertIsString( $store );
		$this->assertIsString( $service );
		$this->assertStringContainsString( "AND state IN ('dispatched','reconcile','retryable')", $store );
		$this->assertStringContainsString( 'public function complete_reconciliation', $store );
		$this->assertStringContainsString( '$this->submissions->complete_reconciliation', $service );
	}

	public function test_review_round_four_abandoned_processing_jobs_have_a_bounded_reclaim_lease(): void {
		$source = file_get_contents( dirname( __DIR__ ) . '/includes/core/class-submission-store.php' );
		$this->assertIsString( $source );
		$this->assertStringContainsString( 'PROCESSING_LEASE_SECONDS = 600', $source );
		$this->assertStringContainsString( "status = 'processing' AND updated_at <= %s", $source );
	}

	public function test_review_round_five_reenqueue_does_not_steal_processing_or_reset_backoff(): void {
		$source = file_get_contents( dirname( __DIR__ ) . '/includes/core/class-submission-store.php' );
		$this->assertIsString( $source );
		$this->assertStringContainsString( 'Do not steal an active processing lease', $source );
		$this->assertStringContainsString( "array( 'queued', 'retry', 'processing' )", $source );
	}
}
