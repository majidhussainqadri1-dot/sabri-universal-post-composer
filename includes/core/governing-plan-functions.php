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

/*
 * The later central plan makes Sabri Green the primary brand color while File
 * 25 remains the canonical token owner. Load only a tiny fallback override;
 * it does not create a second design system and selectors are File-22 scoped.
 */
add_action(
	'wp_enqueue_scripts',
	static function (): void {
		wp_enqueue_style(
			'supc-governing-plan-brand',
			SUPC_URL . 'assets/css/governing-plan-brand.css',
			array(),
			SUPC_VERSION
		);
	},
	100
);

/*
 * Compatibility event from the plan-complete runtime is explicitly forwarded
 * to File 26, the later central plan's canonical Search/Discovery/Ranking
 * owner. File 22 never writes an index, ranking record, or search database.
 */
add_action(
	'supc_search_seo_event',
	static function ( string $event, array $metadata ): void {
		do_action( 'supc_file26_search_projection_event', $event, $metadata );
	},
	10,
	2
);
