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
		private string $adapter_icon = 'edit',
		private bool $adapter_available = true,
		private bool $throw_on_start = false
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
	public function is_available(): bool { return $this->adapter_available; }
	public function can_create( int $user_id ): bool { return $user_id > 0; }
	public function start_url( int $user_id ): string {
		unset( $user_id );
		if ( $this->throw_on_start ) {
			throw new RuntimeException( 'Private adapter exception.' );
		}
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

		$surface = new Create_Surface( $this->registry );
		$groups  = $surface->collect_groups( 1 );

		$this->assertSame( array( 'publishing', 'media', 'other' ), array_keys( $groups ) );
		$this->assertSame( 'social_post', $groups['publishing']['cards'][0]['key'] );
		$this->assertSame( 'Restricted content', $groups['other']['cards'][0]['privacy_label'] );
		$this->assertSame( 'unknown_group', $surface->diagnostics()['custom_item:unknown_group']['code'] );
	}

	public function test_allowlisted_external_and_http_downgrade_routes_are_rejected(): void {
		$this->assertTrue( $this->registry->register( new Surface_Test_Adapter( 'external_item', 'External', 'publishing', 'public', 'https://allowed-external.example/create/' ) ) );
		$this->assertTrue( $this->registry->register( new Surface_Test_Adapter( 'downgrade_item', 'Downgrade', 'publishing', 'public', 'http://example.test/create/' ) ) );

		$surface = new Create_Surface( $this->registry );
		$groups  = $surface->collect_groups( 1 );

		$this->assertSame( array(), $groups );
		$this->assertSame( 2, $surface->system_check_row( 1 )['error_count'] );
		$this->assertContains( 'invalid_route', $surface->system_check_row( 1 )['codes'] );
	}

	public function test_same_origin_https_and_relative_routes_are_accepted(): void {
		$this->assertTrue( $this->registry->register( new Surface_Test_Adapter( 'relative_item', 'Relative', 'publishing', 'public', '/create-post/' ) ) );
		$this->assertTrue( $this->registry->register( new Surface_Test_Adapter( 'absolute_item', 'Absolute', 'publishing', 'public', 'https://example.test/create-video/' ) ) );

		$groups = ( new Create_Surface( $this->registry ) )->collect_groups( 1 );

		$this->assertCount( 2, $groups['publishing']['cards'] );
	}

	public function test_unknown_privacy_is_rejected_at_registration_without_disabling_healthy_adapter(): void {
		$this->assertInstanceOf( WP_Error::class, $this->registry->register( new Surface_Test_Adapter( 'legacy_item', 'Legacy', 'publishing', 'unknown' ) ) );
		$this->assertTrue( $this->registry->register( new Surface_Test_Adapter( 'healthy_item', 'Healthy', 'publishing', 'private', '/healthy/create/' ) ) );

		$surface = new Create_Surface( $this->registry );
		$groups  = $surface->collect_groups( 1 );

		$this->assertSame( 'healthy_item', $groups['publishing']['cards'][0]['key'] );
		$this->assertCount( 1, $groups['publishing']['cards'] );
		$this->assertSame( 'invalid_privacy', $this->registry->errors()['legacy_item']['code'] );
	}

	public function test_render_exception_uses_only_a_controlled_diagnostic_code(): void {
		$this->assertTrue( $this->registry->register( new Surface_Test_Adapter( 'throwing_item', 'Throwing', 'publishing', 'public', '/throwing/', 10, 'edit', true, true ) ) );

		$surface = new Create_Surface( $this->registry );

		$this->assertSame( array(), $surface->collect_groups( 1 ) );
		$this->assertContains(
			array( 'supc_adapter_render_error', array( 'throwing_item', 'render_exception' ) ),
			$GLOBALS['supc_test_actions_fired']
		);
		$this->assertStringNotContainsString(
			'RuntimeException',
			serialize( $GLOBALS['supc_test_actions_fired'] )
		);
	}

	public function test_permission_and_integration_failures_have_distinct_messages(): void {
		$this->assertTrue( $this->registry->register( new Surface_Test_Adapter( 'permission_item', 'Permission' ) ) );
		$GLOBALS['supc_test_capabilities'][1] = array();
		$this->registry->flush_cache();

		$permission_html = ( new Create_Surface( $this->registry ) )->render();
		$this->assertStringContainsString( 'No creation permission is available for this account.', $permission_html );

		$this->assertTrue( $this->registry->unregister( 'permission_item' ) );
		$GLOBALS['supc_test_capabilities'][1] = array( 'publish_posts' => true );
		$this->assertTrue( $this->registry->register( new Surface_Test_Adapter( 'broken_route', 'Broken', 'publishing', 'public', 'https://external.example/create/' ) ) );
		$integration_html = ( new Create_Surface( $this->registry ) )->render();
		$this->assertStringContainsString( 'Authorized creation services are temporarily unavailable.', $integration_html );
		$this->assertStringNotContainsString( 'No creation permission is available for this account.', $integration_html );
	}

	public function test_unavailable_native_module_is_not_reported_as_permission_denial(): void {
		$this->assertTrue( $this->registry->register( new Surface_Test_Adapter( 'offline_item', 'Offline', 'publishing', 'public', '/offline/', 10, 'edit', false ) ) );

		$html = ( new Create_Surface( $this->registry ) )->render();

		$this->assertStringContainsString( 'Authorized creation services are temporarily unavailable.', $html );
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

		$this->assertStringContainsString( 'No creation permission is available for this account.', $html );
		$this->assertStringNotContainsString( 'data-supc-type=', $html );
	}
}
