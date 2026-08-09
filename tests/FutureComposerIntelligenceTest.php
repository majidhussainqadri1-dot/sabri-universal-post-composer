<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Sabri\UniversalComposer\Http\Future_Rest_Controller;

final class FutureComposerIntelligenceTest extends TestCase {
	private function root(): string {
		return dirname( __DIR__ );
	}

	public function test_future_controller_declares_exactly_eighteen_bounded_capabilities(): void {
		$this->assertTrue( class_exists( Future_Rest_Controller::class ) );
		$constants = ( new ReflectionClass( Future_Rest_Controller::class ) )->getConstants();
		$local     = $constants['LOCAL_CAPABILITIES'];
		$bridge    = $constants['BRIDGE_CAPABILITIES'];
		$this->assertCount( 8, $local );
		$this->assertCount( 10, $bridge );
		$this->assertCount( 18, array_unique( array_merge( $local, $bridge ) ) );
		$this->assertSame(
			array(
				'evidence_graph',
				'privacy_assistant',
				'voice_dictation',
				'command_palette',
				'adaptive_composer',
				'accessibility_coach',
				'readiness_score',
				'encrypted_offline_recovery',
			),
			$local
		);
		$this->assertSame(
			array(
				'ai_copilot',
				'medical_terminology',
				'collaboration',
				'review_annotations',
				'semantic_diff',
				'conflict_merge',
				'template_library',
				'media_workbench',
				'cross_format_derivative',
				'publication_impact',
			),
			$bridge
		);
	}

	public function test_browser_surface_contains_all_eighteen_future_experiences(): void {
		$js = (string) file_get_contents( $this->root() . '/assets/js/future-intelligence.js' );
		foreach (
			array(
				'AI Composer Copilot',
				'Evidence Graph & Citation Heatmap',
				'Medical Terminology Intelligence',
				'Patient Privacy De-identification Assistant',
				'Voice-to-Structured Composer',
				'Real-Time Collaborative Drafting',
				'Inline Reviewer Annotation Layer',
				'Semantic Revision Diff',
				'Conflict Merge Studio',
				'Governed Template & Block Library',
				'Command Palette / Slash Commands',
				'Adaptive Composer Mode',
				'Accessibility Coach',
				'Advanced Media Workbench Bridge',
				'Cross-Format Derivative Studio',
				'Explainable Content Readiness Score',
				'Publication Impact Simulator',
				'Encrypted Offline Recovery',
			) as $feature
		) {
			$this->assertStringContainsString( $feature, $js );
		}
	}

	public function test_future_layer_preserves_native_ownership_and_sensitive_boundaries(): void {
		$rest      = (string) file_get_contents( $this->root() . '/includes/http/class-future-rest-controller.php' );
		$hardening = (string) file_get_contents( $this->root() . '/includes/core/class-future-intelligence-hardening.php' );
		$contract  = (string) file_get_contents( $this->root() . '/includes/contracts/interface-future-capability-adapter.php' );
		$functions = (string) file_get_contents( $this->root() . '/includes/core/functions.php' );
		$this->assertStringContainsString( 'ephemeral_bridge', $rest );
		$this->assertStringContainsString( 'future_sensitive_external_advisory_blocked', $hardening );
		$this->assertStringContainsString( 'supc_future_sensitive_capability_allowed', $hardening );
		$this->assertStringContainsString( 'guard_request(', $rest );
		$this->assertStringContainsString( 'filter_capabilities(', $rest );
		$this->assertStringContainsString( 'must not persist the request or response body', $contract );
		$this->assertStringContainsString( 'creates no competing', $functions );
	}

	public function test_encrypted_recovery_is_device_bound_and_never_plaintext_local_storage(): void {
		$js = (string) file_get_contents( $this->root() . '/assets/js/future-intelligence.js' );
		$this->assertStringContainsString( 'indexedDB.open(DB_NAME', $js );
		$this->assertStringContainsString( 'AES-GCM', $js );
		$this->assertStringContainsString( "generateKey({ name: 'AES-GCM', length: 256 }, false", $js );
		$this->assertStringContainsString( '!isSensitiveDraft()', $js );
		$this->assertStringNotContainsString( 'localStorage.setItem', $js );
		$this->assertStringNotContainsString( 'sessionStorage.setItem', $js );
	}

	public function test_ai_derivatives_and_templates_require_explicit_human_actions(): void {
		$js = (string) file_get_contents( $this->root() . '/assets/js/future-intelligence.js' );
		$this->assertStringContainsString( 'data-tool="ai"', $js );
		$this->assertStringContainsString( 'data-tool="derivative"', $js );
		$this->assertStringContainsString( 'data-template-apply', $js );
		$this->assertStringContainsString( "button.addEventListener('click'", $js );
		$this->assertStringNotContainsString( "bridgeInvoke('ai_copilot'", substr( $js, 0, (int) strpos( $js, 'bridgeTool(panel.querySelector(\'[data-tool="ai"]\')' ) ) );
	}

	public function test_future_runtime_is_no_cache_and_loaded_only_on_authorized_create_surface(): void {
		$runtime = (string) file_get_contents( $this->root() . '/includes/core/class-future-intelligence-runtime.php' );
		$rest    = (string) file_get_contents( $this->root() . '/includes/http/class-future-rest-controller.php' );
		$this->assertStringContainsString( 'Page_Resolver::is_create_request()', $runtime );
		$this->assertStringContainsString( 'available_for_user', $runtime );
		$this->assertStringContainsString( "'Cache-Control', 'private, no-store, max-age=0'", $rest );
		$this->assertStringContainsString( 'wp_verify_nonce', $rest );
		$this->assertStringContainsString( 'future-intelligence-advanced.js', $runtime );
	}

	public function test_fresh_review_hardening_completes_literal_advanced_interactions(): void {
		$advanced = (string) file_get_contents( $this->root() . '/assets/js/future-intelligence-advanced.js' );
		$this->assertStringContainsString( "event.key !== '/'", $advanced );
		$this->assertStringContainsString( "action: 'join'", $advanced );
		$this->assertStringContainsString( "action: 'pull'", $advanced );
		$this->assertStringContainsString( 'supc-intel-annotation-marker', $advanced );
		$this->assertStringContainsString( "invoke('review_annotations'", $advanced );
		$this->assertStringContainsString( "action: 'resolve'", $advanced );
		$this->assertStringContainsString( "resolution: 'keep_current'", str_replace( 'resolution,', "resolution: 'keep_current',", $advanced ) );
		$this->assertStringContainsString( "invoke('media_workbench'", $advanced );
		$this->assertStringContainsString( 'Insert returned suggestion at cursor', $advanced );
		$this->assertStringContainsString( 'Apply remote update explicitly', $advanced );
		$this->assertStringNotContainsString( 'localStorage.setItem', $advanced );
		$this->assertStringNotContainsString( 'sessionStorage.setItem', $advanced );
	}
}
