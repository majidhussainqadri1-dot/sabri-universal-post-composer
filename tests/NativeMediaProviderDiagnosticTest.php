<?php
/**
 * V12.3 D13: native media readiness is a separate diagnostic from metadata
 * storage. Never report that an unregistered provider is upload-ready.
 *
 * This is a source-contract regression, not a live provider runtime test.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class NativeMediaProviderDiagnosticTest extends TestCase {
	public function test_native_media_health_does_not_equate_upload_token_table_to_provider_readiness(): void {
		$source = file_get_contents( dirname( __DIR__ ) . '/includes/core/class-plan-completion-runtime.php' );
		self::assertIsString( $source );
		self::assertStringContainsString( "'upload_token_store'", $source );
		self::assertStringContainsString( "'native_social_media_upload'", $source );
		self::assertStringContainsString( "'social_publication'", $source );
		self::assertStringContainsString( 'instanceof \\Sabri\\UniversalComposer\\Contracts\\Upload_Token_Adapter', $source );
		self::assertStringContainsString( "'native_social_media_upload_adapter_missing'", $source );
		self::assertStringContainsString( "'status' => " . '$native_upload' . " ? 'pass' : 'warning'", $source );
	}

	public function test_diagnostic_key_and_code_are_allowlisted_without_private_payload_fields(): void {
		$source = file_get_contents( dirname( __DIR__ ) . '/includes/admin/class-system-check-page.php' );
		self::assertIsString( $source );
		self::assertStringContainsString( "'native_social_media_upload'", $source );
		self::assertStringContainsString( "'native_social_media_upload_adapter_missing'", $source );
		self::assertStringNotContainsString( 'native_social_media_upload_subject_id', $source );
		self::assertStringNotContainsString( 'native_social_media_upload_url', $source );
	}
}
