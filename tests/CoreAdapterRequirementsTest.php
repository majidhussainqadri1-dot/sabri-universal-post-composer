<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Sabri\UniversalComposer\Contracts\Diagnostic_Adapter;
use Sabri\UniversalComposer\Contracts\Workflow_Adapter;
use Sabri\UniversalComposer\Core\Permission_Resolver;
use Sabri\UniversalComposer\Core\Registry;
use Sabri\UniversalComposer\Integration\Core_Adapter_Requirements;

final class File21_Contract_Adapter implements Workflow_Adapter, Diagnostic_Adapter {
	public function __construct(
		private string $key = 'social_publication',
		private string $minimum = '1.0.3',
		private bool $available = true,
		private string $nativeModule = 'sabri-complete-home-news-feed',
		private string $capability = 'sabri_feed_create_posts',
		private string $actualVersion = '1.0.3',
		private string $workflowApi = '1.0.0',
		private bool $supportsDrafts = true
	) {
	}

	public function api_version(): string { return '1.0.0'; }
	public function workflow_api_version(): string { return $this->workflowApi; }
	public function schema_version(): string { return '1.0.0'; }
	public function supports_native_drafts(): bool { return $this->supportsDrafts; }
	public function key(): string { return $this->key; }
	public function label(): string { return 'Social Post'; }
	public function description(): string { return 'Native File 21 social publication.'; }
	public function group(): string { return 'publishing'; }
	public function icon(): string { return 'admin-post'; }
	public function priority(): int { return 10; }
	public function native_module(): string { return $this->nativeModule; }
	public function minimum_native_version(): string { return $this->minimum; }
	public function required_capability(): string { return $this->capability; }
	public function privacy_classification(): string { return 'public'; }
	public function is_available(): bool { return $this->available; }
	public function can_create( int $user_id ): bool { return $user_id > 0; }
	public function start_url( int $user_id ): string { return '/create-post/?user=' . $user_id; }
	public function schema(): array {
		return array(
			'version' => '1.0.0',
			'fields'  => array(
				'content' => array(
					'type'          => 'textarea',
					'label_code'    => 'content',
					'required'      => true,
					'privacy_class' => 'public',
				),
			),
		);
	}
	public function create_draft( int $user_id, ?string $native_reference, array $payload ) { unset( $user_id, $payload ); return array( 'native_reference' => $native_reference ?? 'draft-1', 'status' => 'draft' ); }
	public function validate( int $user_id, array $payload ) { unset( $user_id, $payload ); return array( 'valid' => true, 'errors' => array(), 'warnings' => array() ); }
	public function preview( int $user_id, array $payload ) { unset( $user_id, $payload ); return array( 'preview_url' => '/preview/draft-1/', 'expires_at' => time() + 300 ); }
	public function submit( int $user_id, string $idempotency_key, array $payload ) { unset( $user_id, $idempotency_key, $payload ); return array( 'native_reference' => 'post-1', 'status' => 'pending_review' ); }
	public function status( int $user_id, string $native_reference ) { unset( $user_id ); return array( 'native_reference' => $native_reference, 'status' => 'pending_review' ); }
	public function canonical_url( int $user_id, string $native_reference ): string { unset( $user_id, $native_reference ); return '/post/1/'; }
	public function health_report(): array {
		return array(
			'actual_native_version' => $this->actualVersion,
			'available'             => $this->available,
		);
	}
}

final class Route_Only_File21_Adapter implements Diagnostic_Adapter {
	public function api_version(): string { return '1.0.0'; }
	public function key(): string { return 'social_publication'; }
	public function label(): string { return 'Social Post'; }
	public function description(): string { return 'Route only.'; }
	public function group(): string { return 'publishing'; }
	public function icon(): string { return 'admin-post'; }
	public function priority(): int { return 10; }
	public function native_module(): string { return 'sabri-complete-home-news-feed'; }
	public function minimum_native_version(): string { return '1.0.3'; }
	public function required_capability(): string { return 'sabri_feed_create_posts'; }
	public function privacy_classification(): string { return 'public'; }
	public function is_available(): bool { return true; }
	public function can_create( int $user_id ): bool { return $user_id > 0; }
	public function start_url( int $user_id ): string { return '/create-post/?user=' . $user_id; }
	public function health_report(): array { return array( 'actual_native_version' => '1.0.3' ); }
}

