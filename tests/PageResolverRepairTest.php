<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Sabri\UniversalComposer\Core\Page_Resolver;

final class PageResolverRepairTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['supc_test_options']      = array();
		$GLOBALS['supc_test_pages']        = array();
		$GLOBALS['supc_test_next_post_id'] = 100;
		Page_Resolver::reset_cache();
	}

	public function test_inspection_is_read_only_when_mapping_is_missing(): void {
		$result = Page_Resolver::inspect();

		$this->assertSame( 'missing', $result['status'] );
		$this->assertSame( array(), $GLOBALS['supc_test_options'] );
		$this->assertSame( array(), $GLOBALS['supc_test_pages'] );
	}

	public function test_repair_maps_existing_shortcode_page_without_editing_it(): void {
		$GLOBALS['supc_test_options']['supc_create_page_id'] = 999;
		$GLOBALS['supc_test_pages'][42] = array(
			'status'    => 'publish',
			'content'   => 'Before [sabri_universal_composer] after',
			'slug'      => 'existing-create',
			'permalink' => 'https://example.test/existing-create/',
		);
		$before = $GLOBALS['supc_test_pages'][42];

		$result = Page_Resolver::repair_mapping( true );

		$this->assertSame( 'mapped_existing', $result['result'] );
		$this->assertSame( 42, $result['page_id'] );
		$this->assertSame( 42, $GLOBALS['supc_test_options']['supc_create_page_id'] );
		$this->assertSame( $before, $GLOBALS['supc_test_pages'][42] );
	}

	public function test_dry_repair_plan_does_not_create_or_change_data(): void {
		$result = Page_Resolver::repair_mapping( false );

		$this->assertSame( 'would_create_managed_page', $result['result'] );
		$this->assertSame( 0, $result['page_id'] );
		$this->assertSame( array(), $GLOBALS['supc_test_options'] );
		$this->assertSame( array(), $GLOBALS['supc_test_pages'] );
	}

	public function test_repair_creates_only_a_managed_page_and_skips_occupied_slug(): void {
		$GLOBALS['supc_test_pages'][10] = array(
			'status'    => 'publish',
			'content'   => 'Unrelated page',
			'slug'      => 'create',
			'permalink' => 'https://example.test/create/',
		);
		$unrelated = $GLOBALS['supc_test_pages'][10];

		$result = Page_Resolver::repair_mapping( true );

		$this->assertSame( 'created_managed_page', $result['result'] );
		$this->assertSame( 101, $result['page_id'] );
		$this->assertSame( 101, $GLOBALS['supc_test_options']['supc_create_page_id'] );
		$this->assertSame( 'create-content', $GLOBALS['supc_test_pages'][101]['slug'] );
		$this->assertSame( '[sabri_universal_composer]', $GLOBALS['supc_test_pages'][101]['content'] );
		$this->assertSame( array( '_supc_managed_page' => 1 ), $GLOBALS['supc_test_pages'][101]['meta_input'] );
		$this->assertSame( $unrelated, $GLOBALS['supc_test_pages'][10] );
	}

	public function test_valid_mapping_is_not_changed(): void {
		$GLOBALS['supc_test_options']['supc_create_page_id'] = 15;
		$GLOBALS['supc_test_pages'][15] = array(
			'status'    => 'publish',
			'content'   => '[sabri_universal_composer]',
			'slug'      => 'create',
			'permalink' => 'https://example.test/create/',
		);

		$result = Page_Resolver::repair_mapping( true );

		$this->assertSame( 'no_change', $result['result'] );
		$this->assertSame( 15, $result['page_id'] );
		$this->assertCount( 1, $GLOBALS['supc_test_pages'] );
	}
}
