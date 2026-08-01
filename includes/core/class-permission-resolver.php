<?php
/**
 * Central Membership Core permission resolver.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

namespace Sabri\UniversalComposer\Core;

use Sabri\UniversalComposer\Contracts\Adapter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Permission_Resolver {
	private const STATUS_CALLBACK      = 'smc_user_status';
	private const STATE_CALLBACK       = 'smc_membership_state';
	private const APPLICATION_CALLBACK = 'smc_application';
	private const FOUNDER_CALLBACK     = 'smc_is_founder';
	private const CORE_DIRECTORY       = 'sabri-membership-core';
	private const CORE_FILE            = 'sabri-membership-core.php';
	private const HARD_BLOCK_STATUSES  = array( 'rejected', 'suspended', 'appeal_review', 'erasure_pending' );

	public function core_available(): bool {
		if (
			! defined( 'SMC_VERSION' ) ||
			! defined( 'SMC_DB_VERSION' ) ||
			! defined( 'SMC_FILE' ) ||
			! defined( 'SMC_PATH' ) ||
			! Version::at_least( (string) SMC_VERSION, SUPC_MIN_SMC_VERSION ) ||
			! Version::at_least( (string) SMC_DB_VERSION, SUPC_MIN_SMC_VERSION ) ||
			! function_exists( self::STATUS_CALLBACK )
		) {
			return false;
		}

		try {
			$smc_file = realpath( (string) SMC_FILE );
			$smc_path = realpath( (string) SMC_PATH );
			if (
				false === $smc_file ||
				false === $smc_path ||
				dirname( $smc_file ) !== $smc_path ||
				self::CORE_FILE !== basename( $smc_file ) ||
				self::CORE_DIRECTORY !== basename( $smc_path )
			) {
				return false;
			}

			return $this->callback_owned_by_core( self::STATUS_CALLBACK, $smc_file, $smc_path );
		} catch ( \Throwable $error ) {
			unset( $error );
			return false;
		}
	}

	public function account_is_eligible( int $user_id ): bool {
		$report = $this->eligibility_report( $user_id );
		return true === $report['eligible'];
	}

	/**
	 * Return a privacy-safe, bounded explanation of the central account decision.
	 * No name, email, phone, application payload, or clinical data is exposed.
	 *
	 * @return array{eligible:bool,reason:string,status:string,application_status:string,application_exists:bool,institutional_account:bool,approved:bool}
	 */
	public function eligibility_report( int $user_id ): array {
		$report = array(
			'eligible'              => false,
			'reason'                => 'membership_account_not_eligible',
			'status'                => '',
			'application_status'    => '',
			'application_exists'    => false,
			'institutional_account' => false,
			'approved'              => false,
		);

		if ( $user_id <= 0 ) {
			$report['reason'] = 'authorization_subject_missing';
			return $report;
		}
		if ( Safe_Mode::disabled() ) {
			$report['reason'] = 'supc_safe_mode_active';
			return $report;
		}
		if ( ! $this->core_available() ) {
			$report['reason'] = 'membership_core_unavailable';
			return $report;
		}

		try {
			$user = get_userdata( $user_id );
			if ( ! $user ) {
				$report['reason'] = 'authorization_subject_missing';
				return $report;
			}

			if ( $this->trusted_optional_callback_available( self::STATE_CALLBACK ) ) {
				$state = call_user_func( self::STATE_CALLBACK, $user_id );
				if ( is_array( $state ) ) {
					$status             = isset( $state['status'] ) && is_string( $state['status'] ) ? sanitize_key( $state['status'] ) : '';
					$application_status = isset( $state['application_status'] ) && is_string( $state['application_status'] ) ? sanitize_key( $state['application_status'] ) : $status;
					$application_exists = ! empty( $state['application_exists'] );
					$institutional      = ! empty( $state['institutional_account'] );
					$approved           = true === (bool) ( $state['approved'] ?? false );

					$report['status']                = $status;
					$report['application_status']    = $application_status;
					$report['application_exists']    = $application_exists;
					$report['institutional_account'] = $institutional;
					$report['approved']              = $approved;

					if ( in_array( $status, self::HARD_BLOCK_STATUSES, true ) || in_array( $application_status, self::HARD_BLOCK_STATUSES, true ) ) {
						$report['reason'] = 'membership_hard_block';
						return $report;
					}

					if ( $approved && ( $institutional || in_array( $status, array( 'approved', 'verified' ), true ) ) ) {
						$report['eligible'] = true;
						$report['reason']   = 'current_user_authorized';
						return $report;
					}

					$report['reason'] = $application_exists ? 'membership_application_blocking' : 'membership_account_not_eligible';
					return $report;
				}
			}

			$status                      = (string) call_user_func( self::STATUS_CALLBACK, $user_id );
			$report['status']            = sanitize_key( $status );
			$report['application_status'] = $report['status'];
		} catch ( \Throwable $error ) {
			unset( $error );
			$report['reason'] = 'membership_contract_exception';
			return $report;
		}

		if ( in_array( $report['status'], self::HARD_BLOCK_STATUSES, true ) ) {
			$report['reason'] = 'membership_hard_block';
			return $report;
		}
		if ( in_array( $report['status'], array( 'approved', 'verified' ), true ) ) {
			$report['eligible'] = true;
			$report['approved'] = true;
			$report['reason']   = 'current_user_authorized';
			return $report;
		}

		/* Compatibility path for File 00 releases before the explicit state API. */
		if ( 'draft' === $report['status'] && $this->has_no_membership_application( $user_id ) ) {
			$report['application_status'] = '';
			if ( $this->is_canonical_founder( $user_id ) ) {
				$report['eligible']              = true;
				$report['approved']              = true;
				$report['institutional_account'] = true;
				$report['reason']                = 'current_user_authorized';
				return $report;
			}
			try {
				if ( function_exists( 'user_can' ) && user_can( $user_id, 'manage_options' ) ) {
					$report['eligible']              = true;
					$report['approved']              = true;
					$report['institutional_account'] = true;
					$report['reason']                = 'current_user_authorized';
					return $report;
				}
			} catch ( \Throwable $error ) {
				unset( $error );
			}
		}

		$report['reason'] = $this->has_no_membership_application( $user_id ) ? 'membership_account_not_eligible' : 'membership_application_blocking';
		return $report;
	}

	public function can_use_capability( int $user_id, string $capability ): bool {
		if ( ! $this->account_is_eligible( $user_id ) ) {
			return false;
		}

		$capability = trim( $capability );
		if ( '' === $capability || sanitize_key( $capability ) !== $capability ) {
			return false;
		}

		try {
			return user_can( $user_id, $capability );
		} catch ( \Throwable $error ) {
			unset( $error );
			return false;
		}
	}

	/**
	 * Resolve an adapter-specific decision only after the caller supplies the
	 * immutable registration-time capability. Live adapter metadata is never an
	 * authorization source after registration.
	 */
	public function can_use_adapter( int $user_id, Adapter $adapter, string $registered_capability ): bool {
		if ( ! $this->can_use_capability( $user_id, $registered_capability ) ) {
			return false;
		}

		try {
			return $adapter->can_create( $user_id );
		} catch ( \Throwable $error ) {
			unset( $error );
			return false;
		}
	}

	private function has_no_membership_application( int $user_id ): bool {
		if ( ! $this->trusted_optional_callback_available( self::APPLICATION_CALLBACK ) ) {
			return false;
		}

		try {
			$application = call_user_func( self::APPLICATION_CALLBACK, $user_id );
			return null === $application || false === $application || array() === $application;
		} catch ( \Throwable $error ) {
			unset( $error );
			return false;
		}
	}

	private function is_canonical_founder( int $user_id ): bool {
		if ( ! $this->trusted_optional_callback_available( self::FOUNDER_CALLBACK ) ) {
			return false;
		}

		try {
			return true === (bool) call_user_func( self::FOUNDER_CALLBACK, $user_id );
		} catch ( \Throwable $error ) {
			unset( $error );
			return false;
		}
	}

	private function trusted_optional_callback_available( string $callback ): bool {
		if ( ! function_exists( $callback ) || ! defined( 'SMC_FILE' ) || ! defined( 'SMC_PATH' ) ) {
			return false;
		}

		$smc_file = realpath( (string) SMC_FILE );
		$smc_path = realpath( (string) SMC_PATH );
		if ( false === $smc_file || false === $smc_path ) {
			return false;
		}

		return $this->callback_owned_by_core( $callback, $smc_file, $smc_path );
	}

	private function callback_owned_by_core( string $callback, string $smc_file, string $smc_path ): bool {
		try {
			$reflection    = new \ReflectionFunction( $callback );
			$callback_file = $reflection->getFileName();
			$callback_file = is_string( $callback_file ) ? realpath( $callback_file ) : false;
			if ( false === $callback_file ) {
				return false;
			}

			$owned_prefix = rtrim( $smc_path, '/\\' ) . DIRECTORY_SEPARATOR;
			return $callback_file === $smc_file || str_starts_with( $callback_file, $owned_prefix );
		} catch ( \Throwable $error ) {
			unset( $error );
			return false;
		}
	}
}
