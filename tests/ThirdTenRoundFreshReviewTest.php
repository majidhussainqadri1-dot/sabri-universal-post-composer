<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ThirdTenRoundFreshReviewTest extends TestCase {
	private function root(): string {
		return dirname( __DIR__ );
	}

	private function contents( string $path ): string {
		$content = file_get_contents( $this->root() . '/' . $path );
		$this->assertIsString( $content, $path . ' must be readable' );
		return (string) $content;
	}

	public function test_round_1_provider_egress_is_minimized_and_remote_apply_is_bounded(): void {
		$js      = $this->contents( 'assets/js/future-intelligence-third-review-hardening.js' );
		$runtime = $this->contents( 'includes/core/class-future-intelligence-runtime.php' );
		$this->assertStringContainsString( 'providerSnapshot', $js );
		$this->assertStringContainsString( "field.dataset.fieldType === 'opaque_reference'", $js );
		$this->assertStringContainsString( 'publication_action', $js );
		$this->assertStringContainsString( 'current: providerSnapshot()', $js );
		$this->assertStringContainsString( 'fields: providerSnapshot()', $js );
		$this->assertStringContainsString( 'safeAssign', $js );
		$this->assertStringContainsString( 'future-intelligence-third-review-hardening.js', $runtime );
	}

	public function test_round_2_encrypted_recovery_rejects_sensitive_content_not_only_sensitive_schema(): void {
		$js = $this->contents( 'assets/js/future-intelligence-recovery-hardening.js' );
		$this->assertStringContainsString( 'fieldsContainSensitiveContent', $js );
		$this->assertStringContainsString( 'Potential personal/sensitive content detected', $js );
		$this->assertStringContainsString( 'fieldsContainSensitiveContent(parsed.fields)', $js );
		$this->assertStringContainsString( 'fieldsContainSensitiveContent(recovered.fields)', $js );
		$this->assertStringContainsString( 'await purgeCurrent()', $js );
	}

	public function test_round_3_annotation_resolution_requires_native_confirmation_before_ui_removal(): void {
		$js = $this->contents( 'assets/js/future-intelligence-annotations-hardening.js' );
		$this->assertStringContainsString( 'resolutionConfirmed', $js );
		$this->assertStringContainsString( 'result.resolved === true', $js );
		$this->assertStringContainsString( "String(result.status || '').toLowerCase() === 'resolved'", $js );
		$this->assertStringContainsString( 'did not confirm that this annotation is resolved', $js );
	}

	public function test_round_4_voice_privacy_is_revalidated_for_each_transcript_result(): void {
		$js = $this->contents( 'assets/js/future-intelligence-voice-hardening.js' );
		$this->assertStringContainsString( 'sensitiveVoiceAllowed', $js );
		$this->assertStringContainsString( 'Revalidate immediately before every transcript insertion', $js );
		$this->assertStringContainsString( "stop('Dictation stopped before transcript insertion", $js );
	}

	public function test_round_5_command_palette_is_safe_without_native_dialog_and_on_reentry(): void {
		$js = $this->contents( 'assets/js/future-intelligence-accessibility-hardening.js' );
		$this->assertStringContainsString( "if (typeof dialog.close !== 'function') dialog.close = closeFallback", $js );
		$this->assertStringContainsString( 'openSafely', $js );
		$this->assertStringContainsString( "if (dialog.hasAttribute('open'))", $js );
		$this->assertStringContainsString( 'event.stopImmediatePropagation()', $js );
	}

	public function test_round_6_sensitive_reviewer_annotations_require_governing_owner_opt_in(): void {
		$php = $this->contents( 'includes/core/class-future-intelligence-hardening.php' );
		$this->assertStringContainsString( "'collaboration',\n\t\t'review_annotations',\n\t\t'semantic_diff'", $php );
		$this->assertStringContainsString( 'supc_future_sensitive_capability_allowed', $php );
		$this->assertStringContainsString( 'future_sensitive_external_advisory_blocked', $php );
	}

	public function test_round_7_failed_autosave_cannot_purge_browser_recovery(): void {
		$js = $this->contents( 'assets/js/future-intelligence-recovery-hardening.js' );
		$this->assertStringContainsString( 'autosaveSucceeded', $js );
		$this->assertStringContainsString( '/^(?:saved|completed)$/i', $js );
		$this->assertStringNotContainsString( '/saved|completed/i', $js );
	}

	public function test_round_8_legacy_recovery_database_is_retired_and_never_reused(): void {
		$js      = $this->contents( 'assets/js/future-intelligence-recovery-retirement.js' );
		$runtime = $this->contents( 'includes/core/class-future-intelligence-runtime.php' );
		$this->assertStringContainsString( "const LEGACY_DB = 'supc-future-recovery-v1'", $js );
		$this->assertStringContainsString( 'indexedDB.deleteDatabase(LEGACY_DB)', $js );
		$this->assertStringContainsString( 'future-intelligence-recovery-retirement.js', $runtime );
		$this->assertStringContainsString( "'localEncryptedRecovery'   => false", $runtime );
	}

	public function test_round_9_human_rich_text_paste_cannot_load_remote_tracking_images(): void {
		$js = $this->contents( 'assets/js/future-intelligence-safety.js' );
		$this->assertStringContainsString( "editor.addEventListener('paste'", $js );
		$this->assertStringContainsString( 'event.stopImmediatePropagation()', $js );
		$this->assertStringContainsString( "url.origin === window.location.origin", $js );
		$this->assertStringContainsString( "document.execCommand('insertHTML', false, cleaned)", $js );
	}

	public function test_round_10_reviewed_future_layer_still_preserves_native_ownership_and_runtime_inventory(): void {
		$bridge  = $this->contents( 'assets/js/future-intelligence-third-review-hardening.js' );
		$runtime = $this->contents( 'includes/core/class-future-intelligence-runtime.php' );
		$rest    = $this->contents( 'includes/http/class-future-rest-controller.php' );
		$this->assertStringNotContainsString( 'CREATE TABLE', $bridge . $rest );
		$this->assertStringNotContainsString( 'wp_insert_post(', $bridge . $rest );
		$this->assertStringContainsString( "'ownership'           => 'native_owner'", $rest );
		$this->assertStringContainsString( 'future-intelligence-third-review-hardening.js', $runtime );
		$this->assertStringContainsString( 'future-intelligence-recovery-retirement.js', $runtime );
	}
}
