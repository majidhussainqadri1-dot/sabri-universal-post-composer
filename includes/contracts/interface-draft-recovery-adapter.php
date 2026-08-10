<?php
/**
 * Optional native draft-recovery contract.
 *
 * File 22 never persists draft bodies. A native owner may implement this
 * read-only contract so a Composer session can safely repopulate its fields
 * after a reload or browser crash.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

namespace Sabri\UniversalComposer\Contracts;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface Draft_Recovery_Adapter {
	/**
	 * Return the current subject-owned native draft payload.
	 *
	 * The returned array must contain only fields declared by the adapter's
	 * current subject schema. File 22 validates it before browser exposure.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public function load_draft( int $user_id, string $native_reference ): array|WP_Error;
}
