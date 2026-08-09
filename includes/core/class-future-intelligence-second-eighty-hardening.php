<?php
/**
 * Second independent eighty-round hardening for Future Composer Intelligence.
 *
 * This layer is deliberately narrow: it adds a final server-side provider
 * preflight without taking ownership from native modules. It blocks client
 * authority-field spoofing, requires explicit human intent before a provider
 * collaboration join, revalidates server-owned session context immediately
 * before provider dispatch, and adds a privacy-minimized user/IP/adapter/
 * capability rate budget with a stale-lock-safe mutex.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

namespace Sabri\UniversalComposer\Core;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Future_Intelligence_Second_Eighty_Hardening {
	private const SESSION_BOUND_CAPABILITIES = array(
		'collaboration',
		'review_annotations',
		'semantic_diff',
		'conflict_merge',
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

	private bool $registered = false;

	public function register(): void {
		if ( $this->registered || ! function_exists( 'add_filter' ) ) {
			return;
		}
		$this->registered = true;
		add_filter( 'supc_future_capability_result', array( $this, 'guard_provider_dispatch' ), -2000, 5 );
	}

	/**
	 * Final File-22-side provider preflight.
	 *
	 * @param mixed               $current     Existing provider/filter result.
	 * @param string              $capability  Future capability code.
	 * @param int                 $user_id     Current user.
	 * @param string              $adapter_key Current native adapter.
	 * @param array<string,mixed> $payload     Bounded provider payload.
	 * @return mixed
	 */
	public function guard_provider_dispatch( mixed $current, string $capability, int $user_id, string $adapter_key, array $payload ): mixed {
		if ( null !== $current ) {
			return $current;
		}
		$capability  = sanitize_key( $capability );
		$adapter_key = sanitize_key( $adapter_key );
		if ( $user_id <= 0 || ! Contract_Boundary::adapter_key( $adapter_key ) ) {
			return $this->error( 'future_second_preflight_invalid_subject', 403 );
		}

		if ( $this->payload_has_client_authority_key( $payload ) ) {
			return $this->error( 'future_client_authority_field_prohibited', 400 );
		}

		if ( in_array( $capability, self::SESSION_BOUND_CAPABILITIES, true ) ) {
			$context_error = $this->validate_server_context( $user_id, $adapter_key, $payload );
			if ( $context_error instanceof WP_Error ) {
				return $context_error;
			}
		}

		$action = isset( $payload['action'] ) && is_string( $payload['action'] ) ? sanitize_key( $payload['action'] ) : '';
		if ( 'collaboration' === $capability && 'join' === $action && true !== ( $payload['user_initiated'] ?? false ) ) {
			return $this->error( 'future_collaboration_join_requires_user_intent', 409 );
		}

		if ( ! $this->within_context_rate_limit( $user_id, $adapter_key, $capability ) ) {
			return $this->error( 'future_context_rate_limited', 429 );
		}
		return null;
	}

	/** @param array<string,mixed> $payload */
	private function payload_has_client_authority_key( array $payload ): bool {
		$walk = static function ( mixed $value, int $depth = 0 ) use ( &$walk ): bool {
			if ( $depth > 8 ) {
				return true;
			}
			if ( ! is_array( $value ) ) {
				return false;
			}
			foreach ( $value as $key => $child ) {
				$key = is_string( $key ) ? sanitize_key( $key ) : '';
				if ( 0 === $depth && '_supc_context' === $key ) {
					continue;
				}
				if ( '' !== $key && in_array( $key, self::RESERVED_AUTHORITY_KEYS, true ) ) {
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

	/**
	 * @param array<string,mixed> $payload Provider payload.
	 * @return bool|WP_Error
	 */
	private function validate_server_context( int $user_id, string $adapter_key, array $payload ): bool|WP_Error {
		$context = $payload['_supc_context'] ?? null;
		if ( ! is_array( $context ) ) {
			return $this->error( 'future_server_context_required', 409 );
		}
		$allowed = array(
			'session_uuid',
			'adapter_key',
			'native_reference',
			'sensitivity_class',
			'lock_version',
			'correlation_id',
		);
		foreach ( array_keys( $context ) as $key ) {
			if ( ! is_string( $key ) || ! in_array( $key, $allowed, true ) ) {
				return $this->error( 'future_server_context_invalid', 409 );
			}
		}

		$session_uuid     = isset( $context['session_uuid'] ) && is_string( $context['session_uuid'] ) ? strtolower( trim( $context['session_uuid'] ) ) : '';
		$context_adapter  = isset( $context['adapter_key'] ) && is_string( $context['adapter_key'] ) ? sanitize_key( $context['adapter_key'] ) : '';
		$lock_version     = isset( $context['lock_version'] ) && is_numeric( $context['lock_version'] ) ? (int) $context['lock_version'] : 0;
		$sensitivity      = isset( $context['sensitivity_class'] ) && is_string( $context['sensitivity_class'] ) ? strtolower( trim( $context['sensitivity_class'] ) ) : '';
		$native_reference = isset( $context['native_reference'] ) && is_string( $context['native_reference'] ) ? trim( $context['native_reference'] ) : '';
		$correlation      = isset( $context['correlation_id'] ) && is_string( $context['correlation_id'] ) ? trim( $context['correlation_id'] ) : '';

		if (
			1 !== preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $session_uuid ) ||
			! hash_equals( $adapter_key, $context_adapter ) ||
			$lock_version <= 0 ||
			! in_array( $sensitivity, array( 'public', 'private', 'sensitive' ), true ) ||
			! Contract_Boundary::bounded_text( $correlation, 1, 96 ) ||
			( '' !== $native_reference && 1 !== preg_match( '/^[A-Za-z0-9][A-Za-z0-9._:-]{0,254}$/D', $native_reference ) )
		) {
			return $this->error( 'future_server_context_invalid', 409 );
		}

		$owned = ( new Session_Store() )->get_owned( $session_uuid, $user_id );
		if ( $owned instanceof WP_Error ) {
			return $this->error( 'future_server_context_stale', 409 );
		}
		$owned_reference = is_string( $owned['native_reference'] ?? null ) ? (string) $owned['native_reference'] : '';
		if (
			! hash_equals( $adapter_key, (string) ( $owned['adapter_key'] ?? '' ) ) ||
			$lock_version !== (int) ( $owned['lock_version'] ?? 0 ) ||
			! hash_equals( $sensitivity, (string) ( $owned['sensitivity_class'] ?? '' ) ) ||
			! hash_equals( $native_reference, $owned_reference )
		) {
			return $this->error( 'future_server_context_stale', 409 );
		}
		return true;
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

		$window = (int) apply_filters( 'supc_future_context_rate_window', 60, $user_id, $adapter_key, $capability );
		$limit  = (int) apply_filters( 'supc_future_context_rate_limit', 60, $user_id, $adapter_key, $capability );
		$window = max( 10, min( 300, $window ) );
		$limit  = max( 1, min( 300, $limit ) );
		$bucket = (int) floor( time() / $window );

		$remote = isset( $_SERVER['REMOTE_ADDR'] ) && is_scalar( $_SERVER['REMOTE_ADDR'] ) ? trim( (string) $_SERVER['REMOTE_ADDR'] ) : '';
		if ( '' === $remote || false === filter_var( $remote, FILTER_VALIDATE_IP ) ) {
			$remote = 'unknown';
		}
		$ip_hash = hash_hmac( 'sha256', $remote, wp_salt( 'auth' ) );
		$scope   = hash( 'sha256', $user_id . '|' . $ip_hash . '|' . $adapter_key . '|' . $capability . '|' . $bucket );
		$counter = 'supc_future_ctx_rate_' . $scope;
		$lock    = 'supc_future_ctx_lock_' . $scope;
		$token   = $this->lock_token( $scope );
		$expires = time() + 5;
		$value   = array( 'token' => $token, 'expires_at' => $expires );

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
			if ( $current < 0 || $current >= $limit ) {
				return false;
			}
			return true === set_transient( $counter, $current + 1, $window + 10 );
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
			__( 'The Future Composer Intelligence request was blocked by a final File 22 authority, intent, or abuse-prevention preflight.', 'sabri-universal-post-composer' ),
			array( 'status' => $status )
		);
	}
}
