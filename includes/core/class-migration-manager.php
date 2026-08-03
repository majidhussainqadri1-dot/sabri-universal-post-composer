<?php
/**
 * Non-destructive File 22 migration, feature flag and settings snapshot law.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

namespace Sabri\UniversalComposer\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Migration_Manager {
	private const SNAPSHOT_OPTION = 'supc_activation_snapshot_v1';
	private const FEATURE_OPTION  = 'supc_feature_enabled';
	private const LOCK_OPTION     = 'supc_migration_lock';
	private const LOCK_TTL        = 300;

	public static function writes_enabled(): bool {
		$value = get_option( self::FEATURE_OPTION, null );
		return null === $value || false === $value || '0' !== (string) $value;
	}

	public static function set_writes_enabled( bool $enabled ): bool {
		$value = $enabled ? '1' : '0';
		return update_option( self::FEATURE_OPTION, $value, false ) || (string) get_option( self::FEATURE_OPTION, '' ) === $value;
	}

	public static function capture_snapshot(): bool {
		if ( false !== get_option( self::SNAPSHOT_OPTION, false ) ) {
			return true;
		}
		$snapshot = array(
			'captured_at'             => time(),
			'supc_create_page_id'     => absint( get_option( 'supc_create_page_id', 0 ) ),
			'supc_my_content_page_id' => absint( get_option( 'supc_my_content_page_id', 0 ) ),
			'supc_emergency_disabled' => (bool) get_option( 'supc_emergency_disabled', false ),
			'supc_feature_enabled'    => self::writes_enabled(),
			'supc_version'            => (string) get_option( 'supc_version', '' ),
			'supc_schema_version'     => (string) get_option( 'supc_schema_version', '' ),
		);
		return add_option( self::SNAPSHOT_OPTION, $snapshot, '', false );
	}

	/** @return array{success:bool,codes:array<int,string>} */
	public static function repair_owned_schema(): array {
		$token = self::acquire_lock();
		if ( '' === $token ) {
			return array( 'success' => false, 'codes' => array( 'migration_locked' ) );
		}
		try {
			$checks = array(
				'session_store'          => Session_Store::maybe_install(),
				'submission_outbox'      => Submission_Store::maybe_install(),
				'upload_token_store'      => Upload_Token_Store::maybe_install(),
				'audit_store'             => Audit_Store::maybe_install(),
			);
			$codes = array();
			foreach ( $checks as $key => $passed ) {
				if ( ! $passed ) {
					$codes[] = $key . '_repair_failed';
				}
			}
			if ( array() === $codes ) {
				update_option( 'supc_version', SUPC_VERSION, false );
				update_option( 'supc_schema_version', SUPC_SCHEMA_VERSION, false );
			}
			return array( 'success' => array() === $codes, 'codes' => $codes );
		} finally {
			self::release_lock( $token );
		}
	}

	/** @return array{success:bool,codes:array<int,string>} */
	public static function schedule_jobs(): array {
		$codes = array();
		if ( ! wp_next_scheduled( 'supc_cleanup_expired_sessions' ) && ! wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'supc_cleanup_expired_sessions' ) ) {
			$codes[] = 'session_cleanup_schedule_failed';
		}
		if ( ! wp_next_scheduled( 'supc_cleanup_plan_metadata' ) && ! wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'supc_cleanup_plan_metadata' ) ) {
			$codes[] = 'metadata_cleanup_schedule_failed';
		}
		if ( ! wp_next_scheduled( 'supc_process_reconciliation_queue' ) && ! wp_schedule_event( time() + 300, 'supc_five_minutes', 'supc_process_reconciliation_queue' ) ) {
			$codes[] = 'reconciliation_schedule_failed';
		}
		return array( 'success' => array() === $codes, 'codes' => $codes );
	}

	/** @return array{success:bool,codes:array<int,string>} */
	public static function rollback_settings(): array {
		$snapshot = get_option( self::SNAPSHOT_OPTION, null );
		if ( ! is_array( $snapshot ) ) {
			return array( 'success' => false, 'codes' => array( 'activation_snapshot_missing' ) );
		}
		$allowed = array(
			'supc_create_page_id'     => absint( $snapshot['supc_create_page_id'] ?? 0 ),
			'supc_my_content_page_id' => absint( $snapshot['supc_my_content_page_id'] ?? 0 ),
			'supc_emergency_disabled' => ! empty( $snapshot['supc_emergency_disabled'] ),
			self::FEATURE_OPTION       => ! empty( $snapshot['supc_feature_enabled'] ) ? '1' : '0',
		);
		$codes = array();
		foreach ( $allowed as $option => $value ) {
			if ( ! update_option( $option, $value, false ) && get_option( $option, null ) !== $value ) {
				$codes[] = sanitize_key( $option ) . '_restore_failed';
			}
		}
		Page_Resolver::reset_cache();
		return array( 'success' => array() === $codes, 'codes' => $codes );
	}

	private static function acquire_lock(): string {
		if ( ! function_exists( 'wp_generate_uuid4' ) ) {
			return '';
		}
		$token = wp_generate_uuid4();
		$value = array( 'token' => $token, 'expires_at' => time() + self::LOCK_TTL );
		if ( add_option( self::LOCK_OPTION, $value, '', false ) ) {
			return $token;
		}
		$current = get_option( self::LOCK_OPTION, null );
		if ( is_array( $current ) && (int) ( $current['expires_at'] ?? 0 ) > time() ) {
			return '';
		}
		delete_option( self::LOCK_OPTION );
		return add_option( self::LOCK_OPTION, $value, '', false ) ? $token : '';
	}

	private static function release_lock( string $token ): void {
		$current = get_option( self::LOCK_OPTION, null );
		if ( is_array( $current ) && is_string( $current['token'] ?? null ) && hash_equals( $current['token'], $token ) ) {
			delete_option( self::LOCK_OPTION );
		}
	}
}
