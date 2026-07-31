<?php
/**
 * Isolated compatibility test for the distributed File 20 version 1.0.0 shape.
 */

declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );
define( 'SUPC_PATH', dirname( __DIR__ ) . '/' );

function get_option( string $key, mixed $default = false ): mixed {
	unset( $key );
	return $default;
}

$shell_path = __DIR__ . '/fixtures/sabri-unified-application-shell-legacy-1.0.0';
define( 'SABRI_SHELL_VERSION', '1.0.0' );
define( 'SABRI_SHELL_FILE', $shell_path . '/sabri-unified-application-shell.php' );
define( 'SABRI_SHELL_PATH', $shell_path . '/' );
define( 'SABRI_SHELL_SLUG', 'sabri-unified-application-shell' );

require dirname( __DIR__ ) . '/includes/core/class-version.php';
require dirname( __DIR__ ) . '/includes/core/class-runtime-trust.php';
require dirname( __DIR__ ) . '/includes/core/class-safe-mode.php';

$failures = array();
if ( ! \Sabri\UniversalComposer\Core\Runtime_Trust::shell_package_claimed() ) {
	$failures[] = 'the real File 20 base package shape was not detected';
}
if ( ! \Sabri\UniversalComposer\Core\Runtime_Trust::shell_package_owned() ) {
	$failures[] = 'the canonical File 20 version 1.0.0 package was rejected';
}
if ( \Sabri\UniversalComposer\Core\Runtime_Trust::shell_create_contract_claimed() ) {
	$failures[] = 'a nonexistent File 20 Create contract was invented';
}
if ( \Sabri\UniversalComposer\Core\Safe_Mode::disabled() ) {
	$failures[] = 'legacy File 20 version 1.0.0 falsely disabled File 22';
}

if ( array() !== $failures ) {
	fwrite( STDERR, implode( PHP_EOL, $failures ) . PHP_EOL );
	exit( 1 );
}

echo "Distributed File 20 version 1.0.0 compatibility contract passed.\n";
