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
	private const FIELD_KEY_PATTERN = '/^[a-z][a-z0-9_]{0,63}$/D';
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

	/** @param array<string,mixed> $payload @return array<string,mixed>|WP_Error */
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

	/** @param array<string,mixed> $payload @return array<string,mixed>|WP_Error */
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

	/** @param array<string,mixed> $payload @return array<string,mixed>|WP_Error */
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
			$result = $adapter->preview( $user_id, $payload );
			if ( $result instanceof WP_Error ) {
				return $this->native_error( $adapter_key, 'preview', $result );
			}
			if ( ! is_array( $result ) || ! $this->bounded_array( $result, self::MAX_RESULT_BYTES ) ) {
				return $this->error( 'invalid_preview_result', 'The native preview result is invalid.', $adapter_key );
			}
			$url        = isset( $result['preview_url'] ) && is_string( $result['preview_url'] ) ? $this->internal_url( $result['preview_url'] ) : '';
			$expires_at = $result['expires_at'] ?? null;
			$now        = time();
			if ( '' === $url || ! is_int( $expires_at ) || $expires_at <= $now || $expires_at > $now + self::MAX_PREVIEW_TTL ) {
				return $this->error( 'invalid_preview_result', 'The native preview result is invalid.', $adapter_key );
			}
			return array( 'preview_url' => $url, 'expires_at' => $expires_at );
		} catch ( Throwable $error ) {
			return $this->exception( $adapter_key, 'preview', $error );
		}
	}

	/** @param array<string,mixed> $payload @return array<string,mixed>|WP_Error */
	public function submit( int $user_id, string $adapter_key, string $idempotency_key, array $payload ): array|WP_Error {
		$preflight = $this->preflight( $user_id, $adapter_key );
		if ( $preflight instanceof WP_Error ) {
			return $preflight;
		}
		if ( ! $this->valid_idempotency_key( $idempotency_key ) ) {
			return $this->error( 'invalid_idempotency_key', 'The submission idempotency key is invalid.', $adapter_key );
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
			return $this->normalize_status_result( $adapter->submit( $user_id, $idempotency_key, $payload ), $adapter_key, 'submit' );
		} catch ( Throwable $error ) {
			return $this->exception( $adapter_key, 'submit', $error );
		}
	}

	/** @return array<string,mixed>|WP_Error */
	public function status( int $user_id, string $adapter_key, string $native_reference ): array|WP_Error {
		$preflight = $this->preflight( $user_id, $adapter_key );
		if ( $preflight instanceof WP_Error ) {
			return $preflight;
		}
		if ( ! $this->valid_native_reference( $native_reference ) ) {
			return $this->error( 'invalid_native_reference', 'The native reference is invalid.', $adapter_key );
		}
		$adapter = $this->resolve_adapter( $user_id, $adapter_key, false );
		if ( $adapter instanceof WP_Error ) {
			return $adapter;
		}
		try {
			return $this->normalize_status_result( $adapter->status( $user_id, $native_reference ), $adapter_key, 'status' );
		} catch ( Throwable $error ) {
			return $this->exception( $adapter_key, 'status', $error );
		}
	}

	/** @return string|WP_Error */
	public function canonical_url( int $user_id, string $adapter_key, string $native_reference ): string|WP_Error {
		$preflight = $this->preflight( $user_id, $adapter_key );
		if ( $preflight instanceof WP_Error ) {
			return $preflight;
		}
		if ( ! $this->valid_native_reference( $native_reference ) ) {
			return $this->error( 'invalid_native_reference', 'The native reference is invalid.', $adapter_key );
		}
		$adapter = $this->resolve_adapter( $user_id, $adapter_key, false );
		if ( $adapter instanceof WP_Error ) {
			return $adapter;
		}
		try {
			$url = $adapter->canonical_url( $user_id, $native_reference );
			$url = is_string( $url ) ? $this->internal_url( $url ) : '';
			return '' !== $url ? $url : $this->error( 'invalid_canonical_url', 'The native canonical URL is invalid.', $adapter_key );
		} catch ( Throwable $error ) {
			return $this->exception( $adapter_key, 'canonical_url', $error );
		}
	}

	public function generate_idempotency_key(): string {
		$left  = wp_generate_uuid4();
		$right = wp_generate_uuid4();
		$key   = $left . ':' . $right;
		return $left !== $right && $this->valid_idempotency_key( $key ) ? $key : '';
	}

	private function preflight( int $user_id, string $adapter_key ): ?WP_Error {
		if ( Safe_Mode::disabled() ) {
			return $this->error( 'workflow_disabled', 'Workflow orchestration is temporarily disabled.', $adapter_key );
		}
		if ( $user_id <= 0 || ! Contract_Boundary::adapter_key( $adapter_key ) ) {
			return $this->error( 'invalid_workflow_request', 'The workflow request is invalid.', $adapter_key );
		}
		if ( ! $this->permissions->account_is_eligible( $user_id ) ) {
			return $this->error( 'workflow_permission_denied', 'The account is not authorized for this workflow.', $adapter_key );
		}
		return null;
	}

	/** @return Workflow_Adapter|WP_Error */
	private function resolve_adapter( int $user_id, string $adapter_key, bool $require_create_policy ): Workflow_Adapter|WP_Error {
		$adapter  = $this->registry->get( $adapter_key );
		$contract = $this->registry->workflow_contract( $adapter_key );
		if ( ! $adapter instanceof Workflow_Adapter || null === $contract ) {
			return $this->error( 'workflow_adapter_unavailable', 'The requested workflow is unavailable.', $adapter_key );
		}
		if ( ! $this->permissions->can_use_capability( $user_id, $contract['required_capability'] ) ) {
			return $this->error( 'workflow_permission_denied', 'The account is not authorized for this workflow.', $adapter_key );
		}
		if ( SUPC_WORKFLOW_API_VERSION !== $contract['workflow_api_version'] ) {
			return $this->error( 'workflow_api_mismatch', 'The native workflow API version is incompatible.', $adapter_key );
		}
		try {
			if ( ! $adapter->is_available() ) {
				return $this->error( 'native_workflow_unavailable', 'The native workflow is unavailable.', $adapter_key );
			}
			if ( $require_create_policy && ! $adapter->can_create( $user_id ) ) {
				return $this->error( 'workflow_permission_denied', 'The account is not authorized for this workflow.', $adapter_key );
			}
			if ( Safe_Mode::disabled() || ! $this->permissions->account_is_eligible( $user_id ) || ! $this->permissions->can_use_capability( $user_id, $contract['required_capability'] ) ) {
				return $this->error( 'workflow_permission_denied', 'The account is not authorized for this workflow.', $adapter_key );
			}
			return $adapter;
		} catch ( Throwable $error ) {
			return $this->exception( $adapter_key, 'resolve', $error );
		}
	}

	/** @return array<string,mixed>|WP_Error */
	private function schema_for_adapter( Workflow_Adapter $adapter, string $adapter_key, int $user_id = 0 ): array|WP_Error {
		try {
			$version  = $adapter->schema_version();
			$contract = $this->registry->workflow_contract( $adapter_key );
			if ( ! is_string( $version ) || ! Contract_Boundary::version( $version ) || null === $contract ) {
				return $this->error( 'invalid_schema_contract', 'The native workflow schema is incompatible.', $adapter_key );
			}
			$schema = $user_id > 0 && $contract['subject_schema_extension']
				? $adapter->schema_for_user( $user_id )
				: $adapter->schema();
			if ( ! is_array( $schema ) || ! $this->bounded_array( $schema, self::MAX_SCHEMA_BYTES ) ) {
				return $this->error( 'invalid_schema_contract', 'The native workflow schema is incompatible.', $adapter_key );
			}
			if ( ! array_key_exists( 'version', $schema ) || ! is_string( $schema['version'] ) || $version !== $schema['version'] || ! array_key_exists( 'fields', $schema ) || ! is_array( $schema['fields'] ) ) {
				return $this->error( 'invalid_schema_contract', 'The native workflow schema is incompatible.', $adapter_key );
			}
			$fields = $this->normalize_schema_fields( $schema['fields'] );
			return null === $fields
				? $this->error( 'invalid_schema_contract', 'The native workflow schema is incompatible.', $adapter_key )
				: array( 'version' => $version, 'fields' => $fields );
		} catch ( Throwable $error ) {
			return $this->exception( $adapter_key, 'schema', $error );
		}
	}

	/** @param array<string,mixed>|WP_Error $result @return array<string,mixed>|WP_Error */
	private function normalize_status_result( array|WP_Error $result, string $adapter_key, string $operation ): array|WP_Error {
		if ( $result instanceof WP_Error ) {
			return $this->native_error( $adapter_key, $operation, $result );
		}
		if ( ! $this->bounded_array( $result, self::MAX_RESULT_BYTES ) ) {
			return $this->error( 'invalid_native_result', 'The native workflow result is invalid.', $adapter_key );
		}
		$reference = $result['native_reference'] ?? null;
		$status    = $result['status'] ?? null;
		if ( ! is_string( $reference ) || ! $this->valid_native_reference( $reference ) || ! is_string( $status ) || ! in_array( $status, self::FINAL_STATUSES, true ) ) {
			return $this->error( 'invalid_native_status', 'The native workflow status is invalid.', $adapter_key );
		}
		$normalized = array( 'native_reference' => $reference, 'status' => $status );
		if ( array_key_exists( 'canonical_url', $result ) ) {
			if ( ! is_string( $result['canonical_url'] ) ) {
				return $this->error( 'invalid_canonical_url', 'The native canonical URL is invalid.', $adapter_key );
			}
			$url = $this->internal_url( $result['canonical_url'] );
			if ( '' === $url ) {
				return $this->error( 'invalid_canonical_url', 'The native canonical URL is invalid.', $adapter_key );
			}
			$normalized['canonical_url'] = $url;
		}
		return $normalized;
	}

	/** @param array<string,mixed> $payload */
	private function validate_payload( array $payload, string $adapter_key ): ?WP_Error {
		$remaining = self::MAX_TOTAL_NODES;
		if ( ! $this->is_safe_value( $payload, 0, $remaining ) ) {
			return $this->error( 'invalid_workflow_payload', 'The workflow payload contains unsupported values.', $adapter_key );
		}
		$encoded = wp_json_encode( $payload );
		if ( ! is_string( $encoded ) || strlen( $encoded ) > self::MAX_PAYLOAD_BYTES ) {
			return $this->error( 'workflow_payload_too_large', 'The workflow payload exceeds the safe request limit.', $adapter_key );
		}
		return null;
	}

	/** @param array<string,mixed> $payload */
	private function validate_payload_contract( Workflow_Adapter $adapter, array $payload, string $adapter_key, bool $require_required, int $user_id ): ?WP_Error {
		$base_error = $this->validate_payload( $payload, $adapter_key );
		if ( $base_error instanceof WP_Error ) {
			return $base_error;
		}
		$schema = $this->schema_for_adapter( $adapter, $adapter_key, $user_id );
		if ( $schema instanceof WP_Error ) {
			return $schema;
		}
		$fields = $schema['fields'];
		foreach ( $payload as $key => $value ) {
			if ( ! is_string( $key ) || ! array_key_exists( $key, $fields ) ) {
				return $this->error( 'workflow_payload_unknown_field', 'The workflow payload contains an undeclared field.', $adapter_key );
			}
			if ( ! $this->field_value_is_valid( $fields[ $key ], $value ) ) {
				return $this->error( 'workflow_payload_field_invalid', 'The workflow payload contains a value that does not match its schema.', $adapter_key );
			}
		}
		if ( $require_required ) {
			foreach ( $fields as $key => $definition ) {
				if ( true === $definition['required'] && ( ! array_key_exists( $key, $payload ) || $this->required_value_is_empty( $definition, $payload[ $key ] ) ) ) {
					return $this->error( 'workflow_payload_required_field_missing', 'The workflow payload is missing a required field.', $adapter_key );
				}
			}
		}
		return null;
	}

	/** @param array<string,mixed> $definition */
	private function field_value_is_valid( array $definition, mixed $value ): bool {
		if ( null === $value ) {
			return false === $definition['required'];
		}
		$type = $definition['type'];
		if ( in_array( $type, array( 'text', 'textarea' ), true ) ) {
			return is_string( $value ) && Contract_Boundary::bounded_text( $value, 0, self::MAX_PAYLOAD_BYTES, true );
		}
		if ( 'checkbox' === $type ) {
			return is_bool( $value );
		}
		if ( 'number' === $type ) {
			if ( ( ! is_int( $value ) && ! is_float( $value ) ) || ( is_float( $value ) && ! is_finite( $value ) ) ) {
				return false;
			}
			if ( array_key_exists( 'minimum', $definition ) && $value < $definition['minimum'] ) {
				return false;
			}
			return ! array_key_exists( 'maximum', $definition ) || $value <= $definition['maximum'];
		}
		if ( 'select' === $type ) {
			return is_string( $value ) && array_key_exists( $value, $definition['choices'] );
		}
		if ( 'multiselect' === $type ) {
			if ( ! is_array( $value ) || array_values( $value ) !== $value || count( $value ) > self::MAX_SCHEMA_CHOICES ) {
				return false;
			}
			$seen = array();
			foreach ( $value as $choice ) {
				if ( ! is_string( $choice ) || ! array_key_exists( $choice, $definition['choices'] ) || isset( $seen[ $choice ] ) ) {
					return false;
				}
				$seen[ $choice ] = true;
			}
			return true;
		}
		if ( 'opaque_reference' === $type ) {
			return is_string( $value ) && ( '' === $value || $this->valid_native_reference( $value ) );
		}
		if ( 'email' === $type ) {
			return is_string( $value ) && ( '' === $value || ( function_exists( 'is_email' ) ? false !== is_email( $value ) : false !== filter_var( $value, FILTER_VALIDATE_EMAIL ) ) );
		}
		if ( 'url' === $type ) {
			return is_string( $value ) && ( '' === $value || $this->valid_http_url_value( $value ) );
		}
		if ( 'date' === $type ) {
			return is_string( $value ) && ( '' === $value || $this->valid_date_value( $value ) );
		}
		if ( 'datetime' === $type ) {
			return is_string( $value ) && ( '' === $value || $this->valid_datetime_value( $value ) );
		}
		return false;
	}

	private function valid_http_url_value( string $value ): bool {
		if ( ! Contract_Boundary::bounded_text( $value, 1, self::MAX_URL_BYTES ) || false === filter_var( $value, FILTER_VALIDATE_URL ) || str_contains( $value, '\\' ) ) {
			return false;
		}
		$parts = wp_parse_url( $value );
		if ( ! is_array( $parts ) ) {
			return false;
		}
		$scheme = strtolower( (string) ( $parts['scheme'] ?? '' ) );
		$host   = (string) ( $parts['host'] ?? '' );
		$port   = isset( $parts['port'] ) ? (int) $parts['port'] : 0;
		return in_array( $scheme, array( 'http', 'https' ), true )
			&& '' !== $host
			&& ! isset( $parts['user'] )
			&& ! isset( $parts['pass'] )
			&& ( 0 === $port || ( $port >= 1 && $port <= 65535 ) );
	}

	private function valid_date_value( string $value ): bool {
		if ( 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/D', $value, $parts ) ) {
			return false;
		}
		$year = (int) $parts[1];
		return $year >= 1 && checkdate( (int) $parts[2], (int) $parts[3], $year );
	}

	private function valid_datetime_value( string $value ): bool {
		$pattern = '/^(\d{4}-\d{2}-\d{2})[T ](\d{2}):(\d{2})(?::(\d{2}))?(Z|[+-](\d{2}):(\d{2}))?$/D';
		if ( 1 !== preg_match( $pattern, $value, $parts ) || ! $this->valid_date_value( $parts[1] ) ) {
			return false;
		}
		$hour = (int) $parts[2];
		$minute = (int) $parts[3];
		$second = isset( $parts[4] ) && '' !== $parts[4] ? (int) $parts[4] : 0;
		if ( $hour > 23 || $minute > 59 || $second > 59 ) {
			return false;
		}
		$timezone = $parts[5] ?? '';
		if ( '' === $timezone || 'Z' === $timezone ) {
			return true;
		}
		$offset_hour = (int) ( $parts[6] ?? 0 );
		$offset_minute = (int) ( $parts[7] ?? 0 );
		return $offset_minute <= 59 && ( $offset_hour < 14 || ( 14 === $offset_hour && 0 === $offset_minute ) );
	}

	/** @param array<string,mixed> $definition */
	private function required_value_is_empty( array $definition, mixed $value ): bool {
		if ( null === $value ) {
			return true;
		}
		if ( 'checkbox' === $definition['type'] ) {
			return true !== $value;
		}
		if ( 'multiselect' === $definition['type'] ) {
			return ! is_array( $value ) || array() === $value;
		}
		return is_string( $value ) && '' === trim( $value );
	}

	/** @param array<mixed> $value */
	private function bounded_array( array $value, int $maximum_bytes ): bool {
		$remaining = self::MAX_TOTAL_NODES;
		if ( ! $this->is_safe_value( $value, 0, $remaining ) ) {
			return false;
		}
		$encoded = wp_json_encode( $value );
		return is_string( $encoded ) && strlen( $encoded ) <= $maximum_bytes;
	}

	private function is_safe_value( mixed $value, int $depth, int &$remaining ): bool {
		--$remaining;
		if ( $remaining < 0 || $depth > self::MAX_DEPTH ) {
			return false;
		}
		if ( null === $value || is_bool( $value ) || is_int( $value ) ) {
			return true;
		}
		if ( is_float( $value ) ) {
			return is_finite( $value );
		}
		if ( is_string( $value ) ) {
			return Contract_Boundary::valid_utf8( $value );
		}
		if ( ! is_array( $value ) || count( $value ) > self::MAX_ARRAY_ITEMS ) {
			return false;
		}
		foreach ( $value as $key => $item ) {
			if ( ! is_int( $key ) && ! is_string( $key ) ) {
				return false;
			}
			if ( is_string( $key ) && ( ! Contract_Boundary::valid_utf8( $key ) || strlen( $key ) > 255 ) ) {
				return false;
			}
			if ( ! $this->is_safe_value( $item, $depth + 1, $remaining ) ) {
				return false;
			}
		}
		return true;
	}

	/** @param array<mixed> $fields @return array<string,array<string,mixed>>|null */
	private function normalize_schema_fields( array $fields ): ?array {
		if ( count( $fields ) > self::MAX_SCHEMA_FIELDS ) {
			return null;
		}
		$normalized = array();
		foreach ( $fields as $key => $definition ) {
			if ( ! is_string( $key ) || ! Contract_Boundary::field_key( $key ) || ! is_array( $definition ) || array() !== array_diff( array_keys( $definition ), self::FIELD_PROPERTIES ) ) {
				return null;
			}
			if ( ! array_key_exists( 'type', $definition ) || ! is_string( $definition['type'] ) || ! in_array( $definition['type'], self::FIELD_TYPES, true ) ) {
				return null;
			}
			if ( ! array_key_exists( 'label_code', $definition ) || ! is_string( $definition['label_code'] ) || 1 !== preg_match( self::CODE_PATTERN, $definition['label_code'] ) ) {
				return null;
			}
			if ( ! array_key_exists( 'privacy_class', $definition ) || ! is_string( $definition['privacy_class'] ) || ! in_array( $definition['privacy_class'], self::PRIVACY_CLASSES, true ) ) {
				return null;
			}
			$required = array_key_exists( 'required', $definition ) ? $definition['required'] : false;
			if ( ! is_bool( $required ) ) {
				return null;
			}
			$type = $definition['type'];
			$field = array(
				'type'          => $type,
				'label_code'    => $definition['label_code'],
				'required'      => $required,
				'privacy_class' => $definition['privacy_class'],
			);
			if ( array_key_exists( 'description_code', $definition ) ) {
				if ( ! is_string( $definition['description_code'] ) || 1 !== preg_match( self::CODE_PATTERN, $definition['description_code'] ) ) {
					return null;
				}
				$field['description_code'] = $definition['description_code'];
			}
			foreach ( array( 'minimum', 'maximum' ) as $bound ) {
				if ( array_key_exists( $bound, $definition ) ) {
					$value = $definition[ $bound ];
					if ( 'number' !== $type || ( ! is_int( $value ) && ! is_float( $value ) ) || ( is_float( $value ) && ! is_finite( $value ) ) ) {
						return null;
					}
					$field[ $bound ] = $value;
				}
			}
			if ( array_key_exists( 'minimum', $field ) && array_key_exists( 'maximum', $field ) && $field['minimum'] > $field['maximum'] ) {
				return null;
			}
			$is_choice = in_array( $type, array( 'select', 'multiselect' ), true );
			if ( $is_choice ) {
				if ( ! array_key_exists( 'choices', $definition ) || ! is_array( $definition['choices'] ) || array() === $definition['choices'] || count( $definition['choices'] ) > self::MAX_SCHEMA_CHOICES ) {
					return null;
				}
				$choices = array();
				foreach ( $definition['choices'] as $choice_key => $choice_label ) {
					if ( ! is_string( $choice_key ) || ! Contract_Boundary::field_key( $choice_key ) || ! is_string( $choice_label ) || 1 !== preg_match( self::CODE_PATTERN, $choice_label ) ) {
						return null;
					}
					$choices[ $choice_key ] = $choice_label;
				}
				$field['choices'] = $choices;
			} elseif ( array_key_exists( 'choices', $definition ) ) {
				return null;
			}
			$normalized[ $key ] = $field;
		}
		return $normalized;
	}

	/** @return array<int,string>|null */
	private function normalize_code_collection( mixed $codes ): ?array {
		if ( ! is_array( $codes ) || array_values( $codes ) !== $codes || count( $codes ) > self::MAX_CODE_COLLECTION_ITEMS ) {
			return null;
		}
		$normalized = array();
		foreach ( $codes as $code ) {
			if ( ! is_string( $code ) || ! Contract_Boundary::code( $code ) || isset( $normalized[ $code ] ) ) {
				return null;
			}
			$normalized[ $code ] = $code;
		}
		return array_values( $normalized );
	}

	private function valid_native_reference( string $reference ): bool {
		return Contract_Boundary::bounded_text( $reference, 1, 255 ) && 1 === preg_match( self::NATIVE_REFERENCE_PATTERN, $reference );
	}

	private function valid_idempotency_key( string $key ): bool {
		$parts = explode( ':', $key );
		return 2 === count( $parts ) && $parts[0] !== $parts[1]
			&& 1 === preg_match( self::UUID_V4_PATTERN, $parts[0] )
			&& 1 === preg_match( self::UUID_V4_PATTERN, $parts[1] );
	}

	private function internal_url( string $url ): string {
		if ( $url !== trim( $url ) || ! Contract_Boundary::bounded_text( $url, 1, self::MAX_URL_BYTES ) || str_contains( $url, '\\' ) ) {
			return '';
		}
		$validated = wp_validate_redirect( $url, '' );
		if ( '' === $validated ) {
			return '';
		}
		if ( str_starts_with( $validated, '/' ) ) {
			return str_starts_with( $validated, '//' ) ? '' : $validated;
		}
		$target = wp_parse_url( $validated );
		$home   = wp_parse_url( home_url( '/' ) );
		if ( ! is_array( $target ) || ! is_array( $home ) ) {
			return '';
		}
		$target_scheme = strtolower( (string) ( $target['scheme'] ?? '' ) );
		$home_scheme   = strtolower( (string) ( $home['scheme'] ?? '' ) );
		$target_host   = strtolower( (string) ( $target['host'] ?? '' ) );
		$home_host     = strtolower( (string) ( $home['host'] ?? '' ) );
		if ( 'https' !== $target_scheme || 'https' !== $home_scheme || '' === $target_host || $target_host !== $home_host || isset( $target['user'] ) || isset( $target['pass'] ) ) {
			return '';
		}
		$target_port = isset( $target['port'] ) ? (int) $target['port'] : 443;
		$home_port   = isset( $home['port'] ) ? (int) $home['port'] : 443;
		return $target_port === $home_port ? $validated : '';
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
				'operation'   => sanitize_key( $operation ),
				'native_code' => $public_code,
			)
		);
	}

	private function exception( string $adapter_key, string $operation, Throwable $error ): WP_Error {
		unset( $error );
		do_action( 'supc_workflow_exception', Contract_Boundary::public_identifier( $adapter_key ), sanitize_key( $operation ), 'native_exception' );
		return $this->error( 'workflow_adapter_exception', 'The native workflow could not complete safely.', $adapter_key );
	}

	private function error( string $code, string $message, string $adapter_key ): WP_Error {
		return new WP_Error( 'supc_' . $code, __( $message, 'sabri-universal-post-composer' ), array( 'adapter_key' => Contract_Boundary::public_identifier( $adapter_key ) ) );
	}

	/** @return array{status:string,codes:array<int,string>,workflow_api_version:string,supports_native_drafts:string,subject_schema_extension:string} */
	private function health_failure( string $code, string $api = 'missing', string $drafts = 'missing', string $subject = 'missing' ): array {
		return array(
			'status'                   => 'fail',
			'codes'                    => array( $code ),
			'workflow_api_version'     => $api,
			'supports_native_drafts'   => $drafts,
			'subject_schema_extension' => $subject,
		);
	}
}
