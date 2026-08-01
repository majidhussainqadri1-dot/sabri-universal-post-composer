<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Sabri\UniversalComposer\Contracts\Workflow_Adapter;
use Sabri\UniversalComposer\Core\Permission_Resolver;
use Sabri\UniversalComposer\Core\Registry;
use Sabri\UniversalComposer\Core\Workflow_Coordinator;

final class Eleventh_Workflow_Adapter implements Workflow_Adapter {
	public string $schema_version_value = '1.0.0';
	/** @var array<string,mixed> */
	public array $schema_fields = array(
		'tags' => array(
			'type'          => 'multiselect',
			'label_code'    => 'tags_label',
			'required'      => true,
			'privacy_class' => 'public',
			'choices'       => array( 'one' => 'choice_one', 'two' => 'choice_two' ),
		),
	);
	/** @var array<int|string,mixed> */
	public array $validation_errors = array();

	public function api_version(): string { return '1.0.0'; }
	public function key(): string { return 'eleventh_workflow'; }
	public function label(): string { return 'Eleventh workflow'; }
	public function description(): string { return 'Strict workflow regression adapter.'; }
	public function group(): string { return 'publishing'; }
	public function icon(): string { return 'admin-post'; }
	public function priority(): int { return 10; }
	public function native_module(): string { return 'eleventh-workflow-module'; }
	public function minimum_native_version(): string { return '1.0.0'; }
	public function required_capability(): string { return 'publish_posts'; }
	public function privacy_classification(): string { return 'private'; }
	public function is_available(): bool { return true; }
	public function can_create( int $user_id ): bool { return $user_id > 0; }
	public function start_url( int $user_id ): string { return '/create/eleventh-workflow/?user=' . $user_id; }
	public function workflow_api_version(): string { return '1.0.0'; }
	public function schema_version(): string { return $this->schema_version_value; }
	public function supports_native_drafts(): bool { return true; }
	public function schema(): array { return array( 'version' => $this->schema_version_value, 'fields' => $this->schema_fields ); }
	public function create_draft( int $user_id, ?string $native_reference, array $payload ) { unset( $user_id, $payload ); return array( 'native_reference' => $native_reference ?? 'draft-1', 'status' => 'draft' ); }
	public function validate( int $user_id, array $payload ) { unset( $user_id, $payload ); return array( 'valid' => array() === $this->validation_errors, 'errors' => $this->validation_errors, 'warnings' => array() ); }
	public function preview( int $user_id, array $payload ) { unset( $user_id, $payload ); return array( 'preview_url' => '/preview/eleventh/', 'expires_at' => time() + 60 ); }
	public function submit( int $user_id, string $idempotency_key, array $payload ) { unset( $user_id, $idempotency_key, $payload ); return array( 'native_reference' => 'post-1', 'status' => 'pending_review' ); }
	public function status( int $user_id, string $native_reference ) { unset( $user_id ); return array( 'native_reference' => $native_reference, 'status' => 'pending_review' ); }
	public function canonical_url( int $user_id, string $native_reference ): string { unset( $user_id, $native_reference ); return '/content/post-1/'; }
}

final class EleventhWorkflowHardeningTest extends TestCase {
	private Registry $registry;
	private Workflow_Coordinator $coordinator;
	private Eleventh_Workflow_Adapter $adapter;

	protected function setUp(): void {
		$GLOBALS['supc_test_statuses'] = array( 1 => 'approved' );
		$GLOBALS['supc_test_capabilities'] = array( 1 => array( 'publish_posts' => true ) );
		$GLOBALS['supc_test_options'] = array();
		$this->adapter = new Eleventh_Workflow_Adapter();
		$permissions = new Permission_Resolver();
		$this->registry = new Registry( $permissions );
		$this->coordinator = new Workflow_Coordinator( $this->registry, $permissions );
		$this->assertTrue( $this->registry->register( $this->adapter ) );
	}

	public function test_schema_version_uses_strict_semver(): void {
		$this->adapter->schema_version_value = '01.0.0';
		$result = $this->coordinator->schema( 1, 'eleventh_workflow' );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'supc_invalid_schema_contract', $result->code );
	}

	public function test_duplicate_multiselect_choices_are_rejected(): void {
		$result = $this->coordinator->validate(
			1,
			'eleventh_workflow',
			array( 'tags' => array( 'one', 'one' ) )
		);
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'supc_workflow_payload_field_invalid', $result->code );
	}

	public function test_validation_code_collection_is_bounded(): void {
		$this->adapter->validation_errors = array_fill( 0, 101, 'validation_failed' );
		$result = $this->coordinator->validate( 1, 'eleventh_workflow', array( 'tags' => array( 'one' ) ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'supc_invalid_validation_result', $result->code );
	}

	public function test_payload_element_count_is_bounded_before_native_invocation(): void {
		$payload = array();
		for ( $index = 0; $index < 1001; ++$index ) {
			$payload[ 'field_' . $index ] = 'x';
		}
		$result = $this->coordinator->validate( 1, 'eleventh_workflow', $payload );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'supc_invalid_workflow_payload', $result->code );
	}
}
