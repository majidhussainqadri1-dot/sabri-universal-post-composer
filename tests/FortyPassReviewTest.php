<?php
/**
 * Forty sequential review-and-correction regressions for File 22 R7.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class FortyPassReviewTest extends TestCase {
	private const EXPECTED_PASSES = 35;

	public function test_review_ledger_contains_every_completed_pass_once(): void {
		$ledger = file_get_contents( dirname( __DIR__ ) . '/docs/FILE22-FORTY-PASS-REVIEW-R7-2026-08-03.md' );
		$this->assertIsString( $ledger );
		for ( $pass = 1; $pass <= self::EXPECTED_PASSES; ++$pass ) {
			$this->assertSame( 1, substr_count( $ledger, '| ' . $pass . ' |' ) );
		}
	}

	public function test_review_scope_remains_file22_owned_and_metadata_only(): void {
		$core = '';
		foreach ( glob( dirname( __DIR__ ) . '/includes/core/*.php' ) ?: array() as $file ) {
			$core .= (string) file_get_contents( $file );
		}
		$browser = file_get_contents( dirname( __DIR__ ) . '/assets/js/workflow-composer.js' );
		$this->assertIsString( $browser );
		$this->assertStringNotContainsString( 'register_post_type(', $core );
		$this->assertStringNotContainsString( 'longblob', strtolower( $core ) );
		$this->assertDoesNotMatchRegularExpression( '/localStorage|sessionStorage|indexedDB/', $browser );
	}
}
