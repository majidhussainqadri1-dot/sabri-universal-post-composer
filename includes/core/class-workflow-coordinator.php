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
	private const MAX_DEPTH = 12;
	private const NATIVE_REFERENCE_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._:-]{0,254}$/';
	private const IDEMPOTENCY_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._:-]{31,127}$/';
	private const FINAL_STATUSES = array( 'draft', 'pending_review', 'scheduled', 'published', 'rejected', 'failed' );

	public function __construct(
		private Registry $registry,
		private Permission_Resolver $permissions
	) {
	}

	/** @return array<string,mixed>|WP_Error */
	public function schema( int $user_id, string $adapter_key ): array|WP_Error {
		$adapter = $this->resolve_adapter( $user_id, $adapter_key );
		if ( $adapter instanceof WP_Error ) {
			return $adapter;
		}

		try {
			$version = trim( $adapter->schema_version() );
			$schema  = $adapter->schema();
			if ( ! $this->valid_version( $version ) || ! isset( $schema['version'], $schema['fields'] ) || $version !== $schema['version'] || ! is_array( $schema['fields'] ) || ! $this->is_safe_value( $schema ) ) {
				return $this->error( 'invalid_schema_contract', 'The native workflow schema is incompatible.', $adapter_key );
			}
			return $schema;
		} catch ( Throwable $error ) {
			return $this->exception( $adapter_key, 'schema', $error );
		}
	}

	/** @param array<string,mixed> $payload @return array<string,mixed>|WP_Error */
	public function create_draft( int $user_id, string $adapter_key, ?string $native_reference, array $payload ): array|WP_Error {
		$adapter = $this->resolve_adapter( $user_id, $adapter_key );
		if ( $adapter instanceof WP_Error ) {
			return $adapter;
		}
		if ( null !== $native_reference && ! $this->valid_native_reference( $native_reference ) ) {
			return $this->error( 'invalid_native_reference', 'The native draft reference is invalid.', $adapter_key );
		}
		$payload_error = $this->validate_payload( $payload, $adapter_key );
		if ( $payload_error instanceof WP_Error ) {
			return $payload_error;
		}

		try {
			$result = $adapter->create_draft( $user_id, $native_reference, $payload );
			if ( $result instanceof WP_Error ) {
				return $result;
			}
			if ( ! is_array( $result ) || ! $this->valid_native_reference( (string) ( $result['native_reference'] ?? '' ) ) || ! $this->is_safe_value( $result ) ) {
				return $this->error( 'invalid_native_result', 'The native draft result is invalid.', $adapter_key );
			}
			return $result;
		} catch ( Throwable $error ) {
			return $this->exception( $adapter_key, 'create_draft', $error );
		}
	}

	/** @param array<string,mixed> $payload @return array<string,mixed>|WP_Error */
	public function validate( int $user_id, string $adapter_key, array $payload ): array|WP_Error {
		$adapter = $this->resolve_adapter( $user_id, $adapter_key );
		if ( $adapter instanceof WP_Error ) {
			return $adapter;
		}
		$payload_error = $this->validate_payload( $payload, $adapter_key );
		if ( $payload_error instanceof WP_Error ) {
			return $payload_error;
		}

		try {
			$result = $adapter->validate( $user_id, $payload );
			if ( $result instanceof WP_Error ) {
				return $result;
			}
			if ( ! is_array( $result ) || ! array_key_exists( 'valid', $result ) || ! is_bool( $result['valid'] ) || ! $this->is_safe_value( $result ) ) {
				return $this->error( 'invalid_validation_result', 'The native validation result is invalid.', $adapter_key );
			}
			return $result;
		} catch ( Throwable $error ) {
			return $this->exception( $adapter_key, 'validate', $error );
		}
	}

	/** @param array<string,mixed> $payload @return array<string,mixed>|WP_Error */
	public function preview( int $user_id, string $adapter_key, array $payload ): array|WP_Error {
		$adapter = $this->resolve_adapter( $user_id, $adapter_key );
		if ( $adapter instanceof WP_Error ) {
			return $adapter;
		}
		$payload_error = $this->validate_payload( $payload, $adapter_key );
		if ( $payload_error instanceof WP_Error ) {
			return $payload_error;
		}

		try {
			$result = $adapter->preview( $user_id, $payload );
			if ( $result instanceof WP_Error ) {
				return $result;
			}
			$url = is_array( $result ) ? $this->internal_url( (string) ( $result['preview_url'] ?? '' ) ) : '';
			if ( '' === $url || ! $this->is_safe_value( $result ) ) {
				return $this->error( 'invalid_preview_result', 'The native preview result is invalid.', $adapter_key );
			}
			$result['preview_url'] = $url;
			return $result;
		} catch ( Throwable $error ) {
			return $this->exception( $adapter_key, 'preview', $error );
		}
	}

	/** @param array<string,mixed> $payload @return array<string,mixed>|WP_Error */
	public function submit( int $user_id, string $adapter_key, string $idempotency_key, array $payload ): array|WP_Error {
		$adapter = $this->resolve_adapter( $user_id, $adapter_key );
		if ( $adapter instanceof WP_Error ) {
			return $adapter;
		}
		if ( 1 !== preg_match( self::IDEMPOTENCY_PATTERN, $idempotency_key ) ) {
			return $this->error( 'invalid_idempotency_key', 'The submission idempotency key is invalid.', $adapter_key );
		}
		$payload_error = $this->validate_payload( $payload, $adapter_key );
		if ( $payload_error instanceof WP_Error ) {
			return $payload_error;
		}

		try {
			$result = $adapter->submit( $user_id, $idempotency_key, $payload );
			return $this->normalize_status_result( $result, $adapter_key );
		} catch ( Throwable $error ) {
			return $this->exception( $adapter_key, 'submit', $error );
		}
	}

	/** @return array<string,mixed>|WP_Error */
	public function status( int $user_id, string $adapter_key, string $native_reference ): array|WP_Error {
		$adapter = $this->resolve_adapter( $user_id, $adapter_key );
		if ( $adapter instanceof WP_Error ) {
			return $adapter;
		}
		if ( ! $this->valid_native_reference( $native_reference ) ) {
			return $this->error( 'invalid_native_reference', 'The native reference is invalid.', $adapter_key );
		}

		try {
			return $this->normalize_status_result( $adapter->status( $user_id, $native_reference ), $adapter_key );
		} catch ( Throwable $error ) {
			return $this->exception( $adapter_key, 'status', $error );
		}
	}

	/** @return string|WP_Error */
	public function canonical_url( int $user_id, string $adapter_key, string $native_reference ): string|WP_Error {
		$adapter = $this->resolve_adapter( $user_id, $adapter_key );
		if ( $adapter instanceof WP_Error ) {
			return $adapter;
		}
		if ( ! $this->valid_native_reference( $native_reference ) ) {
			return $this->error( 'invalid_native_reference', 'The native reference is invalid.', $adapter_key );
		}

		try {
			$url = $this->internal_url( $adapter->canonical_url( $native_reference ) );
			return '' !== $url ? $url : $this->error( 'invalid_canonical_url', 'The native canonical URL is invalid.', $adapter_key );
		} catch ( Throwable $error ) {
			return $this->exception( $adapter_key, 'canonical_url', $error );
		}
	}

	public function generate_idempotency_key(): string {
		return wp_generate_uuid4() . ':' . wp_generate_uuid4();
	}

	/** @return Workflow_Adapter|WP_Error */
	private function resolve_adapter( int $user_id, string $adapter_key ): Workflow_Adapter|WP_Error {
		if ( Safe_Mode::disabled() ) {
			return $this->error( 'workflow_disabled', 'Workflow orchestration is temporarily disabled.', $adapter_key );
		}
		if ( $user_id <= 0 || 1 !== preg_match( '/^[a-z][a-z0-9_]{2,63}$/', $adapter_key ) ) {
			return $this->error( 'invalid_workflow_request', 'The workflow request is invalid.', $adapter_key );
		}

		$adapter = $this->registry->get( $adapter_key );
		if ( ! $adapter instanceof Workflow_Adapter ) {
			return $this->error( 'workflow_adapter_unavailable', 'The requested adapter does not support direct workflow orchestration.', $adapter_key );
		}

		try {
			if ( ! $adapter->is_available() ) {
				return $this->error( 'native_workflow_unavailable', 'The native workflow is unavailable.', $adapter_key );
			}
			if ( ! $this->permissions->can_use_adapter( $user_id, $adapter ) ) {
				return $this->error( 'workflow_permission_denied', 'The account is not authorized for this workflow.', $adapter_key );
			}
			return $adapter;
		} catch ( Throwable $error ) {
			return $this->exception( $adapter_key, 'resolve', $error );
		}
	}

	/** @param array<string,mixed>|WP_Error $result @return array<string,mixed>|WP_Error */
	private function normalize_status_result( array|WP_Error $result, string $adapter_key ): array|WP_Error {
		if ( $result instanceof WP_Error ) {
			return $result;
		}
		if ( ! $this->valid_native_reference( (string) ( $result['native_reference'] ?? '' ) ) ) {
			return $this->error( 'invalid_native_result', 'The native workflow result is invalid.', $adapter_key );
		}
		$status = sanitize_key( (string) ( $result['status'] ?? '' ) );
		if ( ! in_array( $status, self::FINAL_STATUSES, true ) || ! $this->is_safe_value( $result ) ) {
			return $this->error( 'invalid_native_status', 'The native workflow status is invalid.', $adapter_key );
		}
		$result['status'] = $status;
		if ( isset( $result['canonical_url'] ) ) {
			$url = $this->internal_url( (string) $result['canonical_url'] );
			if ( '' === $url ) {
				return $this->error( 'invalid_canonical_url', 'The native canonical URL is invalid.', $adapter_key );
			}
			$result['canonical_url'] = $url;
		}
		return $result;
	}

	/** @param array<string,mixed> $payload */
	private function validate_payload( array $payload, string $adapter_key ): ?WP_Error {
		if ( ! $this->is_safe_value( $payload ) ) {
			return $this->error( 'invalid_workflow_payload', 'The workflow payload contains unsupported values.', $adapter_key );
		}
		$encoded = wp_json_encode( $payload );
		if ( ! is_string( $encoded ) || strlen( $encoded ) > self::MAX_PAYLOAD_BYTES ) {
			return $this->error( 'workflow_payload_too_large', 'The workflow payload exceeds the safe request limit.', $adapter_key );
		}
		return null;
	}

	private function is_safe_value( mixed $value, int $depth = 0 ): bool {
		if ( $depth > self::MAX_DEPTH ) {
			return false;
		}
		if ( null === $value || is_scalar( $value ) ) {
			return ! is_float( $value ) || is_finite( $value );
		}
		if ( ! is_array( $value ) ) {
			return false;
		}
		foreach ( $value as $key => $item ) {
			if ( ! is_int( $key ) && ! is_string( $key ) ) {
				return false;
			}
			if ( ! $this->is_safe_value( $item, $depth + 1 ) ) {
				return false;
			}
		}
		return true;
	}

	private function valid_native_reference( string $reference ): bool {
		return 1 === preg_match( self::NATIVE_REFERENCE_PATTERN, $reference );
	}

	private function valid_version( string $version ): bool {
		return 1 === preg_match( '/^[0-9]+\.[0-9]+\.[0-9]+(?:[-+][A-Za-z0-9.-]+)?$/', $version );
	}

	private function internal_url( string $url ): string {
		$url = trim( $url );
		if ( '' === $url || 1 === preg_match( '/[\x00-\x1F\x7F]/', $url ) || str_contains( $url, '\\' ) ) {
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

	private function exception( string $adapter_key, string $operation, Throwable $error ): WP_Error {
		do_action( 'supc_workflow_exception', $adapter_key, $operation, get_class( $error ) );
		return $this->error( 'workflow_adapter_exception', 'The native workflow could not complete safely.', $adapter_key );
	}

	private function error( string $code, string $message, string $adapter_key ): WP_Error {
		return new WP_Error( 'supc_' . $code, __( $message, 'sabri-universal-post-composer' ), array( 'adapter_key' => sanitize_key( $adapter_key ) ) );
	}
}
