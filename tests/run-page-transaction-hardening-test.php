<?php
/**
 * Isolated transaction hardening tests for File 22 Create-page repair.
 */

declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );
define( 'OBJECT', 'OBJECT' );
$GLOBALS['supc_transaction_options'] = array();
$GLOBALS['supc_transaction_add_calls'] = 0;

function get_option( string $key, mixed $default = false ): mixed {
	return array_key_exists( $key, $GLOBALS['supc_transaction_options'] )
		? $GLOBALS['supc_transaction_options'][ $key ]
		: $default;
}

function update_option( string $key, mixed $value, bool $autoload = true ): bool {
	unset( $autoload );
	// WordPress persists and later unserializes option values, so object identity
	// is not preserved even when the value is restored correctly.
	$GLOBALS['supc_transaction_options'][ $key ] = unserialize( serialize( $value ) );
	return true;
}

function delete_option( string $key ): bool {
	unset( $GLOBALS['supc_transaction_options'][ $key ] );
	return true;
}

function add_option( string $key, mixed $value, string $deprecated = '', bool|string $autoload = true ): bool {
	unset( $key, $value, $deprecated, $autoload );
	++$GLOBALS['supc_transaction_add_calls'];
	return true;
}

function wp_generate_uuid4(): string {
	return 'not-a-valid-uuid';
}

require dirname( __DIR__ ) . '/includes/core/class-page-resolver.php';

$reflection = new ReflectionClass( \Sabri\UniversalComposer\Core\Page_Resolver::class );
$restore = $reflection->getMethod( 'restore_mapping' );
$lock = $reflection->getMethod( 'acquire_repair_lock' );

$previous = (object) array( 'corrupt-but-restorable' => array( 'x' => 1 ) );
$restored = $restore->invoke( null, true, $previous );
$token = $lock->invoke( null );

$failures = array();
if ( true !== $restored ) {
	$failures[] = 'semantically identical serialized option value was reported as a failed rollback';
}
if ( '' !== $token ) {
	$failures[] = 'malformed UUID was accepted as a repair lock token';
}
if ( 0 !== $GLOBALS['supc_transaction_add_calls'] ) {
	$failures[] = 'malformed UUID reached atomic lock persistence';
}

if ( array() !== $failures ) {
	fwrite( STDERR, implode( PHP_EOL, $failures ) . PHP_EOL );
	exit( 1 );
}

echo "File 22 page transaction hardening contract passed.\n";
