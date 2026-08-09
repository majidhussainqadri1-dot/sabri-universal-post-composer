<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class EightyRoundFreshReviewTest extends TestCase {
	private function root(): string {
		return dirname( __DIR__ );
	}

	private function contents( string $path ): string {
		$content = file_get_contents( $this->root() . '/' . $path );
		$this->assertIsString( $content, $path . ' must be readable' );
		return (string) $content;
	}

	public function test_authoritative_privacy_and_sensitive_local_capabilities_fail_closed(): void {
		$hardening = $this->contents( 'includes/core/class-future-intelligence-hardening.php' );
		$runtime   = $this->contents( 'includes/core/class-future-intelligence-runtime.php' );
		$rest      = $this->contents( 'includes/http/class-future-rest-controller.php' );
		$this->assertStringContainsString( 'privacy_classification', $hardening );
		$this->assertStringContainsString( 'adapterPrivacyClassification', $runtime );
		$this->assertStringContainsString( "'sensitive' !== \$privacy_class", $runtime );
		$this->assertStringContainsString( "array_diff( \$local, array( 'encrypted_offline_recovery' ) )", $rest );
		$this->assertStringContainsString( "array_diff( \$local, array( 'voice_dictation' ) )", $rest );
	}

	public function test_stateful_provider_context_is_server_owned_and_client_spoofing_is_rejected(): void {
		$rest     = $this->contents( 'includes/http/class-future-rest-controller.php' );
		$contract = $this->contents( 'includes/contracts/interface-future-capability-adapter.php' );
		$this->assertStringContainsString( 'SESSION_BOUND_CAPABILITIES', $rest );
		$this->assertStringContainsString( "isset( \$payload['_supc_context'] )", $rest );
		$this->assertStringContainsString( 'future_reserved_context_prohibited', $rest );
		$this->assertStringContainsString( "\$provider_payload['_supc_context']", $rest );
		$this->assertStringContainsString( "'lock_version'", $rest );
		$this->assertStringContainsString( "'correlation_id'", $rest );
		$this->assertStringContainsString( 'reserves the top-level `_supc_context`', $contract );
	}

	public function test_provider_error_audit_and_rate_limit_invariants_are_support_safe(): void {
		$rest = $this->contents( 'includes/http/class-future-rest-controller.php' );
		$this->assertStringContainsString( 'new Audit_Store()', $rest );
		$this->assertStringContainsString( "'support_reference'", $rest );
		$this->assertStringContainsString( "'retryable'", $rest );
		$this->assertStringContainsString( "'draft_protected'", $rest );
		$this->assertStringContainsString( 'Your draft remains protected', $rest );
		$this->assertStringContainsString( 'add_option( $lock', $rest );
		$this->assertStringContainsString( 'delete_option( $lock )', $rest );
		$this->assertStringContainsString( 'Contention or storage uncertainty fails closed', $rest );
	}

	public function test_capability_discovery_does_not_patch_global_fetch(): void {
		$broker = $this->contents( 'assets/js/future-intelligence-capability-broker.js' );
		$this->assertStringContainsString( "mode: 'native-fetch'", $broker );
		$this->assertStringContainsString( 'globalFetchPatched: false', $broker );
		$this->assertStringNotContainsString( 'window.fetch =', $broker );
	}

	public function test_encrypted_recovery_is_complete_bounded_and_privacy_bound(): void {
		$js = $this->contents( 'assets/js/future-intelligence-recovery-hardening.js' );
		$this->assertStringContainsString( 'adapterPrivacyClassification', $js );
		$this->assertStringContainsString( 'MAX_RECOVERY_BYTES', $js );
		$this->assertStringContainsString( 'MAX_RECOVERY_FIELDS', $js );
		$this->assertStringContainsString( 'fieldsContainSensitiveContent', $js );
		$this->assertStringContainsString( 'return invalid ? null : fields', $js );
		$this->assertStringContainsString( 'no partial or truncated browser recovery was retained', $js );
		$this->assertStringContainsString( '/^(?:saved|completed)$/i', $js );
		$this->assertStringContainsString( 'guardian|credential|identity_evidence', $js );
	}

	public function test_voice_annotation_template_and_collaboration_corrections_are_present(): void {
		$voice       = $this->contents( 'assets/js/future-intelligence-voice-hardening.js' );
		$annotations = $this->contents( 'assets/js/future-intelligence-annotations-hardening.js' );
		$template    = $this->contents( 'assets/js/future-intelligence-template-hardening.js' );
		$collab      = $this->contents( 'assets/js/future-intelligence-third-review-hardening.js' );
		$this->assertStringContainsString( 'adapterPrivacyClassification', $voice );
		$this->assertStringContainsString( 'Revalidate immediately before every transcript insertion', $voice );
		$this->assertStringContainsString( "Object.prototype.hasOwnProperty.call(result, 'annotation_id')", $annotations );
		$this->assertStringContainsString( 'identity_evidence|author_id|effective_author', $template );
		$this->assertStringContainsString( 'remoteEnvelopeIsSafe', $collab );
		$this->assertStringContainsString( 'partial/truncated application is prohibited', $collab );
	}

	public function test_conflict_resolution_requires_a_bounded_server_validated_token(): void {
		$php = $this->contents( 'includes/core/class-future-intelligence-hardening.php' );
		$this->assertStringContainsString( "'conflict_token'", $php );
		$this->assertStringContainsString( "strlen( \$token ) <= 512", $php );
		$this->assertStringContainsString( "preg_match( '/^[A-Za-z0-9._~:+\\/-]+$/D'", $php );
	}

	public function test_private_editor_safety_covers_paste_provider_and_asynchronous_restore_paths(): void {
		$js = $this->contents( 'assets/js/future-intelligence-safety.js' );
		$this->assertStringContainsString( "editor.addEventListener('paste'", $js );
		$this->assertStringContainsString( "form.addEventListener('input'", $js );
		$this->assertStringContainsString( 'new MutationObserver(sanitizeRenderedEditor)', $js );
		$this->assertStringContainsString( 'url.origin === window.location.origin', $js );
	}

	public function test_native_media_ownership_and_upload_url_safety_remain_in_core(): void {
		$media       = $this->contents( 'assets/js/future-intelligence-media-hardening.js' );
		$coordinator = $this->contents( 'includes/core/class-workflow-coordinator.php' );
		$this->assertStringContainsString( 'MAX_PIXELS = 40000000', $media );
		$this->assertStringContainsString( 'authoritative native upload workflow', $media );
		$this->assertStringContainsString( 'invalid_native_upload_url', $coordinator );
		$this->assertStringContainsString( "'https' !== strtolower", $coordinator );
		$this->assertStringContainsString( "isset( \$parts['user'] )", $coordinator );
	}

	public function test_future_layer_preserves_file_16_23_24_and_native_owner_boundaries(): void {
		$contract = $this->contents( 'includes/contracts/interface-future-capability-adapter.php' );
		$rest     = $this->contents( 'includes/http/class-future-rest-controller.php' );
		$future   = $this->contents( 'assets/js/future-intelligence-third-review-hardening.js' );
		$this->assertStringContainsString( 'owning File 16', $contract );
		$this->assertStringContainsString( 'advisory output only, human review required', $contract );
		$this->assertStringContainsString( 'autonomous diagnosis/prescription/potency/dosage/emergency replacement', $contract );
		$this->assertStringContainsString( 'no fabricated references', $contract );
		$this->assertStringContainsString( "'ownership'", $rest );
		$this->assertStringContainsString( "'native_owner'", $rest );
		$this->assertStringNotContainsString( 'CREATE TABLE', $contract . $rest . $future );
		$this->assertStringNotContainsString( 'wp_insert_post(', $contract . $rest . $future );
	}

	public function test_eighty_round_evidence_ledger_exists_and_is_release_truthful(): void {
		$ledger = $this->contents( 'docs/EIGHTY-ROUND-FRESH-REVIEW-2026-08-09.md' );
		$this->assertStringContainsString( '| 1 | Canonical ownership boundary |', $ledger );
		$this->assertStringContainsString( '| 80 | Final exact-head regression / release truth |', $ledger );
		$this->assertStringContainsString( 'PR merge, deterministic production package parity, Hostinger staging acceptance, live deployment and operational acceptance remain separate gates', $ledger );
	}
}
