<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Sabri\UniversalComposer\Contracts\Workflow_Adapter;
use Sabri\UniversalComposer\Core\Permission_Resolver;
use Sabri\UniversalComposer\Core\Registry;
use Sabri\UniversalComposer\Core\Workflow_Coordinator;

final class Fourth_Review_Workflow_Adapter implements Workflow_Adapter {
	public string $workflow_api = '1.0.0';
	public string $capability = 'publish_posts';
	public int $schema_calls = 0;
	public int $authorization_calls = 0;

	/** @var array<string, mixed> */
	public array $schema_fields = array(
		'title' => array(
			'type'          => 'text',
			'label_code'    => 'title',
			'required'      => true,
			'privacy_class' => 'public',
		),
	);

	public function api_version(): string { return '1.0.0'; }
	public function key(): string { return 'fourth_review'; }
	public function label(): string { return 'Fourth Review'; }
	public function description(): string { return 'Fourth complete review workflow adapter.'; }
	public function group(): string { return 'publishing'; }
	public function icon(): string { return 'edit'; }
	public function priority(): int { return 10; }
	public function native_module(): string { return 'fourth-review-module'; }
	public function minimum_native_version(): string { return '1.0.0'; }
	public function required_capability(): string { return $this->capability; }
	public function privacy_classification(): string { return 'private'; }
	public function is_available(): bool { return true; }
	public function can_create( int $user_id ): bool { ++$this->authorization_calls; return $user_id > 0; }
	public function start_url( int $user_id ): string { return '/create/fourth-review/?user=' . $user_id; }
	public function workflow_api_version(): string { return $this->workflow_api; }
	public function schema_version(): string { return '1.0.0'; }
	public function supports_native_drafts(): bool { return true; }
	public function schema(): array {
		++$this->schema_calls;
		return array( 'version' => '1.0.0', 'fields' => $this->schema_fields );
	}
	public function create_draft( int $user_id, ?string $native_reference, array $payload ) {
		unset( $user_id, $payload );
		return array( 'native_reference' => $native_reference ?? 'draft-1', 'status' => 'draft' );
	}
	public function validate( int $user_id, array $payload ) {
		unset( $user_id, $payload );
		return array( 'valid' => true, 'errors' => array(), 'warnings' => array() );
	}
	public function preview( int $user_id, array $payload ) {
		unset( $user_id, $payload );
		return array( 'preview_url' => '/preview/fourth-review/', 'expires_at' => time() + 300 );
	}
	public function submit( int $user_id, string $idempotency_key, array $payload ) {
		unset( $user_id, $idempotency_key, $payload );
		return array( 'native_reference' => 'post-1', 'status' => 'pending_review' );
	}
	public function status( int $user_id, string $native_reference ) {
		unset( $user_id );
		return array( 'native_reference' => $native_reference, 'status' => 'pending_review' );
	}
	public function canonical_url( int $user_id, string $native_reference ): string {
		unset( $user_id, $native_reference );
		return '/post/1/';
	}
}

final class FourthCompleteReviewTest extends TestCase {
	private Permission_Resolver $permissions;
	private Registry $registry;
	private Workflow_Coordinator $coordinator;
	private Fourth_Review_Workflow_Adapter $adapter;

	protected function setUp(): void {
		$GLOBALS['supc_test_statuses'] = array( 1 => 'approved' );
		$GLOBALS['supc_test_capabilities'] = array( 1 => array( 'publish_posts' => true ) );
		$GLOBALS['supc_test_current_user'] = 1;
		$GLOBALS['supc_test_manage_options'] = false;
		$GLOBALS['supc_test_options'] = array();

		$this->permissions = new Permission_Resolver();
		$this->registry = new Registry( $this->permissions );
		$this->coordinator = new Workflow_Coordinator( $this->registry, $this->permissions );
		$this->adapter = new Fourth_Review_Workflow_Adapter();
	}

	public function test_permission_denial_precedes_workflow_compatibility(): void {
		$this->adapter->workflow_api = '2.0.0';
		$this->adapter->capability = 'manage_options';
		$this->assertTrue( $this->registry->register( $this->adapter ) );

		$result = $this->coordinator->schema( 1, 'fourth_review' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'supc_workflow_permission_denied', $result->code );
		$this->assertSame( 0, $this->adapter->schema_calls );
		$this->assertSame( 0, $this->adapter->authorization_calls );
	}

	public function test_incompatible_contract_health_does_not_invoke_schema(): void {
		$this->adapter->workflow_api = '2.0.0';
		$this->assertTrue( $this->registry->register( $this->adapter ) );

		$health = $this->coordinator->contract_health( 'fourth_review' );

		$this->assertSame( 'fail', $health['status'] );
		$this->assertSame( array( 'workflow_api_mismatch' ), $health['codes'] );
		$this->assertSame( 0, $this->adapter->schema_calls );
	}

	public function test_missing_workflow_registration_metadata_fails_closed(): void {
		$this->assertTrue( $this->registry->register( $this->adapter ) );
		$property = new ReflectionProperty( Registry::class, 'workflow_contracts' );
		$property->setValue( $this->registry, array() );

		$health = $this->coordinator->contract_health( 'fourth_review' );

		$this->assertSame( 'fail', $health['status'] );
		$this->assertSame( array( 'workflow_registration_metadata_missing' ), $health['codes'] );
		$this->assertSame( 'missing', $health['workflow_api_version'] );
		$this->assertSame( 0, $this->adapter->schema_calls );
	}

	public function test_choice_fields_require_nonempty_declared_choices(): void {
		$this->adapter->schema_fields = array(
			'category' => array(
				'type'          => 'select',
				'label_code'    => 'category',
				'required'      => true,
				'privacy_class' => 'public',
			),
		);
		$this->assertTrue( $this->registry->register( $this->adapter ) );
		$this->assertSame( 'supc_invalid_schema_contract', $this->coordinator->schema( 1, 'fourth_review' )->code );

		$this->adapter->schema_fields['category']['choices'] = array();
		$this->assertSame( 'supc_invalid_schema_contract', $this->coordinator->schema( 1, 'fourth_review' )->code );

		$this->adapter->schema_fields['category']['choices'] = array( 'news' => 'choice_news' );
		$this->assertIsArray( $this->coordinator->schema( 1, 'fourth_review' ) );
	}

	public function test_adapter_permission_helper_uses_registered_capability(): void {
		$this->adapter->capability = 'publish_posts';

		$this->assertFalse( $this->permissions->can_use_adapter( 1, $this->adapter, 'manage_options' ) );
		$this->assertSame( 0, $this->adapter->authorization_calls );
		$this->assertTrue( $this->permissions->can_use_adapter( 1, $this->adapter, 'publish_posts' ) );
		$this->assertSame( 1, $this->adapter->authorization_calls );
	}
}
