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
	private const STATUS_CALLBACK = 'smc_user_status';

	public function core_available(): bool {
		if ( ! defined( 'SMC_VERSION' ) ) {
			return false;
		}

		if ( version_compare( (string) SMC_VERSION, SUPC_MIN_SMC_VERSION, '<' ) ) {
			return false;
		}

		return (bool) call_user_func( 'function_exists', self::STATUS_CALLBACK );
	}

	public function account_is_eligible( int $user_id ): bool {
		if ( $user_id <= 0 || Safe_Mode::disabled() || ! $this->core_available() ) {
			return false;
		}

		try {
			$user = get_userdata( $user_id );
			if ( ! $user ) {
				return false;
			}

			$status = (string) call_user_func( self::STATUS_CALLBACK, $user_id );
		} catch ( \Throwable $error ) {
			unset( $error );
			return false;
		}

		// A WordPress role or capability may narrow an approved account later,
		// but it must never expand a pending, rejected, suspended, expired, or
		// otherwise unknown Membership Core state.
		return in_array( $status, array( 'approved', 'verified' ), true );
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
}
