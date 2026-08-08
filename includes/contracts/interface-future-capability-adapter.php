<?php
/**
 * Optional future-intelligence capability bridge.
 *
 * File 22 remains an orchestration facade. Implementers keep canonical data,
 * AI truth, moderation truth, media bytes, annotations and publication state
 * inside their native owning modules.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

namespace Sabri\UniversalComposer\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface Future_Capability_Adapter {
	/**
	 * Return capability codes supported for the current authorized subject.
	 *
	 * @return array<int,string>
	 */
	public function future_capabilities( int $user_id ): array;

	/**
	 * Invoke one capability without transferring permanent ownership to File 22.
	 * File 22 must not persist the request or response body.
	 *
	 * For stateful capabilities, File 22 reserves the top-level `_supc_context`
	 * payload key. The REST bridge rejects any client-supplied value for that key
	 * and injects an owned, adapter-matching server context immediately before
	 * provider dispatch. Current reserved context fields are bounded metadata:
	 * session_uuid, adapter_key, native_reference, sensitivity_class,
	 * lock_version and correlation_id. Providers MUST treat that context as an
	 * orchestration pointer only, MUST re-authorize native mutations, and MUST
	 * never turn it into a second canonical draft/publication/review store.
	 *
	 * AI/terminology/derivative implementers remain subject to the owning File 16
	 * and privacy contracts: advisory output only, human review required, no
	 * autonomous diagnosis/prescription/potency/dosage/emergency replacement,
	 * no fabricated references, and no unauthorized patient-identifying egress.
	 *
	 * @param array<string,mixed> $payload Ephemeral, bounded invocation payload.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function invoke_future_capability( int $user_id, string $capability, array $payload ): array|\WP_Error;
}
