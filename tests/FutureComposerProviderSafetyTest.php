<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class FutureComposerProviderSafetyTest extends TestCase {
	public function test_provider_returned_rich_text_is_sanitized_before_stable_composer_input_handler(): void {
		$root    = dirname( __DIR__ );
		$safety  = (string) file_get_contents( $root . '/assets/js/future-intelligence-safety.js' );
		$runtime = (string) file_get_contents( $root . '/includes/core/class-future-intelligence-runtime.php' );
		$this->assertStringContainsString( "form.addEventListener('input'", $safety );
		$this->assertStringContainsString( 'event.target !== form', $safety );
		$this->assertStringContainsString( 'source.value = cleaned', $safety );
		$this->assertStringContainsString( 'editor.innerHTML = cleaned', $safety );
		$this->assertStringContainsString( 'future-intelligence-safety.js', $runtime );
		$this->assertStringContainsString( "array( 'supc-future-intelligence-advanced' )", $runtime );
		$this->assertStringNotContainsString( 'localStorage', $safety );
		$this->assertStringNotContainsString( 'sessionStorage', $safety );
	}
}
