<?php
/**
 * Full native workflow orchestration contract.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

namespace Sabri\UniversalComposer\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Used when File 22 directly orchestrates native drafts and submission.
 */
interface Workflow_Adapter extends Adapter {
	public function workflow_api_version(): string;

	public function schema_version(): string;

	public function supports_native_drafts(): bool;

	/**
	 * @return array<string, mixed>
	 */
	public function schema(): array;

	/**
	 * @param string|null          $native_reference Existing native draft reference when resuming.
	 * @param array<string, mixed> $payload          Validated draft payload.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function create_draft( int $user_id, ?string $native_reference, array $payload );

	/**
	 * @param array<string, mixed> $payload Draft payload.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function validate( int $user_id, array $payload );

	/**
	 * @param array<string, mixed> $payload Draft payload.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function preview( int $user_id, array $payload );

	/**
	 * @param array<string, mixed> $payload Final payload.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function submit( int $user_id, string $idempotency_key, array $payload );

	/**
	 * @return array<string, mixed>|\WP_Error
	 */
	public function status( int $user_id, string $native_reference );

	/**
	 * Resolve a canonical URL only after native ownership/visibility checks for
	 * the authenticated subject.
	 */
	public function canonical_url( int $user_id, string $native_reference ): string;
}
