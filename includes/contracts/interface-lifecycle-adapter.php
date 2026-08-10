<?php
/**
 * Native lifecycle command contract.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

namespace Sabri\UniversalComposer\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Optional extension for native owners that allow File 22 to orchestrate
 * edit/revision/correction/scheduling lifecycle commands. The native module
 * remains the only owner of state transitions and permanent content.
 */
interface Lifecycle_Adapter extends Governed_Workflow_Adapter {
	/**
	 * Exact File 22 lifecycle API version implemented by this adapter.
	 */
	public function lifecycle_api_version(): string;

	/**
	 * Return commands currently permitted for the authenticated subject and
	 * native object. Capability/state/ownership must be revalidated natively.
	 *
	 * @return array<int, string>|\WP_Error
	 */
	public function lifecycle_capabilities( int $user_id, string $native_reference );

	/**
	 * Execute one native lifecycle command idempotently.
	 *
	 * @param array<string, mixed> $payload Command payload owned and validated by the native module.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function execute_lifecycle(
		int $user_id,
		string $native_reference,
		string $command,
		string $idempotency_key,
		array $payload
	);
}
