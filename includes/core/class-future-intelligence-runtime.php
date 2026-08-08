<?php
/**
 * Browser/runtime bootstrap for the 18-item Future Composer Intelligence layer.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

namespace Sabri\UniversalComposer\Core;

use Sabri\UniversalComposer\Http\Future_Rest_Controller;
use Sabri\UniversalComposer\Http\Rest_Controller;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Future_Intelligence_Runtime {
	private bool $registered = false;
	private Future_Rest_Controller $rest;
	private Future_Intelligence_Hardening $hardening;

	public function __construct() {
		$hardening_file = __DIR__ . '/class-future-intelligence-hardening.php';
		if ( ! class_exists( Future_Intelligence_Hardening::class, false ) && is_readable( $hardening_file ) ) {
			require_once $hardening_file;
		}
		$this->rest      = new Future_Rest_Controller();
		$this->hardening = new Future_Intelligence_Hardening();
	}

	public function register(): void {
		if ( $this->registered || ! function_exists( 'add_action' ) || ! function_exists( 'add_filter' ) ) {
			return;
		}
		$this->registered = true;
		$this->hardening->register();
		$this->rest->register();
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ), 40 );
		add_filter( 'supc_system_check_report', array( $this, 'append_system_check' ), 88 );
	}

	public function enqueue_assets(): void {
		if ( Safe_Mode::disabled() || ! is_user_logged_in() || ! Page_Resolver::is_create_request() ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only route selection. All future REST operations re-authorize server-side.
		$adapter_key = isset( $_GET['type'] ) && is_scalar( $_GET['type'] ) ? sanitize_key( wp_unslash( (string) $_GET['type'] ) ) : '';
		if ( ! Contract_Boundary::adapter_key( $adapter_key ) ) {
			return;
		}
		try {
			$available = Plugin::instance()->registry()->available_for_user( get_current_user_id() );
		} catch ( \Throwable $error ) {
			unset( $error );
			return;
		}
		if ( ! isset( $available[ $adapter_key ] ) ) {
			return;
		}

		$version = defined( 'SUPC_FUTURE_INTELLIGENCE_VERSION' ) ? (string) SUPC_FUTURE_INTELLIGENCE_VERSION : '1.0.0';

		if ( function_exists( 'wp_style_is' ) && ! wp_style_is( 'supc-create-surface', 'registered' ) ) {
			wp_register_style( 'supc-create-surface', SUPC_URL . 'assets/css/create-surface.css', array( 'dashicons' ), SUPC_VERSION );
		}
		if ( function_exists( 'wp_style_is' ) && ! wp_style_is( 'supc-workflow-composer', 'registered' ) ) {
			wp_register_style( 'supc-workflow-composer', SUPC_URL . 'assets/css/workflow-composer.css', array( 'supc-create-surface' ), SUPC_VERSION );
		}
		if ( function_exists( 'wp_script_is' ) && ! wp_script_is( 'supc-workflow-composer', 'registered' ) ) {
			wp_register_script( 'supc-workflow-composer', SUPC_URL . 'assets/js/workflow-composer.js', array(), SUPC_VERSION, true );
		}
		wp_enqueue_style( 'supc-create-surface' );
		wp_enqueue_style( 'supc-workflow-composer' );
		wp_enqueue_script( 'supc-workflow-composer' );
		wp_enqueue_style( 'supc-future-intelligence', SUPC_URL . 'assets/css/future-intelligence.css', array( 'supc-workflow-composer' ), $version );
		wp_enqueue_script( 'supc-future-intelligence', SUPC_URL . 'assets/js/future-intelligence.js', array( 'supc-workflow-composer' ), $version, true );
		wp_enqueue_script( 'supc-future-intelligence-advanced', SUPC_URL . 'assets/js/future-intelligence-advanced.js', array( 'supc-future-intelligence' ), $version, true );
		wp_enqueue_script( 'supc-future-intelligence-safety', SUPC_URL . 'assets/js/future-intelligence-safety.js', array( 'supc-future-intelligence-advanced' ), $version, true );
		wp_enqueue_script( 'supc-future-intelligence-recovery-hardening', SUPC_URL . 'assets/js/future-intelligence-recovery-hardening.js', array( 'supc-future-intelligence-safety' ), $version, true );
		wp_enqueue_script( 'supc-future-intelligence-annotations-hardening', SUPC_URL . 'assets/js/future-intelligence-annotations-hardening.js', array( 'supc-future-intelligence-recovery-hardening' ), $version, true );
		wp_localize_script(
			'supc-future-intelligence',
			'SUPCFuture',
			array(
				'version'   => $version,
				'restRoot'  => untrailingslashit( esc_url_raw( rest_url( Rest_Controller::NAMESPACE ) ) ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'userId'    => get_current_user_id(),
				'adapter'   => $adapter_key,
				'locale'    => function_exists( 'determine_locale' ) ? determine_locale() : get_locale(),
				'isRtl'     => is_rtl(),
				'privacy'   => array(
					'localEncryptedRecovery' => (bool) apply_filters( 'supc_future_encrypted_recovery_allowed', true, get_current_user_id(), $adapter_key ),
					'externalSensitiveAdvice' => (bool) apply_filters( 'supc_future_sensitive_capability_allowed', false, 'client_discovery', get_current_user_id(), $adapter_key ),
				),
				'strings'   => array(
					'title'               => __( 'Composer Intelligence', 'sabri-universal-post-composer' ),
					'providerUnavailable' => __( 'Provider unavailable', 'sabri-universal-post-composer' ),
					'sensitiveBlocked'    => __( 'External advisory is disabled for sensitive drafts unless the governing owner explicitly authorizes it.', 'sabri-universal-post-composer' ),
					'working'             => __( 'Working…', 'sabri-universal-post-composer' ),
					'failed'              => __( 'The advisory request failed.', 'sabri-universal-post-composer' ),
				),
			)
		);
	}

	/** @param array<int,array<string,mixed>> $rows @return array<int,array<string,mixed>> */
	public function append_system_check( array $rows ): array {
		$required = array(
			SUPC_PATH . 'includes/contracts/interface-future-capability-adapter.php',
			SUPC_PATH . 'includes/core/class-future-intelligence-hardening.php',
			SUPC_PATH . 'includes/http/class-future-rest-controller.php',
			SUPC_PATH . 'assets/js/future-intelligence.js',
			SUPC_PATH . 'assets/js/future-intelligence-advanced.js',
			SUPC_PATH . 'assets/js/future-intelligence-safety.js',
			SUPC_PATH . 'assets/js/future-intelligence-recovery-hardening.js',
			SUPC_PATH . 'assets/js/future-intelligence-annotations-hardening.js',
			SUPC_PATH . 'assets/css/future-intelligence.css',
		);
		$missing = array();
		foreach ( $required as $path ) {
			if ( ! is_readable( $path ) ) {
				$missing[] = basename( $path );
			}
		}
		$rows[] = array(
			'key'    => 'future_composer_intelligence_18',
			'status' => array() === $missing ? 'pass' : 'fail',
			'count'  => count( $missing ),
			'codes'  => array() === $missing ? array() : array( 'future_intelligence_runtime_missing' ),
		);
		return $rows;
	}
}
