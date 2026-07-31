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
	private const MAX_PAYLOAD_BYTES = 1048576;
	private const MAX_SCHEMA_BYTES = 262144;
	private const MAX_RESULT_BYTES = 1048576;
	private const MAX_DEPTH = 12;
	private const MAX_TOTAL_NODES = 10000;
	private const MAX_PREVIEW_TTL = 1800;
	private const MAX_SCHEMA_FIELDS = 100;
	private const MAX_SCHEMA_CHOICES = 100;
	private const MAX_ARRAY_ITEMS = 1000;
	private const MAX_CODE_COLLECTION_ITEMS = 100;
	private const MAX_URL_BYTES = 2048;
	private const NATIVE_REFERENCE_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._:-]{0,254}$/D';
	private const UUID_V4_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D';
	private const CODE_PATTERN = '/^[a-z][a-z0-9_.:-]{0,63}$/D';
	private const FINAL_STATUSES = array( 'draft', 'pending_review', 'scheduled', 'published', 'rejected', 'failed' );
	private const DRAFT_STATUSES = array( 'draft', 'pending_review' );
	private const FIELD_TYPES = array( 'text', 'textarea', 'select', 'multiselect', 'checkbox', 'number', 'date', 'datetime', 'url', 'email', 'opaque_reference' );
	private const FIELD_PROPERTIES = array( 'type', 'label_code', 'description_code', 'required', 'privacy_class', 'minimum', 'maximum', 'choices' );
	private const PRIVACY_CLASSES = array( 'public', 'private', 'sensitive' );
	private const NATIVE_ERROR_CODES = array( 'permission_denied', 'validation_failed', 'conflict', 'rate_limited', 'temporarily_unavailable', 'not_found', 'expired', 'invalid_reference' );

	public function __construct(
		private Registry $registry,
		private Permission_Resolver $permissions
	) {
	}

	/** @return array<string,mixed>|WP_Error */
	public function schema( int $user_id, string $adapter_key ): array|WP_Error {
		$preflight = $this->preflight( $user_id, $adapter_key );
		if ( $preflight instanceof WP_Error ) {
			return $preflight;
		}
		$adapter = $this->resolve_adapter( $user_id, $adapter_key, true );
		return $adapter instanceof WP_Error ? $adapter : $this->schema_for_adapter( $adapter, $adapter_key, $user_id );
	}

	/** @return array{status:string,codes:array<int,string>,workflow_api_version:string,supports_native_drafts:string,subject_schema_extension:string} */
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
				'status'                   => 'pass',
				'codes'                    => array(),
				'workflow_api_version'     => 'not_applicable',
				'supports_native_drafts'   => 'not_applicable',
				'subject_schema_extension' => 'not_applicable',
			);
		}

		$contract = $this->registry->workflow_contract( $adapter_key );
		if ( null === $contract ) {
			return $this->health_failure(
				'workflow_registration_metadata_missing',
				'missing',
				'missing',
				'no'
			);
		}

		$subject = $contract['subject_schema_extension'] ? 'yes' : 'no';
		$drafts  = $contract['supports_native_drafts'] ? 'yes' : 'no';
		if ( SUPC_WORKFLOW_API_VERSION !== $contract['workflow_api_version'] ) {
			return $this->health_failure( 'workflow_api_mismatch', $contract['workflow_api_version'], $drafts, $subject );
		}

		$schema = $this->schema_for_adapter( $adapter, $adapter_key );
		if ( $schema instanceof WP_Error ) {
			$code = 'supc_workflow_adapter_exception' === $schema->code
				? 'workflow_contract_exception'
				: 'invalid_schema_contract';
			return $this->health_failure( $code, $contract['workflow_api_version'], $drafts, $subject );
		}

		return array(
			'status'                   => 'pass',
			'codes'                    => array(),
			'workflow_api_version'     => $contract['workflow_api_version'],
			'supports_native_drafts'   => $drafts,
			'subject_schema_extension' => $subject,
		);
	}

	/**
	 * @param array<string,mixed> $payload Validated payload.
	 * @return array<string,mixed>|WP_Error
	 */
	public function create_draft( int $user_id, string $adapter_key, ?string $native_reference, array $payload ): array|WP_Error {
		$preflight = $this->preflight( $user_id, $adapter_key );
		if ( $preflight instanceof WP_Error ) {
			return $preflight;
		}
		if ( null !== $native_reference && ! $this->valid_native_reference( $native_reference ) ) {
			return $this->error( 'invalid_native_reference', 'The native draft reference is invalid.', $adapter_key );
		}

		$adapter = $this->resolve_adapter( $user_id, $adapter_key, true );
		if ( $adapter instanceof WP_Error ) {
			return $adapter;
		}
		$contract = $this->registry->workflow_contract( $adapter_key );
		if ( null === $contract || ! $contract['supports_native_drafts'] ) {
			return $this->error( 'native_drafts_unsupported', 'The native workflow does not support direct draft orchestration.', $adapter_key );
		}

		$payload_error = $this->validate_payload_contract( $adapter, $payload, $adapter_key, false, $user_id );
		if ( $payload_error instanceof WP_Error ) {
			return $payload_error;
		}

		try {
			$result = $adapter->create_draft( $user_id, $native_reference, $payload );
			if ( $result instanceof WP_Error ) {
				return $this->native_error( $adapter_key, 'create_draft', $result );
			}
			if ( ! is_array( $result ) || ! $this->bounded_array( $result, self::MAX_RESULT_BYTES ) ) {
				return $this->error( 'invalid_native_result', 'The native draft result is invalid.', $adapter_key );
			}
			$reference = $result['native_reference'] ?? null;
			$status    = $result['status'] ?? null;
			if ( ! is_string( $reference ) || ! $this->valid_native_reference( $reference ) || ! is_string( $status ) || ! in_array( $status, self::DRAFT_STATUSES, true ) ) {
				return $this->error( 'invalid_native_result', 'The native draft result is invalid.', $adapter_key );
			}
			return array( 'native_reference' => $reference, 'status' => $status );
		} catch ( Throwable $error ) {
			return $this->exception( $adapter_key, 'create_draft', $error );
		}
	}

	/**
	 * @param array<string,mixed> $payload Validated payload.
	 * @return array<string,mixed>|WP_Error
	 */
	public function validate( int $user_id, string $adapter_key, array $payload ): array|WP_Error {
		$preflight = $this->preflight( $user_id, $adapter_key );
		if ( $preflight instanceof WP_Error ) {
			return $preflight;
		}
		$adapter = $this->resolve_adapter( $user_id, $adapter_key, true );
		if ( $adapter instanceof WP_Error ) {
			return $adapter;
		}
		$payload_error = $this->validate_payload_contract( $adapter, $payload, $adapter_key, true, $user_id );
		if ( $payload_error instanceof WP_Error ) {
			return $payload_error;
		}

		try {
			$result = $adapter->validate( $user_id, $payload );
			if ( $result instanceof WP_Error ) {
				return $this->native_error( $adapter_key, 'validate', $result );
			}
			if ( ! is_array( $result ) || ! $this->bounded_array( $result, self::MAX_RESULT_BYTES ) || ! array_key_exists( 'valid', $result ) || ! is_bool( $result['valid'] ) ) {
				return $this->error( 'invalid_validation_result', 'The native validation result is invalid.', $adapter_key );
			}
			$errors   = $this->normalize_code_collection( $result['errors'] ?? array() );
			$warnings = $this->normalize_code_collection( $result['warnings'] ?? array() );
			if ( null === $errors || null === $warnings ) {
				return $this->error( 'invalid_validation_result', 'The native validation result is invalid.', $adapter_key );
			}
			if ( ( $result['valid'] && array() !== $errors ) || ( ! $result['valid'] && array() === $errors ) || array_intersect( $errors, $warnings ) ) {
				return $this->error( 'invalid_validation_result', 'The native validation result is internally inconsistent.', $adapter_key );
			}
			return array( 'valid' => $result['valid'], 'errors' => $errors, 'warnings' => $warnings );
		} catch ( Throwable $error ) {
			return $this->exception( $adapter_key, 'validate', $error );
		}
	}

	/**
	 * @param array<string,mixed> $payload Validated payload.
	 * @return array<string,mixed>|WP_Error
	 */
	public function preview( int $user_id, string $adapter_key, array $payload ): array|WP_Error {
		$preflight = $this->preflight( $user_id, $adapter_key );
		if ( $preflight instanceof WP_Error ) {
			return $preflight;
		}
		$adapter = $this->resolve_adapter( $user_id, $adapter_key, true );
		if ( $adapter instanceof WP_Error ) {
			return $adapter;
		}
		$payload_error = $this->validate_payload_contract( $adapter, $payload, $adapter_key, true, $user_id );
		if ( $payload_error instanceof WP_Error ) {
			return $payload_error;
		}

		try {
			$result = $adapter->preview(