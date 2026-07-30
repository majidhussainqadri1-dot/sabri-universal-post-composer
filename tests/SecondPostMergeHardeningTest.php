<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Sabri\UniversalComposer\Contracts\Diagnostic_Adapter;
use Sabri\UniversalComposer\Contracts\Workflow_Adapter;
use Sabri\UniversalComposer\Core\Permission_Resolver;
use Sabri\UniversalComposer\Core\Registry;
use Sabri\UniversalComposer\Integration\Core_Adapter_Requirements;
use Sabri\UniversalComposer\Presentation\Create_Surface;

final class Second_Post_Merge_Mutable_Adapter implements Workflow_Adapter, Diagnostic_Adapter {
	public string $adapter_key;
	public string $group_value;
	public int $priority_value;
	public string $native_module_value;
	public string $minimum_version_value;
	public string $capability_value;
	public string $privacy_value;
	public string $workflow_api_value;
	public bool $available = true;

	public function __construct(
		string $key = 'mutable_adapter',
		string $group = 'media',
		int $priority = 20,
		string $native_module = 'mutable-module',
		string $minimum_version = '1.0.0',
		string $capability = 'publish_posts',
		string $privacy = 'sensitive',
		string $workflow_api = '1.0.0'
	) {
		$this->adapter_key           = $key;
		$this->group_value           = $group;
		$this->priority_value        = $priority;
		$this->native_module_value   = $native_module;
		$this->minimum_version_value = $minimum_version;
		$this->capability_value      = $capability;
		$this->privacy_value         = $privacy;
		$this->workflow_api_value    = $workflow_api;
	}

	public function api_version(): string { return '1.0.0'; }
	public function key(): string { return $this->adapter_key; }
	public function label(): string { return 'Mutable Adapter'; }
	public function description(): string { return 'Mutable adapter used for immutable-contract regression tests.'; }
	public function group(): string { return $this->group_value; }
	public function icon(): string { return 'admin-post'; }
	public function priority(): int { return $this->priority_value; }
	public function native_module(): string { return $this->native_module_value; }
	public function minimum_native_version(): string { return $this->minimum_version_value; }
	public function required_capability(): string { return $this->capability_value; }
	public function privacy_classification(): string { return $this->privacy_value; }
	public function is_available(): bool { return $this->available; }
	public function can_create( int $user_id ): bool { return $user_id > 0; }
	public function start_url( int $user_id ): string { return '/create/mutable/?user=' . $user_id; }
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
	public function health_report(): array { return array( 'actual_native_version' => '1.0.3', 'available' => $this->available ); }
}

final class SecondPostMergeHardeningTest extends TestCase {
	private Registry $registry;

	protected function setUp(): void {
		$GLOBALS['supc_test_statuses']     = array( 1 => 'approved' );
		$GLOBALS['supc_test_capabilities'] = array(
			1 => array(
				'publish_posts'           => true,
				'sabri_feed_create_posts' => true,
			),
		);
		$GLOBALS['supc_test_options']      = array();
		$GLOBALS['supc_test_current_user'] = 1;
		$this->registry                    = new Registry( new Permission_Resolver() );
	}

	public function test_structural_metadata_remains_immutable_in_ordering_and_create_surface(): void {
		$adapter = new Second_Post_Merge_Mutable_Adapter();
		$first   = new Second_Post_Merge_Mutable_Adapter( key: 'first_adapter', priority: 10, privacy: 'public' );
		$this->assertTrue( $this->registry->register( $adapter ) );
		$this->assertTrue( $this->registry->register( $first ) );

		$adapter->group_value           = 'commerce';
		$adapter->priority_value        = -100;
		$adapter->minimum_version_value = '0.1.0';
		$adapter->privacy_value         = 'public';

		$this->assertSame( array( 'first_adapter', 'mutable_adapter' ), array_keys( $this->registry->all() ) );
		$contract = $this->registry->adapter_contract( 'mutable_adapter' );
		$this->assertSame( 'media', $contract['group'] );
		$this->assertSame( 20, $contract['priority'] );
		$this->assertSame( '1.0.0', $contract['minimum_native_version'] );
		$this->assertSame( 'sensitive', $contract['privacy_classification'] );

		$groups = ( new Create_Surface( $this->registry ) )->collect_groups( 1 );
		$this->assertArrayHasKey( 'media', $groups );
		$cards = array_column( $groups['media']['cards'], null, 'key' );
		$this->assertSame( 'sensitive', $cards['mutable_adapter']['privacy'] );
		$this->assertArrayNotHasKey( 'commerce', $groups );
	}

	public function test_incompatible_workflow_is_diagnostic_only_and_never_invokable(): void {
		$adapter = new Second_Post_Merge_Mutable_Adapter( key: 'future_workflow', workflow_api: '2.0.0' );
		$this->assertTrue( $this->registry->register( $adapter ) );

		$this->assertSame( array(), $this->registry->available_for_user( 1 ) );
		$this->assertSame( 'unavailable', $this->registry->creation_state_for_user( 1 ) );
		$this->assertSame( 'workflow_api_mismatch', $this->registry->errors()['future_workflow']['code'] );
	}

	public function test_successful_re_registration_clears_stale_error_for_same_key(): void {
		$adapter = new Second_Post_Merge_Mutable_Adapter( key: 'corrected_adapter', privacy: 'unknown' );
		$this->assertInstanceOf( WP_Error::class, $this->registry->register( $adapter ) );
		$this->assertArrayHasKey( 'corrected_adapter', $this->registry->errors() );

		$adapter->privacy_value = 'private';
		$this->assertTrue( $this->registry->register( $adapter ) );
		$this->assertArrayNotHasKey( 'corrected_adapter', $this->registry->errors() );
	}

	public function test_file21_readiness_uses_registration_snapshot_not_mutated_metadata(): void {
		$adapter = new Second_Post_Merge_Mutable_Adapter(
			key: 'social_publication',
			group: 'publishing',
			priority: 10,
			native_module: 'sabri-complete-home-news-feed',
			minimum_version: '1.0.3',
			capability: 'sabri_feed_create_posts',
			privacy: 'public'
		);
		$this->assertTrue( $this->registry->register( $adapter ) );

		$adapter->group_value           = 'commerce';
		$adapter->native_module_value   = 'foreign-module';
		$adapter->minimum_version_value = '0.1.0';
		$adapter->capability_value      = 'read';
		$adapter->privacy_value         = 'sensitive';

		$report = ( new Core_Adapter_Requirements( $this->registry ) )->social_publication_report();
		$this->assertSame( 'pass', $report['status'] );
		$this->assertSame( array(), $report['codes'] );
		$this->assertSame( 'sabri-complete-home-news-feed', $report['native_module'] );
		$this->assertSame( '1.0.3', $report['minimum_native'] );
	}
}
