<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Sabri\UniversalComposer\Contracts\Workflow_Adapter;
use Sabri\UniversalComposer\Core\Permission_Resolver;
use Sabri\UniversalComposer\Core\Registry;
use Sabri\UniversalComposer\Core\Workflow_Coordinator;

final class Subject_Schema_Test_Adapter implements Workflow_Adapter {
	public int $static_schema_calls = 0;
	public int $subject_schema_calls = 0;

	public function api_version(): string { return '1.0.0'; }
	public function workflow_api_version(): string { return '1.0.0'; }
	public function schema_version(): string { return '1.0.0'; }
	public function supports_native_drafts(): bool { return true; }
	public function key(): string { return 'subject_schema'; }
	public function label(): string { return 'Subject schema'; }
	public function description(): string { return 'Subject schema test.'; }
	public function group(): string { return 'publishing'; }
	public function icon(): string { return 'edit'; }
	public function priority(): int { return 10; }
	public function native_module(): string { return 'file98'; }
	public function minimum_native_version(): string { return '1.0.0'; }
	public function required_capability(): string { return 'publish_posts'; }
	public function privacy_classification(): string { return 'private'; }
	public function is_available(): bool { return true; }
	public function can_create( int $user_id ): bool { return in_array( $user_id, array( 1, 3 ), true ); }
	public function start_url( int $user_id ): string { return '/subject-schema/' . $user_id; }

	public function schema(): array {
		++$this->static_schema_calls;
		return $this->schema_envelope( array( 'standard' => 'type_standard', 'institutional' => 'type_institutional' ) );
	}

	public function schema_for_user( int $user_id ): array {
		++$this->subject_schema_calls;
		$choices = 1 === $user_id
			? array( 'standard' => 'type_standard' )
			: array( 'standard' => 'type_standard', 'institutional' => 'type_institutional' );
		return $this->schema_envelope( $choices );
	}

	public function create_draft( int $user_id, ?string $native_reference, array $payload ) { unset( $user_id, $payload ); return array( 'native_reference' => $native_reference ?? 'draft-1', 'status' => 'draft' ); }
	public function validate( int $user_id, array $payload ) { unset( $user_id, $payload ); return array( 'valid' => true, 'errors' => array(), 'warnings' => array() ); }
	public function preview( int $user_id, array $payload ) { unset( $user_id, $payload ); return array( 'preview_url' => '/preview/draft-1/', 'expires_at' => time() + 300 ); }
	public function submit( int $user_id, string $idempotency_key, array $payload ) { unset( $user_id, $idempotency_key, $payload ); return array( 'native_reference' => 'post-1', 'status' => 'pending_review' ); }
	public function status( int $user_id, string $native_reference ) { unset( $user_id ); return array( 'native_reference' => $native_reference, 'status' => 'pending_review' ); }
	public function canonical_url( int $user_id, string $native_reference ): string { unset( $user_id, $native_reference ); return '/post/1/'; }

	/** @param array<string,string> $choices */
	private function schema_envelope( array $choices ): array {
		return array(
			'version' => '1.0.0',
			'fields' => array(
				'content_type' => array(
					'type' => 'select',
					'label_code' => 'content_type',
					'required' => true,
					'privacy_class' => 'private',
					'choices' => $choices,
				),
			),
		);
	}
}

final class SubjectSchemaTest extends TestCase {
	private Workflow_Coordinator $coordinator;
	private Subject_Schema_Test_Adapter $adapter;

	protected function setUp(): void {
		$GLOBALS['supc_test_statuses'] = array( 1 => 'approved', 3 => 'approved' );
		$GLOBALS['supc_test_capabilities'] = array( 1 => array( 'publish_posts' => true ), 3 => array( 'publish_posts' => true ) );
		$permissions = new Permission_Resolver();
		$registry = new Registry( $permissions );
		$this->adapter = new Subject_Schema_Test_Adapter();
		$this->assertTrue( $registry->register( $this->adapter ) );
		$this->coordinator = new Workflow_Coordinator( $registry, $permissions );
	}

	public function test_contract_health_uses_role_neutral_schema_only(): void {
		$health = $this->coordinator->contract_health( 'subject_schema' );
		$this->assertSame( 'pass', $health['status'] );
		$this->assertSame( 'yes', $health['subject_schema_extension'] );
		$this->assertSame( 1, $this->adapter->static_schema_calls );
		$this->assertSame( 0, $this->adapter->subject_schema_calls );
	}

	public function test_interactive_schema_and_payload_validation_use_subject_schema(): void {
		$doctor = $this->coordinator->schema( 1, 'subject_schema' );
		$this->assertArrayNotHasKey( 'institutional', $doctor['fields']['content_type']['choices'] );
		$this->assertSame(
			'supc_workflow_payload_field_invalid',
			$this->coordinator->validate( 1, 'subject_schema', array( 'content_type' => 'institutional' ) )->code
		);

		$founder = $this->coordinator->schema( 3, 'subject_schema' );
		$this->assertArrayHasKey( 'institutional', $founder['fields']['content_type']['choices'] );
		$this->assertIsArray( $this->coordinator->validate( 3, 'subject_schema', array( 'content_type' => 'institutional' ) ) );
		$this->assertGreaterThanOrEqual( 4, $this->adapter->subject_schema_calls );
	}
}
