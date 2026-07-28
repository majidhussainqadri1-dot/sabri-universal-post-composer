<?php
/**
 * Public File 22 integration functions.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

use Sabri\UniversalComposer\Contracts\Adapter;
use Sabri\UniversalComposer\Core\Plugin;

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
