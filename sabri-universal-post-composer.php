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
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

define('SUPC_VERSION', '0.1.0-dev');
define('SUPC_SCHEMA_VERSION', '0.0.0');
define('SUPC_FILE', __FILE__);
define('SUPC_PATH', plugin_dir_path(__FILE__));
define('SUPC_URL', plugin_dir_url(__FILE__));

require_once SUPC_PATH . 'includes/contracts/interface-adapter.php';
require_once SUPC_PATH . 'includes/core/class-registry.php';
require_once SUPC_PATH . 'includes/integration/class-shell-bridge.php';
require_once SUPC_PATH . 'includes/core/class-plugin.php';

register_activation_hook(
    __FILE__,
    static function (): void {
        if (version_compare(PHP_VERSION, '8.1', '<')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(
                esc_html__('Sabri Universal Post Composer requires PHP 8.1 or later.', 'sabri-universal-post-composer'),
                esc_html__('Plugin activation failed', 'sabri-universal-post-composer'),
                array('back_link' => true)
            );
        }

        global $wp_version;
        if (version_compare((string) $wp_version, '6.5', '<')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(
                esc_html__('Sabri Universal Post Composer requires WordPress 6.5 or later.', 'sabri-universal-post-composer'),
                esc_html__('Plugin activation failed', 'sabri-universal-post-composer'),
                array('back_link' => true)
            );
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
