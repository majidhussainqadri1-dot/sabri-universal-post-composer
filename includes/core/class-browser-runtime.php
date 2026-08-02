<?php
/**
 * Browser-facing Composer runtime layered over the stable File 22 contract core.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

namespace Sabri\UniversalComposer\Core;

use Sabri\UniversalComposer\Contracts\Workflow_Adapter;
use Sabri\UniversalComposer\Http\Rest_Controller;
use Sabri\UniversalComposer\Presentation\Create_Surface;
use Sabri\UniversalComposer\Presentation\Workflow_Surface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Browser_Runtime {
	private Registry $registry;
	private Workflow_Coordinator $coordinator;
	private Workflow_Surface $workflow_surface;
	private Rest_Controller $rest_controller;
	private bool $booted = false;

	public function __construct() {
		$plugin                  = Plugin::instance();
		$this->registry          = $plugin->registry();
		$this->coordinator       = $plugin->workflow_coordinator();
		$this->workflow_surface  = new Workflow_Surface( $this->registry, $this->coordinator );
		$this->rest_controller   = new Rest_Controller( $this->registry, $this->coordinator, new Session_Store() );
	}

	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;
		add_action( 'admin_init', array( Session_Store::class, 'maybe_install' ) );
		add_shortcode( 'sabri_universal_composer', array( $this, 'render_shortcode' ) );
		$this->rest_controller->register();
		add_action( 'supc_cleanup_expired_sessions', array( Session_Store::class, 'cleanup_expired' ) );
		add_filter( 'supc_system_check_report', array( $this, 'append_system_check' ), 20 );
	}

	public function render_shortcode(): string {
		// Reuse the established private/no-cache boundary and base assets.
		// If output has already begun, the required private response headers cannot
		// be guaranteed; fail closed through the established core notice.
		$fallback = Plugin::instance()->render_shortcode();
		if ( headers_sent() ) {
			return $fallback;
		}
		$user_id = get_current_user_id();
		if ( $user_id <= 0 || Safe_Mode::disabled() ) {
			return $fallback;
		}
		if ( '' !== $this->workflow_surface->selected_adapter_key() ) {
			$this->enqueue_workflow_assets();
			return $this->workflow_surface->render( $user_id );
		}
		$groups = $this->gateway_groups( $user_id );
		return array() === $groups ? $fallback : $this->render_gateway( $groups );
	}

	/**
	 * @return array<string,array{label:string,description:string,cards:array<int,array<string,string>>}>
	 */
	public function gateway_groups( int $user_id ): array {
		$groups = ( new Create_Surface( $this->registry ) )->collect_groups( $user_id );
		foreach ( $groups as &$group ) {
			foreach ( $group['cards'] as &$card ) {
				$adapter  = $this->registry->get( $card['key'] );
				$contract = $this->registry->workflow_contract( $card['key'] );
				if ( $adapter instanceof Workflow_Adapter && null !== $contract && ! empty( $contract['supports_native_drafts'] ) ) {
					$card['url'] = add_query_arg( array( 'type' => $card['key'] ), Page_Resolver::url() );
				}
			}
			unset( $card );
		}
		unset( $group );
		return $groups;
	}

	/** @param array<string,array{label:string,description:string,cards:array<int,array<string,string>>}> $groups */
	private function render_gateway( array $groups ): string {
		$heading_id = wp_unique_id( 'supc-browser-gateway-' );
		$html       = '<section class="supc-create" aria-labelledby="' . esc_attr( $heading_id ) . '">';
		$html      .= '<header class="supc-create__hero"><p class="supc-create__eyebrow">' . esc_html__( 'Universal Post Composer', 'sabri-universal-post-composer' ) . '</p>';
		$html      .= '<h2 id="' . esc_attr( $heading_id ) . '">' . esc_html__( 'What would you like to create?', 'sabri-universal-post-composer' ) . '</h2>';
		$html      .= '<p>' . esc_html__( 'Authorized workflow adapters open inside File 22. Every draft and final publication remains owned by its native module.', 'sabri-universal-post-composer' ) . '</p></header><div class="supc-create__groups">';
		foreach ( $groups as $group_key => $group ) {
			$group_id = wp_unique_id( 'supc-browser-group-' );
			$html    .= '<section class="supc-create-group" data-supc-group="' . esc_attr( $group_key ) . '" aria-labelledby="' . esc_attr( $group_id ) . '">';
			$html    .= '<div class="supc-create-group__heading"><h3 id="' . esc_attr( $group_id ) . '">' . esc_html( $group['label'] ) . '</h3><p>' . esc_html( $group['description'] ) . '</p></div><ul class="supc-create-grid" role="list">';
			foreach ( $group['cards'] as $card ) {
				$html .= '<li class="supc-create-card" data-supc-type="' . esc_attr( $card['key'] ) . '"><a class="supc-create-card__link" href="' . esc_url( $card['url'] ) . '">';
				$html .= '<span class="supc-create-card__icon dashicons ' . esc_attr( $card['icon_class'] ) . '" aria-hidden="true"></span><span class="supc-create-card__body">';
				$html .= '<span class="supc-create-card__title">' . esc_html( $card['label'] ) . '</span><span class="supc-create-card__description">' . esc_html( $card['description'] ) . '</span>';
				$html .= '<span class="supc-create-card__meta"><span class="supc-create-card__privacy">' . esc_html( $card['privacy_label'] ) . '</span><span class="supc-create-card__continue">' . esc_html__( 'Continue', 'sabri-universal-post-composer' ) . '<span aria-hidden="true">›</span></span></span></span></a></li>';
			}
			$html .= '</ul></section>';
		}
		$html .= '</div><aside class="supc-create__boundary"><strong>' . esc_html__( 'One gateway, one native record.', 'sabri-universal-post-composer' ) . '</strong> ' . esc_html__( 'File 22 retains only bounded orchestration metadata; it does not duplicate canonical content, media, consent, moderation, or publication history.', 'sabri-universal-post-composer' ) . '</aside></section>';
		return $html;
	}

	private function enqueue_workflow_assets(): void {
		wp_enqueue_style( 'supc-workflow-composer', SUPC_URL . 'assets/css/workflow-composer.css', array( 'supc-create-surface' ), SUPC_VERSION );
		wp_enqueue_script( 'supc-workflow-composer', SUPC_URL . 'assets/js/workflow-composer.js', array(), SUPC_VERSION, true );
		wp_localize_script(
			'supc-workflow-composer',
			'SUPCWorkflow',
			array(
				'restRoot' => untrailingslashit( esc_url_raw( rest_url( Rest_Controller::NAMESPACE ) ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'strings'  => array(
					'errorHeading'        => __( 'Please correct the following problems.', 'sabri-universal-post-composer' ),
					'genericError'        => __( 'The request could not be completed. Your native draft was not duplicated.', 'sabri-universal-post-composer' ),
					'requestBusy'         => __( 'Another draft operation is still running. Wait for it to finish and try again.', 'sabri-universal-post-composer' ),
					'sessionRecovered'    => __( 'The workflow session was reconnected. Last update: %s. Draft field recovery remains with the native owner.', 'sabri-universal-post-composer' ),
					'sessionNotRecovered' => __( 'The previous workflow session could not be reconnected.', 'sabri-universal-post-composer' ),
					'saving'              => __( 'Saving draft…', 'sabri-universal-post-composer' ),
					'saved'               => __( 'Draft saved by the native content owner.', 'sabri-universal-post-composer' ),
					'notSaved'            => __( 'Changes were not saved.', 'sabri-universal-post-composer' ),
					'unsaved'             => __( 'Changes are not saved yet.', 'sabri-universal-post-composer' ),
					'previewing'          => __( 'Preparing preview…', 'sabri-universal-post-composer' ),
					'previewReady'        => __( 'Preview is ready.', 'sabri-universal-post-composer' ),
					'openPreview'         => __( 'Open preview', 'sabri-universal-post-composer' ),
					'previewFailed'       => __( 'Preview could not be prepared.', 'sabri-universal-post-composer' ),
					'fixFields'           => __( 'Complete the required fields before submitting.', 'sabri-universal-post-composer' ),
					'validating'          => __( 'Validating content…', 'sabri-universal-post-composer' ),
					'validationFailed'    => __( 'Validation found problems that must be corrected.', 'sabri-universal-post-composer' ),
					'submitting'          => __( 'Submitting through the native owner…', 'sabri-universal-post-composer' ),
					'submitted'           => __( 'Submission completed.', 'sabri-universal-post-composer' ),
					'submitFailed'        => __( 'Submission was not completed.', 'sabri-universal-post-composer' ),
					'viewPublication'     => __( 'View publication', 'sabri-universal-post-composer' ),
				),
				'errors'   => array(
					'supc_session_conflict'         => __( 'This draft changed in another tab. Reload the current session before continuing.', 'sabri-universal-post-composer' ),
					'supc_permission_denied'        => __( 'Your account is no longer authorized for this workflow.', 'sabri-universal-post-composer' ),
					'supc_rate_limited'             => __( 'Too many requests were made. Wait briefly and try again.', 'sabri-universal-post-composer' ),
					'supc_validation_failed'        => __( 'The native owner rejected one or more field values.', 'sabri-universal-post-composer' ),
					'supc_adapter_version_changed'  => __( 'The native workflow changed after this session began. Start a new draft session before continuing.', 'sabri-universal-post-composer' ),
				),
			)
		);
	}

	/** @param array<int,array<string,mixed>> $rows @return array<int,array<string,mixed>> */
	public function append_system_check( array $rows ): array {
		$ready  = Session_Store::table_exists();
		$rows[] = array(
			'key'    => 'session_store',
			'status' => $ready ? 'pass' : 'fail',
			'count'  => $ready ? 0 : 1,
			'codes'  => $ready ? array() : array( 'session_store_missing' ),
		);
		$package = $this->file21_package_version();
		$status  = null === $package ? 'warning' : ( Version::valid( $package ) && version_compare( $package, '1.0.3.2', '>=' ) ? 'pass' : 'fail' );
		$rows[]  = array(
			'key'    => 'file21_package_identity',
			'status' => $status,
			'count'  => 'pass' === $status ? 0 : 1,
			'codes'  => 'pass' === $status ? array() : array( null === $package ? 'file21_package_identity_unknown' : 'file21_package_identity_too_low' ),
		);
		return $rows;
	}

	private function file21_package_version(): ?string {
		if ( ! function_exists( 'get_plugins' ) && defined( 'ABSPATH' ) ) {
			$plugin_api = ABSPATH . 'wp-admin/includes/plugin.php';
			if ( is_readable( $plugin_api ) ) {
				require_once $plugin_api;
			}
		}
		if ( ! function_exists( 'get_plugins' ) ) {
			return null;
		}
		$versions = array();
		foreach ( get_plugins() as $basename => $headers ) {
			$name   = isset( $headers['Name'] ) ? (string) $headers['Name'] : '';
			$domain = isset( $headers['TextDomain'] ) ? (string) $headers['TextDomain'] : '';
			if ( 'sabri-complete-home-news-feed' !== $domain && 'Sabri Complete Home and News Feed' !== $name && ! str_ends_with( (string) $basename, '/sabri-complete-home-news-feed.php' ) ) {
				continue;
			}
			$version = isset( $headers['Version'] ) ? trim( (string) $headers['Version'] ) : '';
			if ( Version::valid( $version ) ) {
				$versions[] = $version;
			}
		}
		if ( array() === $versions ) {
			return null;
		}
		usort( $versions, static fn ( string $left, string $right ): int => Version::compare( $right, $left ) );
		return $versions[0];
	}
}
