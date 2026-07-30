<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Sabri\UniversalComposer\Admin\System_Check_Page;
use Sabri\UniversalComposer\Contracts\Diagnostic_Adapter;
use Sabri\UniversalComposer\Contracts\Workflow_Adapter;
use Sabri\UniversalComposer\Core\Permission_Resolver;
use Sabri\UniversalComposer\Core\Registry;
use Sabri\UniversalComposer\Integration\Core_Adapter_Requirements;

final class Third_Post_Merge_Mutable_Adapter implements Workflow_Adapter, Diagnostic_Adapter {
	public string $adapter_key;
	public string $label_value;
	public string $group_value;
	public int $priority_value;
	public string $native_module_value;
	public string $minimum_version_value;
	public string $capability_value;
	public string $privacy_value;
	public string $workflow_api_value;
	public string $actual_version_value;
	public bool $available = true;

	public function __construct(
		string $key = 'third_adapter',
		string $label = 'Third Adapter',
		string $group = 'media',
		int $priority = 20,
		string $native_module = 'third-module',
		string $minimum_version = '1.0.0',
		string $capability = 'publish_posts',
		string $privacy = 'sensitive',
		string $workflow_api = '1.0.0',
		string $actual_version = '1.0.0'
	) {
		$this->adapter_key           = $key;
		$this->label_value           = $label;
		$this->group_value           = $group;
		$this->priority_value        = $priority;
		$this->native_module_value   = $native_module;
		$this->minimum_version_value = $minimum_version;
		$this->capability_value      = $capability;
		$this->privacy_value         = $privacy;
		$this->workflow_api_value    = $workflow_api;
		$this->actual_version_value  = $actual_version;
	}

	public function api_version(): string { return '1.0.0'; }
	public function key(): string { return $this->adapter_key; }
	public function label(): string { return $this->label_value; }
	public function description(): string { return 'Mutable adapter for third independent review regression tests.'; }
	public function group(): string { return $this->group_value; }
	public function icon(): string { return 'admin-post'; }
	public function priority(): int { return $this->priority_value; }
	public function native_module(): string { return $this->native_module_value; }
	public function minimum_native_version(): string { return $this->minimum_version_value; }
	public function required_capability(): string { return $this->capability_value; }
	public function privacy_classification(): string { return $this->privacy_value; }
	public function is_available(): bool { return $this->available; }
	public function can_create( int $user_id ): bool { return $user_id > 0; }
	public function start_url( int $user_id ): string { return '/create/third/?user=' . $user_id; }
	public function workflow_api_version(): string { return $this->workflow_api_value; }
	public function schema_version(): string { return '1.0.0'; }
	public function supports_native_drafts(): bool { return true; }
	public function schema(): array {
		return array(
			'version' => '1.0.0',
			'fields'  => array(
				'content' => array(
					'type'          => 'textarea',
					'label_code'    => 'content',
					'required'      => true,
					'privacy_class' => 'public',
				),
			),
		);
	}
	public function schema_for_user( int $user_id ): array { unset( $user_id ); return $this->schema(); }
	public function create_draft( int $user_id, ?string $native_reference, array $payload ) { unset( $user_id, $payload ); return array( 'native_reference' => $native_reference ?? 'draft-1', 'status' => 'draft' ); }
	public function validate( int $user_id, array $payload ) { unset( $user_id, $payload ); return array( 'valid' => true, 'errors' => array(), 'warnings' => array() ); }
	public function preview( int $user_id, array $payload ) { unset( $user_id, $payload ); return array( 'preview_url' => '/preview/draft-1/', 'expires_at' => time() + 300 ); }
	public function submit( int $user_id, string $idempotency_key, array $payload ) { unset( $user_id, $idempotency_key, $payload ); return array( 'native_reference' => 'post-1', 'status' => 'pending_review' ); }
	public function status( int $user_id, string $native_reference ) { unset( $user_id ); return array( 'native_reference' => $native_reference, 'status' => 'pending_review' ); }
	public function canonical_url( int $user_id, string $native_reference ): string { unset( $user_id, $native_reference ); return '/post/1/'; }
	public function health_report(): array {
		return array(
			'status'                => 'pass',
			'codes'                 => array(),
			'actual_native_version' => $this->actual_version_value,
			'available'             => $this->available,
		);
	}
}

final class ThirdPostMergeHardeningTest extends TestCase {
	private Registry $registry;

