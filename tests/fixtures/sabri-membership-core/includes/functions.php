<?php
/**
 * Canonical File 00 callback fixtures.
 */

declare(strict_types=1);

if ( ! defined( 'SMC_CONTRACT_VERSION' ) ) {
	define( 'SMC_CONTRACT_VERSION', '1.1.1' );
}
if ( ! defined( 'SUPC_MIN_SMC_DB_VERSION' ) ) {
	define( 'SUPC_MIN_SMC_DB_VERSION', '1.0.1' );
}
if ( ! defined( 'SUPC_MIN_SMC_CONTRACT_VERSION' ) ) {
	define( 'SUPC_MIN_SMC_CONTRACT_VERSION', '1.1.1' );
}

function smc_user_status( int $user_id ): string {
	$state = smc_membership_state( $user_id );
	return (string) $state['status'];
}

/** @return array<string,mixed> */
function smc_membership_state( int $user_id ): array {
	if ( isset( $GLOBALS['supc_test_membership_states'][ $user_id ] ) ) {
		return $GLOBALS['supc_test_membership_states'][ $user_id ];
	}

	$status      = $GLOBALS['supc_test_statuses'][ $user_id ] ?? 'draft';
	$application = $GLOBALS['supc_test_membership_applications'][ $user_id ] ?? null;
	$founder     = ! empty( $GLOBALS['supc_test_founders'][ $user_id ] );
	$admin       = function_exists( 'user_can' ) && user_can( $user_id, 'manage_options' );
	$hard_block  = in_array( $status, array( 'rejected', 'suspended', 'appeal_review', 'erasure_pending' ), true );

	if ( $founder || $admin ) {
		return array(
			'contract_version'      => SMC_CONTRACT_VERSION,
			'application_exists'    => null !== $application,
			'application_status'    => null !== $application ? $status : '',
			'status'                => $hard_block ? $status : 'verified',
			'institutional_account' => true,
			'account_class'         => $founder ? 'founder' : 'administrator',
			'approved'              => ! $hard_block,
		);
	}

	return array(
		'contract_version'      => SMC_CONTRACT_VERSION,
		'application_exists'    => null !== $application,
		'application_status'    => null !== $application ? $status : '',
		'status'                => null !== $application ? $status : 'not_enrolled',
		'institutional_account' => false,
		'account_class'         => 'member',
		'approved'              => 'approved' === $status,
	);
}

function smc_application( int $user_id ): mixed {
	return $GLOBALS['supc_test_membership_applications'][ $user_id ] ?? null;
}

function smc_is_founder( int $user_id ): bool {
	return ! empty( $GLOBALS['supc_test_founders'][ $user_id ] );
}
