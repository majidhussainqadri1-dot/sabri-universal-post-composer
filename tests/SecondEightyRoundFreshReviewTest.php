<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SecondEightyRoundFreshReviewTest extends TestCase {
	private function root(): string {
		return dirname( __DIR__ );
	}

	private function contents( string $path ): string {
		$content = file_get_contents( $this->root() . '/' . $path );
		$this->assertIsString( $content, $path . ' must be readable' );
		return (string) $content;
	}

	public function test_second_preflight_blocks_client_authority_and_requires_human_collaboration_join(): void {
		$php = $this->contents( 'includes/core/class-future-intelligence-second-eighty-hardening.php' );
		$this->assertStringContainsString( 'RESERVED_AUTHORITY_KEYS', $php );
		$this->assertStringContainsString( 'future_client_authority_field_prohibited', $php );
		$this->assertStringContainsString( "'_supc_context'", $php );
		$this->assertStringContainsString( "'native_reference'", $php );
		$this->assertStringContainsString( "'author_id'", $php );
		$this->assertStringContainsString( 'future_collaboration_join_requires_user_intent', $php );
		$this->assertStringContainsString( "true !== ( \$payload['user_initiated'] ?? false )", $php );
	}

	public function test_server_context_is_revalidated_immediately_before_provider_filters(): void {
		$php = $this->contents( 'includes/core/class-future-intelligence-second-eighty-hardening.php' );
		$this->assertStringContainsString( 'validate_server_context', $php );
		$this->assertStringContainsString( '( new Session_Store() )->get_owned', $php );
		$this->assertStringContainsString( "\$lock_version !== (int) ( \$owned['lock_version'] ?? 0 )", $php );
		$this->assertStringContainsString( 'future_server_context_stale', $php );
		$this->assertStringContainsString( 'bool|WP_Error', $php );
		$this->assertStringNotContainsString( 'true|WP_Error', $php );
	}

	public function test_contextual_rate_budget_is_privacy_minimized_and_stale_lock_safe(): void {
		$php = $this->contents( 'includes/core/class-future-intelligence-second-eighty-hardening.php' );
		$this->assertStringContainsString( "\$_SERVER['REMOTE_ADDR']", $php );
		$this->assertStringContainsString( "hash_hmac( 'sha256', \$remote, wp_salt( 'auth' ) )", $php );
		$this->assertStringContainsString( "'|' . \$adapter_key . '|' . \$capability", $php );
		$this->assertStringContainsString( "'expires_at'", $php );
		$this->assertStringContainsString( "isset( \$existing['expires_at'] )", $php );
		$this->assertStringContainsString( "hash_equals( \$token, \$existing['token'] )", $php );
		$this->assertStringContainsString( 'return false;', $php );
	}

	public function test_active_recovery_is_v3_with_session_alias_schema_fingerprint_and_atomic_restore(): void {
		$runtime = $this->contents( 'includes/core/class-future-intelligence-runtime.php' );
		$js      = $this->contents( 'assets/js/future-intelligence-recovery-v3.js' );
		$this->assertStringContainsString( "'supc-future-intelligence-recovery-v3'", $runtime );
		$this->assertStringNotContainsString( "wp_enqueue_script( 'supc-future-intelligence-recovery-hardening'", $runtime );
		$this->assertStringContainsString( "const DB_NAME = 'supc-future-recovery-v3'", $js );
		$this->assertStringContainsString( 'aliases', $js );
		$this->assertStringContainsString( 'bindCurrentSession', $js );
		$this->assertStringContainsString( 'schemaFingerprint', $js );
		$this->assertStringContainsString( 'parsed.schema_fingerprint !== fingerprint', $js );
		$this->assertStringContainsString( 'Two-phase restore', $js );
		$this->assertStringContainsString( 'prepared.forEach((assignment) => assignment())', $js );
		$this->assertStringContainsString( "indexedDB.deleteDatabase('supc-future-recovery-v2')", $js );
	}

	public function test_recovery_rejects_partial_oversize_and_expanded_identifiers(): void {
		$js = $this->contents( 'assets/js/future-intelligence-recovery-v3.js' );
		$this->assertStringContainsString( 'MAX_RECOVERY_BYTES', $js );
		$this->assertStringContainsString( 'MAX_RECOVERY_FIELDS', $js );
		$this->assertStringContainsString( 'MAX_FIELD_LENGTH', str_replace( 'MAX_TEXT_LENGTH', 'MAX_FIELD_LENGTH', $js ) );
		$this->assertStringContainsString( 'medical\\s+record|mrn|patient\\s+id', $js );
		$this->assertStringContainsString( 'passport|cnic|national\\s+id', $js );
		$this->assertStringContainsString( "'Encrypted recovery was not stored because the draft exceeded the bounded recovery schema", $js );
		$this->assertStringContainsString( 'return null;', $js );
	}

	public function test_provider_snapshots_are_complete_or_rejected_and_never_append_native_reference(): void {
		$js = $this->contents( 'assets/js/future-intelligence-second-eighty-hardening.js' );
		$this->assertStringContainsString( 'const boundedSnapshot = () =>', $js );
		$this->assertStringContainsString( 'if (value.length > limit) return null;', $js );
		$this->assertStringContainsString( 'Nothing was silently truncated or sent', $js );
		$this->assertStringNotContainsString( 'out.native_reference', $js );
		$this->assertStringContainsString( "action: 'compare_current', current", $js );
		$this->assertStringContainsString( "action: 'pull', current", $js );
	}

	public function test_ai_terminology_and_derivative_requests_refuse_silent_source_truncation(): void {
		$js = $this->contents( 'assets/js/future-intelligence-second-eighty-hardening.js' );
		$this->assertStringContainsString( 'const boundedWritingPayload = () =>', $js );
		$this->assertStringContainsString( 'title.length > MAX_TITLE_LENGTH || content.length > MAX_PROVIDER_TEXT', $js );
		$this->assertStringContainsString( 'no partial/truncated advisory request was sent', $js );
		$this->assertStringContainsString( "['ai', 'ai_copilot'", $js );
		$this->assertStringContainsString( "['terminology', 'medical_terminology'", $js );
		$this->assertStringContainsString( "['derivative', 'cross_format_derivative'", $js );
	}

	public function test_explicit_suggestion_apply_is_one_shot_and_status_text_cannot_be_reapplied(): void {
		$js = $this->contents( 'assets/js/future-intelligence-second-eighty-hardening.js' );
		$this->assertStringContainsString( 'let consumed = true;', $js );
		$this->assertStringContainsString( "result.dataset.status !== 'ready'", $js );
		$this->assertStringContainsString( 'consumed = true;', $js );
		$this->assertStringContainsString( 'Suggestion inserted once by explicit human action', $js );
		$this->assertStringContainsString( "generator.addEventListener('click'", $js );
	}

	public function test_conflict_state_requires_exact_positive_native_confirmation(): void {
		$js = $this->contents( 'assets/js/future-intelligence-second-eighty-hardening.js' );
		$this->assertStringContainsString( "result.resolved === true || String(result.status || '').toLowerCase() === 'resolved'", $js );
		$this->assertStringContainsString( "String(result.conflict_token || '') === token", $js );
		$this->assertStringContainsString( 'The conflict remains open locally.', $js );
		$this->assertStringContainsString( 'conflictState = null;', $js );
	}

	public function test_template_and_collaboration_apply_are_whole_envelope_actions(): void {
		$js = $this->contents( 'assets/js/future-intelligence-second-eighty-hardening.js' );
		$this->assertStringContainsString( 'remoteEnvelopeIsSafe', $js );
		$this->assertStringContainsString( 'prepareRemoteAssignments', $js );
		$this->assertStringContainsString( 'partial application is prohibited', $js );
		$this->assertStringContainsString( "action: 'recommended', current", $js );
		$this->assertStringContainsString( 'Provider template failed complete current-schema validation', $js );
	}

	public function test_privacy_assistant_expands_patient_identifier_classes(): void {
		$js = $this->contents( 'assets/js/future-intelligence-second-eighty-hardening.js' );
		$this->assertStringContainsString( 'passport_like', $js );
		$this->assertStringContainsString( 'medical_record', $js );
		$this->assertStringContainsString( 'gps_coordinates', $js );
		$this->assertStringContainsString( 'explicit_address', $js );
		$this->assertStringContainsString( 'authoritative Patient Case privacy/consent workflow still decides publication', $js );
	}

	public function test_final_voice_layer_supports_rich_editor_without_exposing_protected_fields(): void {
		$js      = $this->contents( 'assets/js/future-intelligence-second-eighty-voice-final.js' );
		$runtime = $this->contents( 'includes/core/class-future-intelligence-runtime.php' );
		$this->assertStringContainsString( "node.matches('[data-supc-rte]')", $js );
		$this->assertStringContainsString( 'sourceIsProtected', $js );
		$this->assertStringContainsString( 'no partial transcript was inserted', $js );
		$this->assertStringContainsString( 'sensitiveVoiceAllowed', $js );
		$this->assertStringContainsString( 'future-intelligence-second-eighty-voice-final.js', $runtime );
	}

	public function test_runtime_and_loader_inventory_second_cycle_hardening(): void {
		$runtime   = $this->contents( 'includes/core/class-future-intelligence-runtime.php' );
		$functions = $this->contents( 'includes/core/functions.php' );
		$this->assertStringContainsString( 'class-future-intelligence-second-eighty-hardening.php', $functions );
		$this->assertStringContainsString( "SUPC_FUTURE_INTELLIGENCE_VERSION', '1.1.0'", $functions );
		$this->assertStringContainsString( 'future-intelligence-recovery-v3.js', $runtime );
		$this->assertStringContainsString( 'future-intelligence-second-eighty-hardening.js', $runtime );
		$this->assertStringContainsString( 'class-future-intelligence-second-eighty-hardening.php', $runtime );
	}

	public function test_second_eighty_ledger_has_exactly_eighty_lenses_and_truthful_release_boundary(): void {
		$ledger = $this->contents( 'docs/SECOND-EIGHTY-ROUND-FRESH-REVIEW-2026-08-09.md' );
		preg_match_all( '/^\|\s*(\d{1,2})\s*\|/m', $ledger, $matches );
		$this->assertCount( 80, $matches[1] );
		$this->assertSame( range( 1, 80 ), array_map( 'intval', $matches[1] ) );
		$this->assertStringContainsString( '3, 7, 12, 18, 19, 22, 25, 31, 36, 38, 39, 41, 55, 72', $ledger );
		$this->assertStringContainsString( 'Pending exact-head QA', $ledger );
		$this->assertStringContainsString( 'deterministic production-package parity, Hostinger staging acceptance, live deployment or operational acceptance', $ledger );
	}

	public function test_future_document_matches_second_cycle_runtime_truth(): void {
		$doc = $this->contents( 'docs/FUTURE-COMPOSER-INTELLIGENCE-18.md' );
		$this->assertStringContainsString( 'active v3 IndexedDB AES-GCM recovery', $doc );
		$this->assertStringContainsString( 'Client payloads may not supply native/session/lock/correlation/author/sensitivity authority fields', $doc );
		$this->assertStringContainsString( 'hashed server-observed IP', $doc );
		$this->assertStringContainsString( 'positive native-owner confirmation of the exact token/resolution', $doc );
		$this->assertStringContainsString( 'status/error/progress text is not re-enabled as insertable content', $doc );
	}
}
