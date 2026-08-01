<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Sabri\UniversalComposer\Contracts\Adapter;
use Sabri\UniversalComposer\Core\Permission_Resolver;
use Sabri\UniversalComposer\Core\Registry;
use Sabri\UniversalComposer\Presentation\Create_Surface;

final class Founder_Admin_Surface_Adapter implements Adapter {
	public function api_version(): string {
		return '1.0.0';
	}

	public function key(): string {
		return 'social_post';
	}

	public function label(): string {
		return 'Social Post';
	}

	public function description(): string {
		return 'Create a native social publication.';
	}

	public function group(): string {
		return 'publishing';
	}

	public function icon(): string {
		return 'edit';
	}

	public function priority(): int {
		return 10;
	}

	public function native_module(): string {
		return 'founder-admin-surface-test';
	}

	public function minimum_native_version(): string {
		return '1.0.0';
	}

	public function required_capability(): string {
		return 'publish_posts';
	}

	public function privacy_classification(): string {
		return 'public';
	}

	public function is_available(): bool {
		return true;
	}

	public function can_create( int $user_id ): bool {
		return $user_id > 0;
	}

	public function start_url( int $user_id ): string {
		unset( $user_id );
		return '/create-post/';
	}
}

final class FounderAdminCreateSurfaceRegressionTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['supc_test_statuses']                = array();
		$GLOBALS['supc_test_capabilities']            = array();
		$GLOBALS['supc_test_membership_applications'] = array();
		$GLOBALS['supc_test_membership_states']       = array();
		$GLOBALS['supc_test_founders']                = array();
		$GLOBALS['supc_test_options']                 = array();
		$GLOBALS['supc_test_logged_in']               = true;
		$GLOBALS['supc_test_manage_options']          = false;
		$GLOBALS['supc_test_unique_id']               = 0;
	}

	public function test_administrator_without_membership_application_receives_create_card(): void {
		$GLOBALS['supc_test_current_user'] = 41;
		$GLOBALS['supc_test_manage_options'] = true;
		$GLOBALS['supc_test_capabilities'][41] = array( 'publish_posts' => true );
		$GLOBALS['supc_test_membership_states'][41] = $this->institutional_state( 'administrator' );

		$html = $this->render_surface();

		$this->assertStringContainsString( 'data-supc-type="social_post"', $html );
		$this->assertStringContainsString( 'href="/create-post/"', $html );
		$this->assertStringNotContainsString( 'No creation permission is available for this account.', $html );
	}

	public function test_canonical_founder_without_membership_application_receives_create_card(): void {
		$GLOBALS['supc_test_current_user'] = 42;
		$GLOBALS['supc_test_founders'][42] = true;
		$GLOBALS['supc_test_capabilities'][42] = array( 'publish_posts' => true );
		$GLOBALS['supc_test_membership_states'][42] = $this->institutional_state( 'founder' );

		$html = $this->render_surface();

		$this->assertStringContainsString( 'data-supc-type="social_post"', $html );
		$this->assertStringNotContainsString( 'No creation permission is available for this account.', $html );
	}

	public function test_administrator_without_native_capability_still_receives_permission_denial(): void {
		$GLOBALS['supc_test_current_user'] = 43;
		$GLOBALS['supc_test_manage_options'] = true;
		$GLOBALS['supc_test_membership_states'][43] = $this->institutional_state( 'administrator' );

		$html = $this->render_surface();

		$this->assertStringContainsString( 'No creation permission is available for this account.', $html );
		$this->assertStringNotContainsString( 'data-supc-type="social_post"', $html );
	}

	private function render_surface(): string {
		$registry = new Registry( new Permission_Resolver() );
		$this->assertTrue( $registry->register( new Founder_Admin_Surface_Adapter() ) );
		return ( new Create_Surface( $registry ) )->render();
	}

	/** @return array<string,mixed> */
	private function institutional_state( string $account_class ): array {
		return array(
			'application_exists'    => false,
			'application_status'    => '',
			'status'                => 'verified',
			'institutional_account' => true,
			'account_class'         => $account_class,
			'approved'              => true,
		);
	}
}
