<?php
/**
 * Isolated fail-closed test for File 22 core constant collisions.
 */

declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );
define( 'SUPC_PATH', '/foreign/path/' );

$GLOBALS['supc_bootstrap_collision_hooks'] = array();

function add_action( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {
	unset( $callback, $priority, $accepted_args );
	$GLOBALS['supc_bootstrap_collision_hooks'][] = $hook;
	return true;
}

require dirname( __DIR__ ) . '/sabri-universal-post-composer.php';

$failures = array();
if ( '/foreign/path/' !== SUPC_PATH ) {
	$failures[] = 'foreign core constant was overwritten';
}
if ( class_exists( '\Sabri\UniversalComposer\Core\Plugin', false ) ) {
	$failures[] = 'runtime classes loaded after a core constant collision';
}
if ( ! in_array( 'admin_init', $GLOBALS['supc_bootstrap_collision_hooks'], true ) ) {
	$failures[] = 'automatic deactivation hook was not registered';
}
if ( ! in_array( 'admin_notices', $GLOBALS['supc_bootstrap_collision_hooks'], true ) ) {
	$failures[] = 'administrator collision notice hook was not registered';
}

if ( array() !== $failures ) {
	fwrite( STDERR, implode( PHP_EOL, $failures ) . PHP_EOL );
	exit( 1 );
}

echo "File 22 bootstrap constant collision contract passed.\n";
