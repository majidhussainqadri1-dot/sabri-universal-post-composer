<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Sabri\UniversalComposer\Contracts\Workflow_Adapter;
use Sabri\UniversalComposer\Core\Permission_Resolver;
use Sabri\UniversalComposer\Core\Registry;
use Sabri\UniversalComposer\Core\Workflow_Coordinator;

if ( ! defined( 'SUPC_WORKFLOW_API_VERSION' ) ) {
	define( 'SUPC_WORKFLOW_API_VERSION', '1.0.0' );
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( mixed $value, int $flags = 0, int $depth = 512 ): string|false {
		return json_encode( $value, $flags, $depth );
	}
}

require_once dirname( __DIR__ ) . '/includes/contracts/interface-workflow-adapter.php';
require_once dirname( __DIR__ ) . '/includes/core/class-workflow-coordinator.php';

final class Coordinator_Test_Workflow_Adapter implements Workflow_Adapter {
	/** @var array<string, array<string, mixed>> */
	private array $submitted = array();

	public int $submit_calls = 0;
	public int $draft_calls = 0;
	public int $availability_calls = 0;
	public bool $throw_on_preview = false;
	public bool $supports_drafts = true;
	public bool $available = true;
	public string $workflow_api_version_value = '1.0.0';
	public string $preview_url = '/native-preview/ref-1/';
	public int $preview_expires_at;
	public string $canonical_url_value = '/native/ref-1/';
	public string $status_value = 'pending_review';
	public string $native_error_operation = '';
	/** @var array<int|string, mixed> */
	public array $validation_errors = array();
	/** @var array<int|string, mixed> */
	public array $validation_warnings = array();
	/** @var array<string, mixed> */
	public array $extra_result = array();
	/** @var array<string, mixed> */
	public array $schema_fields = array( 'title' => array( 'type' => 'string' ) );

	public function __construct() {
		$this->preview_expires_at = time() + 300;
	}

	public function api_version(): string { return '1.0.0'; }
	public function key(): string { return 'workflow_test'; }
	public function label(): string { return 'Workflow Test'; }
	public function description(): string { return 'Workflow test adapter.'; }
	public function group(): string { return 'publishing'; }
	public function icon(): string { return 'edit'; }
	public function priority(): int { return 10; }
	public function native_module(): string { return 'file99'; }
	public function minimum_native_version(): string { return '1.0.0'; }
	public function required_capability(): string { return 'publish_posts'; }
	public function privacy_classification(): string { return 'private'; }
	public function is_available(): bool {
		++$this->availability_calls;
		return $this->available;
	}
	public function can_create( int $user_id ): bool { return 1 === $user_id; }
	public function start_url( int $user_id ): string { return '/native/create/?user=' . $user_id; }
	public function workflow_api_version(): string { return $this->workflow_api_version_value; }
	public function schema_version(): string { return '1.0.0'; }
	public function supports_native_drafts(): bool { return $this->supports_drafts; }
	public function schema(): array {
		return array(
			'version'      => '1.0.0',
			'fields'       => $this->schema_fields,
			'private_meta' => 'must-not-be-returned',
		);
	}
	public function create_draft( int $user_id, ?string $native_reference, array $payload ) {
		unset( $user_id, $payload );
		++$this->draft_calls;
		if ( 'create_draft' === $this->native_error_operation ) {
			return new WP_Error( 'native_private_failure', 'Patient narrative must not escape.', array( 'secret' => 'identity' ) );
		}
		return array_merge(
			array(
				'native_reference' => $native_reference ?? 'draft-1',
				'status'           => 'draft',
				'private_payload'  => 'must-not-be-returned',
			),
			$this->extra_result
		);
	}
	public function validate( int $user_id, array $payload ) {
		unset( $user_id );
		if ( 'validate' === $this->native_error_operation ) {
			return new WP_Error( 'native_private_failure', 'Private validation message.', array( 'payload' => $payload ) );
		}
		return array_merge(
			array(
				'valid'          => isset( $payload['title'] ),
				'errors'         => $this->validation_errors,
				'warnings'       => $this->validation_warnings,
				'private_detail' => 'must-not-be-returned',
			),
			$this->extra_result
		);
	}
	public function preview( int $user_id, array $payload ) {
		unset( $user_id, $payload );
		if ( $this->throw_on_preview ) {
			throw new RuntimeException( 'Private payload details must never be logged.' );
		}
		if ( 'preview' === $this->native_error_operation ) {
			return new WP_Error( 'native_preview_failure', 'Private preview message.', array( 'secret' => 'value' ) );
		}
		return array_merge(
			array(
				'preview_url'    => $this->preview_url,
				'expires_at'     => $this->preview_expires_at,
				'private_detail' => 'must-not-be-returned',
			),
			$this->extra_result
		);
	}
	public function submit( int $user_id, string $idempotency_key, array $payload ) {
		unset( $user_id, $payload );
		++$this->submit_calls;
		if ( 'submit' === $this->native_error_operation ) {
			return new WP_Error( 'native_submit_failure', 'Private submit message.', array( 'secret' => 'value' ) );
		}
		if ( ! isset( $this->submitted[ $idempotency_key ] ) ) {
			$this->submitted[ $idempotency_key ] = array_merge(
				array(
					'native_reference' => 'post-1',
					'status'           => $this->status_value,
					'canonical_url'    => $this->canonical_url_value,
					'private_detail'   => 'must-not-be-returned',
				),
				$this->extra_result
			);
		}
		return $this->submitted[ $idempotency_key ];
	}
	public function status( int $user_id, string $native_reference ) {
		unset( $user_id );
		return array_merge(
			array(
				'native_reference' => $native_reference,
				'status'           => $this->status_value,
				'canonical_url'    => $this->canonical_url_value,
				'private_detail'   => 'must-not-be-returned',
			),
			$this->extra_result
		);
	}
	public function canonical_url( string $native_reference ): string {
		unset( $native_reference );
		return $this->canonical_url_value;
	}
}

