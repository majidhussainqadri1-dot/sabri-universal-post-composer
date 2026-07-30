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
use Sabri\UniversalComposer\Core\Permission_Resolver;
use Sabri\UniversalComposer\Core\Registry;
use Sabri\UniversalComposer\Core\Workflow_Coordinator;
use Throwable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class System_Check_Page {
	private const PAGE_SLUG = 'supc-system-check';
	private const CAPABILITY = 'manage_options';
	private const REPAIR_ACTION = 'supc_repair_create_page';
	private const NONCE_ACTION = 'supc_repair_create_page';
	private const ALLOWED_GROUPS = array( 'publishing', 'knowledge', 'media', 'commerce', 'other' );
	private const ALLOWED_PRIVACY = array( 'public', 'private', 'sensitive' );
	private const SAFE_SYSTEM_KEYS = array(
		'membership_core',
		'create_page',
		'adapter_errors',
		'public_api_contract',
		'file20_create_contract',
		'create_surface_diagnostics',
		'social_publication_adapter',
	);
	private const SAFE_DIAGNOSTIC_CODES = array(
		'membership_core_unavailable',
		'create_page_ready',
		'create_page_repairable',
		'create_page_ambiguous',
		'create_page_missing',
		'invalid_key',
		'duplicate_key',
		'api_mismatch',
		'invalid_required_capability',
		'invalid_native_module',
		'invalid_minimum_native_version',
		'invalid_privacy',
		'registration_exception',
		'availability_exception',
		'state_exception',
		'public_api_version_mismatch',
		'public_api_owner_mismatch',
		'public_api_function_collision',
		'public_api_incomplete',
		'file20_contract_version_mismatch',
		'file20_contract_owner_mismatch',
		'file20_contract_collision',
		'file20_contract_functions_missing',
		'file20_contract_unavailable',
		'file20_contract_exception',
		'invalid_route',
		'unknown_group',
		'render_exception',
		'incompatible_adapter_api',
		'native_unavailable',
		'workflow_api_mismatch',
		'invalid_schema_contract',
		'workflow_contract_exception',
		'social_publication_not_registered',
		'social_publication_contract_mismatch',
		'social_publication_native_version_unreported',
		'social_publication_native_version_invalid',
		'social_publication_native_version_too_low',
		'social_publication_temporarily_unavailable',
		'adapter_key_mismatch',
		'native_module_mismatch',
		'minimum_native_version_too_low',
		'required_capability_mismatch',
		'group_mismatch',
		'privacy_classification_mismatch',
		'diagnostic_contract_missing',
		'workflow_contract_missing',
		'subject_schema_api_mismatch',
		'subject_schema_contract_missing',
		'workflow_registration_metadata_missing',
		'workflow_capability_mismatch',
		'native_draft_contract_missing',
		'diagnostic_exception',
		'native_version_pending',
		'native_version_mismatch',
		'native_version_unreported',
		'configuration_missing',
		'route_missing',
		'dependency_missing',
		'dependency_incompatible',
		'safe_mode',
		'contract_mismatch',
		'temporarily_unavailable',
		'schema_invalid',
		'permission_configuration_invalid',
		'unrecognized_diagnostic',
	);

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

		$inspection   = Page_Resolver::inspect();
		$rows         = $this->system_rows();
		$adapter_rows = $this->adapter_rows();
		$notice       = $this->request_notice();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Universal Composer Health', 'sabri-universal-post-composer' ); ?></h1>
			<p><?php echo esc_html__( 'Read-only health information for File 22 and its registered native-module adapters. No user content, URLs, identity data, or clinical data is displayed.', 'sabri-universal-post-composer' ); ?></p>
			<p><?php echo esc_html__( 'Static adapter and workflow contract health validates the role-neutral base schema. Subject-aware schema support is reported separately. Create-surface invocation diagnostics are limited to the currently signed-in administrator and do not replace the staging role matrix.', 'sabri-universal-post-composer' ); ?></p>

			<?php if ( '' !== $notice['text'] ) : ?>
				<div class="notice <?php echo esc_attr( 'notice-' . $notice['type'] ); ?> is-dismissible"><p><?php echo esc_html( $notice['text'] ); ?></p></div>
			<?php endif; ?>

			<h2><?php echo esc_html__( 'System Check', 'sabri-universal-post-composer' ); ?></h2>
			<?php $this->render_system_table( $rows ); ?>

			<h2><?php echo esc_html__( 'Static Adapter and Workflow Contract Health', 'sabri-universal-post-composer' ); ?></h2>
			<?php $this->render_adapter_table( $adapter_rows ); ?>

			<h2><?php echo esc_html__( 'Create Page Mapping', 'sabri-universal-post-composer' ); ?></h2>
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: mapping status, 2: configured page ID, 3: discovered page ID, 4: candidate count. */
						__( 'Status: %1$s. Configured page ID: %2$d. Discovered shortcode page ID: %3$d. Candidate count: %4$d.', 'sabri-universal-post-composer' ),
						(string) $inspection['status'],
						(int) $inspection['configured_page_id'],
						(int) $inspection['discovered_page_id'],
						count( $inspection['candidate_page_ids'] )
					)
				);
				?>
			</p>
			<p><?php echo esc_html__( 'The repair operation can only remap File 22 to an existing published shortcode page or create one new File 22-managed Create page. It never edits, deletes, or overwrites unrelated pages.', 'sabri-universal-post-composer' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::REPAIR_ACTION ); ?>">
				<?php wp_nonce_field( self::NONCE_ACTION ); ?>
				<?php if ( 'ambiguous' === $inspection['status'] ) : ?>
					<p>
						<label for="supc-candidate-page-id"><strong><?php echo esc_html__( 'Select the canonical Create page', 'sabri-universal-post-composer' ); ?></strong></label><br>
						<select id="supc-candidate-page-id" name="supc_candidate_page_id" required>
							<option value=""><?php echo esc_html__( 'Choose a page ID', 'sabri-universal-post-composer' ); ?></option>
							<?php foreach ( $inspection['candidate_page_ids'] as $candidate_id ) : ?>
								<option value="<?php echo esc_attr( (string) $candidate_id ); ?>"><?php echo esc_html( sprintf( __( 'Page ID %d', 'sabri-universal-post-composer' ), $candidate_id ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>
				<?php endif; ?>
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
		$mode             = isset( $_POST['supc_mode'] ) ? sanitize_key( wp_unslash( (string) $_POST['supc_mode'] ) ) : '';
		$selected_page_id = isset( $_POST['supc_candidate_page_id'] ) ? absint( wp_unslash( $_POST['supc_candidate_page_id'] ) ) : 0;

		if ( 'dry_run' === $mode ) {
			$result = Page_Resolver::inspect();
			$code   = 'dry_run_' . sanitize_key( (string) $result['status'] );
		} elseif ( 'repair' === $mode ) {
			$result = Page_Resolver::repair_mapping( true, $selected_page_id );
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
			if ( ! in_array( $key, self::SAFE_SYSTEM_KEYS, true ) ) {
				$key = 'unrecognized_check';
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
		$rows        = array();
		$coordinator = new Workflow_Coordinator( $this->registry, new Permission_Resolver() );
		foreach ( $this->registry->all() as $key => $adapter ) {
			try {
				$status     = 'pass';
				$codes      = array();
				$group      = sanitize_key( $adapter->group() );
				$privacy    = sanitize_key( $adapter->privacy_classification() );
				$native     = sanitize_key( $adapter->native_module() );
				$capability = sanitize_key( $adapter->required_capability() );
				$minimum    = sanitize_text_field( $adapter->minimum_native_version() );

				if ( SUPC_ADAPTER_API_VERSION !== $adapter->api_version() ) {
					$status  = $this->worse_status( $status, 'fail' );
					$codes[] = 'incompatible_adapter_api';
				}
				if ( ! in_array( $group, self::ALLOWED_GROUPS, true ) ) {
					$status  = $this->worse_status( $status, 'warning' );
					$codes[] = 'unknown_group';
				}
				if ( ! in_array( $privacy, self::ALLOWED_PRIVACY, true ) ) {
					$status  = $this->worse_status( $status, 'fail' );
					$codes[] = 'invalid_privacy';
				}
				if ( '' === $native || $native !== $adapter->native_module() ) {
					$status  = $this->worse_status( $status, 'fail' );
					$codes[] = 'invalid_native_module';
				}
				if ( '' === $capability || $capability !== $adapter->required_capability() ) {
					$status  = $this->worse_status( $status, 'fail' );
					$codes[] = 'invalid_required_capability';
				}
				if ( 1 !== preg_match( '/^\d+\.\d+\.\d+(?:[-+][A-Za-z0-9.-]+)?$/', $minimum ) ) {
					$status  = $this->worse_status( $status, 'warning' );
					$codes[] = 'invalid_minimum_native_version';
				}

				if ( ! $adapter->is_available() ) {
					$status  = $this->worse_status( $status, 'warning' );
					$codes[] = 'native_unavailable';
				}

				if ( $adapter instanceof Diagnostic_Adapter ) {
					$health = $adapter->health_report();
					$status = $this->worse_status( $status, $this->normalize_status( (string) ( $health['status'] ?? $status ) ) );
					$codes  = array_merge( $codes, $this->normalize_codes( $health['codes'] ?? array() ) );
				}

				$workflow = $coordinator->contract_health( $key );
				$status   = $this->worse_status( $status, $this->normalize_status( $workflow['status'] ) );
				$codes    = array_merge( $codes, $workflow['codes'] );

				$rows[] = array(
					'key'                      => sanitize_key( $key ),
					'native_module'            => $native,
					'api_version'              => sanitize_text_field( $adapter->api_version() ),
					'workflow_api_version'     => sanitize_text_field( $workflow['workflow_api_version'] ),
					'supports_native_drafts'   => sanitize_text_field( $workflow['supports_native_drafts'] ),
					'subject_schema_extension' => sanitize_text_field( $workflow['subject_schema_extension'] ),
					'minimum_native'           => $minimum,
					'group'                    => $group,
					'privacy'                  => $privacy,
					'status'                   => $status,
					'codes'                    => implode( ', ', array_values( array_unique( $codes ) ) ),
				);
			} catch ( Throwable $error ) {
				unset( $error );
				$rows[] = array(
					'key'                      => sanitize_key( $key ),
					'native_module'            => '',
					'api_version'              => '',
					'workflow_api_version'     => '',
					'supports_native_drafts'   => '',
					'subject_schema_extension' => '',
					'minimum_native'           => '',
					'group'                    => '',
					'privacy'                  => '',
					'status'                   => 'fail',
					'codes'                    => 'diagnostic_exception',
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
			<caption class="screen-reader-text"><?php echo esc_html__( 'File 22 system checks and diagnostic codes', 'sabri-universal-post-composer' ); ?></caption>
			<thead><tr><th scope="col"><?php echo esc_html__( 'Check', 'sabri-universal-post-composer' ); ?></th><th scope="col"><?php echo esc_html__( 'Status', 'sabri-universal-post-composer' ); ?></th><th scope="col"><?php echo esc_html__( 'Count', 'sabri-universal-post-composer' ); ?></th><th scope="col"><?php echo esc_html__( 'Codes', 'sabri-universal-post-composer' ); ?></th></tr></thead>
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
			<caption class="screen-reader-text"><?php echo esc_html__( 'Role-independent static adapter and workflow contract health with subject-aware schema support', 'sabri-universal-post-composer' ); ?></caption>
			<thead><tr><th scope="col"><?php echo esc_html__( 'Adapter', 'sabri-universal-post-composer' ); ?></th><th scope="col"><?php echo esc_html__( 'Native Module', 'sabri-universal-post-composer' ); ?></th><th scope="col"><?php echo esc_html__( 'API', 'sabri-universal-post-composer' ); ?></th><th scope="col"><?php echo esc_html__( 'Workflow API', 'sabri-universal-post-composer' ); ?></th><th scope="col"><?php echo esc_html__( 'Native Drafts', 'sabri-universal-post-composer' ); ?></th><th scope="col"><?php echo esc_html__( 'Subject Schema', 'sabri-universal-post-composer' ); ?></th><th scope="col"><?php echo esc_html__( 'Minimum Native', 'sabri-universal-post-composer' ); ?></th><th scope="col"><?php echo esc_html__( 'Group', 'sabri-universal-post-composer' ); ?></th><th scope="col"><?php echo esc_html__( 'Privacy', 'sabri-universal-post-composer' ); ?></th><th scope="col"><?php echo esc_html__( 'Status', 'sabri-universal-post-composer' ); ?></th><th scope="col"><?php echo esc_html__( 'Codes', 'sabri-universal-post-composer' ); ?></th></tr></thead>
			<tbody>
			<?php foreach ( $rows as $row ) : ?>
				<tr><td><code><?php echo esc_html( $row['key'] ); ?></code></td><td><code><?php echo esc_html( $row['native_module'] ); ?></code></td><td><?php echo esc_html( $row['api_version'] ); ?></td><td><?php echo esc_html( $row['workflow_api_version'] ); ?></td><td><?php echo esc_html( $row['supports_native_drafts'] ); ?></td><td><?php echo esc_html( $row['subject_schema_extension'] ); ?></td><td><?php echo esc_html( $row['minimum_native'] ); ?></td><td><?php echo esc_html( $row['group'] ); ?></td><td><?php echo esc_html( $row['privacy'] ); ?></td><td><?php echo esc_html( strtoupper( $row['status'] ) ); ?></td><td><?php echo esc_html( $row['codes'] ); ?></td></tr>
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
				$normalized[] = in_array( $code, self::SAFE_DIAGNOSTIC_CODES, true )
					? $code
					: 'unrecognized_diagnostic';
			}
		}

		return array_values( array_unique( $normalized ) );
	}

	/**
	 * @return array{text:string,type:string}
	 */
	public function notice_for_code( string $code ): array {
		$notices = array(
			'dry_run_ready'                  => array( 'type' => 'success', 'text' => __( 'Dry run: the current Create page mapping is valid. No change is required.', 'sabri-universal-post-composer' ) ),
			'dry_run_repairable'             => array( 'type' => 'info', 'text' => __( 'Dry run: one existing published shortcode page can be mapped safely. No change was made.', 'sabri-universal-post-composer' ) ),
			'dry_run_ambiguous'              => array( 'type' => 'warning', 'text' => __( 'Dry run: multiple published shortcode pages were found. Select the canonical page before repair.', 'sabri-universal-post-composer' ) ),
			'dry_run_missing'                => array( 'type' => 'warning', 'text' => __( 'Dry run: no valid Create page exists. A managed page can be created by the repair operation.', 'sabri-universal-post-composer' ) ),
			'no_change'                      => array( 'type' => 'success', 'text' => __( 'The current Create page mapping was already valid. No change was made.', 'sabri-universal-post-composer' ) ),
			'mapped_existing'                => array( 'type' => 'success', 'text' => __( 'File 22 was safely mapped to the selected existing published shortcode page.', 'sabri-universal-post-composer' ) ),
			'created_managed_page'           => array( 'type' => 'success', 'text' => __( 'A new File 22-managed Create page was created, validated, and mapped.', 'sabri-universal-post-composer' ) ),
			'ambiguous_selection_required'   => array( 'type' => 'warning', 'text' => __( 'Multiple shortcode pages exist. Select the canonical Create page and run repair again.', 'sabri-universal-post-composer' ) ),
			'invalid_candidate'              => array( 'type' => 'error', 'text' => __( 'The selected page is not a valid published Create-page candidate. No mapping was changed.', 'sabri-universal-post-composer' ) ),
			'mapping_persistence_failed'     => array( 'type' => 'error', 'text' => __( 'The page was found or created, but WordPress did not persist the Create-page mapping.', 'sabri-universal-post-composer' ) ),
			'repair_locked'                  => array( 'type' => 'warning', 'text' => __( 'Another Create-page repair is already running. No second repair was started.', 'sabri-universal-post-composer' ) ),
			'managed_slug_unavailable'       => array( 'type' => 'error', 'text' => __( 'All approved managed Create-page slugs are occupied. No page was created.', 'sabri-universal-post-composer' ) ),
			'managed_page_insert_failed'     => array( 'type' => 'error', 'text' => __( 'WordPress could not insert the managed Create page.', 'sabri-universal-post-composer' ) ),
			'managed_page_validation_failed' => array( 'type' => 'error', 'text' => __( 'WordPress inserted an object that failed File 22 ownership or page validation. No mapping was accepted.', 'sabri-universal-post-composer' ) ),
			'invalid_request'                => array( 'type' => 'error', 'text' => __( 'The repair request was invalid and no change was made.', 'sabri-universal-post-composer' ) ),
		);

		return $notices[ $code ] ?? array( 'type' => 'info', 'text' => '' );
	}

	/**
	 * @return array{text:string,type:string}
	 */
	private function request_notice(): array {
		$raw  = filter_input( INPUT_GET, 'supc_notice', FILTER_UNSAFE_RAW );
		$code = is_string( $raw ) ? sanitize_key( wp_unslash( $raw ) ) : '';
		return $this->notice_for_code( $code );
	}
}
