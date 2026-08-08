<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class TenRoundFreshReviewTest extends TestCase {
	private function root(): string {
		return dirname( __DIR__ );
	}

	private function contents( string $path ): string {
		$content = file_get_contents( $this->root() . '/' . $path );
		$this->assertIsString( $content, $path . ' must be readable' );
		return (string) $content;
	}

	public function test_review_1_server_not_browser_owns_sensitive_advisory_decision(): void {
		$php = $this->contents( 'includes/core/class-future-intelligence-hardening.php' );
		$this->assertStringContainsString( 'schema_read_only', $php );
		$this->assertStringContainsString( 'payload_contains_sensitive_shape', $php );
		$this->assertStringContainsString( 'future_sensitive_external_advisory_blocked', $php );
		$this->assertStringContainsString( 'future_capability_action_invalid', $php );
		$this->assertStringContainsString( "'review_annotations'", $php );
		$this->assertStringContainsString( "'conflict_merge'", $php );
	}

	public function test_review_2_provider_rich_text_cannot_load_remote_tracking_images(): void {
		$js = $this->contents( 'assets/js/future-intelligence-safety.js' );
		$this->assertStringContainsString( 'url.origin === window.location.origin', $js );
		$this->assertStringContainsString( 'referrerpolicy', $js );
		$this->assertStringContainsString( "child.removeAttribute('src')", $js );
	}

	public function test_review_3_recovery_is_scoped_expiring_and_legacy_path_is_disabled(): void {
		$runtime = $this->contents( 'includes/core/class-future-intelligence-runtime.php' );
		$js      = $this->contents( 'assets/js/future-intelligence-recovery-hardening.js' );
		$this->assertStringContainsString( "'localEncryptedRecovery'   => false", $runtime );
		$this->assertStringContainsString( "'auditedEncryptedRecovery'", $runtime );
		$this->assertStringContainsString( 'supc-future-recovery-v2', $js );
		$this->assertStringContainsString( '24 * 60 * 60 * 1000', $js );
		$this->assertStringContainsString( 'supcRecoveryToken', $js );
		$this->assertStringContainsString( 'user_id', $js );
		$this->assertStringContainsString( 'parsed.scope !== scope()', $js );
		$this->assertStringContainsString( 'purgeCurrentUser', $js );
		$this->assertStringContainsString( 'action=logout', $js );
		$this->assertStringNotContainsString( 'localStorage.setItem', $js );
		$this->assertStringNotContainsString( 'sessionStorage.setItem', $js );
	}

	public function test_review_4_collaboration_cannot_apply_authority_or_sensitive_fields(): void {
		$js = $this->contents( 'assets/js/future-intelligence-advanced.js' );
		$this->assertStringContainsString( 'protectedRemoteField', $js );
		$this->assertStringContainsString( 'native_reference|publication_action', $js );
		$this->assertStringContainsString( "field.dataset.fieldType === 'multiselect'", $js );
		$this->assertStringContainsString( 'remoteFields = null', $js );
		$this->assertStringContainsString( 'lastConflict = null', $js );
	}

	public function test_review_5_annotations_have_explicit_resolve_workflow(): void {
		$js = $this->contents( 'assets/js/future-intelligence-annotations-hardening.js' );
		$this->assertStringContainsString( "capability: 'review_annotations'", $js );
		$this->assertStringContainsString( "action: 'resolve'", $js );
		$this->assertStringContainsString( 'Mark resolved', $js );
	}

	public function test_review_6_voice_is_structured_privacy_aware_and_ephemeral(): void {
		$runtime = $this->contents( 'includes/core/class-future-intelligence-runtime.php' );
		$js      = $this->contents( 'assets/js/future-intelligence-voice-hardening.js' );
		$this->assertStringContainsString( 'supc_future_sensitive_voice_allowed', $runtime );
		$this->assertStringContainsString( 'currently selected Composer field', $js );
		$this->assertStringContainsString( 'sensitiveVoiceAllowed', $js );
		$this->assertStringContainsString( 'browser or speech vendor may process audio', $js );
		$this->assertStringNotContainsString( 'localStorage', $js );
	}

	public function test_review_7_media_workbench_has_memory_and_decode_bounds(): void {
		$js = $this->contents( 'assets/js/future-intelligence-media-hardening.js' );
		$this->assertStringContainsString( '25 * 1024 * 1024', $js );
		$this->assertStringContainsString( 'MAX_DIMENSION = 12000', $js );
		$this->assertStringContainsString( 'MAX_PIXELS = 40000000', $js );
		$this->assertStringContainsString( 'image/jpeg', $js );
		$this->assertStringContainsString( 'image/png', $js );
		$this->assertStringContainsString( 'image/webp', $js );
		$this->assertStringContainsString( 'bitmap.close', $js );
		$this->assertStringContainsString( 'native upload workflow', $js );
	}

	public function test_review_8_capability_discovery_is_coalesced_only_for_safe_gets(): void {
		$js = $this->contents( 'assets/js/future-intelligence-capability-broker.js' );
		$this->assertStringContainsString( "method !== 'GET'", $js );
		$this->assertStringContainsString( '/future\\/capabilities\\/', $js );
		$this->assertStringContainsString( 'TTL_MS = 5000', $js );
		$this->assertStringContainsString( 'nativeFetch(input, init)', $js );
		$this->assertStringNotContainsString( '/future/invoke', $js );
	}

	public function test_review_9_command_palette_has_focus_escape_and_target_hardening(): void {
		$js  = $this->contents( 'assets/js/future-intelligence-accessibility-hardening.js' );
		$css = $this->contents( 'assets/css/future-intelligence.css' );
		$this->assertStringContainsString( 'aria-modal', $js );
		$this->assertStringContainsString( "event.key === 'Escape'", $js );
		$this->assertStringContainsString( "event.key !== 'Tab'", $js );
		$this->assertStringContainsString( 'restoreFocus', $js );
		$this->assertStringContainsString( 'min-height:44px', $css );
		$this->assertStringContainsString( ':focus-visible', $css );
	}

	public function test_review_10_templates_recovery_impact_and_preflight_preserve_native_authority(): void {
		$template   = $this->contents( 'assets/js/future-intelligence-template-hardening.js' );
		$php        = $this->contents( 'includes/core/class-future-intelligence-hardening.php' );
		$controller = $this->contents( 'includes/http/class-future-rest-controller.php' );
		$runtime    = $this->contents( 'includes/core/class-future-intelligence-runtime.php' );
		$this->assertStringContainsString( 'protectedField', $template );
		$this->assertStringContainsString( 'native_reference|publication_action', $template );
		$this->assertStringContainsString( 'consent|privacy_confirm|medical_disclaimer_confirm', $template );
		$this->assertStringContainsString( "'publication_impact' === \$capability", $php );
		$this->assertStringContainsString( 'publish|submit|schedule|update|revision', $php );
		$this->assertStringContainsString( 'Non-bypassable final preflight', $controller );
		$this->assertStringContainsString( '->guard_request(', $controller );
		$this->assertStringContainsString( '->filter_capabilities(', $controller );
		$this->assertStringContainsString( 'future-intelligence-template-hardening.js', $runtime );
		$this->assertStringNotContainsString( 'CREATE TABLE', $template . $php . $controller . $runtime );
	}

	public function test_final_runtime_system_check_covers_every_hardening_asset(): void {
		$runtime = $this->contents( 'includes/core/class-future-intelligence-runtime.php' );
		foreach (
			array(
				'future-intelligence-capability-broker.js',
				'future-intelligence-safety.js',
				'future-intelligence-recovery-hardening.js',
				'future-intelligence-annotations-hardening.js',
				'future-intelligence-voice-hardening.js',
				'future-intelligence-media-hardening.js',
				'future-intelligence-accessibility-hardening.js',
				'future-intelligence-template-hardening.js',
			) as $asset
		) {
			$this->assertStringContainsString( $asset, $runtime );
		}
	}
}