final class CoreAdapterRequirementsTest extends TestCase {
	private Registry $registry;
	private Core_Adapter_Requirements $requirements;

	protected function setUp(): void {
		$GLOBALS['supc_test_statuses']     = array( 1 => 'approved' );
		$GLOBALS['supc_test_capabilities'] = array( 1 => array( 'sabri_feed_create_posts' => true ) );
		$GLOBALS['supc_test_options']      = array();
		$this->registry                    = new Registry( new Permission_Resolver() );
		$this->requirements                = new Core_Adapter_Requirements( $this->registry );
	}

	public function test_missing_social_adapter_is_a_release_failure(): void {
		$report = $this->requirements->social_publication_report();
		$this->assertSame( 'fail', $report['status'] );
		$this->assertContains( 'social_publication_not_registered', $report['codes'] );
		$this->assertSame( 'social_publication', $report['adapter_key'] );
	}

	public function test_available_file21_workflow_adapter_passes(): void {
		$this->assertTrue( $this->registry->register( new File21_Contract_Adapter() ) );
		$report = $this->requirements->social_publication_report();
		$this->assertSame( 'pass', $report['status'] );
		$this->assertSame( array(), $report['codes'] );
		$this->assertSame( 'sabri-complete-home-news-feed', $report['native_module'] );
		$this->assertSame( '1.0.3', $report['actual_native'] );
	}

	public function test_route_only_file21_adapter_is_a_release_failure(): void {
		$this->assertTrue( $this->registry->register( new Route_Only_File21_Adapter() ) );
		$report = $this->requirements->social_publication_report();
		$this->assertSame( 'fail', $report['status'] );
		$this->assertContains( 'workflow_contract_missing', $report['codes'] );
	}

	public function test_temporarily_unavailable_adapter_warns_without_fatal(): void {
		$this->assertTrue( $this->registry->register( new File21_Contract_Adapter( available: false ) ) );
		$report = $this->requirements->social_publication_report();
		$this->assertSame( 'warning', $report['status'] );
		$this->assertContains( 'social_publication_temporarily_unavailable', $report['codes'] );
	}

	public function test_old_declared_minimum_is_a_contract_failure(): void {
		$this->assertTrue( $this->registry->register( new File21_Contract_Adapter( minimum: '1.0.2' ) ) );
		$report = $this->requirements->social_publication_report();
		$this->assertSame( 'fail', $report['status'] );
		$this->assertContains( 'minimum_native_version_too_low', $report['codes'] );
	}

	public function test_wrong_native_owner_is_a_contract_failure(): void {
		$this->assertTrue( $this->registry->register( new File21_Contract_Adapter( nativeModule: 'another-plugin' ) ) );
		$report = $this->requirements->social_publication_report();
		$this->assertSame( 'fail', $report['status'] );
		$this->assertContains( 'native_module_mismatch', $report['codes'] );
	}

	public function test_wrong_central_capability_is_a_contract_failure(): void {
		$this->assertTrue( $this->registry->register( new File21_Contract_Adapter( capability: 'read' ) ) );
		$report = $this->requirements->social_publication_report();
		$this->assertSame( 'fail', $report['status'] );
		$this->assertContains( 'required_capability_mismatch', $report['codes'] );
	}

	public function test_actual_native_version_below_minimum_is_a_release_failure(): void {
		$this->assertTrue( $this->registry->register( new File21_Contract_Adapter( actualVersion: '1.0.2' ) ) );
		$report = $this->requirements->social_publication_report();
		$this->assertSame( 'fail', $report['status'] );
		$this->assertContains( 'social_publication_native_version_too_low', $report['codes'] );
	}
}
