<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Sabri\UniversalComposer\Core\Page_Resolver;

final class PageResolverRepairTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['supc_test_options']          = array();
		$GLOBALS['supc_test_pages']            = array();
		$GLOBALS['supc_test_next_post_id']     = 100;
		$GLOBALS['supc_test_update_fail_keys'] = array();
		$GLOBALS['supc_test_add_option_fail']  = false;
		$GLOBALS['supc_test_insert_mutations'] = array();
		$GLOBALS['supc_test_get_posts_calls']  = 0;
		Page_Resolver::reset_cache();
	}

	public function test_inspection_is_read_only_when_mapping_is_missing(): void {
		$result = Page_Resolver::inspect();

		$this->assertSame( 'missing', $result['status'] );
		$this->assertSame( array(), $GLOBALS['supc_test_options'] );
		$this->assertSame( array(), $GLOBALS['supc_test_pages'] );
	}

	public function test_inspection_is_memoized_per_request(): void {
		Page_Resolver::inspect();
		Page_Resolver::inspect();

		$this->assertSame( 1, $GLOBALS['supc_test_get_posts_calls'] );
	}

	public function test_configured_non_page_object_is_not_ready(): void {
		$GLOBALS['supc_test_options']['supc_create_page_id'] = 20;
		$GLOBALS['supc_test_pages'][20] = $this->page( 'create', '[sabri_universal_composer]', 'post' );

		$result = Page_Resolver::inspect();

		$this->assertSame( 'missing', $result['status'] );
		$this->assertSame( 20, $result['configured_page_id'] );
	}

	public function test_read_only_resolution_discovers_one_page_without_persisting_mapping(): void {
		$GLOBALS['supc_test_pages'][42] = $this->page( 'existing-create' );

		$this->assertSame( 42, Page_Resolver::resolve_page_id( false ) );
		$this->assertArrayNotHasKey( 'supc_create_page_id', $GLOBALS['supc_test_options'] );
	}

	public function test_multiple_shortcode_pages_require_explicit_selection(): void {
		$GLOBALS['supc_test_pages'][42] = $this->page( 'existing-create' );
		$GLOBALS['supc_test_pages'][43] = $this->page( 'second-create' );

		$inspection = Page_Resolver::inspect();
		$this->assertSame( 'ambiguous', $inspection['status'] );
		$this->assertSame( array( 42, 43 ), $inspection['candidate_page_ids'] );

		$result = Page_Resolver::repair_mapping( true );
		$this->assertSame( 'ambiguous_selection_required', $result['result'] );
		$this->assertArrayNotHasKey( 'supc_create_page_id', $GLOBALS['supc_test_options'] );

		Page_Resolver::reset_cache();
		$result = Page_Resolver::repair_mapping( true, 43 );
		$this->assertSame( 'mapped_existing', $result['result'] );
		$this->assertSame( 43, $GLOBALS['supc_test_options']['supc_create_page_id'] );
	}

	public function test_repair_maps_existing_shortcode_page_without_editing_it(): void {
		$GLOBALS['supc_test_options']['supc_create_page_id'] = 999;
		$GLOBALS['supc_test_pages'][42] = $this->page( 'existing-create', 'Before [sabri_universal_composer] after' );
		$before = $GLOBALS['supc_test_pages'][42];

		$result = Page_Resolver::repair_mapping( true );

		$this->assertSame( 'mapped_existing', $result['result'] );
		$this->assertSame( 42, $result['page_id'] );
		$this->assertSame( 42, $GLOBALS['supc_test_options']['supc_create_page_id'] );
		$this->assertSame( $before, $GLOBALS['supc_test_pages'][42] );
	}

	public function test_mapping_persistence_failure_is_not_reported_as_success(): void {
		$GLOBALS['supc_test_pages'][42]            = $this->page( 'existing-create' );
		$GLOBALS['supc_test_update_fail_keys'][]   = 'supc_create_page_id';

		$result = Page_Resolver::repair_mapping( true );

		$this->assertSame( 'mapping_persistence_failed', $result['result'] );
		$this->assertSame( 42, $result['page_id'] );
		$this->assertArrayNotHasKey( 'supc_create_page_id', $GLOBALS['supc_test_options'] );
	}

	public function test_dry_repair_plan_does_not_create_or_change_data(): void {
		$result = Page_Resolver::repair_mapping( false );

		$this->assertSame( 'would_create_managed_page', $result['result'] );
		$this->assertSame( 0, $result['page_id'] );
		$this->assertSame( array(), $GLOBALS['supc_test_options'] );
		$this->assertSame( array(), $GLOBALS['supc_test_pages'] );
	}

	public function test_repair_lock_prevents_a_second_concurrent_mutation(): void {
		$GLOBALS['supc_test_options']['supc_create_page_repair_lock'] = array(
			'token'   => 'existing-lock',
			'created' => time(),
		);

		$result = Page_Resolver::repair_mapping( true );

		$this->assertSame( 'repair_locked', $result['result'] );
		$this->assertSame( array(), $GLOBALS['supc_test_pages'] );
	}

	public function test_repair_creates_one_managed_page_and_skips_occupied_slug(): void {
		$GLOBALS['supc_test_pages'][10] = $this->page( 'create', 'Unrelated page' );
		$unrelated = $GLOBALS['supc_test_pages'][10];

		$result = Page_Resolver::repair_mapping( true );

		$this->assertSame( 'created_managed_page', $result['result'] );
		$this->assertSame( 101, $result['page_id'] );
		$this->assertSame( 101, $GLOBALS['supc_test_options']['supc_create_page_id'] );
		$this->assertSame( 'page', $GLOBALS['supc_test_pages'][101]['type'] );
		$this->assertSame( 'create-content', $GLOBALS['supc_test_pages'][101]['slug'] );
		$this->assertSame( '[sabri_universal_composer]', $GLOBALS['supc_test_pages'][101]['content'] );
		$this->assertSame( array( '_supc_managed_page' => 1 ), $GLOBALS['supc_test_pages'][101]['meta_input'] );
		$this->assertSame( $unrelated, $GLOBALS['supc_test_pages'][10] );
	}

	public function test_uniquified_slug_fails_validation_without_repeated_orphan_creation(): void {
		$GLOBALS['supc_test_insert_mutations']['slug'] = 'create-2';

		$result = Page_Resolver::repair_mapping( true );

		$this->assertSame( 'managed_page_validation_failed', $result['result'] );
		$this->assertCount( 1, $GLOBALS['supc_test_pages'] );
		$this->assertArrayNotHasKey( 'supc_create_page_id', $GLOBALS['supc_test_options'] );
	}

	public function test_stripped_ownership_meta_fails_without_second_insert(): void {
		$GLOBALS['supc_test_insert_mutations']['strip_meta'] = true;

		$result = Page_Resolver::repair_mapping( true );

		$this->assertSame( 'managed_page_validation_failed', $result['result'] );
		$this->assertCount( 1, $GLOBALS['supc_test_pages'] );
		$this->assertSame( array(), $GLOBALS['supc_test_pages'][101]['meta_input'] );
	}

	public function test_inserted_non_page_fails_validation(): void {
		$GLOBALS['supc_test_insert_mutations']['type'] = 'post';

		$result = Page_Resolver::repair_mapping( true );

		$this->assertSame( 'managed_page_validation_failed', $result['result'] );
		$this->assertCount( 1, $GLOBALS['supc_test_pages'] );
	}

	public function test_valid_mapping_is_not_changed(): void {
		$GLOBALS['supc_test_options']['supc_create_page_id'] = 15;
		$GLOBALS['supc_test_pages'][15] = $this->page( 'create' );

		$result = Page_Resolver::repair_mapping( true );

		$this->assertSame( 'no_change', $result['result'] );
		$this->assertSame( 15, $result['page_id'] );
		$this->assertCount( 1, $GLOBALS['supc_test_pages'] );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function page( string $slug, string $content = '[sabri_universal_composer]', string $type = 'page' ): array {
		return array(
			'status'     => 'publish',
			'type'       => $type,
			'content'    => $content,
			'slug'       => $slug,
			'permalink'  => 'https://example.test/' . $slug . '/',
			'meta_input' => array(),
		);
	}
}
