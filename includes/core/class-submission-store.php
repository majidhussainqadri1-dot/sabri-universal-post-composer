<?php
/**
 * Durable submission identity and reconciliation outbox for File 22.
 *
 * Only privacy-safe orchestration metadata is stored here. Draft bodies,
 * consent evidence, identity documents, and media bytes remain with native
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

final class Submission_Store {
	private const SUBMISSION_TABLE_SUFFIX = 'supc_submissions';
	private const OUTBOX_TABLE_SUFFIX     = 'supc_outbox';
	private const SCHEMA_OPTION           = 'supc_submission_schema_version';
	private const SCHEMA_VERSION          = '1.0.0';
	private const MAX_ATTEMPTS            = 5;
	private const BACKOFF_SECONDS         = array( 60, 300, 1800, 7200, 43200 );
	private const ATTEMPT_STATES          = array( 'prepared', 'dispatched', 'retryable', 'reconcile', 'resolved', 'failed', 'dead_letter' );
	private const OUTBOX_STATES           = array( 'queued', 'processing', 'retry', 'completed', 'dead_letter' );
	private const REFERENCE_PATTERN       = '/^[A-Za-z0-9][A-Za-z0-9._:-]{0,254}$/D';
	private const HASH_PATTERN            = '/^[0-9a-f]{64}$/D';
	private const UUID_V4_PATTERN         = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D';

	public static function install(): bool {
		global $wpdb;
		if ( ! is_object( $wpdb ) || ! isset( $wpdb->prefix ) ) {
			return false;
		}
		$charset        = method_exists( $wpdb, 'get_charset_collate' ) ? (string) $wpdb->get_charset_collate() : '';
		$submissions    = self::submission_table_name();
		$outbox         = self::outbox_table_name();
		$submission_sql = "CREATE TABLE {$submissions} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			attempt_uuid char(36) NOT NULL,
			session_uuid char(36) NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			adapter_key varchar(64) NOT NULL,
			native_reference varchar(255) NOT NULL,
			idempotency_key varchar(80) NOT NULL,
			payload_hash char(64) NOT NULL,
			state varchar(32) NOT NULL DEFAULT 'prepared',
			native_status varchar(32) NULL,
			native_response_hash char(64) NULL,
			attempts smallint(5) unsigned NOT NULL DEFAULT 0,
			last_error varchar(64) NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			completed_at datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY attempt_uuid (attempt_uuid),
			UNIQUE KEY idempotency_key (idempotency_key),
			UNIQUE KEY session_submission (session_uuid,user_id),
			KEY state_updated (state,updated_at),
			KEY user_adapter (user_id,adapter_key)
		) {$charset};";
		$outbox_sql = "CREATE TABLE {$outbox} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_uuid char(36) NOT NULL,
			attempt_uuid char(36) NOT NULL,
			session_uuid char(36) NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			adapter_key varchar(64) NOT NULL,
			native_reference varchar(255) NOT NULL,
			topic varchar(64) NOT NULL,
			status varchar(32) NOT NULL DEFAULT 'queued',
			attempts smallint(5) unsigned NOT NULL DEFAULT 0,
			next_attempt_at datetime NOT NULL,
			last_error_code varchar(64) NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			processed_at datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY event_uuid (event_uuid),
			UNIQUE KEY attempt_topic (attempt_uuid,topic),
			KEY due_queue (status,next_attempt_at),
			KEY session_uuid (session_uuid)
		) {$charset};";
		if ( defined( 'ABSPATH' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}
		if ( ! function_exists( 'dbDelta' ) ) {
			return false;
		}
		dbDelta( $submission_sql );
		dbDelta( $outbox_sql );
		if ( ! self::tables_exist() ) {
			return false;
		}
		if ( function_exists( 'update_option' ) ) {
			update_option( self::SCHEMA_OPTION, self::SCHEMA_VERSION, false );
		}
		return true;
	}

	public static function maybe_install(): bool {
		$installed = function_exists( 'get_option' ) ? (string) get_option( self::SCHEMA_OPTION, '' ) : '';
		return self::SCHEMA_VERSION === $installed && self::tables_exist() ? true : self::install();
	}

	public static function tables_exist(): bool {
		return self::table_exists( self::submission_table_name() ) && self::table_exists( self::outbox_table_name() );
	}

	/**
	 * Canonical, order-stable payload fingerprint. The payload itself is never
	 * stored in File 22 submission or outbox tables.
	 *
	 * @param array<string,mixed> $payload Validated user payload.
	 */
	public static function payload_fingerprint( array $payload ): string {
		$canonical = self::canonicalize( $payload );
		$encoded   = function_exists( 'wp_json_encode' ) ? wp_json_encode( $canonical ) : json_encode( $canonical );
		return is_string( $encoded ) ? hash( 'sha256', $encoded ) : '';
	}

	/** @return array<string,mixed>|WP_Error */
	public function prepare(
		string $session_uuid,
		int $user_id,
		string $adapter_key,
		string $native_reference,
		string $proposed_key,
		string $payload_hash
	): array|WP_Error {
		if (
			$user_id <= 0 ||
			! $this->valid_uuid( $session_uuid ) ||
			! Contract_Boundary::adapter_key( $adapter_key ) ||
			1 !== preg_match( self::REFERENCE_PATTERN, $native_reference ) ||
			! ( new Workflow_Validator() )->valid_idempotency_key( $proposed_key ) ||
			1 !== preg_match( self::HASH_PATTERN, $payload_hash )
		) {
			return $this->error( 'invalid_submission_identity' );
		}
		$existing = $this->get_for_session( $session_uuid, $user_id );
		if ( ! $existing instanceof WP_Error ) {
			if (
				! hash_equals( (string) $existing['adapter_key'], $adapter_key ) ||
				! hash_equals( (string) $existing['native_reference'], $native_reference )
			) {
				return $this->error( 'submission_identity_conflict' );
			}
			if ( ! hash_equals( (string) $existing['payload_hash'], $payload_hash ) ) {
				return $this->error( 'idempotency_payload_mismatch' );
			}
			return $existing;
		}
		if ( 'supc_submission_not_found' !== $this->error_code( $existing ) ) {
			return $existing;
		}

		global $wpdb;
		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'insert' ) || ! function_exists( 'wp_generate_uuid4' ) ) {
			return $this->error( 'submission_store_unavailable' );
		}
		$attempt_uuid = strtolower( wp_generate_uuid4() );
		if ( ! $this->valid_uuid( $attempt_uuid ) ) {
			return $this->error( 'submission_identifier_failed' );
		}
		$now      = gmdate( 'Y-m-d H:i:s' );
		$inserted = $wpdb->insert(
			self::submission_table_name(),
			array(
				'attempt_uuid'    => $attempt_uuid,
				'session_uuid'    => strtolower( $session_uuid ),
				'user_id'         => $user_id,
				'adapter_key'      => $adapter_key,
				'native_reference' => $native_reference,
				'idempotency_key'  => $proposed_key,
				'payload_hash'     => $payload_hash,
				'state'            => 'prepared',
				'attempts'         => 0,
				'created_at'       => $now,
				'updated_at'       => $now,
			),
			array( '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);
		if ( 1 !== $inserted ) {
			$existing = $this->get_for_session( $session_uuid, $user_id );
			return $existing instanceof WP_Error ? $this->error( 'submission_prepare_failed' ) : $existing;
		}
		return $this->get_by_attempt( $attempt_uuid );
	}

	/** @return array<string,mixed>|WP_Error */
	public function get_for_session( string $session_uuid, int $user_id ): array|WP_Error {
		if ( $user_id <= 0 || ! $this->valid_uuid( $session_uuid ) ) {
			return $this->error( 'invalid_submission_reference' );
		}
		global $wpdb;
		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_row' ) || ! method_exists( $wpdb, 'prepare' ) ) {
			return $this->error( 'submission_store_unavailable' );
		}
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT attempt_uuid,session_uuid,user_id,adapter_key,native_reference,idempotency_key,payload_hash,state,native_status,native_response_hash,attempts,last_error,created_at,updated_at,completed_at FROM %i WHERE session_uuid = %s AND user_id = %d LIMIT 1',
				self::submission_table_name(),
				strtolower( $session_uuid ),
				$user_id
			),
			ARRAY_A
		);
		return is_array( $row ) ? $this->normalize_submission( $row ) : $this->error( 'submission_not_found' );
	}

	/** @return array<string,mixed>|WP_Error */
	public function get_by_attempt( string $attempt_uuid ): array|WP_Error {
		if ( ! $this->valid_uuid( $attempt_uuid ) ) {
			return $this->error( 'invalid_submission_reference' );
		}
		global $wpdb;
		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_row' ) || ! method_exists( $wpdb, 'prepare' ) ) {
			return $this->error( 'submission_store_unavailable' );
		}
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT attempt_uuid,session_uuid,user_id,adapter_key,native_reference,idempotency_key,payload_hash,state,native_status,native_response_hash,attempts,last_error,created_at,updated_at,completed_at FROM %i WHERE attempt_uuid = %s LIMIT 1',
				self::submission_table_name(),
				strtolower( $attempt_uuid )
			),
			ARRAY_A
		);
		return is_array( $row ) ? $this->normalize_submission( $row ) : $this->error( 'submission_not_found' );
	}

	/** @return array<string,mixed>|WP_Error */
	public function mark_dispatched( string $attempt_uuid ): array|WP_Error {
		$submission = $this->get_by_attempt( $attempt_uuid );
		if ( $submission instanceof WP_Error ) {
			return $submission;
		}
		if ( ! in_array( (string) $submission['state'], array( 'prepared', 'retryable' ), true ) ) {
			return $this->error(
				in_array( (string) $submission['state'], array( 'resolved', 'failed', 'dead_letter' ), true )
					? 'submission_already_final'
					: 'reconciliation_pending'
			);
		}
		global $wpdb;
		$updated = is_object( $wpdb ) && method_exists( $wpdb, 'query' ) && method_exists( $wpdb, 'prepare' )
			? $wpdb->query(
				$wpdb->prepare(
					'UPDATE %i SET state = %s, attempts = attempts + 1, last_error = NULL, updated_at = %s, completed_at = NULL WHERE attempt_uuid = %s',
					self::submission_table_name(),
					'dispatched',
					gmdate( 'Y-m-d H:i:s' ),
					strtolower( $attempt_uuid )
				)
			)
			: false;
		return 1 === $updated ? $this->get_by_attempt( $attempt_uuid ) : $this->error( 'submission_dispatch_record_failed' );
	}

	public function mark_uncertain( string $attempt_uuid, string $error_code, bool $enqueue = true ): bool {
		$submission = $this->get_by_attempt( $attempt_uuid );
		if ( $submission instanceof WP_Error || ! Contract_Boundary::code( $error_code ) ) {
			return false;
		}
		global $wpdb;
		$updated = is_object( $wpdb ) && method_exists( $wpdb, 'update' )
			? $wpdb->update(
				self::submission_table_name(),
				array( 'state' => 'reconcile', 'last_error' => $error_code, 'updated_at' => gmdate( 'Y-m-d H:i:s' ) ),
				array( 'attempt_uuid' => strtolower( $attempt_uuid ) ),
				array( '%s', '%s', '%s' ),
				array( '%s' )
			)
			: false;
		return false !== $updated && ( ! $enqueue || $this->enqueue_reconciliation( $submission, $error_code ) );
	}

	public function mark_reconciled( string $attempt_uuid, string $native_status, string $response_hash ): bool {
		if ( ! in_array( $native_status, array( 'draft', 'pending_review', 'scheduled', 'published', 'rejected', 'failed' ), true ) || 1 !== preg_match( self::HASH_PATTERN, $response_hash ) ) {
			return false;
		}
		$retryable = 'draft' === $native_status;
		global $wpdb;
		$updated = is_object( $wpdb ) && method_exists( $wpdb, 'update' )
			? $wpdb->update(
				self::submission_table_name(),
				array(
					'state'                => $retryable ? 'retryable' : 'resolved',
					'native_status'        => $native_status,
					'native_response_hash' => $response_hash,
					'last_error'           => null,
					'updated_at'           => gmdate( 'Y-m-d H:i:s' ),
					'completed_at'         => $retryable ? null : gmdate( 'Y-m-d H:i:s' ),
				),
				array( 'attempt_uuid' => strtolower( $attempt_uuid ) ),
				array( '%s', '%s', '%s', '%s', '%s', '%s' ),
				array( '%s' )
			)
			: false;
		if ( false === $updated ) {
			return false;
		}
		$this->complete_outbox_for_attempt( $attempt_uuid );
		return true;
	}

	/** @return array<int,array<string,mixed>> */
	public function due_reconciliations( int $limit = 20 ): array {
		$limit = max( 1, min( 100, $limit ) );
		global $wpdb;
		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_results' ) || ! method_exists( $wpdb, 'prepare' ) ) {
			return array();
		}
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT event_uuid,attempt_uuid,session_uuid,user_id,adapter_key,native_reference,topic,status,attempts,next_attempt_at,last_error_code,created_at,updated_at,processed_at FROM %i WHERE status IN ('queued','retry') AND next_attempt_at <= %s ORDER BY next_attempt_at ASC,id ASC LIMIT %d",
				self::outbox_table_name(),
				gmdate( 'Y-m-d H:i:s' ),
				$limit
			),
			ARRAY_A
		);
		return is_array( $rows ) ? array_map( array( $this, 'normalize_outbox' ), $rows ) : array();
	}

	public function claim_reconciliation( string $event_uuid ): bool {
		if ( ! $this->valid_uuid( $event_uuid ) ) {
			return false;
		}
		global $wpdb;
		$claimed = is_object( $wpdb ) && method_exists( $wpdb, 'query' ) && method_exists( $wpdb, 'prepare' )
			? $wpdb->query(
				$wpdb->prepare(
					"UPDATE %i SET status = 'processing', updated_at = %s WHERE event_uuid = %s AND status IN ('queued','retry') AND next_attempt_at <= %s",
					self::outbox_table_name(),
					gmdate( 'Y-m-d H:i:s' ),
					strtolower( $event_uuid ),
					gmdate( 'Y-m-d H:i:s' )
				)
			)
			: false;
		return 1 === $claimed;
	}

	public function retry_reconciliation( string $event_uuid, string $error_code ): string {
		if ( ! $this->valid_uuid( $event_uuid ) || ! Contract_Boundary::code( $error_code ) ) {
			return 'invalid';
		}
		$outbox = $this->get_outbox( $event_uuid );
		if ( $outbox instanceof WP_Error ) {
			return 'missing';
		}
		$attempts = (int) $outbox['attempts'] + 1;
		$dead     = $attempts >= self::MAX_ATTEMPTS;
		$index    = min( $attempts - 1, count( self::BACKOFF_SECONDS ) - 1 );
		global $wpdb;
		$updated = is_object( $wpdb ) && method_exists( $wpdb, 'update' )
			? $wpdb->update(
				self::outbox_table_name(),
				array(
					'status'          => $dead ? 'dead_letter' : 'retry',
					'attempts'        => $attempts,
					'next_attempt_at' => gmdate( 'Y-m-d H:i:s', time() + self::BACKOFF_SECONDS[ $index ] ),
					'last_error_code' => $error_code,
					'updated_at'      => gmdate( 'Y-m-d H:i:s' ),
				),
				array( 'event_uuid' => strtolower( $event_uuid ) ),
				array( '%s', '%d', '%s', '%s', '%s' ),
				array( '%s' )
			)
			: false;
		if ( false === $updated ) {
			return 'failed';
		}
		if ( $dead ) {
			$this->mark_submission_dead_letter( (string) $outbox['attempt_uuid'], $error_code );
			do_action(
				'supc_reconciliation_dead_letter',
				Contract_Boundary::public_identifier( (string) $outbox['adapter_key'] ),
				Contract_Boundary::public_identifier( (string) $outbox['session_uuid'] ),
				$error_code
			);
			return 'dead_letter';
		}
		return 'retry';
	}

	public function enqueue_stale_submissions(): int {
		global $wpdb;
		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_results' ) || ! method_exists( $wpdb, 'prepare' ) ) {
			return 0;
		}
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT attempt_uuid,session_uuid,user_id,adapter_key,native_reference,idempotency_key,payload_hash,state,native_status,native_response_hash,attempts,last_error,created_at,updated_at,completed_at FROM %i WHERE state IN ('dispatched','reconcile') AND updated_at <= %s ORDER BY updated_at ASC LIMIT 50",
				self::submission_table_name(),
				gmdate( 'Y-m-d H:i:s', time() - 600 )
			),
			ARRAY_A
		);
		$count = 0;
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$submission = $this->normalize_submission( $row );
				if ( $this->enqueue_reconciliation( $submission, 'stale_dispatch' ) ) {
					++$count;
				}
			}
		}
		return $count;
	}

	/** @return array{queued:int,retry:int,processing:int,dead_letter:int} */
	public function queue_counts(): array {
		$counts = array( 'queued' => 0, 'retry' => 0, 'processing' => 0, 'dead_letter' => 0 );
		global $wpdb;
		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_results' ) ) {
			return $counts;
		}
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT status,COUNT(*) AS total FROM %i WHERE status IN (%s,%s,%s,%s) GROUP BY status', self::outbox_table_name(), 'queued', 'retry', 'processing', 'dead_letter' ), ARRAY_A );
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$status = isset( $row['status'] ) ? sanitize_key( (string) $row['status'] ) : '';
				if ( array_key_exists( $status, $counts ) ) {
					$counts[ $status ] = (int) ( $row['total'] ?? 0 );
				}
			}
		}
		return $counts;
	}

	public static function submission_table_name(): string {
		global $wpdb;
		$prefix = is_object( $wpdb ) && isset( $wpdb->prefix ) ? (string) $wpdb->prefix : 'wp_';
		return $prefix . self::SUBMISSION_TABLE_SUFFIX;
	}

	public static function outbox_table_name(): string {
		global $wpdb;
		$prefix = is_object( $wpdb ) && isset( $wpdb->prefix ) ? (string) $wpdb->prefix : 'wp_';
		return $prefix . self::OUTBOX_TABLE_SUFFIX;
	}

	/** @param array<string,mixed> $submission */
	private function enqueue_reconciliation( array $submission, string $error_code ): bool {
		$existing = $this->get_outbox_for_attempt( (string) $submission['attempt_uuid'] );
		if ( ! $existing instanceof WP_Error ) {
			if ( 'completed' === (string) $existing['status'] ) {
				return true;
			}
			if ( 'dead_letter' === (string) $existing['status'] ) {
				return false;
			}
			global $wpdb;
			$updated = is_object( $wpdb ) && method_exists( $wpdb, 'update' )
				? $wpdb->update(
					self::outbox_table_name(),
					array( 'status' => 'queued', 'next_attempt_at' => gmdate( 'Y-m-d H:i:s' ), 'last_error_code' => $error_code, 'updated_at' => gmdate( 'Y-m-d H:i:s' ) ),
					array( 'event_uuid' => (string) $existing['event_uuid'] ),
					array( '%s', '%s', '%s', '%s' ),
					array( '%s' )
				)
				: false;
			return false !== $updated;
		}
		if ( 'supc_outbox_not_found' !== $this->error_code( $existing ) || ! function_exists( 'wp_generate_uuid4' ) ) {
			return false;
		}
		$event_uuid = strtolower( wp_generate_uuid4() );
		if ( ! $this->valid_uuid( $event_uuid ) ) {
			return false;
		}
		global $wpdb;
		$now      = gmdate( 'Y-m-d H:i:s' );
		$inserted = is_object( $wpdb ) && method_exists( $wpdb, 'insert' )
			? $wpdb->insert(
				self::outbox_table_name(),
				array(
					'event_uuid'       => $event_uuid,
					'attempt_uuid'     => (string) $submission['attempt_uuid'],
					'session_uuid'     => (string) $submission['session_uuid'],
					'user_id'          => (int) $submission['user_id'],
					'adapter_key'      => (string) $submission['adapter_key'],
					'native_reference' => (string) $submission['native_reference'],
					'topic'            => 'submission_reconcile',
					'status'           => 'queued',
					'attempts'         => 0,
					'next_attempt_at'  => $now,
					'last_error_code'  => $error_code,
					'created_at'       => $now,
					'updated_at'       => $now,
				),
				array( '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
			)
			: false;
		if ( 1 === $inserted ) {
			return true;
		}
		$existing = $this->get_outbox_for_attempt( (string) $submission['attempt_uuid'] );
		return ! $existing instanceof WP_Error;
	}

	private function complete_outbox_for_attempt( string $attempt_uuid ): void {
		global $wpdb;
		if ( is_object( $wpdb ) && method_exists( $wpdb, 'update' ) ) {
			$wpdb->update(
				self::outbox_table_name(),
				array( 'status' => 'completed', 'updated_at' => gmdate( 'Y-m-d H:i:s' ), 'processed_at' => gmdate( 'Y-m-d H:i:s' ) ),
				array( 'attempt_uuid' => strtolower( $attempt_uuid ) ),
				array( '%s', '%s', '%s' ),
				array( '%s' )
			);
		}
	}

	private function mark_submission_dead_letter( string $attempt_uuid, string $error_code ): void {
		global $wpdb;
		if ( is_object( $wpdb ) && method_exists( $wpdb, 'update' ) ) {
			$wpdb->update(
				self::submission_table_name(),
				array( 'state' => 'dead_letter', 'last_error' => $error_code, 'updated_at' => gmdate( 'Y-m-d H:i:s' ) ),
				array( 'attempt_uuid' => strtolower( $attempt_uuid ) ),
				array( '%s', '%s', '%s' ),
				array( '%s' )
			);
		}
	}

	/** @return array<string,mixed>|WP_Error */
	private function get_outbox( string $event_uuid ): array|WP_Error {
		global $wpdb;
		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_row' ) || ! method_exists( $wpdb, 'prepare' ) ) {
			return $this->error( 'outbox_store_unavailable' );
		}
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT event_uuid,attempt_uuid,session_uuid,user_id,adapter_key,native_reference,topic,status,attempts,next_attempt_at,last_error_code,created_at,updated_at,processed_at FROM %i WHERE event_uuid = %s LIMIT 1',
				self::outbox_table_name(),
				strtolower( $event_uuid )
			),
			ARRAY_A
		);
		return is_array( $row ) ? $this->normalize_outbox( $row ) : $this->error( 'outbox_not_found' );
	}

	/** @return array<string,mixed>|WP_Error */
	private function get_outbox_for_attempt( string $attempt_uuid ): array|WP_Error {
		global $wpdb;
		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_row' ) || ! method_exists( $wpdb, 'prepare' ) ) {
			return $this->error( 'outbox_store_unavailable' );
		}
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT event_uuid,attempt_uuid,session_uuid,user_id,adapter_key,native_reference,topic,status,attempts,next_attempt_at,last_error_code,created_at,updated_at,processed_at FROM %i WHERE attempt_uuid = %s AND topic = %s LIMIT 1',
				self::outbox_table_name(),
				strtolower( $attempt_uuid ),
				'submission_reconcile'
			),
			ARRAY_A
		);
		return is_array( $row ) ? $this->normalize_outbox( $row ) : $this->error( 'outbox_not_found' );
	}

	private static function table_exists( string $table ): bool {
		global $wpdb;
		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_var' ) || ! method_exists( $wpdb, 'prepare' ) ) {
			return false;
		}
		$like = method_exists( $wpdb, 'esc_like' ) ? $wpdb->esc_like( $table ) : addcslashes( $table, '_%\\' );
		return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );
	}

	private static function canonicalize( mixed $value ): mixed {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		$keys = array_keys( $value );
		$list = array() === $value || $keys === range( 0, count( $value ) - 1 );
		if ( ! $list ) {
			ksort( $value, SORT_STRING );
		}
		foreach ( $value as $key => $item ) {
			$value[ $key ] = self::canonicalize( $item );
		}
		return $value;
	}

	private function valid_uuid( string $uuid ): bool {
		return 1 === preg_match( self::UUID_V4_PATTERN, strtolower( $uuid ) );
	}

	/** @param array<string,mixed> $row @return array<string,mixed> */
	private function normalize_submission( array $row ): array {
		$state = sanitize_key( (string) ( $row['state'] ?? '' ) );
		return array(
			'attempt_uuid'        => strtolower( (string) $row['attempt_uuid'] ),
			'session_uuid'        => strtolower( (string) $row['session_uuid'] ),
			'user_id'             => (int) $row['user_id'],
			'adapter_key'         => sanitize_key( (string) $row['adapter_key'] ),
			'native_reference'    => (string) $row['native_reference'],
			'idempotency_key'     => (string) $row['idempotency_key'],
			'payload_hash'        => strtolower( (string) $row['payload_hash'] ),
			'state'               => in_array( $state, self::ATTEMPT_STATES, true ) ? $state : 'failed',
			'native_status'       => null === $row['native_status'] ? null : sanitize_key( (string) $row['native_status'] ),
			'native_response_hash' => null === $row['native_response_hash'] ? null : strtolower( (string) $row['native_response_hash'] ),
			'attempts'            => (int) $row['attempts'],
			'last_error'          => null === $row['last_error'] ? null : sanitize_key( (string) $row['last_error'] ),
			'created_at'          => (string) $row['created_at'],
			'updated_at'          => (string) $row['updated_at'],
			'completed_at'        => null === $row['completed_at'] ? null : (string) $row['completed_at'],
		);
	}

	/** @param array<string,mixed> $row @return array<string,mixed> */
	private function normalize_outbox( array $row ): array {
		$status = sanitize_key( (string) ( $row['status'] ?? '' ) );
		return array(
			'event_uuid'       => strtolower( (string) $row['event_uuid'] ),
			'attempt_uuid'     => strtolower( (string) $row['attempt_uuid'] ),
			'session_uuid'     => strtolower( (string) $row['session_uuid'] ),
			'user_id'          => (int) $row['user_id'],
			'adapter_key'      => sanitize_key( (string) $row['adapter_key'] ),
			'native_reference' => (string) $row['native_reference'],
			'topic'            => sanitize_key( (string) $row['topic'] ),
			'status'           => in_array( $status, self::OUTBOX_STATES, true ) ? $status : 'dead_letter',
			'attempts'         => (int) $row['attempts'],
			'next_attempt_at'  => (string) $row['next_attempt_at'],
			'last_error_code'  => null === $row['last_error_code'] ? null : sanitize_key( (string) $row['last_error_code'] ),
			'created_at'       => (string) $row['created_at'],
			'updated_at'       => (string) $row['updated_at'],
			'processed_at'     => null === $row['processed_at'] ? null : (string) $row['processed_at'],
		);
	}

	private function error_code( WP_Error $error ): string {
		if ( is_callable( array( $error, 'get_error_code' ) ) ) {
			return (string) call_user_func( array( $error, 'get_error_code' ) );
		}
		$properties = get_object_vars( $error );
		return isset( $properties['code'] ) ? (string) $properties['code'] : '';
	}

	private function error( string $code ): WP_Error {
		return new WP_Error( 'supc_' . sanitize_key( $code ), __( 'The Composer submission record could not be completed.', 'sabri-universal-post-composer' ) );
	}
}
