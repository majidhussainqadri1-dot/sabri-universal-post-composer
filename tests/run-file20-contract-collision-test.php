<?php
/**
 * Isolated collision test for File 20's foreign Create contract producer.
 */

declare(strict_types=1);

define( 'SABRI_SHELL_CREATE_CONTRACT_VERSION', '9.9.9' );
define( 'SABRI_SHELL_CREATE_CONTRACT_OWNER', 'foreign-producer' );
define( 'SABRI_SHELL_CREATE_FUNCTIONS_OWNED', false );

function sabri_shell_create_contract_available(): bool {
	throw new RuntimeException( 'A foreign producer must not be invoked.' );
}

function sabri_shell_create_visible_for_current_user(): bool {
	throw new RuntimeException( 'A foreign producer must not be invoked.' );
}

require_once __DIR__ . '/workflow-bootstrap.php';

$rows = Sabri\UniversalComposer\Core\Plugin::instance()->append_system_check( array() );
$row  = null;
foreach ( $rows as $candidate ) {
	if ( 'file20_create_contract' === ( $candidate['key'] ?? '' ) ) {
		$row = $candidate;
		break;
	}
}

$failures = array();
if ( ! is_array( $row ) ) {
	$failures[] = 'File 20 contract row is missing.';
} else {
	foreach ( array( 'file20_contract_version_mismatch', 'file20_contract_owner_mismatch', 'file20_contract_collision' ) as $code ) {
		if ( ! in_array( $code, $row['codes'] ?? array(), true ) ) {
			$failures[] = 'Missing controlled collision code: ' . $code;
		}
	}
	if ( 'fail' !== ( $row['status'] ?? '' ) ) {
		$failures[] = 'A foreign producer did not fail closed.';
	}
}

if ( array() !== $failures ) {
	fwrite( STDERR, implode( PHP_EOL, $failures ) . PHP_EOL );
	exit( 1 );
}

echo "File 20 foreign contract collision passed without invocation.\n";
