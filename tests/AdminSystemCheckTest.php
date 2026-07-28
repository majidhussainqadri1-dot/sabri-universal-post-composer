<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Sabri\UniversalComposer\Admin\System_Check_Page;
use Sabri\UniversalComposer\Contracts\Diagnostic_Adapter;
use Sabri\UniversalComposer\Core\Permission_Resolver;
use Sabri\UniversalComposer\Core\Registry;

final class Admin_Health_Test_Adapter implements Diagnostic_Adapter {
	public function api_version(): string { return '1.0.0'; }
	public function key(): string { return 'health_adapter'; }
	public function label(): string { return 'Private label must not appear in health rows'; }
	public function description(): string { return 'Private description must not appear in health rows'; }
	public function group(): string { return 'publishing'; }
	public function icon(): string { return 'admin-post'; }
	public function priority(): int { return 10; }
	public function native_module(): string { return 'file21'; }
	public function minimum_native_version(): string { return '1.0.3'; }
	public function required_capability(): string { return 'publish_posts'; }
	public function privacy_classification(): string { return 'public'; }
	public function is_available(): bool { return true; }
	public function can_create( int $user_id ): bool { return $user_id > 0; }
	public function start_url( int $user_id ): string { return '/create-post/?user=' . $user_id; }
	public function health_report(): array {
		return array(
			'status'  => 'warning',
			'codes'   => array( 'native_version_pending', 'native_version_pending', 'Bad Code!' ),
			'message' => 'A raw internal message that must not be exposed.',
			'url'     => 'https://secret.example/path',
		);
	}
}

final class AdminSystemCheckTest extends TestCase {
	private Registry $registry;
	private System_Check_Page $page;

	protected function setUp(): void {
		$GLOBALS['supc_test_statuses']       = array( 1 => 'approved' );
		$GLOBALS['supc_test_capabilities']   = array( 1 => array( 'publish_posts' => true ) );
		$GLOBALS['supc_test_options']        = array();
		$GLOBALS['supc_test_current_user']   = 1;
		$GLOBALS['supc_test_manage_options'] = true;
		$GLOBALS['supc_test_filter_values']  = array();
		$this->registry = new Registry( new Permission_Resolver() );
		$this->page     = new System_Check_Page( $this->registry );
	}

	public function test_system_rows_normalize_untrusted_filter_data(): void {
		$GLOBALS['supc_test_filter_values']['supc_system_check_report'] = array(
			array(
				'key'    => 'Create Page!',
				'status' => 'unknown',
				'count'  => -5,
				'codes'  => array( 'Bad Code!', 'valid_code', 'valid_code' ),
			),
			'not-an-array',
		);

		$rows = $this->page->system_rows();

		$this->assertCount( 1, $rows );
		$this->assertSame( 'createpage', $rows[0]['key'] );
		$this->assertSame( 'warning', $rows[0]['status'] );
		$this->assertSame( 0, $rows[0]['count'] );
		$this->assertSame( array( 'badcode', 'valid_code' ), $rows[0]['codes'] );
	}

	public function test_adapter_health_rows_are_privacy_safe_and_deterministic(): void {
		$this->assertTrue( $this->registry->register( new Admin_Health_Test_Adapter() ) );

		$rows = $this->page->adapter_rows();

		$this->assertCount( 1, $rows );
		$this->assertSame( 'health_adapter', $rows[0]['key'] );
		$this->assertSame( 'file21', $rows[0]['native_module'] );
		$this->assertSame( 'warning', $rows[0]['status'] );
		$this->assertSame( 'native_version_pending, badcode', $rows[0]['codes'] );
		$this->assertArrayNotHasKey( 'label', $rows[0] );
		$this->assertArrayNotHasKey( 'description', $rows[0] );
		$this->assertStringNotContainsString( 'secret.example', implode( ' ', $rows[0] ) );
	}

	public function test_admin_page_is_capability_protected(): void {
		$GLOBALS['supc_test_manage_options'] = false;

		$this->expectException( RuntimeException::class );
		$this->page->render();
	}
}
