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
	 * Immutable registration-time base metadata used by every authorization and
	 * exact-owner decision. Native adapter methods may report health dynamically,
	 * but they cannot weaken the capability or change the owner after acceptance.
	 *
	 * @var array<string, array{api_version:string,required_capability:string,native_module:string,minimum_native_version:string,privacy_classification:string}>
	 */
	private array $adapter_contracts = array();

	/**
	 * Immutable registration-time workflow metadata. Runtime authorization uses
	 * this snapshot before invoking any native adapter method.
	 *
	 * @var array<string, array{workflow_api_version:string,required_capability:string,supports_native_drafts:bool}>
	 */
	private array $workflow_contracts = array();

	/** @var array<int, array<string, Adapter>> */
	private array $available_cache = array();

	/** @var array<int, string> */
	private array $state_cache = array();

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
				return $this->registration_error( 'duplicate_key', $key, 'Adapter key is already registered.' );
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
			if ( 1 !== preg_match( '/^[0-9]+\.[0-9]+\.[0-9]+(?:[-+][A-Za-z0-9.-]+)?$/', $minimum_native_version ) ) {
				return $this->registration_error( 'invalid_minimum_native_version', $key, 'Adapter minimum native version is invalid.' );
			}

			$privacy_classification = $adapter->privacy_classification();
			if ( ! in_array( $privacy_classification, array( 'public', 'private', 'sensitive' ), true ) ) {
				return $this->registration_error( 'invalid_privacy', $key, 'Adapter privacy classification is invalid.' );
			}

			$base_contract = array(
				'api_version'            => $api_version,
				'required_capability'    => $capability,
				'native_module'          => $native_module,
				'minimum_native_version' => $minimum_native_version,
				'privacy_classification' => $privacy_classification,
			);

			$workflow_contract = null;
			if ( $adapter instanceof Workflow_Adapter ) {
				$workflow_contract = array(
					'workflow_api_version'   => trim( $adapter->workflow_api_version() ),
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

		unset( $this->adapters[ $key ], $this->adapter_contracts[ $key ], $this->workflow_contracts[ $key ] );
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
	 * @return array{api_version:string,required_capability:string,native_module:string,minimum_native_version:string,privacy_classification:string}|null
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
		uasort( $adapters, array( $this, 'compare_adapters' ) );
		return $adapters;
	}

	/**
	 * Return only healthy adapters the user can actually invoke.
	 *
	 * Central account and immutable capability checks always run before native
	 * availability. Native availability is then resolved before adapter-specific
	 * authorization so an offline integration is never mislabeled as a permission
	 * denial.
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
				$contract = $this->adapter_contract( $key );
				if ( null === $contract ) {
					$this->runtime_error( $key, 'registration_exception' );
					continue;
				}
				if ( ! $this->permissions->can_use_capability( $user_id, $contract['required_capability'] ) ) {
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

		$this->available_cache[ $user_id ] = $available;
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
		if ( isset( $this->state_cache[ $user_id ] ) ) {
			return $this->state_cache[ $user_id ];
		}

		if ( $user_id <= 0 || ! $this->permissions->account_is_eligible( $user_id ) ) {
			$this->state_cache[ $user_id ] = 'denied';
			return 'denied';
		}

		$adapters = $this->all();
		if ( array() === $adapters ) {
			$this->state_cache[ $user_id ] = 'unavailable';
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
				if ( ! $adapter->is_available() ) {
					$has_unavailable = true;
					continue;
				}
				if ( ! $adapter->can_create( $user_id ) ) {
					continue;
				}

				$this->state_cache[ $user_id ] = 'available';
				return 'available';
			} catch ( Throwable $error ) {
				unset( $error );
				$has_unavailable = true;
				$this->runtime_error( $key, 'state_exception' );
			}
		}

		$this->state_cache[ $user_id ] = $has_unavailable ? 'unavailable' : 'denied';
		return $this->state_cache[ $user_id ];
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

	public function flush_cache(): void {
		$this->available_cache = array();
		$this->state_cache     = array();
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
			unset( $error );
			return 0;
		}
	}

	private function registration_error( string $code, string $key, string $message ): WP_Error {
		$this->errors[ $key ] = array(
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
