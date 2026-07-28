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

if ( ! function_exists( 'supc_workflow_schema' ) ) {
	/** @return array<string,mixed>|\WP_Error */
	function supc_workflow_schema( int $user_id, string $adapter_key ) {
		return Plugin::instance()->workflow_coordinator()->schema( $user_id, $adapter_key );
	}
}

if ( ! function_exists( 'supc_workflow_create_draft' ) ) {
	/** @param array<string,mixed> $payload @return array<string,mixed>|\WP_Error */
	function supc_workflow_create_draft( int $user_id, string $adapter_key, ?string $native_reference, array $payload ) {
		return Plugin::instance()->workflow_coordinator()->create_draft( $user_id, $adapter_key, $native_reference, $payload );
	}
}

if ( ! function_exists( 'supc_workflow_validate' ) ) {
	/** @param array<string,mixed> $payload @return array<string,mixed>|\WP_Error */
	function supc_workflow_validate( int $user_id, string $adapter_key, array $payload ) {
		return Plugin::instance()->workflow_coordinator()->validate( $user_id, $adapter_key, $payload );
	}
}

if ( ! function_exists( 'supc_workflow_preview' ) ) {
	/** @param array<string,mixed> $payload @return array<string,mixed>|\WP_Error */
	function supc_workflow_preview( int $user_id, string $adapter_key, array $payload ) {
		return Plugin::instance()->workflow_coordinator()->preview( $user_id, $adapter_key, $payload );
	}
}

if ( ! function_exists( 'supc_workflow_submit' ) ) {
	/** @param array<string,mixed> $payload @return array<string,mixed>|\WP_Error */
	function supc_workflow_submit( int $user_id, string $adapter_key, string $idempotency_key, array $payload ) {
		return Plugin::instance()->workflow_coordinator()->submit( $user_id, $adapter_key, $idempotency_key, $payload );
	}
}

if ( ! function_exists( 'supc_workflow_status' ) ) {
	/** @return array<string,mixed>|\WP_Error */
	function supc_workflow_status( int $user_id, string $adapter_key, string $native_reference ) {
		return Plugin::instance()->workflow_coordinator()->status( $user_id, $adapter_key, $native_reference );
	}
}

if ( ! function_exists( 'supc_workflow_canonical_url' ) ) {
	/** @return string|\WP_Error */
	function supc_workflow_canonical_url( int $user_id, string $adapter_key, string $native_reference ) {
		return Plugin::instance()->workflow_coordinator()->canonical_url( $user_id, $adapter_key, $native_reference );
	}
}

if ( ! function_exists( 'supc_generate_idempotency_key' ) ) {
	function supc_generate_idempotency_key(): string {
		return Plugin::instance()->workflow_coordinator()->generate_idempotency_key();
	}
}
