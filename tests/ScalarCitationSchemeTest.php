<?php
/**
 * Regression: scalar strings in citation arrays must receive the same
 * unsafe-scheme checks as structured citations and top-level source fields.
 */
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Sabri\UniversalComposer\Core\Policy_Engine;

final class ScalarCitationSchemeTest extends TestCase {
    private Policy_Engine $engine;

    protected function setUp(): void {
        $this->engine = new Policy_Engine();
    }

    /** @return array<string,mixed> */
    private function clinical_payload( array $references ): array {
        return array(
            'content_type' => 'patient_case',
            'anonymized' => true,
            'consent_reference' => 'consent:patient:1',
            'medical_safety_acknowledged' => true,
            'references' => $references,
        );
    }

    public function test_unsafe_scalar_citation_is_rejected_even_with_valid_evidence(): void {
        foreach ( array(
            'javascript:alert(1)',
            'vbscript:msgbox(1)',
            'data:text/html,test',
            'file:///etc/passwd',
            'ftp://example.test/reference',
            'mailto:someone@example.test',
            'about:blank',
            'intent://example.test/#Intent',
        ) as $unsafe ) {
            foreach ( array( 'validate', 'preview', 'submit', 'revision' ) as $phase ) {
                $result = $this->engine->evaluate(
                    1,
                    'social_publication',
                    $this->clinical_payload( array( 'source:book:1', $unsafe ) ),
                    $phase
                );
                self::assertInstanceOf( WP_Error::class, $result, $unsafe );
                self::assertContains( 'unsafe_link_or_embed_protocol', $result->data['codes'], $unsafe );
                self::assertSame( 'security_hold', $result->data['hold_state'] );
                self::assertStringNotContainsString( $unsafe, wp_json_encode( $result->data ) ?: '' );
            }
        }
    }

    public function test_reference_strings_and_structured_doi_still_work(): void {
        foreach ( array(
            array( 'source:book:1' ),
            array( 'Hering, Guiding Symptoms, vol. 1' ),
            array( array( 'doi' => '10.1000/182' ) ),
            array( array( 'url' => 'https://example.test/book/42' ) ),
        ) as $references ) {
            $result = $this->engine->evaluate(
                1,
                'social_publication',
                $this->clinical_payload( $references ),
                'submit'
            );
            self::assertIsArray( $result );
            self::assertSame( 'clear', $result['hold_state'] );
        }
    }

    public function test_nonmedical_citation_arrays_are_not_exempt_from_security_rules(): void {
        $result = $this->engine->evaluate( 1, 'social_publication', array(
            'content_type' => 'standard_publication',
            'citations' => array( 'source:book:1', 'javascript:alert(1)' ),
        ), 'preview' );
        self::assertInstanceOf( WP_Error::class, $result );
        self::assertContains( 'unsafe_link_or_embed_protocol', $result->data['codes'] );
    }
}
