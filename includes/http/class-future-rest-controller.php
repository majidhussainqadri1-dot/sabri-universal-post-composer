<?php
/**
 * REST bridge for the File 22 Future Composer Intelligence Superset.
 *
 * Requests and responses are ephemeral. File 22 does not persist AI prompts,
 * collaboration patches, annotations, semantic diffs, templates, derivative
 * content, media bytes, or publication-impact truth.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

namespace Sabri\UniversalComposer\Http;

use Sabri\UniversalComposer\Contracts\Adapter;
use Sabri\UniversalComposer\Contracts\Future_Capability_Adapter;
use Sabri\UniversalComposer\Core\Audit_Store;
use Sabri\UniversalComposer\Core\Contract_Boundary;
use Sabri\UniversalComposer\Core\Future_Intelligence_Hardening;
use Sabri\UniversalComposer\Core\Permission_Resolver;
use Sabri\UniversalComposer\Core\Plugin;
use Sabri\UniversalComposer\Core\Safe_Mode;
use Sabri\UniversalComposer\Core\Session_Store;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Future_Rest_Controller {
	private const MAX_REQUEST_BYTES  = 262144;
	private const MAX_RESPONSE_BYTES = 524288;
	private const RATE_LIMIT         = 60;
	private const RATE_WINDOW        = 60;

	/** @var array<int,string> */
	private const LOCAL_CAPABILITIES = array(
		'evidence_graph',
		'privacy_assistant',
		'voice_dictation',
		'command_palette',
		'adaptive_composer',
		'accessibility_coach',
		'readiness_score',
		'encrypted_offline_recovery',
	);

	/** @var array<int,string> */
	private const BRIDGE_CAPABILITIES = array(
		'ai_copilot',
		'medical_terminology',
		'collaboration',
		'review_annotations',
		'semantic_diff',
		'conflict_merge',
		'template_library',
		'media_workbench',
		'cross_format_derivative',
		'publication_impact',
	);

	/** @var array<int,string> */
	private const SESSION_BOUND_CAPABILITIES = array(
		'collaboration',
		'review_annotations',
		'semantic_diff',
		'conflict_merge',
	);

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ), 30 );
	}

	public function routes(): void {
		register_rest_route(
			Rest_Controller::NAMESPACE,
			'/future/capabilities/(?P<adapter>[a-z][a-z0-9._-]{0,63})',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'capabilities' ),
				'permission_callback' => array( $this, 'permission' ),
			)
		);
		register_rest_route(
			Rest_Controller::NAMESPACE,
			'/future/invoke',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'invoke' ),
				'permission_callback' => array( $this, 'permission' ),
			)
		);
	}

	public function permission( WP_REST_Request $request ): bool|WP_Error {
		foreach ( array( 'DONOTCACHEPAGE', 'DONOTCACHEOBJECT', 'DONOTCACHEDB' ) as $constant ) {
			if ( ! defined( $constant ) ) {
				define( $constant, true );
			}
		}
		do_action( 'litespeed_control_set_nocache', 'sabri-universal-post-composer-future-intelligence' );

		if ( Safe_Mode::disabled() ) {
			return $this->error( 'future_safe_mode', 503 );
		}
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return $this->error( 'future_authentication_required', 401 );
		}
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( '' === $nonce || ! function_exists( 'wp_verify_nonce' ) || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return $this->error( 'future_invalid_rest_nonce', 403 );
		}
		if ( ! ( new Permission_Resolver() )->account_is_eligible( $user_id ) ) {
			return $this->error( 'future_account_not_eligible', 403 );
		}
		$route = method_exists( $request, 'get_route' ) ? (string) $request->get_route() : '';
		if ( str_ends_with( $route, '/future/invoke' ) && ! $this->within_rate_limit( $user_id ) ) {
			return $this->error( 'future_rate_limited', 429 );
		}
		return true;
	}

	public function capabilities( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$adapter_key   = sanitize_key( (string) $request['adapter'] );
		$authorization = $this->authorized_adapter( $adapter_key );
		if ( $authorization instanceof WP_Error ) {
			return $authorization;
		}
		$bridge  = $this->bridge_capabilities( get_current_user_id(), $adapter_key, $authorization );
		$privacy = $this->adapter_privacy( $authorization );
		$local   = self::LOCAL_CAPABILITIES;
		if ( 'sensitive' === $privacy ) {
			$local = array_values( array_diff( $local, array( 'encrypted_offline_recovery' ) ) );
			if ( ! (bool) apply_filters( 'supc_future_sensitive_voice_allowed', false, get_current_user_id(), $adapter_key ) ) {
				$local = array_values( array_diff( $local, array( 'voice_dictation' ) ) );
			}
		}
		return $this->response(
			array(
				'version'                        => defined( 'SUPC_FUTURE_INTELLIGENCE_VERSION' ) ? (string) SUPC_FUTURE_INTELLIGENCE_VERSION : '1.0.0',
				'local_capabilities'             => $local,
				'bridge_capabilities'            => $bridge,
				'all_capabilities'               => array_values( array_unique( array_merge( $local, $bridge ) ) ),
				'adapter_privacy_classification' => $privacy,
				'ownership'                      => 'native_owner',
				'ephemeral_bridge'               => true,
			)
		);
	}

	public function invoke( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$body = $this->body( $request );
		if ( $body instanceof WP_Error ) {
			return $body;
		}
		$adapter_key = isset( $body['adapter_key'] ) && is_string( $body['adapter_key'] ) ? sanitize_key( $body['adapter_key'] ) : '';
		$capability  = isset( $body['capability'] ) && is_string( $body['capability'] ) ? sanitize_key( $body['capability'] ) : '';
		$payload     = isset( $body['payload'] ) && is_array( $body['payload'] ) ? $body['payload'] : array();
		$session     = isset( $body['session_uuid'] ) && is_string( $body['session_uuid'] ) ? strtolower( trim( $body['session_uuid'] ) ) : '';
		$correlation = $this->support_reference();

		if ( ! Contract_Boundary::adapter_key( $adapter_key ) || ! in_array( $capability, self::BRIDGE_CAPABILITIES, true ) ) {
			return $this->error( 'future_invalid_capability_request', 400 );
		}
		if ( isset( $payload['_supc_context'] ) ) {
			return $this->error( 'future_reserved_context_prohibited', 400 );
		}
		if ( ! $this->payload_is_bounded( $payload ) ) {
			return $this->error( 'future_payload_too_large', 413 );
		}
		if ( in_array( $capability, self::SESSION_BOUND_CAPABILITIES, true ) && '' === $session ) {
			return $this->error( 'future_session_required', 409 );
		}

		$adapter = $this->authorized_adapter( $adapter_key );
		if ( $adapter instanceof WP_Error ) {
			return $adapter;
		}
		$declared = $this->bridge_capabilities( get_current_user_id(), $adapter_key, $adapter );
		if ( ! in_array( $capability, $declared, true ) ) {
			$this->audit( $capability, $adapter_key, 'denied', $session, null, $correlation );
			return $this->error( 'future_capability_unavailable', 409 );
		}

		$owned = null;
		if ( '' !== $session ) {
			$owned = ( new Session_Store() )->get_owned( $session, get_current_user_id() );
			if ( $owned instanceof WP_Error || ! hash_equals( $adapter_key, (string) ( $owned['adapter_key'] ?? '' ) ) ) {
				$this->audit( $capability, $adapter_key, 'denied', null, null, $correlation );
				return $this->error( 'future_session_mismatch', 409 );
			}
		}

		$provider_payload = $payload;
		if ( in_array( $capability, self::SESSION_BOUND_CAPABILITIES, true ) ) {
			if ( ! is_array( $owned ) ) {
				return $this->error( 'future_session_required', 409 );
			}
			$provider_payload['_supc_context'] = array(
				'session_uuid'      => (string) $owned['session_uuid'],
				'adapter_key'       => (string) $owned['adapter_key'],
				'native_reference'  => is_string( $owned['native_reference'] ?? null ) ? (string) $owned['native_reference'] : '',
				'sensitivity_class' => (string) ( $owned['sensitivity_class'] ?? $this->adapter_privacy( $adapter ) ),
				'lock_version'      => (int) ( $owned['lock_version'] ?? 0 ),
				'correlation_id'    => $correlation,
			);
		}
		if ( ! $this->payload_is_bounded( $provider_payload ) ) {
			$this->audit( $capability, $adapter_key, 'failed', $session, is_array( $owned ) ? ( $owned['native_reference'] ?? null ) : null, $correlation );
			return $this->error( 'future_provider_context_too_large', 413 );
		}

		$preflight = ( new Future_Intelligence_Hardening() )->guard_request(
			null,
			$capability,
			get_current_user_id(),
			$adapter_key,
			$provider_payload
		);
		if ( $preflight instanceof WP_Error ) {
			$this->audit( $capability, $adapter_key, 'denied', $session, is_array( $owned ) ? ( $owned['native_reference'] ?? null ) : null, $correlation );
			return $this->normalize_error( $preflight, $correlation );
		}

		$result = apply_filters( 'supc_future_capability_result', null, $capability, get_current_user_id(), $adapter_key, $provider_payload );
		if ( null === $result && $adapter instanceof Future_Capability_Adapter ) {
			try {
				$result = $adapter->invoke_future_capability( get_current_user_id(), $capability, $provider_payload );
			} catch ( \Throwable $error ) {
				unset( $error );
				$result = $this->error( 'future_provider_exception', 502 );
			}
		}
		if ( null === $result ) {
			$this->audit( $capability, $adapter_key, 'failed', $session, is_array( $owned ) ? ( $owned['native_reference'] ?? null ) : null, $correlation );
			return $this->error( 'future_provider_unavailable', 503 );
		}
		if ( $result instanceof WP_Error ) {
			$this->audit( $capability, $adapter_key, 'failed', $session, is_array( $owned ) ? ( $owned['native_reference'] ?? null ) : null, $correlation );
			return $this->normalize_error( $result, $correlation );
		}
		if ( ! is_array( $result ) || ! $this->response_is_bounded( $result ) ) {
			$this->audit( $capability, $adapter_key, 'failed', $session, is_array( $owned ) ? ( $owned['native_reference'] ?? null ) : null, $correlation );
			return $this->error( 'future_provider_response_invalid', 502 );
		}

		$this->audit( $capability, $adapter_key, 'success', $session, is_array( $owned ) ? ( $owned['native_reference'] ?? null ) : null, $correlation );
		do_action( 'supc_future_capability_invoked', $capability, get_current_user_id(), $adapter_key, true, $correlation );
		return $this->response(
			array(
				'capability'     => $capability,
				'result'         => $result,
				'ephemeral'      => true,
				'correlation_id' => $correlation,
			)
		);
	}

	private function authorized_adapter( string $adapter_key ): Adapter|WP_Error {
		if ( ! Contract_Boundary::adapter_key( $adapter_key ) ) {
			return $this->error( 'future_invalid_adapter', 400 );
		}
		try {
			$registry  = Plugin::instance()->registry();
			$available = $registry->available_for_user( get_current_user_id() );
			$adapter   = $registry->get( $adapter_key );
		} catch ( \Throwable $error ) {
			unset( $error );
			return $this->error( 'future_registry_unavailable', 503 );
		}
		if ( ! $adapter instanceof Adapter || ! isset( $available[ $adapter_key ] ) ) {
			return $this->error( 'future_adapter_not_authorized', 403 );
		}
		return $adapter;
	}

	private function adapter_privacy( Adapter $adapter ): string {
		try {
			$value = strtolower( trim( $adapter->privacy_classification() ) );
		} catch ( \Throwable $error ) {
			unset( $error );
			return 'sensitive';
		}
		return in_array( $value, array( 'public', 'private', 'sensitive' ), true ) ? $value : 'sensitive';
	}

	/** @return array<int,string> */
	private function bridge_capabilities( int $user_id, string $adapter_key, Adapter $adapter ): array {
		$capabilities = array();
		if ( $adapter instanceof Future_Capability_Adapter ) {
			try {
				$capabilities = $adapter->future_capabilities( $user_id );
			} catch ( \Throwable $error ) {
				unset( $error );
				$capabilities = array();
			}
		}
		$filtered = apply_filters( 'supc_future_capabilities', $capabilities, $user_id, $adapter_key );
		if ( is_array( $filtered ) ) {
			$capabilities = $filtered;
		}
		$final = ( new Future_Intelligence_Hardening() )->filter_capabilities( $capabilities, $user_id, $adapter_key );
		if ( is_array( $final ) ) {
			$capabilities = $final;
		}
		$out = array();
		foreach ( array_slice( $capabilities, 0, 32 ) as $capability ) {
			$key = is_string( $capability ) ? sanitize_key( $capability ) : '';
			if ( in_array( $key, self::BRIDGE_CAPABILITIES, true ) ) {
				$out[] = $key;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/** @return array<string,mixed>|WP_Error */
	private function body( WP_REST_Request $request ): array|WP_Error {
		$raw = (string) $request->get_body();
		if ( strlen( $raw ) > self::MAX_REQUEST_BYTES ) {
			return $this->error( 'future_request_too_large', 413 );
		}
		$body = $request->get_json_params();
		return is_array( $body ) ? $body : $this->error( 'future_invalid_json_body', 400 );
	}

	/** @param array<string,mixed> $payload */
	private function payload_is_bounded( array $payload ): bool {
		$encoded = wp_json_encode( $payload );
		if ( count( $payload ) > 64 || ! is_string( $encoded ) || strlen( $encoded ) > self::MAX_REQUEST_BYTES ) {
			return false;
		}
		return $this->bounded_value( $payload, 0, 256, 131072 );
	}

	/** @param array<string,mixed> $result */
	private function response_is_bounded( array $result ): bool {
		$encoded = wp_json_encode( $result );
		if ( ! is_string( $encoded ) || strlen( $encoded ) > self::MAX_RESPONSE_BYTES ) {
			return false;
		}
		return $this->bounded_value( $result, 0, 256, 262144 );
	}

	private function bounded_value( mixed $value, int $depth, int $max_items, int $max_string_bytes ): bool {
		if ( $depth > 8 || is_resource( $value ) || is_object( $value ) ) {
			return false;
		}
		if ( is_string( $value ) ) {
			return strlen( $value ) <= $max_string_bytes;
		}
		if ( is_array( $value ) ) {
			if ( count( $value ) > $max_items ) {
				return false;
			}
			foreach ( $value as $item ) {
				if ( ! $this->bounded_value( $item, $depth + 1, $max_items, $max_string_bytes ) ) {
					return false;
				}
			}
		}
		return true;
	}

	private function within_rate_limit( int $user_id ): bool {
		if ( ! function_exists( 'get_transient' ) || ! function_exists( 'set_transient' ) || ! function_exists( 'add_option' ) || ! function_exists( 'delete_option' ) ) {
			return false;
		}
		$bucket = (string) floor( time() / self::RATE_WINDOW );
		$hash   = hash( 'sha256', (string) $user_id . '|' . $bucket );
		$key    = 'supc_future_rate_' . $hash;
		$lock   = 'supc_future_rate_lock_' . $hash;
		// add_option is backed by a unique option_name and therefore acts as the
		// cross-request mutex. Contention or storage uncertainty fails closed.
		if ( ! add_option( $lock, time(), '', false ) ) {
			return false;
		}
		try {
			$count = (int) get_transient( $key );
			if ( $count >= self::RATE_LIMIT ) {
				return false;
			}
			return false !== set_transient( $key, $count + 1, self::RATE_WINDOW + 5 );
		} finally {
			delete_option( $lock );
		}
	}

	private function audit( string $capability, string $adapter_key, string $outcome, ?string $session_uuid, mixed $native_reference, string $correlation ): void {
		$event   = 'future.' . sanitize_key( $capability );
		$native  = is_string( $native_reference ) && '' !== $native_reference ? $native_reference : null;
		$session = is_string( $session_uuid ) && 1 === preg_match( '/^[0-9a-f-]{36}$/D', strtolower( $session_uuid ) ) ? strtolower( $session_uuid ) : null;
		try {
			( new Audit_Store() )->record( get_current_user_id(), $adapter_key, $event, $outcome, $session, $native, $correlation );
		} catch ( \Throwable $error ) {
			unset( $error );
			// Audit-storage failure must not turn an already-authorized advisory into
			// a false success/failure. File 24/System Check can surface ledger health.
		}
	}

	/** @param array<string,mixed> $data */
	private function response( array $data, int $status = 200 ): WP_REST_Response {
		$response = new WP_REST_Response( $data, $status );
		$response->header( 'Cache-Control', 'private, no-store, max-age=0' );
		$response->header( 'Pragma', 'no-cache' );
		$response->header( 'X-Content-Type-Options', 'nosniff' );
		$response->header( 'Referrer-Policy', 'no-referrer' );
		return $response;
	}

	private function normalize_error( WP_Error $error, ?string $correlation = null ): WP_Error {
		$code   = (string) $error->get_error_code();
		$data   = $error->get_error_data();
		$status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 422;
		$reference = is_string( $correlation ) && '' !== $correlation
			? $correlation
			: ( is_array( $data ) && isset( $data['support_reference'] ) && is_string( $data['support_reference'] ) ? $data['support_reference'] : $this->support_reference() );
		$field = is_array( $data ) && isset( $data['field'] ) && is_string( $data['field'] ) && 1 === preg_match( '/^[a-z][a-z0-9_.-]{0,63}$/D', $data['field'] ) ? $data['field'] : null;
		$retryable = is_array( $data ) && isset( $data['retryable'] ) && is_bool( $data['retryable'] )
			? $data['retryable']
			: in_array( $status, array( 429, 502, 503, 504 ), true );
		$message = __( 'The advisory request could not be completed. Your draft remains protected.', 'sabri-universal-post-composer' );
		if ( null !== $field ) {
			$message .= ' ' . sprintf( __( 'Field: %s.', 'sabri-universal-post-composer' ), $field );
		}
		$message .= ' ' . ( $retryable ? __( 'Retry is allowed.', 'sabri-universal-post-composer' ) : __( 'Retry is not advised until the issue is corrected.', 'sabri-universal-post-composer' ) );
		$message .= ' ' . sprintf( __( 'Support reference: %s.', 'sabri-universal-post-composer' ), $reference );
		do_action( 'supc_future_error', '' !== $code ? $code : 'future_provider_error', $status, get_current_user_id(), $reference );
		return new WP_Error(
			'' !== $code ? $code : 'future_provider_error',
			$message,
			array(
				'status'            => max( 400, min( 599, $status ) ),
				'support_reference' => $reference,
				'field'             => $field,
				'retryable'         => $retryable,
				'draft_protected'   => true,
			)
		);
	}

	private function support_reference(): string {
		try {
			$random = bin2hex( random_bytes( 6 ) );
		} catch ( \Throwable $error ) {
			unset( $error );
			$random = substr( hash( 'sha256', (string) microtime( true ) . '|' . (string) get_current_user_id() ), 0, 12 );
		}
		return 'SUPC-FUT-' . strtoupper( $random );
	}

	private function error( string $code, int $status ): WP_Error {
		$reference = $this->support_reference();
		$retryable = in_array( $status, array( 429, 502, 503, 504 ), true );
		$message   = __( 'The Future Composer Intelligence request could not be completed. Your draft remains protected.', 'sabri-universal-post-composer' );
		$message  .= ' ' . ( $retryable ? __( 'Retry is allowed.', 'sabri-universal-post-composer' ) : __( 'Retry is not advised until the issue is corrected.', 'sabri-universal-post-composer' ) );
		$message  .= ' ' . sprintf( __( 'Support reference: %s.', 'sabri-universal-post-composer' ), $reference );
		do_action( 'supc_future_error', sanitize_key( $code ), $status, get_current_user_id(), $reference );
		return new WP_Error(
			'supc_' . sanitize_key( $code ),
			$message,
			array(
				'status'            => $status,
				'support_reference' => $reference,
				'retryable'         => $retryable,
				'draft_protected'   => true,
			)
		);
	}
}
