<?php
/**
 * Isolated fail-closed idempotency-key generator contract.
 */

declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );
define( 'SUPC_WORKFLOW_API_VERSION', '1.0.0' );

function wp_generate_uuid4(): string {
	return 'malformed-platform-uuid';
}

require dirname( __DIR__ ) . '/includes/contracts/interface-adapter.php';
require dirname( __DIR__ ) . '/includes/contracts/interface-workflow-adapter.php';
require dirname( __DIR__ ) . '/includes/core/class-version.php';
require dirname( __DIR__ ) . '/includes/core/class-permission-resolver.php';
require dirname( __DIR__ ) . '/includes/core/class-registry.php';
require dirname( __DIR__ ) . '/includes/core/class-workflow-coordinator.php';

$permissions = new \Sabri\UniversalComposer\Core\Permission_Resolver();
$registry = new \Sabri\UniversalComposer\Core\Registry( $permissions );
$coordinator = new \Sabri\UniversalComposer\Core\Workflow_Coordinator( $registry, $permissions );

if ( '' !== $coordinator->generate_idempotency_key() ) {
	fwrite( STDERR, "Malformed platform UUID output was accepted as an idempotency key.\n" );
	exit( 1 );
}

echo "Malformed platform UUID output failed closed.\n";
