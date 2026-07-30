<?php
/**
 * Plugin Name: Sabri Universal Post Composer
 * Plugin URI:  https://www.sabrihomeopathy.com/
 * Description: Role-aware, adapter-driven creation gateway for the Sabri Social Homeopathy Platform.
 * Version:     0.1.0-dev
 * Author:      Dr. Allamah Majid Hussain Sabri Muhaddith Mursheed
 * Text Domain: sabri-universal-post-composer
 * Requires at least: 6.5
 * Requires PHP: 8.1
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$core_constants = array(
	'SUPC_VERSION',
	'SUPC_SCHEMA_VERSION',
	'SUPC_ADAPTER_API_VERSION',
	'SUPC_WORKFLOW_API_VERSION',
	'SUPC_SUBJECT_SCHEMA_API_VERSION',
	'SUPC_MIN_SMC_VERSION',
	'SUPC_FILE',
	'SUPC_PATH',
	'SUPC_URL',
);
$core_constant_collisions = array_values( array_filter( $core_constants, 'defined' ) );

if ( array() !== $core_constant_collisions ) {
	// Never consume a foreign path, URL, version, or contract constant. The plugin
	// remains inert and removes itself from the active set on the next admin load.
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
				. esc_html__( 'Sabri Universal Post Composer was disabled because another component preclaimed one or more File 22 core constants.', 'sabri-universal-post-composer' )
				. '</p></div>';
		}
	);
	return;
}

define( 'SUPC_VERSION', '0.1.0-dev' );
define( 'SUPC_SCHEMA_VERSION', '0.1.0' );
define( 'SUPC_ADAPTER_API_VERSION', '1.0.0' );
define( 'SUPC_WORKFLOW_API_VERSION', '1.0.0' );
define( 'SUPC_SUBJECT_SCHEMA_API_VERSION', '1.0.0' );
define( 'SUPC_MIN_SMC_VERSION', '1.0.1' );
define( 'SUPC_FILE', __FILE__ );
define( 'SUPC_PATH', plugin_dir_path( __FILE__ ) );
define( 'SUPC_URL', plugin_dir_url( __FILE__ ) );

require_once SUPC_PATH . 'includes/contracts/interface-adapter.php';
require_once SUPC_PATH . 'includes/contracts/interface-workflow-adapter.php';
require_once SUPC_PATH . 'includes/contracts/interface-diagnostic-adapter.php';
require_once SUPC_PATH . 'includes/core/class-safe-mode.php';
require_once SUPC_PATH . 'includes/core/class-permission-resolver.php';
require_once SUPC_PATH . 'includes/core/class-page-resolver.php';
require_once SUPC_PATH . 'includes/core/class-registry.php';
require_once SUPC_PATH . 'includes/core/class-workflow-coordinator.php';
require_once SUPC_PATH . 'includes/presentation/class-create-surface.php';
require_once SUPC_PATH . 'includes/integration/class-shell-bridge.php';
require_once SUPC_PATH . 'includes/integration/class-core-adapter-requirements.php';
require_once SUPC_PATH . 'includes/admin/class-system-check-page.php';
require_once SUPC_PATH . 'includes/core/class-plugin.php';
require_once SUPC_PATH . 'includes/core/functions.php';

register_activation_hook(
	__FILE__,
	static function (): void {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

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

		if (
			! defined( 'SMC_VERSION' ) ||
			version_compare( (string) SMC_VERSION, SUPC_MIN_SMC_VERSION, '<' ) ||
			! function_exists( 'smc_user_status' )
		) {
			$failure( __( 'Sabri Membership Core 1.0.1 or later must be active before File 22 can be activated.', 'sabri-universal-post-composer' ) );
		}

		\Sabri\UniversalComposer\Core\Page_Resolver::activate();
		update_option( 'supc_version', SUPC_VERSION, false );
		update_option( 'supc_schema_version', SUPC_SCHEMA_VERSION, false );
	}
);

register_deactivation_hook(
	__FILE__,
	static function (): void {
		delete_transient( 'supc_adapter_health' );
	}
);

add_action(
	'plugins_loaded',
	static function (): void {
		\Sabri\UniversalComposer\Core\Plugin::instance()->boot();
	},
	20
);
