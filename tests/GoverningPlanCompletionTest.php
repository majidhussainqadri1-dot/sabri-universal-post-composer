<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Sabri\UniversalComposer\Contracts\Lifecycle_Adapter;
use Sabri\UniversalComposer\Core\Governing_Plan_Runtime;
use Sabri\UniversalComposer\Core\Plugin;

final class Governing_Plan_Test_Adapter implements Lifecycle_Adapter {
	public function api_version(): string { return '1.0.0'; }
	public function workflow_api_version(): string { return '1.0.0'; }
	public function governance_api_version(): string { return '1.0.0'; }
	public function lifecycle_api_version(): string { return '1.0.0'; }
	public function schema_version(): string { return '1.0.0'; }
	public function supports_native_drafts(): bool { return true; }
	public function key(): string { return 'governed_runtime'; }
	public function label(): string { return 'Governed Runtime'; }
	public function description(): string { return 'Governed native workflow.'; }
	public function group(): string { return 'publishing'; }
	public function icon(): string { return 'edit'; }
	public function priority(): int { return 10; }
	public function native_module(): string { return 'governed-native-owner'; }
	public function minimum_native_version(): string { return '1.0.0'; }
	public function required_capability(): string { return 'publish_posts'; }
	public function privacy_classification(): string { return 'public'; }
	public function is_available(): bool { return true; }
	public function can_create( int $user_id ): bool { return 1 === $user_id; }
	public function start_url( int $user_id ): string { return 1 === $user_id ? '/create/?type=governed_runtime' : ''; }
	public function schema(): array {
		return array(
			'version' => '1.0.0',
			'fields'  => array(
				'title' => array( 'type' => 'text', 'label_code' => 'title', 'required' => true, 'privacy_class' => 'public' ),
			),
		);
	}
	public function create_draft( int $user_id, ?string $native_reference, array $payload ) {
		unset( $user_id, $payload );
		return array( 'native_reference' => $native_reference ?: 'native:governed:1', 'status' => 'draft' );
	}
	public function validate( int $user_id, array $payload ) {
		unset( $user_id, $payload );
		return array( 'valid' => true, 'errors' => array(), 'warnings' => array() );
	}
	public function preview( int $user_id, array $payload ) {
		unset( $user_id, $payload );
		return array( 'preview_url' => 'https://example.test/preview/governed/', 'expires_at' => time() + 300 );
	}
	public function submit( int $user_id, string $idempotency_key, array $payload ) {
		unset( $user_id, $idempotency_key );
		return array(
			'native_reference' => (string) ( $payload['native_reference'] ?? 'native:governed:1' ),
			'status'           => 'published',
			'canonical_url'    => 'https://example.test/governed/1/',
		);
	}
	public function status( int $user_id, string $native_reference ) {
		unset( $user_id );
		return array( 'native_reference' => $native_reference, 'status' => 'published' );
	}
	public function canonical_url( int $user_id, string $native_reference ): string {
		unset( $user_id, $native_reference );
		return 'https://example.test/governed/1/';
	}
	public function governance_profile(): array {
		return array(
			'authoring_features' => array(
				'rights_license',
				'accessibility_authoring',
				'translation',
				'corrections',
				'revision_history',
				'scheduling',
				'patient_case_safety',
				'medical_safety',
				'source_evidence',
				'preview_matrix',
				'search_projection',
				'notification_events',
			),
			'media_rules'            => 'native_opaque_references',
			'edit_capability'        => 'edit_posts',
			'cleanup_policy'         => 'reversible_native',
			'search_indexing_policy' => 'native_canonical',
			'notification_events'    => array( 'publication_corrected', 'publication_scheduled' ),
		);
	}
	public function lifecycle_capabilities( int $user_id, string $native_reference ) {
		unset( $native_reference );
		return 1 === $user_id ? array( 'edit', 'revise', 'correct', 'schedule', 'unschedule', 'withdraw', 'archive', 'restore' ) : array();
	}
	public function execute_lifecycle(
		int $user_id,
		string $native_reference,
		string $command,
		string $idempotency_key,
		array $payload
	) {
		unset( $user_id, $idempotency_key, $payload );
		return array(
			'native_reference' => $native_reference,
			'status'           => in_array( $command, array( 'schedule', 'unschedule' ), true ) ? 'scheduled' : 'published',
			'canonical_url'    => 'https://example.test/governed/1/',
		);
	}
}

