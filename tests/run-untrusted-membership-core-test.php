<?php
/**
 * Isolated fail-closed contract for a coherent but noncanonical Membership Core API.
 */

declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );
define( 'SUPC_MIN_SMC_VERSION', '1.0.1' );
define( 'SMC_VERSION', '99.0.0' );
define( 'SMC_DB_VERSION', '99.0.0' );
define( 'SMC_FILE', __FILE__ );
define( 'SMC_PATH', __DIR__ . '/' );

function smc_user_status( int $user_id ): string {
	unset( $user_id );
	return 'approved';
}

require dirname( __DIR__ ) . '/includes/contracts/interface-adapter.php';
require dirname( __DIR__ ) . '/includes/core/class-version.php';
require dirname( __DIR__ ) . '/includes/core/class-safe-mode.php';
require dirname( __DIR__ ) . '/includes/core/class-permission-resolver.php';

$resolver = new \Sabri\UniversalComposer\Core\Permission_Resolver();
if ( $resolver->core_available() ) {
	fwrite( STDERR, "A coherent noncanonical Membership Core package was trusted.\n" );
	exit( 1 );
}

echo "File 22 canonical Membership Core package contract passed.\n";
