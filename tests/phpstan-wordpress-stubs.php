<?php
/**
 * Minimal WordPress and plugin signatures used only by PHPStan.
 */

declare(strict_types=1);

if ( ! defined( 'SUPC_WORKFLOW_API_VERSION' ) ) {
	define( 'SUPC_WORKFLOW_API_VERSION', '1.0.0' );
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( mixed $value, int $flags = 0, int $depth = 512 ): string|false {
		return json_encode( $value, $flags, $depth );
	}
}
