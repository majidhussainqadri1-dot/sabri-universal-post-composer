<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Sabri\UniversalComposer\Core\Runtime_Trust;
use Sabri\UniversalComposer\Core\Safe_Mode;

function supc_ninth_foreign_function(): bool {
	return true;
}

final class NinthCompleteReviewTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['supc_test_options'] = array();
		\Sabri\UnifiedShell\SafeMode::$throw = false;
		\Sabri\UnifiedShell\SafeMode::$disabled = false;
	}

	public function test_public_api_functions_are_declared_by_file22_source(): void {
		$functions = array(
			'supc_register_adapter',
			'supc_unregister_adapter',
			'supc_adapter_available',
			'supc_adapter_matches',
			'supc_workflow_schema',
			'supc_workflow_create_draft',
			'supc_workflow_validate',
			'supc_workflow_preview',
			'supc_workflow_submit',
			'supc_workflow_status',
			'supc_workflow_canonical_url',
			'supc_generate_idempotency_key',
		);

		$this->assertTrue(
			Runtime_Trust::functions_declared_by_file(
				$functions,
				dirname( __DIR__ ) . '/includes/core/functions.php'
			)
		);
	}

	public function test_foreign_function_cannot_satisfy_file22_source_ownership(): void {
		$this->assertFalse(
			Runtime_Trust::functions_declared_by_file(
				array( 'supc_ninth_foreign_function' ),
				dirname( __DIR__ ) . '/includes/core/functions.php'
			)
		);
	}

	public function test_canonical_file20_symbols_are_reflection_owned(): void {
		$this->assertTrue(
			Runtime_Trust::shell_symbols_owned(
				array(
					'sabri_shell_create_contract_available',
					'sabri_shell_create_visible_for_current_user',
				),
				'\Sabri\UnifiedShell\SafeMode'
			)
		);
	}

	public function test_owned_file20_safe_mode_can_be_called(): void {
		$this->assertFalse( Safe_Mode::disabled() );
		\Sabri\UnifiedShell\SafeMode::$disabled = true;
		$this->assertTrue( Safe_Mode::disabled() );
	}
}
