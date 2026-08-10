<?php
/**
 * Safe File 22 activation and migration wizard.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

namespace Sabri\UniversalComposer\Admin;

use Sabri\UniversalComposer\Core\Audit_Store;
use Sabri\UniversalComposer\Core\Migration_Manager;
use Sabri\UniversalComposer\Core\Page_Resolver;
use Sabri\UniversalComposer\Core\Permission_Resolver;
use Sabri\UniversalComposer\Core\Plugin;
use Sabri\UniversalComposer\Core\Submission_Store;
use Sabri\UniversalComposer\Core\Workspace_Page_Resolver;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Activation_Wizard {
	private const PAGE_SLUG = 'supc-activation-wizard';
	private const ACTION    = 'supc_activation_wizard';
	private const NONCE     = 'supc_activation_wizard';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	public function menu(): void {
		add_management_page(
			__( 'Composer Activation', 'sabri-universal-post-composer' ),
			__( 'Composer Activation', 'sabri-universal-post-composer' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage File 22.', 'sabri-universal-post-composer' ) );
		}
		nocache_headers();
		$steps  = $this->steps();
		$notice = isset( $_GET['supc_result'] ) && is_string( $_GET['supc_result'] ) ? sanitize_key( wp_unslash( $_GET['supc_result'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- status only.
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Universal Composer Activation and Migration', 'sabri-universal-post-composer' ); ?></h1>
			<p><?php echo esc_html__( 'Every operation is limited to File 22-owned settings, pages, tables and schedules. The wizard never creates a live publication and never edits companion-module content.', 'sabri-universal-post-composer' ); ?></p>
			<?php if ( '' !== $notice ) : ?><div class="notice notice-info"><p><?php echo esc_html( $notice ); ?></p></div><?php endif; ?>
			<table class="widefat striped"><caption class="screen-reader-text"><?php echo esc_html__( 'Activation readiness', 'sabri-universal-post-composer' ); ?></caption><thead><tr><th scope="col"><?php echo esc_html__( 'Step', 'sabri-universal-post-composer' ); ?></th><th scope="col"><?php echo esc_html__( 'Status', 'sabri-universal-post-composer' ); ?></th><th scope="col"><?php echo esc_html__( 'Detail', 'sabri-universal-post-composer' ); ?></th></tr></thead><tbody>
			<?php foreach ( $steps as $step ) : ?><tr><th scope="row"><?php echo esc_html( $step['label'] ); ?></th><td><?php echo esc_html( $step['status'] ); ?></td><td><?php echo esc_html( $step['detail'] ); ?></td></tr><?php endforeach; ?>
			</tbody></table>
			<h2><?php echo esc_html__( 'Controlled actions', 'sabri-universal-post-composer' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
				<?php wp_nonce_field( self::NONCE ); ?>
				<p><button class="button" name="supc_operation" value="repair_schema"><?php echo esc_html__( 'Repair File 22 tables and indexes', 'sabri-universal-post-composer' ); ?></button>
				<button class="button" name="supc_operation" value="repair_create_page"><?php echo esc_html__( 'Repair Create page', 'sabri-universal-post-composer' ); ?></button>
				<button class="button" name="supc_operation" value="repair_workspace_page"><?php echo esc_html__( 'Repair My Content page', 'sabri-universal-post-composer' ); ?></button>
				<button class="button" name="supc_operation" value="schedule_jobs"><?php echo esc_html__( 'Repair schedules', 'sabri-universal-post-composer' ); ?></button></p>
				<p><button class="button button-primary" name="supc_operation" value="enable"><?php echo esc_html__( 'Enable Composer writes', 'sabri-universal-post-composer' ); ?></button>
				<button class="button" name="supc_operation" value="disable"><?php echo esc_html__( 'Disable Composer writes', 'sabri-universal-post-composer' ); ?></button>
				<button class="button" name="supc_operation" value="rollback" onclick="return confirm('<?php echo esc_js( __( 'Restore the File 22 settings snapshot? Companion content will not be changed.', 'sabri-universal-post-composer' ) ); ?>');"><?php echo esc_html__( 'Restore activation snapshot', 'sabri-universal-post-composer' ); ?></button></p>
			</form>
			<p><strong><?php echo esc_html__( 'Staging law:', 'sabri-universal-post-composer' ); ?></strong> <?php echo esc_html__( 'Real Founder, doctor, restricted-account, browser, accessibility, cache, backup and rollback acceptance remains a separate external gate.', 'sabri-universal-post-composer' ); ?></p>
		</div>
		<?php
	}

	public function handle(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage File 22.', 'sabri-universal-post-composer' ) );
		}
		$method = isset( $_SERVER['REQUEST_METHOD'] ) && is_string( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : '';
		if ( 'POST' !== $method ) {
			wp_die( esc_html__( 'The activation request must use POST.', 'sabri-universal-post-composer' ) );
		}
		check_admin_referer( self::NONCE );
		$operation = isset( $_POST['supc_operation'] ) && is_string( $_POST['supc_operation'] ) ? sanitize_key( wp_unslash( $_POST['supc_operation'] ) ) : '';
		$result = match ( $operation ) {
			'repair_schema'         => Migration_Manager::repair_owned_schema(),
			'repair_create_page'    => $this->page_result( Page_Resolver::repair_mapping( true ) ),
			'repair_workspace_page' => $this->page_result( Workspace_Page_Resolver::repair() ),
			'schedule_jobs'         => Migration_Manager::schedule_jobs(),
			'enable'                => array( 'success' => Migration_Manager::set_writes_enabled( true ), 'codes' => array() ),
			'disable'               => array( 'success' => Migration_Manager::set_writes_enabled( false ), 'codes' => array() ),
			'rollback'              => Migration_Manager::rollback_settings(),
			default                 => array( 'success' => false, 'codes' => array( 'invalid_operation' ) ),
		};
		$code = $result['success'] ? $operation . '_success' : ( $result['codes'][0] ?? $operation . '_failed' );
		$redirect = add_query_arg( array( 'page' => self::PAGE_SLUG, 'supc_result' => sanitize_key( $code ) ), admin_url( 'tools.php' ) );
		if ( ! wp_safe_redirect( $redirect ) ) {
			wp_die( esc_html__( 'The activation result redirect failed.', 'sabri-universal-post-composer' ) );
		}
		exit;
	}

	/** @return array<int,array{label:string,status:string,detail:string}> */
	private function steps(): array {
		$permissions = new Permission_Resolver();
		$create      = Page_Resolver::inspect();
		$workspace   = Workspace_Page_Resolver::inspect();
		$registry    = Plugin::instance()->registry();
		$queue       = Submission_Store::tables_exist() ? ( new Submission_Store() )->queue_counts() : array( 'queued' => 0, 'retry' => 0, 'processing' => 0, 'dead_letter' => 0 );
		$summary     = ( new Audit_Store() )->summary( 30 );
		return array(
			array( 'label' => __( 'Membership Core', 'sabri-universal-post-composer' ), 'status' => $permissions->core_available() ? 'pass' : 'fail', 'detail' => __( 'File 00 is the only hard dependency.', 'sabri-universal-post-composer' ) ),
			array( 'label' => __( 'Create page', 'sabri-universal-post-composer' ), 'status' => (string) $create['status'], 'detail' => (string) $create['configured_page_id'] ),
			array( 'label' => __( 'My Content page', 'sabri-universal-post-composer' ), 'status' => (string) $workspace['status'], 'detail' => (string) $workspace['configured_page_id'] ),
			array( 'label' => __( 'Registered adapters', 'sabri-universal-post-composer' ), 'status' => count( $registry->all() ) > 0 ? 'pass' : 'warning', 'detail' => (string) count( $registry->all() ) ),
			array( 'label' => __( 'Reconciliation queue', 'sabri-universal-post-composer' ), 'status' => $queue['dead_letter'] > 0 ? 'fail' : 'pass', 'detail' => (string) array_sum( $queue ) ),
			array( 'label' => __( 'Recent audit events', 'sabri-universal-post-composer' ), 'status' => Audit_Store::table_exists() ? 'pass' : 'fail', 'detail' => (string) $summary['total'] ),
			array( 'label' => __( 'Composer write flag', 'sabri-universal-post-composer' ), 'status' => Migration_Manager::writes_enabled() ? 'enabled' : 'disabled', 'detail' => __( 'Read-only recovery remains separate from write availability.', 'sabri-universal-post-composer' ) ),
		);
	}

	/** @param array<string,mixed> $source @return array{success:bool,codes:array<int,string>} */
	private function page_result( array $source ): array {
		$result = sanitize_key( (string) ( $source['result'] ?? '' ) );
		$success = in_array( $result, array( 'already_ready', 'mapped', 'created', 'mapped_existing', 'created_new' ), true );
		return array( 'success' => $success, 'codes' => $success ? array() : array( '' === $result ? 'page_repair_failed' : $result ) );
	}
}
