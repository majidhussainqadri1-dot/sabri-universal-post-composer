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
	private const PUBLIC_API_FUNCTIONS = array(
		'supc_register_adapter',
		'supc_unregister_adapter',
		'supc_adapter_available',
		'supc_adapter_matches',
		'supc_workflow_schema',
		'supc_workflow_create_draft',
		'supc_workflow_validate',
		'supc_workflow_preview',
		'supc_workflow_submit',
		'supc_workflow_status',
		'supc_workflow_canonical_url',
		'supc_generate_idempotency_key',
	);
	private const FILE20_FUNCTIONS = array(
		'sabri_shell_create_contract_available',
		'sabri_shell_create_visible_for_current_user',
	);
	private const SOCIAL_ADAPTER_KEY = 'social_publication';
	private const SOCIAL_CAPABILITY = 'sabri_feed_create_posts';

	private static ?self $instance = null;
	private Permission_Resolver $permissions;
	private Registry $registry;
	private Workflow_Coordinator $workflow_coordinator;
	private Create_Surface $create_surface;
	private System_Check_Page $system_check_page;
	private bool $booted = false;
	private bool $private_headers_applied = false;

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
		if ( ! $this->send_private_surface_headers() ) {
			return $this->privacy_boundary_notice();
		}

		$this->ensure_create_surface_assets();
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
		$page_status    = Page_Resolver::inspect()['status'];
		$core_available = $this->permissions->core_available();
		$rows[] = array(
			'key'    => 'membership_core',
			'status' => $core_available ? 'pass' : 'fail',
			'codes'  => $core_available ? array() : array( 'membership_core_unavailable' ),
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
		$rows[] = $this->current_user_authorization_row();
		$rows[] = $this->create_surface->system_check_row( get_current_user_id() );
		return $rows;
	}

	private function ensure_create_surface_assets(): void {
		wp_enqueue_style( 'supc-create-surface', SUPC_URL . 'assets/css/create-surface.css', array( 'dashicons' ), SUPC_VERSION );

		if (
			function_exists( 'did_action' ) &&
			function_exists( 'wp_style_is' ) &&
			function_exists( 'wp_print_styles' ) &&
			did_action( 'wp_head' ) > 0 &&
			! wp_style_is( 'supc-create-surface', 'done' )
		) {
			wp_print_styles( 'supc-create-surface' );
		}
	}

	private function send_private_surface_headers(): bool {
		if ( $this->private_headers_applied ) {
			return true;
		}
		if ( headers_sent() ) {
			return false;
		}

		foreach ( array( 'DONOTCACHEPAGE', 'DONOTCACHEOBJECT', 'DONOTCACHEDB' ) as $constant ) {
			if ( ! defined( $constant ) ) {
				define( $constant, true );
			}
		}

		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
		header( 'Vary: Cookie', false );
		do_action( 'litespeed_control_set_nocache', 'sabri-universal-post-composer' );
		do_action( 'supc_private_surface_headers_applied' );
		$this->private_headers_applied = true;
		return true;
	}

	private function privacy_boundary_notice(): string {
		$heading_id = wp_unique_id( 'supc-privacy-boundary-heading-' );
		return '<section class="supc-create-notice supc-create-notice--unavailable" role="status" aria-labelledby="'
			. esc_attr( $heading_id )
			. '"><h2 id="'
			. esc_attr( $heading_id )
			. '">'
			. esc_html__( 'Content creation is unavailable in this page context.', 'sabri-universal-post-composer' )
			. '</h2><p>'
			. esc_html__( 'The private response boundary could not be applied before output began. No account, adapter, draft, or publication state was evaluated.', 'sabri-universal-post-composer' )
			. '</p></section>';
	}

	private function is_create_surface_request(): bool {
		if ( Page_Resolver::is_create_request() ) {
			return true;
		}
		global $post;
		return $post instanceof \WP_Post && ( has_shortcode( (string) $post->post_content, 'sabri_universal_composer' ) || has_shortcode( (string) $post->post_content, 'sabri_composer_my_content' ) );
	}

	/**
	 * Audit every independent authorization gate in one pass. One failed gate
	 * never prevents the remaining safe, read-only checks from running.
	 *
	 * @return array<string,mixed>
	 */
	private function current_user_authorization_row(): array {
		$user_id = get_current_user_id();
		$codes   = array();
		$report  = $this->permissions->eligibility_report( $user_id );

		if ( ! $report['eligible'] ) {
			$codes[] = $report['reason'];
		}

		$adapter  = $this->registry->get( self::SOCIAL_ADAPTER_KEY );
		$contract = $this->registry->adapter_contract( self::SOCIAL_ADAPTER_KEY );
		if ( null === $adapter ) {
			$codes[] = 'social_publication_not_registered';
		}
		if ( null === $contract ) {
			$codes[] = 'social_publication_registration_metadata_missing';
		} elseif ( self::SOCIAL_CAPABILITY !== $contract['required_capability'] ) {
			$codes[] = 'required_capability_mismatch';
		}

		if ( $user_id <= 0 ) {
			$codes[] = 'authorization_subject_missing';
		} else {
			try {
				if ( ! user_can( $user_id, self::SOCIAL_CAPABILITY ) ) {
					$codes[] = 'native_capability_missing';
				}
			} catch ( \Throwable $error ) {
				unset( $error );
				$codes[] = 'native_capability_check_exception';
			}
		}

		if ( ! defined( 'SABRI_HNF_VERSION' ) ) {
			$codes[] = 'file21_runtime_missing';
		} elseif ( ! Version::valid( (string) SABRI_HNF_VERSION ) || version_compare( (string) SABRI_HNF_VERSION, '1.0.3', '<' ) ) {
			$codes[] = 'file21_runtime_too_low';
		}

		$copies = $this->file21_copy_counts();
		if ( $copies['installed'] > 1 ) {
			$codes[] = 'file21_duplicate_installed_copies';
		}
		if ( $copies['active'] > 1 ) {
			$codes[] = 'file21_duplicate_active_copies';
		}

		if ( class_exists( '\\Sabri\\HomeNewsFeed\\Settings' ) && is_callable( array( '\\Sabri\\HomeNewsFeed\\Settings', 'get' ) ) ) {
			try {
				$settings = \Sabri\HomeNewsFeed\Settings::get();
				if ( empty( $settings['general']['enabled'] ) ) {
					$codes[] = 'file21_general_disabled';
				}
				if ( empty( $settings['composer']['public_composer_enabled'] ) ) {
					$codes[] = 'file21_composer_disabled';
				}
			} catch ( \Throwable $error ) {
				unset( $error );
				$codes[] = 'file21_settings_exception';
			}
		} elseif ( defined( 'SABRI_HNF_VERSION' ) ) {
			$codes[] = 'file21_settings_exception';
		}

		if ( class_exists( '\\Sabri\\HomeNewsFeed\\SafeMode' ) ) {
			try {
				if ( \Sabri\HomeNewsFeed\SafeMode::emergency_disabled() ) {
					$codes[] = 'file21_emergency_disabled';
				}
				if ( \Sabri\HomeNewsFeed\SafeMode::query_safe_mode() ) {
					$codes[] = 'file21_safe_mode_active';
				}
			} catch ( \Throwable $error ) {
				unset( $error );
				$codes[] = 'file21_safe_mode_exception';
			}
		} elseif ( defined( 'SABRI_HNF_VERSION' ) ) {
			$codes[] = 'file21_safe_mode_exception';
		}

		if ( null !== $adapter ) {
			try {
				if ( ! $adapter->is_available() ) {
					$codes[] = 'native_adapter_unavailable';
				} elseif ( $user_id <= 0 || ! $adapter->can_create( $user_id ) ) {
					$codes[] = 'native_adapter_create_denied';
				}
			} catch ( \Throwable $error ) {
				unset( $error );
				$codes[] = 'native_adapter_authorization_exception';
			}
		}

		$codes = array_values( array_unique( $codes ) );
		return array(
			'key'    => 'current_user_authorization',
			'status' => array() === $codes ? 'pass' : 'fail',
			'count'  => count( $codes ),
			'codes'  => $codes,
		);
	}

	/** @return array{installed:int,active:int} */
	private function file21_copy_counts(): array {
		$result = array( 'installed' => 0, 'active' => 0 );
		if ( ! function_exists( 'get_plugins' ) && defined( 'ABSPATH' ) ) {
			$plugin_api = ABSPATH . 'wp-admin/includes/plugin.php';
			if ( is_readable( $plugin_api ) ) {
				require_once $plugin_api;
			}
		}
		if ( ! function_exists( 'get_plugins' ) ) {
			return $result;
		}

		foreach ( get_plugins() as $basename => $headers ) {
			$name   = isset( $headers['Name'] ) ? (string) $headers['Name'] : '';
			$domain = isset( $headers['TextDomain'] ) ? (string) $headers['TextDomain'] : '';
			$match  = 'sabri-complete-home-news-feed' === $domain
				|| in_array( $name, array( 'Sabri Complete Home and News Feed', 'Sabri News Feed and Publishing' ), true )
				|| str_ends_with( (string) $basename, '/sabri-complete-home-news-feed.php' );
			if ( ! $match ) {
				continue;
			}
			++$result['installed'];
			if ( function_exists( 'is_plugin_active' ) && is_plugin_active( $basename ) ) {
				++$result['active'];
			}
		}
		return $result;
	}

	/** @return array<string, mixed> */
	private function public_api_contract_row(): array {
		$codes              = array();
		$version            = $this->runtime_constant( 'SUPC_PUBLIC_API_VERSION' );
		$owner              = $this->runtime_constant( 'SUPC_PUBLIC_API_OWNER' );
		$owned              = $this->runtime_constant( 'SUPC_PUBLIC_API_FUNCTIONS_OWNED' );
		$collisions         = $this->runtime_constant( 'SUPC_PUBLIC_API_COLLISIONS' );
		$functions_complete = Runtime_Trust::functions_available( self::PUBLIC_API_FUNCTIONS );

		if ( '1.0.0' !== $version ) { $codes[] = 'public_api_version_mismatch'; }
		if ( 'sabri-universal-post-composer' !== $owner ) { $codes[] = 'public_api_owner_mismatch'; }
		if ( true !== $owned || ! is_string( $collisions ) || '' !== $collisions ) { $codes[] = 'public_api_function_collision'; }
		if ( ! $functions_complete ) { $codes[] = 'public_api_incomplete'; }
		if ( ! Runtime_Trust::public_api_owned( SUPC_PATH . 'includes/core/functions.php' ) ) {
			$codes[] = 'public_api_function_collision';
		}

		$codes = array_values( array_unique( $codes ) );
		return array( 'key' => 'public_api_contract', 'status' => array() === $codes ? 'pass' : 'fail', 'count' => count( $codes ), 'codes' => $codes );
	}

	/** @return array<string, mixed> */
	private function file20_contract_row(): array {
		$package_claimed  = Runtime_Trust::shell_package_claimed();
		$contract_claimed = Runtime_Trust::shell_create_contract_claimed();

		if ( ! $package_claimed && ! $contract_claimed ) {
			return array( 'key' => 'file20_create_contract', 'status' => 'warning', 'count' => 1, 'codes' => array( 'file20_contract_missing' ) );
		}

		if ( $package_claimed && ! Runtime_Trust::shell_package_owned() ) {
			return array( 'key' => 'file20_create_contract', 'status' => 'fail', 'count' => 1, 'codes' => array( 'file20_contract_collision' ) );
		}

		if ( ! $contract_claimed ) {
			return array(
				'key'    => 'file20_create_contract',
				'status' => 'warning',
				'count'  => 2,
				'codes'  => array( 'file20_legacy_contract_missing', 'file20_visibility_contract_missing' ),
			);
		}

		$codes                 = array();
		$version               = $this->runtime_constant( 'SABRI_SHELL_CREATE_CONTRACT_VERSION' );
		$owner                 = $this->runtime_constant( 'SABRI_SHELL_CREATE_CONTRACT_OWNER' );
		$owned                 = $this->runtime_constant( 'SABRI_SHELL_CREATE_FUNCTIONS_OWNED' );
		$functions_complete    = Runtime_Trust::functions_available( self::FILE20_FUNCTIONS );
		$availability_callback = null;

		if ( '1.0.1' !== $version ) { $codes[] = 'file20_contract_version_mismatch'; }
		if ( 'sabri-unified-application-shell' !== $owner ) { $codes[] = 'file20_contract_owner_mismatch'; }
		if ( true !== $owned ) { $codes[] = 'file20_contract_collision'; }
		if ( ! $functions_complete ) { $codes[] = 'file20_contract_functions_missing'; }
		if ( ! Runtime_Trust::shell_create_contract_owned() ) { $codes[] = 'file20_contract_collision'; }

		if ( array() === $codes ) {
			$availability_callback = Runtime_Trust::owned_shell_function( self::FILE20_FUNCTIONS[0] );
			if ( ! $availability_callback instanceof \Closure ) {
				$codes[] = 'file20_contract_collision';
			}
		}

		$status = array() === $codes ? 'pass' : 'fail';
		if ( 'pass' === $status && $availability_callback instanceof \Closure ) {
			try {
				if ( ! (bool) $availability_callback() ) {
					$status  = 'warning';
					$codes[] = 'file20_contract_unavailable';
				}
			} catch ( \Throwable $error ) {
				unset( $error );
				$status  = 'fail';
				$codes[] = 'file20_contract_exception';
			}
		}

		$codes = array_values( array_unique( $codes ) );
		return array( 'key' => 'file20_create_contract', 'status' => $status, 'count' => count( $codes ), 'codes' => $codes );
	}

	private function runtime_constant( string $name ): mixed {
		return defined( $name ) ? constant( $name ) : null;
	}
}
