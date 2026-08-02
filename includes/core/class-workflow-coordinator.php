<?php
/**
 * Guarded native workflow orchestration.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

namespace Sabri\UniversalComposer\Core;

use Sabri\UniversalComposer\Contracts\Workflow_Adapter;
use Throwable;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Workflow_Coordinator {
	private const NATIVE_ERROR_CODES = array(
		'permission_denied', 'validation_failed', 'conflict', 'rate_limited',
		'temporarily_unavailable', 'not_found', 'expired', 'invalid_reference',
	);

	private Workflow_Validator $validator;

	public function __construct(
		private Registry $registry,
		private Permission_Resolver $permissions
	) {
		$this->validator = new Workflow_Validator();
	}

	/** @return array<string,mixed>|WP_Error */
	public function schema( int $user_id, string $adapter_key ): array|WP_Error {
		$adapter = $this->resolve( $user_id, $adapter_key, true );
		if ( $adapter instanceof WP_Error ) {
			return $adapter;
		}
		$contract = $this->registry->workflow_contract( $adapter_key );
		return null === $contract
			? $this->error( 'workflow_adapter_unavailable', $adapter_key )
			: $this->validator->schema( $adapter, $contract, $user_id, $adapter_key );
	}

	/**
	 * @return array{status:string,codes:array<int,string>,workflow_api_version:string,supports_native_drafts:string,subject_schema_extension:string}
	 */
	public function contract_health( string $adapter_key ): array {
		if ( ! Contract_Boundary::adapter_key( $adapter_key ) ) {
			return $this->health_failure( 'invalid_adapter_key' );
		}
		$adapter = $this->registry->get( $adapter_key );
		if ( null === $adapter ) {
			return $this->health_failure( 'workflow_adapter_not_registered' );
		}
		if ( ! $adapter instanceof Workflow_Adapter ) {
			return array(
				'status' => 'pass',
				'codes' => array(),
				'workflow_api_version' => 'not_applicable',
				'supports_native_drafts' => 'not_applicable',
				'subject_schema_extension' => 'not_applicable',
			);
		}
		$contract = $this->registry->workflow_contract( $adapter_key );
		if ( null === $contract ) {
			return $this->health_failure( 'workflow_registration_metadata_missing' );
		}
		$drafts  = $contract['supports_native_drafts'] ? 'yes' : 'no';
		$subject = $contract['subject_schema_extension'] ? 'yes' : 'no';
		$api     = $contract['workflow_api_version'];
		if ( SUPC_WORKFLOW_API_VERSION !== $api ) {
			return $this->health_failure( 'workflow_api_mismatch', $api, $drafts, $subject );
		}
		$schema = $this->validator->schema( $adapter, $contract, 0, $adapter_key );
		if ( $schema instanceof WP_Error ) {
			$code = 'supc_workflow_adapter_exception' === $schema->code
				? 'workflow_contract_exception'
				: 'invalid_schema_contract';
			return $this->health_failure( $code, $api, $drafts, $subject );
		}
		return array(
			'status' => 'pass',
			'codes' => array(),
			'workflow_api_version' => $api,
			'supports_native_drafts' => $drafts,
			'subject_schema_extension' => $subject,
		);
	}

	/**
	 * @param array<string,mixed> $payload Native payload.
	 * @return array<string,mixed>|WP_Error
	 */
	public function create_draft( int $user_id, string $adapter_key, ?string $native_reference, array $payload ): array|WP_Error {
		if ( null !== $native_reference && ! $this->validator->valid_reference( $native_reference ) ) {
			return $this->error( 'invalid_native_reference', $adapter_key );
		}
		$adapter = $this->resolve( $user_id, $adapter_key, true );
		if ( $adapter instanceof WP_Error ) {
			return $adapter;
		}
		$contract = $this->registry->workflow_contract( $adapter_key );
		if ( null === $contract || ! $contract['supports_native_drafts'] ) {
			return $this->error( 'native_drafts_unsupported', $adapter_key );
		}
		$payload_error = $this->validator->payload( $adapter, $contract, $payload, $user_id, $adapter_key, false );
		if ( $payload_error instanceof WP_Error ) {
			return $payload_error;
		}
		try {
			$result = $adapter->create_draft( $user_id, $native_reference, $payload );
			if ( $result instanceof WP_Error ) {
				return $this->native_error( $adapter_key, 'create_draft', $result );
			}
			$validated = $this->validator->draft_result( $result, $adapter_key );
			return $validated instanceof WP_Error || null === $native_reference
				? $validated
				: $this->require_native_reference( $validated, $native_reference, $adapter_key );
		} catch ( Throwable $error ) {
			return $this->exception( $adapter_key, 'create_draft', $error );
		}
	}

	/**
	 * @param array<string,mixed> $payload Native payload.
	 * @return array<string,mixed>|WP_Error
	 */
	public function validate( int $user_id, string $adapter_key, array $payload ): array|WP_Error {
		$adapter = $this->resolve( $user_id, $adapter_key, true );
		if ( $adapter instanceof WP_Error ) {
			return $adapter;
		}
		$contract = $this->registry->workflow_contract( $adapter_key );
		if ( null === $contract ) {
			return $this->error( 'workflow_adapter_unavailable', $adapter_key );
		}
		$payload_error = $this->validator->payload( $adapter, $contract, $payload, $user_id, $adapter_key, true );
		if ( $payload_error instanceof WP_Error ) {
			return $payload_error;
		}
		try {
			$result = $adapter->validate( $user_id, $payload );
			return $result instanceof WP_Error
				? $this->native_error( $adapter_key, 'validate', $result )
				: $this->validator->validation_result( $result, $adapter_key );
		} catch ( Throwable $error ) {
			return $this->exception( $adapter_key, 'validate', $error );
		}
	}

	/**
	 * @param array<string,mixed> $payload Native payload.
	 * @return array<string,mixed>|WP_Error
	 */
	public function preview( int $user_id, string $adapter_key, array $payload ): array|WP_Error {
		$adapter = $this->resolve( $user_id, $adapter_key, true );
		if ( $adapter instanceof WP_Error ) {
			return $adapter;
		}
		$contract = $this->registry->workflow_contract( $adapter_key );
		if ( null === $contract ) {
			return $this->error( 'workflow_adapter_unavailable', $adapter_key );
		}
		$native_reference = $payload['native_reference'] ?? null;
		if ( ! is_string( $native_reference ) || ! $this->validator->valid_reference( $native_reference ) ) {
			return $this->error( 'invalid_native_reference', $adapter_key );
		}
		$user_payload = $payload;
		unset( $user_payload['native_reference'] );
		$payload_error = $this->validator->payload( $adapter, $contract, $user_payload, $user_id, $adapter_key, true );
		if ( $payload_error instanceof WP_Error ) {
			return $payload_error;
		}
		$native_payload                     = $user_payload;
		$native_payload['native_reference'] = $native_reference;
		try {
			$result = $adapter->preview( $user_id, $native_payload );
			return $result instanceof WP_Error
				? $this->native_error( $adapter_key, 'preview', $result )
				: $this->validator->preview_result( $result, $adapter_key );
		} catch ( Throwable $error ) {
			return $this->exception( $adapter_key, 'preview', $error );
		}
	}

	/**
	 * @param array<string,mixed> $payload Native payload.
	 * @return array<string,mixed>|WP_Error
	 */
	public function submit( int $user_id, string $adapter_key, string $idempotency_key, array $payload ): array|WP_Error {
		if ( ! $this->validator->valid_idempotency_key( $idempotency_key ) ) {
			return $this->error( 'invalid_idempotency_key', $adapter_key );
		}
		$adapter = $this->resolve( $user_id, $adapter_key, true );
		if ( $adapter instanceof WP_Error ) {
			return $adapter;
		}
		$contract = $this->registry->workflow_contract( $adapter_key );
		if ( null === $contract ) {
			return $this->error( 'workflow_adapter_unavailable', $adapter_key );
		}
		$native_reference = $payload['native_reference'] ?? null;
		if ( ! is_string( $native_reference ) || ! $this->validator->valid_reference( $native_reference ) ) {
			return $this->error( 'invalid_native_reference', $adapter_key );
		}
		$user_payload = $payload;
		unset( $user_payload['native_reference'] );
		$payload_error = $this->validator->payload( $adapter, $contract, $user_payload, $user_id, $adapter_key, true );
		if ( $payload_error instanceof WP_Error ) {
			return $payload_error;
		}
		$native_payload                     = $user_payload;
		$native_payload['native_reference'] = $native_reference;
		try {
			$result = $adapter->submit( $user_id, $idempotency_key, $native_payload );
			if ( $result instanceof WP_Error ) {
				return $this->native_error( $adapter_key, 'submit', $result );
			}
			$validated = $this->validator->status_result( $result, $adapter_key );
			return $validated instanceof WP_Error
				? $validated
				: $this->require_native_reference( $validated, $native_reference, $adapter_key );
		} catch ( Throwable $error ) {
			return $this->exception( $adapter_key, 'submit', $error );
		}
	}

	/** @return array<string,mixed>|WP_Error */
	public function status( int $user_id, string $adapter_key, string $native_reference ): array|WP_Error {
		if ( ! $this->validator->valid_reference( $native_reference ) ) {
			return $this->error( 'invalid_native_reference', $adapter_key );
		}
		$adapter = $this->resolve( $user_id, $adapter_key, false );
		if ( $adapter instanceof WP_Error ) {
			return $adapter;
		}
		try {
			$result = $adapter->status( $user_id, $native_reference );
			if ( $result instanceof WP_Error ) {
				return $this->native_error( $adapter_key, 'status', $result );
			}
			$validated = $this->validator->status_result( $result, $adapter_key );
			return $validated instanceof WP_Error
				? $validated
				: $this->require_native_reference( $validated, $native_reference, $adapter_key );
		} catch ( Throwable $error ) {
			return $this->exception( $adapter_key, 'status', $error );
		}
	}

	/** @return string|WP_Error */
	public function canonical_url( int $user_id, string $adapter_key, string $native_reference ): string|WP_Error {
		if ( ! $this->validator->valid_reference( $native_reference ) ) {
			return $this->error( 'invalid_native_reference', $adapter_key );
		}
		$adapter = $this->resolve( $user_id, $adapter_key, false );
		if ( $adapter instanceof WP_Error ) {
			return $adapter;
		}
		try {
			$url = $adapter->canonical_url( $user_id, $native_reference );
			$url = $this->validator->internal_url( $url );
			return '' !== $url ? $url : $this->error( 'invalid_canonical_url', $adapter_key );
		} catch ( Throwable $error ) {
			return $this->exception( $adapter_key, 'canonical_url', $error );
		}
	}

	public function generate_idempotency_key(): string {
		$left  = wp_generate_uuid4();
		$right = wp_generate_uuid4();
		$key   = $left . ':' . $right;
		return $this->validator->valid_idempotency_key( $key ) ? $key : '';
	}

	/** @return Workflow_Adapter|WP_Error */
	private function resolve( int $user_id, string $adapter_key, bool $require_create_policy ): Workflow_Adapter|WP_Error {
		if ( Safe_Mode::disabled() ) {
			return $this->error( 'workflow_disabled', $adapter_key );
		}
		if ( $user_id <= 0 || ! Contract_Boundary::adapter_key( $adapter_key ) ) {
			return $this->error( 'invalid_workflow_request', $adapter_key );
		}
		if ( ! $this->permissions->account_is_eligible( $user_id ) ) {
			return $this->error( 'workflow_permission_denied', $adapter_key );
		}
		$adapter  = $this->registry->get( $adapter_key );
		$contract = $this->registry->workflow_contract( $adapter_key );
		if ( ! $adapter instanceof Workflow_Adapter || null === $contract ) {
			return $this->error( 'workflow_adapter_unavailable', $adapter_key );
		}
		if ( ! $this->permissions->can_use_capability( $user_id, $contract['required_capability'] ) ) {
			return $this->error( 'workflow_permission_denied', $adapter_key );
		}
		if ( SUPC_WORKFLOW_API_VERSION !== $contract['workflow_api_version'] ) {
			return $this->error( 'workflow_api_mismatch', $adapter_key );
		}
		try {
			if ( ! $adapter->is_available() ) {
				return $this->error( 'native_workflow_unavailable', $adapter_key );
			}
			if ( $require_create_policy && ! $adapter->can_create( $user_id ) ) {
				return $this->error( 'workflow_permission_denied', $adapter_key );
			}
			if ( ! $this->central_authority_allows( $user_id, $contract['required_capability'] ) ) {
				return $this->error( 'workflow_permission_denied', $adapter_key );
			}
			return $adapter;
		} catch ( Throwable $error ) {
			return $this->exception( $adapter_key, 'resolve', $error );
		}
	}

	/**
	 * Re-evaluate mutable central authority after native policy checks.
	 */
	private function central_authority_allows( int $user_id, string $capability ): bool {
		return ! Safe_Mode::disabled()
			&& $this->permissions->account_is_eligible( $user_id )
			&& $this->permissions->can_use_capability( $user_id, $capability );
	}

	/**
	 * Bind every native response to the object already owned by the session.
	 * A buggy or compromised adapter must not silently substitute another object.
	 *
	 * @param array<string,mixed> $result Validated native result.
	 * @return array<string,mixed>|WP_Error
	 */
	private function require_native_reference( array $result, string $expected, string $adapter_key ): array|WP_Error {
		$actual = $result['native_reference'] ?? null;
		if ( ! is_string( $actual ) || ! hash_equals( $expected, $actual ) ) {
			do_action( 'supc_workflow_reference_mismatch', Contract_Boundary::public_identifier( $adapter_key ) );
			return $this->error( 'native_reference_mismatch', $adapter_key );
		}
		return $result;
	}

	private function native_error( string $adapter_key, string $operation, WP_Error $error ): WP_Error {
		$native_code = is_callable( array( $error, 'get_error_code' ) )
			? (string) call_user_func( array( $error, 'get_error_code' ) )
			: (string) ( get_object_vars( $error )['code'] ?? '' );
		$native_code = strtolower( trim( $native_code ) );
		$public_code = in_array( $native_code, self::NATIVE_ERROR_CODES, true ) ? $native_code : 'native_error';
		do_action( 'supc_workflow_native_error', Contract_Boundary::public_identifier( $adapter_key ), sanitize_key( $operation ), $public_code );
		return new WP_Error(
			'supc_native_workflow_error',
			__( 'The native workflow returned a controlled error.', 'sabri-universal-post-composer' ),
			array(
				'adapter_key' => Contract_Boundary::public_identifier( $adapter_key ),
				'operation' => sanitize_key( $operation ),
				'native_code' => $public_code,
			)
		);
	}

	private function exception( string $adapter_key, string $operation, Throwable $error ): WP_Error {
		unset( $error );
		do_action( 'supc_workflow_exception', Contract_Boundary::public_identifier( $adapter_key ), sanitize_key( $operation ), 'native_exception' );
		return $this->error( 'workflow_adapter_exception', $adapter_key );
	}

	private function error( string $code, string $adapter_key ): WP_Error {
		return new WP_Error(
			'supc_' . $code,
			__( 'The workflow request could not be completed safely.', 'sabri-universal-post-composer' ),
			array( 'adapter_key' => Contract_Boundary::public_identifier( $adapter_key ) )
		);
	}

	/**
	 * @return array{status:string,codes:array<int,string>,workflow_api_version:string,supports_native_drafts:string,subject_schema_extension:string}
	 */
	private function health_failure( string $code, string $api = 'missing', string $drafts = 'missing', string $subject = 'missing' ): array {
		return array(
			'status' => 'fail',
			'codes' => array( $code ),
			'workflow_api_version' => $api,
			'supports_native_drafts' => $drafts,
			'subject_schema_extension' => $subject,
		);
	}
}
