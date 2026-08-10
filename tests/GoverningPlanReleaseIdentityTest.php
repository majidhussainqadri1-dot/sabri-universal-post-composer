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

	public function test_public_status_documents_are_aligned_with_candidate_truth(): void {
		$readme = (string) file_get_contents( $this->root . '/readme.txt' );
		$github = (string) file_get_contents( $this->root . '/README.md' );
		$change = (string) file_get_contents( $this->root . '/CHANGELOG.md' );
		$this->assertStringContainsString( 'Stable tag: 1.0.0-rc.3', $readme );
		$this->assertStringContainsString( '1.0.0-rc.3', $github );
		$this->assertStringContainsString( '## 1.0.0-rc.3', $change );
		$this->assertStringContainsString( 'not', strtolower( $github ) );
		$this->assertStringContainsString( 'staging-accepted', strtolower( $github ) );
		$this->assertStringContainsString( 'live-deployed', strtolower( $github ) );
	}

	public function test_manifest_contains_current_plan_complete_and_governed_runtime(): void {
		$manifest = (string) file_get_contents( $this->root . '/MANIFEST.md' );
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
				'tests/GoverningPlanCompletionTest.php',
				'tests/GoverningPlanReleaseIdentityTest.php',
				'docs/FILE22-NEW-GOVERNING-PLANS-CODING-CLOSURE-2026-08-10.md',
				'docs/FILE22-NEW-PLANS-TWO-FRESH-REVIEW-CLOSURE-2026-08-10.md',
			) as $path
		) {
			$this->assertStringContainsString( $path, $manifest, $path . ' missing from manifest' );
		}
	}

	public function test_candidate_workflow_identity_is_rc3_and_checks_both_plan_layers(): void {
		$workflow_path = $this->root . '/.github/workflows/file22-new-plans-1.0.0-rc.3.yml';
		$this->assertFileExists( $workflow_path );
		$workflow = (string) file_get_contents( $workflow_path );
		$this->assertStringContainsString( 'File 22 New Plans 1.0.0-rc.3', $workflow );
		$this->assertStringContainsString( '1.0.0-rc.3-NEW-PLANS-RC', $workflow );
		$this->assertStringContainsString( "SUPC_SCHEMA_VERSION', '1.0.0", $workflow );
		$this->assertStringContainsString( "SUPC_PLAN_CONTRACT_VERSION', '1.0.0", $workflow );
		$this->assertStringContainsString( "SUPC_GOVERNANCE_API_VERSION', '1.0.0", $workflow );
		$this->assertStringContainsString( "SUPC_LIFECYCLE_API_VERSION', '1.0.0", $workflow );
	}

	public function test_final_production_100_identity_is_not_claimed_as_stable_release(): void {
		$plugin = (string) file_get_contents( $this->root . '/sabri-universal-post-composer.php' );
		$readme = (string) file_get_contents( $this->root . '/readme.txt' );
		$this->assertStringNotContainsString( 'Version:     1.0.0\n', $plugin );
		$this->assertStringNotContainsString( 'Stable tag: 1.0.0\n', $readme );
	}
}