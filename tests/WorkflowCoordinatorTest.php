<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Sabri\UniversalComposer\Contracts\Workflow_Adapter;
use Sabri\UniversalComposer\Core\Permission_Resolver;
use Sabri\UniversalComposer\Core\Plugin;
use Sabri\UniversalComposer\Core\Registry;
use Sabri\UniversalComposer\Core\Workflow_Coordinator;

final class Coordinator_Test_Workflow_Adapter implements Workflow_Adapter {
	/** @var array<string, array<string, mixed>> */
	private array $submitted = array();

	public string $adapter_key = 'workflow_test';
	public int $submit_calls = 0;
	public int $draft_calls = 0;
	public int $availability_calls = 0;
	public int $authorization_calls = 0;
	public int $schema_calls = 0;
	public bool $throw_on_preview = false;
	public bool $supports_drafts = true;
	public bool $available = true;
	public string $workflow_api_version_value = '1.0.0';
	public string $preview_url = '/native-preview/ref-1/';
	public int $preview_expires_at;
	public string $canonical_url_value = '/native/ref-1/';
	public string $status_value = 'pending_review';
	public string $draft_status_value = 'draft';
	public bool $omit_draft_status = false;
	public string $native_error_operation = '';
	/** @var array<int|string, mixed> */
	public array $validation_errors = array();
	/** @var array<int|string, mixed> */
	public array $validation_warnings = array();
	/** @var array<string, mixed> */
	public array $extra_result = array();
	/** @var array<string, mixed> */
	public array $schema_fields = array(
		'title' => array(
			'type'          => 'text',
			'label_code'    => 'title_label',
			'required'      => true,
			'privacy_class' => 'public',
		),
	);
	/** @var array<string, int> */
	public array $reference_owners = array( 'post-1' => 1 );

	public function __construct() {
		$this->preview_expires_at = time() + 300;
	}

	public function api_version(): string { return '1.0.0'; }
	public function key(): string { return $this->adapter_key; }
	public function label(): string { return 'Workflow Test'; }
	public function description(): string { return 'Workflow test adapter.'; }
	public function group(): string { return 'publishing'; }
	public function icon(): string { return 'edit'; }
	public function priority(): int { return 10; }
	public function native_module(): string { return 'file99'; }
	public function minimum_native_version(): string { return '1.0.0'; }
	public function required_capability(): string { return 'publish_posts'; }
	public function privacy_classification(): string { return 'private'; }
	public function is_available(): bool { ++$this->availability_calls; return $this->available; }
	public function can_create( int $user_id ): bool { ++$this->authorization_calls; return $this->available && in_array( $user_id, array( 1, 3 ), true ); }
	public function start_url( int $user_id ): string { return '/native/create/?user=' . $user_id; }
	public function workflow_api_version(): string { return $this->workflow_api_version_value; }
	public function schema_version(): string { return '1.0.0'; }
	public function supports_native_drafts(): bool { return $this->supports_drafts; }
	public function schema(): array {
		++$this->schema_calls;
		return array( 'version' => '1.0.0', 'fields' => $this->schema_fields, 'private_meta' => 'must-not-be-returned' );
	}
	public function create_draft( int $user_id, ?string $native_reference, array $payload ) {
		unset( $user_id, $payload );
		++$this->draft_calls;
		if ( 'create_draft' === $this->native_error_operation ) {
			return new WP_Error( 'patient_john_doe_positive', 'Patient narrative must not escape.', array( 'secret' => 'identity' ) );
		}
		$result = array( 'native_reference' => $native_reference ?? 'draft-1', 'private_payload' => 'must-not-be-returned' );
		if ( ! $this->omit_draft_status ) {
			$result['status'] = $this->draft_status_value;
		}
		return array_merge( $result, $this->extra_result );
	}
	public function validate( int $user_id, array $payload ) {
		unset( $user_id );
		if ( 'validate' === $this->native_error_operation ) {
			return new WP_Error( 'validation_failed', 'Private validation message.', array( 'payload' => $payload ) );
		}
		return array_merge( array( 'valid' => isset( $payload['title'] ), 'errors' => $this->validation_errors, 'warnings' => $this->validation_warnings, 'private_detail' => 'must-not-be-returned' ), $this->extra_result );
	}
	public function preview( int $user_id, array $payload ) {
		unset( $user_id, $payload );
		if ( $this->throw_on_preview ) {
			throw new RuntimeException( 'Private payload details must never be logged.' );
		}
		return array_merge( array( 'preview_url' => $this->preview_url, 'expires_at' => $this->preview_expires_at, 'private_detail' => 'must-not-be-returned' ), $this->extra_result );
	}
	public function submit( int $user_id, string $idempotency_key, array $payload ) {
		unset( $user_id, $payload );
		++$this->submit_calls;
		if ( ! isset( $this->submitted[ $idempotency_key ] ) ) {
			$this->submitted[ $idempotency_key ] = array_merge( array( 'native_reference' => 'post-1', 'status' => $this->status_value, 'canonical_url' => $this->canonical_url_value, 'private_detail' => 'must-not-be-returned' ), $this->extra_result );
		}
		return $this->submitted[ $idempotency_key ];
	}
	public function status( int $user_id, string $native_reference ) {
		if ( ( $this->reference_owners[ $native_reference ] ?? 0 ) !== $user_id ) {
			return new WP_Error( 'permission_denied', 'Private ownership detail.' );
		}
		return array( 'native_reference' => $native_reference, 'status' => $this->status_value, 'canonical_url' => $this->canonical_url_value );
	}
	public function canonical_url( int $user_id, string $native_reference ): string {
		return ( $this->reference_owners[ $native_reference ] ?? 0 ) === $user_id ? $this->canonical_url_value : '';
	}
}

