<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Sabri\UniversalComposer\Contracts\Workflow_Adapter;
use Sabri\UniversalComposer\Core\Browser_Runtime;
use Sabri\UniversalComposer\Core\Permission_Resolver;
use Sabri\UniversalComposer\Core\Registry;
use Sabri\UniversalComposer\Core\Workflow_Coordinator;
use Sabri\UniversalComposer\Presentation\Workflow_Surface;

final class Runtime_Workflow_Adapter implements Workflow_Adapter {
	public static bool $substitute_reference = false;

	public function api_version(): string { return '1.0.0'; }
	public function workflow_api_version(): string { return '1.0.0'; }
	public function schema_version(): string { return '1.0.0'; }
	public function supports_native_drafts(): bool { return true; }
	public function key(): string { return 'runtime_workflow'; }
	public function label(): string { return 'Runtime Workflow'; }
	public function description(): string { return 'Create a native runtime item.'; }
	public function group(): string { return 'publishing'; }
	public function icon(): string { return 'edit'; }
	public function priority(): int { return 5; }
	public function native_module(): string { return 'runtime-native-owner'; }
	public function minimum_native_version(): string { return '1.0.0'; }
	public function required_capability(): string { return 'publish_posts'; }
	public function privacy_classification(): string { return 'public'; }
	public function is_available(): bool { return true; }
	public function can_create( int $user_id ): bool { return 1 === $user_id; }
	public function start_url( int $user_id ): string { return 1 === $user_id ? '/native-create/' : ''; }
	public function schema(): array {
		return array(
			'version' => '1.0.0',
			'fields' => array(
				'title' => array( 'type' => 'text', 'label_code' => 'title', 'required' => true, 'privacy_class' => 'public' ),
				'content' => array( 'type' => 'textarea', 'label_code' => 'content', 'required' => true, 'privacy_class' => 'public' ),
			),
		);
	}
	public function create_draft( int $user_id, ?string $native_reference, array $payload ) {
		return array(
			'native_reference' => self::$substitute_reference ? 'native:other' : ( $native_reference ?: 'native:1' ),
			'status' => 'draft',
		);
	}
	public function validate( int $user_id, array $payload ) { return array( 'valid' => true, 'errors' => array(), 'warnings' => array() ); }
	public function preview( int $user_id, array $payload ) { return array( 'preview_url' => 'https://example.test/preview/1/', 'expires_at' => time() + 300 ); }
	public function submit( int $user_id, string $idempotency_key, array $payload ) {
		return array(
			'native_reference' => self::$substitute_reference ? 'native:other' : 'native:1',
			'status' => 'published',
			'canonical_url' => 'https://example.test/item/1/',
		);
	}
	public function status( int $user_id, string $native_reference ) {
		return array(
			'native_reference' => self::$substitute_reference ? 'native:other' : $native_reference,
			'status' => 'draft',
		);
	}
	public function canonical_url( int $user_id, string $native_reference ): string { return 'https://example.test/item/1/'; }
}

final class CoreComposerRuntimeTest extends TestCase {
	private Registry $registry;

	protected function setUp(): void {
		$_GET = array();
		Runtime_Workflow_Adapter::$substitute_reference = false;
		$GLOBALS['supc_test_current_user'] = 1;
		$GLOBALS['supc_test_logged_in'] = true;
		$GLOBALS['supc_test_statuses'] = array( 1 => 'approved' );
		$GLOBALS['supc_test_capabilities'] = array( 1 => array( 'publish_posts' => true ) );
		$GLOBALS['supc_test_options'] = array();
		$GLOBALS['supc_test_pages'] = array();
		$this->registry = new Registry( new Permission_Resolver() );
		$this->assertTrue( $this->registry->register( new Runtime_Workflow_Adapter() ) );
	}

	public function test_workflow_adapter_card_stays_inside_file22_create_surface(): void {
		$runtime = new Browser_Runtime();
		$plugin_registry = \Sabri\UniversalComposer\Core\Plugin::instance()->registry();
		$this->assertTrue( $plugin_registry->register( new Runtime_Workflow_Adapter() ) );
		$groups = $runtime->gateway_groups( 1 );
		$url = $groups['publishing']['cards'][0]['url'];
		$this->assertStringContainsString( 'type=runtime_workflow', $url );
		$this->assertStringNotContainsString( '/native-create/', $url );
	}

	public function test_schema_driven_surface_has_accessible_native_owner_boundary(): void {
		$_GET['type'] = 'runtime_workflow';
		$surface = new Workflow_Surface( $this->registry, new Workflow_Coordinator( $this->registry, new Permission_Resolver() ) );
		$html = $surface->render( 1 );
		$this->assertStringContainsString( 'data-supc-workflow', $html );
		$this->assertStringContainsString( 'name="title"', $html );
		$this->assertStringContainsString( 'name="content"', $html );
		$this->assertStringContainsString( 'role="status"', $html );
		$this->assertStringContainsString( 'never written to browser local storage', $html );
	}

	public function test_browser_runtime_never_uses_persistent_web_storage(): void {
		$source = file_get_contents( dirname( __DIR__ ) . '/assets/js/workflow-composer.js' );
		$this->assertIsString( $source );
		$this->assertStringNotContainsString( 'localStorage', $source );
		$this->assertStringNotContainsString( 'sessionStorage', $source );
		$this->assertStringNotContainsString( 'indexedDB', $source );
	}

