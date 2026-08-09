<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ThirdEightyRoundFreshReviewTest extends TestCase {
	private function root(): string {
		return dirname( __DIR__ );
	}

	private function contents( string $path ): string {
		$content = file_get_contents( $this->root() . '/' . $path );
		$this->assertIsString( $content, $path . ' must be readable' );
		return (string) $content;
	}

	public function test_pre_callback_guard_runs_before_provider_filters(): void {
		$php = $this->contents( 'includes/core/class-future-intelligence-third-eighty-hardening.php' );
		$this->assertStringContainsString( "'rest_request_before_callbacks'", $php );
		$this->assertStringContainsString( "'/future/invoke'", $php );
		$this->assertStringContainsString( 'account_is_eligible', $php );
		$this->assertStringContainsString( 'available_for_user', $php );
		$this->assertStringContainsString( 'payload_has_reserved_authority_key', $php );
		$this->assertStringContainsString( 'future_client_authority_field_prohibited', $php );
	}

	public function test_reserved_authority_keys_and_stateful_sessions_fail_closed(): void {
		$php = $this->contents( 'includes/core/class-future-intelligence-third-eighty-hardening.php' );
		foreach ( array( '_supc_context', 'native_reference', 'lock_version', 'correlation_id', 'author_id', 'effective_author', 'sensitivity_class', 'session_uuid', 'user_id' ) as $token ) {
			$this->assertStringContainsString( "'{$token}'", $php );
		}
		$this->assertStringContainsString( 'SESSION_BOUND_CAPABILITIES', $php );
		$this->assertStringContainsString( '( new Session_Store() )->get_owned', $php );
		$this->assertStringContainsString( 'future_third_preflight_session_stale', $php );
	}

	public function test_sensitive_shape_preflight_has_expanded_patient_identifier_coverage(): void {
		$php = $this->contents( 'includes/core/class-future-intelligence-third-eighty-hardening.php' );
		$this->assertStringContainsString( 'medical\\s+record|mrn|patient\\s+id|registration\\s+number', $php );
		$this->assertStringContainsString( 'passport|cnic|national\\s+id', $php );
		$this->assertStringContainsString( 'gps', strtolower( str_replace( '-?\\d{1,2}', 'GPS', $php ) ) );
		$this->assertStringContainsString( 'گھر\\s*کا\\s*پتہ', $php );
		$this->assertStringContainsString( 'supc_future_sensitive_capability_allowed', $php );
	}

	public function test_collaboration_join_still_requires_explicit_human_intent_before_callback(): void {
		$php = $this->contents( 'includes/core/class-future-intelligence-third-eighty-hardening.php' );
		$this->assertStringContainsString( "'collaboration' === \$capability && 'join' === \$action", $php );
		$this->assertStringContainsString( "true !== ( \$payload['user_initiated'] ?? false )", $php );
		$this->assertStringContainsString( 'future_collaboration_join_requires_user_intent', $php );
	}

	public function test_context_rate_scope_is_privacy_minimized_and_wpcs_safe(): void {
		$php = $this->contents( 'includes/core/class-future-intelligence-third-eighty-hardening.php' );
		$this->assertStringContainsString( "filter_input( INPUT_SERVER, 'REMOTE_ADDR', FILTER_UNSAFE_RAW )", $php );
		$this->assertStringContainsString( 'sanitize_text_field( $raw_remote )', $php );
		$this->assertStringContainsString( "filter_var( \$remote, FILTER_VALIDATE_IP )", $php );
		$this->assertStringContainsString( "hash_hmac( 'sha256', \$remote, wp_salt( 'auth' ) )", $php );
		$this->assertStringContainsString( "'|' . \$adapter_key . '|' . \$capability", $php );
		$this->assertStringContainsString( "hash_equals( \$token, \$existing['token'] )", $php );
	}

	public function test_live_sensitive_transition_purges_exact_scope_recovery_immediately(): void {
		$js = $this->contents( 'assets/js/future-intelligence-third-eighty-final.js' );
		$this->assertStringContainsString( 'workflowIsSensitiveNow', $js );
		$this->assertStringContainsString( 'purgeCurrentRecovery', $js );
		$this->assertStringContainsString( "form.addEventListener('input', enforceRecoveryPrivacy, true)", $js );
		$this->assertStringContainsString( 'if (raw.length > MAX_LOCAL_ANALYSIS_TEXT) return true;', $js );
		$this->assertStringContainsString( "dbDelete('drafts', id)", $js );
		$this->assertStringContainsString( "dbDelete('keys', id)", $js );
	}

	public function test_evidence_graph_is_complete_or_rejected_not_silently_sampled(): void {
		$js = $this->contents( 'assets/js/future-intelligence-third-eighty-final.js' );
		$this->assertStringContainsString( 'No partial heatmap was presented as complete', $js );
		$this->assertStringContainsString( 'Claims analyzed completely:', $js );
		$this->assertStringNotContainsString( '.slice(0, 80)', $js );
	}

	public function test_slash_command_is_routed_through_hardened_launcher(): void {
		$js = $this->contents( 'assets/js/future-intelligence-third-eighty-final.js' );
		$this->assertStringContainsString( "event.key !== '/'", $js );
		$this->assertStringContainsString( 'event.stopImmediatePropagation()', $js );
		$this->assertStringContainsString( 'launcher.click()', $js );
		$this->assertStringNotContainsString( 'showModal()', $js );
	}

	public function test_audit_identifiers_are_strict_and_native_references_are_keyed_not_plain_hashes(): void {
		$php = $this->contents( 'includes/core/class-audit-store.php' );
		$this->assertStringContainsString( 'UUID_PATTERN', $php );
		$this->assertStringContainsString( "preg_match( self::UUID_PATTERN, \$event_uuid )", $php );
		$this->assertStringContainsString( 'privacy_hash( $native_reference )', $php );
		$this->assertStringContainsString( "hash_hmac( 'sha256', \$value, wp_salt( 'auth' ) )", $php );
		$this->assertStringNotContainsString( "hash( 'sha256', \$native_reference )", $php );
	}

	public function test_runtime_inventory_loads_third_cycle_guards_last(): void {
		$runtime = $this->contents( 'includes/core/class-future-intelligence-runtime.php' );
		$this->assertStringContainsString( 'class-future-intelligence-third-eighty-hardening.php', $runtime );
		$this->assertStringContainsString( 'Future_Intelligence_Third_Eighty_Hardening', $runtime );
		$this->assertStringContainsString( 'future-intelligence-third-eighty-final.js', $runtime );
		$this->assertStringContainsString( "array( 'supc-future-intelligence-second-eighty-voice-final' )", $runtime );
	}

	public function test_future_document_matches_third_cycle_runtime_truth(): void {
		$doc = $this->contents( 'docs/FUTURE-COMPOSER-INTELLIGENCE-18.md' );
		$this->assertStringContainsString( 'rest_request_before_callbacks', $doc );
		$this->assertStringContainsString( 'before any capability-result provider filter can execute', $doc );
		$this->assertStringContainsString( 'live transition from low-risk to sensitive/identifying/over-bound content', $doc );
		$this->assertStringContainsString( 'keyed HMAC reductions', $doc );
		$this->assertStringContainsString( 'complete bounded draft or refuses the analysis', $doc );
	}

	public function test_third_eighty_ledger_contains_exactly_eighty_fresh_lenses(): void {
		$ledger = $this->contents( 'docs/THIRD-EIGHTY-ROUND-FRESH-REVIEW-2026-08-09.md' );
		preg_match_all( '/^\|\s*(\d{1,2})\s*\|/m', $ledger, $matches );
		$this->assertCount( 80, $matches[1] );
		$this->assertSame( range( 1, 80 ), array_map( 'intval', $matches[1] ) );
		$this->assertStringContainsString( '5, 7, 11, 39, 40, 48, 52, 60, 62, 63, 78', $ledger );
		$this->assertStringContainsString( 'deterministic production-package parity', $ledger );
		$this->assertStringContainsString( 'Hostinger staging acceptance', $ledger );
	}
}
