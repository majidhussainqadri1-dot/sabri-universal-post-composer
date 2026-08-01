<?php
/**
 * PHPUnit bootstrap extension for direct workflow contracts.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if ( ! defined( 'SUPC_FILE' ) ) {
	define( 'SUPC_FILE', dirname( __DIR__ ) . '/sabri-universal-post-composer.php' );
}
if ( ! defined( 'SUPC_PATH' ) ) {
	define( 'SUPC_PATH', dirname( __DIR__ ) . '/' );
}

require_once SUPC_PATH . 'includes/core/class-contract-boundary.php';
require_once SUPC_PATH . 'includes/core/class-runtime-trust.php';

if ( ! defined( 'SUPC_WORKFLOW_API_VERSION' ) ) {
	define( 'SUPC_WORKFLOW_API_VERSION', '1.0.0' );
}
if ( ! defined( 'SUPC_SUBJECT_SCHEMA_API_VERSION' ) ) {
	define( 'SUPC_SUBJECT_SCHEMA_API_VERSION', '1.0.0' );
}

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
	if ( ! class_exists( '\\Sabri\\UnifiedShell\\SafeMode', false ) ) {
		require_once $shell_path . '/includes/class-safe-mode.php';
	}
	require_once $shell_path . '/includes/functions.php';
} )();

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( mixed $value, int $flags = 0, int $depth = 512 ): string|false {
		return json_encode( $value, $flags, $depth );
	}
}

require_once SUPC_PATH . 'includes/contracts/interface-workflow-adapter.php';
require_once SUPC_PATH . 'includes/core/class-workflow-validator.php';
require_once SUPC_PATH . 'includes/core/class-workflow-coordinator.php';
require_once SUPC_PATH . 'includes/core/class-plugin.php';
require_once SUPC_PATH . 'includes/core/functions.php';