	public function test_session_table_contains_metadata_not_draft_payload(): void {
		$source = file_get_contents( dirname( __DIR__ ) . '/includes/core/class-session-store.php' );
		$this->assertIsString( $source );
		$this->assertStringContainsString( 'native_reference varchar(255)', $source );
		$this->assertStringContainsString( 'idempotency_key varchar(80)', $source );
		$this->assertDoesNotMatchRegularExpression( '/\n\s*payload(?:_json)?\s+(?:longtext|text|json)/i', $source );
	}

	public function test_submit_persists_idempotency_identity_before_native_dispatch(): void {
		$source = file_get_contents( dirname( __DIR__ ) . '/includes/http/class-rest-controller.php' );
		$this->assertIsString( $source );
		$persist = strpos( $source, 'ensure_idempotency_key' );
		$dispatch = strpos( $source, '$this->coordinator->submit' );
		$this->assertIsInt( $persist );
		$this->assertIsInt( $dispatch );
		$this->assertLessThan( $dispatch, $persist );
		$this->assertStringContainsString( 'wp_verify_nonce( $nonce, \'wp_rest\' )', $source );
		$this->assertStringContainsString( 'no-store, no-cache', $source );
	}

	public function test_internal_native_reference_does_not_need_to_be_declared_as_a_user_field(): void {
		$coordinator = new Workflow_Coordinator( $this->registry, new Permission_Resolver() );
		$payload = array( 'title' => 'A title', 'content' => 'A body' );
		$preview = $coordinator->preview( 1, 'runtime_workflow', $payload + array( 'native_reference' => 'native:1' ) );
		$this->assertIsArray( $preview );
		$submit = $coordinator->submit(
			1,
			'runtime_workflow',
			'123e4567-e89b-42d3-a456-426614174000:123e4567-e89b-42d3-a456-426614174001',
			$payload + array( 'native_reference' => 'native:1' )
		);
		$this->assertIsArray( $submit );
		$this->assertSame( 'native:1', $submit['native_reference'] );
	}

	public function test_native_adapter_cannot_substitute_another_object_reference(): void {
		$coordinator = new Workflow_Coordinator( $this->registry, new Permission_Resolver() );
		$payload = array( 'title' => 'A title', 'content' => 'A body' );
		Runtime_Workflow_Adapter::$substitute_reference = true;

		$draft = $coordinator->create_draft( 1, 'runtime_workflow', 'native:1', $payload );
		$this->assertInstanceOf( WP_Error::class, $draft );
		$this->assertSame( 'supc_native_reference_mismatch', $draft->code );

		$submit = $coordinator->submit(
			1,
			'runtime_workflow',
			'123e4567-e89b-42d3-a456-426614174000:123e4567-e89b-42d3-a456-426614174001',
			$payload + array( 'native_reference' => 'native:1' )
		);
		$this->assertInstanceOf( WP_Error::class, $submit );
		$this->assertSame( 'supc_native_reference_mismatch', $submit->code );

		$status = $coordinator->status( 1, 'runtime_workflow', 'native:1' );
		$this->assertInstanceOf( WP_Error::class, $status );
		$this->assertSame( 'supc_native_reference_mismatch', $status->code );
	}

	public function test_review_r3_guards_resume_authorization_and_accessibility_boundaries(): void {
		$browser = file_get_contents( dirname( __DIR__ ) . '/assets/js/workflow-composer.js' );
		$rest    = file_get_contents( dirname( __DIR__ ) . '/includes/http/class-rest-controller.php' );
		$surface = file_get_contents( dirname( __DIR__ ) . '/includes/presentation/class-workflow-surface.php' );

		$this->assertIsString( $browser );
		$this->assertIsString( $rest );
		$this->assertIsString( $surface );
		$this->assertStringContainsString( 'await resumePromise', $browser );
		$this->assertStringContainsString( '!dirty && session && session.native_reference', $browser );
		$this->assertStringContainsString( '$this->coordinator->schema', $rest );
		$this->assertStringContainsString( 'SESSION_LOCK_TTL = 600', $rest );
		$this->assertStringContainsString( "'supc_workflow_permission_denied' => 403", $rest );
		$this->assertStringContainsString( 'Workflow_Validator() )->internal_url', $surface );
		$this->assertStringContainsString( 'aria-describedby', $surface );
	}

	public function test_fresh_adversarial_review_guards_browser_and_error_boundaries(): void {
		$browser = file_get_contents( dirname( __DIR__ ) . '/assets/js/workflow-composer.js' );
		$runtime = file_get_contents( dirname( __DIR__ ) . '/includes/core/class-browser-runtime.php' );
		$rest    = file_get_contents( dirname( __DIR__ ) . '/includes/http/class-rest-controller.php' );

		$this->assertIsString( $browser );
		$this->assertIsString( $runtime );
		$this->assertIsString( $rest );
		$this->assertStringContainsString( 'window.setInterval', $browser );
		$this->assertStringContainsString( '25000', $browser );
		$this->assertStringContainsString( "window.open('about:blank'", $browser );
		$this->assertStringContainsString( "replace(/\\/+$/, '')", $browser );
		$this->assertStringContainsString( 'if ( headers_sent() )', $runtime );
		$this->assertStringContainsString(
			"add_action( 'admin_init', array( Session_Store::class, 'maybe_install' ) )",
			$runtime
		);
		$this->assertStringContainsString( 'get_error_data', $rest );
		$this->assertStringContainsString( 'adapter_version_changed', $rest );
	}
}
