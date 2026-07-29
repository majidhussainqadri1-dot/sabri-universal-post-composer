<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Sabri\UniversalComposer\Core\Page_Resolver;
use Sabri\UniversalComposer\Core\Plugin;

if ( ! function_exists( 'nocache_headers' ) ) {
	function nocache_headers(): void {
		++$GLOBALS['supc_test_nocache_headers'];
	}
}

final class PluginPrivacyTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['supc_test_nocache_headers'] = 0;
		$GLOBALS['supc_test_actions_fired'] = array();
	}

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

	public function test_direct_shortcode_render_enforces_private_headers_without_detectable_page(): void {
		$GLOBALS['supc_test_options'] = array();
		$GLOBALS['supc_test_pages'] = array();
		$GLOBALS['supc_test_is_page'] = 0;
		$GLOBALS['post'] = null;
		Page_Resolver::reset_cache();

		$output = Plugin::instance()->render_shortcode();

		$this->assertIsString( $output );
		$this->assertSame( 1, $GLOBALS['supc_test_nocache_headers'] );
		$this->assertContains(
			array( 'supc_private_surface_headers_applied', array() ),
			$GLOBALS['supc_test_actions_fired']
		);
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
