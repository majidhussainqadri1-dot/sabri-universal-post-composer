<?php
/**
 * Isolated contract proving bootstrap/API preflight variables remain local.
 */

declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );
define( 'SUPC_PATH', '/foreign/path/' );

function add_action( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {
	unset( $hook, $callback, $priority, $accepted_args );
	return true;
}

require dirname( __DIR__ ) . '/sabri-universal-post-composer.php';
require dirname( __DIR__ ) . '/includes/core/functions.php';

$unexpected = array(
	'core_constants',
	'core_symbols',
	'core_constant_collisions',
	'core_symbol_collisions',
	'public_api_functions',
	'public_api_markers',
	'public_api_function_collisions',
	'public_api_marker_collisions',
	'public_api_collisions',
);

$leaked = array_values(
	array_filter(
		$unexpected,
		static fn ( string $key ): bool => array_key_exists( $key, $GLOBALS )
	)
);
if ( array() !== $leaked ) {
	fwrite( STDERR, 'Generic bootstrap variables leaked into WordPress global scope: ' . implode( ', ', $leaked ) . PHP_EOL );
	exit( 1 );
}

echo "File 22 global-scope isolation contract passed.\n";