final class GoverningPlanCompletionTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['supc_test_current_user'] = 1;
		$GLOBALS['supc_test_logged_in'] = true;
		$GLOBALS['supc_test_statuses'] = array( 1 => 'approved' );
		$GLOBALS['supc_test_capabilities'] = array(
			1 => array(
				'publish_posts' => true,
				'edit_posts'    => true,
			),
		);
		Plugin::instance()->registry()->unregister( 'governed_runtime' );
		$this->assertTrue( Plugin::instance()->registry()->register( new Governing_Plan_Test_Adapter() ) );
	}

	protected function tearDown(): void {
		Plugin::instance()->registry()->unregister( 'governed_runtime' );
	}

	public function test_governance_profile_covers_new_plan_cross_cutting_contracts(): void {
		$result = Governing_Plan_Runtime::instance()->governance_profile( 1, 'governed_runtime' );
		$this->assertIsArray( $result );
		$this->assertSame( '1.0.0', $result['governance_api_version'] );
		$this->assertContains( 'rights_license', $result['profile']['authoring_features'] );
		$this->assertContains( 'accessibility_authoring', $result['profile']['authoring_features'] );
		$this->assertContains( 'translation', $result['profile']['authoring_features'] );
		$this->assertContains( 'patient_case_safety', $result['profile']['authoring_features'] );
		$this->assertContains( 'medical_safety', $result['profile']['authoring_features'] );
		$this->assertContains( 'search_projection', $result['profile']['authoring_features'] );
		$this->assertContains( 'notification_events', $result['profile']['authoring_features'] );
		$this->assertSame( 'native_canonical', $result['profile']['search_indexing_policy'] );
	}

	public function test_native_lifecycle_correction_is_authorized_idempotent_and_reference_bound(): void {
		$runtime = Governing_Plan_Runtime::instance();
		$reference = 'native:governed:1';
		$capabilities = $runtime->lifecycle_capabilities( 1, 'governed_runtime', $reference );
		$this->assertIsArray( $capabilities );
		$this->assertContains( 'correct', $capabilities['commands'] );

		$result = $runtime->execute_lifecycle(
			1,
			'governed_runtime',
			$reference,
			'correct',
			'123e4567-e89b-42d3-a456-426614174000:123e4567-e89b-42d3-a456-426614174001',
			array( 'reason' => 'Correct a material statement.' )
		);
		$this->assertIsArray( $result );
		$this->assertSame( $reference, $result['native_reference'] );
		$this->assertSame( 'published', $result['status'] );
	}

	public function test_lifecycle_edit_authority_is_independent_from_new_create_authority(): void {
		$GLOBALS['supc_test_capabilities'][1]['publish_posts'] = false;
		$GLOBALS['supc_test_capabilities'][1]['edit_posts'] = true;
		$result = Governing_Plan_Runtime::instance()->execute_lifecycle(
			1,
			'governed_runtime',
			'native:governed:1',
			'correct',
			'123e4567-e89b-42d3-a456-426614174000:123e4567-e89b-42d3-a456-426614174001',
			array( 'reason' => 'Existing-object correction remains separately authorized.' )
		);
		$this->assertIsArray( $result );
		$this->assertSame( 'published', $result['status'] );
	}

	public function test_lifecycle_write_fails_closed_when_edit_capability_changes(): void {
		$GLOBALS['supc_test_capabilities'][1]['edit_posts'] = false;
		$result = Governing_Plan_Runtime::instance()->execute_lifecycle(
			1,
			'governed_runtime',
			'native:governed:1',
			'correct',
			'123e4567-e89b-42d3-a456-426614174000:123e4567-e89b-42d3-a456-426614174001',
			array( 'reason' => 'Denied after authority change.' )
		);
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'supc_lifecycle_permission_denied', $result->code );
	}

	public function test_internal_runtime_rejects_authorization_subject_spoofing(): void {
		$result = Governing_Plan_Runtime::instance()->lifecycle_capabilities( 2, 'governed_runtime', 'native:governed:1' );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'supc_lifecycle_permission_denied', $result->code );
	}

	public function test_public_governed_helpers_do_not_accept_arbitrary_user_ids(): void {
		$this->assertSame( 1, ( new ReflectionFunction( 'supc_adapter_governance' ) )->getNumberOfParameters() );
		$this->assertSame( 2, ( new ReflectionFunction( 'supc_lifecycle_capabilities' ) )->getNumberOfParameters() );
		$this->assertSame( 5, ( new ReflectionFunction( 'supc_execute_lifecycle' ) )->getNumberOfParameters() );
	}

	public function test_approved_adapter_catalog_uses_plan_keys_and_file_owners_without_guessed_slugs(): void {
		$catalog = Governing_Plan_Runtime::instance()->approved_adapter_catalog();
		$this->assertSame( '21', $catalog['social_publication']['owner_file'] );
		$this->assertSame( '05', $catalog['learning_lesson']['owner_file'] );
		$this->assertSame( '06', $catalog['encyclopedia_entry']['owner_file'] );
		$this->assertSame( '10', $catalog['video']['owner_file'] );
		$this->assertSame( '11', $catalog['reel']['owner_file'] );
		$this->assertSame( '12', $catalog['pdf_document']['owner_file'] );
		$this->assertSame( '18', $catalog['marketplace_listing']['owner_file'] );
		$this->assertArrayNotHasKey( 'native_module', $catalog['learning_lesson'] );
	}

	public function test_governed_rest_and_payload_controls_are_present(): void {
		$source = file_get_contents( dirname( __DIR__ ) . '/includes/core/class-governing-plan-runtime.php' );
		$this->assertIsString( $source );
		$this->assertStringContainsString( 'MAX_REQUEST_BYTES', $source );
		$this->assertStringContainsString( 'within_rate_limit', $source );
		$this->assertStringContainsString( 'unexpected_request_field', $source );
		$this->assertStringContainsString( 'MAX_COMMAND_PAYLOAD_BYTES', $source );
	}

	public function test_new_runtime_does_not_create_duplicate_native_content_storage(): void {
		$source = file_get_contents( dirname( __DIR__ ) . '/includes/core/class-governing-plan-runtime.php' );
		$this->assertIsString( $source );
		$this->assertStringNotContainsString( 'register_post_type(', $source );
		$this->assertStringNotContainsString( 'CREATE TABLE', $source );
		$this->assertStringContainsString( 'File 22 stores no command payload', $source );
	}
}
