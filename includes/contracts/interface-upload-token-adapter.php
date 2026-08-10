<?php
/**
 * Optional native upload-token extension.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

namespace Sabri\UniversalComposer\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * File 22 never owns bytes. Native owners may issue opaque upload references
 * and upload destinations through this interface.
 */
interface Upload_Token_Adapter {
	/**
	 * @param array<string,mixed> $metadata Bounded mime/size/checksum metadata only.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function begin_upload( int $user_id, string $purpose, array $metadata );

	/**
	 * @return array<string,mixed>|\WP_Error
	 */
	public function complete_upload( int $user_id, string $upload_reference );

	/**
	 * @return bool|\WP_Error
	 */
	public function cancel_upload( int $user_id, string $upload_reference );
}
