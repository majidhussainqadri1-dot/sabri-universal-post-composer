<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Sabri\UniversalComposer\Contracts\Adapter;
use Sabri\UniversalComposer\Core\Permission_Resolver;
use Sabri\UniversalComposer\Core\Registry;
use Sabri\UniversalComposer\Presentation\Create_Surface;

final class Surface_Test_Adapter implements Adapter {
	public function __construct(
		private string $adapter_key,
		private string $adapter_label,
		private string $adapter_group = 'publishing',
		private string $adapter_privacy = 'public',
		private string $adapter_url = '/create-native/',
		private int $adapter_priority = 10,
		private string $adapter_icon = 'edit'
	) {
	}

	public function api_version(): string { return '1.0.0'; }
	public function key(): string { return $this->adapter_key; }
	public function label(): string { return $this->adapter_label; }
	public function description(): string { return 'Create a native item.'; }
	public function group(): string { return $this->adapter_group; }
	public function icon(): string { return $this->adapter_icon; }
	public function priority(): int { return $this->adapter_priority; }
	public function native_module(): string { return 'surface-test-module'; }
	public function minimum_native_version(): string { return '1.0.0'; }
	public function required_capability(): string { return 'publish_posts'; }
	public function privacy_classification(): string { return $this->adapter_privacy; }
	public function is_available(): bool { return true; }
	public function can_create( int $user_id ): bool { return $user_id > 0; }
	public function start_url( int $user_id ): string {
		unset( $user_id );
		return $this->adapter_url;
	}
}

final class CreateSurfaceTest extends TestCase {
	private Registry $registry;

	protected function setUp(): void {
		$GLOBALS['supc_test_statuses']      = array( 1 => 'approved', 2 => 'suspended' );
		$GLOBALS['supc_test_capabilities']  = array( 1 => array( 'publish_posts' => true ) );
		$GLOBALS['supc_test_options']       = array();
		$GLOBALS['supc_test_logged_in']     = true;
		$GLOBALS['supc_test_current_user']  = 1;
		$GLOBALS['supc_test_unique_id']     = 0;
		$GLOBALS['supc_test_actions_fired'] = array();
		$this->registry                     = new Registry( new Permission_Resolver() );
	}

	public function test_groups_authorized_adapters_in_canonical_section_order(): void {
		$this->assertTrue( $this->registry->register( new Surface_Test_Adapter( 'video_item', 'Video', 'media', 'public', '/video/create/', 1 ) ) );
		$this->assertTrue( $this->registry->register( new Surface_Test_Adapter( 'social_post', 'Social Post', 'publishing', 'public', '/create-post/', 50 ) ) );
		$this->assertTrue( $this->registry->register( new Surface_Test_Adapter( 'custom_item', 'Custom', 'unregistered_group', 'private', '/custom/create/', 2 ) ) );

		$groups = ( new Create_Surface( $this->registry ) )->collect_groups( 1 );

		$this->assertSame( array( 'publishing', 'media', 'other' ), array_keys( $groups ) );
		$this->assertSame( 'social_post', $groups['publishing']['cards'][0]['key'] );
		$this->assertSame( 'Restricted content', $groups['other']['cards'][0]['privacy_label'] );
		$this->assertSame( 'supc_adapter_group_fallback', $GLOBALS['supc_test_actions_fired'][0][0] );
	}

	public function test_invalid_external_start_url_is_not_rendered(): void {
		$this->assertTrue( $this->registry->register( new Surface_Test_Adapter( 'unsafe_item', 'Unsafe', 'publishing', 'public', 'https://evil.example/create/' ) ) );

		$groups = ( new Create_Surface( $this->registry ) )->collect_groups( 1 );

		$this->assertSame( array(), $groups );
		$this->assertSame( 'supc_adapter_invalid_start_url', $GLOBALS['supc_test_actions_fired'][0][0] );
	}

	public function test_unknown_privacy_falls_back_to_restricted_and_emits_diagnostic(): void {
		$this->assertTrue( $this->registry->register( new Surface_Test_Adapter( 'legacy_item', 'Legacy', 'publishing', 'unknown' ) ) );

		$groups = ( new Create_Surface( $this->registry ) )->collect_groups( 1 );

		$this->assertSame( 'private', $groups['publishing']['cards'][0]['privacy'] );
		$this->assertSame( 'Restricted content', $groups['publishing']['cards'][0]['privacy_label'] );
		$this->assertSame( 'supc_adapter_privacy_fallback', $GLOBALS['supc_test_actions_fired'][0][0] );
	}

	public function test_render_outputs_accessible_native_links_and_escapes_adapter_text(): void {
		$this->assertTrue( $this->registry->register( new Surface_Test_Adapter( 'social_post', '<script>Post</script>', 'publishing', 'sensitive', '/create-post/', 10, 'dashicons-admin-post' ) ) );

		$html = ( new Create_Surface( $this->registry ) )->render();

		$this->assertStringContainsString( 'aria-labelledby=', $html );
		$this->assertStringContainsString( 'data-supc-group="publishing"', $html );
		$this->assertStringContainsString( 'href="/create-post/"', $html );
		$this->assertStringContainsString( '&lt;script&gt;Post&lt;/script&gt;', $html );
		$this->assertStringNotContainsString( '<script>Post</script>', $html );
		$this->assertStringContainsString( 'Sensitive workflow', $html );
		$this->assertStringContainsString( 'dashicons-admin-post', $html );
		$this->assertStringNotContainsString( 'dashicons-dashicons-admin-post', $html );
		$this->assertStringContainsString( 'supc-create-card__arrow', $html );
		$this->assertStringContainsString( 'One gateway, one native record.', $html );
	}

	public function test_suspended_user_receives_no_creation_choices(): void {
		$this->assertTrue( $this->registry->register( new Surface_Test_Adapter( 'social_post', 'Social Post' ) ) );
		$GLOBALS['supc_test_current_user'] = 2;

		$html = ( new Create_Surface( $this->registry ) )->render();

		$this->assertStringContainsString( 'No authorized content type is currently available.', $html );
		$this->assertStringNotContainsString( 'data-supc-type=', $html );
	}
}
