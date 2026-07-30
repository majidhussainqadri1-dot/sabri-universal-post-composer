<?php
/**
 * Isolated collision test for File 22's global public PHP API.
 */

declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );

function supc_register_adapter( $adapter ) {
	unset( $adapter );
	return 'foreign';
}

require dirname( __DIR__ ) . '/includes/core/functions.php';

$failures = array();
if ( ! defined( 'SUPC_PUBLIC_API_FUNCTIONS_OWNED' ) || false !== SUPC_PUBLIC_API_FUNCTIONS_OWNED ) {
	$failures[] = 'collision did not set fail-closed ownership marker';
}
if ( ! defined( 'SUPC_PUBLIC_API_VERSION' ) || '0.0.0' !== (string) SUPC_PUBLIC_API_VERSION ) {
	$failures[] = 'collision did not set incompatible API version';
}
if ( ! defined( 'SUPC_PUBLIC_API_OWNER' ) || 'unclaimed' !== (string) SUPC_PUBLIC_API_OWNER ) {
	$failures[] = 'collision did not preserve unclaimed ownership';
}
if ( ! defined( 'SUPC_PUBLIC_API_COLLISIONS' ) || false === strpos( (string) SUPC_PUBLIC_API_COLLISIONS, 'supc_register_adapter' ) ) {
	$failures[] = 'collision identifier was not recorded';
}
foreach ( array( 'supc_unregister_adapter', 'supc_adapter_available', 'supc_workflow_schema', 'supc_workflow_submit' ) as $function ) {
	if ( function_exists( $function ) ) {
		$failures[] = 'partial mixed API was declared: ' . $function;
	}
}
if ( 'foreign' !== supc_register_adapter( null ) ) {
	$failures[] = 'foreign producer was overwritten';
}

if ( array() !== $failures ) {
	fwrite( STDERR, implode( PHP_EOL, $failures ) . PHP_EOL );
	exit( 1 );
}

echo "File 22 public API collision contract passed.\n";
