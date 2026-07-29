<?php
/**
 * Public server-side integration functions.
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

if ( ! function_exists( 'supc_adapter_available' ) ) {
	function supc_adapter_available( string $key, int $user_id = 0 ): bool {
		$user_id = $user_id > 0 ? $user_id : get_current_user_id();
		try {
			return isset( Plugin::instance()->registry()->available_for_user( $user_id )[ $key ] );
		} catch ( \Throwable $error ) {
			unset( $error );
			return false;
		}
	}
}

if ( ! function_exists( 'supc_adapter_matches' ) ) {
	function supc_adapter_matches( string $key, string $native_module ): bool {
		try {
			$adapter = Plugin::instance()->registry()->get( $key );
			return null !== $adapter && $adapter->native_module() === $native_module;
		} catch ( \Throwable $error ) {
			unset( $error );
			return false;
		}
	}
}

if ( ! function_exists( 'supc_workflow_schema' ) ) {
	/** @return array<string,mixed>|\WP_Error */
	function supc_workflow_schema( string $adapter_key ): array|\WP_Error {
		return Plugin::instance()->workflow_coordinator()->schema( get_current_user_id(), $adapter_key );
	}
}

if ( ! function_exists( 'supc_workflow_create_draft' ) ) {
	/** @param array<string,mixed> $payload @return array<string,mixed>|\WP_Error */
	function supc_workflow_create_draft( string $adapter_key, ?string $native_reference, array $payload ): array|\WP_Error {
		return Plugin::instance()->workflow_coordinator()->create_draft( get_current_user_id(), $adapter_key, $native_reference, $payload );
	}
}

if ( ! function_exists( 'supc_workflow_validate' ) ) {
	/** @param array<string,mixed> $payload @return array<string,mixed>|\WP_Error */
	function supc_workflow_validate( string $adapter_key, array $payload ): array|\WP_Error {
		return Plugin::instance()->workflow_coordinator()->validate( get_current_user_id(), $adapter_key, $payload );
	}
}

if ( ! function_exists( 'supc_workflow_preview' ) ) {
	/** @param array<string,mixed> $payload @return array<string,mixed>|\WP_Error */
	function supc_workflow_preview( string $adapter_key, array $payload ): array|\WP_Error {
		return Plugin::instance()->workflow_coordinator()->preview( get_current_user_id(), $adapter_key, $payload );
	}
}

if ( ! function_exists( 'supc_workflow_submit' ) ) {
	/** @param array<string,mixed> $payload @return array<string,mixed>|\WP_Error */
	function supc_workflow_submit( string $adapter_key, string $idempotency_key, array $payload ): array|\WP_Error {
		return Plugin::instance()->workflow_coordinator()->submit( get_current_user_id(), $adapter_key, $idempotency_key, $payload );
	}
}

if ( ! function_exists( 'supc_workflow_status' ) ) {
	/** @return array<string,mixed>|\WP_Error */
	function supc_workflow_status( string $adapter_key, string $native_reference ): array|\WP_Error {
		return Plugin::instance()->workflow_coordinator()->status( get_current_user_id(), $adapter_key, $native_reference );
	}
}

if ( ! function_exists( 'supc_workflow_canonical_url' ) ) {
	/** @return string|\WP_Error */
	function supc_workflow_canonical_url( string $adapter_key, string $native_reference ): string|\WP_Error {
		return Plugin::instance()->workflow_coordinator()->canonical_url( get_current_user_id(), $adapter_key, $native_reference );
	}
}

if ( ! function_exists( 'supc_generate_idempotency_key' ) ) {
	function supc_generate_idempotency_key(): string {
		return Plugin::instance()->workflow_coordinator()->generate_idempotency_key();
	}
}
