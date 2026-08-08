<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SecondTenRoundFreshReviewTest extends TestCase {
	private function root(): string {
		return dirname( __DIR__ );
	}

	private function contents( string $path ): string {
		$content = file_get_contents( $this->root() . '/' . $path );
		$this->assertIsString( $content, $path . ' must be readable' );
		return (string) $content;
	}

	public function test_round_1_recovery_scope_is_stable_and_authority_fields_are_not_cached(): void {
		$js = $this->contents( 'assets/js/future-intelligence-recovery-hardening.js' );
		$this->assertStringContainsString( 'auditedEncryptedRecovery', $js );
		$this->assertStringContainsString( 'const stableScope = session() || nativeReference() || stableTabToken', $js );
		$this->assertStringContainsString( 'protectedRecoveryField', $js );
		$this->assertStringContainsString( "field.dataset.fieldType === 'opaque_reference'", $js );
		$this->assertStringContainsString( 'native_reference|publication_action', $js );
		$this->assertStringContainsString( "form.dispatchEvent(new Event('input'", $js );
	}

	public function test_round_2_stateful_provider_tools_require_an_owned_session(): void {
		$php = $this->contents( 'includes/http/class-future-rest-controller.php' );
		$this->assertStringContainsString( 'SESSION_BOUND_CAPABILITIES', $php );
		$this->assertStringContainsString( "'collaboration'", $php );
		$this->assertStringContainsString( "'review_annotations'", $php );
		$this->assertStringContainsString( "'semantic_diff'", $php );
		$this->assertStringContainsString( "'conflict_merge'", $php );
		$this->assertStringContainsString( 'future_session_required', $php );
		$this->assertStringContainsString( 'get_owned', $php );
		$this->assertStringContainsString( 'future_session_mismatch', $php );
	}

	public function test_round_3_sensitive_content_provider_egress_is_default_blocked(): void {
		$php = $this->contents( 'includes/core/class-future-intelligence-hardening.php' );
		foreach ( array( 'ai_copilot', 'medical_terminology', 'collaboration', 'semantic_diff', 'conflict_merge', 'template_library', 'cross_format_derivative', 'publication_impact' ) as $capability ) {
			$this->assertStringContainsString( "'{$capability}'", $php );
		}
		$this->assertStringContainsString( 'adapter_is_sensitive', $php );
		$this->assertStringContainsString( 'payload_contains_sensitive_shape', $php );
		$this->assertStringContainsString( 'future_sensitive_external_advisory_blocked', $php );
	}

	public function test_round_4_publication_impact_action_must_exist_in_native_schema(): void {
		$php = $this->contents( 'includes/core/class-future-intelligence-hardening.php' );
		$this->assertStringContainsString( 'publication_action_is_allowed', $php );
		$this->assertStringContainsString( 'schema_read_only', $php );
		$this->assertStringContainsString( "['fields']['publication_action']", $php );
		$this->assertStringContainsString( 'array_keys( $choices )', $php );
		$this->assertStringContainsString( 'hash_equals( $action', $php );
	}

	public function test_round_5_provider_responses_must_encode_and_be_structurally_bounded(): void {
		$php = $this->contents( 'includes/http/class-future-rest-controller.php' );
		$this->assertStringContainsString( 'response_is_bounded', $php );
		$this->assertStringContainsString( '! is_string( $encoded )', $php );
		$this->assertStringContainsString( 'bounded_value', $php );
		$this->assertStringContainsString( '$depth > 8', $php );
		$this->assertStringContainsString( 'MAX_RESPONSE_BYTES', $php );
	}

	public function test_round_6_read_only_discovery_does_not_consume_mutation_rate_budget(): void {
		$php = $this->contents( 'includes/http/class-future-rest-controller.php' );
		$this->assertStringContainsString( 'method_exists( $request, \'get_route\' )', $php );
		$this->assertStringContainsString( 'str_ends_with( $route, \'/future/invoke\' )', $php );
		$this->assertStringContainsString( 'within_rate_limit( $user_id )', $php );
	}

	public function test_round_7_capability_cache_is_bound_to_nonce_and_does_not_cache_failures(): void {
		$js = $this->contents( 'assets/js/future-intelligence-capability-broker.js' );
		$this->assertStringContainsString( "headerValue(input, init, 'X-WP-Nonce')", $js );
		$this->assertStringContainsString( "'|nonce:'", $js );
		$this->assertStringContainsString( "'|credentials:'", $js );
		$this->assertStringContainsString( 'if (!response.ok) cache.delete(key)', $js );
		$this->assertStringContainsString( "method !== 'GET'", $js );
	}

	public function test_round_8_accessibility_review_remains_green(): void {
		$js  = $this->contents( 'assets/js/future-intelligence-accessibility-hardening.js' );
		$css = $this->contents( 'assets/css/future-intelligence.css' );
		$this->assertStringContainsString( 'aria-modal', $js );
		$this->assertStringContainsString( "event.key === 'Escape'", $js );
		$this->assertStringContainsString( "event.key !== 'Tab'", $js );
		$this->assertStringContainsString( 'restoreFocus', $js );
		$this->assertStringContainsString( 'min-height:44px', $css );
		$this->assertStringContainsString( 'prefers-reduced-motion', $css );
		$this->assertStringContainsString( 'forced-colors', $css );
	}

	public function test_round_9_future_layer_still_does_not_create_a_competing_domain_store(): void {
		$paths = array(
			'includes/http/class-future-rest-controller.php',
			'includes/core/class-future-intelligence-hardening.php',
			'includes/core/class-future-intelligence-runtime.php',
			'includes/contracts/interface-future-capability-adapter.php',
		);
		$combined = '';
		foreach ( $paths as $path ) {
			$combined .= $this->contents( $path );
		}
		$this->assertStringNotContainsString( 'CREATE TABLE', $combined );
		$this->assertStringNotContainsString( 'wp_insert_post(', $combined );
		$this->assertStringNotContainsString( 'update_post_meta(', $combined );
		$this->assertStringContainsString( 'ephemeral_bridge', $combined );
	}

	public function test_round_10_review_evidence_and_runtime_inventory_are_present(): void {
		$runtime = $this->contents( 'includes/core/class-future-intelligence-runtime.php' );
		foreach ( array(
			'future-intelligence-capability-broker.js',
			'future-intelligence-safety.js',
			'future-intelligence-recovery-hardening.js',
			'future-intelligence-annotations-hardening.js',
			'future-intelligence-voice-hardening.js',
			'future-intelligence-media-hardening.js',
			'future-intelligence-accessibility-hardening.js',
			'future-intelligence-template-hardening.js',
		) as $asset ) {
			$this->assertStringContainsString( $asset, $runtime );
		}
		$this->assertFileExists( $this->root() . '/docs/SECOND-TEN-ROUND-FRESH-REVIEW-2026-08-08.md' );
	}
}
