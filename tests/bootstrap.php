<?php
/**
 * Minimal WordPress stubs for isolated contract tests.
 */

declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );
define( 'SUPC_ADAPTER_API_VERSION', '1.0.0' );
define( 'SUPC_MIN_SMC_VERSION', '1.0.1' );
define( 'SUPC_VERSION', '0.1.0-dev' );
define( 'SUPC_URL', 'https://example.test/wp-content/plugins/sabri-universal-post-composer/' );
define( 'SMC_VERSION', '1.0.1' );

$GLOBALS['supc_test_statuses']      = array( 1 => 'approved', 2 => 'suspended' );
$GLOBALS['supc_test_capabilities']  = array( 1 => array( 'sabri_feed_create_posts' => true ) );
$GLOBALS['supc_test_options']       = array();
$GLOBALS['supc_test_logged_in']     = true;
$GLOBALS['supc_test_current_user']  = 1;
$GLOBALS['supc_test_unique_id']     = 0;
$GLOBALS['supc_test_enqueued_css']  = array();
$GLOBALS['supc_test_actions_fired'] = array();

class WP_Error {
	public function __construct(
		public string $code = '',
		public string $message = '',
		public mixed $data = null
	) {
	}
}

class WP_Post {
	public string $post_content = '';
}

function __( string $text, string $domain = '' ): string {
	unset( $domain );
	return $text;
}

function esc_html__( string $text, string $domain = '' ): string {
	return esc_html( __( $text, $domain ) );
}

function esc_attr__( string $text, string $domain = '' ): string {
	return esc_attr( __( $text, $domain ) );
}

function esc_html( string $text ): string {
	return htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
}

function esc_attr( string $text ): string {
	return esc_html( $text );
}

function esc_url( string $url ): string {
	return esc_attr( $url );
}

function do_action( string $hook, ...$args ): void {
	$GLOBALS['supc_test_actions_fired'][] = array( $hook, $args );
}

function add_action( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {
	unset( $hook, $callback, $priority, $accepted_args );
	return true;
}

function add_filter( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {
	unset( $hook, $callback, $priority, $accepted_args );
	return true;
}

function get_userdata( int $user_id ): object|false {
	return $user_id > 0 ? (object) array( 'ID' => $user_id ) : false;
}

function user_can( int $user_id, string $capability ): bool {
	if ( 'manage_options' === $capability ) {
		return false;
	}
	return ! empty( $GLOBALS['supc_test_capabilities'][ $user_id ][ $capability ] );
}

function smc_user_status( int $user_id ): string {
	return $GLOBALS['supc_test_statuses'][ $user_id ] ?? 'draft';
}

function get_option( string $key, mixed $default = false ): mixed {
	return $GLOBALS['supc_test_options'][ $key ] ?? $default;
}

function is_user_logged_in(): bool {
	return (bool) $GLOBALS['supc_test_logged_in'];
}

function get_current_user_id(): int {
	return (int) $GLOBALS['supc_test_current_user'];
}

function wp_login_url( string $redirect = '' ): string {
	return 'https://example.test/wp-login.php?redirect_to=' . rawurlencode( $redirect );
}

function home_url( string $path = '' ): string {
	return 'https://example.test' . ( '' === $path ? '' : '/' . ltrim( $path, '/' ) );
}

/** @return array<string, int|string>|false */
function wp_parse_url( string $url ): array|false {
	$parts = parse_url( $url );
	return is_array( $parts ) ? $parts : false;
}

function wp_unique_id( string $prefix = '' ): string {
	++$GLOBALS['supc_test_unique_id'];
	return $prefix . $GLOBALS['supc_test_unique_id'];
}

function sanitize_key( string $key ): string {
	$key = strtolower( $key );
	return preg_replace( '/[^a-z0-9_\-]/', '', $key ) ?? '';
}

function sanitize_html_class( string $class ): string {
	return preg_replace( '/[^A-Za-z0-9_-]/', '', $class ) ?? '';
}

function wp_validate_redirect( string $location, string $fallback = '' ): string {
	if (
		str_starts_with( $location, '/' ) ||
		str_starts_with( $location, 'https://' ) ||
		str_starts_with( $location, 'http://' )
	) {
		return $location;
	}
	return $fallback;
}

function wp_enqueue_style( string $handle, string $src = '', array $deps = array(), string|bool|null $ver = false, string $media = 'all' ): void {
	$GLOBALS['supc_test_enqueued_css'][ $handle ] = array( $src, $deps, $ver, $media );
}

function has_shortcode( string $content, string $tag ): bool {
	return str_contains( $content, '[' . $tag );
}

require_once dirname( __DIR__ ) . '/includes/contracts/interface-adapter.php';
require_once dirname( __DIR__ ) . '/includes/contracts/interface-diagnostic-adapter.php';
require_once dirname( __DIR__ ) . '/includes/core/class-safe-mode.php';
require_once dirname( __DIR__ ) . '/includes/core/class-permission-resolver.php';
require_once dirname( __DIR__ ) . '/includes/core/class-page-resolver.php';
require_once dirname( __DIR__ ) . '/includes/core/class-registry.php';
require_once dirname( __DIR__ ) . '/includes/presentation/class-create-surface.php';
require_once dirname( __DIR__ ) . '/includes/integration/class-core-adapter-requirements.php';
