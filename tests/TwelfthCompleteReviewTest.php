<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Sabri\UniversalComposer\Admin\System_Check_Page;
use Sabri\UniversalComposer\Core\Permission_Resolver;
use Sabri\UniversalComposer\Core\Registry;

final class TwelfthCompleteReviewTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['supc_test_current_user']             = 1;
		$GLOBALS['supc_test_manage_options']           = true;
		$GLOBALS['supc_test_nonce_checked']            = false;
		$GLOBALS['supc_test_statuses']                 = array( 1 => 'approved', 2 => 'suspended' );
		$GLOBALS['supc_test_capabilities']             = array( 1 => array( 'sabri_feed_create_posts' => true ) );
		$GLOBALS['supc_test_membership_applications']  = array();
		$GLOBALS['supc_test_founders']                 = array();
	}

	public function test_missing_request_method_fails_closed_before_nonce_processing(): void {
		$page = new System_Check_Page( new Registry( new Permission_Resolver() ) );

		try {
			$page->handle_repair();
			$this->fail( 'A repair request without an explicit POST method was accepted.' );
		} catch ( RuntimeException $error ) {
			$this->assertStringContainsString( 'must use POST', $error->getMessage() );
		}

		$this->assertFalse( $GLOBALS['supc_test_nonce_checked'] );
	}

	public function test_shared_nocache_stub_initializes_its_counter_without_warning(): void {
		unset( $GLOBALS['supc_test_nocache_headers'] );

		nocache_headers();

		$this->assertSame( 1, $GLOBALS['supc_test_nocache_headers'] );
	}

	public function test_canonical_founder_without_membership_application_can_use_native_capability(): void {
		$GLOBALS['supc_test_current_user'] = 3;
		$GLOBALS['supc_test_manage_options'] = false;
		$GLOBALS['supc_test_founders'][3] = true;
		$GLOBALS['supc_test_capabilities'][3] = array( 'sabri_feed_create_posts' => true );

		$resolver = new Permission_Resolver();

		$this->assertTrue( $resolver->can_use_capability( 3, 'sabri_feed_create_posts' ) );
	}

	public function test_administrator_without_membership_application_can_use_native_capability(): void {
		$GLOBALS['supc_test_current_user'] = 4;
		$GLOBALS['supc_test_manage_options'] = true;
		$GLOBALS['supc_test_capabilities'][4] = array( 'sabri_feed_create_posts' => true );

		$resolver = new Permission_Resolver();

		$this->assertTrue( $resolver->can_use_capability( 4, 'sabri_feed_create_posts' ) );
	}

	public function test_administrator_eligibility_never_bypasses_native_capability(): void {
		$GLOBALS['supc_test_current_user'] = 4;
		$GLOBALS['supc_test_manage_options'] = true;
		$GLOBALS['supc_test_capabilities'][4] = array();

		$resolver = new Permission_Resolver();

		$this->assertFalse( $resolver->can_use_capability( 4, 'sabri_feed_create_posts' ) );
	}

	public function test_explicit_draft_application_remains_denied_for_administrator(): void {
		$GLOBALS['supc_test_current_user'] = 4;
		$GLOBALS['supc_test_manage_options'] = true;
		$GLOBALS['supc_test_capabilities'][4] = array( 'sabri_feed_create_posts' => true );
		$GLOBALS['supc_test_membership_applications'][4] = array( 'status' => 'draft' );

		$resolver = new Permission_Resolver();

		$this->assertFalse( $resolver->can_use_capability( 4, 'sabri_feed_create_posts' ) );
	}

	public function test_suspended_founder_remains_denied(): void {
		$GLOBALS['supc_test_current_user'] = 5;
		$GLOBALS['supc_test_manage_options'] = false;
		$GLOBALS['supc_test_statuses'][5] = 'suspended';
		$GLOBALS['supc_test_founders'][5] = true;
		$GLOBALS['supc_test_capabilities'][5] = array( 'sabri_feed_create_posts' => true );

		$resolver = new Permission_Resolver();

		$this->assertFalse( $resolver->can_use_capability( 5, 'sabri_feed_create_posts' ) );
	}
}
