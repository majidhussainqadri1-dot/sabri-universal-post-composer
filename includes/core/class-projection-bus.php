<?php
/**
 * Metadata-only projection/event boundary for companion modules.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

namespace Sabri\UniversalComposer\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Projection_Bus {
	private const EVENTS = array(
		'session_created', 'draft_saved', 'validation_completed', 'preview_ready',
		'submission_pending', 'submission_resolved', 'revision_submitted',
		'draft_discarded', 'upload_issued', 'upload_completed', 'upload_cancelled',
	);

	/** @param array<string,mixed> $metadata */
	public function emit( string $event, array $metadata ): bool {
		if ( ! in_array( $event, self::EVENTS, true ) ) {
			return false;
		}
		$bounded = array();
		foreach ( array( 'session_uuid', 'adapter_key', 'native_status', 'publication_state', 'review_state', 'hold_state', 'native_reference_hash' ) as $key ) {
			if ( isset( $metadata[ $key ] ) && is_scalar( $metadata[ $key ] ) ) {
				$value = (string) $metadata[ $key ];
				if ( strlen( $value ) <= 128 ) {
					$bounded[ $key ] = $value;
				}
			}
		}
		$bounded['event']      = $event;
		$bounded['occurred_at'] = gmdate( 'c' );

		// Companion owners consume these events; File 22 never delivers alerts,
		// indexes content, or writes timeline/dashboard records itself.
		do_action( 'supc_projection_event', $event, $bounded );
		do_action( 'supc_file23_projection_event', $event, $bounded );
		do_action( 'supc_file24_assurance_event', $event, $bounded );
		do_action( 'supc_file25_timeline_event', $event, $bounded );
		do_action( 'supc_file19_notification_event', $event, $bounded );
		do_action( 'supc_search_seo_event', $event, $bounded );
		return true;
	}
}