	protected function setUp(): void {
		$GLOBALS['supc_test_statuses'] = array(
			1 => 'approved',
			2 => 'approved',
		);
		$GLOBALS['supc_test_capabilities'] = array(
			1 => array(
				'publish_posts'           => true,
				'sabri_feed_create_posts' => true,
			),
			2 => array(),
		);
		$GLOBALS['supc_test_options']      = array();
		$GLOBALS['supc_test_current_user'] = 1;
		$this->registry                    = new Registry( new Permission_Resolver() );
	}

	public function test_capability_denial_precedes_workflow_compatibility_classification(): void {
		$adapter = new Third_Post_Merge_Mutable_Adapter( key: 'future_workflow', workflow_api: '2.0.0' );
		$this->assertTrue( $this->registry->register( $adapter ) );

		$this->assertSame( array(), $this->registry->available_for_user( 2 ) );
		$this->assertSame( 'denied', $this->registry->creation_state_for_user( 2 ) );
		$this->assertArrayNotHasKey( 'future_workflow', $this->registry->errors() );

		$this->registry->flush_cache();
		$this->assertSame( 'unavailable', $this->registry->creation_state_for_user( 1 ) );
		$this->assertSame( 'workflow_api_mismatch', $this->registry->errors()['future_workflow']['code'] );
	}

	public function test_duplicate_registration_does_not_pollute_active_adapter_error_slot(): void {
		$first     = new Third_Post_Merge_Mutable_Adapter( key: 'stable_adapter' );
		$duplicate = new Third_Post_Merge_Mutable_Adapter( key: 'stable_adapter' );
		$this->assertTrue( $this->registry->register( $first ) );
		$this->assertInstanceOf( WP_Error::class, $this->registry->register( $duplicate ) );

		$this->assertArrayNotHasKey( 'stable_adapter', $this->registry->errors() );
		$this->assertContains( 'duplicate_key', array_column( $this->registry->errors(), 'code' ) );
		$this->assertArrayHasKey( 'stable_adapter', $this->registry->available_for_user( 1 ) );
	}

	public function test_equal_priority_order_uses_stable_key_not_mutable_label(): void {
		$alpha = new Third_Post_Merge_Mutable_Adapter( key: 'alpha_adapter', label: 'Zulu', priority: 10 );
		$beta  = new Third_Post_Merge_Mutable_Adapter( key: 'beta_adapter', label: 'Alpha', priority: 10 );
		$this->assertTrue( $this->registry->register( $alpha ) );
		$this->assertTrue( $this->registry->register( $beta ) );
		$this->assertSame( array( 'alpha_adapter', 'beta_adapter' ), array_keys( $this->registry->all() ) );

		$alpha->label_value = 'Alpha';
		$beta->label_value  = 'Zulu';
		$this->assertSame( array( 'alpha_adapter', 'beta_adapter' ), array_keys( $this->registry->all() ) );
	}

	public function test_admin_system_check_uses_registration_snapshot(): void {
		$adapter = new Third_Post_Merge_Mutable_Adapter(
			key: 'snapshot_adapter',
			group: 'media',
			native_module: 'original-module',
			minimum_version: '1.0.0',
			capability: 'publish_posts',
			privacy: 'sensitive'
		);
		$this->assertTrue( $this->registry->register( $adapter ) );

		$adapter->group_value           = 'commerce';
		$adapter->native_module_value   = 'mutated-module';
		$adapter->minimum_version_value = '9.9.9';
		$adapter->capability_value      = 'read';
		$adapter->privacy_value         = 'public';

		$rows = array_column( ( new System_Check_Page( $this->registry ) )->adapter_rows(), null, 'key' );
		$row  = $rows['snapshot_adapter'];
		$this->assertSame( 'original-module', $row['native_module'] );
		$this->assertSame( '1.0.0', $row['minimum_native'] );
		$this->assertSame( 'media', $row['group'] );
		$this->assertSame( 'sensitive', $row['privacy'] );
		$this->assertSame( 'pass', $row['status'] );
		$this->assertSame( '', $row['codes'] );
	}

	public function test_file21_readiness_fails_when_actual_version_is_below_declared_minimum(): void {
		$adapter = new Third_Post_Merge_Mutable_Adapter(
			key: 'social_publication',
			group: 'publishing',
			priority: 10,
			native_module: 'sabri-complete-home-news-feed',
			minimum_version: '2.0.0',
			capability: 'sabri_feed_create_posts',
			privacy: 'public',
			actual_version: '1.5.0'
		);
		$this->assertTrue( $this->registry->register( $adapter ) );

		$report = ( new Core_Adapter_Requirements( $this->registry ) )->social_publication_report();
		$this->assertSame( 'fail', $report['status'] );
		$this->assertContains( 'social_publication_native_version_below_declared_minimum', $report['codes'] );
	}
}
