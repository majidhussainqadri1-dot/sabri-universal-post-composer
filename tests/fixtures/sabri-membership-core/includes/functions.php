<?php
/**
 * Canonical File 00 status callback fixture.
 */

declare(strict_types=1);

function smc_user_status( int $user_id ): string {
	return $GLOBALS['supc_test_statuses'][ $user_id ] ?? 'draft';
}
