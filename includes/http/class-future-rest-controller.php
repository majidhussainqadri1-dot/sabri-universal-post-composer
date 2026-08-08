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

use Sabri\UniversalComposer\Contracts\Future_Capability_Adapter;
use Sabri\UniversalComposer\Core\Contract_Boundary;
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
		return $this->within_rate_limit( $user_id ) ? true : $this->error( 'future_rate_limited', 429 );
	}

	public function capabilities( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$adapter_key = sanitize_key( (string) $request['adapter'] );
		$authorization = $this->authorized_adapter( $adapter_key );
		if ( $authorization instanceof WP_Error ) {
			return $authorization;
		}
		$bridge = $this->bridge_capabilities( get_current_user_id(), $adapter_key, $authorization );
		return $this->response(
			array(
				'version'             => defined( 'SUPC_FUTURE_INTELLIGENCE_VERSION' ) ? (string) SUPC_FUTURE_INTELLIGENCE_VERSION : '1.0.0',
				'local_capabilities'  => self::LOCAL_CAPABILITIES,
				'bridge_capabilities' => $bridge,
				'all_capabilities'    => array_values( array_unique( array_merge( self::LOCAL_CAPABILITIES, $bridge ) ) ),
				'ownership'           => 'native_owner',
				'ephemeral_bridge'    => true,
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

		if ( ! Contract_Boundary::adapter_key( $adapter_key ) || ! in_array( $capability, self::BRIDGE_CAPABILITIES, true ) ) {
			return $this->error( 'future_invalid_capability_request', 400 );
		}
		if ( ! $this->payload_is_bounded( $payload ) ) {
			return $this->error( 'future_payload_too_large', 413 );
		}

		$adapter = $this->authorized_adapter( $adapter_key );
		if ( $adapter instanceof WP_Error ) {
			return $adapter;
		}
		$declared = $this->bridge_capabilities( get_current_user_id(), $adapter_key, $adapter );
		if ( ! in_array( $capability, $declared, true ) ) {
			return $this->error( 'future_capability_unavailable', 409 );
		}
		if ( '' !== $session ) {
			$owned = ( new Session_Store() )->get_owned( $session, get_current_user_id() );
			if ( $owned instanceof WP_Error || ! hash_equals( $adapter_key, (string) ( $owned['adapter_key'] ?? '' ) ) ) {
				return $this->error( 'future_session_mismatch', 409 );
			}
		}

		$sensitive = ! empty( $payload['sensitive'] );
		if ( $sensitive && in_array( $capability, array( 'ai_copilot', 'medical_terminology', 'cross_format_derivative' ), true ) ) {
			$allowed = (bool) apply_filters( 'supc_future_sensitive_capability_allowed', false, $capability, get_current_user_id(), $adapter_key );
			if ( ! $allowed ) {
				return $this->error( 'future_sensitive_external_advisory_blocked', 403 );
			}
		}

		$result = apply_filters( 'supc_future_capability_result', null, $capability, get_current_user_id(), $adapter_key, $payload );
		if ( null === $result && $adapter instanceof Future_Capability_Adapter ) {
			try {
				$result = $adapter->invoke_future_capability( get_current_user_id(), $capability, $payload );
			} catch ( \Throwable $error ) {
				unset( $error );
				$result = $this->error( 'future_provider_exception', 502 );
			}
		}
		if ( null === $result ) {
			return $this->error( 'future_provider_unavailable', 503 );
		}
		if ( $result instanceof WP_Error ) {
			return $this->normalize_error( $result );
		}
		if ( ! is_array( $result ) || strlen( (string) wp_json_encode( $result ) ) > self::MAX_RESPONSE_BYTES ) {
			return $this->error( 'future_provider_response_invalid', 502 );
		}

		do_action( 'supc_future_capability_invoked', $capability, get_current_user_id(), $adapter_key, true );
		return $this->response(
			array(
				'capability' => $capability,
				'result'     => $result,
				'ephemeral'  => true,
			)
		);
	}

	/** @return object|WP_Error */
	private function authorized_adapter( string $adapter_key ): object|WP_Error {
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
		if ( ! is_object( $adapter ) || ! isset( $available[ $adapter_key ] ) ) {
			return $this->error( 'future_adapter_not_authorized', 403 );
		}
		return $adapter;
	}

	/** @return array<int,string> */
	private function bridge_capabilities( int $user_id, string $adapter_key, object $adapter ): array {
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
		if ( count( $payload ) > 64 || strlen( (string) wp_json_encode( $payload ) ) > self::MAX_REQUEST_BYTES ) {
			return false;
		}
		$walk = static function ( mixed $value, int $depth = 0 ) use ( &$walk ): bool {
			if ( $depth > 8 ) {
				return false;
			}
			if ( is_resource( $value ) || is_object( $value ) ) {
				return false;
			}
			if ( is_string( $value ) && strlen( $value ) > 131072 ) {
				return false;
			}
			if ( is_array( $value ) ) {
				if ( count( $value ) > 256 ) {
					return false;
				}
				foreach ( $value as $item ) {
					if ( ! $walk( $item, $depth + 1 ) ) {
						return false;
					}
				}
			}
			return true;
		};
		return $walk( $payload );
	}

	private function within_rate_limit( int $user_id ): bool {
		if ( ! function_exists( 'get_transient' ) || ! function_exists( 'set_transient' ) ) {
			return true;
		}
		$key   = 'supc_future_rate_' . hash( 'sha256', (string) $user_id . '|' . (string) floor( time() / self::RATE_WINDOW ) );
		$count = (int) get_transient( $key );
		if ( $count >= self::RATE_LIMIT ) {
			return false;
		}
		set_transient( $key, $count + 1, self::RATE_WINDOW + 5 );
		return true;
	}

	/** @param array<string,mixed> $data */
	private function response( array $data, int $status = 200 ): WP_REST_Response {
		$response = new WP_REST_Response( $data, $status );
		$response->header( 'Cache-Control', 'private, no-store, max-age=0' );
		$response->header( 'Pragma', 'no-cache' );
		$response->header( 'X-Content-Type-Options', 'nosniff' );
		return $response;
	}

	private function normalize_error( WP_Error $error ): WP_Error {
		$code = (string) $error->get_error_code();
		$data = $error->get_error_data();
		$status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 422;
		return new WP_Error( '' !== $code ? $code : 'future_provider_error', $error->get_error_message(), array( 'status' => max( 400, min( 599, $status ) ) ) );
	}

	private function error( string $code, int $status ): WP_Error {
		return new WP_Error( 'supc_' . sanitize_key( $code ), __( 'The Future Composer Intelligence request could not be completed.', 'sabri-universal-post-composer' ), array( 'status' => $status ) );
	}
}
