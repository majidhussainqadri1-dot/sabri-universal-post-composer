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
		if ( self::hard_disabled() || self::shell_disabled() ) {
			return true;
		}
		if ( ! class_exists( Migration_Manager::class, false ) ) {
			// In the complete plugin runtime a missing migration authority is a
			// bootstrap failure and must block writes. Isolated collision tests
			// intentionally load only the trust boundary and remain evaluable.
			return defined( 'SUPC_FILE' );
		}
		return ! Migration_Manager::writes_enabled();
	}

	/**
	 * Read-only draft/session recovery remains available during an intentional
	 * File 22 feature pause or an owned File 20 Safe Mode. It is never allowed
	 * through emergency disable, foreign symbol ownership or incoherent runtime
	 * provenance.
	 */
	public static function read_only_recovery_allowed(): bool {
		return ! self::hard_disabled();
	}

	private static function hard_disabled(): bool {
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
		// contract, but any foreign, partial, or incoherent package/contract
		// claim is a hard trust failure and may not expose read recovery.
		if ( Runtime_Trust::shell_package_claimed() && ! Runtime_Trust::shell_package_owned() ) {
			return true;
		}
		return Runtime_Trust::shell_create_contract_claimed() && ! Runtime_Trust::shell_create_contract_owned();
	}

	private static function shell_disabled(): bool {
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
