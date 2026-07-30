<?php
/**
 * Minimal WordPress stubs for isolated contract tests.
 */

declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );
define( 'OBJECT', 'OBJECT' );
define( 'SUPC_ADAPTER_API_VERSION', '1.0.0' );
define( 'SUPC_MIN_SMC_VERSION', '1.0.1' );
define( 'SUPC_VERSION', '0.1.0-dev' );
define( 'SUPC_URL', 'https://example.test/wp-content/plugins/sabri-universal-post-composer/' );

( static function (): void {
	$membership_path = __DIR__ . '/fixtures/sabri-membership-core';
	define( 'SMC_VERSION', '1.0.1' );
	define( 'SMC_DB_VERSION', '1.0.1' );
	define( 'SMC_FILE', $membership_path . '/sabri-membership-core.php' );
	define( 'SMC_PATH', $membership_path . '/' );
	require_once $membership_path . '/includes/functions.php';
} )();

$GLOBALS['supc_test_statuses']           = array( 1 => 'approved', 2 => 'suspended' );
$GLOBALS['supc_test_capabilities']       = array( 1 => array( 'sabri_feed_create_posts' => true ) );
$GLOBALS['supc_test_options']            = array();
$GLOBALS['supc_test_logged_in']          = true;
$GLOBALS['supc_test_current_user']       = 1;
$GLOBALS['supc_test_manage_options']     = true;
$GLOBALS['supc_test_unique_id']          = 0;
$GLOBALS['supc_test_uuid_counter']       = 0;
$GLOBALS['supc_test_enqueued_css']       = array();
$GLOBALS['supc_test_actions_fired']      = array();
$GLOBALS['supc_test_filter_values']      = array();
$GLOBALS['supc_test_pages']              = array();
$GLOBALS['supc_test_next_post_id']       = 100;
$GLOBALS['supc_test_is_page']            = 0;
$GLOBALS['supc_test_redirect']           = '';
$GLOBALS['supc_test_redirect_success']   = true;
$GLOBALS['supc_test_nonce_checked']      = false;
$GLOBALS['supc_test_update_fail_keys']   = array();
$GLOBALS['supc_test_add_option_fail']    = false;
$GLOBALS['supc_test_insert_mutations']   = array();
$GLOBALS['supc_test_get_posts_calls']    = 0;
$GLOBALS['supc_test_delete_post_result'] = 'object';
$GLOBALS['supc_test_deleted_posts']      = array();
$GLOBALS['supc_test_update_post_result'] = 'id';
$GLOBALS['supc_test_updated_posts']      = array();

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

function apply_filters( string $hook, mixed $value ): mixed {
	return $GLOBALS['supc_test_filter_values'][ $hook ] ?? $value;
}

function add_management_page( string $page_title, string $menu_title, string $capability, string $menu_slug, callable $callback ): string {
	unset( $page_title, $menu_title, $capability, $callback );
	return 'tools_page_' . $menu_slug;
}

function get_userdata( int $user_id ): object|false {
	return $user_id > 0 ? (object) array( 'ID' => $user_id ) : false;
}

function user_can( int $user_id, string $capability ): bool {
	if ( 'manage_options' === $capability ) {
		return $user_id === (int) $GLOBALS['supc_test_current_user'] && (bool) $GLOBALS['supc_test_manage_options'];
	}
	return ! empty( $GLOBALS['supc_test_capabilities'][ $user_id ][ $capability ] );
}

function current_user_can( string $capability ): bool {
	return user_can( get_current_user_id(), $capability );
}

function get_option( string $key, mixed $default = false ): mixed {
	return array_key_exists( $key, $GLOBALS['supc_test_options'] )
		? $GLOBALS['supc_test_options'][ $key ]
		: $default;
}

function update_option( string $key, mixed $value, bool $autoload = true ): bool {
	unset( $autoload );
	if ( in_array( $key, $GLOBALS['supc_test_update_fail_keys'], true ) ) {
		return false;
	}
	$GLOBALS['supc_test_options'][ $key ] = $value;
	return true;
}

function add_option( string $key, mixed $value, string $deprecated = '', bool|string $autoload = true ): bool {
	unset( $deprecated, $autoload );
	if ( $GLOBALS['supc_test_add_option_fail'] || array_key_exists( $key, $GLOBALS['supc_test_options'] ) ) {
		return false;
	}
	$GLOBALS['supc_test_options'][ $key ] = $value;
	return true;
}

