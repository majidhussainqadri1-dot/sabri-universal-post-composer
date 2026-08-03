<?php
/**
 * Metadata-only upload-token registry. File bytes always remain native-owned.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

namespace Sabri\UniversalComposer\Core;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Upload_Token_Store {
	private const TABLE_SUFFIX   = 'supc_upload_tokens';
	private const SCHEMA_OPTION  = 'supc_upload_schema_version';
	private const SCHEMA_VERSION = '1.1.0';
	private const ACTIVE_TTL     = 86400;
	private const TERMINAL_TTL   = 2592000;
	private const UUID_PATTERN   = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D';
	private const REF_PATTERN    = '/^[A-Za-z0-9][A-Za-z0-9._:-]{0,254}$/D';

	public static function install(): bool {
		global $wpdb;
		if ( ! is_object( $wpdb ) || ! isset( $wpdb->prefix ) ) {
			return false;
		}
		$table   = self::table_name();
		$charset = method_exists( $wpdb, 'get_charset_collate' ) ? (string) $wpdb->get_charset_collate() : '';
		$sql     = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			upload_uuid char(36) NOT NULL,
			session_uuid char(36) NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			adapter_key varchar(64) NOT NULL,
			native_upload_reference varchar(255) NOT NULL,
			purpose varchar(64) NOT NULL,
			mime_type varchar(127) NOT NULL,
			size_bytes bigint(20) unsigned NOT NULL,
			checksum_sha256 char(64) NULL,
			status varchar(16) NOT NULL DEFAULT 'issued',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			expires_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY upload_uuid (upload_uuid),
			UNIQUE KEY adapter_native_reference (adapter_key,native_upload_reference),
			KEY session_status (session_uuid,status),
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
		update_option( self::SCHEMA_OPTION, self::SCHEMA_VERSION, false );
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
		$like  = method_exists( $wpdb, 'esc_like' ) ? $wpdb->esc_like( $table ) : addcslashes( $table, '_%\\' );
		return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );
	}

	/** @param array<string,mixed> $metadata @return array<string,mixed>|WP_Error */
	public function register( string $session_uuid, int $user_id, string $adapter_key, string $native_reference, string $purpose, array $metadata ): array|WP_Error {
		$mime = isset( $metadata['mime_type'] ) && is_string( $metadata['mime_type'] ) ? strtolower( trim( $metadata['mime_type'] ) ) : '';
		$size = isset( $metadata['size_bytes'] ) && is_int( $metadata['size_bytes'] ) ? $metadata['size_bytes'] : 0;
		$hash = isset( $metadata['checksum_sha256'] ) && is_string( $metadata['checksum_sha256'] ) ? strtolower( $metadata['checksum_sha256'] ) : null;
		if (
			$user_id <= 0 ||
			1 !== preg_match( self::UUID_PATTERN, strtolower( $session_uuid ) ) ||
			! Contract_Boundary::adapter_key( $adapter_key ) ||
			1 !== preg_match( self::REF_PATTERN, $native_reference ) ||
			! Contract_Boundary::code( $purpose ) ||
			1 !== preg_match( '/^[a-z0-9][a-z0-9.+-]{0,63}\/[a-z0-9][a-z0-9.+-]{0,62}$/D', $mime ) ||
			$size <= 0 || $size > 536870912 ||
			( null !== $hash && 1 !== preg_match( '/^[0-9a-f]{64}$/D', $hash ) ) ||
			! function_exists( 'wp_generate_uuid4' )
		) {
			return $this->error( 'invalid_upload_metadata' );
		}
		$existing = $this->get_by_native_reference( $adapter_key, $native_reference );
		if ( ! $existing instanceof WP_Error ) {
			if (
				(int) $existing['user_id'] !== $user_id ||
				! hash_equals( (string) $existing['session_uuid'], strtolower( $session_uuid ) ) ||
				! hash_equals( (string) $existing['purpose'], $purpose ) ||
				! hash_equals( (string) $existing['mime_type'], $mime ) ||
				(int) $existing['size_bytes'] !== $size ||
				( null !== $hash && ( ! is_string( $existing['checksum_sha256'] ) || ! hash_equals( (string) $existing['checksum_sha256'], $hash ) ) )
			) {
				return $this->error( 'upload_identity_conflict' );
			}
			return $existing;
		}
		if ( 'supc_upload_not_found' !== $this->error_code( $existing ) ) {
			return $existing;
		}

		global $wpdb;
		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'insert' ) ) {
			return $this->error( 'upload_store_unavailable' );
		}
		$uuid = strtolower( wp_generate_uuid4() );
		$now  = time();
		$ok   = $wpdb->insert(
			self::table_name(),
			array(
				'upload_uuid'             => $uuid,
				'session_uuid'            => strtolower( $session_uuid ),
				'user_id'                 => $user_id,
				'adapter_key'              => $adapter_key,
				'native_upload_reference'  => $native_reference,
				'purpose'                  => $purpose,
				'mime_type'                => $mime,
				'size_bytes'               => $size,
				'checksum_sha256'          => $hash,
				'status'                   => 'issued',
				'created_at'               => gmdate( 'Y-m-d H:i:s', $now ),
				'updated_at'               => gmdate( 'Y-m-d H:i:s', $now ),
				'expires_at'               => gmdate( 'Y-m-d H:i:s', $now + self::ACTIVE_TTL ),
			),
			array( '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
		);
		if ( 1 === $ok ) {
			return $this->get_owned( $uuid, $user_id );
		}
		$existing = $this->get_by_native_reference( $adapter_key, $native_reference );
		if (
			! $existing instanceof WP_Error &&
			(int) $existing['user_id'] === $user_id &&
			hash_equals( (string) $existing['session_uuid'], strtolower( $session_uuid ) )
		) {
			return $existing;
		}
		return $this->error( 'upload_register_failed' );
	}

	/** @return array<string,mixed>|WP_Error */
	public function get_owned( string $upload_uuid, int $user_id ): array|WP_Error {
		if ( $user_id <= 0 || 1 !== preg_match( self::UUID_PATTERN, strtolower( $upload_uuid ) ) ) {
			return $this->error( 'invalid_upload_reference' );
		}
		global $wpdb;
		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_row' ) || ! method_exists( $wpdb, 'prepare' ) ) {
			return $this->error( 'upload_store_unavailable' );
		}
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT upload_uuid,session_uuid,user_id,adapter_key,native_upload_reference,purpose,mime_type,size_bytes,checksum_sha256,status,created_at,updated_at,expires_at FROM %i WHERE upload_uuid = %s AND user_id = %d LIMIT 1',
				self::table_name(),
				strtolower( $upload_uuid ),
				$user_id
			),
			ARRAY_A
		);
		return is_array( $row ) ? $this->normalize( $row ) : $this->error( 'upload_not_found' );
	}

	/** @return array<string,mixed>|WP_Error */
	public function transition( string $upload_uuid, int $user_id, string $expected_status, string $status ): array|WP_Error {
		if ( ! in_array( $expected_status, array( 'issued', 'uploading', 'completed' ), true ) || ! in_array( $status, array( 'uploading', 'completed', 'cancelled', 'failed' ), true ) ) {
			return $this->error( 'invalid_upload_transition' );
		}
		$current = $this->get_owned( $upload_uuid, $user_id );
		if ( $current instanceof WP_Error ) {
			return $current;
		}
		if ( hash_equals( (string) $current['status'], $status ) ) {
			return $current;
		}
		if ( ! hash_equals( (string) $current['status'], $expected_status ) ) {
			return $this->error( 'upload_state_conflict' );
		}
		global $wpdb;
		$now     = time();
		$updated = is_object( $wpdb ) && method_exists( $wpdb, 'query' ) && method_exists( $wpdb, 'prepare' )
			? $wpdb->query(
				$wpdb->prepare(
					'UPDATE %i SET status = %s, updated_at = %s, expires_at = %s WHERE upload_uuid = %s AND user_id = %d AND status = %s',
					self::table_name(),
					$status,
					gmdate( 'Y-m-d H:i:s', $now ),
					gmdate( 'Y-m-d H:i:s', $now + ( in_array( $status, array( 'completed', 'cancelled', 'failed' ), true ) ? self::TERMINAL_TTL : self::ACTIVE_TTL ) ),
					strtolower( $upload_uuid ),
					$user_id,
					$expected_status
				)
			)
			: false;
		return 1 === $updated ? $this->get_owned( $upload_uuid, $user_id ) : $this->error( 'upload_state_conflict' );
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


	/** @return array<string,mixed>|WP_Error */
	private function get_by_native_reference( string $adapter_key, string $native_reference ): array|WP_Error {
		global $wpdb;
		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_row' ) || ! method_exists( $wpdb, 'prepare' ) ) {
			return $this->error( 'upload_store_unavailable' );
		}
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT upload_uuid,session_uuid,user_id,adapter_key,native_upload_reference,purpose,mime_type,size_bytes,checksum_sha256,status,created_at,updated_at,expires_at FROM %i WHERE adapter_key = %s AND native_upload_reference = %s LIMIT 1',
				self::table_name(),
				$adapter_key,
				$native_reference
			),
			ARRAY_A
		);
		return is_array( $row ) ? $this->normalize( $row ) : $this->error( 'upload_not_found' );
	}

	private function error_code( WP_Error $error ): string {
		return is_callable( array( $error, 'get_error_code' ) ) ? (string) $error->get_error_code() : '';
	}

	/** @param array<string,mixed> $row @return array<string,mixed> */
	private function normalize( array $row ): array {
		return array(
			'upload_uuid'             => strtolower( (string) $row['upload_uuid'] ),
			'session_uuid'            => strtolower( (string) $row['session_uuid'] ),
			'user_id'                 => (int) $row['user_id'],
			'adapter_key'              => sanitize_key( (string) $row['adapter_key'] ),
			'native_upload_reference'  => (string) $row['native_upload_reference'],
			'purpose'                  => sanitize_key( (string) $row['purpose'] ),
			'mime_type'                => strtolower( (string) $row['mime_type'] ),
			'size_bytes'               => (int) $row['size_bytes'],
			'checksum_sha256'          => null === $row['checksum_sha256'] ? null : strtolower( (string) $row['checksum_sha256'] ),
			'status'                   => sanitize_key( (string) $row['status'] ),
			'created_at'               => (string) $row['created_at'],
			'updated_at'               => (string) $row['updated_at'],
			'expires_at'               => (string) $row['expires_at'],
		);
	}

	private function error( string $code ): WP_Error {
		return new WP_Error( 'supc_' . $code, __( 'The native upload-token request could not be completed.', 'sabri-universal-post-composer' ) );
	}
}
