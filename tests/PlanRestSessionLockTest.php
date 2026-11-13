<?php
/**
 * Source + behaviour regression for File 22's shared session lock boundary.
 * This tests busy-lock denials without a native owner or production database.
 */
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Sabri\UniversalComposer\Http\Plan_Rest_Controller;

if ( ! class_exists( 'WP_REST_Request', false ) ) {
    class WP_REST_Request implements ArrayAccess {
        private array $params = array();
        private string $raw_body = '';

        public function set_body( string $body ): void {
            $this->raw_body = $body;
        }

        public function get_body(): string {
            return $this->raw_body;
        }

        public function get_json_params(): array {
            throw new RuntimeException( 'Oversized JSON must not be decoded.' );
        }

        public function offsetExists( mixed $offset ): bool {
            return isset( $this->params[$offset] );
        }
        public function offsetGet( mixed $offset ): mixed {
            return $this->params[$offset] ?? null;
        }
        public function offsetSet( mixed $offset, mixed $value ): void {
            $this->params[$offset] = $value;
        }
        public function offsetUnset( mixed $offset ): void {
            unset( $this->params[$offset] );
        }
    }
}

final class PlanRestSessionLockTest extends TestCase {
    private array $prior_options;

    protected function setUp(): void {
        $this->prior_options = $GLOBALS['supc_test_options'] ?? array();
    }

    protected function tearDown(): void {
        $GLOBALS['supc_test_options'] = $this->prior_options;
    }

    /** @return list<string> */
    private function protectedEndpoints(): array {
        return array( 'delete_session', 'revision', 'begin_upload', 'complete_upload', 'cancel_upload' );
    }

    public function test_all_five_mutations_reject_a_busy_cross_controller_session_lock(): void {
        $uuid = '123e4567-e89b-42d3-a456-426614174000';
        $key = 'supc_lock_' . hash( 'sha256', $uuid );
        $GLOBALS['supc_test_options'][$key] = array(
            'token' => 'another-worker',
            'expires_at' => time() + 1000,
        );
        $request = new WP_REST_Request();
        $request['session'] = $uuid;

        $controller = ( new ReflectionClass( Plan_Rest_Controller::class ) )->newInstanceWithoutConstructor();
        foreach ( $this->protectedEndpoints() as $method ) {
            $result = $controller->$method( $request );
            self::assertInstanceOf( WP_Error::class, $result, $method );
            self::assertSame( 'supc_session_busy', $result->get_error_code(), $method );
            self::assertSame( 'another-worker', $GLOBALS['supc_test_options'][$key]['token'], $method );
        }
    }

    public function test_each_native_mutation_holds_and_releases_same_lock_in_finally(): void {
        $source = (string) file_get_contents( dirname( __DIR__ ) . '/includes/http/class-plan-rest-controller.php' );
        foreach ( $this->protectedEndpoints() as $method ) {
            $start = strpos( $source, 'public function ' . $method . '(' );
            self::assertIsInt( $start );
            $end = strpos( $source, "\n\t}\n", $start );
            self::assertIsInt( $end );
            $body = substr( $source, $start, $end - $start );
            self::assertStringContainsString( '$this->acquire_session_lock( $uuid )', $body, $method );
            self::assertStringContainsString( "'session_busy', 409", $body, $method );
            self::assertStringContainsString( 'finally {', $body, $method );
            self::assertStringContainsString( '$this->release_session_lock( $uuid, $token )', $body, $method );
            self::assertLessThan( strpos( $body, 'finally {' ), strpos( $body, 'try {' ), $method );
        }
    }

    public function test_draft_discard_requires_explicit_current_lock_version_before_native_delete(): void {
        $source = (string) file_get_contents( dirname( __DIR__ ) . '/includes/http/class-plan-rest-controller.php' );
        $start = strpos( $source, 'public function delete_session(' );
        $end = strpos( $source, 'public function status(', $start );
        self::assertIsInt( $start );
        self::assertIsInt( $end );
        $method = substr( $source, $start, $end - $start );

        self::assertStringContainsString( "\$lock = \$body['lock_version'] ?? null;", $method );
        self::assertStringContainsString( "! is_int( \$lock ) || \$lock !== (int) \$session['lock_version']", $method );
        self::assertStringNotContainsString( "?: (int) \$session['lock_version']", $method );
        self::assertLessThan(
            strpos( $method, '$this->coordinator->discard_draft' ),
            strpos( $method, '! is_int( $lock )' ),
            'Reject stale or missing client version before irreversible native deletion.'
        );
    }

    public function test_oversized_actual_lifecycle_body_is_denied_before_json_decode(): void {
        $request = new WP_REST_Request();
        $request->set_body( str_repeat( 'x', 1048577 ) );
        $runtime = ( new ReflectionClass( \Sabri\UniversalComposer\Core\Governing_Plan_Runtime::class ) )->newInstanceWithoutConstructor();
        $result = $runtime->rest_lifecycle_execute( $request );

        self::assertInstanceOf( WP_Error::class, $result );
        self::assertSame( 'supc_request_too_large', $result->get_error_code() );
        self::assertSame( 413, $result->data['status'] );
    }

    public function test_plan_and_durable_submission_controllers_share_the_same_option_lock_namespace(): void {
        $base = dirname( __DIR__ ) . '/includes/http/';
        $plan = (string) file_get_contents( $base . 'class-plan-rest-controller.php' );
        $reconciliation = (string) file_get_contents( $base . 'class-reconciliation-rest-controller.php' );
        foreach ( array( $plan, $reconciliation ) as $source ) {
            self::assertStringContainsString( "'supc_lock_' . hash( 'sha256', \$uuid )", $source );
        }
    }
}
