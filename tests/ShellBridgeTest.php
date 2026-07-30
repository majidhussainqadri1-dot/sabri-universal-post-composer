<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Sabri\UniversalComposer\Contracts\Adapter;
use Sabri\UniversalComposer\Core\Page_Resolver;
use Sabri\UniversalComposer\Core\Permission_Resolver;
use Sabri\UniversalComposer\Core\Registry;
use Sabri\UniversalComposer\Integration\Shell_Bridge;

require_once dirname( __DIR__ ) . '/includes/integration/class-shell-bridge.php';

final class Shell_Bridge_Test_Adapter implements Adapter {
	public function api_version(): string { return '1.0.0'; }
	public function key(): string { return 'shell_bridge_test'; }
	public function label(): string { return 'Shell bridge test'; }
	public function description(): string { return 'Shell bridge test adapter.'; }
	public function group(): string { return 'publishing'; }
	public function icon(): string { return 'admin-post'; }
	public function priority(): int { return 10; }
	public function native_module(): string { return 'shell-bridge-test'; }
	public function minimum_native_version(): string { return '1.0.0'; }
	public function required_capability(): string { return 'publish_posts'; }
	public function privacy_classification(): string { return 'public'; }
	public function is_available(): bool { return true; }
	public function can_create( int $user_id ): bool { return $user_id > 0; }
	public function start_url( int $user_id ): string { return '/create/?user=' . $user_id; }
}

final class ShellBridgeTest extends TestCase {
	private Shell_Bridge $bridge;

	protected function setUp(): void {
		$GLOBALS['supc_test_statuses']      = array( 1 => 'approved', 2 => 'suspended' );
		$GLOBALS['supc_test_capabilities']  = array( 1 => array( 'publish_posts' => true ) );
		$GLOBALS['supc_test_options']       = array();
		$GLOBALS['supc_test_pages']         = array(
			22 => array(
				'status'     => 'publish',
				'type'       => 'page',
				'content'    => '[sabri_universal_composer]',
				'slug'       => 'create',
				'permalink'  => 'https://example.test/create/',
				'meta_input' => array(),
			),
		);
		$GLOBALS['supc_test_current_user']  = 1;
		$GLOBALS['supc_test_actions_fired'] = array();
		Page_Resolver::reset_cache();

		$registry = new Registry( new Permission_Resolver() );
		$this->assertTrue( $registry->register( new Shell_Bridge_Test_Adapter() ) );
		$this->bridge = new Shell_Bridge( $registry );
	}

	protected function tearDown(): void {
		$GLOBALS['supc_test_pages'] = array();
		Page_Resolver::reset_cache();
	}

	public function test_shell_visibility_ignores_a_different_caller_supplied_subject(): void {
		$this->assertTrue( $this->bridge->filter_create_visibility( false, 2 ) );
		$this->assertContains(
			array( 'supc_deprecated_subject_argument_ignored', array( 'sabri_shell_can_show_create' ) ),
			$GLOBALS['supc_test_actions_fired']
		);
	}

	public function test_shell_visibility_cannot_substitute_an_approved_user_for_current_suspended_user(): void {
		$GLOBALS['supc_test_current_user'] = 2;

		$this->assertFalse( $this->bridge->filter_create_visibility( true, 1 ) );
	}
}
