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

		// File 22 may not continue as an internal Composer while its declared public
		// integration API is partial, colliding, or executable from a foreign source.
		if (
			Runtime_Trust::public_api_claimed() &&
			(
				! defined( 'SUPC_PATH' ) ||
				! Runtime_Trust::public_api_owned( SUPC_PATH . 'includes/core/functions.php' )
			)
		) {
			return true;
		}

		if ( ! Runtime_Trust::shell_claimed() ) {
			return false;
		}

		$callback      = Runtime_Trust::owned_shell_static_method( self::SHELL_SAFE_MODE_CLASS, 'disabled' );
		$trusted_shell = defined( 'SABRI_SHELL_CREATE_CONTRACT_VERSION' )
			&& '1.0.1' === (string) SABRI_SHELL_CREATE_CONTRACT_VERSION
			&& defined( 'SABRI_SHELL_CREATE_CONTRACT_OWNER' )
			&& 'sabri-unified-application-shell' === (string) SABRI_SHELL_CREATE_CONTRACT_OWNER
			&& defined( 'SABRI_SHELL_CREATE_FUNCTIONS_OWNED' )
			&& true === SABRI_SHELL_CREATE_FUNCTIONS_OWNED
			&& null !== $callback;

		// Historical evidence marker: Runtime_Trust::shell_symbols_owned established
		// package provenance in the ninth cycle; owned_shell_static_method now also
		// proves that the executable method itself is declared inside that package.
		// A colliding, incomplete, obsolete, inherited, or foreign-source shell
		// runtime must never be trusted to clear the platform emergency boundary.
		if ( ! $trusted_shell ) {
			return true;
		}

		try {
			return (bool) $callback();
		} catch ( \Throwable $error ) {
			unset( $error );
			return true;
		}
	}
}
