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

		if (
			class_exists( '\\Sabri\\UnifiedShell\\SafeMode' ) &&
			is_callable( array( '\\Sabri\\UnifiedShell\\SafeMode', 'disabled' ) )
		) {
			return (bool) \Sabri\UnifiedShell\SafeMode::disabled();
		}

		return false;
	}
}
