<?php
/**
 * Regression locks for the 2026-08-10 eighty-pass review.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class EightyPassReviewTest extends TestCase {
	private string $root;

	protected function setUp(): void {
		$this->root = dirname( __DIR__ );
	}

	public function test_active_brand_cascade_uses_sabri_green_fallback(): void {
		$create   = strtolower( (string) file_get_contents( $this->root . '/assets/css/create-surface.css' ) );
		$workflow = strtolower( (string) file_get_contents( $this->root . '/assets/css/workflow-composer.css' ) );

		$this->assertStringContainsString( 'var(--sabri-color-primary, #087a4e)', $create );
		$this->assertStringContainsString( 'var(--sabri-color-primary,#087a4e)', $workflow );
		$this->assertStringNotContainsString( '--supc-orange: #ff8a1f', $create );
		$this->assertStringNotContainsString( 'var(--sabri-color-primary,#ff8a1f)', $workflow );
	}

	public function test_current_numbering_and_dependency_docs_are_not_stale(): void {
		$architecture = (string) file_get_contents( $this->root . '/docs/ARCHITECTURE.md' );
		$decision     = (string) file_get_contents( $this->root . '/docs/DECISION-LOG.md' );
		$compat       = (string) file_get_contents( $this->root . '/docs/COMPATIBILITY-MATRIX.md' );
		$amendment    = (string) file_get_contents( $this->root . '/docs/MASTER-PLAN-AMENDMENT-v2.1.md' );
		$file21       = (string) file_get_contents( $this->root . '/docs/FILE21-INTEGRATION.md' );

		$this->assertStringContainsString( 'File 25 — Complete Public UI, Profile Timeline and Visual Experience', $architecture );
		$this->assertStringContainsString( 'File 23 is Doctor and Founder Publishing Dashboard', $decision );
		$this->assertStringContainsString( 'Plugin 1.2.3 / DB 1.2.0 / Contract 1.1.2', $compat );
		$this->assertStringContainsString( 'Historical/superseded in part', $amendment );
		$this->assertStringContainsString( 'merged File 22 `1.0.0-rc.3` source', $file21 );
	}

	public function test_private_rest_errors_gain_support_reference_without_content_data(): void {
		$functions = (string) file_get_contents( $this->root . '/includes/core/functions.php' );

		$this->assertStringContainsString( "'rest_post_dispatch'", $functions );
		$this->assertStringContainsString( "'/sabri-composer/v1'", $functions );
		$this->assertStringContainsString( "'support_reference'", $functions );
		$this->assertStringContainsString( "'SUPC-'", $functions );
		$this->assertStringContainsString( 'random_bytes( 8 )', $functions );
		$this->assertStringNotContainsString( "'content' =>", $functions );
		$this->assertStringNotContainsString( "'consent' =>", $functions );
	}

	public function test_architecture_describes_current_private_rest_runtime(): void {
		$architecture = (string) file_get_contents( $this->root . '/docs/ARCHITECTURE.md' );
		$this->assertStringContainsString( 'authenticated private REST controllers', $architecture );
		$this->assertStringContainsString( 'private no-store/noindex response boundaries', $architecture );
		$this->assertStringNotContainsString( 'It does not expose a public HTTP controller. Any future REST', $architecture );
	}

	public function test_cross_plan_owner_and_no_duplicate_backend_invariants(): void {
		$projection = (string) file_get_contents( $this->root . '/includes/core/class-projection-bus.php' );
		$functions  = (string) file_get_contents( $this->root . '/includes/core/governing-plan-functions.php' );
		$runtime    = '';
		$iterator   = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $this->root . '/includes', FilesystemIterator::SKIP_DOTS ) );
		foreach ( $iterator as $file ) {
			if ( $file->isFile() && 'php' === strtolower( $file->getExtension() ) ) {
				$runtime .= "\n" . (string) file_get_contents( $file->getPathname() );
			}
		}

		$this->assertStringContainsString( 'supc_file26_search_projection_event', $functions );
		$this->assertStringContainsString( "do_action( 'supc_search_seo_event'", $projection );
		$this->assertStringNotContainsString( 'register_post_type(', $runtime );
		$this->assertDoesNotMatchRegularExpression( '/PKR\s*400|Premium\s+tier|paid\s+AI|donor.{0,40}(?:rank|reach|feature)/i', $runtime );
	}

	public function test_eighty_pass_ledger_contains_every_pass_and_defect_index(): void {
		$ledger = (string) file_get_contents( $this->root . '/docs/FILE22-EIGHTY-PASS-REVIEW-AND-CORRECTION-2026-08-10.md' );
		for ( $pass = 1; $pass <= 80; $pass++ ) {
			$needle = $pass < 9 ? 'Pass 0' . $pass : ( $pass < 10 ? 'Pass 0' . $pass : '|' . sprintf( ' %02d ', $pass ) . '|' );
			if ( $pass <= 8 ) {
				$this->assertStringContainsString( 'Pass 0' . $pass, $ledger );
			} else {
				$this->assertStringContainsString( '| ' . sprintf( '%02d', $pass ) . ' |', $ledger );
			}
		}
		$this->assertStringContainsString( 'Passes 01, 02, 03, 04, 05, 06, 07 and 08', $ledger );
		$this->assertStringContainsString( 'Passes **09–80**', $ledger );
	}
}