final class WorkflowCoordinatorTest extends TestCase {
	private Registry $registry;
	private Permission_Resolver $permissions;
	private Workflow_Coordinator $coordinator;
	private Coordinator_Test_Workflow_Adapter $adapter;

	protected function setUp(): void {
		$GLOBALS['supc_test_statuses']      = array( 1 => 'approved', 2 => 'suspended' );
		$GLOBALS['supc_test_capabilities']  = array( 1 => array( 'publish_posts' => true ) );
		$GLOBALS['supc_test_actions_fired'] = array();
		$GLOBALS['supc_test_uuid_counter']  = 0;
		$this->permissions                  = new Permission_Resolver();
		$this->registry                     = new Registry( $this->permissions );
		$this->coordinator                  = new Workflow_Coordinator( $this->registry, $this->permissions );
		$this->adapter                      = new Coordinator_Test_Workflow_Adapter();
		$this->assertTrue( $this->registry->register( $this->adapter ) );
	}

	public function test_schema_draft_validation_preview_status_and_canonical_url_are_guarded_and_whitelisted(): void {
		$schema = $this->coordinator->schema( 1, 'workflow_test' );
		$this->assertIsArray( $schema );
		$this->assertSame( array( 'version', 'fields' ), array_keys( $schema ) );
		$this->assertSame( '1.0.0', $schema['version'] );

		$draft = $this->coordinator->create_draft( 1, 'workflow_test', null, array( 'title' => 'Test' ) );
		$this->assertIsArray( $draft );
		$this->assertSame( array( 'native_reference', 'status' ), array_keys( $draft ) );
		$this->assertSame( 'draft-1', $draft['native_reference'] );

		$validation = $this->coordinator->validate( 1, 'workflow_test', array( 'title' => 'Test' ) );
		$this->assertIsArray( $validation );
		$this->assertSame( array( 'valid', 'errors', 'warnings' ), array_keys( $validation ) );
		$this->assertTrue( $validation['valid'] );

		$preview = $this->coordinator->preview( 1, 'workflow_test', array( 'title' => 'Test' ) );
		$this->assertIsArray( $preview );
		$this->assertSame( array( 'preview_url', 'expires_at' ), array_keys( $preview ) );
		$this->assertSame( '/native-preview/ref-1/', $preview['preview_url'] );

		$status = $this->coordinator->status( 1, 'workflow_test', 'post-1' );
		$this->assertIsArray( $status );
		$this->assertSame( array( 'native_reference', 'status', 'canonical_url' ), array_keys( $status ) );
		$this->assertSame( 'pending_review', $status['status'] );
		$this->assertSame( '/native/ref-1/', $this->coordinator->canonical_url( 1, 'workflow_test', 'post-1' ) );
	}

