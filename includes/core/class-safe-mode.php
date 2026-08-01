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
	private const SHELL_SAFE_MODE_CLASS = '\Sabri\UnifiedShell\SafeMode';

	public static function disabled(): bool {
		if ( defined( 'SUPC_DISABLE' ) && SUPC_DISABLE ) {
			return true;
		}

		if ( (bool) get_option( 'supc_emergency_disabled', false ) ) {
			return true;
		}

		if ( ! Runtime_Trust::shell_claimed() ) {
			return false;
		}

		$callback      = array( self::SHELL_SAFE_MODE_CLASS, 'disabled' );
		$trusted_shell = defined( 'SABRI_SHELL_CREATE_CONTRACT_VERSION' )
			&& '1.0.1' === (string) SABRI_SHELL_CREATE_CONTRACT_VERSION
			&& defined( 'SABRI_SHELL_CREATE_CONTRACT_OWNER' )
			&& 'sabri-unified-application-shell' === (string) SABRI_SHELL_CREATE_CONTRACT_OWNER
			&& defined( 'SABRI_SHELL_CREATE_FUNCTIONS_OWNED' )
			&& true === SABRI_SHELL_CREATE_FUNCTIONS_OWNED
			&& is_callable( $callback )
			&& Runtime_Trust::shell_symbols_owned( array(), self::SHELL_SAFE_MODE_CLASS );

		// A colliding, incomplete, obsolete, or foreign-source shell runtime must
		// never be trusted to clear the platform-wide emergency boundary.
		if ( ! $trusted_shell ) {
			return true;
		}

		try {
			return (bool) call_user_func( $callback );
		} catch ( \Throwable $error ) {
			unset( $error );
			return true;
		}
	}
}