function delete_option( string $key ): bool {
	if ( ! array_key_exists( $key, $GLOBALS['supc_test_options'] ) ) {
		return false;
	}
	unset( $GLOBALS['supc_test_options'][ $key ] );
	return true;
}

function absint( mixed $value ): int {
	return abs( (int) $value );
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

function admin_url( string $path = '' ): string {
	return 'https://example.test/wp-admin/' . ltrim( $path, '/' );
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

function wp_generate_uuid4(): string {
	++$GLOBALS['supc_test_uuid_counter'];
	return '00000000-0000-4000-8000-' . str_pad( (string) $GLOBALS['supc_test_uuid_counter'], 12, '0', STR_PAD_LEFT );
}

function sanitize_key( string $key ): string {
	$key = strtolower( $key );
	return preg_replace( '/[^a-z0-9_\-]/', '', $key ) ?? '';
}

function sanitize_html_class( string $class ): string {
	return preg_replace( '/[^A-Za-z0-9_-]/', '', $class ) ?? '';
}

function sanitize_text_field( string $text ): string {
	return trim( strip_tags( $text ) );
}

function wp_unslash( mixed $value ): mixed {
	return is_string( $value ) ? stripslashes( $value ) : $value;
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

function get_post_status( int $post_id ): string|false {
	return $GLOBALS['supc_test_pages'][ $post_id ]['status'] ?? false;
}

function get_post_type( int $post_id ): string|false {
	return $GLOBALS['supc_test_pages'][ $post_id ]['type'] ?? false;
}

function get_post_field( string $field, int $post_id ): mixed {
	if ( 'post_content' === $field ) {
		return $GLOBALS['supc_test_pages'][ $post_id ]['content'] ?? '';
	}
	if ( 'post_name' === $field ) {
		return $GLOBALS['supc_test_pages'][ $post_id ]['slug'] ?? '';
	}
	return '';
}

function get_post_meta( int $post_id, string $key = '', bool $single = false ): mixed {
	$value = $GLOBALS['supc_test_pages'][ $post_id ]['meta_input'][ $key ] ?? '';
	return $single ? $value : array( $value );
}

/** @return array<int, int> */
function get_posts( array $args = array() ): array {
	++$GLOBALS['supc_test_get_posts_calls'];
	$ids       = array();
	$post_type = (string) ( $args['post_type'] ?? 'post' );
	$status    = (string) ( $args['post_status'] ?? 'publish' );
	foreach ( $GLOBALS['supc_test_pages'] as $id => $page ) {
		if ( $status === ( $page['status'] ?? '' ) && $post_type === ( $page['type'] ?? 'page' ) ) {
			$ids[] = (int) $id;
		}
	}
	sort( $ids );
	return $ids;
}

function get_page_by_path( string $path, string $output = OBJECT, string $post_type = 'page' ): object|null {
	unset( $output );
	foreach ( $GLOBALS['supc_test_pages'] as $id => $page ) {
		if ( $path === ( $page['slug'] ?? '' ) && $post_type === ( $page['type'] ?? 'page' ) ) {
			return (object) array( 'ID' => (int) $id );
		}
	}
	return null;
}

function wp_insert_post( array $postarr, bool $wp_error = false ): int|WP_Error {
	unset( $wp_error );
	$mutations = $GLOBALS['supc_test_insert_mutations'];
	if ( ! empty( $mutations['return_error'] ) ) {
		return new WP_Error( 'insert_failed', 'Insert failed.' );
	}

	$id      = ++$GLOBALS['supc_test_next_post_id'];
	$slug    = (string) ( $postarr['post_name'] ?? '' );
	$content = (string) ( $postarr['post_content'] ?? '' );
	$status  = (string) ( $postarr['post_status'] ?? 'draft' );
	$type    = (string) ( $postarr['post_type'] ?? 'post' );
	$meta    = $postarr['meta_input'] ?? array();

	if ( isset( $mutations['slug'] ) ) {
		$slug = (string) $mutations['slug'];
	}
	if ( isset( $mutations['content'] ) ) {
		$content = (string) $mutations['content'];
	}
	if ( isset( $mutations['status'] ) ) {
		$status = (string) $mutations['status'];
	}
	if ( isset( $mutations['type'] ) ) {
		$type = (string) $mutations['type'];
	}
	if ( ! empty( $mutations['strip_meta'] ) ) {
		$meta = array();
	}

	$GLOBALS['supc_test_pages'][ $id ] = array(
		'status'     => $status,
		'type'       => $type,
		'content'    => $content,
		'slug'       => $slug,
		'permalink'  => 'https://example.test/' . $slug . '/',
		'meta_input' => $meta,
	);
	return $id;
}

function wp_delete_post( int $post_id, bool $force_delete = false ): object|false|null {
	unset( $force_delete );
	$result = (string) $GLOBALS['supc_test_delete_post_result'];
	if ( 'null' === $result ) {
		return null;
	}
	if ( 'false' === $result ) {
		return false;
	}
	if ( ! isset( $GLOBALS['supc_test_pages'][ $post_id ] ) ) {
		return null;
	}

	$GLOBALS['supc_test_deleted_posts'][] = $post_id;
	unset( $GLOBALS['supc_test_pages'][ $post_id ] );
	return (object) array( 'ID' => $post_id );
}

function wp_update_post( array $postarr, bool $wp_error = false ): int|WP_Error {
	unset( $wp_error );
	$result  = (string) $GLOBALS['supc_test_update_post_result'];
	$post_id = (int) ( $postarr['ID'] ?? 0 );
	if ( 'error' === $result ) {
		return new WP_Error( 'update_failed', 'Update failed.' );
	}
	if ( 'zero' === $result || $post_id <= 0 || ! isset( $GLOBALS['supc_test_pages'][ $post_id ] ) ) {
		return 0;
	}

	if ( 'no_mutation' !== $result ) {
		if ( array_key_exists( 'post_status', $postarr ) ) {
			$GLOBALS['supc_test_pages'][ $post_id ]['status'] = (string) $postarr['post_status'];
		}
		if ( array_key_exists( 'post_content', $postarr ) ) {
			$GLOBALS['supc_test_pages'][ $post_id ]['content'] = (string) $postarr['post_content'];
		}
	}

	$GLOBALS['supc_test_updated_posts'][] = $post_id;
	return $post_id;
}

function is_wp_error( mixed $thing ): bool {
	return $thing instanceof WP_Error;
}

function get_permalink( int $post_id ): string|false {
	return $GLOBALS['supc_test_pages'][ $post_id ]['permalink'] ?? false;
}

function is_page( int $post_id ): bool {
	return (int) $GLOBALS['supc_test_is_page'] === $post_id;
}

function wp_nonce_field( string $action ): void {
	echo '<input type="hidden" name="_wpnonce" value="' . esc_attr( $action ) . '">';
}

function check_admin_referer( string $action ): int {
	unset( $action );
	$GLOBALS['supc_test_nonce_checked'] = true;
	return 1;
}

function submit_button( string $text, string $type = 'primary', string $name = 'submit', bool $wrap = true, array|string $other_attributes = array() ): void {
	unset( $wrap );
	$value = is_array( $other_attributes ) ? (string) ( $other_attributes['value'] ?? $text ) : $text;
	echo '<button class="button button-' . esc_attr( $type ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">' . esc_html( $text ) . '</button>';
}

function add_query_arg( array $args, string $url ): string {
	return $url . '?' . http_build_query( $args );
}

function wp_safe_redirect( string $location ): bool {
	$GLOBALS['supc_test_redirect'] = $location;
	return (bool) $GLOBALS['supc_test_redirect_success'];
}

function wp_die( string $message ): never {
	throw new RuntimeException( $message );
}

require_once dirname( __DIR__ ) . '/includes/contracts/interface-adapter.php';
require_once dirname( __DIR__ ) . '/includes/contracts/interface-diagnostic-adapter.php';
require_once dirname( __DIR__ ) . '/includes/core/class-version.php';
require_once dirname( __DIR__ ) . '/includes/core/class-safe-mode.php';
require_once dirname( __DIR__ ) . '/includes/core/class-permission-resolver.php';
require_once dirname( __DIR__ ) . '/includes/core/class-page-resolver.php';
require_once dirname( __DIR__ ) . '/includes/core/class-registry.php';
require_once dirname( __DIR__ ) . '/includes/presentation/class-create-surface.php';
require_once dirname( __DIR__ ) . '/includes/integration/class-core-adapter-requirements.php';
require_once dirname( __DIR__ ) . '/includes/admin/class-system-check-page.php';
