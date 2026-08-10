<?php
/**
 * File 22 orchestration-session store.
 *
 * The store deliberately persists only workflow identity, opaque native
 * references, concurrency state, payload fingerprints, and idempotency
 * metadata. Draft bodies, patient consent, identity evidence, and media bytes
 * remain with native owners.
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
	private const TABLE_SUFFIX        = 'supc_sessions';
	private const SCHEMA_OPTION       = 'supc_session_schema_version';
	private const SCHEMA_VERSION      = '1.2.0';
	private const ORDINARY_TTL        = 15552000; // 180 days.
	private const SENSITIVE_TTL       = 2592000;  // 30 days.
	private const COMPLETED_TTL       = 2592000;  // 30 days.
	private const MAX_ADAPTER_BYTES   = 64;
	private const MAX_VERSION_BYTES   = 32;
	private const MAX_REFERENCE_BYTES = 255;
	private const SESSION_PATTERN     = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D';
	private const HASH_PATTERN        = '/^[0-9a-f]{64}$/D';
	private const STATES              = array( 'new', 'draft', 'valid', 'submitting', 'reconcile', 'submitted', 'under_review', 'changes_requested', 'approved', 'withdrawn', 'scheduled', 'published', 'hidden', 'archived', 'deleted', 'rejected', 'failed' );
	private const SENSITIVITY         = array( 'public', 'private', 'sensitive' );
	private const COMPOSER_STATES      = array( 'new', 'editing', 'autosaved', 'offline_pending', 'conflicted', 'abandoned', 'completed' );
	private const REVIEW_STATES        = array( 'not_required', 'draft', 'submitted', 'under_review', 'changes_requested', 'approved', 'rejected', 'withdrawn' );
	private const PUBLICATION_STATES   = array( 'unpublished', 'scheduled', 'published', 'hidden', 'archived', 'deleted' );
	private const HOLD_STATES          = array( 'clear', 'privacy_hold', 'medical_hold', 'copyright_hold', 'security_hold', 'suspended' );

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
			composer_state varchar(32) NOT NULL DEFAULT 'new',
			review_state varchar(32) NOT NULL DEFAULT 'draft',
			publication_state varchar(32) NOT NULL DEFAULT 'unpublished',
			hold_state varchar(32) NOT NULL DEFAULT 'clear',
			sensitivity_class varchar(16) NOT NULL DEFAULT 'private',
			lock_version bigint(20) unsigned NOT NULL DEFAULT 1,
			idempotency_key varchar(80) NULL,
			payload_hash char(64) NULL,
			submit_attempts smallint(5) unsigned NOT NULL DEFAULT 0,
			reconciliation_required tinyint(1) unsigned NOT NULL DEFAULT 0,
			last_error varchar(64) NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			expires_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY session_uuid (session_uuid),
			KEY user_adapter (user_id,adapter_key),
			KEY state_reconcile (state,reconciliation_required),
			KEY workflow_dimensions (composer_state,review_state,publication_state,hold_state),
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
		$like  = method_exists( $wpdb, 'esc_like' ) ? $wpdb->esc_like( $table ) : addcslashes( $table, '_%\\' );
		return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );
	}

	/** @return array<string,mixed>|WP_Error */
	public function create( int $user_id, string $adapter_key, string $adapter_version, string $sensitivity_class = 'private' ): array|WP_Error {
		if (
			$user_id <= 0 ||
			! Contract_Boundary::adapter_key( $adapter_key ) ||
			! Contract_Boundary::bounded_text( $adapter_key, 1, self::MAX_ADAPTER_BYTES ) ||
			! Contract_Boundary::version( $adapter_version ) ||
			! Contract_Boundary::bounded_text( $adapter_version, 1, self::MAX_VERSION_BYTES ) ||
			! in_array( $sensitivity_class, self::SENSITIVITY, true ) ||
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
				'session_uuid'           => $uuid,
				'user_id'                => $user_id,
				'adapter_key'             => $adapter_key,
				'adapter_version'         => $adapter_version,
				'state'                   => 'new',
				'composer_state'          => 'new',
				'review_state'            => 'draft',
				'publication_state'       => 'unpublished',
				'hold_state'              => 'clear',
				'sensitivity_class'       => $sensitivity_class,
				'lock_version'           => 1,
				'submit_attempts'         => 0,
				'reconciliation_required' => 0,
				'created_at'              => gmdate( 'Y-m-d H:i:s', $now ),
				'updated_at'              => gmdate( 'Y-m-d H:i:s', $now ),
				'expires_at'              => gmdate( 'Y-m-d H:i:s', $now + $this->ttl_for_sensitivity( $sensitivity_class ) ),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s' )
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
				'SELECT session_uuid,user_id,adapter_key,adapter_version,native_reference,state,composer_state,review_state,publication_state,hold_state,sensitivity_class,lock_version,idempotency_key,payload_hash,submit_attempts,reconciliation_required,last_error,created_at,updated_at,expires_at FROM %i WHERE session_uuid = %s AND user_id = %d LIMIT 1',
				self::table_name(),
				strtolower( $uuid ),
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
		$current = $this->get_owned( $uuid, $user_id );
		if ( $current instanceof WP_Error ) {
			return $current;
		}
		$dimensions = $this->dimensions_for_state( $state, $current );
		$now = time();
		$ttl = in_array( $state, array( 'submitted', 'under_review', 'approved', 'withdrawn', 'scheduled', 'published', 'hidden', 'archived', 'deleted', 'rejected', 'failed' ), true )
			? self::COMPLETED_TTL
			: $this->ttl_for_sensitivity( (string) $current['sensitivity_class'] );
		global $wpdb;
		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'query' ) || ! method_exists( $wpdb, 'prepare' ) ) {
			return $this->error( 'session_store_unavailable' );
		}
		$updated = $wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET state = %s, composer_state = %s, review_state = %s, publication_state = %s, hold_state = %s, native_reference = %s, idempotency_key = %s, last_error = %s, lock_version = lock_version + 1, updated_at = %s, expires_at = %s WHERE session_uuid = %s AND user_id = %d AND lock_version = %d',
				self::table_name(),
				$state,
				$dimensions['composer_state'],
				$dimensions['review_state'],
				$dimensions['publication_state'],
				$dimensions['hold_state'],
				$native_reference,
				null !== $idempotency_key ? $idempotency_key : (string) ( $current['idempotency_key'] ?? '' ),
				$last_error,
				gmdate( 'Y-m-d H:i:s', $now ),
				gmdate( 'Y-m-d H:i:s', $now + $ttl ),
				strtolower( $uuid ),
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
	public function ensure_idempotency_key( string $uuid, int $user_id, string $idempotency_key ): array|WP_Error {
		if ( ! $this->valid_uuid( $uuid ) || $user_id <= 0 || ! ( new Workflow_Validator() )->valid_idempotency_key( $idempotency_key ) ) {
			return $this->error( 'invalid_submission_identity' );
		}
		$current = $this->get_owned( $uuid, $user_id );
		if ( $current instanceof WP_Error ) {
			return $current;
		}
		if ( is_string( $current['idempotency_key'] ) && '' !== $current['idempotency_key'] ) {
			return hash_equals( $current['idempotency_key'], $idempotency_key )
				? $current
				: $this->error( 'idempotency_key_conflict' );
		}
		global $wpdb;
		$updated = is_object( $wpdb ) && method_exists( $wpdb, 'query' ) && method_exists( $wpdb, 'prepare' )
			? $wpdb->query(
				$wpdb->prepare(
					'UPDATE %i SET idempotency_key = %s, lock_version = lock_version + 1, updated_at = %s WHERE session_uuid = %s AND user_id = %d AND lock_version = %d AND (idempotency_key IS NULL OR idempotency_key = %s)',
					self::table_name(),
					$idempotency_key,
					gmdate( 'Y-m-d H:i:s' ),
					strtolower( $uuid ),
					$user_id,
					(int) $current['lock_version'],
					''
				)
			)
			: false;
		if ( 1 !== $updated ) {
			$latest = $this->get_owned( $uuid, $user_id );
			if ( ! $latest instanceof WP_Error && is_string( $latest['idempotency_key'] ) && hash_equals( $latest['idempotency_key'], $idempotency_key ) ) {
				return $latest;
			}
			return $latest instanceof WP_Error ? $latest : $this->error( 'session_conflict', array( 'current' => $latest ) );
		}
		return $this->get_owned( $uuid, $user_id );
	}

	/** @return array<string,mixed>|WP_Error */
	public function begin_submission(
		string $uuid,
		int $user_id,
		int $expected_lock_version,
		string $idempotency_key,
		string $payload_hash
	): array|WP_Error {
		if (
			$user_id <= 0 ||
			$expected_lock_version <= 0 ||
			! $this->valid_uuid( $uuid ) ||
			1 !== preg_match( self::HASH_PATTERN, $payload_hash ) ||
			! ( new Workflow_Validator() )->valid_idempotency_key( $idempotency_key )
		) {
			return $this->error( 'invalid_submission_identity' );
		}
		$current = $this->get_owned( $uuid, $user_id );
		if ( $current instanceof WP_Error ) {
			return $current;
		}
		if ( in_array( (string) $current['state'], array( 'submitted', 'under_review', 'approved', 'withdrawn', 'scheduled', 'published', 'hidden', 'archived', 'deleted', 'rejected', 'failed' ), true ) ) {
			return $this->error( 'submission_already_final' );
		}
		if ( ! empty( $current['reconciliation_required'] ) || in_array( (string) $current['state'], array( 'submitting', 'reconcile' ), true ) ) {
			return $this->error( 'reconciliation_pending' );
		}
		if ( is_string( $current['idempotency_key'] ) && '' !== $current['idempotency_key'] && ! hash_equals( $current['idempotency_key'], $idempotency_key ) ) {
			return $this->error( 'idempotency_key_conflict' );
		}
		if ( is_string( $current['payload_hash'] ) && '' !== $current['payload_hash'] && ! hash_equals( $current['payload_hash'], $payload_hash ) ) {
			return $this->error( 'idempotency_payload_mismatch' );
		}
		global $wpdb;
		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'query' ) || ! method_exists( $wpdb, 'prepare' ) ) {
			return $this->error( 'session_store_unavailable' );
		}
		$now     = time();
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE %i SET state = 'submitting', composer_state = 'autosaved', review_state = 'draft', publication_state = 'unpublished', idempotency_key = %s, payload_hash = %s, submit_attempts = submit_attempts + 1, reconciliation_required = 0, last_error = NULL, lock_version = lock_version + 1, updated_at = %s, expires_at = %s WHERE session_uuid = %s AND user_id = %d AND lock_version = %d",
				self::table_name(),
				$idempotency_key,
				$payload_hash,
				gmdate( 'Y-m-d H:i:s', $now ),
				gmdate( 'Y-m-d H:i:s', $now + $this->ttl_for_sensitivity( (string) $current['sensitivity_class'] ) ),
				strtolower( $uuid ),
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


	/**
	 * Reset a submission identity after a provable pre-native local failure.
	 * This method must never be used after an adapter invocation or uncertain
	 * network outcome.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public function reset_pre_dispatch( string $uuid, int $user_id, int $expected_lock_version, string $error_code ): array|WP_Error {
		if ( ! Contract_Boundary::code( $error_code ) ) {
			$error_code = 'pre_dispatch_blocked';
		}
		$current = $this->get_owned( $uuid, $user_id );
		if ( $current instanceof WP_Error ) {
			return $current;
		}
		if ( 'submitting' !== (string) $current['state'] || ! empty( $current['reconciliation_required'] ) ) {
			return $this->error( 'pre_dispatch_reset_not_allowed' );
		}
		global $wpdb;
		$updated = is_object( $wpdb ) && method_exists( $wpdb, 'query' ) && method_exists( $wpdb, 'prepare' )
			? $wpdb->query(
				$wpdb->prepare(
					"UPDATE %i SET state = 'draft', composer_state = 'autosaved', review_state = 'draft', publication_state = 'unpublished', idempotency_key = NULL, payload_hash = NULL, reconciliation_required = 0, last_error = %s, lock_version = lock_version + 1, updated_at = %s, expires_at = %s WHERE session_uuid = %s AND user_id = %d AND lock_version = %d AND state = 'submitting'",
					self::table_name(),
					$error_code,
					gmdate( 'Y-m-d H:i:s' ),
					gmdate( 'Y-m-d H:i:s', time() + $this->ttl_for_sensitivity( (string) $current['sensitivity_class'] ) ),
					strtolower( $uuid ),
					$user_id,
					$expected_lock_version
				)
			)
			: false;
		return 1 === $updated ? $this->get_owned( $uuid, $user_id ) : $this->error( 'session_conflict', array( 'current' => $current ) );
	}

	/** @return array<string,mixed>|WP_Error */
	public function mark_reconciliation( string $uuid, int $user_id, int $expected_lock_version, string $error_code ): array|WP_Error {
		if ( ! Contract_Boundary::code( $error_code ) ) {
			$error_code = 'reconciliation_required';
		}
		$current = $this->get_owned( $uuid, $user_id );
		if ( $current instanceof WP_Error ) {
			return $current;
		}
		global $wpdb;
		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'query' ) || ! method_exists( $wpdb, 'prepare' ) ) {
			return $this->error( 'session_store_unavailable' );
		}
		$now     = time();
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE %i SET state = 'reconcile', composer_state = 'conflicted', reconciliation_required = 1, last_error = %s, lock_version = lock_version + 1, updated_at = %s, expires_at = %s WHERE session_uuid = %s AND user_id = %d AND lock_version = %d",
				self::table_name(),
				$error_code,
				gmdate( 'Y-m-d H:i:s', $now ),
				gmdate( 'Y-m-d H:i:s', $now + $this->ttl_for_sensitivity( (string) $current['sensitivity_class'] ) ),
				strtolower( $uuid ),
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
	public function finalize_submission(
		string $uuid,
		int $user_id,
		int $expected_lock_version,
		string $native_status,
		string $native_reference,
		string $idempotency_key,
		string $payload_hash
	): array|WP_Error {
		return $this->apply_reconciliation(
			$uuid,
			$user_id,
			$expected_lock_version,
			$native_status,
			$native_reference,
			$idempotency_key,
			$payload_hash
		);
	}

	/** @return array<string,mixed>|WP_Error */
	public function apply_reconciliation(
		string $uuid,
		int $user_id,
		int $expected_lock_version,
		string $native_status,
		string $native_reference,
		string $idempotency_key,
		string $payload_hash
	): array|WP_Error {
		$state = $this->session_state_for_native( $native_status );
		if (
			'' === $state ||
			! $this->valid_uuid( $uuid ) ||
			$user_id <= 0 ||
			$expected_lock_version <= 0 ||
			1 !== preg_match( '/^[A-Za-z0-9][A-Za-z0-9._:-]{0,254}$/D', $native_reference ) ||
			1 !== preg_match( self::HASH_PATTERN, $payload_hash ) ||
			! ( new Workflow_Validator() )->valid_idempotency_key( $idempotency_key )
		) {
			return $this->error( 'invalid_reconciliation_result' );
		}
		$current = $this->get_owned( $uuid, $user_id );
		if ( $current instanceof WP_Error ) {
			return $current;
		}
		if (
			! is_string( $current['native_reference'] ) ||
			! hash_equals( $current['native_reference'], $native_reference ) ||
			! is_string( $current['idempotency_key'] ) ||
			! hash_equals( $current['idempotency_key'], $idempotency_key ) ||
			! is_string( $current['payload_hash'] ) ||
			! hash_equals( $current['payload_hash'], $payload_hash )
		) {
			return $this->error( 'submission_identity_conflict' );
		}
		if ( ! $this->transition_allows( (string) $current['state'], $state ) ) {
			return $this->error( 'native_status_regression' );
		}
		if ( $state === (string) $current['state'] && empty( $current['reconciliation_required'] ) ) {
			return $current;
		}
		global $wpdb;
		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'query' ) || ! method_exists( $wpdb, 'prepare' ) ) {
			return $this->error( 'session_store_unavailable' );
		}
		$dimensions = $this->dimensions_for_native_status( $native_status, $current );
		$now = time();
		$ttl = 'draft' === $state
			? $this->ttl_for_sensitivity( (string) $current['sensitivity_class'] )
			: self::COMPLETED_TTL;
		$updated = $wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET state = %s, composer_state = %s, review_state = %s, publication_state = %s, hold_state = %s, reconciliation_required = 0, last_error = NULL, lock_version = lock_version + 1, updated_at = %s, expires_at = %s WHERE session_uuid = %s AND user_id = %d AND lock_version = %d',
				self::table_name(),
				$state,
				$dimensions['composer_state'],
				$dimensions['review_state'],
				$dimensions['publication_state'],
				$dimensions['hold_state'],
				gmdate( 'Y-m-d H:i:s', $now ),
				gmdate( 'Y-m-d H:i:s', $now + $ttl ),
				strtolower( $uuid ),
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


	/** @return array<int,array<string,mixed>> */
	public function list_owned( int $user_id, int $limit = 50 ): array {
		$limit = max( 1, min( 100, $limit ) );
		if ( $user_id <= 0 ) {
			return array();
		}
		global $wpdb;
		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_results' ) || ! method_exists( $wpdb, 'prepare' ) ) {
			return array();
		}
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT session_uuid,user_id,adapter_key,adapter_version,native_reference,state,composer_state,review_state,publication_state,hold_state,sensitivity_class,lock_version,idempotency_key,payload_hash,submit_attempts,reconciliation_required,last_error,created_at,updated_at,expires_at FROM %i WHERE user_id = %d AND expires_at >= %s ORDER BY updated_at DESC,id DESC LIMIT %d',
				self::table_name(),
				$user_id,
				gmdate( 'Y-m-d H:i:s' ),
				$limit
			),
			ARRAY_A
		);
		return is_array( $rows ) ? array_map( array( $this, 'normalize_row' ), $rows ) : array();
	}


	/**
	 * Persist a controlled policy hold without storing policy payload details.
	 *
	 * @param array<string,mixed> $session Current owned session.
	 * @return array<string,mixed>
	 */
	public function apply_policy_error( array $session, WP_Error $error ): array {
		$code = is_callable( array( $error, 'get_error_code' ) ) ? (string) $error->get_error_code() : '';
		if ( 'supc_policy_violation' !== $code ) {
			return $session;
		}
		$data = is_callable( array( $error, 'get_error_data' ) ) ? $error->get_error_data( $code ) : null;
		$hold = is_array( $data ) && isset( $data['hold_state'] ) && is_string( $data['hold_state'] ) ? sanitize_key( $data['hold_state'] ) : 'security_hold';
		$codes = is_array( $data ) && isset( $data['codes'] ) && is_array( $data['codes'] ) ? $data['codes'] : array();
		$last = isset( $codes[0] ) && is_string( $codes[0] ) && Contract_Boundary::code( $codes[0] ) ? $codes[0] : 'policy_violation';
		$result = $this->update_hold_state(
			(string) $session['session_uuid'],
			(int) $session['user_id'],
			(int) $session['lock_version'],
			$hold,
			$last
		);
		return $result instanceof WP_Error ? $session : $result;
	}

	/** @return array<string,mixed>|WP_Error */
	public function update_hold_state( string $uuid, int $user_id, int $expected_lock_version, string $hold_state, ?string $last_error = null ): array|WP_Error {
		if ( ! in_array( $hold_state, self::HOLD_STATES, true ) || ( null !== $last_error && ! Contract_Boundary::code( $last_error ) ) ) {
			return $this->error( 'invalid_hold_state' );
		}
		$current = $this->get_owned( $uuid, $user_id );
		if ( $current instanceof WP_Error ) {
			return $current;
		}
		global $wpdb;
		$updated = is_object( $wpdb ) && method_exists( $wpdb, 'query' ) && method_exists( $wpdb, 'prepare' )
			? $wpdb->query(
				$wpdb->prepare(
					'UPDATE %i SET hold_state = %s, last_error = %s, lock_version = lock_version + 1, updated_at = %s WHERE session_uuid = %s AND user_id = %d AND lock_version = %d',
					self::table_name(),
					$hold_state,
					$last_error,
					gmdate( 'Y-m-d H:i:s' ),
					strtolower( $uuid ),
					$user_id,
					$expected_lock_version
				)
			)
			: false;
		return 1 === $updated ? $this->get_owned( $uuid, $user_id ) : $this->error( 'session_conflict', array( 'current' => $current ) );
	}

	/** @return array<string,mixed>|WP_Error */
	public function mark_abandoned( string $uuid, int $user_id, int $expected_lock_version ): array|WP_Error {
		$current = $this->get_owned( $uuid, $user_id );
		if ( $current instanceof WP_Error ) {
			return $current;
		}
		if ( ! empty( $current['reconciliation_required'] ) || in_array( (string) $current['state'], array( 'submitting', 'reconcile' ), true ) ) {
			return $this->error( 'reconciliation_pending' );
		}
		global $wpdb;
		$updated = is_object( $wpdb ) && method_exists( $wpdb, 'query' ) && method_exists( $wpdb, 'prepare' )
			? $wpdb->query(
				$wpdb->prepare(
					"UPDATE %i SET composer_state = 'abandoned', review_state = 'withdrawn', publication_state = 'unpublished', lock_version = lock_version + 1, updated_at = %s, expires_at = %s WHERE session_uuid = %s AND user_id = %d AND lock_version = %d",
					self::table_name(),
					gmdate( 'Y-m-d H:i:s' ),
					gmdate( 'Y-m-d H:i:s', time() + self::COMPLETED_TTL ),
					strtolower( $uuid ),
					$user_id,
					$expected_lock_version
				)
			)
			: false;
		return 1 === $updated ? $this->get_owned( $uuid, $user_id ) : $this->error( 'session_conflict', array( 'current' => $current ) );
	}

	public function delete_owned( string $uuid, int $user_id ): bool {
		if ( $user_id <= 0 || ! $this->valid_uuid( $uuid ) ) {
			return false;
		}
		global $wpdb;
		return is_object( $wpdb ) && method_exists( $wpdb, 'delete' ) && 1 === $wpdb->delete( self::table_name(), array( 'session_uuid' => strtolower( $uuid ), 'user_id' => $user_id ), array( '%s', '%d' ) );
	}

	public static function cleanup_expired(): int {
		global $wpdb;
		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'query' ) || ! method_exists( $wpdb, 'prepare' ) ) {
			return 0;
		}
		$now = gmdate( 'Y-m-d H:i:s' );
		if ( class_exists( Submission_Store::class, false ) && Submission_Store::tables_exist() ) {
			$deleted = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM %i WHERE expires_at < %s AND session_uuid NOT IN (SELECT session_uuid FROM %i WHERE status IN ('queued','retry','processing','dead_letter')) LIMIT 500",
					self::table_name(),
					$now,
					Submission_Store::outbox_table_name()
				)
			);
		} else {
			$deleted = $wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE expires_at < %s LIMIT 500', self::table_name(), $now ) );
		}
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

	private function ttl_for_sensitivity( string $sensitivity_class ): int {
		return 'sensitive' === $sensitivity_class ? self::SENSITIVE_TTL : self::ORDINARY_TTL;
	}

	private function transition_allows( string $current, string $target ): bool {
		return match ( $current ) {
			'published'         => in_array( $target, array( 'published', 'hidden', 'archived', 'deleted' ), true ),
			'hidden'            => in_array( $target, array( 'hidden', 'published', 'archived', 'deleted' ), true ),
			'archived'          => in_array( $target, array( 'archived', 'deleted' ), true ),
			'deleted'           => 'deleted' === $target,
			'scheduled'         => in_array( $target, array( 'scheduled', 'published', 'hidden', 'archived', 'deleted', 'rejected', 'failed' ), true ),
			'approved'          => in_array( $target, array( 'approved', 'scheduled', 'published', 'hidden', 'archived', 'rejected', 'failed' ), true ),
			'under_review'      => in_array( $target, array( 'under_review', 'changes_requested', 'approved', 'rejected', 'withdrawn', 'scheduled', 'published', 'failed' ), true ),
			'changes_requested' => in_array( $target, array( 'changes_requested', 'draft', 'submitted', 'under_review', 'withdrawn', 'failed' ), true ),
			'withdrawn'         => in_array( $target, array( 'withdrawn', 'draft', 'submitted' ), true ),
			'submitted'         => in_array( $target, array( 'submitted', 'under_review', 'changes_requested', 'approved', 'withdrawn', 'scheduled', 'published', 'rejected', 'failed' ), true ),
			'rejected'          => in_array( $target, array( 'rejected', 'draft', 'submitted' ), true ),
			'failed'            => in_array( $target, array( 'failed', 'draft', 'submitted' ), true ),
			'draft'             => in_array( $target, array( 'draft', 'submitted', 'under_review', 'changes_requested', 'approved', 'withdrawn', 'scheduled', 'published', 'rejected', 'failed' ), true ),
			'new', 'valid', 'submitting', 'reconcile' => true,
			default => false,
		};
	}

	private function session_state_for_native( string $native_status ): string {
		return match ( $native_status ) {
			'draft'             => 'draft',
			'pending_review'    => 'submitted',
			'under_review'      => 'under_review',
			'changes_requested' => 'changes_requested',
			'approved'          => 'approved',
			'withdrawn'         => 'withdrawn',
			'scheduled'         => 'scheduled',
			'published'         => 'published',
			'hidden'            => 'hidden',
			'archived'          => 'archived',
			'deleted'           => 'deleted',
			'rejected'          => 'rejected',
			'failed'            => 'failed',
			default             => '',
		};
	}


	/** @param array<string,mixed> $current @return array{composer_state:string,review_state:string,publication_state:string,hold_state:string} */
	private function dimensions_for_state( string $state, array $current ): array {
		$base = array(
			'composer_state'    => (string) ( $current['composer_state'] ?? 'new' ),
			'review_state'      => (string) ( $current['review_state'] ?? 'draft' ),
			'publication_state' => (string) ( $current['publication_state'] ?? 'unpublished' ),
			'hold_state'        => (string) ( $current['hold_state'] ?? 'clear' ),
		);
		return match ( $state ) {
			'new'        => array_merge( $base, array( 'composer_state' => 'new', 'review_state' => 'draft', 'publication_state' => 'unpublished' ) ),
			'draft', 'valid' => array_merge( $base, array( 'composer_state' => 'autosaved', 'review_state' => 'draft', 'publication_state' => 'unpublished', 'hold_state' => 'clear' ) ),
			'submitting', 'reconcile' => array_merge( $base, array( 'composer_state' => 'conflicted' ) ),
			'submitted'         => array_merge( $base, array( 'composer_state' => 'completed', 'review_state' => 'submitted', 'publication_state' => 'unpublished' ) ),
			'under_review'      => array_merge( $base, array( 'composer_state' => 'completed', 'review_state' => 'under_review', 'publication_state' => 'unpublished' ) ),
			'changes_requested' => array_merge( $base, array( 'composer_state' => 'editing', 'review_state' => 'changes_requested', 'publication_state' => 'unpublished' ) ),
			'approved'          => array_merge( $base, array( 'composer_state' => 'completed', 'review_state' => 'approved', 'publication_state' => 'unpublished' ) ),
			'withdrawn'         => array_merge( $base, array( 'composer_state' => 'completed', 'review_state' => 'withdrawn', 'publication_state' => 'unpublished' ) ),
			'scheduled'         => array_merge( $base, array( 'composer_state' => 'completed', 'review_state' => 'approved', 'publication_state' => 'scheduled' ) ),
			'published'         => array_merge( $base, array( 'composer_state' => 'completed', 'review_state' => 'approved', 'publication_state' => 'published' ) ),
			'hidden'            => array_merge( $base, array( 'composer_state' => 'completed', 'review_state' => 'approved', 'publication_state' => 'hidden' ) ),
			'archived'          => array_merge( $base, array( 'composer_state' => 'completed', 'review_state' => 'approved', 'publication_state' => 'archived' ) ),
			'deleted'           => array_merge( $base, array( 'composer_state' => 'completed', 'review_state' => 'approved', 'publication_state' => 'deleted' ) ),
			'rejected'          => array_merge( $base, array( 'composer_state' => 'completed', 'review_state' => 'rejected', 'publication_state' => 'unpublished' ) ),
			'failed'     => array_merge( $base, array( 'composer_state' => 'conflicted', 'publication_state' => 'unpublished' ) ),
			default      => $base,
		};
	}

	/** @param array<string,mixed> $current @return array{composer_state:string,review_state:string,publication_state:string,hold_state:string} */
	private function dimensions_for_native_status( string $native_status, array $current ): array {
		$dimensions = $this->dimensions_for_state( $this->session_state_for_native( $native_status ), $current );
		$dimensions['hold_state'] = 'clear';
		return $dimensions;
	}

	/** @param array<string,mixed> $row @return array<string,mixed> */
	private function normalize_row( array $row ): array {
		$state       = sanitize_key( (string) $row['state'] );
		$composer    = sanitize_key( (string) ( $row['composer_state'] ?? 'new' ) );
		$review      = sanitize_key( (string) ( $row['review_state'] ?? 'draft' ) );
		$publication = sanitize_key( (string) ( $row['publication_state'] ?? 'unpublished' ) );
		$hold        = sanitize_key( (string) ( $row['hold_state'] ?? 'clear' ) );
		$sensitivity = sanitize_key( (string) $row['sensitivity_class'] );
		return array(
			'session_uuid'           => strtolower( (string) $row['session_uuid'] ),
			'user_id'                => (int) $row['user_id'],
			'adapter_key'             => sanitize_key( (string) $row['adapter_key'] ),
			'adapter_version'         => (string) $row['adapter_version'],
			'native_reference'       => null === $row['native_reference'] ? null : (string) $row['native_reference'],
			'state'                   => in_array( $state, self::STATES, true ) ? $state : 'failed',
			'composer_state'          => in_array( $composer, self::COMPOSER_STATES, true ) ? $composer : 'conflicted',
			'review_state'            => in_array( $review, self::REVIEW_STATES, true ) ? $review : 'draft',
			'publication_state'       => in_array( $publication, self::PUBLICATION_STATES, true ) ? $publication : 'unpublished',
			'hold_state'              => in_array( $hold, self::HOLD_STATES, true ) ? $hold : 'security_hold',
			'sensitivity_class'       => in_array( $sensitivity, self::SENSITIVITY, true ) ? $sensitivity : 'sensitive',
			'lock_version'           => (int) $row['lock_version'],
			'idempotency_key'        => null === $row['idempotency_key'] || '' === (string) $row['idempotency_key'] ? null : (string) $row['idempotency_key'],
			'payload_hash'           => null === $row['payload_hash'] || '' === (string) $row['payload_hash'] ? null : strtolower( (string) $row['payload_hash'] ),
			'submit_attempts'        => (int) $row['submit_attempts'],
			'reconciliation_required' => 1 === (int) $row['reconciliation_required'],
			'last_error'             => null === $row['last_error'] ? null : sanitize_key( (string) $row['last_error'] ),
			'created_at'             => (string) $row['created_at'],
			'updated_at'             => (string) $row['updated_at'],
			'expires_at'             => (string) $row['expires_at'],
		);
	}

	private function error( string $code, mixed $data = null ): WP_Error {
		return new WP_Error( 'supc_' . $code, __( 'The Composer session request could not be completed.', 'sabri-universal-post-composer' ), $data );
	}
}
