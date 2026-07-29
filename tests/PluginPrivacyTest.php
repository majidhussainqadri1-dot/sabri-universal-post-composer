<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Sabri\UniversalComposer\Core\Page_Resolver;
use Sabri\UniversalComposer\Core\Plugin;

final class PluginPrivacyTest extends TestCase {
	protected function tearDown(): void {
		$GLOBALS['post'] = null;
		$GLOBALS['supc_test_pages'] = array();
		$GLOBALS['supc_test_is_page'] = 0;
		Page_Resolver::reset_cache();
	}

	public function test_noncanonical_shortcode_surface_is_noindex_even_without_page_mapping(): void {
		$GLOBALS['supc_test_options'] = array();
		$GLOBALS['supc_test_pages'] = array();
		$GLOBALS['supc_test_is_page'] = 0;
		Page_Resolver::reset_cache();

		$post = new WP_Post();
		$post->post_content = 'Before [sabri_universal_composer] after';
		$GLOBALS['post'] = $post;

		$robots = Plugin::instance()->filter_create_robots( array() );

		$this->assertTrue( $robots['noindex'] );
		$this->assertTrue( $robots['nofollow'] );
		$this->assertTrue( $robots['noarchive'] );
	}

	public function test_unrelated_page_is_not_forced_noindex(): void {
		$GLOBALS['supc_test_options'] = array();
		$GLOBALS['supc_test_pages'] = array();
		$GLOBALS['supc_test_is_page'] = 0;
		Page_Resolver::reset_cache();

		$post = new WP_Post();
		$post->post_content = 'Ordinary public content.';
		$GLOBALS['post'] = $post;

		$this->assertSame( array(), Plugin::instance()->filter_create_robots( array() ) );
	}
}
