<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Sabri\UniversalComposer\Contracts\Adapter;
use Sabri\UniversalComposer\Core\Permission_Resolver;
use Sabri\UniversalComposer\Core\Registry;

final class Test_Adapter implements Adapter {
	public function __construct(
		private string $adapter_key,
		private int $adapter_priority = 10,
		private bool $available = true,
		private bool $authorized = true,
		private bool $throw_on_available = false,
		private string $api = '1.0.0'
	) {
	}

	public function api_version(): string { return $this->api; }
	public function key(): string { return $this->adapter_key; }
	public function label(): string { return ucfirst( $this->adapter_key ); }
	public function description(): string { return 'Test adapter.'; }
	public function group(): string { return 'test'; }
	public function icon(): string { return 'admin-post'; }
	public function priority(): int { return $this->adapter_priority; }
	public function native_module(): string { return 'test-module'; }
	public function minimum_native_version(): string { return '1.0.0'; }
	public function required_capability(): string { return 'publish_posts'; }
	public function privacy_classification(): string { return 'public'; }
	public function is_available(): bool {
		if ( $this->throw_on_available ) {
			throw new RuntimeException( 'Failure.' );
		}
		return $this->available;
	}
	public function can_create( int $user_id ): bool { return $this->authorized && $user_id > 0; }
	public function start_url( int $user_id ): string { return '/create/' . $this->adapter_key . '?user=' . $user_id; }
}

final class RegistryTest extends TestCase {
	private Registry $registry;

	protected function setUp(): void {
		$GLOBALS['supc_test_statuses']     = array( 1 => 'approved', 2 => 'suspended' );
		$GLOBALS['supc_test_capabilities'] = array( 1 => array( 'publish_posts' => true ) );
		$GLOBALS['supc_test_options']      = array();
		$this->registry                    = new Registry( new Permission_Resolver() );
	}

	public function test_registers_and_sorts_adapters(): void {
		$this->assertTrue( $this->registry->register( new Test_Adapter( 'later', 20 ) ) );
		$this->assertTrue( $this->registry->register( new Test_Adapter( 'first', 10 ) ) );
		$this->assertSame( array( 'first', 'later' ), array_keys( $this->registry->all() ) );
	}

	public function test_rejects_invalid_duplicate_and_incompatible_adapters(): void {
		$this->assertInstanceOf( WP_Error::class, $this->registry->register( new Test_Adapter( 'Bad-Key' ) ) );
		$this->assertTrue( $this->registry->register( new Test_Adapter( 'valid_key' ) ) );
		$this->assertInstanceOf( WP_Error::class, $this->registry->register( new Test_Adapter( 'valid_key' ) ) );
		$this->assertInstanceOf( WP_Error::class, $this->registry->register( new Test_Adapter( 'old_api', 10, true, true, false, '0.9.0' ) ) );
	}

	public function test_central_permission_denies_suspended_user(): void {
		$this->assertTrue( $this->registry->register( new Test_Adapter( 'publication' ) ) );
		$this->assertNotEmpty( $this->registry->available_for_user( 1 ) );
		$this->assertSame( array(), $this->registry->available_for_user( 2 ) );
	}

	public function test_one_adapter_exception_does_not_disable_others(): void {
		$this->assertTrue( $this->registry->register( new Test_Adapter( 'broken_one', 1, true, true, true ) ) );
		$this->assertTrue( $this->registry->register( new Test_Adapter( 'healthy_one', 2 ) ) );
		$this->assertSame( array( 'healthy_one' ), array_keys( $this->registry->available_for_user( 1 ) ) );
		$this->assertArrayHasKey( 'broken_one', $this->registry->errors() );
	}
}
