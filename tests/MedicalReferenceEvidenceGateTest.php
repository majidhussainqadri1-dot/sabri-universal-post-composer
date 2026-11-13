<?php
/**
 * Medical reference presence is a security/safety gate, not a JSON container
 * occupancy check. The actual scholarly verification belongs to native owner.
 */
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Sabri\UniversalComposer\Core\Policy_Engine;

final class MedicalReferenceEvidenceGateTest extends TestCase {
    private Policy_Engine $engine;

    protected function setUp(): void {
        $this->engine = new Policy_Engine();
    }

    /** @return array<string,mixed> */
    private function clinicalPayload( mixed $references ): array {
        return array(
            'content_type' => 'patient_case',
            'anonymized' => true,
            'consent_reference' => 'consent:patient:1',
            'medical_safety_acknowledged' => true,
            'references' => $references,
        );
    }

    public function test_empty_or_fictitious_reference_containers_do_not_bypass_medical_hold(): void {
        foreach ( array(
            array( null ),
            array( false ),
            array( '' ),
            array( '  ' ),
            array( 'n/a' ),
            array( 'pending' ),
            array( array() ),
            array( array( 'bogus_field' => 'present' ) ),
            array( array( 'doi' => null ) ),
            array( array( 'reference' => 'n/a' ) ),
            array( array( 'source' => array( 'bogus' => 'x' ) ) ),
            array( '0' ),
        ) as $references ) {
            foreach ( array( 'validate', 'preview', 'submit', 'revision' ) as $phase ) {
                $result = $this->engine->evaluate( 1, 'social_publication', $this->clinicalPayload( $references ), $phase );
                self::assertInstanceOf( WP_Error::class, $result, $phase );
                self::assertContains( 'medical_references_required', $result->data['codes'], $phase );
                self::assertSame( 'medical_hold', $result->data['hold_state'] );
                self::assertStringNotContainsString( 'bogus_field', wp_json_encode( $result->data ) ?: '' );
            }
        }
    }

    public function test_recognized_opaque_and_structured_native_references_remain_valid(): void {
        foreach ( array(
            array( 'source:book:1' ),
            array( array( 'reference' => 'source:book:1' ) ),
            array( array( 'doi' => '10.1000/clinical.2026' ) ),
            array( array( 'url' => 'https://example.test/book/42' ) ),
            array( array( 'source' => 'Kent Materia Medica, p. 42' ) ),
            'Allen:Keynotes:42',
            array( array( 'bogus_field' => 'x' ), array( 'citation' => 'Hering, Guiding Symptoms, vol. 1' ) ),
        ) as $references ) {
            $result = $this->engine->evaluate( 1, 'social_publication', $this->clinicalPayload( $references ), 'validate' );
            self::assertIsArray( $result );
            self::assertSame( 'clear', $result['hold_state'] );
        }
    }

    public function test_draft_stage_stays_editable_without_a_finished_citation(): void {
        $result = $this->engine->evaluate( 1, 'social_publication', $this->clinicalPayload( array( null ) ), 'draft' );
        self::assertIsArray( $result );
        self::assertSame( 'clear', $result['hold_state'] );
    }

    public function test_other_native_owner_does_not_receive_new_medical_only_hold(): void {
        $payload = array(
            'content_type' => 'standard_publication',
            'references' => array( null ),
        );
        $result = $this->engine->evaluate( 1, 'social_publication', $payload, 'submit' );
        self::assertIsArray( $result );
    }
}
