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
	private const MAX_ADAPTERS = 100;
	private const MAX_ERRORS = 200;
	private const MIN_PRIORITY = -10000;
	private const MAX_PRIORITY = 10000;
	private const DYNAMIC_ERROR_CODES = array( 'availability_exception', 'state_exception', 'workflow_api_mismatch' );

	/** @var array<string, Adapter> */
	private array $adapters = array();

	/**
	 * @var array<string, array{api_version:string,required_capability:string,native_module:string,minimum_native_version:string,privacy_classification:string,group:string,priority:int}>
	 */
	private array $adapter_contracts = array();

	/**
	 * @var array<string, array{workflow_api_version:string,required_capability:string,supports_native_drafts:bool,subject_schema_extension:bool}>
	 */
	private array $workflow_contracts = array();

	/** @var array<string, array<string, mixed>> */
	private array $errors = array();

	public function __construct( private Permission_Resolver $permissions ) {
	}

	/** @return true|WP_Error */
	public function register( Adapter $adapter ) {
		$key         = 'invalid_adapter';
		$storage_key = '[registration]:' . substr( hash( 'sha256', get_class( $adapter ) ), 0, 20 );

		try {
			$raw_key     = $adapter->key();
			$storage_key = Contract_Boundary::diagnostic_storage_key( $raw_key, 'registration' );
			$key         = Contract_Boundary::public_identifier( $raw_key );
			if ( ! Contract_Boundary::adapter_key( $raw_key ) ) {
				return $this->registration_error( 'invalid_key', $key, 'Adapter key is not canonical.', $storage_key );
			}
			$key = $raw_key;

			if ( isset( $this->adapters[ $key ] ) ) {
				return $this->registration_error(
					'duplicate_key',
					$key,
					'Adapter key is already registered.',
					$this->duplicate_error_key( $key )
				);
			}

			if ( count( $this->adapters ) >= self::MAX_ADAPTERS ) {
				return $this->registration_error( 'adapter_limit_reached', $key, 'The bounded adapter registry is full.' );
			}

			$api_version = $adapter->api_version();
			if ( ! Contract_Boundary::version( $api_version ) || SUPC_ADAPTER_API_VERSION !== $api_version ) {
				return $this->registration_error( 'api_mismatch', $key, 'Adapter API version is incompatible.' );
			}

			$capability = $adapter->required_capability();
			if ( ! Contract_Boundary::capability( $capability ) ) {
				return $this->registration_error( 'invalid_required_capability', $key, 'Adapter capability is not canonical.' );
			}

			$native_module = $adapter->native_module();
			if ( ! Contract_Boundary::native_module( $native_module ) ) {
				return $this->registration_error( 'invalid_native_module', $key, 'Adapter native module is not canonical.' );
			}

			$minimum_native_version = $adapter->minimum_native_version();
			if ( ! Contract_Boundary::version( $minimum_native_version ) ) {
				return $this->registration_error( 'invalid_minimum_native_version', $key, 'Adapter minimum native version is invalid.' );
			}

			$privacy_classification = $adapter->privacy_classification();
			if (
				! Contract_Boundary::bounded_text( $privacy_classification, 1, 16 ) ||
				$privacy_classification !== trim( $privacy_classification ) ||
				! in_array( $privacy_classification, array( 'public', 'private', 'sensitive' ), true )
			) {
				return $this->registration_error( 'invalid_privacy', $key, 'Adapter privacy classification is invalid.' );
			}

			$group = $adapter->group();
			if ( ! Contract_Boundary::group( $group ) ) {
				return $this->registration_error( 'invalid_group', $key, 'Adapter group is invalid.' );
			}

			$priority = $adapter->priority();
			if ( $priority < self::MIN_PRIORITY || $priority > self::MAX_PRIORITY ) {
				return $this->registration_error( 'invalid_priority', $key, 'Adapter priority is outside the bounded range.' );
			}

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
				$workflow_api_version = $adapter->workflow_api_version();
				if ( ! Contract_Boundary::version( $workflow_api_version ) ) {
					return $this->registration_error( 'api_mismatch', $key, 'Workflow Adapter API version is malformed.' );
				}

				$workflow_contract = array(
					'workflow_api_version'     => $workflow_api_version,
					'required_capability'      => $capability,
					'supports_native_drafts'   => $adapter->supports_native_drafts(),
					'subject_schema_extension' => Contract_Boundary::declared_public_instance_method( $adapter, 'schema_for_user', 1 ),
				);
			}

			$this->adapters[ $key ]          = $adapter;
			$this->adapter_contracts[ $key ] = $base_contract;
			if ( null !== $workflow_contract ) {
				$this->workflow_contracts[ $key ] = $workflow_contract;
			}

			unset( $this->errors[ $key ], $this->errors[ $storage_key ], $this->errors[ $this->duplicate_error_key( $key ) ] );
			$this->flush_cache();
			return true;
		} catch ( Throwable $error ) {
			unset( $error );
			unset( $this->adapters[ $key ], $this->adapter_contracts[ $key ], $this->workflow_contracts[ $key ] );
			$this->flush_cache();
			return $this->runtime_error( $storage_key, 'registration_exception', $key );
		}
	}

	public function unregister( string $key ): bool {
		if ( ! Contract_Boundary::adapter_key( $key ) || ! isset( $this->adapters[ $key ] ) ) {
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
		return Contract_Boundary::adapter_key( $key ) ? ( $this->adapters[ $key ] ?? null ) : null;
	}

	/** @return array{api_version:string,required_capability:string,native_module:string,minimum_native_version:string,privacy_classification:string,group:string,priority:int}|null */
	public function adapter_contract( string $key ): ?array {
		return Contract_Boundary::adapter_key( $key ) ? ( $this->adapter_contracts[ $key ] ?? null ) : null;
	}

	/** @return array{workflow_api_version:string,required_capability:string,supports_native_drafts:bool,subject_schema_extension:bool}|null */
	public function workflow_contract( string $key ): ?array {
		return Contract_Boundary::adapter_key( $key ) ? ( $this->workflow_contracts[ $key ] ?? null ) : null;
	}

	/** @return array<string, Adapter> */
	public function all(): array {
		$adapters = $this->adapters;
		uksort( $adapters, array( $this, 'compare_adapter_keys' ) );
		return $adapters;
	}

	/**
	 * Evaluate one coherent availability snapshot. Native adapter methods execute
	 * at most once per adapter in this snapshot, and central authority is checked
	 * again before an allow result is released.
	 *
	 * @return array{state:string,adapters:array<string,Adapter>}
	 */
	public function availability_snapshot_for_user( int $user_id ): array {
		if ( $user_id <= 0 || ! $this->central_authority_allows( $user_id ) ) {
			return array( 'state' => 'denied', 'adapters' => array() );
		}

		$adapters = $this->all();
		if ( array() === $adapters ) {
			return array( 'state' => 'unavailable', 'adapters' => array() );
		}

		$available       = array();
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
				$available[ $key ] = $adapter;
				$this->clear_dynamic_error( $key );
			} catch ( Throwable $error ) {
				unset( $error );
				$has_unavailable = true;
				$this->runtime_error( $key, 'availability_exception' );
			}
		}

		if ( ! $this->central_authority_allows( $user_id ) ) {
			return array( 'state' => 'denied', 'adapters' => array() );
		}
		if ( array() !== $available ) {
			return array( 'state' => 'available', 'adapters' => $available );
		}
		return array( 'state' => $has_unavailable ? 'unavailable' : 'denied', 'adapters' => array() );
	}

	/**
	 * Re-evaluate mutable central authority at each decision boundary.
	 */
	private function central_authority_allows( int $user_id ): bool {
		return ! Safe_Mode::disabled() && $this->permissions->account_is_eligible( $user_id );
	}

	/** @return array<string, Adapter> */
	public function available_for_user( int $user_id ): array {
		return $this->availability_snapshot_for_user( $user_id )['adapters'];
	}

	public function has_available_for_user( int $user_id ): bool {
		return array() !== $this->availability_snapshot_for_user( $user_id )['adapters'];
	}

	public function creation_state_for_user( int $user_id ): string {
		return $this->availability_snapshot_for_user( $user_id )['state'];
	}

	public function has_central_capability_for_user( int $user_id ): bool {
		return 'unavailable' === $this->availability_snapshot_for_user( $user_id )['state'];
	}

	/** @return array<string, array<string, mixed>> */
	public function errors(): array {
		return $this->errors;
	}

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
		$this->record_error( $storage_key ?? Contract_Boundary::diagnostic_storage_key( $key ), array( 'code' => $code, 'message' => $message ) );
		$public_key = Contract_Boundary::public_identifier( $key );
		do_action( 'supc_adapter_registration_error', $public_key, $code );
		return new WP_Error( 'supc_' . $code, $message, array( 'adapter' => $public_key ) );
	}

	private function runtime_error( string $storage_key, string $code, string $public_key = '' ): WP_Error {
		$storage_key = Contract_Boundary::adapter_key( $storage_key ) || str_starts_with( $storage_key, '[' )
			? substr( $storage_key, 0, 96 )
			: Contract_Boundary::diagnostic_storage_key( $storage_key, 'runtime' );
		$this->record_error( $storage_key, array( 'code' => $code, 'occurred_at' => gmdate( 'c' ) ) );
		$public_key = Contract_Boundary::public_identifier( '' !== $public_key ? $public_key : $storage_key );
		do_action( 'supc_adapter_runtime_error', $public_key, $code );
		return new WP_Error( 'supc_' . $code, __( 'The content adapter is temporarily unavailable.', 'sabri-universal-post-composer' ) );
	}

	private function clear_dynamic_error( string $key ): void {
		$code = isset( $this->errors[ $key ]['code'] ) ? (string) $this->errors[ $key ]['code'] : '';
		if ( in_array( $code, self::DYNAMIC_ERROR_CODES, true ) ) {
			unset( $this->errors[ $key ] );
		}
	}

	/** @param array<string,mixed> $error Privacy-safe bounded diagnostic. */
	private function record_error( string $storage_key, array $error ): void {
		$storage_key = substr( $storage_key, 0, 96 );
		if ( isset( $this->errors[ $storage_key ] ) ) {
			$this->errors[ $storage_key ] = $error;
			return;
		}
		if ( count( $this->errors ) >= self::MAX_ERRORS ) {
			$this->errors['[registry-limit]'] = array( 'code' => 'registry_error_limit_reached' );
			return;
		}
		$this->errors[ $storage_key ] = $error;
	}
}
