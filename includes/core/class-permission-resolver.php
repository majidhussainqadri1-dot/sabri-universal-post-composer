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
	private const CORE_DIRECTORY  = 'sabri-membership-core';
	private const CORE_FILE       = 'sabri-membership-core.php';

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

			$reflection    = new \ReflectionFunction( self::STATUS_CALLBACK );
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
