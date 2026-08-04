<?php
/**
 * Bounded File 22 readiness provider for File 23.
 *
 * File 22 remains the sole create/edit/draft/preview/submit surface. This
 * adapter exposes readiness metadata only; it never transfers draft ownership
 * or permits File 23 to mutate Composer sessions directly.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

namespace Sabri\UniversalComposer\Integration;

use Sabri\UniversalComposer\Core\Page_Resolver;
use Sabri\UniversalComposer\Core\Safe_Mode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class File23_Dashboard_Adapter_Runtime implements \SPDB_Provider_Adapter {
	private const PROVIDER_KEY = 'sabri_universal_post_composer';
	private const CONTRACT     = '2.0.0';

	public function get_provider_key(): string {
		return self::PROVIDER_KEY;
	}

	public function get_provider_name(): string {
		return 'Sabri Universal Post Composer';
	}

	public function get_provider_version(): string {
		return defined( 'SUPC_VERSION' ) && preg_match( '/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', (string) SUPC_VERSION )
			? (string) SUPC_VERSION
			: '0.3.1';
	}

	public function get_minimum_contract_version(): string {
		return self::CONTRACT;
	}

	public function get_maximum_contract_version(): string {
		return self::CONTRACT;
	}

	public function get_declared_capability_state(): string {
		return 'write_capable';
	}

	public function get_object_types(): array {
		return array( 'composer_gateway' );
	}

	public function get_privacy_classifications(): array {
		return array( 'restricted' );
	}

	public function get_supported_capabilities(): array {
		return array( 'spdb_manage_own_content' );
	}

	/** File 23 receives no direct Composer mutation operation. */
	public function get_operation_definitions(): array {
		return array();
	}

	public function health_check(): array {
		$ready = Page_Resolver::is_ready() && ! Safe_Mode::disabled();
		return array(
			'status'               => $ready ? 'healthy' : 'unavailable',
			'composer_page_ready'  => Page_Resolver::is_ready(),
			'safe_mode_disabled'   => Safe_Mode::disabled(),
			'adapter_api_version'  => defined( 'SUPC_ADAPTER_API_VERSION' ) ? (string) SUPC_ADAPTER_API_VERSION : '',
			'workflow_api_version' => defined( 'SUPC_WORKFLOW_API_VERSION' ) ? (string) SUPC_WORKFLOW_API_VERSION : '',
			'create_url_available' => '' !== Page_Resolver::url(),
		);
	}

	/** Composer sessions are not inventory records and are not projected. */
	public function list_items( array $query ) {
		unset( $query );
		return array( 'items' => array(), 'total' => 0, 'has_more' => false );
	}

	public function get_item( string $object_type, string $object_id ) {
		unset( $object_type, $object_id );
		return new \WP_Error( 'supc_spdb_object_not_projected', __( 'File 22 Composer sessions are not projected as dashboard content objects.', 'sabri-universal-post-composer' ) );
	}

	public function get_allowed_operations( string $object_type, string $object_id ): array {
		unset( $object_type, $object_id );
		return array();
	}

	public function execute_operation( string $operation_key, string $object_type, string $object_id, array $payload ) {
		unset( $operation_key, $object_type, $object_id, $payload );
		return new \WP_Error( 'supc_spdb_direct_write_forbidden', __( 'Open the canonical File 22 Composer to create or edit content.', 'sabri-universal-post-composer' ), array( 'status' => 409 ) );
	}
}
