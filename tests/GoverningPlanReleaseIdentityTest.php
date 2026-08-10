<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class GoverningPlanReleaseIdentityTest extends TestCase {
	private string $root;

	protected function setUp(): void {
		$this->root = dirname( __DIR__ );
	}

	public function test_candidate_uses_unique_040_software_identity_without_schema_bump(): void {
		$plugin = (string) file_get_contents( $this->root . '/sabri-universal-post-composer.php' );
		$this->assertStringContainsString( 'Version:     0.4.0', $plugin );
		$this->assertStringContainsString( "define( 'SUPC_VERSION', '0.4.0' );", $plugin );
		$this->assertStringContainsString( "define( 'SUPC_SCHEMA_VERSION', '0.3.0' );", $plugin );
		$this->assertStringContainsString( "define( 'SUPC_GOVERNANCE_API_VERSION', '1.0.0' );", $plugin );
		$this->assertStringContainsString( "define( 'SUPC_LIFECYCLE_API_VERSION', '1.0.0' );", $plugin );
		$this->assertStringContainsString( "define( 'SUPC_REST_API_VERSION', '1.1.0' );", $plugin );
	}

	public function test_public_status_documents_are_aligned_with_candidate_truth(): void {
		$readme = (string) file_get_contents( $this->root . '/readme.txt' );
		$github = (string) file_get_contents( $this->root . '/README.md' );
		$change = (string) file_get_contents( $this->root . '/CHANGELOG.md' );
		$this->assertStringContainsString( 'Stable tag: 0.4.0', $readme );
		$this->assertStringContainsString( 'Version 0.4.0', $github );
		$this->assertStringContainsString( '## 0.4.0', $change );
		$this->assertStringNotContainsString( 'Draft PR #23', $readme );
		$this->assertStringNotContainsString( 'Draft PR #23', $github );
		$this->assertStringContainsString( 'not', strtolower( $github ) );
		$this->assertStringContainsString( 'staging-accepted', strtolower( $github ) );
	}

	public function test_manifest_contains_current_governed_runtime_and_tests(): void {
		$manifest = (string) file_get_contents( $this->root . '/MANIFEST.md' );
		foreach (
			array(
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

	public function test_candidate_workflow_identity_is_040_and_legacy_030_workflow_is_retired(): void {
		$new = $this->root . '/.github/workflows/file22-governing-plans-0.4.0.yml';
		$old = $this->root . '/.github/workflows/file22-reconciliation-0.3.0.yml';
		$this->assertFileExists( $new );
		$this->assertFileDoesNotExist( $old );
		$workflow = (string) file_get_contents( $new );
		$this->assertStringContainsString( 'File 22 Governing Plans 0.4.0', $workflow );
		$this->assertStringContainsString( '0.4.0-GOVERNING-PLANS-RC', $workflow );
		$this->assertStringContainsString( "SUPC_SCHEMA_VERSION', '0.3.0", $workflow );
		$this->assertStringContainsString( "SUPC_GOVERNANCE_API_VERSION', '1.0.0", $workflow );
		$this->assertStringContainsString( "SUPC_LIFECYCLE_API_VERSION', '1.0.0", $workflow );
	}

	public function test_production_100_identity_is_not_claimed_by_candidate_runtime_or_readmes(): void {
		$plugin = (string) file_get_contents( $this->root . '/sabri-universal-post-composer.php' );
		$readme = (string) file_get_contents( $this->root . '/readme.txt' );
		$this->assertStringNotContainsString( 'Version:     1.0.0', $plugin );
		$this->assertStringNotContainsString( 'Stable tag: 1.0.0', $readme );
	}
}