final class WorkflowCoordinatorTest extends TestCase {
	private Registry $registry;
	private Permission_Resolver $permissions;
	private Workflow_Coordinator $coordinator;
	private Coordinator_Test_Workflow_Adapter $adapter;

	protected function setUp(): void {
		$GLOBALS['supc_test_statuses']       = array( 1 => 'approved', 2 => 'suspended', 3 => 'approved' );
		$GLOBALS['supc_test_capabilities']   = array( 1 => array( 'publish_posts' => true ), 3 => array( 'publish_posts' => true ) );
		$GLOBALS['supc_test_actions_fired']  = array();
		$GLOBALS['supc_test_uuid_counter']   = 0;
		$GLOBALS['supc_test_current_user']   = 1;
		$GLOBALS['supc_test_manage_options'] = false;
		$this->permissions                   = new Permission_Resolver();
		$this->registry                      = new Registry( $this->permissions );
		$this->coordinator                   = new Workflow_Coordinator( $this->registry, $this->permissions );
		$this->adapter                       = new Coordinator_Test_Workflow_Adapter();
		$this->assertTrue( $this->registry->register( $this->adapter ) );
		$this->reset_native_counters();
	}

	public function test_schema_and_results_are_guarded_and_whitelisted(): void {
		$schema = $this->coordinator->schema( 1, 'workflow_test' );
		$this->assertIsArray( $schema );
		$this->assertSame( array( 'version', 'fields' ), array_keys( $schema ) );
		$draft = $this->coordinator->create_draft( 1, 'workflow_test', null, array( 'title' => 'Test' ) );
		$this->assertSame( array( 'native_reference', 'status' ), array_keys( $draft ) );
		$preview = $this->coordinator->preview( 1, 'workflow_test', array( 'title' => 'Test' ) );
		$this->assertSame( array( 'preview_url', 'expires_at' ), array_keys( $preview ) );
	}

	public function test_ineligible_subject_is_denied_before_native_methods(): void {
		$result = $this->coordinator->schema( 2, 'workflow_test' );
		$this->assertSame( 'supc_workflow_permission_denied', $result->code );
		$this->assertSame( 0, $this->adapter->authorization_calls );
		$this->assertSame( 0, $this->adapter->availability_calls );
	}

	public function test_unavailable_native_is_not_mislabeled_as_permission_denial(): void {
		$this->adapter->available = false;
		$result = $this->coordinator->schema( 1, 'workflow_test' );
		$this->assertSame( 'supc_native_workflow_unavailable', $result->code );
		$this->assertSame( 1, $this->adapter->availability_calls );
		$this->assertSame( 0, $this->adapter->authorization_calls );
	}

