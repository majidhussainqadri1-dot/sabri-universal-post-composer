<?php
/**
 * Isolated ownership test for a coherent but noncanonical File 20 runtime.
 */

declare(strict_types=1);

namespace Sabri\UnifiedShell {
	final class SafeMode {
		public static function disabled(): bool {
			return false;
		}
	}
}

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'SABRI_SHELL_VERSION', '99.0.0' );
	define( 'SABRI_SHELL_FILE', __FILE__ );
	define( 'SABRI_SHELL_PATH', __DIR__ . '/' );
	define( 'SABRI_SHELL_SLUG', 'sabri-unified-application-shell' );
	define( 'SABRI_SHELL_CREATE_CONTRACT_VERSION', '1.0.1' );
	define( 'SABRI_SHELL_CREATE_CONTRACT_OWNER', 'sabri-unified-application-shell' );
	define( 'SABRI_SHELL_CREATE_FUNCTIONS_OWNED', true );

	function sabri_shell_create_contract_available(): bool {
		return true;
	}

	function sabri_shell_create_visible_for_current_user(): bool {
		return true;
	}

	function get_option( string $key, mixed $default = false ): mixed {
		unset( $key );
		return $default;
	}

	require dirname( __DIR__ ) . '/includes/core/class-version.php';
	require dirname( __DIR__ ) . '/includes/core/class-runtime-trust.php';
	require dirname( __DIR__ ) . '/includes/core/class-safe-mode.php';

	$functions = array(
		'sabri_shell_create_contract_available',
		'sabri_shell_create_visible_for_current_user',
	);
	if ( \Sabri\UniversalComposer\Core\Runtime_Trust::shell_symbols_owned( $functions, '\Sabri\UnifiedShell\SafeMode' ) ) {
		fwrite( STDERR, "A coherent foreign File 20 runtime was treated as owned.\n" );
		exit( 1 );
	}
	if ( true !== \Sabri\UniversalComposer\Core\Safe_Mode::disabled() ) {
		fwrite( STDERR, "A coherent foreign shell Safe Mode class was trusted.\n" );
		exit( 1 );
	}

	echo "File 22 untrusted shell runtime provenance contract passed.\n";
}
