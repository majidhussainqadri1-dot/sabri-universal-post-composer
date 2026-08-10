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
	 * Read the normalized governance contract for one adapter.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	function supc_adapter_governance( string $adapter_key, ?int $user_id = null ) {
		$subject = null === $user_id ? get_current_user_id() : $user_id;
		return Governing_Plan_Runtime::instance()->governance_profile( $subject, $adapter_key );
	}
}

if ( ! function_exists( 'supc_lifecycle_capabilities' ) ) {
	/**
	 * Read lifecycle commands currently authorized by the native owner.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	function supc_lifecycle_capabilities( string $adapter_key, string $native_reference, ?int $user_id = null ) {
		$subject = null === $user_id ? get_current_user_id() : $user_id;
		return Governing_Plan_Runtime::instance()->lifecycle_capabilities( $subject, $adapter_key, $native_reference );
	}
}

if ( ! function_exists( 'supc_execute_lifecycle' ) ) {
	/**
	 * Execute one correction/revision/scheduling command through the native owner.
	 *
	 * @param array<string, mixed> $payload Native command payload.
	 * @return array<string, mixed>|WP_Error
	 */
	function supc_execute_lifecycle(
		string $adapter_key,
		string $native_reference,
		string $command,
		string $idempotency_key,
		array $payload = array(),
		?int $user_id = null
	) {
		$subject = null === $user_id ? get_current_user_id() : $user_id;
		return Governing_Plan_Runtime::instance()->execute_lifecycle(
			$subject,
			$adapter_key,
			$native_reference,
			$command,
			$idempotency_key,
			$payload
		);
	}
}
