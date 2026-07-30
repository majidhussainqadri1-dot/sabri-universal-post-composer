<?php
/**
 * Isolated bootstrap symbol-collision contract.
 */

declare(strict_types=1);

namespace Sabri\UniversalComposer\Core {
	final class Registry {
	}
}

namespace {
	define( 'ABSPATH', __DIR__ . '/' );

	function add_action( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {
		unset( $hook, $callback, $priority, $accepted_args );
		return true;
	}

	require dirname( __DIR__ ) . '/sabri-universal-post-composer.php';

	$failures = array();
	if ( defined( 'SUPC_VERSION' ) ) {
		$failures[] = 'bootstrap continued after a preclaimed File 22 class';
	}
	if ( ! class_exists( 'Sabri\\UniversalComposer\\Core\\Registry', false ) ) {
		$failures[] = 'preclaimed class was not preserved';
	}
	if ( class_exists( 'Sabri\\UniversalComposer\\Core\\Plugin', false ) ) {
		$failures[] = 'runtime files loaded after symbol collision';
	}

	if ( array() !== $failures ) {
		fwrite( STDERR, implode( PHP_EOL, $failures ) . PHP_EOL );
		exit( 1 );
	}

	echo "File 22 bootstrap symbol collision contract passed.\n";
}
