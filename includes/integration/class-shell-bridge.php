<?php
/**
 * Unified Application Shell bridge.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

namespace Sabri\UniversalComposer\Integration;

use Sabri\UniversalComposer\Core\Page_Resolver;
use Sabri\UniversalComposer\Core\Registry;
use Sabri\UniversalComposer\Core\Runtime_Trust;
use Sabri\UniversalComposer\Core\Safe_Mode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Shell_Bridge {
	public function __construct( private Registry $registry ) {
	}

	public function register(): void {
		add_filter( 'sabri_shell_create_url', array( $this, 'filter_create_url' ) );
		if ( Runtime_Trust::shell_create_contract_owned() ) {
			add_filter( 'sabri_shell_can_show_create', array( $this, 'filter_create_visibility' ), 10, 3 );
		}
	}

	public function filter_create_url( mixed $url ): string {
		$fallback     = is_string( $url ) ? $url : '';
		$composer_url = Safe_Mode::disabled() ? '' : Page_Resolver::url();
		return '' !== $composer_url ? $composer_url : $fallback;
	}

	/**
	 * @param mixed $allowed Existing shell presentation decision.
	 * @param mixed $user_id Current user ID supplied by File 20.
	 * @param mixed $settings File 20 settings supplied for compatibility.
	 */
	public function filter_create_visibility( $allowed, $user_id = 0, $settings = array() ): bool {
		unset( $allowed, $settings );
		$current_user_id = get_current_user_id();
		$supplied_id     = is_int( $user_id ) || ( is_string( $user_id ) && 1 === preg_match( '/^[1-9][0-9]*$/D', $user_id ) )
			? (int) $user_id
			: 0;
		if ( $supplied_id > 0 && $supplied_id !== $current_user_id ) {
			do_action( 'supc_deprecated_subject_argument_ignored', 'sabri_shell_can_show_create' );
		}
		if ( $current_user_id <= 0 || Safe_Mode::disabled() || ! Page_Resolver::is_ready() ) {
			return false;
		}
		$snapshot = $this->registry->availability_snapshot_for_user( $current_user_id );
		return 'available' === $snapshot['state'] && array() !== $snapshot['adapters'];
	}
}
