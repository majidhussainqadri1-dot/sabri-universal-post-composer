<?php
/**
 * Canonical File 20 Safe Mode fixture.
 */

declare(strict_types=1);

namespace Sabri\UnifiedShell;

final class SafeMode {
	public static bool $throw = false;
	public static bool $disabled = false;

	public static function disabled(): bool {
		if ( self::$throw ) {
			throw new \RuntimeException( 'Private shell failure.' );
		}

		return self::$disabled;
	}
}
