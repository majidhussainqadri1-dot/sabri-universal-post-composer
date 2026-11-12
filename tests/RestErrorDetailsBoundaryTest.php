<?php
/**
 * WP-028 / WP-051: prevent internal WP_Error data from escaping private REST.
 */
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Sabri\UniversalComposer\Core\Contract_Boundary;
use Sabri\UniversalComposer\Http\Rest_Controller;
use Sabri\UniversalComposer\Http\Plan_Rest_Controller;
use Sabri\UniversalComposer\Http\Reconciliation_Rest_Controller;

final class PrivateRestDataCarrierError extends WP_Error {
    public function get_error_data( $code = '' ) {
        unset( $code );
        return $this->data;
    }
}

final class RestErrorDetailsBoundaryTest extends TestCase {
    /** @return array<class-string> */
    private function controllers(): array {
        return array(
            Rest_Controller::class,
            Plan_Rest_Controller::class,
            Reconciliation_Rest_Controller::class,
        );
    }

    private function normalize( string $class, WP_Error $error, array $extra = array() ): WP_Error {
        $object = ( new ReflectionClass( $class ) )->newInstanceWithoutConstructor();
        return ( new ReflectionMethod( $class, 'normalize_error' ) )->invoke( $object, $error, $extra );
    }

    public function test_conflict_errors_do_not_expose_private_current_session_rows(): void {
        $raw = array(
            'current'         => array(
                'user_id'         => 42,
                'idempotency_key' => 'private-uuid-v4:private-uuid-v4',
                'payload_hash'    => str_repeat( 'a', 64 ),
                'native_reference' => 'post-sensitive',
                'patient_notes'   => 'never-expose',
            ),
            'secret'          => 'secret-value',
            'native_code'     => 'private_lookup',
            'adapter_key'     => 'internal',
            'status'          => 409,
        );

        foreach ( $this->controllers() as $controller ) {
            $outcome = $this->normalize( $controller, new PrivateRestDataCarrierError( 'supc_session_conflict', 'Error', $raw ) );
            self::assertSame( 409, $outcome->data['status'], $controller );
            self::assertSame( array(), $outcome->data['details'], 'No raw current-session data in ' . $controller );
            self::assertStringNotContainsString( 'private-uuid', json_encode( $outcome->data ) ?: '' );
        }
    }

    public function test_controlled_policy_holds_and_known_codes_survive_without_arbitrary_data(): void {
        $raw = array(
            'status' => 422,
            'hold_state' => 'privacy_hold',
            'codes' => array( 'patient_consent_reference_required', 'unknown_private_patient_1234', 'patient_pii_phone_detected' ),
            'current' => array( 'patient_notes' => 'confidential' ),
        );
        $expected = array(
            'hold_state' => 'privacy_hold',
            'codes' => array( 'patient_consent_reference_required', 'patient_pii_phone_detected' ),
        );
        self::assertSame( $expected, Contract_Boundary::safe_rest_error_details( 'supc_policy_violation', $raw ) );
        foreach ( $this->controllers() as $controller ) {
            $outcome = $this->normalize( $controller, new PrivateRestDataCarrierError( 'supc_policy_violation', 'Error', $raw ) );
            self::assertSame( $expected, $outcome->data['details'] );
            self::assertSame( 422, $outcome->data['status'], $controller );
        }
    }

    public function test_only_explicitly_curated_extra_details_are_forwarded(): void {
        $raw = array(
            'current' => array( 'idempotency_key' => 'private-key' ),
            'status' => 409,
        );
        $extra = array( 'session' => array( 'lock_version' => 7 ) );
        foreach ( $this->controllers() as $controller ) {
            $outcome = $this->normalize( $controller, new PrivateRestDataCarrierError( 'supc_session_conflict', 'Error', $raw ), $extra );
            self::assertSame( $extra, $outcome->data['details'] );
        }
    }

    public function test_reject_non_list_unbounded_or_invalid_policy_code_data(): void {
        self::assertSame( array(), Contract_Boundary::safe_rest_error_details( 'supc_session_conflict', array( 'hold_state' => 'privacy_hold' ) ) );
        self::assertSame( array(), Contract_Boundary::safe_rest_error_details( 'supc_policy_violation', 'not-an-array' ) );
        self::assertSame(
            array(),
            Contract_Boundary::safe_rest_error_details(
                'supc_policy_violation',
                array( 'hold_state' => 'private_patient_1234', 'codes' => array( 'nested' => 'patient_pii_phone_detected' ) )
            )
        );
        $many = array_fill( 0, 150, 'patient_pii_phone_detected' );
        self::assertSame(
            array( 'codes' => array( 'patient_pii_phone_detected' ) ),
            Contract_Boundary::safe_rest_error_details( 'supc_policy_violation', array( 'codes' => $many ) )
        );
    }

    public function test_native_error_mapping_does_not_echo_native_diagnostic_payload(): void {
        $raw = array( 'native_code' => 'native_error', 'adapter_key' => 'internal_secret', 'operation' => 'submit', 'patient_text' => 'private' );
        foreach ( $this->controllers() as $controller ) {
            $outcome = $this->normalize( $controller, new PrivateRestDataCarrierError( 'supc_native_workflow_error', 'Error', $raw ) );
            self::assertSame( array(), $outcome->data['details'] );
        }
    }
}
