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

		if (
			Runtime_Trust::public_api_claimed() &&
			(
				! defined( 'SUPC_PATH' ) ||
				! Runtime_Trust::public_api_owned( SUPC_PATH . 'includes/core/functions.php' )
			)
		) {
			return true;
		}

		// A canonical legacy File 20 package may predate the optional Create
		// contract, but a foreign or incoherent base package claim must never be
		// ignored merely because it did not also claim the later contract.
		if ( Runtime_Trust::shell_package_claimed() && ! Runtime_Trust::shell_package_owned() ) {
			return true;
		}

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
