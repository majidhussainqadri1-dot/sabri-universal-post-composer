<?php
/**
 * Fail-soft adapter registry.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

namespace Sabri\UniversalComposer\Core;

use Sabri\UniversalComposer\Contracts\Adapter;
use Throwable;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Registry {
	/** @var array<string, Adapter> */
	private array $adapters = array();

	/** @var array<int, array<string, Adapter>> */
	private array $available_cache = array();

	/** @var array<string, array<string, mixed>> */
	private array $errors = array();

	public function __construct( private Permission_Resolver $permissions ) {
	}

	/**
	 * @return true|WP_Error
	 */
	public function register( Adapter $adapter ) {
		try {
			$key = $adapter->key();
			if ( 1 !== preg_match( '/^[a-z][a-z0-9_]{2,63}$/', $key ) ) {
				return $this->registration_error( 'invalid_key', $key, 'Adapter key is not canonical.' );
			}

			if ( isset( $this->adapters[ $key ] ) ) {
				return $this->registration_error( 'duplicate_key', $key, 'Adapter key is already registered.' );
			}

			if ( SUPC_ADAPTER_API_VERSION !== $adapter->api_version() ) {
				return $this->registration_error( 'api_mismatch', $key, 'Adapter API version is incompatible.' );
			}

			$this->adapters[ $key ] = $adapter;
			$this->flush_cache();
			return true;
		} catch ( Throwable $error ) {
			$key = 'unknown_' . count( $this->errors );
			return $this->runtime_error( $key, 'registration_exception', $error );
		}
	}

	public function unregister( string $key ): bool {
		if ( ! isset( $this->adapters[ $key ] ) ) {
			return false;
		}

		unset( $this->adapters[ $key ] );
		$this->flush_cache();
		return true;
	}

	public function get( string $key ): ?Adapter {
		if ( 1 !== preg_match( '/^[a-z][a-z0-9_]{2,63}$/', $key ) ) {
			return null;
		}

		return $this->adapters[ $key ] ?? null;
	}

	/**
	 * @return array<string, Adapter>
	 */
	public function all(): array {
		$adapters = $this->adapters;
		uasort( $adapters, array( $this, 'compare_adapters' ) );
		return $adapters;
	}

	/**
	 * Return only healthy adapters the user can actually invoke.
	 *
	 * @return array<string, Adapter>
	 */
	public function available_for_user( int $user_id ): array {
		if ( isset( $this->available_cache[ $user_id ] ) ) {
			return $this->available_cache[ $user_id ];
		}

		if ( $user_id <= 0 || ! $this->permissions->account_is_eligible( $user_id ) ) {
			$this->available_cache[ $user_id ] = array();
			return array();
		}

		$available = array();
		foreach ( $this->all() as $key => $adapter ) {
			try {
				if ( $adapter->is_available() && $this->permissions->can_use_adapter( $user_id, $adapter ) ) {
					$available[ $key ] = $adapter;
				}
			} catch ( Throwable $error ) {
				$this->runtime_error( $key, 'availability_exception', $error );
			}
		}

		$this->available_cache[ $user_id ] = $available;
		return $available;
	}

	public function has_available_for_user( int $user_id ): bool {
		return array() !== $this->available_for_user( $user_id );
	}

	/**
	 * Determine whether the central account and capability gates permit at least
	 * one registered adapter, without treating native-module availability as a
	 * permission decision.
	 */
	public function has_central_capability_for_user( int $user_id ): bool {
		if ( $user_id <= 0 || ! $this->permissions->account_is_eligible( $user_id ) ) {
			return false;
		}

		foreach ( $this->all() as $key => $adapter ) {
			try {
				$capability = trim( $adapter->required_capability() );
				if ( '' === $capability || user_can( $user_id, $capability ) ) {
					return true;
				}
			} catch ( Throwable $error ) {
				$this->runtime_error( $key, 'capability_exception', $error );
			}
		}

		return false;
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public function errors(): array {
		return $this->errors;
	}

	public function flush_cache(): void {
		$this->available_cache = array();
	}

	private function compare_adapters( Adapter $left, Adapter $right ): int {
		try {
			$priority = $left->priority() <=> $right->priority();
			if ( 0 !== $priority ) {
				return $priority;
			}

			$label = strcasecmp( $left->label(), $right->label() );
			return 0 !== $label ? $label : strcmp( $left->key(), $right->key() );
		} catch ( Throwable $error ) {
			return 0;
		}
	}

	private function registration_error( string $code, string $key, string $message ): WP_Error {
		$this->errors[ $key ] = array(
			'code'    => $code,
			'message' => $message,
		);

		do_action( 'supc_adapter_registration_error', $key, $code );
		return new WP_Error( 'supc_' . $code, $message, array( 'adapter' => $key ) );
	}

	private function runtime_error( string $key, string $code, Throwable $error ): WP_Error {
		$this->errors[ $key ] = array(
			'code'        => $code,
			'exception'   => get_class( $error ),
			'message'     => $error->getMessage(),
			'occurred_at' => gmdate( 'c' ),
		);

		do_action( 'supc_adapter_runtime_error', $key, $code );
		return new WP_Error( 'supc_' . $code, __( 'The content adapter is temporarily unavailable.', 'sabri-universal-post-composer' ) );
	}
}
