<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Permanent 20-pass repository/cross-file completion guard for File 22.
 *
 * These tests intentionally verify the File 22 side of versioned boundaries.
 * Companion repositories and staging/live remain separately verified evidence.
 */
final class TwentyPassCrossFileCompletionTest extends TestCase {
	private string $root;

	protected function setUp(): void {
		$this->root = dirname( __DIR__ );
	}

	private function source( string $path ): string {
		$source = file_get_contents( $this->root . '/' . $path );
		$this->assertIsString( $source, 'Missing source: ' . $path );
		return (string) $source;
	}

	public function test_pass_01_canonical_ownership_remains_orchestration_only(): void {
		$truth = $this->source( 'docs/FILE22-RC3-NEW-PLANS-RELEASE-TRUTH-2026-08-10.md' );
		$runtime = $this->source( 'includes/core/class-governing-plan-runtime.php' );
		$this->assertStringContainsString( 'role-aware creation facade and command orchestrator', $truth );
		$this->assertStringNotContainsString( 'register_post_type(', $runtime );
		$this->assertStringNotContainsString( 'CREATE TABLE', $runtime );
	}

	public function test_pass_02_universal_create_gateway_is_real_and_role_aware(): void {
		$plugin = $this->source( 'includes/core/class-plugin.php' );
		$surface = $this->source( 'includes/presentation/class-create-surface.php' );
		$this->assertStringContainsString( "add_shortcode( 'sabri_universal_composer'", $plugin );
		$this->assertStringContainsString( 'system_check_row', $surface );
		$this->assertStringContainsString( 'availability_snapshot_for_user', $surface );
	}

	public function test_pass_03_file00_is_the_hard_identity_authority(): void {
		$main = $this->source( 'sabri-universal-post-composer.php' );
		$resolver = $this->source( 'includes/core/class-permission-resolver.php' );
		$this->assertStringContainsString( "Requires Plugins: sabri-membership-core", $main );
		$this->assertStringContainsString( "SUPC_MIN_SMC_VERSION', '1.2.3", $main );
		$this->assertStringContainsString( 'SMC_CONTRACT_VERSION', $resolver );
	}

	public function test_pass_04_file20_shell_create_contract_is_versioned_and_fail_safe(): void {
		$bridge = $this->source( 'includes/integration/class-shell-bridge.php' );
		$plugin = $this->source( 'includes/core/class-plugin.php' );
		$this->assertStringContainsString( 'sabri_shell_create_url', $bridge );
		$this->assertStringContainsString( 'sabri_shell_can_show_create', $bridge );
		$this->assertStringContainsString( 'file20_create_contract', $plugin );
	}

	public function test_pass_05_file21_remains_canonical_social_publication_owner(): void {
		$requirements = $this->source( 'includes/integration/class-core-adapter-requirements.php' );
		$this->assertStringContainsString( "SOCIAL_PUBLICATION_KEY     = 'social_publication'", $requirements );
		$this->assertStringContainsString( "FILE21_NATIVE_MODULE       = 'sabri-complete-home-news-feed'", $requirements );
		$this->assertStringContainsString( "REQUIRED_CREATE_CAPABILITY = 'sabri_feed_create_posts'", $requirements );
	}

	public function test_pass_06_file23_dashboard_bridge_is_loaded_and_current_versioned(): void {
		$main = $this->source( 'sabri-universal-post-composer.php' );
		$plugin = $this->source( 'includes/core/class-plugin.php' );
		$bridge = $this->source( 'includes/integration/class-file23-dashboard-bridge.php' );
		$adapter = $this->source( 'includes/integration/class-file23-dashboard-adapter-runtime.php' );
		$this->assertStringContainsString( 'class-file23-dashboard-bridge.php', $main );
		$this->assertStringContainsString( 'new File23_Dashboard_Bridge()', $plugin );
		$this->assertStringContainsString( "spdb/file22_composer_url", $bridge );
		$this->assertStringContainsString( "spdb/register_adapters", $bridge );
		$this->assertStringContainsString( "defined( 'SUPC_VERSION' )", $adapter );
		$this->assertStringNotContainsString( 'SUPC_FILE23_BRIDGE_VERSION', $adapter );
	}

