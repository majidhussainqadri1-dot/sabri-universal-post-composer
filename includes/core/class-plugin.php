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
		/** Late-loading modules may also call supc_register_adapter() directly. */
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
		// Template/widget/direct do_shortcode() invocation may not be detectable at
		// template_redirect. Enforce the private response boundary again here.
		$this->send_private_surface_headers();
		return $this->create_surface->render();
	}

	public function protect_create_surface(): void {
		if ( $this->is_create_surface_request() ) {
			$this->send_private_surface_headers();
		}
	}

	/**
	 * @param array<string, bool> $robots Existing robots directives.
	 * @return array<string, bool>
	 */
	public function filter_create_robots( array $robots ): array {
		if ( $this->is_create_surface_request() ) {
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
			'codes'  => $this->permissions->core_available() ? array() : array( 'membership_core_unavailable' ),
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
			'codes'  => array_values( array_unique( array_map( static fn ( array $error ): string => sanitize_key( (string) ( $error['code'] ?? 'adapter_error' ) ), $this->registry->errors() ) ) ),
		);
		$rows[] = $this->public_api_contract_row();
		$rows[] = $this->file20_contract_row();
		$rows[] = $this->create_surface->system_check_row( get_current_user_id() );
		return $rows;
	}

	private function send_private_surface_headers(): void {
		nocache_headers();
		if ( ! headers_sent() ) {
			header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
		}
		do_action( 'supc_private_surface_headers_applied' );
	}

	private function is_create_surface_request(): bool {
		if ( Page_Resolver::is_create_request() ) {
			return true;
		}
		global $post;
		return $post instanceof \WP_Post && has_shortcode( (string) $post->post_content, 'sabri_universal_composer' );
	}

	/** @return array<string, mixed> */
	private function public_api_contract_row(): array {
		$required_functions = array(
			'supc_register_adapter', 'supc_unregister_adapter', 'supc_adapter_available',
			'supc_adapter_matches', 'supc_workflow_schema', 'supc_workflow_create_draft',
			'supc_workflow_validate', 'supc_workflow_preview', 'supc_workflow_submit',
			'supc_workflow_status', 'supc_workflow_canonical_url', 'supc_generate_idempotency_key',
		);
		$codes   = array();
		$version = $this->runtime_constant( 'SUPC_PUBLIC_API_VERSION' );
		$owner   = $this->runtime_constant( 'SUPC_PUBLIC_API_OWNER' );
		$owned   = $this->runtime_constant( 'SUPC_PUBLIC_API_FUNCTIONS_OWNED' );
		if ( '1.0.0' !== $version ) { $codes[] = 'public_api_version_mismatch'; }
		if ( 'sabri-universal-post-composer' !== $owner ) { $codes[] = 'public_api_owner_mismatch'; }
		if ( true !== $owned ) { $codes[] = 'public_api_function_collision'; }
		foreach ( $required_functions as $function ) {
			if ( ! function_exists( $function ) ) { $codes[] = 'public_api_incomplete'; break; }
		}
		return array( 'key' => 'public_api_contract', 'status' => array() === $codes ? 'pass' : 'fail', 'count' => count( $codes ), 'codes' => array_values( array_unique( $codes ) ) );
	}

	/** @return array<string, mixed> */
	private function file20_contract_row(): array {
		$codes   = array();
		$version = $this->runtime_constant( 'SABRI_SHELL_CREATE_CONTRACT_VERSION' );
		$owner   = $this->runtime_constant( 'SABRI_SHELL_CREATE_CONTRACT_OWNER' );
		$owned   = $this->runtime_constant( 'SABRI_SHELL_CREATE_FUNCTIONS_OWNED' );
		if ( '1.0.1' !== $version ) { $codes[] = 'file20_contract_version_mismatch'; }
		if ( 'sabri-unified-application-shell' !== $owner ) { $codes[] = 'file20_contract_owner_mismatch'; }
		if ( true !== $owned ) { $codes[] = 'file20_contract_collision'; }
		if ( ! function_exists( 'sabri_shell_create_contract_available' ) || ! function_exists( 'sabri_shell_create_visible_for_current_user' ) ) {
			$codes[] = 'file20_contract_functions_missing';
		} elseif ( ! sabri_shell_create_contract_available() ) {
			$codes[] = 'file20_contract_unavailable';
		}
		return array( 'key' => 'file20_create_contract', 'status' => array() === $codes ? 'pass' : 'fail', 'count' => count( $codes ), 'codes' => array_values( array_unique( $codes ) ) );
	}

	private function runtime_constant( string $name ): mixed {
		return defined( $name ) ? constant( $name ) : null;
	}
}
