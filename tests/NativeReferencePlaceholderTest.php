<?php
/**
 * WP-033/035/037: placeholder values are not valid native opaque references.
 * This is only the File 22 common boundary, not native source existence proof.
 */
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Sabri\UniversalComposer\Core\Policy_Engine;

final class NativeReferencePlaceholderTest extends TestCase {
    private Policy_Engine $engine;

    protected function setUp(): void {
        $this->engine = new Policy_Engine();
    }

    private function policy( array $payload ): array|WP_Error {
        return $this->engine->evaluate( 1, 'social_publication', $payload, 'submit' );
    }

    public function test_patient_consent_placeholder_never_satisfies_consent_presence(): void {
        foreach ( array( 'pending', 'unknown', 'none', 'tbd', 'placeholder', 'false', '0', '1', 'not_available' ) as $value ) {
            $outcome = $this->policy( array(
                'content_type' => 'patient_case',
                'anonymized' => true,
                'consent_reference' => $value,
                'references' => array( 'source:book:1' ),
                'medical_safety_acknowledged' => true,
            ) );
            self::assertInstanceOf( WP_Error::class, $outcome );
            self::assertContains( 'patient_consent_reference_required', $outcome->data['codes'], $value );
            self::assertSame( 'privacy_hold', $outcome->data['hold_state'] );
        }
    }

    public function test_successful_case_placeholder_does_not_replace_documented_case_reference(): void {
        foreach ( array( 'pending', 'none', 'tbd', '0' ) as $value ) {
            $result = $this->policy( array(
                'content_type' => 'disease',
                'successful_case_reference' => $value,
                'successful_case_references' => array( 'unknown', 'tbd' ),
                'references' => array( 'source:book:1' ),
                'medical_safety_acknowledged' => true,
            ) );
            self::assertInstanceOf( WP_Error::class, $result );
            self::assertContains( 'successful_case_reference_required', $result->data['codes'], $value );
        }
    }

    public function test_marketplace_seller_and_copyright_sources_cannot_be_placeholders(): void {
        $seller = $this->policy( array(
            'content_type' => 'marketplace',
            'seller_profile_reference' => 'pending',
        ) );
        self::assertInstanceOf( WP_Error::class, $seller );
        self::assertContains( 'verified_seller_reference_required', $seller->data['codes'] );

        $copyright = $this->policy( array(
            'content_type' => 'standard_publication',
            'third_party_material' => true,
            'rights_confirmed' => true,
            'source_reference' => 'unknown',
        ) );
        self::assertInstanceOf( WP_Error::class, $copyright );
        self::assertContains( 'copyright_source_reference_required', $copyright->data['codes'] );
    }

    public function test_real_opaque_reference_formats_remain_eligible_for_native_review(): void {
        foreach ( array(
            array(
                'content_type' => 'patient_case',
                'anonymized' => true,
                'consent_reference' => 'consent:patient:1',
                'references' => array( 'source:book:1' ),
                'medical_safety_acknowledged' => true,
            ),
            array(
                'content_type' => 'disease',
                'successful_case_reference' => 'case:verified:1',
                'references' => array( 'source:book:1' ),
                'medical_safety_acknowledged' => true,
            ),
            array(
                'content_type' => 'marketplace',
                'seller_profile_reference' => 'seller:verified:1',
            ),
            array(
                'content_type' => 'standard_publication',
                'third_party_material' => true,
                'rights_confirmed' => true,
                'source_reference' => 'rights:license:1',
            ),
        ) as $payload ) {
            $result = $this->policy( $payload );
            self::assertIsArray( $result );
            self::assertSame( 'clear', $result['hold_state'] );
        }
    }

    public function test_untrusted_link_protocols_stay_blocked_without_rejecting_opaque_native_sources(): void {
        $valid = $this->policy( array(
            'content_type' => 'standard_publication',
            'third_party_material' => true,
            'rights_confirmed' => true,
            'source_reference' => 'rights:license:1',
        ) );
        self::assertIsArray( $valid );

        foreach ( array(
            array( 'source_reference' => 'javascript:alert' ),
            array( 'source_reference' => 'data:text/html,script' ),
            array( 'source_url' => 'ftp://example.test/resource' ),
            array( 'link_url' => 'mailto:fake@example.test' ),
        ) as $field ) {
            $result = $this->policy( array_merge( array(
                'content_type' => 'standard_publication',
            ), $field ) );
            self::assertInstanceOf( WP_Error::class, $result );
            self::assertContains( 'unsafe_link_or_embed_protocol', $result->data['codes'] );
        }
    }

    public function test_draft_phase_still_allows_incomplete_external_proof(): void {
        $result = $this->engine->evaluate( 1, 'social_publication', array(
            'content_type' => 'patient_case',
            'anonymized' => false,
            'consent_reference' => 'pending',
        ), 'draft' );
        self::assertInstanceOf( WP_Error::class, $result );
        self::assertContains( 'patient_consent_reference_required', $result->data['codes'] );
        // Patient privacy holds remain effective even at draft phase.
    }
}
