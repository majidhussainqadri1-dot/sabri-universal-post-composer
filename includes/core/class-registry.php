<?php
/**
 * Fail-soft adapter registry.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

namespace Sabri\UniversalComposer\Core;

use Sabri\UniversalComposer\Contracts\Adapter;
use Sabri\UniversalComposer\Contracts\Workflow_Adapter;
use Throwable;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Registry {
	/** @var array<string, Adapter> */
	private array $adapters = array();

	/**
	 * Immutable registration-time base metadata used by authorization, exact-owner,
	 * privacy, grouping, ordering, and minimum-version decisions. Native adapters
	 * may report operational health dynamically, but cannot rewrite structural or
	 * security metadata after registration.
	 *
	 * @var array<string, array{api_version:string,required_capability:string,native_module:string,minimum_native_version:string,privacy_classification:string,group:string,priority:int}>
	 */
	private array $adapter_contracts = array();

	/**
	 * Immutable registration-time workflow metadata. Runtime authorization uses
	 * this snapshot before invoking any native adapter method.
	 *
	 * @var array<string, array{workflow_api_version:string,required_capability:string,supports_native_drafts:bool}>
	 */
	private array $workflow_contracts = array();

	/** @var array<string, array<string, mixed>> */
	private array $errors = array();

	public function __construct( private Permission_Resolver $permissions ) {
	}

	/**
	 * @return true|WP_Error
	 */
	public function register( Adapter $adapter ) {
		$key = 'unknown_' . count( $this->errors );

		try {
			$key = $adapter->key();
			if ( 1 !== preg_match( '/^[a-z][a-z0-9_]{2,63}$/', $key ) ) {
				return $this->registration_error( 'invalid_key', $key, 'Adapter key is not canonical.' );
			}

			if ( isset( $this->adapters[ $key ] ) ) {
				return $this->registration_error(
					'duplicate_key',
					$key,
					'Adapter key is already registered.',
					$this->duplicate_error_key( $key )
				);
			}

			$api_version = trim( $adapter->api_version() );
			if ( SUPC_ADAPTER_API_VERSION !== $api_version ) {
				return $this->registration_error( 'api_mismatch', $key, 'Adapter API version is incompatible.' );
			}

			$capability = trim( $adapter->required_capability() );
			if ( '' === $capability || sanitize_key( $capability ) !== $capability ) {
				return $this->registration_error( 'invalid_required_capability', $key, 'Adapter capability is not canonical.' );
			}

			$native_module = trim( $adapter->native_module() );
			if ( 1 !== preg_match( '/^[a-z][a-z0-9-]{2,127}$/', $native_module ) ) {
				return $this->registration_error( 'invalid_native_module', $key, 'Adapter native module is not canonical.' );
			}

			$minimum_native_version = trim( $adapter->minimum_native_version() );
			if ( ! Version::valid( $minimum_native_version ) ) {
				return $this->registration_error( 'invalid_minimum_native_version', $key, 'Adapter minimum native version is invalid.' );
			}

			$privacy_classification = trim( $adapter->privacy_classification() );
			if ( ! in_array( $privacy_classification, array( 'public', 'private', 'sensitive' ), true ) ) {
				return $this->registration_error( 'invalid_privacy', $key, 'Adapter privacy classification is invalid.' );
			}

			$group = trim( $adapter->group() );
			if ( '' === $group || strlen( $group ) > 64 ) {
				return $this->registration_error( 'invalid_group', $key, 'Adapter group is invalid.' );
			}

			$priority = $adapter->priority();

			$base_contract = array(
				'api_version'            => $api_version,
				'required_capability'    => $capability,
				'native_module'          => $native_module,
				'minimum_native_version' => $minimum_native_version,
				'privacy_classification' => $privacy_classification,
				'group'                   => $group,
				'priority'                => $priority,
			);

			$workflow_contract = null;
			if ( $adapter instanceof Workflow_Adapter ) {
				$workflow_api_version = trim( $adapter->workflow_api_version() );
				if ( ! Version::valid( $workflow_api_version ) ) {
					return $this->registration_error( 'api_mismatch', $key, 'Workflow Adapter API version is malformed.' );
				}

				$workflow_contract = array(
					'workflow_api_version'   => $workflow_api_version,
					'required_capability'    => $capability,
					'supports_native_drafts' => $adapter->supports_native_drafts(),
				);
			}

			// Registration is atomic: no adapter becomes visible until every required
			// base and workflow metadata method has completed successfully.
			$this->adapters[ $key ]          = $adapter;
			$this->adapter_contracts[ $key ] = $base_contract;
			if ( null !== $workflow_contract ) {
				$this->workflow_contracts[ $key ] = $workflow_contract;
			}

			// A corrected re-registration must not inherit a stale diagnostic from an
			// earlier failed attempt or duplicate collision using the same key.
			unset( $this->errors[ $key ], $this->errors[ $this->duplicate_error_key( $key ) ] );
			$this->flush_cache();
			return true;
		} catch ( Throwable $error ) {
			unset( $error );

			// Defensive rollback protects against future edits that accidentally move
			// a mutation above the final atomic commit block.
			unset( $this->adapters[ $key ], $this->adapter_contracts[ $key ], $this->workflow_contracts[ $key ] );
			$this->flush_cache();
			return $this->runtime_error( $key, 'registration_exception' );
		}
	}

	public function unregister( string $key ): bool {
		if ( ! isset( $this->adapters[ $key ] ) ) {
			return false;
		}

		unset(
			$this->adapters[ $key ],
			$this->adapter_contracts[ $key ],
			$this->workflow_contracts[ $key ],
			$this->errors[ $key ],
			$this->errors[ $this->duplicate_error_key( $key ) ]
		);
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
	 * @return array{api_version:string,required_capability:string,native_module:string,minimum_native_version:string,privacy_classification:string,group:string,priority:int}|null
	 */
	public function adapter_contract( string $key ): ?array {
		return $this->adapter_contracts[ $key ] ?? null;
	}

	/**
	 * @return array{workflow_api_version:string,required_capability:string,supports_native_drafts:bool}|null
	 */
	public function workflow_contract( string $key ): ?array {
		return $this->workflow_contracts[ $key ] ?? null;
	}

	/**
	 * @return array<string, Adapter>
	 */
	public function all(): array {
		$adapters = $this->adapters;
		uksort( $adapters, array( $this, 'compare_adapter_keys' ) );
		return $adapters;
	}

	/**
	 * Return only healthy adapters the user can actually invoke.
	 *
	 * Authorization, Safe Mode, native availability, and adapter-specific policy
	 * are deliberately re-evaluated on every call. Caching an allow decision can
	 * outlive a same-request suspension, capability revocation, emergency-disable,
	 * or native outage and would violate the fail-closed boundary.
	 *
	 * @return array<string, Adapter>
	 */
	public function available_for_user( int $user_id ): array {
		if ( $user_id <= 0 || ! $this->permissions->account_is_eligible( $user_id ) ) {
			return array();
		}

		$available = array();
		foreach ( $this->all() as $key => $adapter ) {
			try {
				$contract = $this->adapter_contract( $key );
				if ( null === $contract ) {
					$this->runtime_error( $key, 'registration_exception' );
					continue;
				}
				if ( ! $this->permissions->can_use_capability( $user_id, $contract['required_capability'] ) ) {
					continue;
				}
				if ( $adapter instanceof Workflow_Adapter && ! $this->workflow_is_compatible( $key ) ) {
					$this->runtime_error( $key, 'workflow_api_mismatch' );
					continue;
				}
				if ( ! $adapter->is_available() ) {
					continue;
				}
				if ( ! $adapter->can_create( $user_id ) ) {
					continue;
				}
				$available[ $key ] = $adapter;
			} catch ( Throwable $error ) {
				unset( $error );
				$this->runtime_error( $key, 'availability_exception' );
			}
		}

		return $available;
	}

	public function has_available_for_user( int $user_id ): bool {
		return array() !== $this->available_for_user( $user_id );
	}

	/**
	 * Return available, unavailable, or denied without conflating an adapter's
	 * own authorization restriction with native-module availability.
	 */
	public function creation_state_for_user( int $user_id ): string {
		if ( $user_id <= 0 || ! $this->permissions->account_is_eligible( $user_id ) ) {
			return 'denied';
		}

		$adapters = $this->all();
		if ( array() === $adapters ) {
			return 'unavailable';
		}

		$has_unavailable = false;
		foreach ( $adapters as $key => $adapter ) {
			try {
				$contract = $this->adapter_contract( $key );
				if ( null === $contract ) {
					$has_unavailable = true;
					$this->runtime_error( $key, 'registration_exception' );
					continue;
				}
				if ( ! $this->permissions->can_use_capability( $user_id, $contract['required_capability'] ) ) {
					continue;
				}
				if ( $adapter instanceof Workflow_Adapter && ! $this->workflow_is_compatible( $key ) ) {
					$has_unavailable = true;
					$this->runtime_error( $key, 'workflow_api_mismatch' );
					continue;
				}
				if ( ! $adapter->is_available() ) {
					$has_unavailable = true;
					continue;
				}
				if ( ! $adapter->can_create( $user_id ) ) {
					continue;
				}

				return 'available';
			} catch ( Throwable $error ) {
				unset( $error );
				$has_unavailable = true;
				$this->runtime_error( $key, 'state_exception' );
			}
		}

		return $has_unavailable ? 'unavailable' : 'denied';
	}

	/**
	 * Compatibility query used by the Create surface. True means that the
	 * central gate permits a registered workflow but its native service is not
	 * available, or that no native adapter has registered at all. Adapter-specific
	 * authorization denial remains false.
	 */
	public function has_central_capability_for_user( int $user_id ): bool {
		return 'unavailable' === $this->creation_state_for_user( $user_id );
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public function errors(): array {
		return $this->errors;
	}

	/**
	 * Retained for API compatibility. Authorization and operational allow decisions
	 * are no longer cached, so there is no security-sensitive state to flush.
	 */
	public function flush_cache(): void {
	}

	private function workflow_is_compatible( string $key ): bool {
		$contract = $this->workflow_contract( $key );
		return null !== $contract && SUPC_WORKFLOW_API_VERSION === $contract['workflow_api_version'];
	}

	private function compare_adapter_keys( string $left_key, string $right_key ): int {
		$left_contract  = $this->adapter_contracts[ $left_key ] ?? null;
		$right_contract = $this->adapter_contracts[ $right_key ] ?? null;
		$left_priority  = null !== $left_contract ? $left_contract['priority'] : PHP_INT_MAX;
		$right_priority = null !== $right_contract ? $right_contract['priority'] : PHP_INT_MAX;
		$priority       = $left_priority <=> $right_priority;
		return 0 !== $priority ? $priority : strcmp( $left_key, $right_key );
	}

	private function duplicate_error_key( string $key ): string {
		return '[duplicate]:' . $key;
	}

	private function registration_error( string $code, string $key, string $message, ?string $storage_key = null ): WP_Error {
		$this->errors[ $storage_key ?? $key ] = array(
			'code'    => $code,
			'message' => $message,
		);

		do_action( 'supc_adapter_registration_error', sanitize_key( $key ), $code );
		return new WP_Error( 'supc_' . $code, $message, array( 'adapter' => sanitize_key( $key ) ) );
	}

	private function runtime_error( string $key, string $code ): WP_Error {
		$this->errors[ $key ] = array(
			'code'        => $code,
			'occurred_at' => gmdate( 'c' ),
		);

		do_action( 'supc_adapter_runtime_error', sanitize_key( $key ), $code );
		return new WP_Error( 'supc_' . $code, __( 'The content adapter is temporarily unavailable.', 'sabri-universal-post-composer' ) );
	}
}
