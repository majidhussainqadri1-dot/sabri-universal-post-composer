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
	 * @param array<string,mixed> $payload Ephemeral, bounded invocation payload.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function invoke_future_capability( int $user_id, string $capability, array $payload ): array|\WP_Error;
}
