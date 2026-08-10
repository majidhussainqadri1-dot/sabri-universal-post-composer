<?php
/**
 * Public-safe File 22 governing-plan API helpers.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

use Sabri\UniversalComposer\Core\Governing_Plan_Runtime;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'supc_adapter_governance' ) ) {
	/**
	 * Read the normalized governance contract for the current authenticated subject.
	 * Caller-supplied user IDs are deliberately unsupported to avoid confused-deputy use.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	function supc_adapter_governance( string $adapter_key ) {
		return Governing_Plan_Runtime::instance()->governance_profile( get_current_user_id(), $adapter_key );
	}
}

if ( ! function_exists( 'supc_lifecycle_capabilities' ) ) {
	/**
	 * Read lifecycle commands for the current authenticated subject only.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	function supc_lifecycle_capabilities( string $adapter_key, string $native_reference ) {
		return Governing_Plan_Runtime::instance()->lifecycle_capabilities(
			get_current_user_id(),
			$adapter_key,
			$native_reference
		);
	}
}

if ( ! function_exists( 'supc_execute_lifecycle' ) ) {
	/**
	 * Execute one correction/revision/scheduling command for the current subject
	 * through the native owner. File 22 never accepts an arbitrary authorization subject.
	 *
	 * @param array<string, mixed> $payload Native command payload.
	 * @return array<string, mixed>|WP_Error
	 */
	function supc_execute_lifecycle(
		string $adapter_key,
		string $native_reference,
		string $command,
		string $idempotency_key,
		array $payload = array()
	) {
		return Governing_Plan_Runtime::instance()->execute_lifecycle(
			get_current_user_id(),
			$adapter_key,
			$native_reference,
			$command,
			$idempotency_key,
			$payload
		);
	}
}
