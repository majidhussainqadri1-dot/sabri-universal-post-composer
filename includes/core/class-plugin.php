<?php
/**
 * Main plugin runtime.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

namespace Sabri\UniversalComposer\Core;

use Sabri\UniversalComposer\Admin\System_Check_Page;
use Sabri\UniversalComposer\Integration\Core_Adapter_Requirements;
use Sabri\UniversalComposer\Integration\Shell_Bridge;
use Sabri\UniversalComposer\Presentation\Create_Surface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {
	private static ?self $instance = null;

	private Permission_Resolver $permissions;

	private Registry $registry;

	private Workflow_Coordinator $workflow_coordinator;

	private Create_Surface $create_surface;

	private System_Check_Page $system_check_page;

	private bool $booted = false;

	private function __construct() {
		$this->permissions          = new Permission_Resolver();
		$this->registry             = new Registry( $this->permissions );
		$this->workflow_coordinator = new Workflow_Coordinator( $this->registry, $this->permissions );
		$this->create_surface       = new Create_Surface( $this->registry );
		$this->system_check_page    = new System_Check_Page( $this->registry );
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

		$this->create_surface->register();
		$this->system_check_page->register();
		( new Shell_Bridge( $this->registry ) )->register();
		( new Core_Adapter_Requirements( $this->registry ) )->register();
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

	public function workflow_coordinator(): Workflow_Coordinator {
		return $this->workflow_coordinator;
	}

	public function render_shortcode(): string {
		return $this->create_surface->render();
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
		$page_status = Page_Resolver::inspect()['status'];
		$rows[] = array(
			'key'    => 'membership_core',
			'status' => $this->permissions->core_available() ? 'pass' : 'fail',
		);
		$rows[] = array(
			'key'    => 'create_page',
			'status' => 'ready' === $page_status ? 'pass' : ( 'repairable' === $page_status ? 'warning' : 'fail' ),
			'codes'  => 'ready' === $page_status ? array() : array( 'create_page_' . $page_status ),
		);
		$rows[] = array(
			'key'    => 'adapter_errors',
			'status' => array() === $this->registry->errors() ? 'pass' : 'warning',
			'count'  => count( $this->registry->errors() ),
		);
		$rows[] = $this->create_surface->system_check_row( get_current_user_id() );
		return $rows;
	}
}
