<?php
/**
 * Isolated bounded Create-page discovery query contract.
 */

declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );
define( 'OBJECT', 'OBJECT' );
$GLOBALS['supc_discovery_args'] = array();

function get_option( string $key, mixed $default = false ): mixed {
	unset( $key );
	return $default;
}

function absint( mixed $value ): int {
	return abs( (int) $value );
}

function get_posts( array $args = array() ): array {
	$GLOBALS['supc_discovery_args'] = $args;
	return array();
}

require dirname( __DIR__ ) . '/includes/core/class-page-resolver.php';
\Sabri\UniversalComposer\Core\Page_Resolver::inspect();

$args = $GLOBALS['supc_discovery_args'];
$failures = array();
if ( 'sabri_universal_composer' !== ( $args['s'] ?? null ) ) {
	$failures[] = 'discovery query is not narrowed to the shortcode token';
}
if ( true !== ( $args['sentence'] ?? null ) ) {
	$failures[] = 'discovery query is not using the exact sentence boundary';
}
if ( 'ids' !== ( $args['fields'] ?? null ) ) {
	$failures[] = 'discovery query hydrates more than page IDs';
}

if ( array() !== $failures ) {
	fwrite( STDERR, implode( PHP_EOL, $failures ) . PHP_EOL );
	exit( 1 );
}

echo "File 22 bounded page discovery contract passed.\n";
