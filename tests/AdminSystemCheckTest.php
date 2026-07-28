<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Sabri\UniversalComposer\Admin\System_Check_Page;
use Sabri\UniversalComposer\Contracts\Diagnostic_Adapter;
use Sabri\UniversalComposer\Core\Page_Resolver;
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

final class Admin_Unavailable_Passing_Health_Adapter implements Diagnostic_Adapter {
	public function api_version(): string { return '1.0.0'; }
	public function key(): string { return 'unavailable_adapter'; }
	public function label(): string { return 'Unavailable'; }
	public function description(): string { return 'Unavailable adapter.'; }
	public function group(): string { return 'media'; }
	public function icon(): string { return 'video-alt3'; }
	public function priority(): int { return 20; }
	public function native_module(): string { return 'file10'; }
	public function minimum_native_version(): string { return '0.1.0'; }
	public function required_capability(): string { return 'publish_posts'; }
	public function privacy_classification(): string { return 'public'; }
	public function is_available(): bool { return false; }
	public function can_create( int $user_id ): bool { return $user_id > 0; }
	public function start_url( int $user_id ): string { return '/video/create/?user=' . $user_id; }
	public function health_report(): array { return array( 'status' => 'pass', 'codes' => array() ); }
}

final class Admin_Invalid_Static_Contract_Adapter implements Diagnostic_Adapter {
	public function api_version(): string { return '1.0.0'; }
	public function key(): string { return 'invalid_static_adapter'; }
	public function label(): string { return 'Invalid static adapter'; }
	public function description(): string { return 'Invalid metadata.'; }
	public function group(): string { return 'legacy-group'; }
	public function icon(): string { return 'edit'; }
	public function priority(): int { return 30; }
	public function native_module(): string { return 'File 10'; }
	public function minimum_native_version(): string { return 'legacy'; }
	public function required_capability(): string { return 'Publish Posts'; }
	public function privacy_classification(): string { return 'unknown'; }
	public function is_available(): bool { return true; }
	public function can_create( int $user_id ): bool { return false; }
	public function start_url( int $user_id ): string { return '/not-used/?user=' . $user_id; }
	public function health_report(): array { return array( 'status' => 'pass', 'codes' => array() ); }
}

final class AdminSystemCheckTest extends TestCase {
	private Registry $registry;
	private System_Check_Page $page;

	protected function setUp(): void {
		$GLOBALS['supc_test_statuses']          = array( 1 => 'approved' );
		$GLOBALS['supc_test_capabilities']      = array( 1 => array( 'publish_posts' => true ) );
		$GLOBALS['supc_test_options']           = array();
		$GLOBALS['supc_test_pages']             = array();
		$GLOBALS['supc_test_current_user']      = 1;
		$GLOBALS['supc_test_manage_options']    = true;
		$GLOBALS['supc_test_filter_values']     = array();
		$GLOBALS['supc_test_update_fail_keys']  = array();
		$GLOBALS['supc_test_insert_mutations']  = array();
		$GLOBALS['supc_test_add_option_fail']   = false;
		Page_Resolver::reset_cache();
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

	public function test_unavailable_adapter_cannot_override_warning_with_passing_health_report(): void {
		$this->assertTrue( $this->registry->register( new Admin_Unavailable_Passing_Health_Adapter() ) );

		$rows = $this->page->adapter_rows();

		$this->assertSame( 'warning', $rows[0]['status'] );
		$this->assertSame( 'native_unavailable', $rows[0]['codes'] );
	}

	public function test_static_contract_health_is_independent_of_current_user_authorization(): void {
		$this->assertTrue( $this->registry->register( new Admin_Invalid_Static_Contract_Adapter() ) );

		$rows = $this->page->adapter_rows();

		$this->assertSame( 'fail', $rows[0]['status'] );
		$this->assertStringContainsString( 'invalid_privacy', $rows[0]['codes'] );
		$this->assertStringContainsString( 'invalid_native_module', $rows[0]['codes'] );
		$this->assertStringContainsString( 'invalid_required_capability', $rows[0]['codes'] );
	}

	public function test_repair_buttons_submit_exact_control_values_and_tables_are_accessible(): void {
		$this->assertTrue( $this->registry->register( new Admin_Health_Test_Adapter() ) );
		$GLOBALS['supc_test_filter_values']['supc_system_check_report'] = array(
			array( 'key' => 'membership_core', 'status' => 'pass', 'count' => 0, 'codes' => array() ),
		);
		ob_start();
		$this->page->render();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'name="supc_mode" value="dry_run"', $html );
		$this->assertStringContainsString( 'name="supc_mode" value="repair"', $html );
		$this->assertSame( 1, substr_count( $html, 'value="dry_run"' ) );
		$this->assertSame( 1, substr_count( $html, 'value="repair"' ) );
		$this->assertSame( 2, substr_count( $html, '<caption class="screen-reader-text">' ) );
		$this->assertGreaterThanOrEqual( 12, substr_count( $html, 'scope="col"' ) );
		$this->assertStringContainsString( 'limited to the currently signed-in administrator', $html );
	}

	public function test_ambiguous_mapping_renders_an_explicit_candidate_selector(): void {
		$GLOBALS['supc_test_pages'][42] = $this->page_record( 'create-one' );
		$GLOBALS['supc_test_pages'][43] = $this->page_record( 'create-two' );

		ob_start();
		$this->page->render();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'name="supc_candidate_page_id"', $html );
		$this->assertStringContainsString( 'value="42"', $html );
		$this->assertStringContainsString( 'value="43"', $html );
	}

	public function test_failure_notices_use_error_severity(): void {
		$notice = $this->page->notice_for_code( 'mapping_persistence_failed' );
		$this->assertSame( 'error', $notice['type'] );
		$this->assertNotSame( '', $notice['text'] );

		$success = $this->page->notice_for_code( 'created_managed_page' );
		$this->assertSame( 'success', $success['type'] );
	}

	public function test_admin_page_is_capability_protected(): void {
		$GLOBALS['supc_test_manage_options'] = false;

		$this->expectException( RuntimeException::class );
		$this->page->render();
	}

	/** @return array<string, mixed> */
	private function page_record( string $slug ): array {
		return array(
			'status'     => 'publish',
			'type'       => 'page',
			'content'    => '[sabri_universal_composer]',
			'slug'       => $slug,
			'permalink'  => 'https://example.test/' . $slug . '/',
			'meta_input' => array(),
		);
	}
}