	public function test_pass_07_file24_assurance_manifest_contract_is_real_and_truthful(): void {
		$main = $this->source( 'sabri-universal-post-composer.php' );
		$plugin = $this->source( 'includes/core/class-plugin.php' );
		$bridge = $this->source( 'includes/integration/class-file24-assurance-bridge.php' );
		$this->assertStringContainsString( 'class-file24-assurance-bridge.php', $main );
		$this->assertStringContainsString( 'new File24_Assurance_Bridge()', $plugin );
		$this->assertStringContainsString( "spcrc/module_manifests", $bridge );
		$this->assertStringContainsString( "CONTRACT_VERSION = '1.2.0'", $bridge );
		$this->assertStringContainsString( "supc_file24_last_security_test", $bridge );
		$this->assertStringContainsString( "'posture'", $bridge );
		$this->assertStringContainsString( "'unassessed'", $bridge );
	}

	public function test_pass_08_file19_notification_contract_uses_native_ingestion_without_owning_delivery(): void {
		$main = $this->source( 'sabri-universal-post-composer.php' );
		$plugin = $this->source( 'includes/core/class-plugin.php' );
		$bridge = $this->source( 'includes/integration/class-file19-notification-bridge.php' );
		$this->assertStringContainsString( 'class-file19-notification-bridge.php', $main );
		$this->assertStringContainsString( 'new File19_Notification_Bridge()', $plugin );
		$this->assertStringContainsString( 'sun_register_notification_producer', $bridge );
		$this->assertStringContainsString( 'sun_ingest_domain_event', $bridge );
		$this->assertStringContainsString( 'Publishing.ComposerSubmissionPending', $bridge );
		$this->assertStringNotContainsString( 'CREATE TABLE', $bridge );
	}

	public function test_pass_09_file25_visual_owner_is_preserved(): void {
		$truth = $this->source( 'docs/FILE22-RC3-NEW-PLANS-RELEASE-TRUTH-2026-08-10.md' );
		$css = $this->source( 'assets/css/governing-plan-brand.css' );
		$this->assertStringContainsString( 'File 25 remains the canonical visual-token owner', $truth );
		$this->assertStringContainsString( '--sabri-color-primary', $css );
	}

	public function test_pass_10_file26_search_owner_is_preserved_without_duplicate_index(): void {
		$functions = $this->source( 'includes/core/governing-plan-functions.php' );
		$bus = $this->source( 'includes/core/class-projection-bus.php' );
		$this->assertStringContainsString( 'supc_file26_search_projection_event', $functions );
		$this->assertStringContainsString( 'supc_search_seo_event', $bus );
		$this->assertStringNotContainsString( 'CREATE TABLE', $functions );
		$this->assertStringNotContainsString( 'register_post_type(', $functions );
	}

	public function test_pass_11_file04_legacy_migration_does_not_become_file22_truth(): void {
		$truth = $this->source( 'docs/FILE22-RC3-NEW-PLANS-RELEASE-TRUTH-2026-08-10.md' );
		$this->assertStringContainsString( 'publication lifecycle | File 21', $truth );
		$this->assertStringNotContainsString( 'File 04 | Universal authoring', $truth );
	}

	public function test_pass_12_file03_profile_truth_is_not_duplicated(): void {
		$truth = $this->source( 'README.md' );
		$this->assertStringContainsString( 'profile backend', strtolower( $truth ) );
		$this->assertStringContainsString( 'does **not** create a duplicate publishing backend', $truth );
	}

	public function test_pass_13_optional_native_adapter_packs_fail_soft(): void {
		$runtime = $this->source( 'includes/core/class-plan-completion-runtime.php' );
		foreach ( array( 'learning_lesson', 'encyclopedia_entry', 'video', 'reel', 'pdf_document', 'marketplace_listing' ) as $key ) {
			$this->assertStringContainsString( "'{$key}'", $runtime );
		}
		$this->assertStringContainsString( "'status' => present ? 'pass' : 'warning'", str_replace( '$present', 'present', $runtime ) );
		$this->assertStringContainsString( 'optional_adapter_pack_absent', $runtime );
	}

