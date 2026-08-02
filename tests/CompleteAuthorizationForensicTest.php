<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Sabri\UniversalComposer\Core\Permission_Resolver;

final class CompleteAuthorizationForensicTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['supc_test_current_user']            = 1;
		$GLOBALS['supc_test_manage_options']          = true;
		$GLOBALS['supc_test_statuses']                = array();
		$GLOBALS['supc_test_capabilities']            = array();
		$GLOBALS['supc_test_membership_applications'] = array();
		$GLOBALS['supc_test_membership_states']       = array();
		$GLOBALS['supc_test_founders']                = array();
	}

	public function test_institutional_account_with_legacy_draft_application_is_eligible(): void {
		$GLOBALS['supc_test_current_user'] = 10;
		$GLOBALS['supc_test_manage_options'] = true;
		$GLOBALS['supc_test_membership_applications'][10] = array( 'status' => 'draft' );
		$GLOBALS['supc_test_membership_states'][10] = array(
			'application_exists'    => true,
			'application_status'    => 'draft',
			'status'                => 'verified',
			'institutional_account' => true,
			'approved'              => true,
		);

		$report = ( new Permission_Resolver() )->eligibility_report( 10 );

		$this->assertTrue( $report['eligible'] );
		$this->assertSame( 'current_user_authorized', $report['reason'] );
		$this->assertSame( 'draft', $report['application_status'] );
	}

	public function test_institutional_hard_block_remains_denied(): void {
		$GLOBALS['supc_test_current_user'] = 11;
		$GLOBALS['supc_test_manage_options'] = true;
		$GLOBALS['supc_test_membership_states'][11] = array(
			'application_exists'    => true,
			'application_status'    => 'suspended',
			'status'                => 'suspended',
			'institutional_account' => true,
			'approved'              => false,
		);

		$report = ( new Permission_Resolver() )->eligibility_report( 11 );

		$this->assertFalse( $report['eligible'] );
		$this->assertSame( 'membership_hard_block', $report['reason'] );
	}

	public function test_ordinary_pending_application_remains_denied(): void {
		$GLOBALS['supc_test_current_user'] = 12;
		$GLOBALS['supc_test_manage_options'] = false;
		$GLOBALS['supc_test_membership_states'][12] = array(
			'application_exists'    => true,
			'application_status'    => 'under_review',
			'status'                => 'under_review',
			'institutional_account' => false,
			'approved'              => false,
		);

		$report = ( new Permission_Resolver() )->eligibility_report( 12 );

		$this->assertFalse( $report['eligible'] );
		$this->assertSame( 'membership_application_blocking', $report['reason'] );
	}

	public function test_version_tracks_are_validated_independently(): void {
		$main = file_get_contents( dirname( __DIR__ ) . '/sabri-universal-post-composer.php' );
		$resolver = file_get_contents( dirname( __DIR__ ) . '/includes/core/class-permission-resolver.php' );

		$this->assertIsString( $main );
		$this->assertIsString( $resolver );
		$this->assertStringContainsString( "SUPC_MIN_SMC_VERSION', '1.2.3", $main );
		$this->assertStringContainsString( "SUPC_MIN_SMC_DB_VERSION', '1.2.0", $main );
		$this->assertStringContainsString( "SUPC_MIN_SMC_CONTRACT_VERSION', '1.1.2", $main );
		$this->assertStringContainsString( 'SMC_DB_VERSION, (string) SUPC_MIN_SMC_DB_VERSION', $resolver );
		$this->assertStringContainsString( 'SMC_CONTRACT_VERSION, (string) SUPC_MIN_SMC_CONTRACT_VERSION', $resolver );
	}

	public function test_forensic_row_collects_all_independent_blockers_without_identity_data(): void {
		$plugin   = file_get_contents( dirname( __DIR__ ) . '/includes/core/class-plugin.php' );
		$resolver = file_get_contents( dirname( __DIR__ ) . '/includes/core/class-permission-resolver.php' );

		$this->assertIsString( $plugin );
		$this->assertIsString( $resolver );
		$this->assertStringContainsString( 'membership_application_blocking', $resolver );
		foreach ( array(
			'native_capability_missing',
			'file21_runtime_missing',
			'file21_duplicate_installed_copies',
			'file21_general_disabled',
			'file21_composer_disabled',
			'file21_emergency_disabled',
			'file21_safe_mode_active',
			'native_adapter_unavailable',
			'native_adapter_create_denied',
		) as $code ) {
			$this->assertStringContainsString( $code, $plugin );
		}

		$method_start = strpos( $plugin, 'private function current_user_authorization_row()' );
		$method_end   = strpos( $plugin, '/** @return array{installed:int,active:int} */', $method_start );
		$this->assertNotFalse( $method_start );
		$this->assertNotFalse( $method_end );
		$method = substr( $plugin, $method_start, $method_end - $method_start );
		$this->assertStringContainsString( 'Audit every independent authorization gate in one pass', $plugin );
		$this->assertSame( 1, substr_count( $method, "return array(\n\t\t\t'key'    => 'current_user_authorization'" ) );
		$this->assertStringNotContainsString( 'user_email', $method );
		$this->assertStringNotContainsString( 'display_name', $method );
		$this->assertStringNotContainsString( 'phone', $method );
	}
}
