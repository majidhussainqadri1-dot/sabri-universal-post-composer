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
		// The distributed File 20 version 1.0.0 consumes this URL filter.
		add_filter( 'sabri_shell_create_url', array( $this, 'filter_create_url' ) );

		// Role-aware Create visibility is a later atomic File 20 contract. Do not
		// claim that legacy File 20 consumes or enforces a nonexistent filter.
		if ( Runtime_Trust::shell_create_contract_owned() ) {
			add_filter( 'sabri_shell_can_show_create', array( $this, 'filter_create_visibility' ), 10, 3 );
		}
	}

	public function filter_create_url( string $url ): string {
		$composer_url = Page_Resolver::url();
		return '' !== $composer_url ? $composer_url : $url;
	}

	/**
	 * File 20's old role list is a presentation default, not an authorization
	 * source. File 22 may replace that result only after central Membership Core
	 * and adapter permission checks. Safe Mode can never be overridden.
	 *
	 * @param mixed $allowed Existing shell presentation decision.
	 * @param mixed $user_id Current user ID supplied by File 20.
	 * @param mixed $settings File 20 settings supplied for compatibility.
	 */
	public function filter_create_visibility( $allowed, $user_id = 0, $settings = array() ): bool {
		unset( $allowed, $settings );

		$current_user_id = get_current_user_id();
		$supplied_id     = is_numeric( $user_id ) ? (int) $user_id : 0;
		if ( $supplied_id > 0 && $supplied_id !== $current_user_id ) {
			do_action( 'supc_deprecated_subject_argument_ignored', 'sabri_shell_can_show_create' );
		}
		$user_id = $current_user_id;
		if ( $user_id <= 0 || Safe_Mode::disabled() || ! Page_Resolver::is_ready() ) {
			return false;
		}

		return $this->registry->has_available_for_user( $user_id );
	}
}
