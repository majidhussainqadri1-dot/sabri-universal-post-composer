<?php
/**
 * Isolated fail-closed test for a partial File 20 Create-contract claim.
 */

declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );
define( 'SUPC_PATH', dirname( __DIR__ ) . '/' );
define( 'SABRI_SHELL_CREATE_CONTRACT_VERSION', '1.0.1' );

function get_option( string $key, mixed $default = false ): mixed {
	unset( $key );
	return $default;
}

require dirname( __DIR__ ) . '/includes/core/class-version.php';
require dirname( __DIR__ ) . '/includes/core/class-runtime-trust.php';
require dirname( __DIR__ ) . '/includes/core/class-safe-mode.php';

$failures = array();
if ( ! \Sabri\UniversalComposer\Core\Runtime_Trust::shell_create_contract_claimed() ) {
	$failures[] = 'partial File 20 Create-contract claim was not detected';
}
if ( \Sabri\UniversalComposer\Core\Runtime_Trust::shell_create_contract_owned() ) {
	$failures[] = 'partial File 20 Create contract was incorrectly trusted';
}
if ( ! \Sabri\UniversalComposer\Core\Safe_Mode::disabled() ) {
	$failures[] = 'partial File 20 Create-contract claim did not fail closed';
}

if ( array() !== $failures ) {
	fwrite( STDERR, implode( PHP_EOL, $failures ) . PHP_EOL );
	exit( 1 );
}

echo "Partial File 20 Create-contract claim failed closed.\n";
