<?php
/**
 * Minimal WordPress signatures used only by PHPStan.
 */

declare(strict_types=1);

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( mixed $value, int $flags = 0, int $depth = 512 ): string|false {
		return json_encode( $value, $flags, $depth );
	}
}
