<?php
/**
 * Isolated ownership test for a colliding File 20 Safe Mode class.
 */

declare(strict_types=1);

namespace Sabri\UnifiedShell {
	final class SafeMode {
		public static function disabled(): bool {
			return false;
		}
	}
}

namespace {
	define( 'ABSPATH', __DIR__ . '/' );

	function get_option( string $key, mixed $default = false ): mixed {
		unset( $key );
		return $default;
	}

	require dirname( __DIR__ ) . '/includes/core/class-safe-mode.php';

	if ( true !== \Sabri\UniversalComposer\Core\Safe_Mode::disabled() ) {
		fwrite( STDERR, "An unowned shell Safe Mode class was trusted.\n" );
		exit( 1 );
	}

	echo "File 22 untrusted shell Safe Mode contract passed.\n";
}
