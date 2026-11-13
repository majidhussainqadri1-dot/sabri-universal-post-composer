<?php
/**
 * Strict bounded input and runtime-contract validation helpers.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

namespace Sabri\UniversalComposer\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Contract_Boundary {
	private const UNSAFE_TEXT_CONTROLS = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F-\x9F\x{202A}-\x{202E}\x{2066}-\x{2069}]/u';
	private const UNSAFE_TOKEN_CONTROLS = '/[\x00-\x1F\x7F-\x9F\x{202A}-\x{202E}\x{2066}-\x{2069}]/u';
	private const PUBLIC_POLICY_CODES = array(
		'invalid_policy_context',
		'unsafe_rich_text_markup_prohibited',
		'unsafe_link_or_embed_protocol',
		'citation_reference_limit_exceeded',
		'verified_seller_reference_required',
		'patient_anonymization_required',
		'patient_consent_reference_required',
		'patient_pii_name_detected',
		'patient_pii_email_detected',
		'patient_pii_phone_detected',
		'patient_pii_address_detected',
		'patient_pii_national_id_detected',
		'patient_pii_identity_number_detected',
		'patient_pii_record_number_detected',
		'patient_pii_context_identifier_detected',
		'patient_pii_location_detected',
		'successful_case_reference_required',
		'medical_references_required',
		'medical_safety_acknowledgement_required',
		'emergency_treatment_content_prohibited',
		'copyright_rights_confirmation_required',
		'copyright_source_reference_required',
	);


	public static function valid_utf8( string $value ): bool {
		return 1 === preg_match( '//u', $value );
	}

	public static function bounded_text( string $value, int $minimum_bytes, int $maximum_bytes, bool $allow_line_breaks = false ): bool {
		$length = strlen( $value );
		if ( $minimum_bytes < 0 || $maximum_bytes < $minimum_bytes || $length < $minimum_bytes || $length > $maximum_bytes || ! self::valid_utf8( $value ) ) {
			return false;
		}

		$pattern = $allow_line_breaks ? self::UNSAFE_TEXT_CONTROLS : self::UNSAFE_TOKEN_CONTROLS;
		return 0 === preg_match( $pattern, $value );
	}

	public static function canonical( string $value, string $pattern, int $maximum_bytes ): bool {
		return self::bounded_text( $value, 1, $maximum_bytes )
			&& $value === trim( $value )
			&& 1 === preg_match( $pattern, $value );
	}

	public static function adapter_key( string $value ): bool {
		return self::canonical( $value, '/^[a-z][a-z0-9_]{2,63}$/D', 64 );
	}

	public static function capability( string $value ): bool {
		return self::canonical( $value, '/^[a-z][a-z0-9_-]{0,63}$/D', 64 );
	}

	public static function native_module( string $value ): bool {
		return self::canonical( $value, '/^[a-z][a-z0-9-]{2,127}$/D', 128 );
	}

	public static function group( string $value ): bool {
		return self::canonical( $value, '/^[a-z][a-z0-9_]{0,63}$/D', 64 );
	}

	public static function code( string $value ): bool {
		return self::canonical( $value, '/^[a-z][a-z0-9_.:-]{0,63}$/D', 64 );
	}

	public static function field_key( string $value ): bool {
		return self::canonical( $value, '/^[a-z][a-z0-9_]{0,63}$/D', 64 );
	}

	public static function idempotency_key( string $value ): bool {
		return self::canonical( $value, '/^[A-Za-z0-9][A-Za-z0-9._:-]{15,127}$/D', 128 );
	}

	public static function version( string $value ): bool {
		return self::bounded_text( $value, 1, 255 ) && $value === trim( $value ) && Version::valid( $value );
	}

	public static function diagnostic_storage_key( string $candidate, string $prefix = 'invalid' ): string {
		if ( self::adapter_key( $candidate ) ) {
			return $candidate;
		}

		$prefix = preg_replace( '/[^a-z0-9_-]/', '', strtolower( $prefix ) ) ?? 'invalid';
		$prefix = '' !== $prefix ? substr( $prefix, 0, 24 ) : 'invalid';
		return '[' . $prefix . ']:' . substr( hash( 'sha256', $candidate ), 0, 20 );
	}

	public static function public_identifier( string $candidate ): string {
		return self::adapter_key( $candidate ) ? $candidate : 'invalid_adapter';
	}

	/**
	 * Reduce a native/store WP_Error payload to controlled public diagnostics.
	 * Raw session rows, idempotency keys, payload hashes, patient data and
	 * arbitrary adapter error data must never be copied into REST responses.
	 *
	 * @param mixed $raw_data Untrusted error data from an internal/native caller.
	 * @return array<string,mixed>
	 */
	public static function safe_rest_error_details( string $error_code, mixed $raw_data ): array {
		if ( 'supc_policy_violation' !== $error_code || ! is_array( $raw_data ) ) {
			return array();
		}
		$safe  = array();
		$holds = array( 'privacy_hold', 'medical_hold', 'copyright_hold', 'security_hold' );
		if ( is_string( $raw_data['hold_state'] ?? null ) && in_array( $raw_data['hold_state'], $holds, true ) ) {
			$safe['hold_state'] = $raw_data['hold_state'];
		}
		$codes = $raw_data['codes'] ?? null;
		if ( is_array( $codes ) && array_is_list( $codes ) ) {
			$public_codes = array();
			foreach ( array_slice( $codes, 0, 100 ) as $code ) {
				if ( is_string( $code ) && self::code( $code ) && in_array( $code, self::PUBLIC_POLICY_CODES, true ) ) {
					$public_codes[] = $code;
				}
			}
			if ( array() !== $public_codes ) {
				$safe['codes'] = array_values( array_unique( $public_codes ) );
			}
		}
		return $safe;
	}

	public static function declared_public_instance_method( object $object, string $method, int $required_parameters ): bool {
		if ( '' === $method || $required_parameters < 0 || ! method_exists( $object, $method ) ) {
			return false;
		}

		try {
			$reflection = new \ReflectionMethod( $object, $method );
			if ( ! $reflection->isPublic() || $reflection->isStatic() || $reflection->isAbstract() ) {
				return false;
			}
			if ( $reflection->getNumberOfRequiredParameters() !== $required_parameters || $reflection->getNumberOfParameters() !== $required_parameters ) {
				return false;
			}

			$parameters = $reflection->getParameters();
			if ( 1 === $required_parameters ) {
				$type = $parameters[0]->getType();
				if ( $type instanceof \ReflectionNamedType && ( $type->allowsNull() || 'int' !== $type->getName() ) ) {
					return false;
				}
			}

			$return_type = $reflection->getReturnType();
			return ! $return_type instanceof \ReflectionNamedType || ( ! $return_type->allowsNull() && 'array' === $return_type->getName() );
		} catch ( \Throwable $error ) {
			unset( $error );
			return false;
		}
	}
}
