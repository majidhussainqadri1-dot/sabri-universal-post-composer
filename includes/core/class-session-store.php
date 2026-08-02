<?php
/**
 * File 22 orchestration-session store.
 *
 * The store deliberately persists only workflow identity, opaque native
 * references, concurrency state, and idempotency metadata. Draft bodies,
 * patient consent, identity evidence, and media bytes remain with native
 * owners.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

namespace Sabri\UniversalComposer\Core;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Session_Store {
	private const TABLE_SUFFIX         = 'supc_sessions';
	private const SCHEMA_OPTION        = 'supc_session_schema_version';
	private const SCHEMA_VERSION       = '1.0.0';
	private const ACTIVE_TTL           = 2592000; // 30 days.
	private const COMPLETED_TTL        = 604800;  // 7 days.
	private const MAX_ADAPTER_BYTES    = 64;
	private const MAX_VERSION_BYTES    = 32;
	private const MAX_REFERENCE_BYTES  = 255;
	private const SESSION_PATTERN      = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D';
	private const STATES               = array( 'new', 'draft', 'valid', 'submitted', 'scheduled', 'published', 'rejected', 'failed' );

	public static function install(): bool {
		global $wpdb;
		if ( ! is_object( $wpdb ) || ! isset( $wpdb->prefix ) ) {
			return false;
		}
		$table   = self::table_name();
		$charset = method_exists( $wpdb, 'get_charset_collate' ) ? (string) $wpdb->get_charset_collate() : '';
		$sql     = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			session_uuid char(36) NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			adapter_key varchar(64) NOT NULL,
			adapter_version varchar(32) NOT NULL,
			native_reference varchar(255) NULL,
			state varchar(32) NOT NULL DEFAULT 'new',
			lock_version bigint(20) unsigned NOT NULL DEFAULT 1,
			idempotency_key varchar(80) NULL,
			last_error varchar(64) NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			expires_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY session_uuid (session_uuid),
			KEY user_adapter (user_id,adapter_key),
			KEY expires_at (expires_at)
		) {$charset};";
		if ( defined( 'ABSPATH' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}
		if ( ! function_exists( 'dbDelta' ) ) {
			return false;
		}
		dbDelta( $sql );
		if ( ! self::table_exists() ) {
			return false;
		}
		if ( function_exists( 'update_option' ) ) {
			update_option( self::SCHEMA_OPTION, self::SCHEMA_VERSION, false );
		}
		return true;
	}

	public static function maybe_install(): bool {
		$installed = function_exists( 'get_option' ) ? (string) get_option( self::SCHEMA_OPTION, '' ) : '';
		return self::SCHEMA_VERSION === $installed && self::table_exists() ? true : self::install();
	}

	public static function table_exists(): bool {
		global $wpdb;
		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_var' ) || ! method_exists( $wpdb, 'prepare' ) ) {
			return false;
		}
		$table = self::table_name();
		return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	}

	/** @return array<string,mixed>|WP_Error */
	public function create( int $user_id, string $adapter_key, string $adapter_version ): array|WP_Error {
		if (
			$user_id <= 0 ||
			! Contract_Boundary::adapter_key( $adapter_key ) ||
			! Contract_Boundary::bounded_text( $adapter_key, 1, self::MAX_ADAPTER_BYTES ) ||
			! Contract_Boundary::version( $adapter_version ) ||
			! Contract_Boundary::bounded_text( $adapter_version, 1, self::MAX_VERSION_BYTES ) ||
			! function_exists( 'wp_generate_uuid4' )
		) {
			return $this->error( 'invalid_session_request' );
		}

		global $wpdb;
		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'insert' ) ) {
			return $this->error( 'session_store_unavailable' );
		}
		$uuid = strtolower( wp_generate_uuid4() );
		if ( 1 !== preg_match( self::SESSION_PATTERN, $uuid ) ) {
			return $this->error( 'session_identifier_failed' );
		}
		$now     = time();
		$created = $wpdb->insert(
			self::table_name(),
			array(
				'session_uuid'   => $uuid,
				'user_id'        => $user_id,
				'adapter_key'     => $adapter_key,
				'adapter_version' => $adapter_version,
				'state'           => 'new',
				'lock_version'    => 1,
				'created_at'      => gmdate( 'Y-m-d H:i:s', $now ),
				'updated_at'      => gmdate( 'Y-m-d H:i:s', $now ),
				'expires_at'      => gmdate( 'Y-m-d H:i:s', $now + self::ACTIVE_TTL ),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);
		if ( 1 !== $created ) {
			return $this->error( 'session_create_failed' );
		}
		return $this->get_owned( $uuid, $user_id );
	}

	/** @return array<string,mixed>|WP_Error */
	public function get_owned( string $uuid, int $user_id ): array|WP_Error {
		if ( $user_id <= 0 || ! $this->valid_uuid( $uuid ) ) {
			return $this->error( 'invalid_session_reference' );
		}
		global $wpdb;
		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_row' ) || ! method_exists( $wpdb, 'prepare' ) ) {
			return $this->error( 'session_store_unavailable' );
		}
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT session_uuid,user_id,adapter_key,adapter_version,native_reference,state,lock_version,idempotency_key,last_error,created_at,updated_at,expires_at FROM %i WHERE session_uuid = %s AND user_id = %d LIMIT 1',
				self::table_name(),
				$uuid,
				$user_id
			),
			ARRAY_A
		);
		if ( ! is_array( $row ) ) {
			return $this->error( 'session_not_found' );
		}
		if ( strtotime( (string) $row['expires_at'] . ' UTC' ) < time() ) {
			return $this->error( 'session_expired' );
		}
		return $this->normalize_row( $row );
	}

	/**
	 * Compare-and-swap update prevents stale tabs from silently overwriting a
	 * newer session state.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public function update(
		string $uuid,
		int $user_id,
		int $expected_lock_version,
		string $state,
		?string $native_reference = null,
		?string $idempotency_key = null,
		?string $last_error = null
	): array|WP_Error {
		if (
			$user_id <= 0 ||
			$expected_lock_version <= 0 ||
			! $this->valid_uuid( $uuid ) ||
			! in_array( $state, self::STATES, true ) ||
			( null !== $native_reference && ( ! Contract_Boundary::bounded_text( $native_reference, 1, self::MAX_REFERENCE_BYTES ) || 1 !== preg_match( '/^[A-Za-z0-9][A-Za-z0-9._:-]{0,254}$/D', $native_reference ) ) ) ||
			( null !== $idempotency_key && ! Contract_Boundary::bounded_text( $idempotency_key, 1, 80 ) ) ||
			( null !== $last_error && ! Contract_Boundary::code( $last_error ) )
		) {
			return $this->error( 'invalid_session_update' );
		}
		global $wpdb;
		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'query' ) || ! method_exists( $wpdb, 'prepare' ) ) {
			return $this->error( 'session_store_unavailable' );
		}
		$now     = time();
		$ttl     = in_array( $state, array( 'submitted', 'scheduled', 'published', 'rejected', 'failed' ), true ) ? self::COMPLETED_TTL : self::ACTIVE_TTL;
		$updated = $wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET state = %s, native_reference = %s, idempotency_key = %s, last_error = %s, lock_version = lock_version + 1, updated_at = %s, expires_at = %s WHERE session_uuid = %s AND user_id = %d AND lock_version = %d',
				self::table_name(),
				$state,
				$native_reference,
				$idempotency_key,
				$last_error,
				gmdate( 'Y-m-d H:i:s', $now ),
				gmdate( 'Y-m-d H:i:s', $now + $ttl ),
				$uuid,
				$user_id,
				$expected_lock_version
			)
		);
		if ( 1 !== $updated ) {
			$current = $this->get_owned( $uuid, $user_id );
			return $current instanceof WP_Error ? $current : $this->error( 'session_conflict', array( 'current' => $current ) );
		}
		return $this->get_owned( $uuid, $user_id );
	}

	/** @return array<string,mixed>|WP_Error */
	public function ensure_idempotency_key( string $uuid, int $user_id, string $key ): array|WP_Error {
		if ( $user_id <= 0 || ! $this->valid_uuid( $uuid ) || ! Contract_Boundary::bounded_text( $key, 73, 80 ) ) {
			return $this->error( 'invalid_idempotency_key' );
		}
		global $wpdb;
		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'query' ) || ! method_exists( $wpdb, 'prepare' ) ) {
			return $this->error( 'session_store_unavailable' );
		}
		$current = $this->get_owned( $uuid, $user_id );
		if ( $current instanceof WP_Error ) {
			return $current;
		}
		if ( is_string( $current['idempotency_key'] ) && '' !== $current['idempotency_key'] ) {
			return hash_equals( $current['idempotency_key'], $key ) ? $current : $this->error( 'idempotency_key_conflict' );
		}
		$updated = $wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET idempotency_key = %s, updated_at = %s WHERE session_uuid = %s AND user_id = %d AND (idempotency_key IS NULL OR idempotency_key = \'\')',
				self::table_name(),
				$key,
				gmdate( 'Y-m-d H:i:s' ),
				$uuid,
				$user_id
			)
		);
		if ( 1 !== $updated ) {
			$current = $this->get_owned( $uuid, $user_id );
			return $current instanceof WP_Error || ! is_string( $current['idempotency_key'] ) || ! hash_equals( $current['idempotency_key'], $key )
				? $this->error( 'idempotency_key_conflict' )
				: $current;
		}
		return $this->get_owned( $uuid, $user_id );
	}

	public function delete_owned( string $uuid, int $user_id ): bool {
		if ( $user_id <= 0 || ! $this->valid_uuid( $uuid ) ) {
			return false;
		}
		global $wpdb;
		return is_object( $wpdb ) && method_exists( $wpdb, 'delete' ) && 1 === $wpdb->delete( self::table_name(), array( 'session_uuid' => $uuid, 'user_id' => $user_id ), array( '%s', '%d' ) );
	}

	public static function cleanup_expired(): int {
		global $wpdb;
		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'query' ) || ! method_exists( $wpdb, 'prepare' ) ) {
			return 0;
		}
		$deleted = $wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE expires_at < %s LIMIT 500', self::table_name(), gmdate( 'Y-m-d H:i:s' ) ) );
		return is_int( $deleted ) && $deleted > 0 ? $deleted : 0;
	}

	public static function table_name(): string {
		global $wpdb;
		$prefix = is_object( $wpdb ) && isset( $wpdb->prefix ) ? (string) $wpdb->prefix : 'wp_';
		return $prefix . self::TABLE_SUFFIX;
	}

	private function valid_uuid( string $uuid ): bool {
		return 1 === preg_match( self::SESSION_PATTERN, strtolower( $uuid ) );
	}

	/** @param array<string,mixed> $row @return array<string,mixed> */
	private function normalize_row( array $row ): array {
		return array(
			'session_uuid'    => strtolower( (string) $row['session_uuid'] ),
			'user_id'         => (int) $row['user_id'],
			'adapter_key'      => sanitize_key( (string) $row['adapter_key'] ),
			'adapter_version'  => (string) $row['adapter_version'],
			'native_reference' => null === $row['native_reference'] ? null : (string) $row['native_reference'],
			'state'            => sanitize_key( (string) $row['state'] ),
			'lock_version'     => (int) $row['lock_version'],
			'idempotency_key'  => null === $row['idempotency_key'] ? null : (string) $row['idempotency_key'],
			'last_error'       => null === $row['last_error'] ? null : sanitize_key( (string) $row['last_error'] ),
			'created_at'       => (string) $row['created_at'],
			'updated_at'       => (string) $row['updated_at'],
			'expires_at'       => (string) $row['expires_at'],
		);
	}

	private function error( string $code, mixed $data = null ): WP_Error {
		return new WP_Error( 'supc_' . $code, __( 'The Composer session request could not be completed.', 'sabri-universal-post-composer' ), $data );
	}
}
