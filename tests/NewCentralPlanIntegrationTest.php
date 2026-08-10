<?php
/**
 * Later central-plan reconciliation regressions for File 22.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class NewCentralPlanIntegrationTest extends TestCase {
	private string $root;

	protected function setUp(): void {
		$this->root = dirname( __DIR__ );
	}

	public function test_search_projection_is_explicitly_forwarded_to_canonical_file26_owner(): void {
		$functions = (string) file_get_contents( $this->root . '/includes/core/governing-plan-functions.php' );
		$bus       = (string) file_get_contents( $this->root . '/includes/core/class-projection-bus.php' );

		$this->assertStringContainsString( "'supc_search_seo_event'", $functions );
		$this->assertStringContainsString( "'supc_file26_search_projection_event'", $functions );
		$this->assertStringContainsString( "do_action( 'supc_search_seo_event'", $bus );
		$this->assertStringNotContainsString( 'CREATE TABLE', strtoupper( $bus ) );
		$this->assertStringNotContainsString( 'register_post_type', $bus );
	}

	public function test_green_is_file22_fallback_while_file25_remains_visual_token_owner(): void {
		$css   = (string) file_get_contents( $this->root . '/assets/css/governing-plan-brand.css' );
		$truth = (string) file_get_contents( $this->root . '/docs/FILE22-RC3-NEW-PLANS-RELEASE-TRUTH-2026-08-10.md' );

		$this->assertStringContainsString( '#087a4e', strtolower( $css ) );
		$this->assertStringContainsString( '--sabri-color-primary', $css );
		$this->assertStringContainsString( 'File 25 remains the canonical visual-token owner', $truth );
		$this->assertStringContainsString( 'Sabri Green `#087A4E`', $truth );
	}

	public function test_file22_runtime_contains_no_new_paid_or_donor_advantage_gate(): void {
		$runtime = '';
		$roots   = array( 'includes', 'sabri-universal-post-composer.php', 'uninstall.php' );
		foreach ( $roots as $relative ) {
			$path = $this->root . '/' . $relative;
			if ( is_file( $path ) ) {
				$runtime .= "\n" . (string) file_get_contents( $path );
				continue;
			}
			$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ) );
			foreach ( $iterator as $file ) {
				if ( $file->isFile() && 'php' === strtolower( $file->getExtension() ) ) {
					$runtime .= "\n" . (string) file_get_contents( $file->getPathname() );
				}
			}
		}

		$this->assertDoesNotMatchRegularExpression( '/PKR\s*400|Free\s*\/\s*Pro|Premium\s+tier|paid\s+AI|donor.{0,40}(?:rank|reach|feature|support\s+advantage)/i', $runtime );
	}

	public function test_ai_teacher_bridge_preserves_file16_generation_and_file21_publication_ownership(): void {
		$truth = (string) file_get_contents( $this->root . '/docs/FILE22-RC3-NEW-PLANS-RELEASE-TRUTH-2026-08-10.md' );
		$this->assertStringContainsString( 'AI Teacher generation policy to File 16', $truth );
		$this->assertStringContainsString( 'publication lifecycle to File 21', $truth );
		$this->assertStringContainsString( 'composer bridge/orchestration to File 22', $truth );
		$this->assertStringContainsString( 'discovery/classification to File 26', $truth );
		$this->assertStringContainsString( 'does not create an AI generation backend', $truth );
	}

	public function test_new_plan_release_truth_keeps_repository_and_environment_gates_separate(): void {
		$truth = (string) file_get_contents( $this->root . '/docs/FILE22-RC3-NEW-PLANS-RELEASE-TRUTH-2026-08-10.md' );
		foreach ( array( 'Specified', 'Coded', 'Packaged', 'Automated-QA Green', 'Staging-Accepted', 'Live-Deployed', 'Operational' ) as $gate ) {
			$this->assertStringContainsString( $gate, $truth );
		}
		$this->assertStringContainsString( '| Staging-Accepted | No |', $truth );
		$this->assertStringContainsString( '| Live-Deployed | No |', $truth );
		$this->assertStringContainsString( '| Operational | No |', $truth );
	}
}
