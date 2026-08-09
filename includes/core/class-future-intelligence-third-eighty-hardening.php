<?php
/**
 * Third independent eighty-round server hardening for Future Composer Intelligence.
 *
 * This guard runs after REST permission callbacks but before the File 22 route
 * callback. It therefore blocks authority spoofing, stale authorization,
 * sensitive provider egress and contextual abuse before any capability-result
 * filter or native Future provider can execute.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

namespace Sabri\UniversalComposer\Core;

use WP_Error;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Future_Intelligence_Third_Eighty_Hardening {
	private const SESSION_BOUND_CAPABILITIES = array(
		'collaboration',
		'review_annotations',
		'semantic_diff',
		'conflict_merge',
	);

	private const EXTERNAL_ADVISORY = array(
		'ai_copilot',
		'medical_terminology',
		'collaboration',
		'review_annotations',
		'semantic_diff',
		'conflict_merge',
		'template_library',
		'cross_format_derivative',
		'publication_impact',
	);

	private const RESERVED_AUTHORITY_KEYS = array(
		'_supc_context',
		'adapter_key',
		'author_id',
		'correlation_id',
		'effective_author',
		'lock_version',
		'native_reference',
		'sensitivity_class',
		'session_uuid',
		'user_id',
	);

	private const RATE_LIMIT  = 60;
	private const RATE_WINDOW = 60;
	private bool $registered  = false;

	public function register(): void {
		if ( $this->registered || ! function_exists( 'add_filter' ) ) {
			return;
		}
		$this->registered = true;
		add_filter( 'rest_request_before_callbacks', array( $this, 'before_callbacks' ), -1000, 3 );
	}

	/**
	 * @param mixed $response Earlier pre-callback response, normally null.
	 * @param mixed $handler  Matched REST handler metadata.
	 * @return mixed
	 */
	public function before_callbacks( mixed $response, mixed $handler, WP_REST_Request $request ): mixed {
		unset( $handler );
		if ( null !== $response ) {
			return $response;
		}
		$route = method_exists( $request, 'get_route' ) ? (string) $request->get_route() : '';
		if ( ! str_ends_with( $route, '/future/invoke' ) ) {
			return $response;
		}

		$user_id = get_current_user_id();
		if ( $user_id <= 0 || ! ( new Permission_Resolver() )->account_is_eligible( $user_id ) ) {
			return $this->error( 'future_third_preflight_subject_denied', 403 );
		}

		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			return $this->error( 'future_third_preflight_invalid_body', 400 );
		}
		$adapter_key = isset( $body['adapter_key'] ) && is_string( $body['adapter_key'] ) ? sanitize_key( $body['adapter_key'] ) : '';
		$capability  = isset( $body['capability'] ) && is_string( $body['capability'] ) ? sanitize_key( $body['capability'] ) : '';
		$payload     = isset( $body['payload'] ) && is_array( $body['payload'] ) ? $body['payload'] : array();
		$session     = isset( $body['session_uuid'] ) && is_string( $body['session_uuid'] ) ? strtolower( trim( $body['session_uuid'] ) ) : '';

		if ( ! Contract_Boundary::adapter_key( $adapter_key ) || '' === $capability ) {
			return $this->error( 'future_third_preflight_invalid_request', 400 );
		}
		if ( $this->payload_has_reserved_authority_key( $payload ) ) {
			return $this->error( 'future_client_authority_field_prohibited', 400 );
		}

		try {
			$registry  = Plugin::instance()->registry();
			$available = $registry->available_for_user( $user_id );
		} catch ( \Throwable $error ) {
			unset( $error );
			return $this->error( 'future_third_preflight_registry_unavailable', 503 );
		}
		if ( ! isset( $available[ $adapter_key ] ) ) {
			return $this->error( 'future_third_preflight_adapter_denied', 403 );
		}

		if ( in_array( $capability, self::SESSION_BOUND_CAPABILITIES, true ) ) {
			if ( 1 !== preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $session ) ) {
				return $this->error( 'future_third_preflight_session_required', 409 );
			}
			$owned = ( new Session_Store() )->get_owned( $session, $user_id );
			if ( $owned instanceof WP_Error || ! hash_equals( $adapter_key, (string) ( $owned['adapter_key'] ?? '' ) ) ) {
				return $this->error( 'future_third_preflight_session_stale', 409 );
			}
		}

		$action = isset( $payload['action'] ) && is_string( $payload['action'] ) ? sanitize_key( $payload['action'] ) : '';
		if ( 'collaboration' === $capability && 'join' === $action && true !== ( $payload['user_initiated'] ?? false ) ) {
			return $this->error( 'future_collaboration_join_requires_user_intent', 409 );
		}

		if ( in_array( $capability, self::EXTERNAL_ADVISORY, true ) && $this->payload_contains_sensitive_shape( $payload ) ) {
			$allowed = (bool) apply_filters( 'supc_future_sensitive_capability_allowed', false, $capability, $user_id, $adapter_key );
			if ( ! $allowed ) {
				return $this->error( 'future_sensitive_external_advisory_blocked', 403 );
			}
		}

		if ( ! $this->within_context_rate_limit( $user_id, $adapter_key, $capability ) ) {
			return $this->error( 'future_third_context_rate_limited', 429 );
		}
		return $response;
	}

	/** @param array<string,mixed> $payload */
	private function payload_has_reserved_authority_key( array $payload ): bool {
		$walk = static function ( mixed $value, int $depth = 0 ) use ( &$walk ): bool {
			if ( $depth > 8 ) {
				return true;
			}
			if ( ! is_array( $value ) ) {
				return false;
			}
			foreach ( $value as $key => $child ) {
				$normalized = is_string( $key ) ? sanitize_key( $key ) : '';
				if ( '' !== $normalized && in_array( $normalized, self::RESERVED_AUTHORITY_KEYS, true ) ) {
					return true;
				}
				if ( is_array( $child ) && $walk( $child, $depth + 1 ) ) {
					return true;
				}
			}
			return false;
		};
		return $walk( $payload );
	}

	/** @param array<string,mixed> $payload */
	private function payload_contains_sensitive_shape( array $payload ): bool {
		$walk = static function ( mixed $value, string $key = '', int $depth = 0 ) use ( &$walk ): bool {
			if ( $depth > 8 ) {
				return true;
			}
			if ( '' !== $key && 1 === preg_match( '/(?:patient|consent|clinical|guardian|cnic|passport|phone|email|address|identity|credential|date_of_birth|dob|medical_record|mrn)/i', $key ) ) {
				return true;
			}
			if ( is_string( $value ) ) {
				$sample = substr( $value, 0, 131072 );
				return 1 === preg_match( '/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i', $sample )
					|| 1 === preg_match( '/(?:\+?\d[\d\s().-]{8,}\d)/', $sample )
					|| 1 === preg_match( '/\b\d{5}-?\d{7}-?\d\b/', $sample )
					|| 1 === preg_match( '/\b(?:passport|cnic|national\s+id|medical\s+record|mrn|patient\s+id|registration\s+number)\s*[:#-]?\s*[A-Z0-9-]{3,}\b/i', $sample )
					|| 1 === preg_match( '/\b(?:DOB|date of birth|تاریخ پیدائش)\s*[:\-]?\s*\d{1,2}[\/\-.]\d{1,2}[\/\-.]\d{2,4}\b/i', $sample )
					|| 1 === preg_match( '/\b-?\d{1,2}\.\d{4,}\s*,\s*-?\d{1,3}\.\d{4,}\b/', $sample )
					|| 1 === preg_match( '/\b(?:address|street|گھر\s*کا\s*پتہ|پتہ)\s*[:\-]\s*[^\n]{8,}/i', $sample );
			}
			if ( is_array( $value ) ) {
				foreach ( $value as $child_key => $child ) {
					if ( $walk( $child, is_string( $child_key ) ? $child_key : '', $depth + 1 ) ) {
						return true;
					}
				}
			}
			return false;
		};
		return $walk( $payload );
	}

	private function within_context_rate_limit( int $user_id, string $adapter_key, string $capability ): bool {
		if (
			! function_exists( 'add_option' ) ||
			! function_exists( 'get_option' ) ||
			! function_exists( 'delete_option' ) ||
			! function_exists( 'get_transient' ) ||
			! function_exists( 'set_transient' ) ||
			! function_exists( 'wp_salt' )
		) {
			return false;
		}

		$raw_remote = isset( $_SERVER['REMOTE_ADDR'] ) && is_scalar( $_SERVER['REMOTE_ADDR'] ) ? wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) : '';
		$remote     = sanitize_text_field( $raw_remote );
		if ( '' === $remote || false === filter_var( $remote, FILTER_VALIDATE_IP ) ) {
			$remote = 'unknown';
		}
		$bucket  = (int) floor( time() / self::RATE_WINDOW );
		$ip_hash = hash_hmac( 'sha256', $remote, wp_salt( 'auth' ) );
		$scope   = hash( 'sha256', $user_id . '|' . $ip_hash . '|' . $adapter_key . '|' . $capability . '|' . $bucket );
		$counter = 'supc_future_third_rate_' . $scope;
		$lock    = 'supc_future_third_lock_' . $scope;
		$token   = $this->lock_token( $scope );
		$value   = array( 'token' => $token, 'expires_at' => time() + 5 );

		$acquired = add_option( $lock, $value, '', false );
		if ( ! $acquired ) {
			$existing = get_option( $lock, null );
			if ( is_array( $existing ) && isset( $existing['expires_at'] ) && (int) $existing['expires_at'] < time() ) {
				delete_option( $lock );
				$acquired = add_option( $lock, $value, '', false );
			}
		}
		if ( ! $acquired ) {
			return false;
		}

		try {
			$current = get_transient( $counter );
			$current = false === $current ? 0 : (int) $current;
			if ( $current < 0 || $current >= self::RATE_LIMIT ) {
				return false;
			}
			return true === set_transient( $counter, $current + 1, self::RATE_WINDOW + 10 );
		} finally {
			$existing = get_option( $lock, null );
			if ( is_array( $existing ) && isset( $existing['token'] ) && is_string( $existing['token'] ) && hash_equals( $token, $existing['token'] ) ) {
				delete_option( $lock );
			}
		}
	}

	private function lock_token( string $scope ): string {
		try {
			return bin2hex( random_bytes( 16 ) );
		} catch ( \Throwable $error ) {
			unset( $error );
			return substr( hash( 'sha256', $scope . '|' . microtime( true ) . '|' . wp_salt( 'nonce' ) ), 0, 32 );
		}
	}

	private function error( string $code, int $status ): WP_Error {
		return new WP_Error(
			'supc_' . sanitize_key( $code ),
			__( 'The Future Composer Intelligence request was blocked before provider execution. Your draft remains protected.', 'sabri-universal-post-composer' ),
			array(
				'status'          => $status,
				'draft_protected' => true,
			)
		);
	}
}