	public function test_workflow_api_and_native_draft_contracts_fail_closed(): void {
		$this->registry->unregister( 'workflow_test' );
		$mismatch = new Coordinator_Test_Workflow_Adapter();
		$mismatch->workflow_api_version_value = '2.0.0';
		$this->assertTrue( $this->registry->register( $mismatch ) );
		$this->assertSame( 'supc_workflow_api_mismatch', $this->coordinator->schema( 1, 'workflow_test' )->code );
		$this->registry->unregister( 'workflow_test' );
		$unsupported = new Coordinator_Test_Workflow_Adapter();
		$unsupported->supports_drafts = false;
		$this->assertTrue( $this->registry->register( $unsupported ) );
		$this->assertSame( 'supc_native_drafts_unsupported', $this->coordinator->create_draft( 1, 'workflow_test', null, array( 'title' => 'Test' ) )->code );
	}

	public function test_draft_requires_explicit_draft_state(): void {
		$this->adapter->omit_draft_status = true;
		$this->assertInstanceOf( WP_Error::class, $this->coordinator->create_draft( 1, 'workflow_test', null, array( 'title' => 'Test' ) ) );
		$this->adapter->omit_draft_status = false;
		$this->adapter->draft_status_value = 'published';
		$this->assertSame( 'supc_invalid_native_result', $this->coordinator->create_draft( 1, 'workflow_test', null, array( 'title' => 'Test' ) )->code );
	}

	public function test_schema_and_payload_contracts_are_strict(): void {
		$this->adapter->schema_fields['title']['default'] = 'Private value';
		$this->assertSame( 'supc_invalid_schema_contract', $this->coordinator->schema( 1, 'workflow_test' )->code );
		$this->adapter->schema_fields = array(
			'category'  => array( 'type' => 'select', 'label_code' => 'category_label', 'required' => true, 'privacy_class' => 'public', 'choices' => array( 'news' => 'choice_news' ) ),
			'amount'    => array( 'type' => 'number', 'label_code' => 'amount_label', 'required' => false, 'privacy_class' => 'private', 'minimum' => 1, 'maximum' => 5 ),
			'case_date' => array( 'type' => 'date', 'label_code' => 'case_date', 'required' => false, 'privacy_class' => 'private' ),
			'case_time' => array( 'type' => 'datetime', 'label_code' => 'case_time', 'required' => false, 'privacy_class' => 'private' ),
			'source'    => array( 'type' => 'url', 'label_code' => 'source', 'required' => false, 'privacy_class' => 'public' ),
		);
		$this->assertSame( 'supc_workflow_payload_unknown_field', $this->coordinator->validate( 1, 'workflow_test', array( 'unknown' => 'x' ) )->code );
		$this->assertSame( 'supc_workflow_payload_required_field_missing', $this->coordinator->validate( 1, 'workflow_test', array() )->code );
		$this->assertSame( 'supc_workflow_payload_field_invalid', $this->coordinator->validate( 1, 'workflow_test', array( 'category' => 'other' ) )->code );
		$this->assertSame( 'supc_workflow_payload_field_invalid', $this->coordinator->validate( 1, 'workflow_test', array( 'category' => 'news', 'amount' => 9 ) )->code );
		$this->assertSame( 'supc_workflow_payload_field_invalid', $this->coordinator->validate( 1, 'workflow_test', array( 'category' => 'news', 'case_date' => '2026-02-30' ) )->code );
		$this->assertSame( 'supc_workflow_payload_field_invalid', $this->coordinator->validate( 1, 'workflow_test', array( 'category' => 'news', 'case_time' => '2026-07-29T24:10' ) )->code );
		$this->assertSame( 'supc_workflow_payload_field_invalid', $this->coordinator->validate( 1, 'workflow_test', array( 'category' => 'news', 'case_time' => '2026-07-29T23:10+14:01' ) )->code );
		$this->assertSame( 'supc_workflow_payload_field_invalid', $this->coordinator->validate( 1, 'workflow_test', array( 'category' => 'news', 'case_time' => '2026-07-29T23:10+05:99' ) )->code );
		$this->assertSame( 'supc_workflow_payload_field_invalid', $this->coordinator->validate( 1, 'workflow_test', array( 'category' => 'news', 'source' => 'file:///etc/passwd' ) )->code );
		$this->assertSame( 'supc_workflow_payload_field_invalid', $this->coordinator->validate( 1, 'workflow_test', array( 'category' => 'news', 'source' => 'https://user:pass@example.test/private' ) )->code );
		$this->assertSame( 'supc_workflow_payload_field_invalid', $this->coordinator->validate( 1, 'workflow_test', array( 'category' => 'news', 'source' => 'https://user@example.test/private' ) )->code );
		$this->assertSame( 'supc_workflow_payload_field_invalid', $this->coordinator->validate( 1, 'workflow_test', array( 'category' => 'news', 'source' => 'https://user:@example.test/private' ) )->code );
		$this->assertIsArray(
			$this->coordinator->validate(
				1,
				'workflow_test',
				array(
					'category'  => 'news',
					'amount'    => 3,
					'case_date' => '2028-02-29',
					'case_time' => '2026-07-29T23:10:45+05:00',
					'source'    => 'https://example.test/source',
				)
			)
		);
	}

