<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Sabri\UniversalComposer\Contracts\Adapter;
use Sabri\UniversalComposer\Contracts\Workflow_Adapter;
use Sabri\UniversalComposer\Core\Permission_Resolver;
use Sabri\UniversalComposer\Core\Registry;

class Sixth_Review_Adapter implements Adapter {
	public function api_version(): string { return '1.0.0'; }
	public function key(): string { return 'sixth_review'; }
	public function label(): string { return 'Sixth Review'; }
	public function description(): string { return 'Sixth complete review adapter.'; }
	public function group(): string { return 'publishing'; }
	public function icon(): string { return 'edit'; }
	public function priority(): int { return 10; }
	public function native_module(): string { return 'sixth-review-module'; }
	public function minimum_native_version(): string { return '1.0.0'; }
	public function required_capability(): string { return 'publish_posts'; }
	public function privacy_classification(): string { return 'private'; }
	public function is_available(): bool { return true; }
	public function can_create( int $user_id ): bool { return $user_id > 0; }
	public function start_url( int $user_id ): string { return '/create/sixth-review/?user=' . $user_id; }
}

final class Sixth_Review_Malformed_Workflow_Adapter extends Sixth_Review_Adapter implements Workflow_Adapter {
	public function key(): string { return 'malformed_workflow'; }
	public function workflow_api_version(): string { return 'not-semver'; }
	public function schema_version(): string { return '1.0.0'; }
	public function supports_native_drafts(): bool { return true; }
	public function schema(): array { return array( 'version' => '1.0.0', 'fields' => array() ); }
	public function create_draft( int $user_id, ?string $native_reference, array $payload ) { unset( $user_id, $native_reference, $payload ); return array(); }
	public function validate( int $user_id, array $payload ) { unset( $user_id, $payload ); return array(); }
	public function preview( int $user_id, array $payload ) { unset( $user_id, $payload ); return array(); }
	public function submit( int $user_id, string $idempotency_key, array $payload ) { unset( $user_id, $idempotency_key, $payload ); return array(); }
	public function status( int $user_id, string $native_reference ) { unset( $user_id, $native_reference ); return array(); }
	public function canonical_url( int $user_id, string $native_reference ): string { unset( $user_id, $native_reference ); return ''; }
}

final class SixthCompleteReviewTest extends TestCase {
	private Registry $registry;

	protected function setUp(): void {
		$GLOBALS['supc_test_statuses'] = array( 1 => 'approved' );
		$GLOBALS['supc_test_capabilities'] = array( 1 => array( 'publish_posts' => true ) );
		$GLOBALS['supc_test_current_user'] = 1;
		$GLOBALS['supc_test_manage_options'] = false;
		$GLOBALS['supc_test_options'] = array();
		$this->registry = new Registry( new Permission_Resolver() );
	}

	public function test_allow_decisions_are_re_evaluated_after_same_request_security_changes(): void {
		$this->assertTrue( $this->registry->register( new Sixth_Review_Adapter() ) );
		$this->assertArrayHasKey( 'sixth_review', $this->registry->available_for_user( 1 ) );
		$this->assertSame( 'available', $this->registry->creation_state_for_user( 1 ) );

		$GLOBALS['supc_test_statuses'][1] = 'suspended';
		$this->assertSame( array(), $this->registry->available_for_user( 1 ) );
		$this->assertSame( 'denied', $this->registry->creation_state_for_user( 1 ) );

		$GLOBALS['supc_test_statuses'][1] = 'approved';
		$GLOBALS['supc_test_options']['supc_emergency_disabled'] = true;
		$this->assertSame( array(), $this->registry->available_for_user( 1 ) );
		$this->assertSame( 'denied', $this->registry->creation_state_for_user( 1 ) );

		$GLOBALS['supc_test_options'] = array();
		$GLOBALS['supc_test_capabilities'][1]['publish_posts'] = false;
		$this->assertSame( array(), $this->registry->available_for_user( 1 ) );
		$this->assertSame( 'denied', $this->registry->creation_state_for_user( 1 ) );
	}

	public function test_malformed_workflow_api_metadata_is_rejected_atomically(): void {
		$result = $this->registry->register( new Sixth_Review_Malformed_Workflow_Adapter() );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'supc_api_mismatch', $result->code );
		$this->assertNull( $this->registry->get( 'malformed_workflow' ) );
		$this->assertNull( $this->registry->adapter_contract( 'malformed_workflow' ) );
		$this->assertNull( $this->registry->workflow_contract( 'malformed_workflow' ) );
	}
}
