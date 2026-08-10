<?php
/**
 * File 22 plan-completion runtime: stores, cleanup, diagnostics and contracts.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

namespace Sabri\UniversalComposer\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plan_Completion_Runtime {
	public function boot(): void {
		add_action( 'supc_cleanup_plan_metadata', array( $this, 'cleanup' ) );
		add_filter( 'supc_system_check_report', array( $this, 'append_system_check' ), 85 );
		add_action( 'supc_workflow_projection', array( $this, 'emit_projection' ), 10, 2 );
		add_filter( 'rest_request_after_callbacks', array( $this, 'audit_rest_response' ), 20, 3 );
	}

	public function audit_rest_response( mixed $response, mixed $handler, mixed $request ): mixed {
		unset( $handler );
		if ( ! is_object( $request ) || ! method_exists( $request, 'get_route' ) || ! method_exists( $request, 'get_method' ) ) {
			return $response;
		}
		$route = (string) $request->get_route();
		if ( ! str_starts_with( $route, '/sabri-composer/v1/' ) ) {
			return $response;
		}
		$method = strtoupper( (string) $request->get_method() );
		if ( str_contains( $route, '/revision' ) || str_contains( $route, '/uploads' ) || ( in_array( $method, array( 'PATCH', 'DELETE' ), true ) && preg_match( '#/sessions/[0-9a-f-]{36}$#', $route ) ) ) {
			return $response;
		}
		$event = $this->rest_event( $route, $method );
		if ( '' === $event ) {
			return $response;
		}
		$data    = is_object( $response ) && method_exists( $response, 'get_data' ) ? $response->get_data() : null;
		$success = ! $response instanceof \WP_Error;
		$session = is_array( $data ) && isset( $data['session'] ) && is_array( $data['session'] ) ? $data['session'] : array();
		$adapter = isset( $session['adapter_key'] ) && is_string( $session['adapter_key'] ) ? sanitize_key( $session['adapter_key'] ) : '';
		if ( '' === $adapter && is_array( $data ) && isset( $data['upload']['adapter_key'] ) && is_string( $data['upload']['adapter_key'] ) ) {
			$adapter = sanitize_key( $data['upload']['adapter_key'] );
		}
		if ( ! Contract_Boundary::adapter_key( $adapter ) ) {
			return $response;
		}
		$session_uuid = isset( $session['session_uuid'] ) && is_string( $session['session_uuid'] ) ? $session['session_uuid'] : null;
		$native       = isset( $session['native_reference'] ) && is_string( $session['native_reference'] ) ? $session['native_reference'] : null;
		$error_code   = $response instanceof \WP_Error && is_callable( array( $response, 'get_error_code' ) ) ? (string) $response->get_error_code() : null;
		( new Audit_Store() )->record( get_current_user_id(), $adapter, $event, $success ? 'success' : 'failed', $session_uuid, $native, $error_code );
		if ( $success && array() !== $session ) {
			( new Projection_Bus() )->emit(
				$event,
				array(
					'session_uuid'          => (string) ( $session['session_uuid'] ?? '' ),
					'adapter_key'           => $adapter,
					'review_state'          => (string) ( $session['review_state'] ?? 'draft' ),
					'publication_state'     => (string) ( $session['publication_state'] ?? 'unpublished' ),
					'hold_state'            => (string) ( $session['hold_state'] ?? 'clear' ),
					'native_reference_hash' => null === $native ? '' : hash( 'sha256', $native ),
				)
			);
		}
		return $response;
	}

	public function cleanup(): void {
		Audit_Store::cleanup_expired();
		Upload_Token_Store::cleanup_expired();
	}

	/** @param array<string,mixed> $metadata */
	public function emit_projection( string $event, array $metadata ): void {
		( new Projection_Bus() )->emit( $event, $metadata );
	}

	/** @param array<int,array<string,mixed>> $rows @return array<int,array<string,mixed>> */
	public function append_system_check( array $rows ): array {
		$rows[] = $this->row( 'audit_store', Audit_Store::table_exists(), 'audit_store_missing' );
		$rows[] = $this->row( 'upload_token_store', Upload_Token_Store::table_exists(), 'upload_token_store_missing' );
		$rows[] = array(
			'key'    => 'taxonomy_map',
			'status' => Version::valid( Taxonomy_Map::VERSION ) ? 'pass' : 'fail',
			'count'  => Version::valid( Taxonomy_Map::VERSION ) ? 0 : 1,
			'codes'  => Version::valid( Taxonomy_Map::VERSION ) ? array() : array( 'taxonomy_map_invalid' ),
		);
		$cron   = function_exists( 'wp_next_scheduled' ) && false !== wp_next_scheduled( 'supc_cleanup_plan_metadata' );
		$rows[] = $this->row( 'plan_metadata_cleanup', $cron, 'plan_metadata_cleanup_missing' );
		$workspace = Workspace_Page_Resolver::inspect();
		$rows[] = array(
			'key'    => 'workspace_page',
			'status' => 'ready' === $workspace['status'] ? 'pass' : ( 'repairable' === $workspace['status'] ? 'warning' : 'fail' ),
			'count'  => 'ready' === $workspace['status'] ? 0 : 1,
			'codes'  => 'ready' === $workspace['status'] ? array() : array( 'workspace_page_' . sanitize_key( (string) $workspace['status'] ) ),
		);
		$plan_contract = defined( 'SUPC_PLAN_CONTRACT_VERSION' ) && Version::valid( SUPC_PLAN_CONTRACT_VERSION );
		$rows[] = $this->row( 'plan_contract', $plan_contract, 'plan_contract_invalid' );
		$feature = Migration_Manager::writes_enabled();
		$rows[] = array(
			'key'    => 'composer_feature_flag',
			'status' => $feature ? 'pass' : 'warning',
			'count'  => $feature ? 0 : 1,
			'codes'  => $feature ? array() : array( 'composer_feature_disabled' ),
		);

		// Optional adapter packs are independently certified. Their absence warns
		// but never disables Core. Keys must match the governing registry/catalog
		// exactly so a correctly registered native provider is never reported absent.
		foreach ( array( 'learning_lesson', 'encyclopedia_entry', 'video', 'reel', 'pdf_document', 'marketplace_listing' ) as $key ) {
			$present = null !== Plugin::instance()->registry()->get( $key );
			$rows[]  = array(
				'key'    => 'adapter_pack_' . $key,
				'status' => $present ? 'pass' : 'warning',
				'count'  => $present ? 0 : 1,
				'codes'  => $present ? array() : array( 'optional_adapter_pack_absent' ),
			);
		}
		return $rows;
	}

	private function rest_event( string $route, string $method ): string {
		if ( str_contains( $route, '/reconcile' ) ) {
			return 'submission_resolved';
		}
		if ( str_contains( $route, '/submit' ) ) {
			return 'submission_pending';
		}
		if ( str_contains( $route, '/revision' ) ) {
			return 'revision_submitted';
		}
		if ( str_contains( $route, '/preview' ) ) {
			return 'preview_ready';
		}
		if ( str_contains( $route, '/validate' ) ) {
			return 'validation_completed';
		}
		if ( str_contains( $route, '/uploads' ) && str_contains( $route, '/complete' ) ) {
			return 'upload_completed';
		}
		if ( str_contains( $route, '/uploads' ) && 'DELETE' === $method ) {
			return 'upload_cancelled';
		}
		if ( str_contains( $route, '/uploads' ) ) {
			return 'upload_issued';
		}
		if ( str_contains( $route, '/autosave' ) || ( 'PATCH' === $method && preg_match( '#/sessions/[0-9a-f-]{36}$#', $route ) ) ) {
			return 'draft_saved';
		}
		if ( 'DELETE' === $method && preg_match( '#/sessions/[0-9a-f-]{36}$#', $route ) ) {
			return 'draft_discarded';
		}
		if ( 'POST' === $method && '/sabri-composer/v1/sessions' === $route ) {
			return 'session_created';
		}
		return '';
	}

	/** @return array<string,mixed> */
	private function row( string $key, bool $pass, string $code ): array {
		return array(
			'key'    => $key,
			'status' => $pass ? 'pass' : 'fail',
			'count'  => $pass ? 0 : 1,
			'codes'  => $pass ? array() : array( $code ),
		);
	}
}
