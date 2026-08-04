<?php
/**
 * File 23 Publishing Dashboard bridge for the canonical File 22 Composer.
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

final class File23_Dashboard_Bridge {
	/** Register URL discovery and late provider registration. */
	public function register(): void {
		add_filter( 'spdb/file22_composer_url', array( $this, 'composer_url' ), 20, 1 );
		add_action( 'spdb/register_adapters', array( $this, 'register_adapter' ), 20, 1 );
	}

	/**
	 * Return only File 22's exact verified Create page. Never invent a fallback.
	 *
	 * @param mixed $current Existing value from another compatible bridge.
	 */
	public function composer_url( $current ): string {
		if ( ! Page_Resolver::is_ready() || Safe_Mode::disabled() ) {
			return is_string( $current ) ? $current : '';
		}
		$url = Page_Resolver::url();
		return '' !== $url ? $url : ( is_string( $current ) ? $current : '' );
	}

	/**
	 * Register a bounded File 22 readiness provider into File 23.
	 *
	 * @param mixed $registry File 23 adapter registry.
	 */
	public function register_adapter( $registry ): void {
		if ( ! interface_exists( 'SPDB_Provider_Adapter' ) || ! is_object( $registry ) || ! is_callable( array( $registry, 'register' ) ) ) {
			return;
		}
		$file = SUPC_PATH . 'includes/integration/class-file23-dashboard-adapter-runtime.php';
		if ( ! is_readable( $file ) ) {
			if ( is_callable( array( $registry, 'record_error' ) ) && class_exists( '\\WP_Error' ) ) {
				$registry->record_error( 'sabri_universal_post_composer', new \WP_Error( 'supc_spdb_adapter_file_missing', __( 'The File 22 Publishing Dashboard adapter file is missing.', 'sabri-universal-post-composer' ) ) );
			}
			return;
		}
		require_once $file;
		if ( ! class_exists( __NAMESPACE__ . '\\File23_Dashboard_Adapter_Runtime', false ) ) {
			return;
		}
		$result = $registry->register( new File23_Dashboard_Adapter_Runtime() );
		if ( function_exists( 'is_wp_error' ) && is_wp_error( $result ) ) {
			do_action( 'supc_file23_adapter_registration_error', sanitize_key( (string) $result->get_error_code() ) );
		}
	}
}
