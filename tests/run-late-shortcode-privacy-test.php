<?php
/**
 * Isolated contract for a direct shortcode invoked after output begins.
 */

declare(strict_types=1);

namespace Sabri\UniversalComposer\Core {
	/** Deterministic isolated-process simulation of an already-started response. */
	function headers_sent(): bool {
		return true;
	}
}

namespace {
	require __DIR__ . '/workflow-bootstrap.php';

	$GLOBALS['supc_test_actions_fired'] = array();
	$GLOBALS['supc_test_enqueued_css'] = array();

	$html = \Sabri\UniversalComposer\Core\Plugin::instance()->render_shortcode();
	$failures = array();
	if ( false === strpos( $html, 'private response boundary could not be applied' ) ) {
		$failures[] = 'late shortcode did not fail closed with the generic privacy notice';
	}
	if ( false !== strpos( $html, 'No creation permission is available' ) || false !== strpos( $html, 'Authorized creation services' ) ) {
		$failures[] = 'late shortcode evaluated subject or adapter state after output began';
	}
	if ( array() !== $GLOBALS['supc_test_enqueued_css'] ) {
		$failures[] = 'late shortcode enqueued private-surface assets after the response boundary failed';
	}
	foreach ( $GLOBALS['supc_test_actions_fired'] as $action ) {
		if ( 'supc_private_surface_headers_applied' === ( $action[0] ?? '' ) ) {
			$failures[] = 'late shortcode falsely reported private headers as applied';
		}
	}

	if ( array() !== $failures ) {
		fwrite( STDERR, implode( PHP_EOL, $failures ) . PHP_EOL );
		exit( 1 );
	}

	echo "File 22 late shortcode privacy contract passed.\n";
}
