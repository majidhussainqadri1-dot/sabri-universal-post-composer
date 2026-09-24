<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class OfflineRecoveryContractTest extends TestCase {
	public function test_offline_recovery_is_encrypted_bounded_and_account_scoped(): void {
		$root = dirname( __DIR__ );
		$js = (string) file_get_contents( $root . '/assets/js/workflow-composer.js' );
		$runtime = (string) file_get_contents( $root . '/includes/core/class-browser-runtime.php' );
		$privacy = (string) file_get_contents( $root . '/docs/PRIVACY.md' );

		$this->assertStringContainsString( "indexedDB.open('supc-offline-recovery-v1'", $js );
		$this->assertStringContainsString( "name: 'AES-GCM'", $js );
		$this->assertStringContainsString( "false, ['encrypt', 'decrypt']", $js );
		$this->assertStringContainsString( 'expiresAt', $js );
		$this->assertStringContainsString( 'offlineConflict', $js );
		$this->assertStringContainsString( "action=logout", $js );
		$this->assertStringContainsString( "hash_hmac( 'sha256'", $runtime );
		$this->assertStringContainsString( "'ttlSeconds' => 7200", $runtime );
		$this->assertStringContainsString( 'media bytes are never stored there', $privacy );
	}
}
