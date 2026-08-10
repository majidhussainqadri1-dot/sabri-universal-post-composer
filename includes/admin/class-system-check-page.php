<?php
/**
 * Privacy-safe administrator System Check and repair controls.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

namespace Sabri\UniversalComposer\Admin;

use Sabri\UniversalComposer\Contracts\Diagnostic_Adapter;
use Sabri\UniversalComposer\Core\Contract_Boundary;
use Sabri\UniversalComposer\Core\Page_Resolver;
use Sabri\UniversalComposer\Core\Permission_Resolver;
use Sabri\UniversalComposer\Core\Registry;
use Sabri\UniversalComposer\Core\Version;
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
	private const MAX_SYSTEM_ROWS = 100;
	private const MAX_DIAGNOSTIC_COUNT = 1000;
	private const GROUPS = array( 'publishing', 'knowledge', 'media', 'commerce', 'other' );
	private const PRIVACY = array( 'public', 'private', 'sensitive' );
	private const SYSTEM_KEYS = array(
		'membership_core', 'create_page', 'adapter_errors', 'public_api_contract', 'file20_create_contract',
		'current_user_authorization', 'create_surface_diagnostics', 'social_publication_adapter', 'unrecognized_check',
		'system_check_pipeline', 'session_store', 'submission_outbox_store', 'reconciliation_queue',
		'reconciliation_cron', 'file21_package_identity', 'audit_store', 'upload_token_store', 'taxonomy_map',
		'plan_metadata_cleanup', 'workspace_page', 'plan_contract', 'composer_feature_flag',
		'adapter_pack_learning', 'adapter_pack_encyclopedia', 'adapter_pack_video', 'adapter_pack_reel',
		'adapter_pack_pdf', 'adapter_pack_marketplace',
	);
	private const SAFE_CODES = array(
		'membership_core_unavailable', 'authorization_subject_missing', 'supc_safe_mode_active',
		'membership_account_not_eligible', 'membership_hard_block', 'membership_application_blocking',
		'membership_contract_exception', 'current_user_authorized',
		'create_page_ready', 'create_page_repairable', 'create_page_ambiguous', 'create_page_missing',
		'create_page_candidate_limit_exceeded', 'invalid_key', 'duplicate_key', 'adapter_limit_reached', 'registry_error_limit_reached',
		'api_mismatch', 'invalid_required_capability', 'invalid_native_module', 'invalid_minimum_native_version', 'invalid_priority',
		'invalid_privacy', 'invalid_group', 'registration_exception', 'registration_contract_missing', 'registration_metadata_missing',
		'availability_exception', 'state_exception', 'public_api_version_mismatch', 'public_api_owner_mismatch',
		'public_api_function_collision', 'public_api_incomplete', 'file20_contract_missing', 'file20_legacy_contract_missing',
		'file20_visibility_contract_missing', 'file20_contract_version_mismatch', 'file20_contract_owner_mismatch',
		'file20_contract_collision', 'file20_contract_functions_missing', 'file20_contract_unavailable', 'file20_contract_exception',
		'create_surface_safe_mode', 'create_surface_subject_unavailable', 'create_surface_subject_not_authorized',
		'create_surface_native_unavailable', 'create_surface_diagnostic_limit_reached', 'invalid_display_metadata', 'invalid_route',
		'unknown_group', 'render_exception', 'incompatible_adapter_api', 'native_unavailable', 'workflow_api_mismatch',
		'invalid_schema_contract', 'workflow_contract_exception', 'workflow_adapter_not_registered', 'invalid_adapter_key',
		'social_publication_not_registered', 'social_publication_registration_metadata_missing', 'social_publication_contract_mismatch',
		'social_publication_native_version_unreported', 'social_publication_native_version_invalid',
		'social_publication_native_version_too_low', 'social_publication_native_version_below_declared_minimum',
		'social_publication_temporarily_unavailable', 'social_publication_diagnostic_exception',
		'social_publication_diagnostic_failure', 'adapter_key_mismatch', 'native_module_mismatch',
		'minimum_native_version_too_low', 'required_capability_mismatch', 'group_mismatch', 'privacy_classification_mismatch',
		'diagnostic_contract_missing', 'workflow_contract_missing', 'subject_schema_api_mismatch', 'subject_schema_contract_missing',
		'workflow_registration_metadata_missing', 'workflow_capability_mismatch', 'native_draft_contract_missing',
		'native_capability_missing', 'native_capability_check_exception', 'file21_runtime_missing', 'file21_runtime_too_low',
		'file21_duplicate_installed_copies', 'file21_duplicate_active_copies', 'file21_general_disabled',
		'file21_composer_disabled', 'file21_settings_exception', 'file21_emergency_disabled', 'file21_safe_mode_active',
		'file21_safe_mode_exception', 'native_adapter_unavailable', 'native_adapter_create_denied',
		'native_adapter_authorization_exception',
		'diagnostic_exception', 'native_version_pending', 'native_version_mismatch', 'native_version_unreported',
		'configuration_missing', 'route_missing', 'dependency_missing', 'dependency_incompatible', 'safe_mode', 'contract_mismatch',
		'temporarily_unavailable', 'schema_invalid', 'permission_configuration_invalid', 'diagnostic_reason_missing',
		'diagnostic_contract_invalid', 'duplicate_system_check', 'system_check_filter_invalid', 'system_check_filter_exception',
		'unrecognized_diagnostic', 'session_store_missing', 'submission_outbox_store_missing',
		'reconciliation_dead_letter_present', 'reconciliation_queue_pending', 'reconciliation_cron_missing',
		'file21_package_identity_missing', 'file21_package_identity_invalid', 'file21_package_identity_too_low', 'audit_store_missing',
		'upload_token_store_missing', 'taxonomy_map_invalid', 'plan_metadata_cleanup_missing',
		'workspace_page_ready', 'workspace_page_repairable', 'workspace_page_ambiguous', 'workspace_page_missing',
		'plan_contract_invalid', 'composer_feature_disabled', 'optional_adapter_pack_absent',
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
		if ( function_exists( 'nocache_headers' ) ) {
			nocache_headers();
		}
		$inspection = Page_Resolver::inspect();
		$notice     = $this->request_notice();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Universal Composer Health', 'sabri-universal-post-composer' ); ?></h1>
			<p><?php echo esc_html__( 'Read-only health information for File 22. No user content, identity data, URLs, or clinical data is displayed.', 'sabri-universal-post-composer' ); ?></p>
			<p><?php echo esc_html__( 'Create-surface invocation diagnostics are limited to the currently signed-in administrator and do not replace the staging role matrix.', 'sabri-universal-post-composer' ); ?></p>
			<?php if ( '' !== $notice['text'] ) : ?>
				<div class="notice <?php echo esc_attr( 'notice-' . $notice['type'] ); ?> is-dismissible"><p><?php echo esc_html( $notice['text'] ); ?></p></div>
			<?php endif; ?>
			<h2><?php echo esc_html__( 'System Check', 'sabri-universal-post-composer' ); ?></h2>
			<?php $this->render_system_table( $this->system_rows() ); ?>
			<h2><?php echo esc_html__( 'Static Adapter and Workflow Contract Health', 'sabri-universal-post-composer' ); ?></h2>
			<?php $this->render_adapter_table( $this->adapter_rows() ); ?>
			<h2><?php echo esc_html__( 'Create Page Mapping', 'sabri-universal-post-composer' ); ?></h2>
			<p><?php echo esc_html( sprintf( __( 'Status: %1$s. Configured page ID: %2$d. Discovered shortcode page ID: %3$d. Candidate count: %4$d.', 'sabri-universal-post-composer' ), (string) $inspection['status'], (int) $inspection['configured_page_id'], (int) $inspection['discovered_page_id'], count( $inspection['candidate_page_ids'] ) ) ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::REPAIR_ACTION ); ?>">
				<?php wp_nonce_field( self::NONCE_ACTION ); ?>
				<?php if ( 'ambiguous' === $inspection['status'] ) : ?>
					<label for="supc-candidate-page-id"><strong><?php echo esc_html__( 'Select the canonical Create page', 'sabri-universal-post-composer' ); ?></strong></label>
					<select id="supc-candidate-page-id" name="supc_candidate_page_id" required>
						<option value=""><?php echo esc_html__( 'Choose a page ID', 'sabri-universal-post-composer' ); ?></option>
						<?php foreach ( $inspection['candidate_page_ids'] as $candidate_id ) : ?>
							<option value="<?php echo esc_attr( (string) $candidate_id ); ?>"><?php echo esc_html( sprintf( __( 'Page ID %d', 'sabri-universal-post-composer' ), $candidate_id ) ); ?></option>
						<?php endforeach; ?>
					</select>
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
		$method = filter_input( INPUT_SERVER, 'REQUEST_METHOD', FILTER_UNSAFE_RAW );
		$method = is_string( $method ) ? strtoupper( sanitize_key( wp_unslash( $method ) ) ) : '';
		if ( 'POST' !== $method ) {
			wp_die( esc_html__( 'The repair request must use POST.', 'sabri-universal-post-composer' ) );
		}
		check_admin_referer( self::NONCE_ACTION );
		$raw_mode = filter_input( INPUT_POST, 'supc_mode', FILTER_UNSAFE_RAW );
		$raw_id   = filter_input( INPUT_POST, 'supc_candidate_page_id', FILTER_UNSAFE_RAW );
		$mode     = is_string( $raw_mode ) ? sanitize_key( wp_unslash( $raw_mode ) ) : '';
		$page_id  = is_scalar( $raw_id ) ? absint( wp_unslash( $raw_id ) ) : 0;
		if ( 'dry_run' === $mode ) {
			Page_Resolver::reset_cache();
			$result = Page_Resolver::inspect();
			$code   = 'dry_run_' . sanitize_key( (string) $result['status'] );
		} elseif ( 'repair' === $mode ) {
			$result = Page_Resolver::repair_mapping( true, $page_id );
			$code   = sanitize_key( (string) $result['result'] );
		} else {
			$code = 'invalid_request';
		}
		$redirect = add_query_arg( array( 'page' => self::PAGE_SLUG, 'supc_notice' => $code ), admin_url( 'tools.php' ) );
		if ( ! wp_safe_redirect( $redirect ) ) {
			wp_die( esc_html__( 'The administrator redirect could not be completed safely.', 'sabri-universal-post-composer' ) );
		}
		exit;
	}

	/** @return array<int,array{key:string,status:string,count:int,codes:array<int,string>}> */
	public function system_rows(): array {
		try {
			$raw = apply_filters( 'supc_system_check_report', array() );
		} catch ( Throwable $error ) {
			unset( $error );
			return array( $this->pipeline_failure( 'system_check_filter_exception' ) );
		}
		if ( ! is_array( $raw ) ) {
			return array( $this->pipeline_failure( 'system_check_filter_invalid' ) );
		}
		$rows = array();
		foreach ( array_slice( $raw, 0, self::MAX_SYSTEM_ROWS ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$key = isset( $row['key'] ) && is_string( $row['key'] ) ? $row['key'] : '';
			$key = Contract_Boundary::field_key( $key ) && in_array( $key, self::SYSTEM_KEYS, true ) ? $key : 'unrecognized_check';
			$status = $this->status( $row['status'] ?? 'warning' );
			$codes  = $this->codes( $row['codes'] ?? array() );
			if ( 'pass' !== $status && array() === $codes ) {
				$codes[] = 'diagnostic_reason_missing';
			}
			if ( 'pass' === $status && array() !== $codes ) {
				$status = 'warning';
			}
			$reported = is_numeric( $row['count'] ?? null ) ? max( 0, (int) $row['count'] ) : count( $codes );
			$entry = array( 'key' => $key, 'status' => $status, 'count' => min( self::MAX_DIAGNOSTIC_COUNT, max( count( $codes ), $reported ) ), 'codes' => $codes );
			if ( isset( $rows[ $key ] ) ) {
				$entry['status'] = $this->worse( $this->worse( $rows[ $key ]['status'], $entry['status'] ), 'warning' );
				$entry['codes']  = array_values( array_unique( array_merge( $rows[ $key ]['codes'], $entry['codes'], array( 'duplicate_system_check' ) ) ) );
				$entry['count']  = min( self::MAX_DIAGNOSTIC_COUNT, max( count( $entry['codes'] ), $rows[ $key ]['count'], $entry['count'] ) );
			}
			$rows[ $key ] = $entry;
		}
		return array_values( $rows );
	}

	/** @return array<int,array<string,string>> */
	public function adapter_rows(): array {
		$rows = array();
		$coordinator = new Workflow_Coordinator( $this->registry, new Permission_Resolver() );
		foreach ( $this->registry->all() as $key => $adapter ) {
			try {
				$contract = $this->registry->adapter_contract( $key );
				if ( null === $contract ) {
					$rows[] = $this->adapter_failure( $key, 'registration_metadata_missing' );
					continue;
				}
				$status = 'pass';
				$codes  = array();
				if ( SUPC_ADAPTER_API_VERSION !== $contract['api_version'] ) {
					$status = 'fail'; $codes[] = 'incompatible_adapter_api';
				}
				if ( ! in_array( $contract['group'], self::GROUPS, true ) ) {
					$status = $this->worse( $status, 'warning' ); $codes[] = 'unknown_group';
				}
				if ( ! in_array( $contract['privacy_classification'], self::PRIVACY, true ) ) {
					$status = 'fail'; $codes[] = 'invalid_privacy';
				}
				if ( ! Version::valid( $contract['minimum_native_version'] ) ) {
					$status = $this->worse( $status, 'warning' ); $codes[] = 'invalid_minimum_native_version';
				}
				if ( ! $adapter->is_available() ) {
					$status = $this->worse( $status, 'warning' ); $codes[] = 'native_unavailable';
				}
				if ( $adapter instanceof Diagnostic_Adapter ) {
					$health        = $adapter->health_report();
					$health_status = $this->status( $health['status'] ?? 'warning' );
					$health_codes  = $this->codes( $health['codes'] ?? array() );
					if ( 'pass' !== $health_status && array() === $health_codes ) {
						$health_codes[] = 'diagnostic_reason_missing';
					}
					if ( 'pass' === $health_status && array() !== $health_codes ) {
						$health_status = 'warning';
					}
					$status = $this->worse( $status, $health_status );
					$codes  = array_merge( $codes, $health_codes );
				}
				$workflow = $coordinator->contract_health( $key );
				$status   = $this->worse( $status, $this->status( $workflow['status'] ) );
				$codes    = array_values( array_unique( array_merge( $codes, $workflow['codes'] ) ) );
				if ( 'pass' !== $status && array() === $codes ) {
					$codes[] = 'diagnostic_reason_missing';
				}
				$rows[] = array(
					'key' => $key,
					'native_module' => $contract['native_module'],
					'api_version' => $contract['api_version'],
					'workflow_api_version' => $workflow['workflow_api_version'],
					'supports_native_drafts' => $workflow['supports_native_drafts'],
					'subject_schema_extension' => $workflow['subject_schema_extension'],
					'minimum_native' => $contract['minimum_native_version'],
					'group' => $contract['group'],
					'privacy' => $contract['privacy_classification'],
					'status' => $status,
					'codes' => implode( ', ', $codes ),
				);
			} catch ( Throwable $error ) {
				unset( $error );
				$rows[] = $this->adapter_failure( $key, 'diagnostic_exception' );
			}
		}
		return $rows;
	}

	/** @param array<int,array{key:string,status:string,count:int,codes:array<int,string>}> $rows */
	private function render_system_table( array $rows ): void {
		?><table class="widefat striped"><caption class="screen-reader-text"><?php echo esc_html__( 'File 22 system checks and diagnostic codes', 'sabri-universal-post-composer' ); ?></caption><thead><tr><th scope="col"><?php echo esc_html__( 'Check', 'sabri-universal-post-composer' ); ?></th><th scope="col"><?php echo esc_html__( 'Status', 'sabri-universal-post-composer' ); ?></th><th scope="col"><?php echo esc_html__( 'Count', 'sabri-universal-post-composer' ); ?></th><th scope="col"><?php echo esc_html__( 'Codes', 'sabri-universal-post-composer' ); ?></th></tr></thead><tbody>
		<?php foreach ( $rows as $row ) : ?><tr><td><code><?php echo esc_html( $row['key'] ); ?></code></td><td><?php echo esc_html( strtoupper( $row['status'] ) ); ?></td><td><?php echo esc_html( (string) $row['count'] ); ?></td><td><?php echo esc_html( implode( ', ', $row['codes'] ) ); ?></td></tr><?php endforeach; ?>
		</tbody></table><?php
	}

	/** @param array<int,array<string,string>> $rows */
	private function render_adapter_table( array $rows ): void {
		?><table class="widefat striped"><caption class="screen-reader-text"><?php echo esc_html__( 'Role-independent static adapter and workflow contract health with subject-aware schema support', 'sabri-universal-post-composer' ); ?></caption><thead><tr><?php foreach ( array( 'Adapter', 'Native Module', 'API', 'Workflow API', 'Native Drafts', 'Subject Schema', 'Minimum Native', 'Group', 'Privacy', 'Status', 'Codes' ) as $heading ) : ?><th scope="col"><?php echo esc_html( $heading ); ?></th><?php endforeach; ?></tr></thead><tbody>
		<?php foreach ( $rows as $row ) : ?><tr><td><code><?php echo esc_html( $row['key'] ); ?></code></td><td><code><?php echo esc_html( $row['native_module'] ); ?></code></td><td><?php echo esc_html( $row['api_version'] ); ?></td><td><?php echo esc_html( $row['workflow_api_version'] ); ?></td><td><?php echo esc_html( $row['supports_native_drafts'] ); ?></td><td><?php echo esc_html( $row['subject_schema_extension'] ); ?></td><td><?php echo esc_html( $row['minimum_native'] ); ?></td><td><?php echo esc_html( $row['group'] ); ?></td><td><?php echo esc_html( $row['privacy'] ); ?></td><td><?php echo esc_html( strtoupper( $row['status'] ) ); ?></td><td><?php echo esc_html( $row['codes'] ); ?></td></tr><?php endforeach; ?>
		</tbody></table><?php
	}

	private function status( mixed $status ): string {
		return is_string( $status ) && in_array( $status, array( 'pass', 'warning', 'fail' ), true ) ? $status : 'warning';
	}

	private function worse( string $left, string $right ): string {
		$weight = array( 'pass' => 0, 'warning' => 1, 'fail' => 2 );
		$left   = isset( $weight[ $left ] ) ? $left : 'warning';
		$right  = isset( $weight[ $right ] ) ? $right : 'warning';
		return $weight[ $left ] >= $weight[ $right ] ? $left : $right;
	}

	/** @return array<int,string> */
	private function codes( mixed $codes ): array {
		if ( ! is_array( $codes ) || array_values( $codes ) !== $codes ) {
			return array( 'diagnostic_contract_invalid' );
		}
		$normalized = array();
		foreach ( array_slice( $codes, 0, 20 ) as $code ) {
			$normalized[] = is_string( $code ) && Contract_Boundary::code( $code ) && in_array( $code, self::SAFE_CODES, true ) ? $code : 'unrecognized_diagnostic';
		}
		return array_values( array_unique( $normalized ) );
	}

	/** @return array{text:string,type:string} */
	public function notice_for_code( string $code ): array {
		$success = array( 'dry_run_ready', 'no_change', 'mapped_existing', 'created_managed_page' );
		$warning = array( 'dry_run_repairable', 'dry_run_ambiguous', 'dry_run_missing', 'ambiguous_selection_required', 'repair_locked' );
		$error   = array( 'dry_run_overflow', 'candidate_limit_exceeded', 'invalid_candidate', 'mapping_persistence_failed', 'managed_slug_unavailable', 'managed_page_insert_failed', 'managed_page_validation_failed', 'invalid_request' );
		if ( in_array( $code, $success, true ) ) {
			return array( 'type' => 'success', 'text' => __( 'The Create-page operation completed successfully.', 'sabri-universal-post-composer' ) );
		}
		if ( in_array( $code, $warning, true ) ) {
			return array( 'type' => 'warning', 'text' => __( 'The Create-page operation requires administrator attention.', 'sabri-universal-post-composer' ) );
		}
		if ( in_array( $code, $error, true ) ) {
			return array( 'type' => 'error', 'text' => __( 'The Create-page operation failed safely and no unverified mapping was accepted.', 'sabri-universal-post-composer' ) );
		}
		return array( 'type' => 'info', 'text' => '' );
	}

	/** @return array{text:string,type:string} */
	private function request_notice(): array {
		$raw  = filter_input( INPUT_GET, 'supc_notice', FILTER_UNSAFE_RAW );
		$code = is_string( $raw ) ? sanitize_key( wp_unslash( $raw ) ) : '';
		return $this->notice_for_code( $code );
	}

	/** @return array{key:string,status:string,count:int,codes:array<int,string>} */
	private function pipeline_failure( string $code ): array {
		return array( 'key' => 'system_check_pipeline', 'status' => 'fail', 'count' => 1, 'codes' => array( $code ) );
	}

	/** @return array<string,string> */
	private function adapter_failure( string $key, string $code ): array {
		return array( 'key' => Contract_Boundary::public_identifier( $key ), 'native_module' => '', 'api_version' => '', 'workflow_api_version' => '', 'supports_native_drafts' => '', 'subject_schema_extension' => '', 'minimum_native' => '', 'group' => '', 'privacy' => '', 'status' => 'fail', 'codes' => $code );
	}
}
