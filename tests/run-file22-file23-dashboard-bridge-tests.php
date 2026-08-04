<?php
/**
 * Adversarial gates for the File 22 -> File 23 Composer bridge.
 */

declare(strict_types=1);

$root = dirname( __DIR__ );
$paths = array(
	'bridge'    => $root . '/includes/integration/class-file23-dashboard-bridge.php',
	'adapter'   => $root . '/includes/integration/class-file23-dashboard-adapter-runtime.php',
	'functions' => $root . '/includes/core/functions.php',
);
$pass = 0;
$fail = 0;
$check = static function ( bool $condition, string $message ) use ( &$pass, &$fail ): void {
	if ( $condition ) {
		++$pass;
		echo "PASS: {$message}\n";
		return;
	}
	++$fail;
	fwrite( STDERR, "FAIL: {$message}\n" );
};
foreach ( $paths as $label => $path ) {
	$check( is_file( $path ), "{$label} source exists" );
}
$bridge    = file_get_contents( $paths['bridge'] ) ?: '';
$adapter   = file_get_contents( $paths['adapter'] ) ?: '';
$functions = file_get_contents( $paths['functions'] ) ?: '';

$check( str_contains( $bridge, "add_filter( 'spdb/file22_composer_url'" ), 'publishes the exact File 22 destination contract' );
$check( str_contains( $bridge, "add_action( 'spdb/register_adapters'" ), 'registers File 22 readiness on File 23 hook' );
$check( str_contains( $bridge, 'Page_Resolver::is_ready()' ), 'requires an exact ready managed Create page' );
$check( str_contains( $bridge, 'Page_Resolver::url()' ), 'uses the canonical File 22 URL resolver' );
$check( str_contains( $bridge, 'Safe_Mode::disabled()' ), 'respects File 22 emergency disable state' );
$check( ! str_contains( $bridge, "home_url( '/create/'" ), 'never guesses /create/' );
$check( ! str_contains( $bridge, 'wp_insert_post' ), 'bridge creates no duplicate Composer page or draft' );
$check( ! str_contains( $bridge, '$wpdb->' ), 'bridge does not query or mutate File 22 storage' );
$check( str_contains( $functions, "define( 'SUPC_FILE23_BRIDGE_VERSION', '0.3.1' )" ), 'bridge release identity is explicit' );
$check( str_contains( $functions, "class-file23-dashboard-bridge.php" ), 'bridge loads before File 23 provider dispatch' );

$check( str_contains( $adapter, 'implements \\SPDB_Provider_Adapter' ), 'readiness adapter implements File 23 Contract 2.0.0' );
$check( str_contains( $adapter, "private const PROVIDER_KEY = 'sabri_universal_post_composer'" ), 'provider key is immutable and canonical' );
$check( str_contains( $adapter, "return array( 'composer_gateway' );" ), 'declares only the Composer gateway object type' );
$check( str_contains( $adapter, "return array();\n\t}\n\n\tpublic function health_check" ), 'declares no direct File 23 operations' );
$check( str_contains( $adapter, 'supc_spdb_direct_write_forbidden' ), 'direct File 23 mutation is rejected' );
$check( str_contains( $adapter, "return array( 'items' => array(), 'total' => 0, 'has_more' => false );" ), 'Composer sessions are not projected as content inventory' );
$check( ! str_contains( $adapter, 'production_accepted' ), 'provider never self-accepts production maturity' );
$check( ! str_contains( $adapter, 'staging_accepted' ), 'provider never self-accepts staging maturity' );
$check( ! preg_match( '/draft_body|post_content|patient|prescription|identity_document|message_body/i', $adapter ), 'adapter exposes no draft body or sensitive domain' );

printf( "File 22 -> File 23 dashboard bridge: %d passed, %d failed.\n", $pass, $fail );
exit( 0 === $fail ? 0 : 1 );
