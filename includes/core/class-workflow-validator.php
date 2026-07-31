<?php
/**
 * Bounded workflow schema, payload, URL, and result validation.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

namespace Sabri\UniversalComposer\Core;

use Sabri\UniversalComposer\Contracts\Workflow_Adapter;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Workflow_Validator {
	private const MAX_PAYLOAD_BYTES = 1048576;
	private const MAX_SCHEMA_BYTES = 262144;
	private const MAX_RESULT_BYTES = 1048576;
	private const MAX_URL_BYTES = 2048;
	private const MAX_DEPTH = 12;
	private const MAX_TOTAL_NODES = 10000;
	private const MAX_ARRAY_ITEMS = 1000;
	private const MAX_SCHEMA_FIELDS = 100;
	private const MAX_CHOICES = 100;
	private const MAX_CODES = 100;
	private const MAX_PREVIEW_TTL = 1800;
	private const FIELD_TYPES = array( 'text', 'textarea', 'select', 'multiselect', 'checkbox', 'number', 'date', 'datetime', 'url', 'email', 'opaque_reference' );
	private const FIELD_PROPERTIES = array( 'type', 'label_code', 'description_code', 'required', 'privacy_class', 'minimum', 'maximum', 'choices' );
	private const PRIVACY = array( 'public', 'private', 'sensitive' );
	private const FINAL_STATUSES = array( 'draft', 'pending_review', 'scheduled', 'published', 'rejected', 'failed' );
	private const DRAFT_STATUSES = array( 'draft', 'pending_review' );
	private const REFERENCE_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._:-]{0,254}$/D';
	private const UUID_V4_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D';

	public function valid_reference( string $reference ): bool {
		return Contract_Boundary::bounded_text( $reference, 1, 255 )
			&& 1 === preg_match( self::REFERENCE_PATTERN, $reference );
	}

	public function valid_idempotency_key( string $key ): bool {
		$parts = explode( ':', $key );
		return 2 === count( $parts )
			&& $parts[0] !== $parts[1]
			&& 1 === preg_match( self::UUID_V4_PATTERN, $parts[0] )
			&& 1 === preg_match( self::UUID_V4_PATTERN, $parts[1] );
	}

	/**
	 * @param array{subject_schema_extension:bool} $contract Registration snapshot.
	 * @return array{version:string,fields:array<string,array<string,mixed>>}|WP_Error
	 */
	public function schema( Workflow_Adapter $adapter, array $contract, int $user_id, string $adapter_key ): array|WP_Error {
		try {
			$version = $adapter->schema_version();
			if ( ! Contract_Boundary::version( $version ) ) {
				return $this->error( 'invalid_schema_contract', $adapter_key );
			}
			$schema = $user_id > 0 && $contract['subject_schema_extension']
				? $adapter->schema_for_user( $user_id )
				: $adapter->schema();
			if ( ! is_array( $schema ) || ! $this->bounded_array( $schema, self::MAX_SCHEMA_BYTES ) ) {
				return $this->error( 'invalid_schema_contract', $adapter_key );
			}
			if (
				! array_key_exists( 'version', $schema ) ||
				! is_string( $schema['version'] ) ||
				$version !== $schema['version'] ||
				! array_key_exists( 'fields', $schema ) ||
				! is_array( $schema['fields'] )
			) {
				return $this->error( 'invalid_schema_contract', $adapter_key );
			}
			$fields = $this->fields( $schema['fields'] );
			return null === $fields
				? $this->error( 'invalid_schema_contract', $adapter_key )
				: array( 'version' => $version, 'fields' => $fields );
		} catch ( \Throwable $error ) {
			unset( $error );
			return $this->error( 'workflow_adapter_exception', $adapter_key );
		}
	}

	/**
	 * @param array<string,mixed> $payload Payload supplied by current subject.
	 * @param array{subject_schema_extension:bool} $contract Registration snapshot.
	 */
	public function payload(
		Workflow_Adapter $adapter,
		array $contract,
		array $payload,
		int $user_id,
		string $adapter_key,
		bool $require_required
	): ?WP_Error {
		$remaining = self::MAX_TOTAL_NODES;
		if ( ! $this->safe_value( $payload, 0, $remaining ) ) {
			return $this->error( 'invalid_workflow_payload', $adapter_key );
		}
		$encoded = wp_json_encode( $payload );
		if ( ! is_string( $encoded ) || strlen( $encoded ) > self::MAX_PAYLOAD_BYTES ) {
			return $this->error( 'workflow_payload_too_large', $adapter_key );
		}
		$schema = $this->schema( $adapter, $contract, $user_id, $adapter_key );
		if ( $schema instanceof WP_Error ) {
			return $schema;
		}
		foreach ( $payload as $key => $value ) {
			if ( ! is_string( $key ) || ! array_key_exists( $key, $schema['fields'] ) ) {
				return $this->error( 'workflow_payload_unknown_field', $adapter_key );
			}
			if ( ! $this->field_value( $schema['fields'][ $key ], $value ) ) {
				return $this->error( 'workflow_payload_field_invalid', $adapter_key );
			}
		}
		if ( $require_required ) {
			foreach ( $schema['fields'] as $key => $definition ) {
				if (
					true === $definition['required'] &&
					( ! array_key_exists( $key, $payload ) || $this->required_empty( $definition, $payload[ $key ] ) )
				) {
					return $this->error( 'workflow_payload_required_field_missing', $adapter_key );
				}
			}
		}
		return null;
	}

	/** @param mixed $result @return array{valid:bool,errors:array<int,string>,warnings:array<int,string>}|WP_Error */
	public function validation_result( mixed $result, string $adapter_key ): array|WP_Error {
		if (
			! is_array( $result ) ||
			! $this->bounded_array( $result, self::MAX_RESULT_BYTES ) ||
			! array_key_exists( 'valid', $result ) ||
			! is_bool( $result['valid'] )
		) {
			return $this->error( 'invalid_validation_result', $adapter_key );
		}
		$errors   = $this->codes( $result['errors'] ?? array() );
		$warnings = $this->codes( $result['warnings'] ?? array() );
		if ( null === $errors || null === $warnings || array_intersect( $errors, $warnings ) ) {
			return $this->error( 'invalid_validation_result', $adapter_key );
		}
		if ( $result['valid'] && array() !== $errors ) {
			return $this->error( 'invalid_validation_result', $adapter_key );
		}
		if ( ! $result['valid'] && array() === $errors ) {
			$errors[] = 'native_validation_failed';
		}
		return array( 'valid' => $result['valid'], 'errors' => $errors, 'warnings' => $warnings );
	}

	/** @param mixed $result @return array{preview_url:string,expires_at:int}|WP_Error */
	public function preview_result( mixed $result, string $adapter_key ): array|WP_Error {
		if ( ! is_array( $result ) || ! $this->bounded_array( $result, self::MAX_RESULT_BYTES ) ) {
			return $this->error( 'invalid_preview_result', $adapter_key );
		}
		$url        = isset( $result['preview_url'] ) && is_string( $result['preview_url'] ) ? $this->internal_url( $result['preview_url'] ) : '';
		$expires_at = $result['expires_at'] ?? null;
		$now        = time();
		if ( '' === $url || ! is_int( $expires_at ) || $expires_at <= $now || $expires_at > $now + self::MAX_PREVIEW_TTL ) {
			return $this->error( 'invalid_preview_result', $adapter_key );
		}
		return array( 'preview_url' => $url, 'expires_at' => $expires_at );
	}

	/** @param mixed $result @return array<string,mixed>|WP_Error */
	public function draft_result( mixed $result, string $adapter_key ): array|WP_Error {
		if ( ! is_array( $result ) || ! $this->bounded_array( $result, self::MAX_RESULT_BYTES ) ) {
			return $this->error( 'invalid_native_result', $adapter_key );
		}
		$reference = $result['native_reference'] ?? null;
		$status    = $result['status'] ?? null;
		if (
			! is_string( $reference ) ||
			! $this->valid_reference( $reference ) ||
			! is_string( $status ) ||
			! in_array( $status, self::DRAFT_STATUSES, true )
		) {
			return $this->error( 'invalid_native_result', $adapter_key );
		}
		return array( 'native_reference' => $reference, 'status' => $status );
	}

	/** @param mixed $result @return array<string,mixed>|WP_Error */
	public function status_result( mixed $result, string $adapter_key ): array|WP_Error {
		if ( ! is_array( $result ) || ! $this->bounded_array( $result, self::MAX_RESULT_BYTES ) ) {
			return $this->error( 'invalid_native_result', $adapter_key );
		}
		$reference = $result['native_reference'] ?? null;
		$status    = $result['status'] ?? null;
		if (
			! is_string( $reference ) ||
			! $this->valid_reference( $reference ) ||
			! is_string( $status ) ||
			! in_array( $status, self::FINAL_STATUSES, true )
		) {
			return $this->error( 'invalid_native_status', $adapter_key );
		}
		$normalized = array( 'native_reference' => $reference, 'status' => $status );
		if ( array_key_exists( 'canonical_url', $result ) ) {
			if ( ! is_string( $result['canonical_url'] ) ) {
				return $this->error( 'invalid_canonical_url', $adapter_key );
			}
			$url = $this->internal_url( $result['canonical_url'] );
			if ( '' === $url ) {
				return $this->error( 'invalid_canonical_url', $adapter_key );
			}
			$normalized['canonical_url'] = $url;
		}
		return $normalized;
	}

	public function internal_url( string $url ): string {
		if (
			$url !== trim( $url ) ||
			! Contract_Boundary::bounded_text( $url, 1, self::MAX_URL_BYTES ) ||
			str_contains( $url, '\\' )
		) {
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
		if (
			'https' !== $target_scheme ||
			'https' !== $home_scheme ||
			'' === $target_host ||
			$target_host !== $home_host ||
			isset( $target['user'] ) ||
			isset( $target['pass'] )
		) {
			return '';
		}
		$target_port = isset( $target['port'] ) ? (int) $target['port'] : 443;
		$home_port   = isset( $home['port'] ) ? (int) $home['port'] : 443;
		return $target_port === $home_port ? $validated : '';
	}

	/** @param array<mixed> $fields @return array<string,array<string,mixed>>|null */
	private function fields( array $fields ): ?array {
		if ( count( $fields ) > self::MAX_SCHEMA_FIELDS ) {
			return null;
		}
		$normalized = array();
		foreach ( $fields as $key => $definition ) {
			if (
				! is_string( $key ) ||
				! Contract_Boundary::field_key( $key ) ||
				! is_array( $definition ) ||
				array() !== array_diff( array_keys( $definition ), self::FIELD_PROPERTIES )
			) {
				return null;
			}
			$type = $definition['type'] ?? null;
			$label = $definition['label_code'] ?? null;
			$privacy = $definition['privacy_class'] ?? null;
			$required = $definition['required'] ?? false;
			if (
				! is_string( $type ) || ! in_array( $type, self::FIELD_TYPES, true ) ||
				! is_string( $label ) || ! Contract_Boundary::code( $label ) ||
				! is_string( $privacy ) || ! in_array( $privacy, self::PRIVACY, true ) ||
				! is_bool( $required )
			) {
				return null;
			}
			$field = array( 'type' => $type, 'label_code' => $label, 'required' => $required, 'privacy_class' => $privacy );
			if ( array_key_exists( 'description_code', $definition ) ) {
				if ( ! is_string( $definition['description_code'] ) || ! Contract_Boundary::code( $definition['description_code'] ) ) {
					return null;
				}
				$field['description_code'] = $definition['description_code'];
			}
			foreach ( array( 'minimum', 'maximum' ) as $bound ) {
				if ( array_key_exists( $bound, $definition ) ) {
					$value = $definition[ $bound ];
					if (
						'number' !== $type ||
						( ! is_int( $value ) && ! is_float( $value ) ) ||
						( is_float( $value ) && ! is_finite( $value ) )
					) {
						return null;
					}
					$field[ $bound ] = $value;
				}
			}
			if ( isset( $field['minimum'], $field['maximum'] ) && $field['minimum'] > $field['maximum'] ) {
				return null;
			}
			$is_choice = in_array( $type, array( 'select', 'multiselect' ), true );
			if ( $is_choice ) {
				$choices = $definition['choices'] ?? null;
				if ( ! is_array( $choices ) || array() === $choices || count( $choices ) > self::MAX_CHOICES ) {
					return null;
				}
				$field['choices'] = array();
				foreach ( $choices as $choice_key => $choice_label ) {
					if (
						! is_string( $choice_key ) ||
						! Contract_Boundary::field_key( $choice_key ) ||
						! is_string( $choice_label ) ||
						! Contract_Boundary::code( $choice_label )
					) {
						return null;
					}
					$field['choices'][ $choice_key ] = $choice_label;
				}
			} elseif ( array_key_exists( 'choices', $definition ) ) {
				return null;
			}
			$normalized[ $key ] = $field;
		}
		return $normalized;
	}

	/** @param array<string,mixed> $definition */
	private function field_value( array $definition, mixed $value ): bool {
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
			return ( ! isset( $definition['minimum'] ) || $value >= $definition['minimum'] )
				&& ( ! isset( $definition['maximum'] ) || $value <= $definition['maximum'] );
		}
		if ( 'select' === $type ) {
			return is_string( $value ) && array_key_exists( $value, $definition['choices'] );
		}
		if ( 'multiselect' === $type ) {
			if ( ! is_array( $value ) || array_values( $value ) !== $value || count( $value ) > self::MAX_CHOICES ) {
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
			return is_string( $value ) && ( '' === $value || $this->valid_reference( $value ) );
		}
		if ( 'email' === $type ) {
			return is_string( $value ) && ( '' === $value || false !== is_email( $value ) );
		}
		if ( 'url' === $type ) {
			return is_string( $value ) && ( '' === $value || $this->http_url( $value ) );
		}
		if ( 'date' === $type ) {
			return is_string( $value ) && ( '' === $value || $this->date( $value ) );
		}
		if ( 'datetime' === $type ) {
			return is_string( $value ) && ( '' === $value || $this->datetime( $value ) );
		}
		return false;
	}

	/** @param array<string,mixed> $definition */
	private function required_empty( array $definition, mixed $value ): bool {
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

	/** @return array<int,string>|null */
	private function codes( mixed $codes ): ?array {
		if ( ! is_array( $codes ) || array_values( $codes ) !== $codes || count( $codes ) > self::MAX_CODES ) {
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

	/** @param array<mixed> $value */
	private function bounded_array( array $value, int $maximum_bytes ): bool {
		$remaining = self::MAX_TOTAL_NODES;
		if ( ! $this->safe_value( $value, 0, $remaining ) ) {
			return false;
		}
		$encoded = wp_json_encode( $value );
		return is_string( $encoded ) && strlen( $encoded ) <= $maximum_bytes;
	}

	private function safe_value( mixed $value, int $depth, int &$remaining ): bool {
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
			if ( is_string( $key ) && ( strlen( $key ) > 255 || ! Contract_Boundary::valid_utf8( $key ) ) ) {
				return false;
			}
			if ( ! $this->safe_value( $item, $depth + 1, $remaining ) ) {
				return false;
			}
		}
		return true;
	}

	private function http_url( string $value ): bool {
		if (
			! Contract_Boundary::bounded_text( $value, 1, self::MAX_URL_BYTES ) ||
			false === filter_var( $value, FILTER_VALIDATE_URL ) ||
			str_contains( $value, '\\' )
		) {
			return false;
		}
		$parts = wp_parse_url( $value );
		if ( ! is_array( $parts ) ) {
			return false;
		}
		$scheme = strtolower( (string) ( $parts['scheme'] ?? '' ) );
		$port   = isset( $parts['port'] ) ? (int) $parts['port'] : 0;
		return in_array( $scheme, array( 'http', 'https' ), true )
			&& '' !== (string) ( $parts['host'] ?? '' )
			&& ! isset( $parts['user'], $parts['pass'] )
			&& ( 0 === $port || ( $port >= 1 && $port <= 65535 ) );
	}

	private function date( string $value ): bool {
		if ( 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/D', $value, $parts ) ) {
			return false;
		}
		$year = (int) $parts[1];
		return $year >= 1 && checkdate( (int) $parts[2], (int) $parts[3], $year );
	}

	private function datetime( string $value ): bool {
		$pattern = '/^(\d{4}-\d{2}-\d{2})[T ](\d{2}):(\d{2})(?::(\d{2}))?(Z|[+-](\d{2}):(\d{2}))?$/D';
		if ( 1 !== preg_match( $pattern, $value, $parts ) || ! $this->date( $parts[1] ) ) {
			return false;
		}
		$hour   = (int) $parts[2];
		$minute = (int) $parts[3];
		$second = isset( $parts[4] ) && '' !== $parts[4] ? (int) $parts[4] : 0;
		if ( $hour > 23 || $minute > 59 || $second > 59 ) {
			return false;
		}
		$zone = $parts[5] ?? '';
		if ( '' === $zone || 'Z' === $zone ) {
			return true;
		}
		$zone_hour   = (int) ( $parts[6] ?? 0 );
		$zone_minute = (int) ( $parts[7] ?? 0 );
		return $zone_minute <= 59 && ( $zone_hour < 14 || ( 14 === $zone_hour && 0 === $zone_minute ) );
	}

	private function error( string $code, string $adapter_key ): WP_Error {
		return new WP_Error(
			'supc_' . $code,
			__( 'The workflow contract is invalid.', 'sabri-universal-post-composer' ),
			array( 'adapter_key' => Contract_Boundary::public_identifier( $adapter_key ) )
		);
	}
}
