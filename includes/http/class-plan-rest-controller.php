<?php
/**
 * Plan-completion REST extensions for File 22 Core.
 *
 * These endpoints complete the harmonized contract without taking permanent
 * ownership from native modules.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

namespace Sabri\UniversalComposer\Http;

use Sabri\UniversalComposer\Contracts\Draft_Lifecycle_Adapter;
use Sabri\UniversalComposer\Contracts\Revision_Adapter;
use Sabri\UniversalComposer\Contracts\Upload_Token_Adapter;
use Sabri\UniversalComposer\Contracts\Workflow_Adapter;
use Sabri\UniversalComposer\Core\Permission_Resolver;
use Sabri\UniversalComposer\Core\Audit_Store;
use Sabri\UniversalComposer\Core\Contract_Boundary;
use Sabri\UniversalComposer\Core\Registry;
use Sabri\UniversalComposer\Core\Safe_Mode;
use Sabri\UniversalComposer\Core\Session_Store;
use Sabri\UniversalComposer\Core\Taxonomy_Map;
use Sabri\UniversalComposer\Core\Upload_Token_Store;
use Sabri\UniversalComposer\Core\Workflow_Coordinator;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plan_Rest_Controller {
	private const MAX_REQUEST_BYTES = 1048576;
	private const RATE_LIMIT         = 90;
	private const RATE_WINDOW        = 60;
	private const SESSION_LOCK_TTL   = 600;

	public function __construct(
		private Registry $registry,
		private Workflow_Coordinator $coordinator,
		private Session_Store $sessions,
		private Upload_Token_Store $uploads,
		private Audit_Store $audit
	) {
	}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ), 20 );
	}

	public function routes(): void {
		$permission = array( $this, 'permission' );
		register_rest_route( Rest_Controller::NAMESPACE, '/types', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'types' ), 'permission_callback' => $permission ) );
		register_rest_route( Rest_Controller::NAMESPACE, '/schema/type/(?P<adapter>[a-z][a-z0-9._-]{0,63})', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'schema' ), 'permission_callback' => $permission ) );
		register_rest_route( Rest_Controller::NAMESPACE, '/sessions', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'sessions' ), 'permission_callback' => $permission ) );
		register_rest_route( Rest_Controller::NAMESPACE, '/sessions/(?P<session>[0-9a-f-]{36})', array( 'methods' => 'PATCH', 'callback' => array( $this, 'patch_session' ), 'permission_callback' => $permission ) );
		register_rest_route( Rest_Controller::NAMESPACE, '/sessions/(?P<session>[0-9a-f-]{36})', array( 'methods' => WP_REST_Server::DELETABLE, 'callback' => array( $this, 'delete_session' ), 'permission_callback' => $permission ) );
		register_rest_route( Rest_Controller::NAMESPACE, '/status/(?P<session>[0-9a-f-]{36})', array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'status' ), 'permission_callback' => $permission ) );
		register_rest_route( Rest_Controller::NAMESPACE, '/sessions/(?P<session>[0-9a-f-]{36})/revision', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'revision' ), 'permission_callback' => $permission ) );
		register_rest_route( Rest_Controller::NAMESPACE, '/sessions/(?P<session>[0-9a-f-]{36})/uploads', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'begin_upload' ), 'permission_callback' => $permission ) );
		register_rest_route( Rest_Controller::NAMESPACE, '/sessions/(?P<session>[0-9a-f-]{36})/uploads/(?P<upload>[0-9a-f-]{36})/complete', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'complete_upload' ), 'permission_callback' => $permission ) );
		register_rest_route( Rest_Controller::NAMESPACE, '/sessions/(?P<session>[0-9a-f-]{36})/uploads/(?P<upload>[0-9a-f-]{36})', array( 'methods' => WP_REST_Server::DELETABLE, 'callback' => array( $this, 'cancel_upload' ), 'permission_callback' => $permission ) );
	}

	public function permission( WP_REST_Request $request ): bool|WP_Error {
		foreach ( array( 'DONOTCACHEPAGE', 'DONOTCACHEOBJECT', 'DONOTCACHEDB' ) as $constant ) {
			if ( ! defined( $constant ) ) {
				define( $constant, true );
			}
		}
		do_action( 'litespeed_control_set_nocache', 'sabri-universal-post-composer-plan-rest' );
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

	public function types(): WP_REST_Response {
		$items = array();
		$candidates = Safe_Mode::disabled() ? $this->registry->all() : $this->registry->available_for_user( get_current_user_id() );
		foreach ( $candidates as $key => $adapter ) {
			$contract = $this->registry->workflow_contract( $key );
			if ( ! $adapter instanceof Workflow_Adapter || null === $contract ) {
				continue;
			}
			if ( Safe_Mode::disabled() && $this->coordinator->schema_read_only( get_current_user_id(), $key ) instanceof WP_Error ) {
				continue;
			}
			$base    = $this->registry->adapter_contract( $key );
			$items[] = array(
				'key'                    => $key,
				'canonical_type'         => Taxonomy_Map::canonical( $key ),
				'label'                  => $adapter->label(),
				'schema_version'         => $adapter->schema_version(),
				'privacy_classification' => (string) ( $base['privacy_classification'] ?? 'private' ),
				'capabilities'           => array(
					'native_drafts' => ! empty( $contract['supports_native_drafts'] ),
					'discard'       => $adapter instanceof Draft_Lifecycle_Adapter,
					'uploads'       => $adapter instanceof Upload_Token_Adapter,
					'revisions'     => $adapter instanceof Revision_Adapter,
				),
			);
		}
		return $this->response( array( 'taxonomy_version' => Taxonomy_Map::VERSION, 'types' => $items ) );
	}

	public function schema( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$result = $this->coordinator->schema_read_only( get_current_user_id(), sanitize_key( (string) $request['adapter'] ) );
		return $result instanceof WP_Error ? $this->normalize_error( $result ) : $this->response( $result );
	}

	public function sessions(): WP_REST_Response {
		$user_id = get_current_user_id();
		$items   = array();
		foreach ( $this->sessions->list_owned( $user_id, 100 ) as $session ) {
			$authorization = $this->coordinator->schema_read_only( $user_id, (string) $session['adapter_key'] );
			if ( $authorization instanceof WP_Error ) {
				continue;
			}
			$items[] = $this->public_session( $session, false );
		}
		return $this->response( array( 'sessions' => $items ) );
	}

	public function patch_session( WP_REST_Request $request ): WP_REST_Response|WP_Error {
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
			$result = $this->coordinator->create_draft(
				get_current_user_id(),
				(string) $context['session']['adapter_key'],
				is_string( $context['session']['native_reference'] ) ? $context['session']['native_reference'] : null,
				$context['payload']
			);
			if ( $result instanceof WP_Error ) {
				$held = $this->sessions->apply_policy_error( $context['session'], $result );
				$this->record_failure( $held, 'draft_save_failed', $result );
				return $this->normalize_error( $result, array( 'session' => $this->public_session( $held ) ) );
			}
			$updated = $this->sessions->update(
				$uuid,
				get_current_user_id(),
				(int) $context['session']['lock_version'],
				'draft',
				(string) $result['native_reference'],
				is_string( $context['session']['idempotency_key'] ) ? $context['session']['idempotency_key'] : null
			);
			if ( $updated instanceof WP_Error ) {
				return $this->normalize_error( $updated );
			}
			$this->record_success( $updated, 'draft_saved', 'draft_saved' );
			return $this->response( array( 'session' => $this->public_session( $updated ), 'native' => $result ) );
		} finally {
			$this->release_session_lock( $uuid, $token );
		}
	}

	public function delete_session( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$session = $this->owned_session( $request );
		if ( $session instanceof WP_Error ) {
			return $session;
		}
		$body = $this->body_optional( $request );
		if ( $body instanceof WP_Error ) {
			return $body;
		}
		$lock = isset( $body['lock_version'] ) && is_int( $body['lock_version'] ) ? $body['lock_version'] : (int) $session['lock_version'];
		if ( $lock !== (int) $session['lock_version'] ) {
			return $this->error( 'session_conflict', 409, array( 'session' => $this->public_session( $session ) ) );
		}
		if ( is_string( $session['native_reference'] ) && '' !== $session['native_reference'] ) {
			$discarded = $this->coordinator->discard_draft( get_current_user_id(), (string) $session['adapter_key'], $session['native_reference'] );
			if ( $discarded instanceof WP_Error ) {
				return $this->normalize_error( $discarded );
			}
			if ( true !== $discarded ) {
				return $this->error( 'native_draft_not_discarded', 409 );
			}
		}
		$abandoned = $this->sessions->mark_abandoned( (string) $session['session_uuid'], get_current_user_id(), (int) $session['lock_version'] );
		if ( $abandoned instanceof WP_Error ) {
			return $this->normalize_error( $abandoned );
		}
		$deleted = $this->sessions->delete_owned( (string) $session['session_uuid'], get_current_user_id() );
		if ( ! $deleted ) {
			return $this->error( 'session_delete_failed', 503 );
		}
		$this->record_success( $abandoned, 'draft_discarded', 'draft_discarded' );
		return $this->response( array( 'deleted' => true ) );
	}

	public function status( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$session = $this->owned_session( $request );
		if ( $session instanceof WP_Error ) {
			return $session;
		}
		if ( ! is_string( $session['native_reference'] ) || '' === $session['native_reference'] ) {
			return $this->response( array( 'session' => $this->public_session( $session ), 'native_status' => null ) );
		}
		$status = $this->coordinator->status_read_only( get_current_user_id(), (string) $session['adapter_key'], $session['native_reference'] );
		return $status instanceof WP_Error ? $this->normalize_error( $status ) : $this->response( array( 'session' => $this->public_session( $session ), 'native_status' => $status ) );
	}

	public function revision( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$context = $this->operation_context( $request );
		if ( $context instanceof WP_Error ) {
			return $context;
		}
		$session = $context['session'];
		if ( ! is_string( $session['native_reference'] ) || '' === $session['native_reference'] ) {
			return $this->error( 'native_reference_required', 409 );
		}
		$body = $this->body( $request );
		if ( $body instanceof WP_Error ) {
			return $body;
		}
		$key = isset( $body['idempotency_key'] ) && is_string( $body['idempotency_key'] ) ? trim( $body['idempotency_key'] ) : $this->coordinator->generate_idempotency_key();
		if ( '' === $key ) {
			return $this->error( 'idempotency_key_failed', 503 );
		}
		if ( ! Contract_Boundary::idempotency_key( $key ) ) {
			return $this->error( 'invalid_idempotency_key', 400 );
		}
		$result = $this->coordinator->submit_revision( get_current_user_id(), (string) $session['adapter_key'], $session['native_reference'], $key, $context['payload'] );
		if ( $result instanceof WP_Error ) {
			$held = $this->sessions->apply_policy_error( $session, $result );
			$this->record_failure( $held, 'revision_submit_failed', $result );
			return $this->normalize_error( $result, array( 'session' => $this->public_session( $held ) ) );
		}
		$native_status = sanitize_key( (string) ( $result['status'] ?? 'pending_review' ) );
		$state_map = array(
			'draft' => 'draft',
			'pending_review' => 'submitted',
			'submitted' => 'submitted',
			'under_review' => 'under_review',
			'changes_requested' => 'changes_requested',
			'approved' => 'approved',
			'published' => 'published',
			'scheduled' => 'scheduled',
			'rejected' => 'rejected',
			'withdrawn' => 'withdrawn',
		);
		if ( ! isset( $state_map[ $native_status ] ) ) {
			return $this->error( 'invalid_native_revision_status', 502 );
		}
		$state = $state_map[ $native_status ];
		$updated = $this->sessions->update( (string) $session['session_uuid'], get_current_user_id(), (int) $session['lock_version'], $state, $session['native_reference'], $key );
		if ( $updated instanceof WP_Error ) {
			return $this->normalize_error( $updated );
		}
		$this->record_success( $updated, 'revision_submitted', 'revision_submitted' );
		return $this->response( array( 'session' => $this->public_session( $updated ), 'native' => $result ) );
	}

	public function begin_upload( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$session = $this->owned_session( $request );
		if ( $session instanceof WP_Error ) {
			return $session;
		}
		if ( ! $this->session_allows_operation( $session, 'upload' ) ) {
			return $this->error( 'session_not_editable', 409, array( 'session' => $this->public_session( $session, false ) ) );
		}
		$body = $this->body( $request );
		if ( $body instanceof WP_Error ) {
			return $body;
		}
		$purpose  = sanitize_key( (string) ( $body['purpose'] ?? '' ) );
		$metadata = isset( $body['metadata'] ) && is_array( $body['metadata'] ) ? $body['metadata'] : array();
		if ( ! Contract_Boundary::code( $purpose ) ) {
			return $this->error( 'invalid_upload_purpose', 400 );
		}
		if ( count( $metadata ) > 32 || strlen( (string) wp_json_encode( $metadata ) ) > 16384 ) {
			return $this->error( 'upload_metadata_too_large', 413 );
		}
		$result   = $this->coordinator->begin_upload( get_current_user_id(), (string) $session['adapter_key'], $purpose, $metadata );
		if ( $result instanceof WP_Error ) {
			return $this->normalize_error( $result );
		}
		$record = $this->uploads->register( (string) $session['session_uuid'], get_current_user_id(), (string) $session['adapter_key'], (string) $result['upload_reference'], $purpose, $metadata );
		if ( $record instanceof WP_Error ) {
			$this->coordinator->cancel_upload( get_current_user_id(), (string) $session['adapter_key'], (string) $result['upload_reference'] );
			return $this->normalize_error( $record );
		}
		$this->record_success( $session, 'upload_issued', 'upload_issued' );
		return $this->response( array( 'upload' => $this->public_upload( $record ), 'native' => $result ), 201 );
	}

	public function complete_upload( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$context = $this->upload_context( $request );
		if ( $context instanceof WP_Error ) {
			return $context;
		}
		$result = $this->coordinator->complete_upload( get_current_user_id(), (string) $context['session']['adapter_key'], (string) $context['upload']['native_upload_reference'] );
		if ( $result instanceof WP_Error ) {
			return $this->normalize_error( $result );
		}
		$expected = in_array( (string) $context['upload']['status'], array( 'issued', 'uploading' ), true ) ? (string) $context['upload']['status'] : 'issued';
		$updated  = $this->uploads->transition( (string) $context['upload']['upload_uuid'], get_current_user_id(), $expected, 'completed' );
		if ( $updated instanceof WP_Error ) {
			return $this->normalize_error( $updated );
		}
		$this->record_success( $context['session'], 'upload_completed', 'upload_completed' );
		return $this->response( array( 'upload' => $this->public_upload( $updated ), 'native' => $result ) );
	}

	public function cancel_upload( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$context = $this->upload_context( $request );
		if ( $context instanceof WP_Error ) {
			return $context;
		}
		$result = $this->coordinator->cancel_upload( get_current_user_id(), (string) $context['session']['adapter_key'], (string) $context['upload']['native_upload_reference'] );
		if ( $result instanceof WP_Error ) {
			return $this->normalize_error( $result );
		}
		$expected = in_array( (string) $context['upload']['status'], array( 'issued', 'uploading', 'completed' ), true ) ? (string) $context['upload']['status'] : 'issued';
		$updated  = $this->uploads->transition( (string) $context['upload']['upload_uuid'], get_current_user_id(), $expected, 'cancelled' );
		if ( $updated instanceof WP_Error ) {
			return $this->normalize_error( $updated );
		}
		$this->record_success( $context['session'], 'upload_cancelled', 'upload_cancelled' );
		return $this->response( array( 'upload' => $this->public_upload( $updated ) ) );
	}

	/** @return array{session:array<string,mixed>,payload:array<string,mixed>}|WP_Error */
	private function operation_context( WP_REST_Request $request ): array|WP_Error {
		$session = $this->owned_session( $request );
		if ( $session instanceof WP_Error ) {
			return $session;
		}
		$route = trim( (string) $request->get_route(), '/' );
		$operation = str_ends_with( $route, '/revision' ) ? 'revision' : 'patch';
		if ( ! $this->session_allows_operation( $session, $operation ) ) {
			return $this->error( 'session_not_editable', 409, array( 'session' => $this->public_session( $session, false ) ) );
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

	/** @return array{session:array<string,mixed>,upload:array<string,mixed>}|WP_Error */
	private function upload_context( WP_REST_Request $request ): array|WP_Error {
		$session = $this->owned_session( $request );
		if ( $session instanceof WP_Error ) {
			return $session;
		}
		if ( ! $this->session_allows_operation( $session, 'upload' ) ) {
			return $this->error( 'session_not_editable', 409, array( 'session' => $this->public_session( $session, false ) ) );
		}
		$upload = $this->uploads->get_owned( strtolower( (string) $request['upload'] ), get_current_user_id() );
		if ( $upload instanceof WP_Error ) {
			return $this->normalize_error( $upload );
		}
		if ( ! hash_equals( (string) $session['session_uuid'], (string) $upload['session_uuid'] ) || ! hash_equals( (string) $session['adapter_key'], (string) $upload['adapter_key'] ) ) {
			return $this->error( 'upload_session_mismatch', 409 );
		}
		return array( 'session' => $session, 'upload' => $upload );
	}


	/** @param array<string,mixed> $session */
	private function session_allows_operation( array $session, string $operation ): bool {
		if ( ! empty( $session['reconciliation_required'] ) || in_array( (string) $session['state'], array( 'submitting', 'reconcile' ), true ) ) {
			return false;
		}
		if ( 'abandoned' === (string) ( $session['composer_state'] ?? '' ) || 'deleted' === (string) ( $session['publication_state'] ?? '' ) ) {
			return false;
		}
		if ( 'patch' === $operation ) {
			return in_array( (string) $session['state'], array( 'new', 'draft', 'valid', 'changes_requested', 'rejected', 'failed', 'withdrawn' ), true );
		}
		return true;
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

	/** @return array<string,mixed>|WP_Error */
	private function body_optional( WP_REST_Request $request ): array|WP_Error {
		$raw = (string) $request->get_body();
		if ( strlen( $raw ) > self::MAX_REQUEST_BYTES ) {
			return $this->error( 'request_too_large', 413 );
		}
		if ( '' === trim( $raw ) ) {
			return array();
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

	/** @param array<string,mixed> $session */
	private function record_success( array $session, string $event_code, string $projection ): void {
		$native = is_string( $session['native_reference'] ?? null ) ? (string) $session['native_reference'] : null;
		$this->audit->record( get_current_user_id(), (string) $session['adapter_key'], $event_code, 'success', (string) $session['session_uuid'], $native );
		do_action( 'supc_workflow_projection', $projection, $this->projection_metadata( $session ) );
	}

	/** @param array<string,mixed> $session */
	private function record_failure( array $session, string $event_code, WP_Error $error ): void {
		$code = is_callable( array( $error, 'get_error_code' ) ) ? (string) $error->get_error_code() : 'native_error';
		$this->audit->record( get_current_user_id(), (string) $session['adapter_key'], $event_code, 'failed', (string) $session['session_uuid'], null, $code );
	}

	/** @param array<string,mixed> $session @return array<string,mixed> */
	private function projection_metadata( array $session ): array {
		return array(
			'session_uuid'      => (string) $session['session_uuid'],
			'adapter_key'       => (string) $session['adapter_key'],
			'review_state'      => (string) ( $session['review_state'] ?? 'draft' ),
			'publication_state' => (string) ( $session['publication_state'] ?? 'unpublished' ),
			'hold_state'        => (string) ( $session['hold_state'] ?? 'clear' ),
			'native_reference_hash' => is_string( $session['native_reference'] ?? null ) ? hash( 'sha256', (string) $session['native_reference'] ) : '',
		);
	}

	/** @param array<string,mixed> $session @return array<string,mixed> */
	private function public_session( array $session, bool $expose_native_reference = true ): array {
		return array(
			'session_uuid'           => (string) $session['session_uuid'],
			'adapter_key'             => (string) $session['adapter_key'],
			'adapter_version'         => (string) $session['adapter_version'],
			'native_reference'       => $expose_native_reference ? $session['native_reference'] : null,
			'native_reference_present' => is_string( $session['native_reference'] ?? null ) && '' !== (string) $session['native_reference'],
			'state'                   => (string) $session['state'],
			'composer_state'          => (string) ( $session['composer_state'] ?? 'new' ),
			'review_state'            => (string) ( $session['review_state'] ?? 'draft' ),
			'publication_state'       => (string) ( $session['publication_state'] ?? 'unpublished' ),
			'hold_state'              => (string) ( $session['hold_state'] ?? 'clear' ),
			'reconciliation_required' => ! empty( $session['reconciliation_required'] ),
			'lock_version'           => (int) $session['lock_version'],
			'updated_at'             => (string) $session['updated_at'],
			'expires_at'             => (string) $session['expires_at'],
		);
	}

	/** @param array<string,mixed> $upload @return array<string,mixed> */
	private function public_upload( array $upload ): array {
		return array(
			'upload_uuid'    => (string) $upload['upload_uuid'],
			'session_uuid'   => (string) $upload['session_uuid'],
			'purpose'        => (string) $upload['purpose'],
			'mime_type'      => (string) $upload['mime_type'],
			'size_bytes'     => (int) $upload['size_bytes'],
			'status'         => (string) $upload['status'],
			'updated_at'     => (string) $upload['updated_at'],
			'expires_at'     => (string) $upload['expires_at'],
		);
	}

	private function response( array $data, int $status = 200 ): WP_REST_Response {
		$response = new WP_REST_Response( $data, $status );
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
		$response->header( 'CDN-Cache-Control', 'no-store' );
		$response->header( 'Surrogate-Control', 'no-store' );
		$response->header( 'X-Robots-Tag', 'noindex, nofollow, noarchive' );
		$response->header( 'Vary', 'Cookie, X-WP-Nonce' );
		return $response;
	}

	private function normalize_error( WP_Error $error, array $extra = array() ): WP_Error {
		$raw_code = is_callable( array( $error, 'get_error_code' ) ) ? (string) $error->get_error_code() : 'native_error';
		$raw_data = is_callable( array( $error, 'get_error_data' ) ) ? $error->get_error_data( $raw_code ) : null;
		$status   = is_array( $raw_data ) && isset( $raw_data['status'] ) && is_int( $raw_data['status'] ) ? $raw_data['status'] : 400;
		if ( 'supc_policy_violation' === $raw_code ) {
			$status = 422;
		}
		return $this->error( str_replace( 'supc_', '', sanitize_key( $raw_code ) ), $status, array_merge( is_array( $raw_data ) ? $raw_data : array(), $extra ) );
	}

	private function error( string $code, int $status, mixed $data = null ): WP_Error {
		return new WP_Error( 'supc_' . sanitize_key( $code ), __( 'The Composer request could not be completed.', 'sabri-universal-post-composer' ), array( 'status' => $status, 'details' => $data ) );
	}
}
