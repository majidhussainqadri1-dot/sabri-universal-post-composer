<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Sabri\UniversalComposer\Core\Page_Resolver;

final class FifthCompleteReviewTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['supc_test_options'] = array();
		$GLOBALS['supc_test_pages'] = array();
		$GLOBALS['supc_test_next_post_id'] = 100;
		$GLOBALS['supc_test_insert_mutations'] = array();
		$GLOBALS['supc_test_add_option_fail'] = false;
		$GLOBALS['supc_test_update_fail_keys'] = array();
		$GLOBALS['supc_test_uuid_counter'] = 0;
		Page_Resolver::reset_cache();
	}

	protected function tearDown(): void {
		Page_Resolver::reset_cache();
	}

	public function test_external_filtered_permalink_is_not_a_create_page_candidate(): void {
		$GLOBALS['supc_test_pages'][51] = array(
			'status'     => 'publish',
			'type'       => 'page',
			'content'    => '[sabri_universal_composer]',
			'slug'       => 'create',
			'permalink'  => 'https://attacker.example/create/',
			'meta_input' => array(),
		);
		$GLOBALS['supc_test_options']['supc_create_page_id'] = 51;

		$inspection = Page_Resolver::inspect();

		$this->assertSame( 'missing', $inspection['status'] );
		$this->assertSame( array(), $inspection['candidate_page_ids'] );
		$this->assertSame( '', Page_Resolver::url() );
	}

	public function test_same_origin_https_permalink_remains_valid(): void {
		$GLOBALS['supc_test_pages'][52] = array(
			'status'     => 'publish',
			'type'       => 'page',
			'content'    => '[sabri_universal_composer]',
			'slug'       => 'create',
			'permalink'  => 'https://example.test/create/',
			'meta_input' => array(),
		);
		$GLOBALS['supc_test_options']['supc_create_page_id'] = 52;

		$this->assertSame( 'ready', Page_Resolver::inspect()['status'] );
		$this->assertSame( 'https://example.test/create/', Page_Resolver::url() );
	}

	public function test_malformed_repair_lock_is_removed_instead_of_deadlocking_repairs(): void {
		$GLOBALS['supc_test_options']['supc_create_page_repair_lock'] = array(
			'token'   => 'not-a-uuid',
			'created' => time() + 3600,
		);

		$result = Page_Resolver::repair_mapping( true );

		$this->assertSame( 'created_managed_page', $result['result'] );
		$this->assertGreaterThan( 0, $result['page_id'] );
		$this->assertArrayNotHasKey( 'supc_create_page_repair_lock', $GLOBALS['supc_test_options'] );
	}

	public function test_valid_current_repair_lock_still_serializes_repairs(): void {
		$GLOBALS['supc_test_options']['supc_create_page_repair_lock'] = array(
			'token'   => '00000000-0000-4000-8000-000000000001',
			'created' => time(),
		);

		$result = Page_Resolver::repair_mapping( true );

		$this->assertSame( 'repair_locked', $result['result'] );
		$this->assertSame( 0, $result['page_id'] );
	}
}
