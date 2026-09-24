<?php
/**
 * WordPress personal-data export and erasure integration for File 22.
 *
 * File 22 owns orchestration metadata only. Native content, media bytes,
 * consent evidence, moderation records, and permanent publication records
 * remain with their canonical owners and are therefore not erased here.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

namespace Sabri\UniversalComposer\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Privacy_Integration {
	private const EXPORT_PAGE_SIZE = 50;
	private const ERASE_BATCH_SIZE = 500;

	public function register(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
	}

	/** @param array<string,array<string,mixed>> $exporters @return array<string,array<string,mixed>> */
	public function register_exporter( array $exporters ): array {
		$exporters['sabri-universal-post-composer'] = array(
			'exporter_friendly_name' => __( 'Sabri Universal Post Composer', 'sabri-universal-post-composer' ),
			'callback'               => array( self::class, 'export_personal_data' ),
		);
		return $exporters;
	}

	/** @param array<string,array<string,mixed>> $erasers @return array<string,array<string,mixed>> */
	public function register_eraser( array $erasers ): array {
		$erasers['sabri-universal-post-composer'] = array(
			'eraser_friendly_name' => __( 'Sabri Universal Post Composer', 'sabri-universal-post-composer' ),
			'callback'             => array( self::class, 'erase_personal_data' ),
		);
		return $erasers;
	}

	/** @return array{data:array<int,array<string,mixed>>,done:bool} */
	public static function export_personal_data( string $email_address, int $page = 1 ): array {
		$user = function_exists( 'get_user_by' ) ? get_user_by( 'email', $email_address ) : false;
		$user_id = is_object( $user ) && isset( $user->ID ) ? (int) $user->ID : 0;
		if ( $user_id <= 0 ) {
			return array( 'data' => array(), 'done' => true );
		}

		$page   = max( 1, $page );
		$offset = ( $page - 1 ) * self::EXPORT_PAGE_SIZE;
		$data   = array();
		$done   = true;

		$sets = self::export_sets( $user_id, $offset, self::EXPORT_PAGE_SIZE );
		foreach ( $sets as $set ) {
			if ( count( $set['rows'] ) >= self::EXPORT_PAGE_SIZE ) {
				$done = false;
			}
			foreach ( $set['rows'] as $row ) {
				$data[] = self::export_item( $set['type'], $row );
			}
		}
		return array( 'data' => $data, 'done' => $done );
	}

	/**
	 * Erase File 22-owned personal orchestration metadata.
	 *
	 * Active/retry/dead-letter reconciliation records are temporarily retained
	 * so an uncertain native write is not duplicated or abandoned. Audit rows
	 * are de-identified while their security/operational facts remain.
	 *
	 * @return array{items_removed:bool,items_retained:bool,messages:array<int,string>,done:bool}
	 */
	public static function erase_personal_data( string $email_address, int $page = 1 ): array {
		unset( $page );
		$user = function_exists( 'get_user_by' ) ? get_user_by( 'email', $email_address ) : false;
		$user_id = is_object( $user ) && isset( $user->ID ) ? (int) $user->ID : 0;
		if ( $user_id <= 0 ) {
			return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true );
		}

		global $wpdb;
		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'query' ) || ! method_exists( $wpdb, 'prepare' ) ) {
			return array(
				'items_removed'  => false,
				'items_retained' => true,
				'messages'       => array( __( 'File 22 privacy erasure could not reach its metadata store.', 'sabri-universal-post-composer' ) ),
				'done'           => true,
			);
		}

		$removed = 0;
		if ( Upload_Token_Store::table_exists() ) {
			$result = $wpdb->query(
				$wpdb->prepare(
					'DELETE FROM %i WHERE user_id = %d LIMIT %d',
					Upload_Token_Store::table_name(),
					$user_id,
					self::ERASE_BATCH_SIZE
				)
			);
			$removed += is_int( $result ) && $result > 0 ? $result : 0;
		}

		if ( Submission_Store::tables_exist() ) {
			$outbox = Submission_Store::outbox_table_name();
			$submissions = Submission_Store::submission_table_name();

			$result = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM %i WHERE user_id = %d AND status = 'completed' LIMIT %d",
					$outbox,
					$user_id,
					self::ERASE_BATCH_SIZE
				)
			);
			$removed += is_int( $result ) && $result > 0 ? $result : 0;

			$result = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM %i WHERE user_id = %d AND attempt_uuid NOT IN (SELECT attempt_uuid FROM %i WHERE user_id = %d AND status IN ('queued','retry','processing','dead_letter')) LIMIT %d",
					$submissions,
					$user_id,
					$outbox,
					$user_id,
					self::ERASE_BATCH_SIZE
				)
			);
			$removed += is_int( $result ) && $result > 0 ? $result : 0;
		}

		if ( Session_Store::table_exists() ) {
			if ( Submission_Store::tables_exist() ) {
				$result = $wpdb->query(
					$wpdb->prepare(
						"DELETE FROM %i WHERE user_id = %d AND session_uuid NOT IN (SELECT session_uuid FROM %i WHERE user_id = %d AND status IN ('queued','retry','processing','dead_letter')) LIMIT %d",
						Session_Store::table_name(),
						$user_id,
						Submission_Store::outbox_table_name(),
						$user_id,
						self::ERASE_BATCH_SIZE
					)
				);
			} else {
				$result = $wpdb->query(
					$wpdb->prepare(
						'DELETE FROM %i WHERE user_id = %d LIMIT %d',
						Session_Store::table_name(),
						$user_id,
						self::ERASE_BATCH_SIZE
					)
				);
			}
			$removed += is_int( $result ) && $result > 0 ? $result : 0;
		}

		if ( Audit_Store::table_exists() ) {
			$result = $wpdb->query(
				$wpdb->prepare(
					'UPDATE %i SET user_id = 0, session_uuid = NULL, native_reference_hash = NULL, correlation_hash = NULL WHERE user_id = %d LIMIT %d',
					Audit_Store::table_name(),
					$user_id,
					self::ERASE_BATCH_SIZE
				)
			);
			$removed += is_int( $result ) && $result > 0 ? $result : 0;
		}

		$active_retained = self::active_reconciliation_count( $user_id );
		$more_erasable   = self::erasable_count( $user_id );
		$messages        = array();
		if ( $active_retained > 0 ) {
			$messages[] = __( 'Some File 22 orchestration metadata is temporarily retained because a native submission is queued, reconciling, or held for dead-letter investigation. It expires under the operational retention policy and does not include the draft body.', 'sabri-universal-post-composer' );
		}

		return array(
			'items_removed'  => $removed > 0,
			'items_retained' => $active_retained > 0,
			'messages'       => $messages,
			'done'           => 0 === $more_erasable,
		);
	}

	/** @return array<int,array{type:string,rows:array<int,array<string,mixed>>}> */
	private static function export_sets( int $user_id, int $offset, int $limit ): array {
		global $wpdb;
		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_results' ) || ! method_exists( $wpdb, 'prepare' ) ) {
			return array();
		}
		$sets = array();

		if ( Session_Store::table_exists() ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT session_uuid,adapter_key,state,composer_state,review_state,publication_state,hold_state,sensitivity_class,created_at,updated_at,expires_at FROM %i WHERE user_id = %d ORDER BY id ASC LIMIT %d OFFSET %d',
					Session_Store::table_name(),
					$user_id,
					$limit,
					$offset
				),
				ARRAY_A
			);
			$sets[] = array( 'type' => 'session', 'rows' => is_array( $rows ) ? $rows : array() );
		}
		if ( Submission_Store::tables_exist() ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT attempt_uuid,adapter_key,state,native_status,attempts,last_error,created_at,updated_at,completed_at FROM %i WHERE user_id = %d ORDER BY id ASC LIMIT %d OFFSET %d',
					Submission_Store::submission_table_name(),
					$user_id,
					$limit,
					$offset
				),
				ARRAY_A
			);
			$sets[] = array( 'type' => 'submission', 'rows' => is_array( $rows ) ? $rows : array() );

			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT event_uuid,adapter_key,topic,status,attempts,created_at,updated_at,processed_at FROM %i WHERE user_id = %d ORDER BY id ASC LIMIT %d OFFSET %d',
					Submission_Store::outbox_table_name(),
					$user_id,
					$limit,
					$offset
				),
				ARRAY_A
			);
			$sets[] = array( 'type' => 'outbox', 'rows' => is_array( $rows ) ? $rows : array() );
		}
		if ( Upload_Token_Store::table_exists() ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT upload_uuid,adapter_key,purpose,mime_type,size_bytes,status,created_at,updated_at,expires_at FROM %i WHERE user_id = %d ORDER BY id ASC LIMIT %d OFFSET %d',
					Upload_Token_Store::table_name(),
					$user_id,
					$limit,
					$offset
				),
				ARRAY_A
			);
			$sets[] = array( 'type' => 'upload', 'rows' => is_array( $rows ) ? $rows : array() );
		}
		if ( Audit_Store::table_exists() ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT event_uuid,adapter_key,event_code,outcome,created_at FROM %i WHERE user_id = %d ORDER BY id ASC LIMIT %d OFFSET %d',
					Audit_Store::table_name(),
					$user_id,
					$limit,
					$offset
				),
				ARRAY_A
			);
			$sets[] = array( 'type' => 'audit', 'rows' => is_array( $rows ) ? $rows : array() );
		}
		return $sets;
	}

	/** @param array<string,mixed> $row @return array<string,mixed> */
	private static function export_item( string $type, array $row ): array {
		$identifiers = array(
			'session'    => 'session_uuid',
			'submission' => 'attempt_uuid',
			'outbox'     => 'event_uuid',
			'upload'     => 'upload_uuid',
			'audit'      => 'event_uuid',
		);
		$id_key = $identifiers[ $type ] ?? '';
		$item_id = $id_key && isset( $row[ $id_key ] ) ? sanitize_text_field( (string) $row[ $id_key ] ) : wp_generate_uuid4();
		$labels = array(
			'adapter_key'       => __( 'Native workflow adapter', 'sabri-universal-post-composer' ),
			'state'             => __( 'Orchestration state', 'sabri-universal-post-composer' ),
			'composer_state'    => __( 'Composer state', 'sabri-universal-post-composer' ),
			'review_state'      => __( 'Review state', 'sabri-universal-post-composer' ),
			'publication_state' => __( 'Publication state', 'sabri-universal-post-composer' ),
			'hold_state'        => __( 'Safety/hold state', 'sabri-universal-post-composer' ),
			'sensitivity_class' => __( 'Privacy class', 'sabri-universal-post-composer' ),
			'native_status'     => __( 'Native status', 'sabri-universal-post-composer' ),
			'attempts'          => __( 'Attempts', 'sabri-universal-post-composer' ),
			'last_error'        => __( 'Last bounded error code', 'sabri-universal-post-composer' ),
			'topic'             => __( 'Reconciliation topic', 'sabri-universal-post-composer' ),
			'status'            => __( 'Status', 'sabri-universal-post-composer' ),
			'purpose'           => __( 'Upload purpose', 'sabri-universal-post-composer' ),
			'mime_type'         => __( 'Declared media type', 'sabri-universal-post-composer' ),
			'size_bytes'        => __( 'Declared size in bytes', 'sabri-universal-post-composer' ),
			'event_code'        => __( 'Audit event', 'sabri-universal-post-composer' ),
			'outcome'           => __( 'Audit outcome', 'sabri-universal-post-composer' ),
			'created_at'        => __( 'Created at (UTC)', 'sabri-universal-post-composer' ),
			'updated_at'        => __( 'Updated at (UTC)', 'sabri-universal-post-composer' ),
			'completed_at'      => __( 'Completed at (UTC)', 'sabri-universal-post-composer' ),
			'processed_at'      => __( 'Processed at (UTC)', 'sabri-universal-post-composer' ),
			'expires_at'        => __( 'Expires at (UTC)', 'sabri-universal-post-composer' ),
		);
		$values = array();
		foreach ( $labels as $key => $label ) {
			if ( ! array_key_exists( $key, $row ) || null === $row[ $key ] || '' === (string) $row[ $key ] ) {
				continue;
			}
			$values[] = array( 'name' => $label, 'value' => sanitize_text_field( (string) $row[ $key ] ) );
		}
		return array(
			'group_id'    => 'sabri-universal-post-composer',
			'group_label' => __( 'Sabri Universal Post Composer', 'sabri-universal-post-composer' ),
			'item_id'     => $type . '-' . $item_id,
			'data'        => $values,
		);
	}

	private static function active_reconciliation_count( int $user_id ): int {
		if ( ! Submission_Store::tables_exist() ) {
			return 0;
		}
		global $wpdb;
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i WHERE user_id = %d AND status IN ('queued','retry','processing','dead_letter')",
				Submission_Store::outbox_table_name(),
				$user_id
			)
		);
		return max( 0, (int) $count );
	}

	private static function erasable_count( int $user_id ): int {
		global $wpdb;
		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_var' ) || ! method_exists( $wpdb, 'prepare' ) ) {
			return 0;
		}
		$count = 0;
		if ( Upload_Token_Store::table_exists() ) {
			$count += (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE user_id = %d', Upload_Token_Store::table_name(), $user_id ) );
		}
		if ( Audit_Store::table_exists() ) {
			$count += (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE user_id = %d', Audit_Store::table_name(), $user_id ) );
		}
		if ( Submission_Store::tables_exist() ) {
			$count += (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM %i WHERE user_id = %d AND status = 'completed'",
					Submission_Store::outbox_table_name(),
					$user_id
				)
			);
			$count += (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM %i WHERE user_id = %d AND attempt_uuid NOT IN (SELECT attempt_uuid FROM %i WHERE user_id = %d AND status IN ('queued','retry','processing','dead_letter'))",
					Submission_Store::submission_table_name(),
					$user_id,
					Submission_Store::outbox_table_name(),
					$user_id
				)
			);
		}
		if ( Session_Store::table_exists() ) {
			if ( Submission_Store::tables_exist() ) {
				$count += (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM %i WHERE user_id = %d AND session_uuid NOT IN (SELECT session_uuid FROM %i WHERE user_id = %d AND status IN ('queued','retry','processing','dead_letter'))",
						Session_Store::table_name(),
						$user_id,
						Submission_Store::outbox_table_name(),
						$user_id
					)
				);
			} else {
				$count += (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE user_id = %d', Session_Store::table_name(), $user_id ) );
			}
		}
		return max( 0, $count );
	}
}
