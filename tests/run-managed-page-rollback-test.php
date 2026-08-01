<?php
/**
 * Isolated rollback contract for a newly inserted invalid managed Create page.
 */

declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );
define( 'OBJECT', 'OBJECT' );
$GLOBALS['supc_options'] = array();
$GLOBALS['supc_pages'] = array();
$GLOBALS['supc_deleted'] = array();
$GLOBALS['supc_actions'] = array();

class WP_Error {
}

function get_option( string $key, mixed $default = false ): mixed { return $GLOBALS['supc_options'][ $key ] ?? $default; }
function update_option( string $key, mixed $value, bool $autoload = true ): bool { unset( $autoload ); $GLOBALS['supc_options'][ $key ] = $value; return true; }
function add_option( string $key, mixed $value, string $deprecated = '', bool|string $autoload = true ): bool { unset( $deprecated, $autoload ); if ( array_key_exists( $key, $GLOBALS['supc_options'] ) ) { return false; } $GLOBALS['supc_options'][ $key ] = $value; return true; }
function delete_option( string $key ): bool { unset( $GLOBALS['supc_options'][ $key ] ); return true; }
function absint( mixed $value ): int { return abs( (int) $value ); }
function wp_generate_uuid4(): string { return '00000000-0000-4000-8000-000000000001'; }
function get_posts( array $args = array() ): array { unset( $args ); return array(); }
function get_page_by_path( string $path, string $output = OBJECT, string $post_type = 'page' ): ?object { unset( $path, $output, $post_type ); return null; }
function __( string $text, string $domain = '' ): string { unset( $domain ); return $text; }
function wp_insert_post( array $postarr, bool $wp_error = false ): int|WP_Error {
	unset( $wp_error );
	$GLOBALS['supc_pages'][101] = array(
		'status' => (string) $postarr['post_status'],
		'type' => (string) $postarr['post_type'],
		'content' => (string) $postarr['post_content'],
		'slug' => 'create-2',
		'permalink' => 'https://example.test/create-2/',
		'meta' => $postarr['meta_input'],
	);
	return 101;
}
function is_wp_error( mixed $value ): bool { return $value instanceof WP_Error; }
function get_post_type( int $id ): string|false { return $GLOBALS['supc_pages'][ $id ]['type'] ?? false; }
function get_post_status( int $id ): string|false { return $GLOBALS['supc_pages'][ $id ]['status'] ?? false; }
function get_post_field( string $field, int $id ): mixed {
	return match ( $field ) {
		'post_content' => $GLOBALS['supc_pages'][ $id ]['content'] ?? '',
		'post_name' => $GLOBALS['supc_pages'][ $id ]['slug'] ?? '',
		default => '',
	};
}
function get_post_meta( int $id, string $key = '', bool $single = false ): mixed { $value = $GLOBALS['supc_pages'][ $id ]['meta'][ $key ] ?? ''; return $single ? $value : array( $value ); }
function get_permalink( int $id ): string|false { return $GLOBALS['supc_pages'][ $id ]['permalink'] ?? false; }
function has_shortcode( string $content, string $tag ): bool { return str_contains( $content, '[' . $tag ); }
function wp_validate_redirect( string $location, string $fallback = '' ): string { return str_starts_with( $location, 'https://') ? $location : $fallback; }
function wp_parse_url( string $url ): array|false { $parts = parse_url( $url ); return is_array( $parts ) ? $parts : false; }
function home_url( string $path = '' ): string { return 'https://example.test/' . ltrim( $path, '/' ); }
function wp_delete_post( int $id, bool $force_delete = false ): object|false { unset( $force_delete ); $GLOBALS['supc_deleted'][] = $id; unset( $GLOBALS['supc_pages'][ $id ] ); return (object) array( 'ID' => $id ); }
function do_action( string $hook, ...$args ): void { $GLOBALS['supc_actions'][] = array( $hook, $args ); }

require dirname( __DIR__ ) . '/includes/core/class-page-resolver.php';
$result = \Sabri\UniversalComposer\Core\Page_Resolver::repair_mapping( true );

$failures = array();
if ( 'managed_page_validation_failed' !== ( $result['result'] ?? '' ) ) {
	$failures[] = 'invalid inserted page was not reported as a validation failure';
}
if ( array( 101 ) !== $GLOBALS['supc_deleted'] || isset( $GLOBALS['supc_pages'][101] ) ) {
	$failures[] = 'invalid newly inserted page was not rolled back';
}
if ( ! in_array( array( 'supc_invalid_managed_page_rollback', array( 101, true ) ), $GLOBALS['supc_actions'], true ) ) {
	$failures[] = 'rollback evidence action was not emitted';
}

if ( array() !== $failures ) {
	fwrite( STDERR, implode( PHP_EOL, $failures ) . PHP_EOL );
	exit( 1 );
}

echo "File 22 managed page rollback contract passed.\n";
