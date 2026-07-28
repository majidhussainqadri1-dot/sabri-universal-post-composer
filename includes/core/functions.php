<?php
/**
 * Public File 22 integration functions.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

use Sabri\UniversalComposer\Contracts\Adapter;
use Sabri\UniversalComposer\Core\Plugin;
use Throwable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'supc_register_adapter' ) ) {
	/**
	 * Register an adapter at any point after File 22 has loaded.
	 *
	 * @return true|\WP_Error
	 */
	function supc_register_adapter( Adapter $adapter ) {
		return Plugin::instance()->registry()->register( $adapter );
	}
}

if ( ! function_exists( 'supc_unregister_adapter' ) ) {
	function supc_unregister_adapter( string $key ): bool {
		return Plugin::instance()->registry()->unregister( $key );
	}
}

if ( ! function_exists( 'supc_adapter_matches' ) ) {
	/**
	 * Confirm that a canonical adapter key belongs to the expected native owner
	 * and is presently available. This function is intentionally read-only and
	 * exposes no draft, user, patient, or publication data.
	 */
	function supc_adapter_matches( string $key, string $native_module ): bool {
		try {
			$adapter = Plugin::instance()->registry()->get( $key );
			return $adapter instanceof Adapter
				&& $key === $adapter->key()
				&& $native_module === $adapter->native_module()
				&& $adapter->is_available();
		} catch ( Throwable $error ) {
			do_action( 'supc_adapter_match_error', $key, get_class( $error ) );
			return false;
		}
	}
}