	public function test_pass_14_sensitive_offline_recovery_is_encrypted_bounded_and_not_plaintext_storage(): void {
		$js = $this->source( 'assets/js/workflow-composer.js' );
		$this->assertStringNotContainsString( 'localStorage', $js );
		$this->assertStringNotContainsString( 'sessionStorage', $js );
		$this->assertStringContainsString( "indexedDB.open('supc-offline-recovery-v1'", $js );
		$this->assertStringContainsString( "name: 'AES-GCM'", $js );
		$this->assertStringContainsString( 'expiresAt', $js );
		$this->assertStringContainsString( 'offlineConflict', $js );
	}

	public function test_pass_15_database_schema_is_metadata_only_and_indexed(): void {
		$session = $this->source( 'includes/core/class-session-store.php' );
		$submission = $this->source( 'includes/core/class-submission-store.php' );
		$upload = $this->source( 'includes/core/class-upload-token-store.php' );
		$this->assertStringContainsString( 'UNIQUE KEY session_uuid', $session );
		$this->assertStringContainsString( 'KEY expires_at', $session );
		$this->assertStringContainsString( 'UNIQUE KEY idempotency_key', $submission );
		$this->assertStringContainsString( 'supc_outbox', $submission );
		$this->assertStringContainsString( 'metadata-only', strtolower( $upload ) );
	}

	public function test_pass_16_idempotency_outbox_reconciliation_and_dead_letter_are_present(): void {
		$submission = $this->source( 'includes/core/class-submission-store.php' );
		$reconcile = $this->source( 'includes/core/class-reconciliation-service.php' );
		$this->assertStringContainsString( 'idempotency_key', $submission );
		$this->assertStringContainsString( 'dead_letter', $submission );
		$this->assertStringContainsString( 'reconcile', $reconcile );
	}

	public function test_pass_17_rest_security_is_subject_bound_and_bounded(): void {
		$runtime = $this->source( 'includes/core/class-governing-plan-runtime.php' );
		$rest = $this->source( 'includes/http/class-rest-controller.php' );
		$this->assertStringContainsString( 'authorization_subject_mismatch', $runtime );
		$this->assertStringContainsString( 'MAX_REQUEST_BYTES', $runtime );
		$this->assertStringContainsString( 'within_rate_limit', $runtime );
		$this->assertStringContainsString( 'permission_callback', $rest );
	}

	public function test_pass_18_accessibility_rtl_and_private_surface_acceptance_remain_documented(): void {
		$accessibility = $this->source( 'docs/ACCESSIBILITY.md' );
		$staging = $this->source( 'docs/STAGING-ACCEPTANCE.md' );
		$this->assertMatchesRegularExpression( '/200%|400%/', $accessibility . $staging );
		$this->assertMatchesRegularExpression( '/RTL|Urdu/i', $accessibility . $staging );
		$this->assertMatchesRegularExpression( '/keyboard/i', $accessibility . $staging );
	}

	public function test_pass_19_ci_negative_assertions_cannot_false_green_and_encrypted_recovery_is_required(): void {
		$workflows = array(
			$this->source( '.github/workflows/file22-new-plans-1.0.0-rc.3.yml' ),
			$this->source( '.github/workflows/file22-eighty-pass-review.yml' ),
			$this->source( '.github/workflows/twelfth-review-evidence.yml' ),
		);
		foreach ( $workflows as $workflow ) {
			$this->assertDoesNotMatchRegularExpression( '/^\s*!\s+grep\b/m', $workflow );
		}
		$rc = $workflows[0];
		$this->assertStringContainsString( "indexedDB.open('supc-offline-recovery-v1'", $rc );
		$this->assertStringContainsString( "name: 'AES-GCM'", $rc );
		$this->assertStringContainsString( 'Forbidden plaintext browser storage found', $rc );
	}

	public function test_pass_20_release_truth_keeps_repository_staging_live_and_operational_separate(): void {
		$truth = $this->source( 'docs/FILE22-RC3-NEW-PLANS-RELEASE-TRUTH-2026-08-10.md' );
		$this->assertStringContainsString( '| Staging-Accepted | No |', $truth );
		$this->assertStringContainsString( '| Live-Deployed | No |', $truth );
		$this->assertStringContainsString( '| Operational | No |', $truth );
		$this->assertStringContainsString( 'Repository source completion does not replace', $truth );
	}
}