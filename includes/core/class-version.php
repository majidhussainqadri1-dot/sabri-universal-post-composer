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
	private const PATTERN = '/^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)(?:-((?:0|[1-9][0-9]*|[0-9]*[A-Za-z-][0-9A-Za-z-]*)(?:\.(?:0|[1-9][0-9]*|[0-9]*[A-Za-z-][0-9A-Za-z-]*))*))?(?:\+([0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*))?$/D';

	public static function valid( string $version ): bool {
		return 1 === preg_match( self::PATTERN, trim( $version ) );
	}

	/**
	 * Compare semantic-version precedence while ignoring build metadata.
	 *
	 * @return int Negative when left is lower, zero when equal, positive when higher.
	 */
	public static function compare( string $left, string $right ): int {
		if ( ! self::valid( $left ) || ! self::valid( $right ) ) {
			throw new \InvalidArgumentException( 'Semantic-version comparison requires valid versions.' );
		}

		return version_compare( self::precedence_value( $left ), self::precedence_value( $right ) );
	}

	public static function at_least( string $actual, string $minimum ): bool {
		try {
			return self::compare( $actual, $minimum ) >= 0;
		} catch ( \InvalidArgumentException $error ) {
			unset( $error );
			return false;
		}
	}

	private static function precedence_value( string $version ): string {
		$build_position = strpos( $version, '+' );
		return false === $build_position ? $version : substr( $version, 0, $build_position );
	}
}
