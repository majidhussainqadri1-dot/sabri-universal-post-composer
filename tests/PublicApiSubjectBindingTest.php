<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Sabri\UniversalComposer\Contracts\Adapter;

final class Public_API_Subject_Test_Adapter implements Adapter {
	public function api_version(): string { return '1.0.0'; }
	public function key(): string { return 'public_api_subject_test'; }
	public function label(): string { return 'Public API subject test'; }
	public function description(): string { return 'Public API subject binding test adapter.'; }
	public function group(): string { return 'publishing'; }
	public function icon(): string { return 'admin-post'; }
	public function priority(): int { return 10; }
	public function native_module(): string { return 'public-api-subject-test'; }
	public function minimum_native_version(): string { return '1.0.0'; }
	public function required_capability(): string { return 'publish_posts'; }
	public function privacy_classification(): string { return 'public'; }
	public function is_available(): bool { return true; }
	public function can_create( int $user_id ): bool { return $user_id > 0; }
	public function start_url( int $user_id ): string { return '/create/?user=' . $user_id; }
}

final class PublicApiSubjectBindingTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['supc_test_statuses']     = array( 1 => 'approved', 2 => 'suspended' );
		$GLOBALS['supc_test_capabilities'] = array( 1 => array( 'publish_posts' => true ) );
		$GLOBALS['supc_test_options']      = array();
		$GLOBALS['supc_test_current_user'] = 1;
		supc_unregister_adapter( 'public_api_subject_test' );
		$this->assertTrue( supc_register_adapter( new Public_API_Subject_Test_Adapter() ) );
	}

	protected function tearDown(): void {
		supc_unregister_adapter( 'public_api_subject_test' );
	}

	public function test_owner_match_requires_current_subject_availability(): void {
		$this->assertTrue( supc_adapter_matches( 'public_api_subject_test', 'public-api-subject-test' ) );
		$this->assertFalse( supc_adapter_matches( 'public_api_subject_test', 'foreign-module' ) );

		$GLOBALS['supc_test_current_user'] = 2;
		$this->assertFalse( supc_adapter_matches( 'public_api_subject_test', 'public-api-subject-test' ) );
	}
}
