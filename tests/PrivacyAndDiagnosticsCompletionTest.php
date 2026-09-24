<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PrivacyAndDiagnosticsCompletionTest extends TestCase {
	public function test_wordpress_privacy_callbacks_are_wired_into_core_runtime(): void {
		$root = dirname( __DIR__ );
		$privacy = (string) file_get_contents( $root . '/includes/core/class-privacy-integration.php' );
		$plugin  = (string) file_get_contents( $root . '/includes/core/class-plugin.php' );
		$main    = (string) file_get_contents( $root . '/sabri-universal-post-composer.php' );

		$this->assertStringContainsString( 'wp_privacy_personal_data_exporters', $privacy );
		$this->assertStringContainsString( 'wp_privacy_personal_data_erasers', $privacy );
		$this->assertStringContainsString( 'active_reconciliation_count', $privacy );
		$this->assertStringContainsString( 'native_reference_hash = NULL', $privacy );
		$this->assertStringContainsString( '( new Privacy_Integration() )->register();', $plugin );
		$this->assertStringContainsString( "class-privacy-integration.php", $main );
	}

	public function test_dynamic_taxonomy_and_plan_codes_remain_visible_without_open_ended_diagnostics(): void {
		$root = dirname( __DIR__ );
		$source = (string) file_get_contents( $root . '/includes/admin/class-system-check-page.php' );

		$this->assertStringContainsString( 'is_safe_diagnostic_code', $source );
		$this->assertStringContainsString( 'taxonomy_(?:missing|alias_missing|alias_collision)', $source );
		$this->assertStringContainsString( 'optional_adapter_unavailable', $source );
		$this->assertStringContainsString( "'unrecognized_diagnostic'", $source );
	}
}
