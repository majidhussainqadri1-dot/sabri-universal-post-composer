<?php
/**
 * Optional native draft lifecycle extension.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

namespace Sabri\UniversalComposer\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Native owners implement this when File 22 may safely discard an owned draft.
 */
interface Draft_Lifecycle_Adapter {
	/**
	 * Permanently discard or safely archive the exact native draft.
	 *
	 * @return bool|\WP_Error
	 */
	public function discard_draft( int $user_id, string $native_reference );
}