	public function test_permission_is_resolved_before_native_availability(): void {
		$this->adapter->available = false;
		$result = $this->coordinator->schema( 2, 'workflow_test' );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'supc_workflow_permission_denied', $result->code );
		$this->assertSame( 0, $this->adapter->availability_calls );
	}

	public function test_workflow_api_mismatch_and_unsupported_native_drafts_fail_closed(): void {
		$this->adapter->workflow_api_version_value = '2.0.0';
		$mismatch = $this->coordinator->schema( 1, 'workflow_test' );
		$this->assertInstanceOf( WP_Error::class, $mismatch );
		$this->assertSame( 'supc_workflow_api_mismatch', $mismatch->code );

		$this->adapter->workflow_api_version_value = '1.0.0';
		$this->adapter->supports_drafts            = false;
		$draft = $this->coordinator->create_draft( 1, 'workflow_test', null, array( 'title' => 'Test' ) );
		$this->assertInstanceOf( WP_Error::class, $draft );
		$this->assertSame( 'supc_native_drafts_unsupported', $draft->code );
		$this->assertSame( 0, $this->adapter->draft_calls );
	}

	public function test_payload_objects_oversized_payloads_and_oversized_results_are_rejected(): void {
		$object_result = $this->coordinator->validate( 1, 'workflow_test', array( 'unsafe' => new stdClass() ) );
		$this->assertInstanceOf( WP_Error::class, $object_result );
		$this->assertSame( 'supc_invalid_workflow_payload', $object_result->code );

		$large_result = $this->coordinator->validate( 1, 'workflow_test', array( 'body' => str_repeat( 'a', 1048577 ) ) );
		$this->assertInstanceOf( WP_Error::class, $large_result );
		$this->assertSame( 'supc_workflow_payload_too_large', $large_result->code );

		$this->adapter->extra_result = array( 'oversized' => str_repeat( 'b', 1048577 ) );
		$native_result = $this->coordinator->status( 1, 'workflow_test', 'post-1' );
		$this->assertInstanceOf( WP_Error::class, $native_result );
		$this->assertSame( 'supc_invalid_native_result', $native_result->code );
	}

	public function test_submission_requires_two_uuid_v4_values_and_preserves_native_idempotency(): void {
		$invalid = $this->coordinator->submit( 1, 'workflow_test', str_repeat( 'a', 64 ), array( 'title' => 'Test' ) );
		$this->assertInstanceOf( WP_Error::class, $invalid );
		$this->assertSame( 'supc_invalid_idempotency_key', $invalid->code );
		$this->assertSame( 0, $this->adapter->submit_calls );

		$key    = $this->coordinator->generate_idempotency_key();
		$first  = $this->coordinator->submit( 1, 'workflow_test', $key, array( 'title' => 'Test' ) );
		$second = $this->coordinator->submit( 1, 'workflow_test', $key, array( 'title' => 'Test' ) );
		$this->assertMatchesRegularExpression( '/^[0-9a-f-]{36}:[0-9a-f-]{36}$/', $key );
		$this->assertSame( $first, $second );
		$this->assertSame( 2, $this->adapter->submit_calls );
		$this->assertArrayNotHasKey( 'private_detail', $first );
	}

	public function test_validation_accepts_only_canonical_codes(): void {
		$this->adapter->validation_errors = array( 'title' => array( 'title_required' ) );
		$valid = $this->coordinator->validate( 1, 'workflow_test', array() );
		$this->assertIsArray( $valid );
		$this->assertSame( array( 'title_required' ), $valid['errors']['title'] );

		$this->adapter->validation_errors = array( 'title' => array( 'Patient name leaked here' ) );
		$invalid = $this->coordinator->validate( 1, 'workflow_test', array() );
		$this->assertInstanceOf( WP_Error::class, $invalid );
		$this->assertSame( 'supc_invalid_validation_result', $invalid->code );
	}

	public function test_preview_requires_same_origin_url_and_short_lifetime(): void {
		$this->adapter->preview_url = 'https://external.example/preview/';
		$external = $this->coordinator->preview( 1, 'workflow_test', array( 'title' => 'Test' ) );
		$this->assertInstanceOf( WP_Error::class, $external );
		$this->assertSame( 'supc_invalid_preview_result', $external->code );

		$this->adapter->preview_url        = '/native-preview/ref-1/';
		$this->adapter->preview_expires_at = time() + 3601;
		$long_lived = $this->coordinator->preview( 1, 'workflow_test', array( 'title' => 'Test' ) );
		$this->assertInstanceOf( WP_Error::class, $long_lived );
		$this->assertSame( 'supc_invalid_preview_result', $long_lived->code );
	}

	public function test_external_or_downgraded_canonical_urls_fail_closed(): void {
		$this->adapter->canonical_url_value = 'http://example.test/native/post-1/';
		$result = $this->coordinator->submit( 1, 'workflow_test', $this->coordinator->generate_idempotency_key(), array( 'title' => 'Test' ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'supc_invalid_canonical_url', $result->code );
	}

	public function test_native_errors_are_normalized_without_message_data_or_payload_leakage(): void {
		$this->adapter->native_error_operation = 'validate';
		$result = $this->coordinator->validate( 1, 'workflow_test', array( 'patient_note' => 'Sensitive narrative' ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'supc_native_workflow_error', $result->code );
		$this->assertStringNotContainsString( 'Private validation message', serialize( $result ) );
		$this->assertStringNotContainsString( 'Sensitive narrative', serialize( $result ) );

		$record = end( $GLOBALS['supc_test_actions_fired'] );
		$this->assertSame( 'supc_workflow_native_error', $record[0] );
		$this->assertSame( array( 'workflow_test', 'validate', 'native_private_failure' ), $record[1] );
	}

	public function test_native_exceptions_are_isolated_without_logging_payload_or_message(): void {
		$this->adapter->throw_on_preview = true;
		$result = $this->coordinator->preview( 1, 'workflow_test', array( 'patient_note' => 'Sensitive narrative' ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'supc_workflow_adapter_exception', $result->code );

		$record = end( $GLOBALS['supc_test_actions_fired'] );
		$this->assertSame( 'supc_workflow_exception', $record[0] );
		$this->assertSame( array( 'workflow_test', 'preview', RuntimeException::class ), $record[1] );
		$this->assertStringNotContainsString( 'Sensitive narrative', serialize( $record ) );
		$this->assertStringNotContainsString( 'Private payload details', serialize( $record ) );
	}

	public function test_invalid_native_status_and_oversized_schema_are_rejected(): void {
		$this->adapter->status_value = 'unknown-state';
		$result = $this->coordinator->status( 1, 'workflow_test', 'post-1' );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'supc_invalid_native_status', $result->code );

		$this->adapter->status_value  = 'pending_review';
		$this->adapter->schema_fields = array( 'title' => array( 'label' => str_repeat( 'x', 262145 ) ) );
		$schema = $this->coordinator->schema( 1, 'workflow_test' );
		$this->assertInstanceOf( WP_Error::class, $schema );
		$this->assertSame( 'supc_invalid_schema_contract', $schema->code );
	}
}
