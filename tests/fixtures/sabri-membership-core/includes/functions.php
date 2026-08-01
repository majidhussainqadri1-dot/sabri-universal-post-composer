<?php
/**
 * Canonical File 00 callback fixtures.
 */

declare(strict_types=1);

function smc_user_status( int $user_id ): string {
	return $GLOBALS['supc_test_statuses'][ $user_id ] ?? 'draft';
}

function smc_application( int $user_id ): mixed {
	return $GLOBALS['supc_test_membership_applications'][ $user_id ] ?? null;
}

function smc_is_founder( int $user_id ): bool {
	return ! empty( $GLOBALS['supc_test_founders'][ $user_id ] );
}
