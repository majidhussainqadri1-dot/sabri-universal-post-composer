<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Sabri\UniversalComposer\Contracts\Adapter;
use Sabri\UniversalComposer\Core\Permission_Resolver;
use Sabri\UniversalComposer\Core\Registry;

final class Eleventh_Review_Adapter implements Adapter {
	public function __construct(
		private string $adapter_key,
		private string $adapter_group = 'publishing',
		private int $adapter_priority = 10
	) {
	}

	public function api_version(): string { return '1.0.0'; }
	public function key(): string { return $this->adapter_key; }
	public function label(): string { return 'Eleventh review adapter'; }
	public function description(): string { return 'Bounded registry regression adapter.'; }
	public function group(): string { return $this->adapter_group; }
	public function icon(): string { return 'admin-post'; }
	public function priority(): int { return $this->adapter_priority; }
	public function native_module(): string { return 'eleventh-review-module'; }
	public function minimum_native_version(): string { return '1.0.0'; }
	public function required_capability(): string { return 'sabri_feed_create_posts'; }
	public function privacy_classification(): string { return 'public'; }
	public function is_available(): bool { return true; }
	public function can_create( int $user_id ): bool { return $user_id > 0; }
	public function start_url( int $user_id ): string { return '/create/eleventh/?user=' . $user_id; }
}

final class EleventhCompleteReviewTest extends TestCase {
	private function registry(): Registry {
		return new Registry( new Permission_Resolver() );
	}

	public function test_noncanonical_group_is_rejected_at_registration(): void {
		$result = $this->registry()->register(
			new Eleventh_Review_Adapter( 'bad_group_adapter', "publishing\nadmin" )
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'supc_invalid_group', $result->code );
	}

	public function test_out_of_range_priority_is_rejected(): void {
		$result = $this->registry()->register(
			new Eleventh_Review_Adapter( 'bad_priority_adapter', 'publishing', 10001 )
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'supc_invalid_priority', $result->code );
	}

	public function test_registry_rejects_more_than_one_hundred_adapters(): void {
		$registry = $this->registry();
		for ( $index = 0; $index < 100; ++$index ) {
			$key = 'bounded_adapter_' . str_pad( (string) $index, 3, '0', STR_PAD_LEFT );
			$this->assertTrue( $registry->register( new Eleventh_Review_Adapter( $key ) ) );
		}

		$result = $registry->register( new Eleventh_Review_Adapter( 'bounded_adapter_overflow' ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'supc_adapter_limit_reached', $result->code );
		$this->assertCount( 100, $registry->all() );
	}
}
