<?php
/**
 * Minimal WordPress stubs for isolated contract tests.
 */

declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );
define( 'SUPC_ADAPTER_API_VERSION', '1.0.0' );
define( 'SUPC_MIN_SMC_VERSION', '1.0.1' );
define( 'SMC_VERSION', '1.0.1' );

$GLOBALS['supc_test_statuses']     = array( 1 => 'approved', 2 => 'suspended' );
$GLOBALS['supc_test_capabilities'] = array( 1 => array( 'publish_posts' => true ) );
$GLOBALS['supc_test_options']      = array();

class WP_Error {
	public function __construct(
		public string $code = '',
		public string $message = '',
		public mixed $data = null
	) {
	}
}

function __( string $text, string $domain = '' ): string {
	unset( $domain );
	return $text;
}

function do_action( string $hook, ...$args ): void {
	unset( $hook, $args );
}

function get_userdata( int $user_id ): object|false {
	return $user_id > 0 ? (object) array( 'ID' => $user_id ) : false;
}

function user_can( int $user_id, string $capability ): bool {
	if ( 'manage_options' === $capability ) {
		return false;
	}
	return ! empty( $GLOBALS['supc_test_capabilities'][ $user_id ][ $capability ] );
}

function smc_user_status( int $user_id ): string {
	return $GLOBALS['supc_test_statuses'][ $user_id ] ?? 'draft';
}

function get_option( string $key, mixed $default = false ): mixed {
	return $GLOBALS['supc_test_options'][ $key ] ?? $default;
}

require_once dirname( __DIR__ ) . '/includes/contracts/interface-adapter.php';
require_once dirname( __DIR__ ) . '/includes/core/class-safe-mode.php';
require_once dirname( __DIR__ ) . '/includes/core/class-permission-resolver.php';
require_once dirname( __DIR__ ) . '/includes/core/class-registry.php';
