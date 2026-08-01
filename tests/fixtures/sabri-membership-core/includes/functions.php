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

	$status             = $GLOBALS['supc_test_statuses'][ $user_id ] ?? 'draft';
	$application_exists = array_key_exists( $user_id, $GLOBALS['supc_test_membership_applications'] ?? array() )
		&& null !== $GLOBALS['supc_test_membership_applications'][ $user_id ];

	/*
	 * Historical tests set status directly and do not model File 00 rows. Keep
	 * that established fixture contract unless a test supplies a complete
	 * explicit state above. This prevents test-only Administrator authority from
	 * changing unrelated workflow and registry expectations.
	 */
	return array(
		'contract_version'      => SMC_CONTRACT_VERSION,
		'application_exists'    => $application_exists,
		'application_status'    => $application_exists ? $status : '',
		'status'                => $status,
		'institutional_account' => false,
		'account_class'         => 'member',
		'approved'              => in_array( $status, array( 'approved', 'verified' ), true ),
	);
}

function smc_application( int $user_id ): mixed {
	return $GLOBALS['supc_test_membership_applications'][ $user_id ] ?? null;
}

function smc_is_founder( int $user_id ): bool {
	return ! empty( $GLOBALS['supc_test_founders'][ $user_id ] );
}
