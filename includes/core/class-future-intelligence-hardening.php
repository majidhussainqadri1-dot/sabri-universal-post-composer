<?php
/**
 * Cross-provider privacy and action hardening for Future Composer Intelligence.
 *
 * The browser's `sensitive` hint is never treated as an authority decision.
 * This guard independently inspects the registered native adapter/schema and
 * the bounded request payload before any filter-backed or adapter-backed
 * provider can receive the request.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

namespace Sabri\UniversalComposer\Core;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Future_Intelligence_Hardening {
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

	private const ACTIONS = array(
		'ai_copilot'              => array( '', 'applyable' ),
		'medical_terminology'     => array( '' ),
		'collaboration'           => array( 'join', 'presence', 'pull' ),
		'review_annotations'      => array( 'list', 'resolve' ),
		'semantic_diff'           => array( 'compare_current' ),
		'conflict_merge'          => array( 'inspect', 'resolve' ),
		'template_library'        => array( 'list', 'recommended' ),
		'media_workbench'         => array( 'capabilities' ),
		'cross_format_derivative' => array( '', 'applyable' ),
		'publication_impact'      => array(),
	);

	private bool $registered = false;

	public function register(): void {
		if ( $this->registered || ! function_exists( 'add_filter' ) ) {
			return;
		}
		$this->registered = true;
		add_filter( 'supc_future_capabilities', array( $this, 'filter_capabilities' ), -1000, 3 );
		add_filter( 'supc_future_capability_result', array( $this, 'guard_request' ), -1000, 5 );
	}

	public function filter_capabilities( mixed $capabilities, int $user_id, string $adapter_key ): mixed {
		if ( ! is_array( $capabilities ) || ! $this->adapter_is_sensitive( $user_id, $adapter_key ) ) {
			return $capabilities;
		}
		return array_values(
			array_filter(
				$capabilities,
				static function ( mixed $capability ) use ( $user_id, $adapter_key ): bool {
					if ( ! is_string( $capability ) || ! in_array( sanitize_key( $capability ), self::EXTERNAL_ADVISORY, true ) ) {
						return true;
					}
					return (bool) apply_filters( 'supc_future_sensitive_capability_allowed', false, sanitize_key( $capability ), $user_id, $adapter_key );
				}
			)
		);
	}

	/** @param array<string,mixed> $payload */
	public function guard_request( mixed $current, string $capability, int $user_id, string $adapter_key, array $payload ): mixed {
		if ( null !== $current ) {
			return $current;
		}
		$capability = sanitize_key( $capability );
		if ( ! isset( self::ACTIONS[ $capability ] ) || ! $this->action_is_allowed( $capability, $payload, $user_id, $adapter_key ) ) {
			return $this->error( 'future_capability_action_invalid', 400 );
		}
		if ( in_array( $capability, self::EXTERNAL_ADVISORY, true ) && $this->request_is_sensitive( $user_id, $adapter_key, $payload ) ) {
			$allowed = (bool) apply_filters( 'supc_future_sensitive_capability_allowed', false, $capability, $user_id, $adapter_key );
			if ( ! $allowed ) {
				return $this->error( 'future_sensitive_external_advisory_blocked', 403 );
			}
		}
		return null;
	}

	/** @param array<string,mixed> $payload */
	private function action_is_allowed( string $capability, array $payload, int $user_id, string $adapter_key ): bool {
		$action = isset( $payload['action'] ) && is_string( $payload['action'] ) ? sanitize_key( $payload['action'] ) : '';
		if ( 'publication_impact' === $capability ) {
			return $this->publication_action_is_allowed( $user_id, $adapter_key, $action );
		}
		if ( ! in_array( $action, self::ACTIONS[ $capability ], true ) ) {
			return false;
		}
		if ( 'ai_copilot' === $capability && '' === $action ) {
			$task = isset( $payload['task'] ) && is_string( $payload['task'] ) ? sanitize_key( $payload['task'] ) : '';
			return in_array( $task, array( 'outline', 'rewrite', 'summary', 'title', 'citations' ), true );
		}
		if ( 'cross_format_derivative' === $capability && '' === $action ) {
			$target = isset( $payload['target'] ) && is_string( $payload['target'] ) ? sanitize_key( $payload['target'] ) : '';
			return in_array( $target, array( 'summary', 'reel_script', 'video_outline', 'lesson_abstract', 'social_excerpt' ), true );
		}
		if ( 'conflict_merge' === $capability && 'resolve' === $action ) {
			$resolution = isset( $payload['resolution'] ) && is_string( $payload['resolution'] ) ? sanitize_key( $payload['resolution'] ) : '';
			$token      = isset( $payload['conflict_token'] ) && is_string( $payload['conflict_token'] ) ? trim( $payload['conflict_token'] ) : '';
			return in_array( $resolution, array( 'keep_current', 'accept_native', 'manual' ), true )
				&& '' !== $token
				&& strlen( $token ) <= 512
				&& 1 === preg_match( '/^[A-Za-z0-9._~:+\/-]+$/D', $token );
		}
		if ( 'review_annotations' === $capability && 'resolve' === $action ) {
			$id = isset( $payload['annotation_id'] ) && is_scalar( $payload['annotation_id'] ) ? trim( (string) $payload['annotation_id'] ) : '';
			return '' !== $id && strlen( $id ) <= 128 && 1 === preg_match( '/^[A-Za-z0-9._:-]+$/D', $id );
		}
		return true;
	}

	private function publication_action_is_allowed( int $user_id, string $adapter_key, string $action ): bool {
		if ( '' === $action || strlen( $action ) > 64 ) {
			return false;
		}
		try {
			$schema = Plugin::instance()->workflow_coordinator()->schema_read_only( $user_id, $adapter_key );
		} catch ( \Throwable $error ) {
			unset( $error );
			return false;
		}
		if ( $schema instanceof WP_Error || ! isset( $schema['fields']['publication_action'] ) || ! is_array( $schema['fields']['publication_action'] ) ) {
			return false;
		}
		$definition = $schema['fields']['publication_action'];
		$choices    = isset( $definition['choices'] ) && is_array( $definition['choices'] ) ? $definition['choices'] : array();
		foreach ( array_keys( $choices ) as $candidate ) {
			if ( is_scalar( $candidate ) && hash_equals( $action, sanitize_key( (string) $candidate ) ) ) {
				return true;
			}
		}
		return false;
	}

	/** @param array<string,mixed> $payload */
	private function request_is_sensitive( int $user_id, string $adapter_key, array $payload ): bool {
		if ( $this->adapter_is_sensitive( $user_id, $adapter_key ) || ! empty( $payload['sensitive'] ) ) {
			return true;
		}
		return $this->payload_contains_sensitive_shape( $payload );
	}

	private function adapter_is_sensitive( int $user_id, string $adapter_key ): bool {
		try {
			$registry = Plugin::instance()->registry();
			$adapter  = $registry->get( $adapter_key );
		} catch ( \Throwable $error ) {
			unset( $error );
			return true;
		}
		if ( ! is_object( $adapter ) || ! is_callable( array( $adapter, 'privacy_classification' ) ) ) {
			return true;
		}
		try {
			$classification = strtolower( trim( (string) $adapter->privacy_classification() ) );
		} catch ( \Throwable $error ) {
			unset( $error );
			return true;
		}
		if ( ! in_array( $classification, array( 'public', 'private', 'sensitive' ), true ) ) {
			return true;
		}
		if ( 'sensitive' === $classification ) {
			return true;
		}
		try {
			$schema = Plugin::instance()->workflow_coordinator()->schema_read_only( $user_id, $adapter_key );
		} catch ( \Throwable $error ) {
			unset( $error );
			return true;
		}
		if ( $schema instanceof WP_Error || ! isset( $schema['fields'] ) || ! is_array( $schema['fields'] ) ) {
			return true;
		}
		foreach ( $schema['fields'] as $key => $definition ) {
			$name    = is_string( $key ) ? strtolower( $key ) : '';
			$privacy = is_array( $definition ) && isset( $definition['privacy_class'] ) && is_string( $definition['privacy_class'] ) ? strtolower( $definition['privacy_class'] ) : '';
			if ( in_array( $privacy, array( 'sensitive', 'restricted', 'private_sensitive', 'medical_sensitive', 'clinical_sensitive' ), true ) ) {
				return true;
			}
			if ( '' !== $name && 1 === preg_match( '/(?:patient|consent|clinical_case|successful_case|guardian|credential|identity_evidence|anonym)/', $name ) ) {
				return true;
			}
		}
		return false;
	}

	/** @param array<string,mixed> $payload */
	private function payload_contains_sensitive_shape( array $payload ): bool {
		$walk = static function ( mixed $value, string $key = '', int $depth = 0 ) use ( &$walk ): bool {
			if ( $depth > 8 ) {
				return true;
			}
			if ( '' !== $key && 1 === preg_match( '/(?:patient|consent|clinical|guardian|cnic|passport|phone|email|address|identity|credential|date_of_birth|dob|medical_record)/i', $key ) ) {
				return true;
			}
			if ( is_string( $value ) ) {
				$sample = substr( $value, 0, 131072 );
				return 1 === preg_match( '/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i', $sample )
					|| 1 === preg_match( '/\b\d{5}-?\d{7}-?\d\b/', $sample )
					|| 1 === preg_match( '/(?:\+?\d[\d\s().-]{8,}\d)/', $sample )
					|| 1 === preg_match( '/\b(?:\d{1,3}\.){3}\d{1,3}\b/', $sample )
					|| 1 === preg_match( '/\b(?:DOB|date of birth|تاریخ پیدائش)\s*[:\-]?\s*\d{1,2}[\/\-.]\d{1,2}[\/\-.]\d{2,4}\b/i', $sample );
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

	private function error( string $code, int $status ): WP_Error {
		return new WP_Error(
			'supc_' . sanitize_key( $code ),
			__( 'The Future Composer Intelligence request was blocked by the server-side privacy or action policy.', 'sabri-universal-post-composer' ),
			array( 'status' => $status )
		);
	}
}
