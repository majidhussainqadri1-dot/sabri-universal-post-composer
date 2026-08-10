<?php
/**
 * Optional native revision extension.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

namespace Sabri\UniversalComposer\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Native owners implement revision submission without File 22 duplicating the
 * permanent revision ledger or editorial truth.
 */
interface Revision_Adapter {
	/**
	 * @param array<string,mixed> $payload Validated revision payload.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function submit_revision( int $user_id, string $native_reference, string $idempotency_key, array $payload );
}
