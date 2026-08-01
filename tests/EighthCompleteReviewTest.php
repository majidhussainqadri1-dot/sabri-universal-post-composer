<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Sabri\UniversalComposer\Core\Permission_Resolver;
use Sabri\UniversalComposer\Core\Version;

final class EighthCompleteReviewTest extends TestCase {
	public function test_official_semver_prerelease_precedence_chain(): void {
		$versions = array(
			'1.0.0-alpha',
			'1.0.0-alpha.1',
			'1.0.0-alpha.beta',
			'1.0.0-beta',
			'1.0.0-beta.2',
			'1.0.0-beta.11',
			'1.0.0-rc.1',
			'1.0.0',
		);

		for ( $index = 0, $last = count( $versions ) - 1; $index < $last; ++$index ) {
			$this->assertLessThan(
				0,
				Version::compare( $versions[ $index ], $versions[ $index + 1 ] ),
				$versions[ $index ] . ' must precede ' . $versions[ $index + 1 ]
			);
		}
	}

	public function test_numeric_prerelease_identifiers_precede_nonnumeric_identifiers(): void {
		$this->assertLessThan( 0, Version::compare( '1.0.0-1', '1.0.0-alpha' ) );
		$this->assertGreaterThan( 0, Version::compare( '1.0.0-alpha.beta', '1.0.0-alpha.1' ) );
	}

	public function test_build_metadata_is_ignored_but_prerelease_is_not(): void {
		$this->assertSame( 0, Version::compare( '1.2.3+linux.9', '1.2.3+windows.4' ) );
		$this->assertLessThan( 0, Version::compare( '1.2.3-rc.1+linux.9', '1.2.3+windows.4' ) );
	}

	public function test_version_inputs_are_bounded_and_whitespace_is_rejected(): void {
		$this->assertFalse( Version::valid( ' 1.2.3' ) );
		$this->assertFalse( Version::valid( '1.2.3 ' ) );
		$this->assertFalse( Version::valid( '1.2.3+' . str_repeat( 'a', 260 ) ) );
	}

	public function test_large_numeric_core_identifiers_compare_without_integer_overflow(): void {
		$this->assertLessThan(
			0,
			Version::compare(
				'999999999999999999999999.0.0',
				'1000000000000000000000000.0.0'
			)
		);
	}

	public function test_invalid_comparison_throws_and_at_least_fails_closed(): void {
		$this->assertFalse( Version::at_least( '01.0.0', '1.0.0' ) );
		$this->expectException( InvalidArgumentException::class );
		Version::compare( '1.0.0', '1.0' );
	}

	public function test_canonical_membership_package_fixture_is_accepted(): void {
		$this->assertSame( 'sabri-membership-core.php', basename( (string) SMC_FILE ) );
		$this->assertSame( 'sabri-membership-core', basename( rtrim( (string) SMC_PATH, '/\\' ) ) );
		$this->assertTrue( ( new Permission_Resolver() )->core_available() );
	}
}
