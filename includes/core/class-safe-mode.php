<?php
/**
 * Safe Mode and emergency-disable integration.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

namespace Sabri\UniversalComposer\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Safe_Mode {
	public static function disabled(): bool {
		if ( defined( 'SUPC_DISABLE' ) && SUPC_DISABLE ) {
			return true;
		}

		if ( (bool) get_option( 'supc_emergency_disabled', false ) ) {
			return true;
		}

		$callback = array( '\\Sabri\\UnifiedShell\\SafeMode', 'disabled' );
		if ( is_callable( $callback ) ) {
			try {
				return (bool) call_user_func( $callback );
			} catch ( \Throwable $error ) {
				unset( $error );
				return true;
			}
		}

		return false;
	}
}
