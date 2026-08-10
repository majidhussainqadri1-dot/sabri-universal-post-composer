<?php
/**
 * Strict semantic-version validation and precedence comparison.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

namespace Sabri\UniversalComposer\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Version {
	private const MAX_LENGTH      = 255;
	private const PATTERN         = '/^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)(?:-((?:0|[1-9][0-9]*|[0-9]*[A-Za-z-][0-9A-Za-z-]*)(?:\.(?:0|[1-9][0-9]*|[0-9]*[A-Za-z-][0-9A-Za-z-]*))*))?(?:\+([0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*))?$/D';
	private const PACKAGE_PATTERN = '/^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)(?:\.(0|[1-9][0-9]*))?$/D';

	public static function valid( string $version ): bool {
		return null !== self::parse( $version );
	}

	/**
	 * Compare Semantic Versioning precedence while ignoring build metadata.
	 *
	 * @return int Negative when left is lower, zero when equal, positive when higher.
	 */
	public static function compare( string $left, string $right ): int {
		$left_version  = self::parse( $left );
		$right_version = self::parse( $right );
		if ( null === $left_version || null === $right_version ) {
			throw new \InvalidArgumentException( 'Semantic-version comparison requires valid versions.' );
		}

		foreach ( array( 'major', 'minor', 'patch' ) as $identifier ) {
			$comparison = self::compare_numeric_identifier( $left_version[ $identifier ], $right_version[ $identifier ] );
			if ( 0 !== $comparison ) {
				return $comparison;
			}
		}

		return self::compare_prerelease( $left_version['prerelease'], $right_version['prerelease'] );
	}

	public static function at_least( string $actual, string $minimum ): bool {
		try {
			return self::compare( $actual, $minimum ) >= 0;
		} catch ( \InvalidArgumentException $error ) {
			unset( $error );
			return false;
		}
	}

	/**
	 * Validate the numeric three- or four-part package identities used by the
	 * WordPress distribution layer. Runtime/API versions remain strict SemVer.
	 */
	public static function valid_wordpress_package( string $version ): bool {
		return null !== self::parse_wordpress_package( $version );
	}

	/**
	 * Compare WordPress package identities after normalizing a missing fourth
	 * component to zero. This comparison is intentionally separate from SemVer.
	 *
	 * @return int Negative when left is lower, zero when equal, positive when higher.
	 */
	public static function compare_wordpress_package( string $left, string $right ): int {
		$left_version  = self::parse_wordpress_package( $left );
		$right_version = self::parse_wordpress_package( $right );
		if ( null === $left_version || null === $right_version ) {
			throw new \InvalidArgumentException( 'WordPress package comparison requires valid numeric package versions.' );
		}

		foreach ( array_keys( $left_version ) as $identifier ) {
			$comparison = self::compare_numeric_identifier( $left_version[ $identifier ], $right_version[ $identifier ] );
			if ( 0 !== $comparison ) {
				return $comparison;
			}
		}
		return 0;
	}

	public static function wordpress_package_at_least( string $actual, string $minimum ): bool {
		try {
			return self::compare_wordpress_package( $actual, $minimum ) >= 0;
		} catch ( \InvalidArgumentException $error ) {
			unset( $error );
			return false;
		}
	}

	/**
	 * @return array{major:string,minor:string,patch:string,prerelease:array<int,string>|null}|null
	 */
	private static function parse( string $version ): ?array {
		if ( '' === $version || $version !== trim( $version ) || strlen( $version ) > self::MAX_LENGTH ) {
			return null;
		}

		if ( 1 !== preg_match( self::PATTERN, $version, $matches ) ) {
			return null;
		}

		$prerelease = isset( $matches[4] ) && '' !== $matches[4]
			? explode( '.', $matches[4] )
			: null;

		return array(
			'major'      => $matches[1],
			'minor'      => $matches[2],
			'patch'      => $matches[3],
			'prerelease' => $prerelease,
		);
	}

	/** @return array{major:string,minor:string,patch:string,package:string}|null */
	private static function parse_wordpress_package( string $version ): ?array {
		if ( '' === $version || $version !== trim( $version ) || strlen( $version ) > self::MAX_LENGTH ) {
			return null;
		}
		if ( 1 !== preg_match( self::PACKAGE_PATTERN, $version, $matches ) ) {
			return null;
		}
		return array(
			'major'   => $matches[1],
			'minor'   => $matches[2],
			'patch'   => $matches[3],
			'package' => isset( $matches[4] ) && '' !== $matches[4] ? $matches[4] : '0',
		);
	}

	private static function compare_numeric_identifier( string $left, string $right ): int {
		$length = strlen( $left ) <=> strlen( $right );
		return 0 !== $length ? $length : strcmp( $left, $right );
	}

	/**
	 * @param array<int,string>|null $left  Left prerelease identifiers.
	 * @param array<int,string>|null $right Right prerelease identifiers.
	 */
	private static function compare_prerelease( ?array $left, ?array $right ): int {
		if ( null === $left && null === $right ) {
			return 0;
		}
		if ( null === $left ) {
			return 1;
		}
		if ( null === $right ) {
			return -1;
		}

		$count = max( count( $left ), count( $right ) );
		for ( $index = 0; $index < $count; ++$index ) {
			if ( ! array_key_exists( $index, $left ) ) {
				return -1;
			}
			if ( ! array_key_exists( $index, $right ) ) {
				return 1;
			}

			$left_identifier  = $left[ $index ];
			$right_identifier = $right[ $index ];
			$left_numeric     = 1 === preg_match( '/^[0-9]+$/D', $left_identifier );
			$right_numeric    = 1 === preg_match( '/^[0-9]+$/D', $right_identifier );

			if ( $left_numeric && $right_numeric ) {
				$comparison = self::compare_numeric_identifier( $left_identifier, $right_identifier );
			} elseif ( $left_numeric ) {
				$comparison = -1;
			} elseif ( $right_numeric ) {
				$comparison = 1;
			} else {
				$comparison = strcmp( $left_identifier, $right_identifier );
			}

			if ( 0 !== $comparison ) {
				return $comparison;
			}
		}

		return 0;
	}
}
