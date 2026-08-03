<?php
/**
 * Request-time and background reconciliation for ambiguous native submissions.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

namespace Sabri\UniversalComposer\Core;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Reconciliation_Service {
	public function __construct(
		private Workflow_Coordinator $coordinator,
		private Session_Store $sessions,
		private Submission_Store $submissions
	) {
	}

	/** @return array{session:array<string,mixed>,native:array<string,mixed>,resolved:bool}|WP_Error */
	public function reconcile_session( string $session_uuid, int $user_id, string $event_uuid = '' ): array|WP_Error {
		$session = $this->sessions->get_owned( $session_uuid, $user_id );
		if ( $session instanceof WP_Error ) {
			$this->retry_event( $event_uuid, $this->error_code( $session ) );
			return $session;
		}
		$submission = $this->submissions->get_for_session( $session_uuid, $user_id );
		if ( $submission instanceof WP_Error ) {
			$this->retry_event( $event_uuid, $this->error_code( $submission ) );
			return $submission;
		}
		$native_reference = $submission['native_reference'] ?? null;
		if ( ! is_string( $native_reference ) || '' === $native_reference ) {
			$error = $this->error( 'reconciliation_native_reference_missing' );
			$this->retry_event( $event_uuid, 'native_reference_missing' );
			return $error;
		}
		if (
			! hash_equals( (string) $session['adapter_key'], (string) $submission['adapter_key'] ) ||
			! is_string( $session['native_reference'] ) ||
			! hash_equals( $session['native_reference'], $native_reference )
		) {
			$this->retry_event( $event_uuid, 'submission_identity_conflict' );
			return $this->error( 'submission_identity_conflict' );
		}
		$status = $this->coordinator->status(
			$user_id,
			(string) $submission['adapter_key'],
			$native_reference
		);
		if ( $status instanceof WP_Error ) {
			$code = $this->safe_error_code( $status );
			$this->submissions->mark_uncertain( (string) $submission['attempt_uuid'], $code, '' === $event_uuid );
			$this->retry_event( $event_uuid, $code );
			return $status;
		}
		$encoded = function_exists( 'wp_json_encode' ) ? wp_json_encode( $status ) : json_encode( $status );
		$hash    = is_string( $encoded ) ? hash( 'sha256', $encoded ) : '';
		if ( '' === $hash ) {
			$this->retry_event( $event_uuid, 'reconciliation_hash_failed' );
			return $this->error( 'reconciliation_hash_failed' );
		}
		if ( ! $this->submissions->mark_reconciled( (string) $submission['attempt_uuid'], (string) $status['status'], $hash ) ) {
			$this->retry_event( $event_uuid, 'submission_reconcile_record_failed' );
			return $this->error( 'submission_reconcile_record_failed' );
		}
		$updated = $this->sessions->apply_reconciliation(
			$session_uuid,
			$user_id,
			(int) $session['lock_version'],
			(string) $status['status'],
			$native_reference,
			(string) $submission['idempotency_key'],
			(string) $submission['payload_hash']
		);
		if ( $updated instanceof WP_Error ) {
			$code = $this->safe_error_code( $updated );
			$this->retry_event( $event_uuid, $code );
			return $updated;
		}
		if ( ! $this->submissions->complete_reconciliation( (string) $submission['attempt_uuid'] ) ) {
			$this->retry_event( $event_uuid, 'outbox_completion_failed' );
			return $this->error( 'outbox_completion_failed' );
		}
		return array(
			'session'  => $updated,
			'native'   => $status,
			'resolved' => 'draft' !== (string) $status['status'],
		);
	}

	public function process_due(): int {
		if ( Safe_Mode::disabled() || ! Submission_Store::tables_exist() || ! Session_Store::table_exists() ) {
			return 0;
		}
		$this->submissions->enqueue_stale_submissions();
		$processed = 0;
		foreach ( $this->submissions->due_reconciliations( 20 ) as $event ) {
			$event_uuid = (string) $event['event_uuid'];
			if ( ! $this->submissions->claim_reconciliation( $event_uuid ) ) {
				continue;
			}
			$this->reconcile_session( (string) $event['session_uuid'], (int) $event['user_id'], $event_uuid );
			++$processed;
		}
		return $processed;
	}

	private function retry_event( string $event_uuid, string $error_code ): void {
		if ( '' !== $event_uuid ) {
			$this->submissions->retry_reconciliation( $event_uuid, $this->safe_code( $error_code ) );
		}
	}

	private function safe_error_code( WP_Error $error ): string {
		return $this->safe_code( $this->error_code( $error ) );
	}

	private function safe_code( string $code ): string {
		$code = sanitize_key( str_replace( 'supc_', '', $code ) );
		return Contract_Boundary::code( $code ) ? $code : 'reconciliation_failed';
	}

	private function error_code( WP_Error $error ): string {
		if ( is_callable( array( $error, 'get_error_code' ) ) ) {
			return (string) call_user_func( array( $error, 'get_error_code' ) );
		}
		$properties = get_object_vars( $error );
		return isset( $properties['code'] ) ? (string) $properties['code'] : '';
	}

	private function error( string $code ): WP_Error {
		return new WP_Error( 'supc_' . sanitize_key( $code ), __( 'The Composer could not reconcile the native submission yet.', 'sabri-universal-post-composer' ) );
	}
}
