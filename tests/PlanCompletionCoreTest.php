<?php
/**
 * Central-plan and File 22 plan-to-code completion regressions.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PlanCompletionCoreTest extends TestCase {
	public function test_plan_contract_and_rest_contract_are_explicit(): void {
		$bootstrap = file_get_contents( dirname( __DIR__ ) . '/sabri-universal-post-composer.php' );
		$this->assertIsString( $bootstrap );
		$this->assertStringContainsString( "SUPC_VERSION', '1.0.0-rc.2", $bootstrap );
		$this->assertStringContainsString( "SUPC_SCHEMA_VERSION', '1.0.0", $bootstrap );
		$this->assertStringContainsString( "SUPC_PLAN_CONTRACT_VERSION', '1.0.0", $bootstrap );
		$this->assertStringContainsString( "SUPC_REST_API_VERSION', '1.2.0", $bootstrap );
		$this->assertStringContainsString( 'class-plan-rest-controller.php', $bootstrap );
		$this->assertStringContainsString( 'class-policy-engine.php', $bootstrap );
		$this->assertStringContainsString( 'class-audit-store.php', $bootstrap );
		$this->assertStringContainsString( 'class-upload-token-store.php', $bootstrap );
	}

	public function test_four_governing_state_dimensions_are_persisted_separately(): void {
		$source = file_get_contents( dirname( __DIR__ ) . '/includes/core/class-session-store.php' );
		$this->assertIsString( $source );
		foreach ( array( 'composer_state', 'review_state', 'publication_state', 'hold_state' ) as $column ) {
			$this->assertStringContainsString( $column . ' varchar(32)', $source );
		}
		$this->assertStringContainsString( "array( 'new', 'editing', 'autosaved', 'offline_pending', 'conflicted', 'abandoned', 'completed' )", $source );
		$this->assertStringContainsString( "array( 'not_required', 'draft', 'submitted', 'under_review', 'changes_requested', 'approved', 'rejected', 'withdrawn' )", $source );
		$this->assertStringContainsString( "array( 'unpublished', 'scheduled', 'published', 'hidden', 'archived', 'deleted' )", $source );
		$this->assertStringContainsString( "array( 'clear', 'privacy_hold', 'medical_hold', 'copyright_hold', 'security_hold', 'suspended' )", $source );
	}

	public function test_plan_rest_contract_covers_type_list_my_content_patch_delete_status_upload_and_revision(): void {
		$source = file_get_contents( dirname( __DIR__ ) . '/includes/http/class-plan-rest-controller.php' );
		$this->assertIsString( $source );
		foreach ( array(
			"'/types'",
			"'/schema/type/(?P<adapter>",
			"'/sessions'",
			"'PATCH'",
			'WP_REST_Server::DELETABLE',
			"'/status/(?P<session>",
			"'/revision'",
			"'/sessions/(?P<session>[0-9a-f-]{36})/uploads'",
			"'/sessions/(?P<session>[0-9a-f-]{36})/uploads/(?P<upload>[0-9a-f-]{36})/complete'",
		) as $contract ) {
			$this->assertStringContainsString( $contract, $source );
		}
		$this->assertStringContainsString( '$this->sessions->list_owned', $source );
		$this->assertStringContainsString( '$this->coordinator->discard_draft', $source );
		$this->assertStringContainsString( '$this->coordinator->submit_revision', $source );
	}

	public function test_upload_and_audit_stores_are_metadata_only(): void {
		$audit  = file_get_contents( dirname( __DIR__ ) . '/includes/core/class-audit-store.php' );
		$upload = file_get_contents( dirname( __DIR__ ) . '/includes/core/class-upload-token-store.php' );
		$this->assertIsString( $audit );
		$this->assertIsString( $upload );
		$this->assertStringContainsString( "'supc_audit_log'", $audit );
		$this->assertStringContainsString( 'native_reference_hash char(64)', $audit );
		$this->assertStringContainsString( "'supc_upload_tokens'", $upload );
		$this->assertStringContainsString( 'native_upload_reference varchar(255)', $upload );
		$this->assertStringNotContainsString( 'longblob', strtolower( $audit . $upload ) );
		$this->assertDoesNotMatchRegularExpression( '/\n\s*(?:body|content|patient|consent|identity_document|media_bytes)\s+(?:longtext|text|json|blob)/i', $audit . $upload );
	}

	public function test_common_policy_holds_cover_patient_medical_copyright_and_marketplace_boundaries(): void {
		$source = file_get_contents( dirname( __DIR__ ) . '/includes/core/class-policy-engine.php' );
		$this->assertIsString( $source );
		foreach ( array(
			'patient_anonymization_required',
			'patient_consent_reference_required',
			'medical_references_required',
			'medical_safety_acknowledgement_required',
			'emergency_treatment_content_prohibited',
			'copyright_rights_confirmation_required',
			'copyright_source_reference_required',
			'verified_seller_reference_required',
		) as $code ) {
			$this->assertStringContainsString( $code, $source );
		}
		$this->assertStringContainsString( "'supc_policy_violation'", $source );
	}

	public function test_native_ownership_is_preserved_and_optional_adapter_packs_fail_soft(): void {
		$runtime = file_get_contents( dirname( __DIR__ ) . '/includes/core/class-plan-completion-runtime.php' );
		$bus     = file_get_contents( dirname( __DIR__ ) . '/includes/core/class-projection-bus.php' );
		$this->assertIsString( $runtime );
		$this->assertIsString( $bus );
		$this->assertStringContainsString( "array( 'learning', 'encyclopedia', 'video', 'reel', 'pdf', 'marketplace' )", $runtime );
		$this->assertStringContainsString( "'status' => \$present ? 'pass' : 'warning'", $runtime );
		$this->assertStringContainsString( 'supc_file23_projection_event', $bus );
		$this->assertStringContainsString( 'supc_file24_assurance_event', $bus );
		$this->assertStringContainsString( 'supc_file25_timeline_event', $bus );
		$this->assertStringContainsString( 'supc_file19_notification_event', $bus );
		$this->assertStringNotContainsString( 'register_post_type', $runtime . $bus );
	}

	public function test_plaintext_browser_storage_and_duplicate_native_backend_remain_absent(): void {
		$browser = file_get_contents( dirname( __DIR__ ) . '/assets/js/workflow-composer.js' );
		$core    = '';
		foreach ( glob( dirname( __DIR__ ) . '/includes/core/*.php' ) ?: array() as $file ) {
			$core .= (string) file_get_contents( $file );
		}
		$this->assertIsString( $browser );
		$this->assertDoesNotMatchRegularExpression( '/localStorage|sessionStorage|indexedDB/', $browser );
		$this->assertStringNotContainsString( 'register_post_type(', $core );
		$this->assertStringNotContainsString( 'wp_supc_content', $core );
	}

	public function test_native_draft_recovery_and_discard_are_optional_owner_contracts(): void {
		$bootstrap   = file_get_contents( dirname( __DIR__ ) . '/sabri-universal-post-composer.php' );
		$coordinator = file_get_contents( dirname( __DIR__ ) . '/includes/core/class-workflow-coordinator.php' );
		$rest        = file_get_contents( dirname( __DIR__ ) . '/includes/http/class-rest-controller.php' );
		$this->assertIsString( $bootstrap );
		$this->assertIsString( $coordinator );
		$this->assertIsString( $rest );
		$this->assertStringContainsString( 'interface-draft-lifecycle-adapter.php', $bootstrap );
		$this->assertStringContainsString( 'interface-draft-recovery-adapter.php', $bootstrap );
		$this->assertStringContainsString( 'load_draft', $coordinator );
		$this->assertStringContainsString( 'discard_draft', $coordinator );
		$this->assertStringContainsString( "'/sessions/(?P<session>[0-9a-f-]{36})/recover'", $rest );
	}
}
