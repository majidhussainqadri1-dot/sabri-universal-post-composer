<?php
/**
 * File 19 Unified Notifications contract bridge.
 *
 * File 22 registers only orchestration-specific publishing facts. File 21 and
 * other native owners remain responsible for canonical publication/social
 * events. Notification storage, policy, preferences and delivery stay in File
 * 19.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

namespace Sabri\UniversalComposer\Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class File19_Notification_Bridge {
	private const PRODUCER = 'sabri-file22-composer';
	private const OWNER    = 'File 22';
	private const SCHEMA   = '1.0.0';

	/** @var array<string,string> */
	private const EVENT_TYPES = array(
		'submission_pending'  => 'Publishing.ComposerSubmissionPending',
		'submission_resolved' => 'Publishing.ComposerSubmissionResolved',
		'revision_submitted'  => 'Publishing.ComposerRevisionSubmitted',
	);

	private bool $registered = false;

	public function register(): void {
		add_action( 'init', array( $this, 'register_producer' ), 19 );
		add_action( 'supc_file19_notification_event', array( $this, 'ingest_projection' ), 10, 2 );
		add_filter( 'supc_system_check_report', array( $this, 'append_system_check' ), 71, 1 );
	}

	public function register_producer(): bool {
		if ( $this->registered ) {
			return true;
		}
		if ( ! function_exists( 'sun_register_notification_producer' ) || ! function_exists( 'sun_ingest_domain_event' ) ) {
			return false;
		}
		$this->registered = (bool) sun_register_notification_producer(
			self::PRODUCER,
			array(
				'owner'               => self::OWNER,
				'event_types'         => array_values( self::EVENT_TYPES ),
				'schema_versions'     => array( self::SCHEMA ),
				'allowed_data_fields' => array( 'action_name', 'summary', 'status' ),
			)
		);
		return $this->registered;
	}

	/**
	 * @param mixed $metadata Privacy-minimized File 22 projection metadata.
	 */
	public function ingest_projection( string $event, mixed $metadata ): void {
		if ( ! isset( self::EVENT_TYPES[ $event ] ) || ! is_array( $metadata ) || ! $this->register_producer() ) {
			return;
		}

		$recipient = isset( $metadata['recipient_user_id'] ) ? absint( $metadata['recipient_user_id'] ) : 0;
		$session   = isset( $metadata['session_uuid'] ) && is_string( $metadata['session_uuid'] ) ? strtolower( $metadata['session_uuid'] ) : '';
		$adapter   = isset( $metadata['adapter_key'] ) && is_string( $metadata['adapter_key'] ) ? sanitize_key( $metadata['adapter_key'] ) : '';
		if ( $recipient <= 0 || '' === $session || '' === $adapter ) {
			return;
		}

		$status = '';
		foreach ( array( 'native_status', 'publication_state', 'review_state' ) as $status_key ) {
			if ( isset( $metadata[ $status_key ] ) && is_scalar( $metadata[ $status_key ] ) && '' !== (string) $metadata[ $status_key ] ) {
				$status = sanitize_key( (string) $metadata[ $status_key ] );
				break;
			}
		}

		$event_id = 'supc:' . sanitize_key( $event ) . ':' . hash(
			'sha256',
			implode( '|', array( $session, $adapter, $status, (string) $recipient ) )
		);
		$occurred_at = isset( $metadata['occurred_at'] ) && is_string( $metadata['occurred_at'] )
			? $metadata['occurred_at']
			: gmdate( 'c' );

		$envelope = array(
			'producer'       => self::PRODUCER,
			'owner'          => self::OWNER,
			'event_id'       => $event_id,
			'event_type'     => self::EVENT_TYPES[ $event ],
			'schema_version' => self::SCHEMA,
			'occurred_at'    => $occurred_at,
			'recipients'     => array( array( 'user_id' => $recipient ) ),
			'trace_id'       => $event_id,
			'category'       => 'publishing',
			'priority'       => 'normal',
			'sensitivity'    => 'standard',
			'data'           => array(
				'action_name' => str_replace( '_', ' ', sanitize_key( $event ) ),
				'summary'     => 'Composer workflow status changed.',
				'status'      => $status,
			),
			'source_version'  => defined( 'SUPC_VERSION' ) ? (string) SUPC_VERSION : '',
			'idempotency_key' => $event_id,
		);

		$result = sun_ingest_domain_event( $envelope );
		if ( function_exists( 'is_wp_error' ) && is_wp_error( $result ) ) {
			do_action( 'supc_file19_notification_ingest_failed', 'file19_ingest_rejected' );
		}
	}

	/** @param array<int,array<string,mixed>> $rows @return array<int,array<string,mixed>> */
	public function append_system_check( array $rows ): array {
		$register = function_exists( 'sun_register_notification_producer' );
		$ingest   = function_exists( 'sun_ingest_domain_event' );
		$partial  = $register xor $ingest;
		$available = $register && $ingest;
		$producer_ready = $available ? $this->register_producer() : false;
		$status = $partial || ( $available && ! $producer_ready ) ? 'fail' : ( $producer_ready ? 'pass' : 'warning' );
		$code = $partial
			? 'file19_notification_contract_partial'
			: ( $available && ! $producer_ready ? 'file19_notification_producer_registration_failed' : 'file19_notification_optional_unavailable' );
		$rows[] = array(
			'key'    => 'file19_notification_contract',
			'status' => $status,
			'count'  => 'pass' === $status ? 0 : 1,
			'codes'  => 'pass' === $status ? array() : array( $code ),
		);
		return $rows;
	}
}