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
		$callback_exists = (bool) call_user_func( 'function_exists', self::STATUS_CALLBACK );

		return defined( 'SMC_VERSION' )
			&& version_compare( (string) SMC_VERSION, SUPC_MIN_SMC_VERSION, '>=' )
			&& $callback_exists;
	}

	public function account_is_eligible( int $user_id ): bool {
		if ( $user_id <= 0 || Safe_Mode::disabled() || ! $this->core_available() ) {
			return false;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		$status = (string) call_user_func( self::STATUS_CALLBACK, $user_id );
		if ( in_array( $status, array( 'rejected', 'suspended', 'expired_document' ), true ) ) {
			return false;
		}

		if ( user_can( $user_id, 'manage_options' ) ) {
			return true;
		}

		return in_array( $status, array( 'approved', 'verified' ), true );
	}

	public function can_use_adapter( int $user_id, Adapter $adapter ): bool {
		if ( ! $this->account_is_eligible( $user_id ) ) {
			return false;
		}

		$capability = trim( $adapter->required_capability() );
		if ( '' !== $capability && ! user_can( $user_id, $capability ) ) {
			return false;
		}

		return $adapter->can_create( $user_id );
	}
}
