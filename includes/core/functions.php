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

( static function (): void {
	$public_api_functions = array(
		'supc_register_adapter',
		'supc_unregister_adapter',
		'supc_adapter_available',
		'supc_adapter_matches',
		'supc_workflow_schema',
		'supc_workflow_create_draft',
		'supc_workflow_validate',
		'supc_workflow_preview',
		'supc_workflow_submit',
		'supc_workflow_status',
		'supc_workflow_canonical_url',
		'supc_generate_idempotency_key',
	);
	$public_api_markers = array(
		'SUPC_PUBLIC_API_VERSION',
		'SUPC_PUBLIC_API_OWNER',
		'SUPC_PUBLIC_API_FUNCTIONS_OWNED',
		'SUPC_PUBLIC_API_COLLISIONS',
	);
	$public_api_function_collisions = array_values( array_filter( $public_api_functions, 'function_exists' ) );
	$public_api_marker_collisions   = array_values( array_filter( $public_api_markers, 'defined' ) );
	$public_api_collisions          = array_merge( $public_api_function_collisions, $public_api_marker_collisions );

	if ( array() === $public_api_collisions ) {
		define( 'SUPC_PUBLIC_API_VERSION', '1.0.0' );
		define( 'SUPC_PUBLIC_API_OWNER', 'sabri-universal-post-composer' );
		define( 'SUPC_PUBLIC_API_FUNCTIONS_OWNED', true );
		define( 'SUPC_PUBLIC_API_COLLISIONS', '' );

		/**
		 * @return true|\WP_Error
		 */
		function supc_register_adapter( Adapter $adapter ) {
			return Plugin::instance()->registry()->register( $adapter );
		}

		function supc_unregister_adapter( string $key ): bool {
			return Plugin::instance()->registry()->unregister( $key );
		}

		/**
		 * Read-only current-subject availability query.
		 *
		 * The optional second parameter remains only as a backward-compatible call
		 * shape. It is never trusted as an authorization subject.
		 */
		function supc_adapter_available( string $key, int $deprecated_user_id = 0 ): bool {
			$user_id = get_current_user_id();
			if ( $deprecated_user_id > 0 && $deprecated_user_id !== $user_id ) {
				do_action( 'supc_deprecated_subject_argument_ignored', 'supc_adapter_available' );
			}
			try {
				return isset( Plugin::instance()->registry()->available_for_user( $user_id )[ $key ] );
			} catch ( \Throwable $error ) {
				unset( $error );
				return false;
			}
		}

		function supc_adapter_matches( string $key, string $native_module ): bool {
			try {
				$user_id  = get_current_user_id();
				$registry = Plugin::instance()->registry();
				$adapter  = $registry->get( $key );
				$contract = $registry->adapter_contract( $key );
				return $user_id > 0
					&& null !== $adapter
					&& null !== $contract
					&& $contract['native_module'] === $native_module
					&& isset( $registry->available_for_user( $user_id )[ $key ] );
			} catch ( \Throwable $error ) {
				unset( $error );
				return false;
			}
		}

		/**
		 * @return array<string, mixed>|\WP_Error
		 */
		function supc_workflow_schema( string $adapter_key ): array|\WP_Error {
			return Plugin::instance()->workflow_coordinator()->schema( get_current_user_id(), $adapter_key );
		}

		/**
		 * @param array<string, mixed> $payload Draft payload.
		 * @return array<string, mixed>|\WP_Error
		 */
		function supc_workflow_create_draft( string $adapter_key, ?string $native_reference, array $payload ): array|\WP_Error {
			return Plugin::instance()->workflow_coordinator()->create_draft( get_current_user_id(), $adapter_key, $native_reference, $payload );
		}

		/**
		 * @param array<string, mixed> $payload Draft payload.
		 * @return array<string, mixed>|\WP_Error
		 */
		function supc_workflow_validate( string $adapter_key, array $payload ): array|\WP_Error {
			return Plugin::instance()->workflow_coordinator()->validate( get_current_user_id(), $adapter_key, $payload );
		}

		/**
		 * @param array<string, mixed> $payload Draft payload.
		 * @return array<string, mixed>|\WP_Error
		 */
		function supc_workflow_preview( string $adapter_key, array $payload ): array|\WP_Error {
			return Plugin::instance()->workflow_coordinator()->preview( get_current_user_id(), $adapter_key, $payload );
		}

		/**
		 * @param array<string, mixed> $payload Final payload.
		 * @return array<string, mixed>|\WP_Error
		 */
		function supc_workflow_submit( string $adapter_key, string $idempotency_key, array $payload ): array|\WP_Error {
			return Plugin::instance()->workflow_coordinator()->submit( get_current_user_id(), $adapter_key, $idempotency_key, $payload );
		}

		/**
		 * @return array<string, mixed>|\WP_Error
		 */
		function supc_workflow_status( string $adapter_key, string $native_reference ): array|\WP_Error {
			return Plugin::instance()->workflow_coordinator()->status( get_current_user_id(), $adapter_key, $native_reference );
		}

		/**
		 * @return string|\WP_Error
		 */
		function supc_workflow_canonical_url( string $adapter_key, string $native_reference ): string|\WP_Error {
			return Plugin::instance()->workflow_coordinator()->canonical_url( get_current_user_id(), $adapter_key, $native_reference );
		}

		function supc_generate_idempotency_key(): string {
			return Plugin::instance()->workflow_coordinator()->generate_idempotency_key();
		}
		return;
	}

	if ( ! defined( 'SUPC_PUBLIC_API_VERSION' ) ) {
		define( 'SUPC_PUBLIC_API_VERSION', '0.0.0' );
	}
	if ( ! defined( 'SUPC_PUBLIC_API_OWNER' ) ) {
		define( 'SUPC_PUBLIC_API_OWNER', 'unclaimed' );
	}
	if ( ! defined( 'SUPC_PUBLIC_API_FUNCTIONS_OWNED' ) ) {
		define( 'SUPC_PUBLIC_API_FUNCTIONS_OWNED', false );
	}
	if ( ! defined( 'SUPC_PUBLIC_API_COLLISIONS' ) ) {
		define( 'SUPC_PUBLIC_API_COLLISIONS', implode( ',', $public_api_collisions ) );
	}
} )();

if ( ! defined( 'SUPC_FILE23_BRIDGE_VERSION' ) ) {
	define( 'SUPC_FILE23_BRIDGE_VERSION', '1.0.0-rc.2' );
}
require_once SUPC_PATH . 'includes/integration/class-file23-dashboard-bridge.php';
$bridge_class = 'Sabri\\UniversalComposer\\Integration\\File23_Dashboard_Bridge';
if ( class_exists( $bridge_class ) ) {
	$bridge = new $bridge_class();
	if ( is_callable( array( $bridge, 'register' ) ) ) {
		$bridge->register();
	}
}
