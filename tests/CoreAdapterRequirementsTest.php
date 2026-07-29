<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Sabri\UniversalComposer\Contracts\Diagnostic_Adapter;
use Sabri\UniversalComposer\Core\Permission_Resolver;
use Sabri\UniversalComposer\Core\Registry;
use Sabri\UniversalComposer\Integration\Core_Adapter_Requirements;

final class File21_Contract_Adapter implements Diagnostic_Adapter {
	public function __construct(
		private string $key = 'social_publication',
		private string $minimum = '1.0.3',
		private bool $available = true,
		private string $nativeModule = 'sabri-complete-home-news-feed',
		private string $capability = 'sabri_feed_create_posts',
		private string $actualVersion = '1.0.3'
	) {
	}

	public function api_version(): string { return '1.0.0'; }
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
	public function health_report(): array {
		return array(
			'actual_native_version' => $this->actualVersion,
			'available'             => $this->available,
		);
	}
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
		$this->assertSame( 'not_registered', $report['reason'] );
		$this->assertSame( 'social_publication', $report['adapter_key'] );
	}

	public function test_available_file21_adapter_passes(): void {
		$this->assertTrue( $this->registry->register( new File21_Contract_Adapter() ) );
		$report = $this->requirements->social_publication_report();
		$this->assertSame( 'pass', $report['status'] );
		$this->assertSame( 'available', $report['reason'] );
		$this->assertSame( 'sabri-complete-home-news-feed', $report['native_module'] );
		$this->assertSame( '1.0.3', $report['actual_native'] );
	}

	public function test_temporarily_unavailable_adapter_warns_without_fatal(): void {
		$this->assertTrue( $this->registry->register( new File21_Contract_Adapter( available: false ) ) );
		$report = $this->requirements->social_publication_report();
		$this->assertSame( 'warning', $report['status'] );
		$this->assertSame( 'temporarily_unavailable', $report['reason'] );
	}

	public function test_old_declared_minimum_is_a_contract_failure(): void {
		$this->assertTrue( $this->registry->register( new File21_Contract_Adapter( minimum: '1.0.2' ) ) );
		$report = $this->requirements->social_publication_report();
		$this->assertSame( 'fail', $report['status'] );
		$this->assertSame( 'contract_mismatch', $report['reason'] );
		$this->assertContains( 'minimum_native_version', $report['contract_errors'] );
	}

	public function test_wrong_native_owner_is_a_contract_failure(): void {
		$this->assertTrue( $this->registry->register( new File21_Contract_Adapter( nativeModule: 'another-plugin' ) ) );
		$report = $this->requirements->social_publication_report();
		$this->assertSame( 'fail', $report['status'] );
		$this->assertContains( 'native_module', $report['contract_errors'] );
	}

	public function test_wrong_central_capability_is_a_contract_failure(): void {
		$this->assertTrue( $this->registry->register( new File21_Contract_Adapter( capability: 'read' ) ) );
		$report = $this->requirements->social_publication_report();
		$this->assertSame( 'fail', $report['status'] );
		$this->assertContains( 'required_capability', $report['contract_errors'] );
	}

	public function test_actual_native_version_below_minimum_is_a_release_failure(): void {
		$this->assertTrue( $this->registry->register( new File21_Contract_Adapter( actualVersion: '1.0.2' ) ) );
		$report = $this->requirements->social_publication_report();
		$this->assertSame( 'fail', $report['status'] );
		$this->assertSame( 'native_version_too_low', $report['reason'] );
	}
}