	public function test_canonical_url_is_subject_bound(): void {
		$this->assertSame( '/native/ref-1/', $this->coordinator->canonical_url( 1, 'workflow_test', 'post-1' ) );
		$this->assertSame( 'supc_invalid_canonical_url', $this->coordinator->canonical_url( 3, 'workflow_test', 'post-1' )->code );
	}

	public function test_public_functions_bind_to_current_subject(): void {
		$public_adapter = new Coordinator_Test_Workflow_Adapter();
		$public_adapter->adapter_key = 'public_workflow';
		$this->assertTrue( Plugin::instance()->registry()->register( $public_adapter ) );
		$GLOBALS['supc_test_current_user'] = 2;
		$this->assertSame( 'supc_workflow_permission_denied', supc_workflow_schema( 'public_workflow' )->code );
		$this->assertSame( 1, ( new ReflectionFunction( 'supc_workflow_schema' ) )->getNumberOfParameters() );
		Plugin::instance()->registry()->unregister( 'public_workflow' );
	}

	public function test_native_error_and_exception_diagnostics_are_allowlisted(): void {
		$this->adapter->native_error_operation = 'create_draft';
		$result = $this->coordinator->create_draft( 1, 'workflow_test', null, array( 'title' => 'Test' ) );
		$this->assertSame( 'native_error', $result->data['native_code'] );
		$this->assertStringNotContainsString( 'john_doe', serialize( $result ) );
		$this->adapter->native_error_operation = 'validate';
		$allowed = $this->coordinator->validate( 1, 'workflow_test', array( 'title' => 'Safe' ) );
		$this->assertSame( 'validation_failed', $allowed->data['native_code'] );
		$this->adapter->native_error_operation = '';
		$this->adapter->throw_on_preview = true;
		$this->coordinator->preview( 1, 'workflow_test', array( 'title' => 'Safe' ) );
		$record = end( $GLOBALS['supc_test_actions_fired'] );
		$this->assertSame( array( 'workflow_test', 'preview', 'native_exception' ), $record[1] );
	}

	public function test_idempotency_preview_and_status_limits_remain_enforced(): void {
		$this->assertInstanceOf( WP_Error::class, $this->coordinator->validate( 1, 'workflow_test', array( 'title' => new stdClass() ) ) );
		$this->assertInstanceOf( WP_Error::class, $this->coordinator->submit( 1, 'workflow_test', str_repeat( 'a', 64 ), array( 'title' => 'Test' ) ) );
		$key = $this->coordinator->generate_idempotency_key();
		$this->assertSame( $this->coordinator->submit( 1, 'workflow_test', $key, array( 'title' => 'Test' ) ), $this->coordinator->submit( 1, 'workflow_test', $key, array( 'title' => 'Test' ) ) );
		$this->adapter->preview_url = 'https://external.example/preview/';
		$this->assertInstanceOf( WP_Error::class, $this->coordinator->preview( 1, 'workflow_test', array( 'title' => 'Test' ) ) );
		$this->adapter->status_value = 'unknown-state';
		$this->assertInstanceOf( WP_Error::class, $this->coordinator->status( 1, 'workflow_test', 'post-1' ) );
	}

	private function reset_native_counters(): void {
		$this->adapter->availability_calls  = 0;
		$this->adapter->authorization_calls = 0;
		$this->adapter->schema_calls        = 0;
	}
}
