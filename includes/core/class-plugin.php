<?php
/**
 * Main plugin runtime.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

namespace Sabri\UniversalComposer\Core;

use Sabri\UniversalComposer\Integration\Shell_Bridge;
use Throwable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {
	private static ?self $instance = null;

	private Registry $registry;

	private bool $booted = false;

	private function __construct() {
		$this->registry = new Registry( new Permission_Resolver() );
	}

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function boot(): void {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;
		load_plugin_textdomain( 'sabri-universal-post-composer', false, dirname( plugin_basename( SUPC_FILE ) ) . '/languages' );

		add_shortcode( 'sabri_universal_composer', array( $this, 'render_shortcode' ) );
		add_action( 'init', array( $this, 'announce_registry' ), 20 );
		add_action( 'template_redirect', array( $this, 'protect_create_surface' ), 0 );
		add_filter( 'wp_robots', array( $this, 'filter_create_robots' ) );
		add_filter( 'supc_system_check_report', array( $this, 'append_system_check' ) );

		( new Shell_Bridge( $this->registry ) )->register();
		do_action( 'supc_booted', $this->registry );
	}

	public function announce_registry(): void {
		/**
		 * Compatibility event. Late-loading modules should call
		 * supc_register_adapter() directly and are not limited to this event.
		 */
		do_action( 'supc_register_adapters', $this->registry );
		do_action( 'supc_registry_ready', $this->registry );
	}

	public function registry(): Registry {
		return $this->registry;
	}

	public function render_shortcode(): string {
		if ( Safe_Mode::disabled() ) {
			return '<div class="supc-notice supc-notice--disabled"><p>'
				. esc_html__( 'Content creation is temporarily unavailable.', 'sabri-universal-post-composer' )
				. '</p></div>';
		}

		if ( ! is_user_logged_in() ) {
			$login_url = wp_login_url( Page_Resolver::url() );
			return sprintf(
				'<div class="supc-notice supc-notice--login"><p>%1$s</p><p><a class="button" href="%2$s">%3$s</a></p></div>',
				esc_html__( 'Sign in to create authorized platform content.', 'sabri-universal-post-composer' ),
				esc_url( $login_url ),
				esc_html__( 'Sign In', 'sabri-universal-post-composer' )
			);
		}

		$available = $this->registry->available_for_user( get_current_user_id() );
		if ( array() === $available ) {
			return '<div class="supc-notice supc-notice--empty"><p>'
				. esc_html__( 'No authorized content type is currently available for this account.', 'sabri-universal-post-composer' )
				. '</p></div>';
		}

		$items = '';
		foreach ( $available as $key => $adapter ) {
			try {
				$url = $adapter->start_url( get_current_user_id() );
				if ( '' === $url ) {
					continue;
				}

				$items .= sprintf(
					'<li class="supc-type" data-supc-type="%1$s"><a class="supc-type-link" href="%2$s"><span class="supc-type-label">%3$s</span><span class="supc-type-description">%4$s</span></a></li>',
					esc_attr( $key ),
					esc_url( $url ),
					esc_html( $adapter->label() ),
					esc_html( $adapter->description() )
				);
			} catch ( Throwable $error ) {
				do_action( 'supc_adapter_render_error', $key, get_class( $error ) );
			}
		}

		if ( '' === $items ) {
			return '<div class="supc-notice supc-notice--empty"><p>'
				. esc_html__( 'No creation route is currently available.', 'sabri-universal-post-composer' )
				. '</p></div>';
		}

		$heading_id = wp_unique_id( 'supc-heading-' );
		return '<section class="supc-shell" aria-labelledby="' . esc_attr( $heading_id ) . '">'
			. '<h2 id="' . esc_attr( $heading_id ) . '">' . esc_html__( 'Create', 'sabri-universal-post-composer' ) . '</h2>'
			. '<p>' . esc_html__( 'Choose an authorized content type. The native module remains the permanent data owner.', 'sabri-universal-post-composer' ) . '</p>'
			. '<ul class="supc-type-list">' . $items . '</ul>'
			. '</section>';
	}

	public function protect_create_surface(): void {
		if ( ! Page_Resolver::is_create_request() ) {
			return;
		}

		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
	}

	/**
	 * @param array<string, bool> $robots Existing robots directives.
	 * @return array<string, bool>
	 */
	public function filter_create_robots( array $robots ): array {
		if ( Page_Resolver::is_create_request() ) {
			$robots['noindex']   = true;
			$robots['nofollow']  = true;
			$robots['noarchive'] = true;
		}

		return $robots;
	}

	/**
	 * @param array<int, array<string, mixed>> $rows Existing report rows.
	 * @return array<int, array<string, mixed>>
	 */
	public function append_system_check( array $rows ): array {
		$rows[] = array(
			'key'    => 'membership_core',
			'status' => ( new Permission_Resolver() )->core_available() ? 'pass' : 'fail',
		);
		$rows[] = array(
			'key'    => 'create_page',
			'status' => Page_Resolver::is_ready() ? 'pass' : 'fail',
		);
		$rows[] = array(
			'key'    => 'adapter_errors',
			'status' => array() === $this->registry->errors() ? 'pass' : 'warning',
			'count'  => count( $this->registry->errors() ),
		);
		return $rows;
	}
}
