<?php
/**
 * Canonical File 20 Create contract fixtures.
 */

declare(strict_types=1);

if ( ! function_exists( 'sabri_shell_create_contract_available' ) ) {
	function sabri_shell_create_contract_available(): bool {
		return true;
	}
}

if ( ! function_exists( 'sabri_shell_create_visible_for_current_user' ) ) {
	function sabri_shell_create_visible_for_current_user(): bool {
		return true;
	}
}
