<?php
/**
 * File 22 REST 1.1 overrides for durable submit and reconciliation.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

namespace Sabri\UniversalComposer\Http;

use Sabri\UniversalComposer\Contracts\Workflow_Adapter;
use Sabri\UniversalComposer\Core\Permission_Resolver;
use Sabri\UniversalComposer\Core\Contract_Boundary;
use Sabri\UniversalComposer\Core\Reconciliation_Service;
use Sabri\UniversalComposer\Core\Registry;
use Sabri\UniversalComposer\Core\Safe_Mode;
use Sabri\UniversalComposer\Core\Session_Store;
use Sabri\UniversalComposer\Core\Submission_Store;
use Sabri\UniversalComposer\Core\Workflow_Coordinator;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Reconciliation_Rest_Controller {
	private const MAX_REQUEST_BYTES = 1048576;
	private const RATE_LIMIT         = 90;
	private const RATE_WINDOW        = 60;
	private const SESSION_LOCK_TTL   = 600;

	public function __construct(
		private Registry $registry,
		private Workflow_Coordinator $coordinator,
		private Session_Store $sessions,
		private Submission_Store $submissions,
		private Reconciliation_Service $reconciliation
	) {
	}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	public function routes(): void {
		$permission = array( $this, 'permission' );
		register_rest_route(
			Rest_Controller::NAMESPACE,
			'/sessions',
			array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'create_session' ), 'permission_callback' => $permission ),
			true
		);
		register_rest_route(
			Rest_Controller::NAMESPACE,
			'/sessions/(?P<session>[0-9a-f-]{36})',
			array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'session' ), 'permission_callback' => $permission ),
			true
		);
		register_rest_route(
			Rest_Controller::NAMESPACE,
			'/sessions/(?P<session>[0-9a-f-]{36})/submit',
			array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'submit' ), 'permission_callback' => $permission ),
			true
		);
		register_rest_route(
			Rest_Controller::NAMESPACE,
			'/sessions/(?P<session>[0-9a-f-]{36})/reconcile',
			array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'reconcile' ), 'permission_callback' => $permission ),
			true
		);
	}

	public function permission( WP_REST_Request $request ): bool|WP_Error {
		foreach ( array( 'DONOTCACHEPAGE', 'DONOTCACHEOBJECT', 'DONOTCACHEDB' ) as $constant ) {
			if ( ! defined( $constant ) ) {
				define( $constant, true );
			}
		}
		do_action( 'litespeed_control_set_nocache', 'sabri-universal-post-composer-rest' );
		$method = strtoupper( (string) $request->get_method() );
		$read_only = in_array( $method, array( 'GET', 'HEAD' ), true );
		if ( Safe_Mode::disabled() && ( ! $read_only || ! Safe_Mode::read_only_recovery_allowed() ) ) {
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
		if ( ! ( new Permission_Resolver() )->account_is_eligible( $user_id ) ) {
			return $this->error( 'account_not_eligible', 403 );
		}
		return $this->within_rate_limit( $user_id ) ? true : $this->error( 'rate_limited', 429 );
	}

	public function create_session( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$body = $this->body( $request );
		if ( $body instanceof WP_Error ) {
			return $body;
		}
		$adapter_key = sanitize_key( (string) ( $body['adapter_key'] ?? '' ) );
		$adapter     = $this->registry->get( $adapter_key );
		$workflow    = $this->registry->workflow_contract( $adapter_key );
		if ( ! Contract_Boundary::adapter_key( $adapter_key ) || ! $adapter instanceof Workflow_Adapter || null === $workflow || empty( $workflow['supports_native_drafts'] ) ) {
			return $this->error( 'workflow_session_unsupported', 409 );
		}
		$schema = $this->coordinator->schema( get_current_user_id(), $adapter_key );
		if ( $schema instanceof WP_Error ) {
			return $this->normalize_error( $schema );
		}
		$contract = $this->registry->adapter_contract( $adapter_key );
		$privacy  = is_array( $contract ) && isset( $contract['privacy_classification'] ) ? (string) $contract['privacy_classification'] : 'private';
		$session  = $this->sessions->create( get_current_user_id(), $adapter_key, $adapter->schema_version(), $privacy );
		return $session instanceof WP_Error
			? $this->normalize_error( $session )
			: $this->response( array( 'session' => $this->public_session( $session ), 'schema' => $schema ), 201 );
	}

	public function session( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$session = $this->owned_session( $request );
		if ( $session instanceof WP_Error ) {
			return $session;
		}
		$authorized = $this->coordinator->schema_read_only( get_current_user_id(), (string) $session['adapter_key'] );
		if ( $authorized instanceof WP_Error ) {
			return $this->normalize_error( $authorized );
		}
		$status         = null;
		$draft_payload  = null;
		$draft_recovery = 'not_applicable';
		if ( is_string( $session['native_reference'] ) && '' !== $session['native_reference'] ) {
			$status = $this->coordinator->status_read_only( get_current_user_id(), (string) $session['adapter_key'], (string) $session['native_reference'] );
			if ( $status instanceof WP_Error ) {
				return $this->normalize_error(
					$status,
					array(
						'session'                 => $this->public_session( $session, false ),
						'reconciliation_required' => ! empty( $session['reconciliation_required'] ),
					)
				);
			}
		}
		if ( is_string( $session['native_reference'] ) && '' !== $session['native_reference'] ) {
			$recovered = $this->coordinator->load_draft( get_current_user_id(), (string) $session['adapter_key'], (string) $session['native_reference'] );
			if ( $recovered instanceof WP_Error ) {
				$code = is_callable( array( $recovered, 'get_error_code' ) ) ? (string) $recovered->get_error_code() : '';
				if ( 'supc_draft_recovery_unsupported' !== $code ) {
					return $this->normalize_error( $recovered, array( 'session' => $this->public_session( $session, false ) ) );
				}
				$draft_recovery = 'unsupported';
			} else {
				$draft_payload  = $recovered;
				$draft_recovery = 'recovered';
			}
		}
		return $this->response( array( 'session' => $this->public_session( $session ), 'native_status' => $status, 'draft_payload' => $draft_payload, 'draft_recovery' => $draft_recovery ) );
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
			$native_reference = $context['session']['native_reference'] ?? null;
			if ( ! is_string( $native_reference ) || '' === $native_reference ) {
				return $this->error( 'draft_required_before_submit', 409 );
			}
			$validation = $this->coordinator->validate( get_current_user_id(), (string) $context['session']['adapter_key'], $context['payload'] );
			if ( $validation instanceof WP_Error ) {
				$held = $this->sessions->apply_policy_error( $context['session'], $validation );
				return $this->normalize_error( $validation, array( 'session' => $this->public_session( $held ) ) );
			}
			if ( empty( $validation['valid'] ) ) {
				return $this->error( 'validation_failed', 422, array( 'errors' => $validation['errors'] ?? array(), 'warnings' => $validation['warnings'] ?? array() ) );
			}
			$payload_hash = Submission_Store::payload_fingerprint( $context['payload'] );
			if ( '' === $payload_hash ) {
				return $this->error( 'payload_fingerprint_failed', 503 );
			}
			$proposed_key = is_string( $context['session']['idempotency_key'] ) && '' !== $context['session']['idempotency_key']
				? $context['session']['idempotency_key']
				: $this->coordinator->generate_idempotency_key();
			if ( '' === $proposed_key ) {
				return $this->error( 'idempotency_key_failed', 503 );
			}
			// This durable prepare replaces the legacy ensure_idempotency_key-only path.
			$submission = $this->submissions->prepare( $uuid, get_current_user_id(), (string) $context['session']['adapter_key'], $native_reference, $proposed_key, $payload_hash );
			if ( $submission instanceof WP_Error ) {
				return $this->normalize_error( $submission );
			}
			$key     = (string) $submission['idempotency_key'];
			$session = $this->sessions->begin_submission( $uuid, get_current_user_id(), (int) $context['session']['lock_version'], $key, $payload_hash );
			if ( $session instanceof WP_Error ) {
				return $this->normalize_error( $session );
			}
			$dispatched = $this->submissions->mark_dispatched( (string) $submission['attempt_uuid'] );
			if ( $dispatched instanceof WP_Error ) {
				$restored = $this->sessions->apply_reconciliation( $uuid, get_current_user_id(), (int) $session['lock_version'], 'draft', $native_reference, $key, $payload_hash );
				return $this->normalize_error( $dispatched, array( 'session' => $this->public_session( $restored instanceof WP_Error ? $session : $restored ) ) );
			}
			$payload                     = $context['payload'];
			$payload['native_reference'] = $native_reference;
			$result                      = $this->coordinator->submit( get_current_user_id(), (string) $session['adapter_key'], $key, $payload );
			if ( $result instanceof WP_Error ) {
				$raw_code = is_callable( array( $result, 'get_error_code' ) ) ? (string) $result->get_error_code() : '';
				$pre_dispatch = in_array(
					$raw_code,
					array(
						'supc_policy_violation', 'supc_workflow_disabled', 'supc_invalid_adapter_key',
						'supc_workflow_adapter_not_registered', 'supc_workflow_adapter_unavailable',
						'supc_workflow_api_mismatch', 'supc_native_workflow_unavailable',
						'supc_workflow_permission_denied', 'supc_invalid_native_reference',
						'supc_invalid_workflow_payload', 'supc_workflow_payload_too_large',
						'supc_workflow_payload_unknown_field', 'supc_workflow_payload_field_invalid',
						'supc_workflow_payload_required_field_missing', 'supc_invalid_idempotency_key',
					),
					true
				);
				if ( $pre_dispatch && $this->submissions->release_pre_dispatch( (string) $submission['attempt_uuid'] ) ) {
					$code     = $this->safe_error_code( $result );
					$restored = $this->sessions->reset_pre_dispatch( $uuid, get_current_user_id(), (int) $session['lock_version'], $code );
					$base     = $restored instanceof WP_Error ? $session : $restored;
					$held     = $this->sessions->apply_policy_error( $base, $result );
					return $this->normalize_error( $result, array( 'session' => $this->public_session( $held ), 'reconciliation_required' => false ) );
				}
				$error_code = $this->safe_error_code( $result );
				$this->submissions->mark_uncertain( (string) $submission['attempt_uuid'], $error_code );
				$marked = $this->sessions->mark_reconciliation( $uuid, get_current_user_id(), (int) $session['lock_version'], $error_code );
				return $this->normalize_error( $result, array( 'session' => $this->public_session( $marked instanceof WP_Error ? $session : $marked ), 'reconciliation_required' => true ) );
			}
			$finalized = $this->sessions->finalize_submission( $uuid, get_current_user_id(), (int) $session['lock_version'], (string) $result['status'], $native_reference, $key, $payload_hash );
			if ( $finalized instanceof WP_Error ) {
				$this->submissions->mark_uncertain( (string) $submission['attempt_uuid'], 'session_finalize_failed' );
				return $this->error( 'session_finalize_failed', 503, array( 'session' => $this->public_session( $session ), 'reconciliation_required' => true ) );
			}
			$response_hash = $this->result_hash( $result );
			if ( '' === $response_hash || ! $this->submissions->mark_reconciled( (string) $submission['attempt_uuid'], (string) $result['status'], $response_hash ) ) {
				$this->submissions->mark_uncertain( (string) $submission['attempt_uuid'], 'submission_ack_record_failed' );
				$marked = $this->sessions->mark_reconciliation( $uuid, get_current_user_id(), (int) $finalized['lock_version'], 'submission_ack_record_failed' );
				return $this->error( 'submission_ack_record_failed', 503, array( 'session' => $this->public_session( $marked instanceof WP_Error ? $finalized : $marked ), 'reconciliation_required' => true ) );
			}
			return $this->response( array( 'session' => $this->public_session( $finalized ), 'native' => $result ) );
		} finally {
			$this->release_session_lock( $uuid, $token );
		}
	}

	public function reconcile( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$uuid  = strtolower( (string) $request['session'] );
		$token = $this->acquire_session_lock( $uuid );
		if ( '' === $token ) {
			return $this->error( 'session_busy', 409 );
		}
		try {
			$session = $this->owned_session( $request );
			if ( $session instanceof WP_Error ) {
				return $session;
			}
			$body = $this->body( $request );
			if ( $body instanceof WP_Error ) {
				return $body;
			}
			if ( ! is_int( $body['lock_version'] ?? null ) || (int) $body['lock_version'] !== (int) $session['lock_version'] ) {
				return $this->error( 'session_conflict', 409, array( 'session' => $this->public_session( $session ) ) );
			}
			$result = $this->reconciliation->reconcile_session( $uuid, get_current_user_id() );
			return $result instanceof WP_Error
				? $this->normalize_error( $result, array( 'session' => $this->public_session( $session ), 'reconciliation_required' => true ) )
				: $this->response( array( 'session' => $this->public_session( $result['session'] ), 'native' => $result['native'], 'resolved' => $result['resolved'] ) );
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
		$adapter = $this->registry->get( (string) $session['adapter_key'] );
		if ( ! $adapter instanceof Workflow_Adapter || ! hash_equals( (string) $session['adapter_version'], $adapter->schema_version() ) ) {
			return $this->error( 'adapter_version_changed', 409, array( 'session' => $this->public_session( $session ) ) );
		}
		return array( 'session' => $session, 'payload' => $payload );
	}

	/** @return array<string,mixed>|WP_Error */
	private function owned_session( WP_REST_Request $request ): array|WP_Error {
		$result = $this->sessions->get_owned( strtolower( (string) $request['session'] ), get_current_user_id() );
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
		$value = array( 'token' => $token, 'expires_at' => time() + self::SESSION_LOCK_TTL );
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
		$key      = 'supc_lock_' . hash( 'sha256', $uuid );
		$existing = get_option( $key, null );
		if ( '' !== $token && is_array( $existing ) && is_string( $existing['token'] ?? null ) && hash_equals( $existing['token'], $token ) ) {
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
	private function public_session( array $session, bool $include_native_reference = true ): array {
		return array(
			'session_uuid'            => (string) $session['session_uuid'],
			'adapter_key'             => (string) $session['adapter_key'],
			'adapter_version'         => (string) $session['adapter_version'],
			'native_reference'        => $include_native_reference ? $session['native_reference'] : null,
			'state'                   => (string) $session['state'],
			'composer_state'          => (string) ( $session['composer_state'] ?? 'new' ),
			'review_state'            => (string) ( $session['review_state'] ?? 'draft' ),
			'publication_state'       => (string) ( $session['publication_state'] ?? 'unpublished' ),
			'hold_state'              => (string) ( $session['hold_state'] ?? 'clear' ),
			'sensitivity_class'       => (string) ( $session['sensitivity_class'] ?? 'private' ),
			'lock_version'            => (int) $session['lock_version'],
			'submit_attempts'         => (int) ( $session['submit_attempts'] ?? 0 ),
			'reconciliation_required' => ! empty( $session['reconciliation_required'] ),
			'updated_at'              => (string) $session['updated_at'],
			'expires_at'              => (string) $session['expires_at'],
		);
	}

	private function response( array $data, int $status = 200 ): WP_REST_Response {
		$response = new WP_REST_Response( $data, $status );
		$response->header( 'Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0' );
		$response->header( 'CDN-Cache-Control', 'no-store' );
		$response->header( 'Surrogate-Control', 'no-store' );
		$response->header( 'Pragma', 'no-cache' );
		$response->header( 'Expires', 'Wed, 11 Jan 1984 05:00:00 GMT' );
		$response->header( 'X-Content-Type-Options', 'nosniff' );
		$response->header( 'X-Robots-Tag', 'noindex, nofollow, noarchive' );
		$response->header( 'Vary', 'Cookie, X-WP-Nonce' );
		return $response;
	}

	private function normalize_error( WP_Error $error, array $extra = array() ): WP_Error {
		$properties = get_object_vars( $error );
		$raw_code   = is_callable( array( $error, 'get_error_code' ) ) ? (string) call_user_func( array( $error, 'get_error_code' ) ) : (string) ( $properties['code'] ?? '' );
		$code       = sanitize_key( $raw_code );
		$raw_data   = is_callable( array( $error, 'get_error_data' ) ) ? call_user_func( array( $error, 'get_error_data' ), $raw_code ) : ( $properties['data'] ?? null );
		$details    = is_array( $raw_data ) ? $raw_data : array();
		$native     = 'supc_native_workflow_error' === $code && is_string( $details['native_code'] ?? null ) ? sanitize_key( $details['native_code'] ) : '';
		$effective  = '' !== $native ? 'supc_' . $native : $code;
		$status     = match ( $effective ) {
			'supc_permission_denied', 'supc_membership_unavailable', 'supc_adapter_permission_denied', 'supc_workflow_permission_denied' => 403,
			'supc_not_found', 'supc_session_not_found', 'supc_submission_not_found' => 404,
			'supc_expired', 'supc_session_expired' => 410,
			'supc_validation_failed', 'supc_workflow_payload_required_field_missing', 'supc_workflow_payload_field_invalid' => 422,
			'supc_conflict', 'supc_session_conflict', 'supc_idempotency_key_conflict', 'supc_adapter_version_changed', 'supc_native_reference_mismatch', 'supc_submission_identity_conflict', 'supc_idempotency_payload_mismatch', 'supc_submission_already_final', 'supc_reconciliation_pending' => 409,
			'supc_rate_limited' => 429,
			'supc_temporarily_unavailable', 'supc_workflow_disabled', 'supc_workflow_adapter_unavailable', 'supc_native_workflow_unavailable', 'supc_session_store_unavailable', 'supc_submission_store_unavailable', 'supc_submission_prepare_failed', 'supc_submission_dispatch_record_failed', 'supc_session_finalize_failed', 'supc_submission_ack_record_failed', 'supc_reconciliation_hash_failed', 'supc_submission_reconcile_record_failed', 'supc_payload_fingerprint_failed', 'supc_workflow_adapter_exception' => 503,
			default => 400,
		};
		return $this->error( str_replace( 'supc_', '', $code ), $status, array_merge( $details, $extra ) );
	}

	/** @param array<string,mixed> $result */
	private function result_hash( array $result ): string {
		$encoded = function_exists( 'wp_json_encode' ) ? wp_json_encode( $result ) : json_encode( $result );
		return is_string( $encoded ) ? hash( 'sha256', $encoded ) : '';
	}

	private function safe_error_code( WP_Error $error ): string {
		$raw  = is_callable( array( $error, 'get_error_code' ) ) ? (string) call_user_func( array( $error, 'get_error_code' ) ) : (string) ( get_object_vars( $error )['code'] ?? '' );
		$code = sanitize_key( str_replace( 'supc_', '', $raw ) );
		return Contract_Boundary::code( $code ) ? $code : 'native_submit_uncertain';
	}

	private function error( string $code, int $status, mixed $data = null ): WP_Error {
		return new WP_Error( 'supc_' . sanitize_key( $code ), __( 'The Composer request could not be completed.', 'sabri-universal-post-composer' ), array( 'status' => $status, 'details' => $data ) );
	}
}
