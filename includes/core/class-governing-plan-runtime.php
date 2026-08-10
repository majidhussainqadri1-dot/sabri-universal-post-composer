<?php
/**
 * Runtime support for the August 2026 governing-plan completion contract.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

namespace Sabri\UniversalComposer\Core;

use Sabri\UniversalComposer\Contracts\Governed_Workflow_Adapter;
use Sabri\UniversalComposer\Contracts\Lifecycle_Adapter;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds plan-governed metadata, adapter certification, and non-owning lifecycle
 * orchestration while preserving each native module as the source of truth.
 */
final class Governing_Plan_Runtime {
	private const REST_NAMESPACE = 'sabri-composer/v1';
	private const MAX_PROFILE_BYTES = 65536;
	private const MAX_COMMAND_PAYLOAD_BYTES = 1048576;
	private const MAX_NOTIFICATION_EVENTS = 50;
	private const MAX_FEATURES = 32;
	private const MAX_COMMANDS = 16;
	private const MAX_DEPTH = 12;
	private const MAX_NODES = 10000;

	private const ALLOWED_FEATURES = array(
		'rights_license',
		'accessibility_authoring',
		'translation',
		'corrections',
		'revision_history',
		'scheduling',
		'patient_case_safety',
		'medical_safety',
		'source_evidence',
		'preview_matrix',
		'search_projection',
		'notification_events',
	);

	private const REQUIRED_SOCIAL_FEATURES = array(
		'rights_license',
		'accessibility_authoring',
		'translation',
		'corrections',
		'revision_history',
		'scheduling',
		'medical_safety',
		'source_evidence',
		'preview_matrix',
		'search_projection',
		'notification_events',
	);

	private const ALLOWED_COMMANDS = array(
		'edit',
		'revise',
		'correct',
		'schedule',
		'unschedule',
		'withdraw',
		'archive',
		'restore',
	);

	private const COMMAND_FEATURES = array(
		'edit'       => 'revision_history',
		'revise'     => 'revision_history',
		'correct'    => 'corrections',
		'schedule'   => 'scheduling',
		'unschedule' => 'scheduling',
		'withdraw'   => 'corrections',
		'archive'    => 'revision_history',
		'restore'    => 'revision_history',
	);

	private static ?self $instance = null;
	private Workflow_Validator $validator;
	private bool $booted = false;

	private function __construct(
		private Registry $registry,
		private Permission_Resolver $permissions
	) {
		$this->validator = new Workflow_Validator();
	}

