<?php
/**
 * Private browser API for the universal workflow composer.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

namespace Sabri\UniversalComposer\Http;

use Sabri\UniversalComposer\Contracts\Workflow_Adapter;
use Sabri\UniversalComposer\Core\Contract_Boundary;
use Sabri\UniversalComposer\Core\Registry;
use Sabri\UniversalComposer\Core\Safe_Mode;
use Sabri\UniversalComposer\Core\Session_Store;
use Sabri\UniversalComposer\Core\Workflow_Coordinator;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Rest_Controller {
	public const NAMESPACE = 'sabri-composer/v1';
	private const MAX_REQUEST_BYTES = 1048576;
	private const RATE_LIMIT = 90;
	private const RATE_WINDOW = 60;

	public function __construct(
		private Registry $registry,
		private Workflow_Coordinator $coordinator,
		private Session_Store $sessions
	) {
	}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	public function routes(): void {
		$permission = array( $this, 'permission' );
		register_rest_route( self::NAMESPACE, '/adapters', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'adapters' ), 'permission_callback' => $permission ) );
		register_rest_route( self::NAMESPACE, '/schema/(?P<adapter>[a-z][a-z0-9._-]{0,63})', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'schema' ), 'permission_callback' => $permission ) );
		register_rest_route( self::NAMESPACE, '/sessions', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'create_session' ), 'permission_callback' => $permission ) );
		register_rest_route( self::NAMESPACE, '/sessions/(?P<session>[0-9a-f-]{36})', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'session' ), 'permission_callback' => $permission ) );
		foreach ( array( 'autosave', 'validate', 'preview', 'submit' ) as $operation ) {
			register_rest_route(
				self::NAMESPACE,
				'/sessions/(?P<session>[0-9a-f-]{36})/' . $operation,
				array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, $operation ), 'permission_callback' => $permission )
			);
		}
	}

	public function permission( WP_REST_Request $request ): bool|WP_Error {
		if ( Safe_Mode::disabled() ) {
			return $this->error( 'safe_mode', 503 );
		}
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return $this->error( 'authentication_required', 401 );
		}
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( '' === $nonce || ! function_exists( 'wp_verify_nonce' ) || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return $this->error( 'invalid_rest_nonce', 403 );
		}
		if ( ! $this->within_rate_limit( $user_id ) ) {
			return $this->error( 'rate_limited', 429 );
		}
		return true;
	}

	public function adapters(): WP_REST_Response {
		$user_id = get_current_user_id();
		$items   = array();
		foreach ( $this->registry->available_for_user( $user_id ) as $key => $adapter ) {
			$contract = $this->registry->workflow_contract( $key );
			if ( ! $adapter instanceof Workflow_Adapter || null === $contract || empty( $contract['supports_native_drafts'] ) ) {
				continue;
			}
			$base = $this->registry->adapter_contract( $key );
			$items[] = array(
				'key' => $key,
				'label' => $adapter->label(),
				'schema_version' => $adapter->schema_version(),
				'privacy_classification' => (string) ( $base['privacy_classification'] ?? 'private' ),
			);
		}
		return $this->response( array( 'adapters' => $items ) );
	}

	public function schema( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$adapter = sanitize_key( (string) $request['adapter'] );
		$result  = $this->coordinator->schema( get_current_user_id(), $adapter );
		return $result instanceof WP_Error ? $this->normalize_error( $result ) : $this->response( $result );
	}

	public function create_session( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$body = $this->body( $request );
		if ( $body instanceof WP_Error ) {
			return $body;
		}
		$adapter_key = sanitize_key( (string) ( $body['adapter_key'] ?? '' ) );
		if ( ! Contract_Boundary::adapter_key( $adapter_key ) ) {
			return $this->error( 'invalid_adapter_key', 400 );
		}
		$adapter  = $this->registry->get( $adapter_key );
		$contract = $this->registry->workflow_contract( $adapter_key );
		if ( ! $adapter instanceof Workflow_Adapter || null === $contract || empty( $contract['supports_native_drafts'] ) ) {
			return $this->error( 'workflow_session_unsupported', 409 );
		}
		$schema = $this->coordinator->schema( get_current_user_id(), $adapter_key );
		if ( $schema instanceof WP_Error ) {
			return $this->normalize_error( $schema );
		}
		$session = $this->sessions->create( get_current_user_id(), $adapter_key, (string) $adapter->schema_version() );
		return $session instanceof WP_Error ? $this->normalize_error( $session ) : $this->response( array( 'session' => $this->public_session( $session ), 'schema' => $schema ), 201 );
	}

	public function session( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$session = $this->owned_session( $request );
		if ( $session instanceof WP_Error ) {
			return $session;
		}
		$status = null;
		if ( is_string( $session['native_reference'] ) && '' !== $session['native_reference'] ) {
			$status = $this->coordinator->status( get_current_user_id(), (string) $session['adapter_key'], (string) $session['native_reference'] );
			if ( $status instanceof WP_Error ) {
				$status = null;
			}
		}
		return $this->response( array( 'session' => $this->public_session( $session ), 'native_status' => $status ) );
	}

	public function autosave( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$token = $this->acquire_session_lock( strtolower( (string) $request['session'] ) );
		if ( '' === $token ) {
			return $this->error( 'session_busy', 409 );
		}
		try {
			$context = $this->operation_context( $request );
			if ( $context instanceof WP_Error ) {
				return $context;
			}
			$result = $this->coordinator->create_draft(
				get_current_user_id(),
				(string) $context['session']['adapter_key'],
				is_string( $context['session']['native_reference'] ) ? $context['session']['native_reference'] : null,
				$context['payload']
			);
			if ( $result instanceof WP_Error ) {
				return $this->normalize_error( $result );
			}
			$updated = $this->sessions->update(
				(string) $context['session']['session_uuid'],
				get_current_user_id(),
				(int) $context['session']['lock_version'],
				'draft',
				(string) $result['native_reference'],
				is_string( $context['session']['idempotency_key'] ) ? $context['session']['idempotency_key'] : null
			);
			if ( $updated instanceof WP_Error ) {
				return $this->error( 'session_mapping_failed', 503, array( 'native_reference' => (string) $result['native_reference'] ) );
			}
			return $this->response( array( 'session' => $this->public_session( $updated ), 'native' => $result ) );
		} finally {
			$this->release_session_lock( strtolower( (string) $request['session'] ), $token );
		}
	}

	public function validate( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$context = $this->operation_context( $request );
		if ( $context instanceof WP_Error ) {
			return $context;
		}
		$result = $this->coordinator->validate( get_current_user_id(), (string) $context['session']['adapter_key'], $context['payload'] );
		return $result instanceof WP_Error ? $this->normalize_error( $result ) : $this->response( $result );
	}

	public function preview( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$token = $this->acquire_session_lock( strtolower( (string) $request['session'] ) );
		if ( '' === $token ) {
			return $this->error( 'session_busy', 409 );
		}
		try {
			$context = $this->operation_context( $request );
			if ( $context instanceof WP_Error ) {
				return $context;
			}
			if ( ! is_string( $context['session']['native_reference'] ) || '' === $context['session']['native_reference'] ) {
				return $this->error( 'draft_required_before_preview', 409 );
			}
			$payload = $context['payload'];
			$payload['native_reference'] = $context['session']['native_reference'];
			$result = $this->coordinator->preview( get_current_user_id(), (string) $context['session']['adapter_key'], $payload );
			return $result instanceof WP_Error ? $this->normalize_error( $result ) : $this->response( $result );
		} finally {
			$this->release_session_lock( strtolower( (string) $request['session'] ), $token );
		}
	}

	public function submit( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$uuid  = strtolower( (string) $request['session'] );
		$token = $this->acquire_session_lock( $uuid );
		if ( '' === $token ) {
			return $this->error( 'session_busy', 409 );
		}
		try {
			$context = $this->operation_context( $request );
			if ( $context instanceof WP_Error ) {
				return $context;
			}
			if ( ! is_string( $context['session']['native_reference'] ) || '' === $context['session']['native_reference'] ) {
				return $this->error( 'draft_required_before_submit', 409 );
			}
			$key = is_string( $context['session']['idempotency_key'] ) && '' !== $context['session']['idempotency_key']
				? $context['session']['idempotency_key']
				: $this->coordinator->generate_idempotency_key();
			if ( '' === $key ) {
				return $this->error( 'idempotency_key_failed', 503 );
			}
			$session = $this->sessions->ensure_idempotency_key( $uuid, get_current_user_id(), $key );
			if ( $session instanceof WP_Error ) {
				return $this->normalize_error( $session );
			}
			$payload = $context['payload'];
			$payload['native_reference'] = $session['native_reference'];
			$result = $this->coordinator->submit( get_current_user_id(), (string) $session['adapter_key'], $key, $payload );
			if ( $result instanceof WP_Error ) {
				return $this->normalize_error( $result, array( 'session' => $this->public_session( $session ) ) );
			}
			$state = in_array( (string) ( $result['status'] ?? '' ), array( 'scheduled', 'published', 'rejected' ), true ) ? (string) $result['status'] : 'submitted';
			$updated = $this->sessions->update(
				$uuid,
				get_current_user_id(),
				(int) $session['lock_version'],
				$state,
				is_string( $session['native_reference'] ) ? $session['native_reference'] : null,
				$key
			);
			return $updated instanceof WP_Error ? $this->normalize_error( $updated ) : $this->response( array( 'session' => $this->public_session( $updated ), 'native' => $result ) );
		} finally {
			$this->release_session_lock( $uuid, $token );
		}
	}

	/** @return array{session:array<string,mixed>,payload:array<string,mixed>}|WP_Error */
	private function operation_context( WP_REST_Request $request ): array|WP_Error {
		$session = $this->owned_session( $request );
		if ( $session instanceof WP_Error ) {
			return $session;
		}
		$body = $this->body( $request );
		if ( $body instanceof WP_Error ) {
			return $body;
		}
		$payload = $body['payload'] ?? null;
		$lock    = $body['lock_version'] ?? null;
		if ( ! is_array( $payload ) || ! is_int( $lock ) || $lock !== (int) $session['lock_version'] ) {
			return $this->error( 'session_conflict', 409, array( 'session' => $this->public_session( $session ) ) );
		}
		return array( 'session' => $session, 'payload' => $payload );
	}

	/** @return array<string,mixed>|WP_Error */
	private function owned_session( WP_REST_Request $request ): array|WP_Error {
		$uuid   = strtolower( (string) $request['session'] );
		$result = $this->sessions->get_owned( $uuid, get_current_user_id() );
		return $result instanceof WP_Error ? $this->normalize_error( $result ) : $result;
	}

	/** @return array<string,mixed>|WP_Error */
	private function body( WP_REST_Request $request ): array|WP_Error {
		$raw = (string) $request->get_body();
		if ( strlen( $raw ) > self::MAX_REQUEST_BYTES ) {
			return $this->error( 'request_too_large', 413 );
		}
		$body = $request->get_json_params();
		return is_array( $body ) ? $body : $this->error( 'invalid_json_body', 400 );
	}

	private function acquire_session_lock( string $uuid ): string {
		if ( 1 !== preg_match( '/^[0-9a-f-]{36}$/D', $uuid ) || ! function_exists( 'wp_generate_uuid4' ) || ! function_exists( 'add_option' ) ) {
			return '';
		}
		$key   = 'supc_lock_' . hash( 'sha256', $uuid );
		$token = wp_generate_uuid4();
		$value = array( 'token' => $token, 'expires_at' => time() + 45 );
		if ( add_option( $key, $value, '', false ) ) {
			return $token;
		}
		$existing = get_option( $key, null );
		if ( ! is_array( $existing ) || (int) ( $existing['expires_at'] ?? 0 ) > time() ) {
			return '';
		}
		delete_option( $key );
		return add_option( $key, $value, '', false ) ? $token : '';
	}

	private function release_session_lock( string $uuid, string $token ): void {
		if ( '' === $token ) {
			return;
		}
		$key      = 'supc_lock_' . hash( 'sha256', $uuid );
		$existing = get_option( $key, null );
		if ( is_array( $existing ) && isset( $existing['token'] ) && is_string( $existing['token'] ) && hash_equals( $existing['token'], $token ) ) {
			delete_option( $key );
		}
	}

	private function within_rate_limit( int $user_id ): bool {
		$bucket = 'supc_rest_' . md5( $user_id . '|' . floor( time() / self::RATE_WINDOW ) );
		$count  = (int) get_transient( $bucket );
		if ( $count >= self::RATE_LIMIT ) {
			return false;
		}
		set_transient( $bucket, $count + 1, self::RATE_WINDOW + 5 );
		return true;
	}

	/** @param array<string,mixed> $session @return array<string,mixed> */
	private function public_session( array $session ): array {
		return array(
			'session_uuid' => (string) $session['session_uuid'],
			'adapter_key' => (string) $session['adapter_key'],
			'adapter_version' => (string) $session['adapter_version'],
			'native_reference' => $session['native_reference'],
			'state' => (string) $session['state'],
			'lock_version' => (int) $session['lock_version'],
			'updated_at' => (string) $session['updated_at'],
			'expires_at' => (string) $session['expires_at'],
		);
	}

	private function response( array $data, int $status = 200 ): WP_REST_Response {
		$response = new WP_REST_Response( $data, $status );
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
		$response->header( 'X-Robots-Tag', 'noindex, nofollow, noarchive' );
		$response->header( 'Vary', 'Cookie, X-WP-Nonce' );
		return $response;
	}

	private function normalize_error( WP_Error $error, array $extra_details = array() ): WP_Error {
		$raw_code = is_callable( array( $error, 'get_error_code' ) ) ? (string) $error->get_error_code() : (string) ( $error->code ?? '' );
		$code     = sanitize_key( $raw_code );
		$status = match ( $code ) {
			'supc_permission_denied', 'supc_membership_unavailable', 'supc_adapter_permission_denied' => 403,
			'supc_conflict', 'supc_session_conflict' => 409,
			'supc_rate_limited' => 429,
			'supc_temporarily_unavailable', 'supc_workflow_adapter_unavailable', 'supc_session_store_unavailable' => 503,
			default => 400,
		};
		$details = is_array( $error->data ) ? $error->data : array();
		return $this->error( str_replace( 'supc_', '', $code ), $status, array_merge( $details, $extra_details ) );
	}

	private function error( string $code, int $status, mixed $data = null ): WP_Error {
		return new WP_Error( 'supc_' . sanitize_key( $code ), __( 'The Composer request could not be completed.', 'sabri-universal-post-composer' ), array( 'status' => $status, 'details' => $data ) );
	}
}
