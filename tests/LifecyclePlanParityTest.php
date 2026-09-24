<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class LifecyclePlanParityTest extends TestCase {
	public function test_retraction_is_a_distinct_native_lifecycle_command(): void {
		$source = (string) file_get_contents( dirname( __DIR__ ) . '/includes/core/class-governing-plan-runtime.php' );
		$this->assertMatchesRegularExpression( "/ALLOWED_COMMANDS[\\s\\S]*'withdraw'[\\s\\S]*'retract'[\\s\\S]*'archive'/", $source );
		$this->assertStringContainsString( "'retract'    => 'corrections'", $source );
	}
}
