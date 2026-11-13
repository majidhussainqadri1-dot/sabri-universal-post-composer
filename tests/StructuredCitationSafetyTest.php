<?php
/**
 * V12.1 CV-053: structured reference URLs are untrusted input and an opaque
 * owner record cannot bypass safe-link and DOI presence checks.
 */
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Sabri\UniversalComposer\Core\Policy_Engine;

final class StructuredCitationSafetyTest extends TestCase {
    private Policy_Engine $engine;

    protected function setUp(): void {
        $this->engine = new Policy_Engine();
    }

    /** @return array<string,mixed> */
    private function patientCase( array $references ): array {
        return array(
            'content_type' => 'patient_case',
            'anonymized' => true,
            'consent_reference' => 'consent:patient:1',
            'medical_safety_acknowledged' => true,
            'references' => $references,
        );
    }

    public function test_nested_malicious_urls_fail_even_when_another_reference_is_valid(): void {
        foreach ( array(
            'javascript:alert(1)',
            'data:text/html,evil',
            'ftp://example.test/file',
            'mailto:secret@example.test',
            '//example.test/p',
            'https://private@example.test/source',
            'https://example.test/' . str_repeat( 'x', 2050 ),
        ) as $bad_url ) {
            $result = $this->engine->evaluate(
                1,
                'social_publication',
                $this->patientCase( array( 'source:book:1', array( 'url' => $bad_url ) ) ),
                'submit'
            );
            self::assertInstanceOf( WP_Error::class, $result, $bad_url );
            self::assertContains( 'unsafe_link_or_embed_protocol', $result->data['codes'], $bad_url );
            self::assertSame( 'security_hold', $result->data['hold_state'] );
            self::assertStringNotContainsString( $bad_url, wp_json_encode( $result->data ) ?: '' );
        }
    }

    public function test_unsafe_scheme_inside_structured_opaque_reference_is_rejected(): void {
        $result = $this->engine->evaluate( 1, 'social_publication', $this->patientCase( array(
            array( 'source_reference' => 'javascript:danger' ),
            array( 'citation' => 'Hering, Guiding Symptoms, vol. 1' ),
        ) ), 'validate' );
        self::assertInstanceOf( WP_Error::class, $result );
        self::assertContains( 'unsafe_link_or_embed_protocol', $result->data['codes'] );
    }

    public function test_supported_https_urls_and_dois_remain_usable_native_locators(): void {
        foreach ( array(
            array( array( 'url' => 'https://example.test/book/23' ) ),
            array( array( 'doi' => '10.1000/182' ) ),
            array( array( 'source_reference' => 'source:book:1' ) ),
            array( array( 'source' => 'Kent Repertory 1897 p. 12' ) ),
        ) as $references ) {
            $result = $this->engine->evaluate( 1, 'social_publication', $this->patientCase( $references ), 'validate' );
            self::assertIsArray( $result );
            self::assertSame( 'clear', $result['hold_state'] );
        }
    }

    public function test_malformed_doi_is_not_evidence_if_it_is_only_locator(): void {
        foreach ( array( 'doi-not-set', '10.abc/xyz', '10.12/test', 'pending' ) as $invalid_doi ) {
            $result = $this->engine->evaluate( 1, 'social_publication', $this->patientCase( array(
                array( 'doi' => $invalid_doi ),
            ) ), 'validate' );
            self::assertInstanceOf( WP_Error::class, $result );
            self::assertContains( 'medical_references_required', $result->data['codes'] );
        }
    }

    public function test_citation_overflow_is_rejected_instead_of_skipping_unchecked_last_url(): void {
        $references = array_fill( 0, 100, 'source:book:1' );
        $references[] = array( 'url' => 'javascript:unscanned' );
        $result = $this->engine->evaluate( 1, 'social_publication', $this->patientCase( $references ), 'submit' );
        self::assertInstanceOf( WP_Error::class, $result );
        self::assertSame( 'security_hold', $result->data['hold_state'] );
        self::assertContains( 'unsafe_link_or_embed_protocol', $result->data['codes'] );
    }

    public function test_benign_overflow_is_also_denied_and_exactly_100_safe_references_still_pass(): void {
        $references = array_fill( 0, 100, 'source:book:1' );
        $accepted = $this->engine->evaluate( 1, 'social_publication', $this->patientCase( $references ), 'preview' );
        self::assertIsArray( $accepted );
        self::assertSame( 'clear', $accepted['hold_state'] );

        $references[] = 'source:book:101';
        $rejected = $this->engine->evaluate( 1, 'social_publication', $this->patientCase( $references ), 'preview' );
        self::assertInstanceOf( WP_Error::class, $rejected );
        self::assertContains( 'unsafe_link_or_embed_protocol', $rejected->data['codes'] );
    }

    public function test_unsafe_structured_source_in_nonmedical_content_also_fails_common_security(): void {
        $result = $this->engine->evaluate( 1, 'social_publication', array(
            'content_type' => 'standard_publication',
            'references' => array( array( 'url' => 'data:text/html,unsafe' ) ),
        ), 'preview' );
        self::assertInstanceOf( WP_Error::class, $result );
        self::assertSame( 'security_hold', $result->data['hold_state'] );
    }
}
