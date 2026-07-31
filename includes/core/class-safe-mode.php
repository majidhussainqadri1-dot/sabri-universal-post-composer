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
	private const SHELL_SAFE_MODE_CLASS = '\\Sabri\\UnifiedShell\\SafeMode';

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

		// File 20 version 1.0.0 is a real, canonical legacy package, but it predates
		// the optional Create contract. Its base constants alone must not disable the
		// Composer. Only a component that actually claims the atomic Create contract
		// may become a File 22 emergency-state authority.
		if ( ! Runtime_Trust::shell_create_contract_claimed() ) {
			return false;
		}

		$callback = Runtime_Trust::owned_shell_static_method( self::SHELL_SAFE_MODE_CLASS, 'disabled' );
		if ( ! Runtime_Trust::shell_create_contract_owned() || null === $callback ) {
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
