<?php
/**
 * Isolated marker-collision test for File 22's global public PHP API.
 */

declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );
define( 'SUPC_PUBLIC_API_COLLISIONS', 'foreign-marker-owner' );

require dirname( __DIR__ ) . '/includes/core/functions.php';

$failures = array();
if ( ! defined( 'SUPC_PUBLIC_API_FUNCTIONS_OWNED' ) || false !== SUPC_PUBLIC_API_FUNCTIONS_OWNED ) {
	$failures[] = 'marker collision did not set fail-closed ownership';
}
if ( ! defined( 'SUPC_PUBLIC_API_VERSION' ) || '0.0.0' !== (string) SUPC_PUBLIC_API_VERSION ) {
	$failures[] = 'marker collision did not set incompatible API version';
}
if ( ! defined( 'SUPC_PUBLIC_API_OWNER' ) || 'unclaimed' !== (string) SUPC_PUBLIC_API_OWNER ) {
	$failures[] = 'marker collision did not preserve unclaimed ownership';
}
if ( 'foreign-marker-owner' !== (string) SUPC_PUBLIC_API_COLLISIONS ) {
	$failures[] = 'foreign collision marker was overwritten';
}
foreach ( array( 'supc_register_adapter', 'supc_adapter_available', 'supc_workflow_schema', 'supc_workflow_submit' ) as $function ) {
	if ( function_exists( $function ) ) {
		$failures[] = 'partial API was declared after marker collision: ' . $function;
	}
}

if ( array() !== $failures ) {
	fwrite( STDERR, implode( PHP_EOL, $failures ) . PHP_EOL );
	exit( 1 );
}

echo "File 22 public API marker collision contract passed.\n";
