<?php
/**
 * PHPUnit bootstrap extension for direct workflow contracts.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if ( ! defined( 'SUPC_WORKFLOW_API_VERSION' ) ) {
	define( 'SUPC_WORKFLOW_API_VERSION', '1.0.0' );
}
if ( ! defined( 'SUPC_SUBJECT_SCHEMA_API_VERSION' ) ) {
	define( 'SUPC_SUBJECT_SCHEMA_API_VERSION', '1.0.0' );
}

// The shared PHPUnit process represents a correctly owned File 20 integration.
// Isolated collision tests predefine contradictory markers before loading this
// bootstrap and therefore retain their fail-closed producer boundary.
if ( ! defined( 'SABRI_SHELL_CREATE_CONTRACT_VERSION' ) ) {
	define( 'SABRI_SHELL_CREATE_CONTRACT_VERSION', '1.0.1' );
}
if ( ! defined( 'SABRI_SHELL_CREATE_CONTRACT_OWNER' ) ) {
	define( 'SABRI_SHELL_CREATE_CONTRACT_OWNER', 'sabri-unified-application-shell' );
}
if ( ! defined( 'SABRI_SHELL_CREATE_FUNCTIONS_OWNED' ) ) {
	define( 'SABRI_SHELL_CREATE_FUNCTIONS_OWNED', true );
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( mixed $value, int $flags = 0, int $depth = 512 ): string|false {
		return json_encode( $value, $flags, $depth );
	}
}

require_once dirname( __DIR__ ) . '/includes/contracts/interface-workflow-adapter.php';
require_once dirname( __DIR__ ) . '/includes/core/class-workflow-coordinator.php';
require_once dirname( __DIR__ ) . '/includes/core/class-plugin.php';
require_once dirname( __DIR__ ) . '/includes/core/functions.php';