	public static function instance(): self {
		if ( null === self::$instance ) {
			$plugin = Plugin::instance();
			self::$instance = new self( $plugin->registry(), new Permission_Resolver() );
		}
		return self::$instance;
	}

	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_filter( 'supc_system_check_report', array( $this, 'append_system_check' ), 30 );
	}

	public function register_rest_routes(): void {
		$permission = array( $this, 'rest_permission' );
		register_rest_route(
			self::REST_NAMESPACE,
			'/governance/(?P<adapter>[a-z][a-z0-9._-]{0,63})',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_governance' ),
				'permission_callback' => $permission,
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/lifecycle/(?P<adapter>[a-z][a-z0-9._-]{0,63})/(?P<reference>[A-Za-z0-9][A-Za-z0-9._:-]{0,254})',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'rest_lifecycle_capabilities' ),
					'permission_callback' => $permission,
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'rest_lifecycle_execute' ),
					'permission_callback' => $permission,
				),
			)
		);
	}

	public function rest_permission( WP_REST_Request $request ): bool|WP_Error {
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
		return true;
	}

	public function rest_governance( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$result = $this->governance_profile(
			get_current_user_id(),
			sanitize_key( (string) $request['adapter'] )
		);
		return $result instanceof WP_Error ? $result : $this->response( $result );
	}

	public function rest_lifecycle_capabilities( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$result = $this->lifecycle_capabilities(
			get_current_user_id(),
			sanitize_key( (string) $request['adapter'] ),
			(string) $request['reference']
		);
		return $result instanceof WP_Error ? $result : $this->response( $result );
	}

	public function rest_lifecycle_execute( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			return $this->error( 'invalid_request_body', 400 );
		}
		$result = $this->execute_lifecycle(
			get_current_user_id(),
			sanitize_key( (string) $request['adapter'] ),
			(string) $request['reference'],
			is_string( $body['command'] ?? null ) ? sanitize_key( $body['command'] ) : '',
			is_string( $body['idempotency_key'] ?? null ) ? (string) $body['idempotency_key'] : '',
			is_array( $body['payload'] ?? null ) ? $body['payload'] : array()
		);
		return $result instanceof WP_Error ? $result : $this->response( $result );
	}

	/**
	 * Return a normalized public-safe governance contract after current File 00
	 * authority and the adapter's central capability are revalidated.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function governance_profile( int $user_id, string $adapter_key ): array|WP_Error {
		$resolved = $this->resolve_governed_adapter( $user_id, $adapter_key );
		if ( $resolved instanceof WP_Error ) {
			return $resolved;
		}
		$profile = $this->profile_for_adapter( $resolved, $adapter_key );
		if ( $profile instanceof WP_Error ) {
			return $profile;
		}
		return array(
			'adapter_key'            => $adapter_key,
			'governance_api_version' => SUPC_GOVERNANCE_API_VERSION,
			'profile'                => $profile,
		);
	}

	/**
	 * @return array{adapter_key:string,native_reference:string,commands:array<int,string>}|WP_Error
	 */
	public function lifecycle_capabilities( int $user_id, string $adapter_key, string $native_reference ): array|WP_Error {
		$adapter = $this->resolve_lifecycle_adapter( $user_id, $adapter_key, $native_reference );
		if ( $adapter instanceof WP_Error ) {
			return $adapter;
		}
		try {
			$commands = $adapter->lifecycle_capabilities( $user_id, $native_reference );
			if ( $commands instanceof WP_Error ) {
				return $this->native_error( 'lifecycle_capabilities', $adapter_key );
			}
			$commands = $this->normalize_commands( $commands );
			if ( null === $commands ) {
				return $this->error( 'invalid_lifecycle_capabilities', 502, $adapter_key );
			}
			return array(
				'adapter_key'      => $adapter_key,
				'native_reference' => $native_reference,
				'commands'         => $commands,
			);
		} catch ( Throwable $error ) {
			unset( $error );
			return $this->error( 'lifecycle_adapter_exception', 502, $adapter_key );
		}
	}

	/**
	 * Execute a correction/revision/scheduling command through the native owner.
	 * File 22 stores no command payload and never mutates the native object itself.
	 *
	 * @param array<string, mixed> $payload Native command payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public function execute_lifecycle(
		int $user_id,
		string $adapter_key,
		string $native_reference,
		string $command,
		string $idempotency_key,
		array $payload
	): array|WP_Error {
		if ( ! in_array( $command, self::ALLOWED_COMMANDS, true ) ) {
			return $this->error( 'unsupported_lifecycle_command', 400, $adapter_key );
		}
		if ( ! $this->validator->valid_idempotency_key( $idempotency_key ) ) {
			return $this->error( 'invalid_idempotency_key', 400, $adapter_key );
		}
		if ( ! $this->bounded_payload( $payload ) ) {
			return $this->error( 'lifecycle_payload_invalid', 400, $adapter_key );
		}

		$adapter = $this->resolve_lifecycle_adapter( $user_id, $adapter_key, $native_reference );
		if ( $adapter instanceof WP_Error ) {
			return $adapter;
		}
		$profile = $this->profile_for_adapter( $adapter, $adapter_key );
		if ( $profile instanceof WP_Error ) {
			return $profile;
		}
		$required_feature = self::COMMAND_FEATURES[ $command ];
		if ( ! in_array( $required_feature, $profile['authoring_features'], true ) ) {
			return $this->error( 'lifecycle_feature_not_declared', 409, $adapter_key );
		}

		$capabilities = $this->lifecycle_capabilities( $user_id, $adapter_key, $native_reference );
		if ( $capabilities instanceof WP_Error ) {
			return $capabilities;
		}
		if ( ! in_array( $command, $capabilities['commands'], true ) ) {
			return $this->error( 'lifecycle_permission_denied', 403, $adapter_key );
		}
		if ( ! $this->central_authority_allows( $user_id, $adapter_key, (string) $profile['edit_capability'] ) ) {
			return $this->error( 'lifecycle_permission_denied', 403, $adapter_key );
		}

		try {
			$result = $adapter->execute_lifecycle(
				$user_id,
				$native_reference,
				$command,
				$idempotency_key,
				$payload
			);
			if ( $result instanceof WP_Error ) {
				return $this->native_error( 'execute_lifecycle', $adapter_key );
			}
			$validated = $this->validator->status_result( $result, $adapter_key );
			if ( $validated instanceof WP_Error ) {
				return $validated;
			}
			$actual = $validated['native_reference'] ?? null;
			if ( ! is_string( $actual ) || ! hash_equals( $native_reference, $actual ) ) {
				return $this->error( 'native_reference_mismatch', 502, $adapter_key );
			}
			do_action(
				'supc_lifecycle_executed',
				Contract_Boundary::public_identifier( $adapter_key ),
				$command,
				(string) $validated['status']
			);
			return $validated;
		} catch ( Throwable $error ) {
			unset( $error );
			return $this->error( 'lifecycle_adapter_exception', 502, $adapter_key );
		}
	}

	/**
	 * Add plan-coverage evidence without treating unavailable optional native
	 * modules as File 22-owned backends.
	 *
	 * @param array<int, array<string, mixed>> $rows Existing system-check rows.
	 * @return array<int, array<string, mixed>>
	 */
	public function append_system_check( array $rows ): array {
		$rows[] = $this->social_governance_row();
		$rows[] = $this->adapter_coverage_row();
		return $rows;
	}

	/** @return array<string, mixed> */
	private function social_governance_row(): array {
		$adapter = $this->registry->get( 'social_publication' );
		$codes   = array();
		if ( ! $adapter instanceof Governed_Workflow_Adapter ) {
			$codes[] = 'governed_workflow_contract_missing';
		} else {
			$profile = $this->profile_for_adapter( $adapter, 'social_publication' );
			if ( $profile instanceof WP_Error ) {
				$codes[] = 'governance_profile_invalid';
			} else {
				foreach ( self::REQUIRED_SOCIAL_FEATURES as $feature ) {
					if ( ! in_array( $feature, $profile['authoring_features'], true ) ) {
						$codes[] = 'feature_missing_' . $feature;
					}
				}
			}
			if ( ! $adapter instanceof Lifecycle_Adapter ) {
				$codes[] = 'lifecycle_contract_missing';
			} elseif ( SUPC_LIFECYCLE_API_VERSION !== $adapter->lifecycle_api_version() ) {
				$codes[] = 'lifecycle_api_mismatch';
			}
		}
		$codes = array_values( array_unique( array_slice( $codes, 0, 30 ) ) );
		return array(
			'key'    => 'governing_plan_social_contract',
			'status' => array() === $codes ? 'pass' : 'fail',
			'count'  => count( $codes ),
			'codes'  => $codes,
		);
	}

	/** @return array<string, mixed> */
	private function adapter_coverage_row(): array {
		$catalog = $this->approved_adapter_catalog();
		$present = array();
		foreach ( $this->registry->all() as $adapter ) {
			try {
				$present[] = $adapter->native_module();
			} catch ( Throwable $error ) {
				unset( $error );
			}
		}
		$present = array_values( array_unique( $present ) );
		$missing = array();
		foreach ( $catalog as $key => $definition ) {
			if ( 'core' === $definition['tier'] ) {
				continue;
			}
			if ( ! in_array( $definition['native_module'], $present, true ) ) {
				$missing[] = 'optional_adapter_unavailable_' . $key;
			}
		}
		return array(
			'key'    => 'approved_adapter_coverage',
			'status' => array() === $missing ? 'pass' : 'warning',
			'count'  => count( $missing ),
			'codes'  => array_slice( $missing, 0, 30 ),
		);
	}

	/**
	 * @return array<string, array{native_module:string,tier:string}>
	 */
	public function approved_adapter_catalog(): array {
		return array(
			'social_publication' => array( 'native_module' => 'sabri-complete-home-news-feed', 'tier' => 'core' ),
			'learning'           => array( 'native_module' => 'learn-sabri-classical-homeopathy', 'tier' => 'adapter' ),
			'encyclopedia'       => array( 'native_module' => 'homeopathy-encyclopedia', 'tier' => 'adapter' ),
			'video'              => array( 'native_module' => 'sabri-video-wall', 'tier' => 'adapter' ),
			'reel'               => array( 'native_module' => 'sabri-reels', 'tier' => 'adapter' ),
			'pdf'                => array( 'native_module' => 'sabri-pdf-library', 'tier' => 'adapter' ),
			'marketplace'        => array( 'native_module' => 'sabri-marketplace', 'tier' => 'adapter' ),
		);
	}

	/** @return Governed_Workflow_Adapter|WP_Error */
	private function resolve_governed_adapter( int $user_id, string $adapter_key ): Governed_Workflow_Adapter|WP_Error {
		if ( Safe_Mode::disabled() || $user_id <= 0 || ! Contract_Boundary::adapter_key( $adapter_key ) ) {
			return $this->error( 'governance_request_denied', 403, $adapter_key );
		}
		$adapter  = $this->registry->get( $adapter_key );
		$contract = $this->registry->adapter_contract( $adapter_key );
		if ( ! $adapter instanceof Governed_Workflow_Adapter || null === $contract ) {
			return $this->error( 'governance_contract_unavailable', 409, $adapter_key );
		}
		if ( ! $this->central_authority_allows( $user_id, $adapter_key, $contract['required_capability'] ) ) {
			return $this->error( 'governance_request_denied', 403, $adapter_key );
		}
		try {
			if ( ! $adapter->is_available() ) {
				return $this->error( 'native_workflow_unavailable', 503, $adapter_key );
			}
		} catch ( Throwable $error ) {
			unset( $error );
			return $this->error( 'governance_adapter_exception', 502, $adapter_key );
		}
		return $adapter;
	}

	/** @return Lifecycle_Adapter|WP_Error */
	private function resolve_lifecycle_adapter( int $user_id, string $adapter_key, string $native_reference ): Lifecycle_Adapter|WP_Error {
		if ( ! $this->validator->valid_reference( $native_reference ) ) {
			return $this->error( 'invalid_native_reference', 400, $adapter_key );
		}
		$adapter = $this->resolve_governed_adapter( $user_id, $adapter_key );
		if ( $adapter instanceof WP_Error ) {
			return $adapter;
		}
		if ( ! $adapter instanceof Lifecycle_Adapter ) {
			return $this->error( 'lifecycle_contract_unavailable', 409, $adapter_key );
		}
		if ( SUPC_LIFECYCLE_API_VERSION !== $adapter->lifecycle_api_version() ) {
			return $this->error( 'lifecycle_api_mismatch', 409, $adapter_key );
		}
		$profile = $this->profile_for_adapter( $adapter, $adapter_key );
		if ( $profile instanceof WP_Error ) {
			return $profile;
		}
		if ( ! $this->central_authority_allows( $user_id, $adapter_key, (string) $profile['edit_capability'] ) ) {
			return $this->error( 'lifecycle_permission_denied', 403, $adapter_key );
		}
		return $adapter;
	}

	/** @return array<string, mixed>|WP_Error */
	private function profile_for_adapter( Governed_Workflow_Adapter $adapter, string $adapter_key ): array|WP_Error {
		try {
			if ( SUPC_GOVERNANCE_API_VERSION !== $adapter->governance_api_version() ) {
				return $this->error( 'governance_api_mismatch', 409, $adapter_key );
			}
			$profile = $adapter->governance_profile();
			return $this->normalize_profile( $profile, $adapter_key );
		} catch ( Throwable $error ) {
			unset( $error );
			return $this->error( 'governance_adapter_exception', 502, $adapter_key );
		}
	}

	/** @param array<string, mixed> $profile */
	private function normalize_profile( array $profile, string $adapter_key ): array|WP_Error {
		$encoded = wp_json_encode( $profile );
		if ( ! is_string( $encoded ) || strlen( $encoded ) > self::MAX_PROFILE_BYTES ) {
			return $this->error( 'governance_profile_invalid', 502, $adapter_key );
		}
		$features = $this->normalize_features( $profile['authoring_features'] ?? null );
		$events   = $this->normalize_event_codes( $profile['notification_events'] ?? null );
		$media    = $profile['media_rules'] ?? null;
		$edit     = $profile['edit_capability'] ?? null;
		$cleanup  = $profile['cleanup_policy'] ?? null;
		$search   = $profile['search_indexing_policy'] ?? null;
		if (
			null === $features || null === $events ||
			! is_string( $media ) || ! Contract_Boundary::code( $media ) ||
			! is_string( $edit ) || ! Contract_Boundary::capability( $edit ) ||
			! is_string( $cleanup ) || ! in_array( $cleanup, array( 'native_owner', 'reversible_native', 'none' ), true ) ||
			! is_string( $search ) || ! in_array( $search, array( 'native_canonical', 'conditional_native', 'noindex' ), true )
		) {
			return $this->error( 'governance_profile_invalid', 502, $adapter_key );
		}
		return array(
			'authoring_features'     => $features,
			'media_rules'            => $media,
			'edit_capability'        => $edit,
			'cleanup_policy'         => $cleanup,
			'search_indexing_policy' => $search,
			'notification_events'    => $events,
		);
	}

	/** @return array<int, string>|null */
	private function normalize_features( mixed $features ): ?array {
		if ( ! is_array( $features ) || array_values( $features ) !== $features || count( $features ) > self::MAX_FEATURES ) {
			return null;
		}
		$normalized = array();
		foreach ( $features as $feature ) {
			if ( ! is_string( $feature ) || ! in_array( $feature, self::ALLOWED_FEATURES, true ) ) {
				return null;
			}
			$normalized[ $feature ] = $feature;
		}
		return array_values( $normalized );
	}

	/** @return array<int, string>|null */
	private function normalize_event_codes( mixed $events ): ?array {
		if ( ! is_array( $events ) || array_values( $events ) !== $events || count( $events ) > self::MAX_NOTIFICATION_EVENTS ) {
			return null;
		}
		$normalized = array();
		foreach ( $events as $event ) {
			if ( ! is_string( $event ) || ! Contract_Boundary::code( $event ) ) {
				return null;
			}
			$normalized[ $event ] = $event;
		}
		return array_values( $normalized );
	}

	/** @return array<int, string>|null */
	private function normalize_commands( mixed $commands ): ?array {
		if ( ! is_array( $commands ) || array_values( $commands ) !== $commands || count( $commands ) > self::MAX_COMMANDS ) {
			return null;
		}
		$normalized = array();
		foreach ( $commands as $command ) {
			if ( ! is_string( $command ) || ! in_array( $command, self::ALLOWED_COMMANDS, true ) ) {
				return null;
			}
			$normalized[ $command ] = $command;
		}
		return array_values( $normalized );
	}

	private function central_authority_allows( int $user_id, string $adapter_key, string $capability ): bool {
		if ( $user_id <= 0 || ! Contract_Boundary::adapter_key( $adapter_key ) || ! Contract_Boundary::capability( $capability ) ) {
			return false;
		}
		return ! Safe_Mode::disabled()
			&& $this->permissions->account_is_eligible( $user_id )
			&& $this->permissions->can_use_capability( $user_id, $capability );
	}

	/** @param array<string, mixed> $payload */
	private function bounded_payload( array $payload ): bool {
		$remaining = self::MAX_NODES;
		if ( ! $this->safe_value( $payload, 0, $remaining ) ) {
			return false;
		}
		$encoded = wp_json_encode( $payload );
		return is_string( $encoded ) && strlen( $encoded ) <= self::MAX_COMMAND_PAYLOAD_BYTES;
	}

	private function safe_value( mixed $value, int $depth, int &$remaining ): bool {
		--$remaining;
		if ( $remaining < 0 || $depth > self::MAX_DEPTH ) {
			return false;
		}
		if ( null === $value || is_bool( $value ) || is_int( $value ) ) {
			return true;
		}
		if ( is_float( $value ) ) {
			return is_finite( $value );
		}
		if ( is_string( $value ) ) {
			return Contract_Boundary::valid_utf8( $value );
		}
		if ( ! is_array( $value ) || count( $value ) > 1000 ) {
			return false;
		}
		foreach ( $value as $key => $item ) {
			if ( ! is_int( $key ) && ! is_string( $key ) ) {
				return false;
			}
			if ( is_string( $key ) && ( strlen( $key ) > 255 || ! Contract_Boundary::valid_utf8( $key ) ) ) {
				return false;
			}
			if ( ! $this->safe_value( $item, $depth + 1, $remaining ) ) {
				return false;
			}
		}
		return true;
	}

	private function response( array $data, int $status = 200 ): WP_REST_Response {
		$response = new WP_REST_Response( $data, $status );
		$response->header( 'Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0' );
		$response->header( 'Pragma', 'no-cache' );
		$response->header( 'X-Robots-Tag', 'noindex, nofollow, noarchive' );
		return $response;
	}

	private function native_error( string $operation, string $adapter_key ): WP_Error {
		do_action(
			'supc_lifecycle_native_error',
			Contract_Boundary::public_identifier( $adapter_key ),
			sanitize_key( $operation )
		);
		return $this->error( 'native_lifecycle_error', 409, $adapter_key );
	}

	private function error( string $code, int $status, string $adapter_key = '' ): WP_Error {
		return new WP_Error(
			'supc_' . sanitize_key( $code ),
			__( 'The governed composer request could not be completed safely.', 'sabri-universal-post-composer' ),
			array(
				'status'      => $status,
				'adapter_key' => Contract_Boundary::public_identifier( $adapter_key ),
			)
		);
	}
}
