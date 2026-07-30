<?php
/**
 * Isolated contract proving File 22 never autoloads an unverified File 20 class.
 */

declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );

$shell_path = __DIR__ . '/fixtures/sabri-unified-application-shell';
define( 'SABRI_SHELL_VERSION', '1.0.0' );
define( 'SABRI_SHELL_FILE', $shell_path . '/sabri-unified-application-shell.php' );
define( 'SABRI_SHELL_PATH', $shell_path . '/' );
define( 'SABRI_SHELL_SLUG', 'sabri-unified-application-shell' );
define( 'SABRI_SHELL_CREATE_CONTRACT_VERSION', '1.0.1' );
define( 'SABRI_SHELL_CREATE_CONTRACT_OWNER', 'sabri-unified-application-shell' );
define( 'SABRI_SHELL_CREATE_FUNCTIONS_OWNED', true );

$GLOBALS['supc_test_shell_autoloads'] = 0;
spl_autoload_register(
	static function ( string $class ): void {
		if ( 'Sabri\\UnifiedShell\\SafeMode' === $class ) {
			++$GLOBALS['supc_test_shell_autoloads'];
		}
	}
);

function get_option( string $key, mixed $default = false ): mixed {
	unset( $key );
	return $default;
}

require dirname( __DIR__ ) . '/includes/core/class-version.php';
require dirname( __DIR__ ) . '/includes/core/class-runtime-trust.php';
require dirname( __DIR__ ) . '/includes/core/class-safe-mode.php';

$failures = array();
if ( ! \Sabri\UniversalComposer\Core\Safe_Mode::disabled() ) {
	$failures[] = 'an unloaded File 20 Safe Mode class did not fail closed';
}
if ( 0 !== $GLOBALS['supc_test_shell_autoloads'] ) {
	$failures[] = 'File 22 autoloaded an unverified File 20 Safe Mode class';
}

if ( array() !== $failures ) {
	fwrite( STDERR, implode( PHP_EOL, $failures ) . PHP_EOL );
	exit( 1 );
}

echo "File 22 unverified shell Safe Mode was not autoloaded and failed closed.\n";
