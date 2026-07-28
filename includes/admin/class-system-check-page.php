<?php
/**
 * Privacy-safe administrator System Check and repair controls.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

namespace Sabri\UniversalComposer\Admin;

use Sabri\UniversalComposer\Contracts\Diagnostic_Adapter;
use Sabri\UniversalComposer\Core\Page_Resolver;
use Sabri\UniversalComposer\Core\Registry;
use Throwable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class System_Check_Page {
	private const PAGE_SLUG = 'supc-system-check';
	private const CAPABILITY = 'manage_options';
	private const REPAIR_ACTION = 'supc_repair_create_page';
	private const NONCE_ACTION = 'supc_repair_create_page';

	public function __construct( private Registry $registry ) {
	}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_post_' . self::REPAIR_ACTION, array( $this, 'handle_repair' ) );
	}

	public function add_menu(): void {
		add_management_page(
			__( 'Universal Composer Health', 'sabri-universal-post-composer' ),
			__( 'Composer Health', 'sabri-universal-post-composer' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	public function render(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to view this page.', 'sabri-universal-post-composer' ) );
		}

		$rows         = $this->system_rows();
		$adapter_rows = $this->adapter_rows();
		$inspection   = Page_Resolver::inspect();
		$notice       = $this->request_notice();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Universal Composer Health', 'sabri-universal-post-composer' ); ?></h1>
			<p><?php echo esc_html__( 'Read-only health information for File 22 and its registered native-module adapters. No user content, URLs, identity data, or clinical data is displayed.', 'sabri-universal-post-composer' ); ?></p>

			<?php if ( '' !== $notice ) : ?>
				<div class="notice notice-info is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
			<?php endif; ?>

			<h2><?php echo esc_html__( 'System Check', 'sabri-universal-post-composer' ); ?></h2>
			<?php $this->render_system_table( $rows ); ?>

			<h2><?php echo esc_html__( 'Adapter Health', 'sabri-universal-post-composer' ); ?></h2>
			<?php $this->render_adapter_table( $adapter_rows ); ?>

			<h2><?php echo esc_html__( 'Create Page Mapping', 'sabri-universal-post-composer' ); ?></h2>
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: mapping status, 2: configured page ID, 3: discovered page ID. */
						__( 'Status: %1$s. Configured page ID: %2$d. Discovered shortcode page ID: %3$d.', 'sabri-universal-post-composer' ),
						(string) $inspection['status'],
						(int) $inspection['configured_page_id'],
						(int) $inspection['discovered_page_id']
					)
				);
				?>
			</p>
			<p><?php echo esc_html__( 'The repair operation can only remap File 22 to an existing published shortcode page or create a new File 22-managed Create page. It never edits, deletes, or overwrites unrelated pages.', 'sabri-universal-post-composer' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::REPAIR_ACTION ); ?>">
				<?php wp_nonce_field( self::NONCE_ACTION ); ?>
				<button type="submit" class="button button-secondary" name="supc_mode" value="dry_run"><?php echo esc_html__( 'Dry Run', 'sabri-universal-post-composer' ); ?></button>
				<button type="submit" class="button button-primary" name="supc_mode" value="repair"><?php echo esc_html__( 'Repair Create Page Mapping', 'sabri-universal-post-composer' ); ?></button>
			</form>
		</div>
		<?php
	}

	public function handle_repair(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to repair File 22.', 'sabri-universal-post-composer' ) );
		}

		check_admin_referer( self::NONCE_ACTION );
		$mode = isset( $_POST['supc_mode'] ) ? sanitize_key( wp_unslash( (string) $_POST['supc_mode'] ) ) : '';

		if ( 'dry_run' === $mode ) {
			$result = Page_Resolver::inspect();
			$code   = 'dry_run_' . sanitize_key( (string) $result['status'] );
		} elseif ( 'repair' === $mode ) {
			$result = Page_Resolver::repair_mapping( true );
			$code   = sanitize_key( (string) $result['result'] );
		} else {
			$code = 'invalid_request';
		}

		$redirect = add_query_arg(
			array(
				'page'        => self::PAGE_SLUG,
				'supc_notice' => $code,
			),
			admin_url( 'tools.php' )
		);

		if ( ! wp_safe_redirect( $redirect ) ) {
			wp_die( esc_html__( 'The administrator redirect could not be completed safely.', 'sabri-universal-post-composer' ) );
		}
		exit;
	}

	/**
	 * @return array<int, array{key:string,status:string,count:int,codes:array<int,string>}>
	 */
	public function system_rows(): array {
		$raw = apply_filters( 'supc_system_check_report', array() );
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		$rows = array();
		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$key    = sanitize_key( (string) ( $row['key'] ?? '' ) );
			$status = $this->normalize_status( (string) ( $row['status'] ?? 'warning' ) );
			$codes  = $this->normalize_codes( $row['codes'] ?? array() );
			if ( '' === $key ) {
				continue;
			}

			$rows[] = array(
				'key'    => $key,
				'status' => $status,
				'count'  => max( 0, (int) ( $row['count'] ?? count( $codes ) ) ),
				'codes'  => $codes,
			);
		}

		return $rows;
	}

	/**
	 * @return array<int, array<string, string>>
	 */
	public function adapter_rows(): array {
		$rows = array();
		foreach ( $this->registry->all() as $key => $adapter ) {
			try {
				$available = $adapter->is_available();
				$status    = $available ? 'pass' : 'warning';
				$codes     = $available ? array() : array( 'native_unavailable' );

				if ( $adapter instanceof Diagnostic_Adapter ) {
					$health = $adapter->health_report();
					$status = $this->worse_status( $status, $this->normalize_status( (string) ( $health['status'] ?? $status ) ) );
					$codes  = array_values( array_unique( array_merge( $codes, $this->normalize_codes( $health['codes'] ?? array() ) ) ) );
				}

				$rows[] = array(
					'key'            => sanitize_key( $key ),
					'native_module'  => sanitize_key( $adapter->native_module() ),
					'api_version'    => sanitize_text_field( $adapter->api_version() ),
					'minimum_native' => sanitize_text_field( $adapter->minimum_native_version() ),
					'group'          => sanitize_key( $adapter->group() ),
					'privacy'        => sanitize_key( $adapter->privacy_classification() ),
					'status'         => $status,
					'codes'          => implode( ', ', $codes ),
				);
			} catch ( Throwable $error ) {
				$rows[] = array(
					'key'            => sanitize_key( $key ),
					'native_module'  => '',
					'api_version'    => '',
					'minimum_native' => '',
					'group'          => '',
					'privacy'        => '',
					'status'         => 'fail',
					'codes'          => 'diagnostic_exception',
				);
			}
		}

		return $rows;
	}

	/**
	 * @param array<int, array{key:string,status:string,count:int,codes:array<int,string>}> $rows System rows.
	 */
	private function render_system_table( array $rows ): void {
		if ( array() === $rows ) {
			echo '<p>' . esc_html__( 'No System Check rows are currently available.', 'sabri-universal-post-composer' ) . '</p>';
			return;
		}
		?>
		<table class="widefat striped">
			<thead><tr><th><?php echo esc_html__( 'Check', 'sabri-universal-post-composer' ); ?></th><th><?php echo esc_html__( 'Status', 'sabri-universal-post-composer' ); ?></th><th><?php echo esc_html__( 'Count', 'sabri-universal-post-composer' ); ?></th><th><?php echo esc_html__( 'Codes', 'sabri-universal-post-composer' ); ?></th></tr></thead>
			<tbody>
			<?php foreach ( $rows as $row ) : ?>
				<tr><td><code><?php echo esc_html( $row['key'] ); ?></code></td><td><?php echo esc_html( strtoupper( $row['status'] ) ); ?></td><td><?php echo esc_html( (string) $row['count'] ); ?></td><td><?php echo esc_html( implode( ', ', $row['codes'] ) ); ?></td></tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * @param array<int, array<string, string>> $rows Adapter rows.
	 */
	private function render_adapter_table( array $rows ): void {
		if ( array() === $rows ) {
			echo '<p>' . esc_html__( 'No adapters are registered.', 'sabri-universal-post-composer' ) . '</p>';
			return;
		}
		?>
		<table class="widefat striped">
			<thead><tr><th><?php echo esc_html__( 'Adapter', 'sabri-universal-post-composer' ); ?></th><th><?php echo esc_html__( 'Native Module', 'sabri-universal-post-composer' ); ?></th><th><?php echo esc_html__( 'API', 'sabri-universal-post-composer' ); ?></th><th><?php echo esc_html__( 'Minimum Native', 'sabri-universal-post-composer' ); ?></th><th><?php echo esc_html__( 'Group', 'sabri-universal-post-composer' ); ?></th><th><?php echo esc_html__( 'Privacy', 'sabri-universal-post-composer' ); ?></th><th><?php echo esc_html__( 'Status', 'sabri-universal-post-composer' ); ?></th><th><?php echo esc_html__( 'Codes', 'sabri-universal-post-composer' ); ?></th></tr></thead>
			<tbody>
			<?php foreach ( $rows as $row ) : ?>
				<tr><td><code><?php echo esc_html( $row['key'] ); ?></code></td><td><code><?php echo esc_html( $row['native_module'] ); ?></code></td><td><?php echo esc_html( $row['api_version'] ); ?></td><td><?php echo esc_html( $row['minimum_native'] ); ?></td><td><?php echo esc_html( $row['group'] ); ?></td><td><?php echo esc_html( $row['privacy'] ); ?></td><td><?php echo esc_html( strtoupper( $row['status'] ) ); ?></td><td><?php echo esc_html( $row['codes'] ); ?></td></tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private function normalize_status( string $status ): string {
		$status = sanitize_key( $status );
		return in_array( $status, array( 'pass', 'warning', 'fail' ), true ) ? $status : 'warning';
	}

	private function worse_status( string $left, string $right ): string {
		$weight = array( 'pass' => 0, 'warning' => 1, 'fail' => 2 );
		return $weight[ $left ] >= $weight[ $right ] ? $left : $right;
	}

	/**
	 * @return array<int, string>
	 */
	private function normalize_codes( mixed $codes ): array {
		if ( ! is_array( $codes ) ) {
			return array();
		}

		$normalized = array();
		foreach ( array_slice( $codes, 0, 20 ) as $code ) {
			$code = substr( sanitize_key( (string) $code ), 0, 64 );
			if ( '' !== $code ) {
				$normalized[] = $code;
			}
		}

		return array_values( array_unique( $normalized ) );
	}

	private function request_notice(): string {
		$raw      = filter_input( INPUT_GET, 'supc_notice', FILTER_UNSAFE_RAW );
		$code     = is_string( $raw ) ? sanitize_key( wp_unslash( $raw ) ) : '';
		$messages = array(
			'dry_run_ready'        => __( 'Dry run: the current Create page mapping is valid. No change is required.', 'sabri-universal-post-composer' ),
			'dry_run_repairable'   => __( 'Dry run: an existing published shortcode page can be mapped safely. No change was made.', 'sabri-universal-post-composer' ),
			'dry_run_missing'      => __( 'Dry run: no valid Create page exists. A managed page can be created by the repair operation.', 'sabri-universal-post-composer' ),
			'no_change'            => __( 'The current Create page mapping was already valid. No change was made.', 'sabri-universal-post-composer' ),
			'mapped_existing'      => __( 'File 22 was safely mapped to an existing published shortcode page.', 'sabri-universal-post-composer' ),
			'created_managed_page' => __( 'A new File 22-managed Create page was created and mapped.', 'sabri-universal-post-composer' ),
			'repair_failed'        => __( 'The Create page mapping could not be repaired. Review System Check and WordPress logs.', 'sabri-universal-post-composer' ),
			'invalid_request'      => __( 'The repair request was invalid and no change was made.', 'sabri-universal-post-composer' ),
		);

		return $messages[ $code ] ?? '';
	}
}
