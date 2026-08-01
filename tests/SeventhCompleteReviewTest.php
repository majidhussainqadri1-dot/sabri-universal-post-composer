<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Sabri\UniversalComposer\Contracts\Adapter;
use Sabri\UniversalComposer\Core\Permission_Resolver;
use Sabri\UniversalComposer\Core\Registry;
use Sabri\UniversalComposer\Core\Version;

final class Seventh_Review_Adapter implements Adapter {
	public function __construct( public string $minimum = '1.0.0' ) {
	}

	public function api_version(): string { return '1.0.0'; }
	public function key(): string { return 'seventh_review'; }
	public function label(): string { return 'Seventh Review'; }
	public function description(): string { return 'Seventh complete review adapter.'; }
	public function group(): string { return 'publishing'; }
	public function icon(): string { return 'edit'; }
	public function priority(): int { return 10; }
	public function native_module(): string { return 'seventh-review-module'; }
	public function minimum_native_version(): string { return $this->minimum; }
	public function required_capability(): string { return 'publish_posts'; }
	public function privacy_classification(): string { return 'private'; }
	public function is_available(): bool { return true; }
	public function can_create( int $user_id ): bool { return $user_id > 0; }
	public function start_url( int $user_id ): string { return '/create/seventh-review/?user=' . $user_id; }
}

final class SeventhCompleteReviewTest extends TestCase {
	public function test_semantic_version_validation_is_strict_and_supports_full_metadata(): void {
		$this->assertTrue( Version::valid( '1.2.3' ) );
		$this->assertTrue( Version::valid( '1.2.3-alpha.1+build.5' ) );
		$this->assertFalse( Version::valid( '01.2.3' ) );
		$this->assertFalse( Version::valid( '1.02.3' ) );
		$this->assertFalse( Version::valid( '1.2.3-alpha..1' ) );
		$this->assertFalse( Version::valid( '1.2.3+' ) );
	}

	public function test_build_metadata_does_not_lower_dependency_precedence(): void {
		$this->assertSame( 0, Version::compare( '1.0.3+build.9', '1.0.3' ) );
		$this->assertTrue( Version::at_least( '1.0.3+build.9', '1.0.3' ) );
		$this->assertFalse( Version::at_least( '1.0.3-rc.1+build.9', '1.0.3' ) );
	}

	public function test_registry_rejects_leading_zero_version_and_accepts_full_semver(): void {
		$registry = new Registry( new Permission_Resolver() );
		$result   = $registry->register( new Seventh_Review_Adapter( '01.0.0' ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'supc_invalid_minimum_native_version', $result->code );
		$this->assertNull( $registry->get( 'seventh_review' ) );

		$this->assertTrue( $registry->register( new Seventh_Review_Adapter( '1.0.0-alpha.1+build.5' ) ) );
		$this->assertSame( '1.0.0-alpha.1+build.5', $registry->adapter_contract( 'seventh_review' )['minimum_native_version'] );
	}

	public function test_owned_membership_core_fixture_is_accepted(): void {
		$this->assertTrue( ( new Permission_Resolver() )->core_available() );
	}
}
