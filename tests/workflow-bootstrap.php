<?php
/**
 * PHPUnit bootstrap extension for direct workflow contracts.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname( __DIR__ ) . '/includes/core/class-runtime-trust.php';

if ( ! defined( 'SUPC_WORKFLOW_API_VERSION' ) ) {
	define( 'SUPC_WORKFLOW_API_VERSION', '1.0.0' );
}
if ( ! defined( 'SUPC_SUBJECT_SCHEMA_API_VERSION' ) ) {
	define( 'SUPC_SUBJECT_SCHEMA_API_VERSION', '1.0.0' );
}

// The shared PHPUnit process represents a correctly owned File 20 integration.
// Isolated collision tests predefine contradictory markers before loading their
// own minimal runtime and therefore retain their fail-closed producer boundary.
( static function (): void {
	$shell_path = __DIR__ . '/fixtures/sabri-unified-application-shell';
	if ( ! defined( 'SABRI_SHELL_VERSION' ) ) {
		define( 'SABRI_SHELL_VERSION', '1.0.0' );
	}
	if ( ! defined( 'SABRI_SHELL_FILE' ) ) {
		define( 'SABRI_SHELL_FILE', $shell_path . '/sabri-unified-application-shell.php' );
	}
	if ( ! defined( 'SABRI_SHELL_PATH' ) ) {
		define( 'SABRI_SHELL_PATH', $shell_path . '/' );
	}
	if ( ! defined( 'SABRI_SHELL_SLUG' ) ) {
		define( 'SABRI_SHELL_SLUG', 'sabri-unified-application-shell' );
	}
	if ( ! defined( 'SABRI_SHELL_CREATE_CONTRACT_VERSION' ) ) {
		define( 'SABRI_SHELL_CREATE_CONTRACT_VERSION', '1.0.1' );
	}
	if ( ! defined( 'SABRI_SHELL_CREATE_CONTRACT_OWNER' ) ) {
		define( 'SABRI_SHELL_CREATE_CONTRACT_OWNER', 'sabri-unified-application-shell' );
	}
	if ( ! defined( 'SABRI_SHELL_CREATE_FUNCTIONS_OWNED' ) ) {
		define( 'SABRI_SHELL_CREATE_FUNCTIONS_OWNED', true );
	}

	require_once $shell_path . '/includes/class-safe-mode.php';
	require_once $shell_path . '/includes/functions.php';
} )();

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( mixed $value, int $flags = 0, int $depth = 512 ): string|false {
		return json_encode( $value, $flags, $depth );
	}
}

require_once dirname( __DIR__ ) . '/includes/contracts/interface-workflow-adapter.php';
require_once dirname( __DIR__ ) . '/includes/core/class-workflow-coordinator.php';
require_once dirname( __DIR__ ) . '/includes/core/class-plugin.php';
require_once dirname( __DIR__ ) . '/includes/core/functions.php';
