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
	function wp_json_encode( mixed $value ): string|false {
		return json_encode( $value );
	}
}

require_once dirname( __DIR__ ) . '/includes/contracts/interface-workflow-adapter.php';
require_once dirname( __DIR__ ) . '/includes/core/class-workflow-coordinator.php';

final class Coordinator_Test_Workflow_Adapter implements Workflow_Adapter {
	/** @var array<string,array<string,mixed>> */
	private array $submitted = array();
	public int $submit_calls = 0;
	public bool $throw_on_preview = false;
	public string $preview_url = '/native-preview/ref-1/';
	public string $canonical_url_value = '/native/ref-1/';
	public string $status_value = 'pending_review';

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
	public function is_available(): bool { return true; }
	public function can_create( int $user_id ): bool { return 1 === $user_id; }
	public function start_url( int $user_id ): string { return '/native/create/?user=' . $user_id; }
	public function schema_version(): string { return '1.0.0'; }
	public function supports_native_drafts(): bool { return true; }
	public function schema(): array { return array( 'version' => '1.0.0', 'fields' => array( 'title' => array( 'type' => 'string' ) ) ); }
	public function create_draft( int $user_id, ?string $native_reference, array $payload ) {
		unset( $user_id, $payload );
		return array( 'native_reference' => $native_reference ?? 'draft-1', 'status' => 'draft' );
	}
	public function validate( int $user_id, array $payload ) {
		unset( $user_id );
		return array( 'valid' => isset( $payload['title'] ), 'errors' => isset( $payload['title'] ) ? array() : array( 'title_required' ) );
	}
	public function preview( int $user_id, array $payload ) {
		unset( $user_id, $payload );
		if ( $this->throw_on_preview ) {
			throw new RuntimeException( 'Private payload details must never be logged.' );
		}
		return array( 'preview_url' => $this->preview_url, 'expires_at' => 2000000000 );
	}
	public function submit( int $user_id, string $idempotency_key, array $payload ) {
		unset( $user_id, $payload );
		++$this->submit_calls;
		if ( ! isset( $this->submitted[ $idempotency_key ] ) ) {
			$this->submitted[ $idempotency_key ] = array(
				'native_reference' => 'post-1',
				'status'           => $this->status_value,
				'canonical_url'    => $this->canonical_url_value,
			);
		}
		return $this->submitted[ $idempotency_key ];
	}
	public function status( int $user_id, string $native_reference ) {
		unset( $user_id );
		return array( 'native_reference' => $native_reference, 'status' => $this->status_value, 'canonical_url' => $this->canonical_url_value );
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

	public function test_schema_draft_validation_preview_status_and_canonical_url_are_guarded(): void {
		$schema = $this->coordinator->schema( 1, 'workflow_test' );
		$this->assertIsArray( $schema );
		$this->assertSame( '1.0.0', $schema['version'] );

		$draft = $this->coordinator->create_draft( 1, 'workflow_test', null, array( 'title' => 'Test' ) );
		$this->assertIsArray( $draft );
		$this->assertSame( 'draft-1', $draft['native_reference'] );

		$validation = $this->coordinator->validate( 1, 'workflow_test', array( 'title' => 'Test' ) );
		$this->assertIsArray( $validation );
		$this->assertTrue( $validation['valid'] );

		$preview = $this->coordinator->preview( 1, 'workflow_test', array( 'title' => 'Test' ) );
		$this->assertIsArray( $preview );
		$this->assertSame( '/native-preview/ref-1/', $preview['preview_url'] );

		$status = $this->coordinator->status( 1, 'workflow_test', 'post-1' );
		$this->assertIsArray( $status );
		$this->assertSame( 'pending_review', $status['status'] );
		$this->assertSame( '/native/ref-1/', $this->coordinator->canonical_url( 1, 'workflow_test', 'post-1' ) );
	}

	public function test_suspended_or_unauthorized_user_is_denied_before_native_workflow(): void {
		$result = $this->coordinator->schema( 2, 'workflow_test' );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'supc_workflow_permission_denied', $result->code );
	}

	public function test_payload_objects_and_oversized_payloads_are_rejected(): void {
		$object_result = $this->coordinator->validate( 1, 'workflow_test', array( 'unsafe' => new stdClass() ) );
		$this->assertInstanceOf( WP_Error::class, $object_result );
		$this->assertSame( 'supc_invalid_workflow_payload', $object_result->code );

		$large_result = $this->coordinator->validate( 1, 'workflow_test', array( 'body' => str_repeat( 'a', 1048577 ) ) );
		$this->assertInstanceOf( WP_Error::class, $large_result );
		$this->assertSame( 'supc_workflow_payload_too_large', $large_result->code );
	}

	public function test_submission_requires_a_strong_idempotency_key_and_preserves_native_idempotency(): void {
		$invalid = $this->coordinator->submit( 1, 'workflow_test', 'short', array( 'title' => 'Test' ) );
		$this->assertInstanceOf( WP_Error::class, $invalid );
		$this->assertSame( 'supc_invalid_idempotency_key', $invalid->code );
		$this->assertSame( 0, $this->adapter->submit_calls );

		$key    = $this->coordinator->generate_idempotency_key();
		$first  = $this->coordinator->submit( 1, 'workflow_test', $key, array( 'title' => 'Test' ) );
		$second = $this->coordinator->submit( 1, 'workflow_test', $key, array( 'title' => 'Test' ) );
		$this->assertMatchesRegularExpression( '/^[A-Za-z0-9][A-Za-z0-9._:-]{31,127}$/', $key );
		$this->assertSame( $first, $second );
		$this->assertSame( 2, $this->adapter->submit_calls );
	}

	public function test_external_preview_and_canonical_urls_fail_closed(): void {
		$this->adapter->preview_url = 'https://external.example/preview/';
		$preview = $this->coordinator->preview( 1, 'workflow_test', array( 'title' => 'Test' ) );
		$this->assertInstanceOf( WP_Error::class, $preview );
		$this->assertSame( 'supc_invalid_preview_result', $preview->code );

		$this->adapter->canonical_url_value = 'http://example.test/native/post-1/';
		$result = $this->coordinator->submit( 1, 'workflow_test', $this->coordinator->generate_idempotency_key(), array( 'title' => 'Test' ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'supc_invalid_canonical_url', $result->code );
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

	public function test_invalid_native_status_is_rejected(): void {
		$this->adapter->status_value = 'unknown-state';
		$result = $this->coordinator->status( 1, 'workflow_test', 'post-1' );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'supc_invalid_native_status', $result->code );
	}
}
