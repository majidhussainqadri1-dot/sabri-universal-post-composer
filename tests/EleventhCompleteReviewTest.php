<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Sabri\UniversalComposer\Admin\System_Check_Page;
use Sabri\UniversalComposer\Contracts\Adapter;
use Sabri\UniversalComposer\Core\Permission_Resolver;
use Sabri\UniversalComposer\Core\Registry;
use Sabri\UniversalComposer\Presentation\Create_Surface;

final class Eleventh_Review_Adapter implements Adapter {
	public function __construct(
		private string $adapter_key,
		private string $adapter_group = 'publishing',
		private int $adapter_priority = 10,
		private string $adapter_label = 'Eleventh review adapter'
	) {
	}

	public function api_version(): string { return '1.0.0'; }
	public function key(): string { return $this->adapter_key; }
	public function label(): string { return $this->adapter_label; }
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
	protected function setUp(): void {
		$GLOBALS['supc_test_statuses'] = array( 1 => 'approved' );
		$GLOBALS['supc_test_capabilities'] = array( 1 => array( 'sabri_feed_create_posts' => true ) );
		$GLOBALS['supc_test_current_user'] = 1;
		$GLOBALS['supc_test_options'] = array();
		$GLOBALS['supc_test_filter_values'] = array();
		if ( class_exists( '\\Sabri\\UnifiedShell\\SafeMode', false ) ) {
			\Sabri\UnifiedShell\SafeMode::$disabled = false;
			\Sabri\UnifiedShell\SafeMode::$throw = false;
		}
	}

	private function registry(): Registry {
		return new Registry( new Permission_Resolver() );
	}

	public function test_noncanonical_group_is_rejected_at_registration(): void {
		$result = $this->registry()->register( new Eleventh_Review_Adapter( 'bad_group_adapter', "publishing\nadmin" ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'supc_invalid_group', $result->code );
	}

	public function test_out_of_range_priority_is_rejected(): void {
		$result = $this->registry()->register( new Eleventh_Review_Adapter( 'bad_priority_adapter', 'publishing', 10001 ) );
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

	public function test_registry_diagnostics_are_bounded(): void {
		$registry = $this->registry();
		for ( $index = 0; $index < 205; ++$index ) {
			$key = 'bad_group_' . str_pad( (string) $index, 3, '0', STR_PAD_LEFT );
			$registry->register( new Eleventh_Review_Adapter( $key, "bad\ngroup" ) );
		}
		$errors = $registry->errors();
		$this->assertLessThanOrEqual( 201, count( $errors ) );
		$this->assertArrayHasKey( '[registry-limit]', $errors );
		$this->assertSame( 'registry_error_limit_reached', $errors['[registry-limit]']['code'] );
	}

	public function test_oversized_display_metadata_is_rejected_before_rendering(): void {
		$registry = $this->registry();
		$this->assertTrue( $registry->register( new Eleventh_Review_Adapter( 'oversized_label_adapter', 'publishing', 10, str_repeat( 'L', 161 ) ) ) );
		$surface = new Create_Surface( $registry );
		$this->assertSame( array(), $surface->collect_groups( 1 ) );
		$codes = array_column( $surface->diagnostics(), 'code' );
		$this->assertContains( 'invalid_display_metadata', $codes );
	}

	public function test_create_surface_count_matches_unique_normalized_codes(): void {
		$registry = $this->registry();
		foreach ( array( 'oversized_label_one', 'oversized_label_two' ) as $key ) {
			$this->assertTrue( $registry->register( new Eleventh_Review_Adapter( $key, 'publishing', 10, str_repeat( 'L', 161 ) ) ) );
		}
		$row = ( new Create_Surface( $registry ) )->system_check_row( 1 );
		$this->assertSame( array( 'invalid_display_metadata' ), $row['codes'] );
		$this->assertSame( 1, $row['count'] );
		$this->assertSame( count( $row['codes'] ), $row['count'] );
	}

	public function test_system_check_rows_and_counts_are_bounded(): void {
		$raw = array();
		for ( $index = 0; $index < 150; ++$index ) {
			$raw[] = array(
				'key' => 'adapter_errors',
				'status' => 'warning',
				'count' => PHP_INT_MAX,
				'codes' => array( 'availability_exception' ),
			);
		}
		$GLOBALS['supc_test_filter_values']['supc_system_check_report'] = $raw;
		$rows = ( new System_Check_Page( $this->registry() ) )->system_rows();

		$this->assertCount( 1, $rows );
		$this->assertSame( 1000, $rows[0]['count'] );
		$this->assertSame( 'warning', $rows[0]['status'] );
		$this->assertContains( 'availability_exception', $rows[0]['codes'] );
		$this->assertContains( 'duplicate_system_check', $rows[0]['codes'] );
	}
}
