<?php
/**
 * Privacy-safe, metadata-only Composer audit ledger.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

namespace Sabri\UniversalComposer\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Audit_Store {
	private const TABLE_SUFFIX   = 'supc_audit_log';
	private const SCHEMA_OPTION  = 'supc_audit_schema_version';
	private const SCHEMA_VERSION = '1.0.0';
	private const RETENTION      = 31536000; // 365 days.

	public static function install(): bool {
		global $wpdb;
		if ( ! is_object( $wpdb ) || ! isset( $wpdb->prefix ) ) {
			return false;
		}
		$table   = self::table_name();
		$charset = method_exists( $wpdb, 'get_charset_collate' ) ? (string) $wpdb->get_charset_collate() : '';
		$sql     = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_uuid char(36) NOT NULL,
			session_uuid char(36) NULL,
			user_id bigint(20) unsigned NOT NULL,
			adapter_key varchar(64) NOT NULL,
			event_code varchar(64) NOT NULL,
			outcome varchar(16) NOT NULL,
			native_reference_hash char(64) NULL,
			correlation_hash char(64) NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY event_uuid (event_uuid),
			KEY session_created (session_uuid,created_at),
			KEY user_created (user_id,created_at),
			KEY event_created (event_code,created_at)
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

	public function record(
		int $user_id,
		string $adapter_key,
		string $event_code,
		string $outcome,
		?string $session_uuid = null,
		?string $native_reference = null,
		?string $correlation = null
	): bool {
		if (
			$user_id <= 0 ||
			! Contract_Boundary::adapter_key( $adapter_key ) ||
			! Contract_Boundary::code( $event_code ) ||
			! in_array( $outcome, array( 'success', 'denied', 'failed', 'pending' ), true ) ||
			( null !== $session_uuid && 1 !== preg_match( '/^[0-9a-f-]{36}$/D', strtolower( $session_uuid ) ) ) ||
			! function_exists( 'wp_generate_uuid4' )
		) {
			return false;
		}
		global $wpdb;
		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'insert' ) ) {
			return false;
		}
		$event_uuid = strtolower( wp_generate_uuid4() );
		$inserted   = $wpdb->insert(
			self::table_name(),
			array(
				'event_uuid'             => $event_uuid,
				'session_uuid'           => null === $session_uuid ? null : strtolower( $session_uuid ),
				'user_id'                => $user_id,
				'adapter_key'             => $adapter_key,
				'event_code'              => $event_code,
				'outcome'                 => $outcome,
				'native_reference_hash'   => null === $native_reference || '' === $native_reference ? null : hash( 'sha256', $native_reference ),
				'correlation_hash'        => null === $correlation || '' === $correlation ? null : hash( 'sha256', $correlation ),
				'created_at'              => gmdate( 'Y-m-d H:i:s' ),
			),
			array( '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		if ( 1 !== $inserted ) {
			return false;
		}
		do_action( 'supc_audit_event', $event_code, $outcome, Contract_Boundary::public_identifier( $adapter_key ) );
		return true;
	}


	/**
	 * Return bounded aggregate observability without exposing users, content,
	 * native references or individual event records.
	 *
	 * @return array{total:int,success:int,denied:int,failed:int,pending:int,events:array<string,int>}
	 */
	public function summary( int $days = 30 ): array {
		$summary = array( 'total' => 0, 'success' => 0, 'denied' => 0, 'failed' => 0, 'pending' => 0, 'events' => array() );
		if ( ! self::table_exists() ) {
			return $summary;
		}
		$days = max( 1, min( 365, $days ) );
		global $wpdb;
		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_results' ) || ! method_exists( $wpdb, 'prepare' ) ) {
			return $summary;
		}
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT event_code,outcome,COUNT(*) AS total FROM %i WHERE created_at >= %s GROUP BY event_code,outcome ORDER BY total DESC LIMIT 100',
				self::table_name(),
				gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) )
			),
			ARRAY_A
		);
		if ( ! is_array( $rows ) ) {
			return $summary;
		}
		foreach ( $rows as $row ) {
			$event   = isset( $row['event_code'] ) ? sanitize_key( (string) $row['event_code'] ) : '';
			$outcome = isset( $row['outcome'] ) ? sanitize_key( (string) $row['outcome'] ) : '';
			$total   = max( 0, min( 1000000, (int) ( $row['total'] ?? 0 ) ) );
			if ( ! Contract_Boundary::code( $event ) || ! array_key_exists( $outcome, $summary ) || 'total' === $outcome || 'events' === $outcome ) {
				continue;
			}
			$summary['total'] += $total;
			$summary[ $outcome ] += $total;
			$summary['events'][ $event ] = ( $summary['events'][ $event ] ?? 0 ) + $total;
		}
		arsort( $summary['events'], SORT_NUMERIC );
		$summary['events'] = array_slice( $summary['events'], 0, 25, true );
		return $summary;
	}

	public static function cleanup_expired(): int {
		global $wpdb;
		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'query' ) || ! method_exists( $wpdb, 'prepare' ) ) {
			return 0;
		}
		$deleted = $wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE created_at < %s LIMIT 500',
				self::table_name(),
				gmdate( 'Y-m-d H:i:s', time() - self::RETENTION )
			)
		);
		return is_int( $deleted ) && $deleted > 0 ? $deleted : 0;
	}

	public static function table_name(): string {
		global $wpdb;
		$prefix = is_object( $wpdb ) && isset( $wpdb->prefix ) ? (string) $wpdb->prefix : 'wp_';
		return $prefix . self::TABLE_SUFFIX;
	}
}
