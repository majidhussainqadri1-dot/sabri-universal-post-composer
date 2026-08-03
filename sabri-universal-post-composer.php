<?php
/**
 * Plugin Name: Sabri Universal Post Composer
 * Plugin URI:  https://www.sabrihomeopathy.com/
 * Description: Role-aware, adapter-driven creation gateway for the Sabri Social Homeopathy Platform.
 * Version:     1.0.0-rc.1
 * Author:      Dr. Allamah Majid Hussain Sabri Muhaddith Mursheed
 * Text Domain: sabri-universal-post-composer
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Requires Plugins: sabri-membership-core
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

( static function (): void {
	$core_constants = array(
		'SUPC_VERSION',
		'SUPC_SCHEMA_VERSION',
		'SUPC_ADAPTER_API_VERSION',
		'SUPC_WORKFLOW_API_VERSION',
		'SUPC_SUBJECT_SCHEMA_API_VERSION',
		'SUPC_MIN_SMC_VERSION',
		'SUPC_MIN_SMC_DB_VERSION',
		'SUPC_MIN_SMC_CONTRACT_VERSION',
		'SUPC_REST_API_VERSION',
		'SUPC_PLAN_CONTRACT_VERSION',
		'SUPC_FILE',
		'SUPC_PATH',
		'SUPC_URL',
	);
	$core_symbols = array(
		'Sabri\UniversalComposer\Contracts\Adapter',
		'Sabri\UniversalComposer\Contracts\Workflow_Adapter',
		'Sabri\UniversalComposer\Contracts\Diagnostic_Adapter',
		'Sabri\UniversalComposer\Contracts\Draft_Lifecycle_Adapter',
		'Sabri\UniversalComposer\Contracts\Draft_Recovery_Adapter',
		'Sabri\UniversalComposer\Contracts\Upload_Token_Adapter',
		'Sabri\UniversalComposer\Contracts\Revision_Adapter',
		'Sabri\UniversalComposer\Core\Version',
		'Sabri\UniversalComposer\Core\Contract_Boundary',
		'Sabri\UniversalComposer\Core\Runtime_Trust',
		'Sabri\UniversalComposer\Core\Safe_Mode',
		'Sabri\UniversalComposer\Core\Migration_Manager',
		'Sabri\UniversalComposer\Core\Permission_Resolver',
		'Sabri\UniversalComposer\Core\Page_Resolver',
		'Sabri\UniversalComposer\Core\Workspace_Page_Resolver',
		'Sabri\UniversalComposer\Core\Registry',
		'Sabri\UniversalComposer\Core\Workflow_Validator',
		'Sabri\UniversalComposer\Core\Workflow_Coordinator',
		'Sabri\UniversalComposer\Core\Policy_Engine',
		'Sabri\UniversalComposer\Core\Audit_Store',
		'Sabri\UniversalComposer\Core\Upload_Token_Store',
		'Sabri\UniversalComposer\Core\Taxonomy_Map',
		'Sabri\UniversalComposer\Core\Projection_Bus',
		'Sabri\UniversalComposer\Core\Plan_Completion_Runtime',
		'Sabri\UniversalComposer\Core\Session_Store',
		'Sabri\UniversalComposer\Core\Submission_Store',
		'Sabri\UniversalComposer\Core\Reconciliation_Service',
		'Sabri\UniversalComposer\Core\Browser_Runtime',
		'Sabri\UniversalComposer\Core\Plugin',
		'Sabri\UniversalComposer\Presentation\Create_Surface',
		'Sabri\UniversalComposer\Presentation\Workflow_Surface',
		'Sabri\UniversalComposer\Presentation\My_Content_Workspace',
		'Sabri\UniversalComposer\Http\Rest_Controller',
		'Sabri\UniversalComposer\Http\Reconciliation_Rest_Controller',
		'Sabri\UniversalComposer\Http\Plan_Rest_Controller',
		'Sabri\UniversalComposer\Integration\Shell_Bridge',
		'Sabri\UniversalComposer\Integration\Core_Adapter_Requirements',
		'Sabri\UniversalComposer\Admin\System_Check_Page',
		'Sabri\UniversalComposer\Admin\Activation_Wizard',
	);
	$core_constant_collisions = array_values( array_filter( $core_constants, 'defined' ) );
	$core_symbol_collisions   = array_values(
		array_filter(
			$core_symbols,
			static fn ( string $symbol ): bool => class_exists( $symbol, false )
				|| interface_exists( $symbol, false )
				|| trait_exists( $symbol, false )
		)
	);

	if ( array() !== $core_constant_collisions || array() !== $core_symbol_collisions ) {
		add_action(
			'admin_init',
			static function (): void {
				if ( ! function_exists( 'deactivate_plugins' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}
				deactivate_plugins( plugin_basename( __FILE__ ) );
			}
		);
		add_action(
			'admin_notices',
			static function (): void {
				echo '<div class="notice notice-error"><p>'
					. esc_html__( 'Sabri Universal Post Composer was disabled because another component preclaimed one or more File 22 core constants or runtime symbols.', 'sabri-universal-post-composer' )
					. '</p></div>';
			}
		);
		return;
	}

	define( 'SUPC_VERSION', '1.0.0-rc.1' );
	define( 'SUPC_SCHEMA_VERSION', '1.0.0' );
	define( 'SUPC_ADAPTER_API_VERSION', '1.0.0' );
	define( 'SUPC_WORKFLOW_API_VERSION', '1.0.0' );
	define( 'SUPC_SUBJECT_SCHEMA_API_VERSION', '1.0.0' );
	define( 'SUPC_MIN_SMC_VERSION', '1.2.3' );
	define( 'SUPC_MIN_SMC_DB_VERSION', '1.2.0' );
	define( 'SUPC_MIN_SMC_CONTRACT_VERSION', '1.1.2' );
	define( 'SUPC_REST_API_VERSION', '1.2.0' );
	define( 'SUPC_PLAN_CONTRACT_VERSION', '1.0.0' );
	define( 'SUPC_FILE', __FILE__ );
	define( 'SUPC_PATH', plugin_dir_path( __FILE__ ) );
	define( 'SUPC_URL', plugin_dir_url( __FILE__ ) );

	require_once SUPC_PATH . 'includes/contracts/interface-adapter.php';
	require_once SUPC_PATH . 'includes/contracts/interface-workflow-adapter.php';
	require_once SUPC_PATH . 'includes/contracts/interface-diagnostic-adapter.php';
	require_once SUPC_PATH . 'includes/contracts/interface-draft-lifecycle-adapter.php';
	require_once SUPC_PATH . 'includes/contracts/interface-draft-recovery-adapter.php';
	require_once SUPC_PATH . 'includes/contracts/interface-upload-token-adapter.php';
	require_once SUPC_PATH . 'includes/contracts/interface-revision-adapter.php';
	require_once SUPC_PATH . 'includes/core/class-version.php';
	require_once SUPC_PATH . 'includes/core/class-contract-boundary.php';
	require_once SUPC_PATH . 'includes/core/class-runtime-trust.php';
	require_once SUPC_PATH . 'includes/core/class-safe-mode.php';
	require_once SUPC_PATH . 'includes/core/class-migration-manager.php';
	require_once SUPC_PATH . 'includes/core/class-permission-resolver.php';
	require_once SUPC_PATH . 'includes/core/class-page-resolver.php';
	require_once SUPC_PATH . 'includes/core/class-workspace-page-resolver.php';
	require_once SUPC_PATH . 'includes/core/class-registry.php';
	require_once SUPC_PATH . 'includes/core/class-workflow-validator.php';
	require_once SUPC_PATH . 'includes/core/class-policy-engine.php';
	require_once SUPC_PATH . 'includes/core/class-audit-store.php';
	require_once SUPC_PATH . 'includes/core/class-upload-token-store.php';
	require_once SUPC_PATH . 'includes/core/class-taxonomy-map.php';
	require_once SUPC_PATH . 'includes/core/class-projection-bus.php';
	require_once SUPC_PATH . 'includes/core/class-plan-completion-runtime.php';
	require_once SUPC_PATH . 'includes/core/class-workflow-coordinator.php';
	require_once SUPC_PATH . 'includes/core/class-session-store.php';
	require_once SUPC_PATH . 'includes/core/class-submission-store.php';
	require_once SUPC_PATH . 'includes/core/class-reconciliation-service.php';
	require_once SUPC_PATH . 'includes/core/class-browser-runtime.php';
	require_once SUPC_PATH . 'includes/presentation/class-create-surface.php';
	require_once SUPC_PATH . 'includes/presentation/class-workflow-surface.php';
	require_once SUPC_PATH . 'includes/presentation/class-my-content-workspace.php';
	require_once SUPC_PATH . 'includes/http/class-rest-controller.php';
	require_once SUPC_PATH . 'includes/http/class-reconciliation-rest-controller.php';
	require_once SUPC_PATH . 'includes/http/class-plan-rest-controller.php';
	require_once SUPC_PATH . 'includes/integration/class-shell-bridge.php';
	require_once SUPC_PATH . 'includes/integration/class-core-adapter-requirements.php';
	require_once SUPC_PATH . 'includes/admin/class-system-check-page.php';
	require_once SUPC_PATH . 'includes/admin/class-activation-wizard.php';
	require_once SUPC_PATH . 'includes/core/class-plugin.php';
	require_once SUPC_PATH . 'includes/core/functions.php';

	add_filter(
		'cron_schedules',
		static function ( array $schedules ): array {
			$schedules['supc_five_minutes'] = array(
				'interval' => 300,
				'display'  => __( 'Every five minutes — File 22 reconciliation', 'sabri-universal-post-composer' ),
			);
			return $schedules;
		}
	);

	register_activation_hook(
		__FILE__,
		static function (): void {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
			\Sabri\UniversalComposer\Core\Migration_Manager::capture_snapshot();

			$failure = static function ( string $message ): void {
				deactivate_plugins( plugin_basename( SUPC_FILE ) );
				wp_die(
					esc_html( $message ),
					esc_html__( 'Plugin activation failed', 'sabri-universal-post-composer' ),
					array( 'back_link' => true )
				);
			};

			if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
				$failure( __( 'Sabri Universal Post Composer requires PHP 8.1 or later.', 'sabri-universal-post-composer' ) );
			}

			global $wp_version;
			if ( version_compare( (string) $wp_version, '6.5', '<' ) ) {
				$failure( __( 'Sabri Universal Post Composer requires WordPress 6.5 or later.', 'sabri-universal-post-composer' ) );
			}

			$permissions = new \Sabri\UniversalComposer\Core\Permission_Resolver();
			if ( ! $permissions->core_available() ) {
				$failure( __( 'Sabri Membership Core 1.2.3 or later, database schema 1.2.0 or later, and contract 1.1.2 or later must be active before File 22 can be activated.', 'sabri-universal-post-composer' ) );
			}

			if ( ! \Sabri\UniversalComposer\Core\Session_Store::install() ) {
				$failure( __( 'The File 22 orchestration session table could not be installed safely.', 'sabri-universal-post-composer' ) );
			}
			if ( ! \Sabri\UniversalComposer\Core\Submission_Store::install() ) {
				$failure( __( 'The File 22 submission map and reconciliation outbox could not be installed safely.', 'sabri-universal-post-composer' ) );
			}
			if ( ! \Sabri\UniversalComposer\Core\Upload_Token_Store::install() ) {
				$failure( __( 'The File 22 metadata-only upload-token table could not be installed safely.', 'sabri-universal-post-composer' ) );
			}
			if ( ! \Sabri\UniversalComposer\Core\Audit_Store::install() ) {
				$failure( __( 'The File 22 privacy-safe audit table could not be installed safely.', 'sabri-universal-post-composer' ) );
			}
			\Sabri\UniversalComposer\Core\Page_Resolver::activate();
			\Sabri\UniversalComposer\Core\Migration_Manager::schedule_jobs();
			\Sabri\UniversalComposer\Core\Migration_Manager::set_writes_enabled( true );
			update_option( 'supc_version', SUPC_VERSION, false );
			update_option( 'supc_schema_version', SUPC_SCHEMA_VERSION, false );
		}
	);

	register_deactivation_hook(
		__FILE__,
		static function (): void {
			delete_transient( 'supc_adapter_health' );
			if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
				wp_clear_scheduled_hook( 'supc_cleanup_expired_sessions' );
				wp_clear_scheduled_hook( 'supc_process_reconciliation_queue' );
				wp_clear_scheduled_hook( 'supc_cleanup_plan_metadata' );
			}
		}
	);

	add_action(
		'plugins_loaded',
		static function (): void {
			\Sabri\UniversalComposer\Core\Plugin::instance()->boot();
		},
		20
	);
	add_action(
		'plugins_loaded',
		static function (): void {
			( new \Sabri\UniversalComposer\Core\Browser_Runtime() )->boot();
		},
		25
	);
	add_action(
		'plugins_loaded',
		static function (): void {
			( new \Sabri\UniversalComposer\Core\Plan_Completion_Runtime() )->boot();
		},
		27
	);

	add_action(
		'plugins_loaded',
		static function (): void {
			( new \Sabri\UniversalComposer\Admin\Activation_Wizard() )->register();
		},
		28
	);
} )();
