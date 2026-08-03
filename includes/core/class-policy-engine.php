<?php
/**
 * Cross-workflow safety and governance policy engine.
 *
 * File 22 applies only common orchestration holds. Native owners retain their
 * complete medical, consent, copyright, moderation, and publication rules.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

namespace Sabri\UniversalComposer\Core;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Policy_Engine {
	private const PHASES = array( 'draft', 'validate', 'preview', 'submit', 'revision' );
	private const MEDICAL_TYPES  = array( 'clinical_case', 'patient_case', 'successful_case', 'disease', 'treatment', 'remedy', 'materia_medica', 'research' );
	private const CONTACT_FIELDS = array( 'phone', 'phone_number', 'whatsapp', 'whatsapp_number', 'seller_phone', 'seller_whatsapp' );
	private const REFERENCE_KEYS = array( 'references', 'citations', 'sources', 'evidence_references' );

	/**
	 * @param array<string,mixed> $payload Validated native-owner payload.
	 * @return array{hold_state:string,codes:array<int,string>}|WP_Error
	 */
	public function evaluate( int $user_id, string $adapter_key, array $payload, string $phase ): array|WP_Error {
		if ( $user_id <= 0 || ! Contract_Boundary::adapter_key( $adapter_key ) || ! in_array( $phase, self::PHASES, true ) ) {
			return $this->error( array( 'invalid_policy_context' ), 'security_hold' );
		}

		$type  = $this->content_type( $adapter_key, $payload );
		$codes = array();
		$hold  = 'clear';

		if ( $this->contains_raw_contact( $payload ) && 'marketplace' === $type ) {
			$codes[] = 'verified_seller_reference_required';
			$hold    = $this->stronger_hold( $hold, 'privacy_hold' );
		}

		if ( 'marketplace' === $type && ! $this->has_reference( $payload, array( 'seller_profile_reference', 'verified_seller_reference' ) ) ) {
			$codes[] = 'verified_seller_reference_required';
			$hold    = $this->stronger_hold( $hold, 'privacy_hold' );
		}

		if ( in_array( $type, array( 'patient_case', 'clinical_case', 'successful_case' ), true ) ) {
			if ( ! $this->truthy( $payload['anonymized'] ?? $payload['patient_anonymized'] ?? false ) ) {
				$codes[] = 'patient_anonymization_required';
				$hold    = $this->stronger_hold( $hold, 'privacy_hold' );
			}
			if ( ! $this->has_reference( $payload, array( 'consent_reference', 'patient_consent_reference' ) ) ) {
				$codes[] = 'patient_consent_reference_required';
				$hold    = $this->stronger_hold( $hold, 'privacy_hold' );
			}
		}

		$strict_phase = in_array( $phase, array( 'validate', 'preview', 'submit', 'revision' ), true );
		if ( $strict_phase && in_array( $type, self::MEDICAL_TYPES, true ) ) {
			if ( ! $this->has_any_references( $payload ) ) {
				$codes[] = 'medical_references_required';
				$hold    = $this->stronger_hold( $hold, 'medical_hold' );
			}
			if ( ! $this->truthy( $payload['medical_safety_acknowledged'] ?? $payload['safety_acknowledged'] ?? false ) ) {
				$codes[] = 'medical_safety_acknowledgement_required';
				$hold    = $this->stronger_hold( $hold, 'medical_hold' );
			}
			if ( $this->truthy( $payload['emergency_claim'] ?? false ) || $this->truthy( $payload['emergency_treatment_request'] ?? false ) ) {
				$codes[] = 'emergency_treatment_content_prohibited';
				$hold    = $this->stronger_hold( $hold, 'medical_hold' );
			}
		}


		if ( $strict_phase && $this->uses_third_party_material( $payload ) ) {
			if ( ! $this->truthy( $payload['rights_confirmed'] ?? $payload['copyright_permission_confirmed'] ?? false ) ) {
				$codes[] = 'copyright_rights_confirmation_required';
				$hold    = $this->stronger_hold( $hold, 'copyright_hold' );
			}
			if ( ! $this->has_reference( $payload, array( 'source_reference', 'license_reference', 'copyright_source_reference' ) ) ) {
				$codes[] = 'copyright_source_reference_required';
				$hold    = $this->stronger_hold( $hold, 'copyright_hold' );
			}
		}

		/**
		 * Native modules and governance services may add common hold codes, but
		 * cannot remove a File 22 hard hold already detected here.
		 *
		 * @param array<int,string> $codes
		 * @param array<string,mixed> $payload
		 */
		$filtered = apply_filters( 'supc_common_policy_codes', $codes, $user_id, $adapter_key, $type, $phase, $payload );
		if ( is_array( $filtered ) ) {
			foreach ( $filtered as $code ) {
				if ( is_string( $code ) && Contract_Boundary::code( $code ) ) {
					$codes[] = $code;
				}
			}
		}
		$codes = array_values( array_unique( $codes ) );
		if ( array() !== $codes ) {
			if ( 'clear' === $hold ) {
				$hold = 'security_hold';
			}
			return $this->error( $codes, $hold );
		}
		return array( 'hold_state' => 'clear', 'codes' => array() );
	}

	/** @param array<string,mixed> $payload */
	private function content_type( string $adapter_key, array $payload ): string {
		$candidates = array(
			$payload['content_type'] ?? null,
			$payload['publication_type'] ?? null,
			$payload['entry_type'] ?? null,
			$payload['mode'] ?? null,
			$adapter_key,
		);
		foreach ( $candidates as $candidate ) {
			if ( ! is_string( $candidate ) ) {
				continue;
			}
			$value = sanitize_key( $candidate );
			$value = match ( $value ) {
				'patient_case_publication', 'public_patient_case' => 'patient_case',
				'success_case', 'successful_patient_case'        => 'successful_case',
				'product', 'listing', 'marketplace_listing'       => 'marketplace',
				default => $value,
			};
			if ( '' !== $value ) {
				return Taxonomy_Map::canonical( $value );
			}
		}
		return 'standard_publication';
	}


	private function stronger_hold( string $current, string $candidate ): string {
		$priority = array(
			'clear'          => 0,
			'copyright_hold' => 10,
			'medical_hold'   => 20,
			'privacy_hold'   => 30,
			'security_hold'  => 40,
			'suspended'      => 50,
		);
		return ( $priority[ $candidate ] ?? 40 ) > ( $priority[ $current ] ?? 0 ) ? $candidate : $current;
	}

	/** @param array<string,mixed> $payload */
	private function contains_raw_contact( array $payload ): bool {
		foreach ( self::CONTACT_FIELDS as $key ) {
			if ( isset( $payload[ $key ] ) && is_scalar( $payload[ $key ] ) && '' !== trim( (string) $payload[ $key ] ) ) {
				return true;
			}
		}
		return false;
	}

	/** @param array<string,mixed> $payload @param array<int,string> $keys */
	private function has_reference( array $payload, array $keys ): bool {
		foreach ( $keys as $key ) {
			$value = $payload[ $key ] ?? null;
			if ( is_string( $value ) && 1 === preg_match( '/^[A-Za-z0-9][A-Za-z0-9._:-]{0,254}$/D', $value ) ) {
				return true;
			}
		}
		return false;
	}

	/** @param array<string,mixed> $payload */
	private function has_any_references( array $payload ): bool {
		foreach ( self::REFERENCE_KEYS as $key ) {
			$value = $payload[ $key ] ?? null;
			if ( is_array( $value ) && array() !== $value ) {
				return true;
			}
			if ( is_string( $value ) && '' !== trim( $value ) ) {
				return true;
			}
		}
		return false;
	}

	/** @param array<string,mixed> $payload */
	private function uses_third_party_material( array $payload ): bool {
		return $this->truthy( $payload['third_party_material'] ?? false )
			|| $this->truthy( $payload['copyrighted_material'] ?? false )
			|| $this->truthy( $payload['licensed_material'] ?? false );
	}

	private function truthy( mixed $value ): bool {
		return true === $value || 1 === $value || '1' === $value || 'yes' === strtolower( (string) $value ) || 'true' === strtolower( (string) $value );
	}

	/** @param array<int,string> $codes */
	private function error( array $codes, string $hold ): WP_Error {
		return new WP_Error(
			'supc_policy_violation',
			__( 'The Composer policy checks found requirements that must be resolved before this operation can continue.', 'sabri-universal-post-composer' ),
			array(
				'status'     => 422,
				'hold_state' => in_array( $hold, array( 'privacy_hold', 'medical_hold', 'copyright_hold', 'security_hold' ), true ) ? $hold : 'security_hold',
				'codes'      => array_values( array_unique( $codes ) ),
			)
		);
	}
}
