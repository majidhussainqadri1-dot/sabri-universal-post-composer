<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Sabri\UniversalComposer\Admin\System_Check_Page;
use Sabri\UniversalComposer\Core\Permission_Resolver;
use Sabri\UniversalComposer\Core\Registry;

final class TwelfthCompleteReviewTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['supc_test_current_user']   = 1;
		$GLOBALS['supc_test_manage_options'] = true;
		$GLOBALS['supc_test_nonce_checked']  = false;
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
}
