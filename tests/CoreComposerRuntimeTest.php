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
	public function create_draft( int $user_id, ?string $native_reference, array $payload ) { return array( 'native_reference' => $native_reference ?: 'native:1', 'status' => 'draft' ); }
	public function validate( int $user_id, array $payload ) { return array( 'valid' => true, 'errors' => array(), 'warnings' => array() ); }
	public function preview( int $user_id, array $payload ) { return array( 'preview_url' => 'https://example.test/preview/1/', 'expires_at' => time() + 300 ); }
	public function submit( int $user_id, string $idempotency_key, array $payload ) { return array( 'native_reference' => 'native:1', 'status' => 'published', 'canonical_url' => 'https://example.test/item/1/' ); }
	public function status( int $user_id, string $native_reference ) { return array( 'native_reference' => $native_reference, 'status' => 'draft' ); }
	public function canonical_url( int $user_id, string $native_reference ): string { return 'https://example.test/item/1/'; }
}

final class CoreComposerRuntimeTest extends TestCase {
	private Registry $registry;

	protected function setUp(): void {
		$_GET = array();
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
