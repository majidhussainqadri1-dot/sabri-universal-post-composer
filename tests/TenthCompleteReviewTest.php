<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Sabri\UniversalComposer\Contracts\Adapter;
use Sabri\UniversalComposer\Core\Permission_Resolver;
use Sabri\UniversalComposer\Core\Registry;
use Sabri\UniversalComposer\Core\Runtime_Trust;
use Sabri\UniversalComposer\Core\Safe_Mode;

class Tenth_Foreign_Safe_Mode_Parent {
	public static function disabled(): bool {
		return false;
	}
}

require_once __DIR__ . '/fixtures/sabri-unified-application-shell/includes/class-inherited-safe-mode.php';

final class Tenth_Complete_Review_Adapter implements Adapter {
	public int $availability_calls = 0;
	public int $authorization_calls = 0;

	public function __construct( private string $adapter_key = 'tenth_review_adapter' ) {
	}

	public function api_version(): string { return '1.0.0'; }
	public function key(): string { return $this->adapter_key; }
	public function label(): string { return 'Tenth review adapter'; }
	public function description(): string { return 'Tenth-cycle Safe Mode regression adapter.'; }
	public function group(): string { return 'publishing'; }
	public function icon(): string { return 'admin-post'; }
	public function priority(): int { return 10; }
	public function native_module(): string { return 'tenth-review-module'; }
	public function minimum_native_version(): string { return '1.0.0'; }
	public function required_capability(): string { return 'publish_posts'; }
	public function privacy_classification(): string { return 'public'; }
	public function is_available(): bool {
		++$this->availability_calls;
		return true;
	}
	public function can_create( int $user_id ): bool {
		++$this->authorization_calls;
		return $user_id > 0;
	}
	public function start_url( int $user_id ): string { return '/create/tenth-review/?user=' . $user_id; }
}

final class TenthCompleteReviewTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['supc_test_options']       = array();
		$GLOBALS['supc_test_statuses']      = array( 1 => 'approved' );
		$GLOBALS['supc_test_capabilities']  = array( 1 => array( 'publish_posts' => true ) );
		$GLOBALS['supc_test_current_user']  = 1;
		\Sabri\UnifiedShell\SafeMode::$throw = false;
		\Sabri\UnifiedShell\SafeMode::$disabled = false;
	}

	protected function tearDown(): void {
		$GLOBALS['supc_test_options'] = array();
		\Sabri\UnifiedShell\SafeMode::$throw = false;
		\Sabri\UnifiedShell\SafeMode::$disabled = false;
		supc_unregister_adapter( 'tenth_public_api' );
	}

	public function test_canonical_public_api_is_reflection_owned(): void {
		$this->assertTrue(
			Runtime_Trust::public_api_owned( SUPC_PATH . 'includes/core/functions.php' )
		);
	}

	public function test_safe_mode_blocks_registry_before_native_methods(): void {
		$adapter  = new Tenth_Complete_Review_Adapter();
		$registry = new Registry( new Permission_Resolver() );
		$this->assertTrue( $registry->register( $adapter ) );

		$GLOBALS['supc_test_options']['supc_emergency_disabled'] = true;

		$this->assertSame( array(), $registry->available_for_user( 1 ) );
		$this->assertSame( 'denied', $registry->creation_state_for_user( 1 ) );
		$this->assertFalse( $registry->has_available_for_user( 1 ) );
		$this->assertFalse( $registry->has_central_capability_for_user( 1 ) );
		$this->assertSame( 0, $adapter->availability_calls );
		$this->assertSame( 0, $adapter->authorization_calls );
	}

	public function test_public_availability_helpers_fail_closed_during_safe_mode(): void {
		$adapter = new Tenth_Complete_Review_Adapter( 'tenth_public_api' );
		$this->assertTrue( supc_register_adapter( $adapter ) );

		$GLOBALS['supc_test_options']['supc_emergency_disabled'] = true;

		$this->assertFalse( supc_adapter_available( 'tenth_public_api' ) );
		$this->assertFalse( supc_adapter_matches( 'tenth_public_api', 'tenth-review-module' ) );
		$this->assertSame( 0, $adapter->availability_calls );
		$this->assertSame( 0, $adapter->authorization_calls );
	}

	public function test_inherited_foreign_shell_method_is_not_trusted(): void {
		$this->assertNull(
			Runtime_Trust::owned_shell_static_method(
				'\\Sabri\\UnifiedShell\\InheritedSafeMode',
				'disabled'
			)
		);
	}

	public function test_canonical_shell_method_is_owned_and_invokable(): void {
		$callback = Runtime_Trust::owned_shell_static_method(
			'\\Sabri\\UnifiedShell\\SafeMode',
			'disabled'
		);

		$this->assertInstanceOf( Closure::class, $callback );
		$this->assertFalse( $callback() );
		$this->assertFalse( Safe_Mode::disabled() );
	}
}
