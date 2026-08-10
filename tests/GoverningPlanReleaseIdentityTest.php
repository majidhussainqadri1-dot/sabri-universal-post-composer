<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class GoverningPlanReleaseIdentityTest extends TestCase {
	private string $root;

	protected function setUp(): void {
		$this->root = dirname( __DIR__ );
	}

	public function test_candidate_uses_unique_rc3_identity_and_plan_complete_schema(): void {
		$plugin = (string) file_get_contents( $this->root . '/sabri-universal-post-composer.php' );
		$this->assertStringContainsString( 'Version:     1.0.0-rc.3', $plugin );
		$this->assertStringContainsString( "define( 'SUPC_VERSION', '1.0.0-rc.3' );", $plugin );
		$this->assertStringContainsString( "define( 'SUPC_SCHEMA_VERSION', '1.0.0' );", $plugin );
		$this->assertStringContainsString( "define( 'SUPC_PLAN_CONTRACT_VERSION', '1.0.0' );", $plugin );
		$this->assertStringContainsString( "define( 'SUPC_GOVERNANCE_API_VERSION', '1.0.0' );", $plugin );
		$this->assertStringContainsString( "define( 'SUPC_LIFECYCLE_API_VERSION', '1.0.0' );", $plugin );
		$this->assertStringContainsString( "define( 'SUPC_REST_API_VERSION', '1.2.0' );", $plugin );
	}

	public function test_authoritative_release_truth_keeps_candidate_and_environment_statuses_separate(): void {
		$truth = (string) file_get_contents( $this->root . '/docs/FILE22-RC3-NEW-PLANS-RELEASE-TRUTH-2026-08-10.md' );
		$this->assertStringContainsString( 'Source candidate: `1.0.0-rc.3`', $truth );
		$this->assertStringContainsString( 'The stable production identity `1.0.0` MUST NOT be claimed', $truth );
		$this->assertStringContainsString( '| Staging-Accepted | No |', $truth );
		$this->assertStringContainsString( '| Live-Deployed | No |', $truth );
		$this->assertStringContainsString( '| Operational | No |', $truth );
		$this->assertStringContainsString( 'Hostinger staging fresh install/upgrade', $truth );
	}

	public function test_rc3_source_manifest_contains_plan_complete_and_governed_runtime(): void {
		$manifest = (string) file_get_contents( $this->root . '/docs/FILE22-RC3-NEW-PLANS-SOURCE-MANIFEST-2026-08-10.md' );
		foreach (
			array(
				'includes/core/class-plan-completion-runtime.php',
				'includes/core/class-policy-engine.php',
				'includes/core/class-audit-store.php',
				'includes/core/class-upload-token-store.php',
				'includes/http/class-plan-rest-controller.php',
				'includes/admin/class-activation-wizard.php',
				'includes/contracts/interface-governed-workflow-adapter.php',
				'includes/contracts/interface-lifecycle-adapter.php',
				'includes/core/class-governing-plan-runtime.php',
				'includes/core/governing-plan-functions.php',
				'assets/css/governing-plan-brand.css',
				'tests/GoverningPlanCompletionTest.php',
				'tests/GoverningPlanReleaseIdentityTest.php',
				'tests/NewCentralPlanIntegrationTest.php',
				'docs/FILE22-RC3-NEW-PLANS-RELEASE-TRUTH-2026-08-10.md',
				'.github/workflows/file22-new-plans-1.0.0-rc.3.yml',
			) as $path
		) {
			$this->assertStringContainsString( $path, $manifest, $path . ' missing from RC3 manifest' );
		}
	}

	public function test_candidate_workflow_identity_is_rc3_and_checks_both_plan_layers(): void {
		$workflow_path = $this->root . '/.github/workflows/file22-new-plans-1.0.0-rc.3.yml';
		$this->assertFileExists( $workflow_path );
		$this->assertFileDoesNotExist( $this->root . '/.github/workflows/file22-reconciliation-0.3.0.yml' );
		$workflow = (string) file_get_contents( $workflow_path );
		$this->assertStringContainsString( 'File 22 New Plans 1.0.0-rc.3', $workflow );
		$this->assertStringContainsString( '1.0.0-rc.3-NEW-PLANS-RC', $workflow );
		$this->assertStringContainsString( "SUPC_SCHEMA_VERSION', '1.0.0", $workflow );
		$this->assertStringContainsString( "SUPC_PLAN_CONTRACT_VERSION', '1.0.0", $workflow );
		$this->assertStringContainsString( "SUPC_GOVERNANCE_API_VERSION', '1.0.0", $workflow );
		$this->assertStringContainsString( "SUPC_LIFECYCLE_API_VERSION', '1.0.0", $workflow );
		$this->assertStringContainsString( 'supc_file26_search_projection_event', $workflow );
		$this->assertStringContainsString( '#087a4e', strtolower( $workflow ) );
	}

	public function test_final_production_100_identity_is_not_claimed_as_runtime_version(): void {
		$plugin = (string) file_get_contents( $this->root . '/sabri-universal-post-composer.php' );
		$this->assertStringNotContainsString( 'Version:     1.0.0\n', $plugin );
		$this->assertStringContainsString( 'Version:     1.0.0-rc.3', $plugin );
	}
}
