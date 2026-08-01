<?php
/**
 * Accessible Universal Create gateway surface.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

namespace Sabri\UniversalComposer\Presentation;

use Sabri\UniversalComposer\Contracts\Adapter;
use Sabri\UniversalComposer\Core\Contract_Boundary;
use Sabri\UniversalComposer\Core\Page_Resolver;
use Sabri\UniversalComposer\Core\Registry;
use Sabri\UniversalComposer\Core\Safe_Mode;
use Throwable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Create_Surface {
	private const GROUP_ORDER = array( 'publishing', 'knowledge', 'media', 'commerce', 'other' );
	private const MAX_LABEL_BYTES = 160;
	private const MAX_DESCRIPTION_BYTES = 1000;
	private const MAX_ROUTE_BYTES = 2048;
	private const MAX_ICON_BYTES = 64;
	private const MAX_DIAGNOSTICS = 300;

	/** @var array<string, array{code:string,severity:string}> */
	private array $diagnostics = array();

	public function __construct( private Registry $registry ) {
	}

	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function enqueue_assets(): void {
		if ( ! $this->is_surface_request() ) {
			return;
		}
		wp_enqueue_style( 'supc-create-surface', SUPC_URL . 'assets/css/create-surface.css', array( 'dashicons' ), SUPC_VERSION );
	}

	public function render(): string {
		if ( Safe_Mode::disabled() ) {
			return $this->notice( 'disabled', __( 'Content creation is temporarily unavailable.', 'sabri-universal-post-composer' ), __( 'Published platform content remains available. Please try again after the creation service is restored.', 'sabri-universal-post-composer' ) );
		}
		if ( ! is_user_logged_in() ) {
			return $this->notice(
				'login',
				__( 'Sign in to create platform content.', 'sabri-universal-post-composer' ),
				__( 'Only authorized platform accounts can open a native creation workflow.', 'sabri-universal-post-composer' ),
				wp_login_url( Page_Resolver::url() ),
				__( 'Sign In', 'sabri-universal-post-composer' )
			);
		}

		$user_id  = get_current_user_id();
		$snapshot = $this->registry->availability_snapshot_for_user( $user_id );
		if ( array() === $snapshot['adapters'] ) {
			return 'unavailable' === $snapshot['state']
				? $this->integration_unavailable_notice()
				: $this->notice( 'permission', __( 'No creation permission is available for this account.', 'sabri-universal-post-composer' ), __( 'Your current platform role or account status does not permit any registered creation workflow.', 'sabri-universal-post-composer' ) );
		}

		$this->diagnostics = array();
		$groups = $this->collect_from_adapters( $snapshot['adapters'], $user_id );
		if ( array() === $groups ) {
			return $this->integration_unavailable_notice();
		}
		if ( $this->surface_state_changed_to_disabled() ) {
			return $this->notice( 'disabled', __( 'Content creation is temporarily unavailable.', 'sabri-universal-post-composer' ), __( 'The creation service changed state before the gateway could be displayed.', 'sabri-universal-post-composer' ) );
		}

		$heading_id = wp_unique_id( 'supc-create-heading-' );
		$intro_id   = wp_unique_id( 'supc-create-intro-' );
		$html       = '<section class="supc-create" aria-labelledby="' . esc_attr( $heading_id ) . '" aria-describedby="' . esc_attr( $intro_id ) . '">';
		$html      .= '<header class="supc-create__hero">';
		$html      .= '<p class="supc-create__eyebrow">' . esc_html__( 'Universal Post Composer', 'sabri-universal-post-composer' ) . '</p>';
		$html      .= '<h2 id="' . esc_attr( $heading_id ) . '">' . esc_html__( 'What would you like to create?', 'sabri-universal-post-composer' ) . '</h2>';
		$html      .= '<p id="' . esc_attr( $intro_id ) . '">' . esc_html__( 'Choose one authorized content type. The selected native module remains the permanent owner of drafts, media, moderation, publication, and canonical URLs.', 'sabri-universal-post-composer' ) . '</p>';
		$html      .= '</header><div class="supc-create__groups">';

		foreach ( $groups as $group_key => $group ) {
			$group_heading_id = wp_unique_id( 'supc-group-heading-' );
			$html .= '<section class="supc-create-group" data-supc-group="' . esc_attr( $group_key ) . '" aria-labelledby="' . esc_attr( $group_heading_id ) . '">';
			$html .= '<div class="supc-create-group__heading"><h3 id="' . esc_attr( $group_heading_id ) . '">' . esc_html( $group['label'] ) . '</h3><p>' . esc_html( $group['description'] ) . '</p></div>';
			$html .= '<ul class="supc-create-grid" role="list">';
			foreach ( $group['cards'] as $card ) {
				$html .= $this->render_card( $card );
			}
			$html .= '</ul></section>';
		}

		$html .= '</div><aside class="supc-create__boundary" aria-label="' . esc_attr__( 'Content ownership notice', 'sabri-universal-post-composer' ) . '">';
		$html .= '<strong>' . esc_html__( 'One gateway, one native record.', 'sabri-universal-post-composer' ) . '</strong> ';
		$html .= esc_html__( 'File 22 routes you to the authorized native workflow and does not create a duplicate permanent content record.', 'sabri-universal-post-composer' );
		$html .= '</aside></section>';
		return $html;
	}

	/**
	 * @return array<string,array{label:string,description:string,cards:array<int,array<string,string>>}>
	 */
	public function collect_groups( int $user_id ): array {
		$this->diagnostics = array();
		$snapshot = $this->registry->availability_snapshot_for_user( $user_id );
		return $this->collect_from_adapters( $snapshot['adapters'], $user_id );
	}

	/** @return array<string,mixed> */
	public function system_check_row( int $user_id ): array {
		if ( Safe_Mode::disabled() ) {
			return $this->not_evaluated_row( 'create_surface_safe_mode' );
		}
		if ( $user_id <= 0 ) {
			return $this->not_evaluated_row( 'create_surface_subject_unavailable' );
		}

		$snapshot = $this->registry->availability_snapshot_for_user( $user_id );
		if ( 'denied' === $snapshot['state'] ) {
			return $this->not_evaluated_row( 'create_surface_subject_not_authorized' );
		}

		$this->diagnostics = array();
		$this->collect_from_adapters( $snapshot['adapters'], $user_id );
		if ( 'unavailable' === $snapshot['state'] && array() === $this->diagnostics ) {
			$this->record_diagnostic( 'surface', 'create_surface_native_unavailable', 'warning' );
		}

		$by_code = array();
		foreach ( $this->diagnostics as $diagnostic ) {
			$code = $diagnostic['code'];
			if ( ! isset( $by_code[ $code ] ) || 'fail' === $diagnostic['severity'] ) {
				$by_code[ $code ] = $diagnostic['severity'];
			}
		}
		ksort( $by_code );
		$codes    = array_keys( $by_code );
		$errors   = count( array_filter( $by_code, static fn ( string $severity ): bool => 'fail' === $severity ) );
		$warnings = count( $by_code ) - $errors;

		return array(
			'key'           => 'create_surface_diagnostics',
			'status'        => $errors > 0 ? 'fail' : ( $warnings > 0 ? 'warning' : 'pass' ),
			'count'         => count( $codes ),
			'error_count'   => $errors,
			'warning_count' => $warnings,
			'codes'         => $codes,
		);
	}

	/** @return array<string,array{code:string,severity:string}> */
	public function diagnostics(): array {
		return $this->diagnostics;
	}

	/**
	 * @param array<string,Adapter> $adapters Available adapters.
	 * @return array<string,array{label:string,description:string,cards:array<int,array<string,string>>}>
	 */
	private function collect_from_adapters( array $adapters, int $user_id ): array {
		$collected = array();
		foreach ( $adapters as $key => $adapter ) {
			try {
				$contract = $this->registry->adapter_contract( $key );
				if ( null === $contract ) {
					$this->record_diagnostic( $key, 'registration_contract_missing', 'fail' );
					continue;
				}
				$card = $this->card_from_adapter( $key, $adapter, $user_id, $contract );
				if ( null === $card ) {
					continue;
				}
				$group_key = $this->canonical_group( $contract['group'] );
				if ( 'other' === $group_key && 'other' !== $contract['group'] ) {
					$this->record_diagnostic( $key, 'unknown_group', 'warning' );
				}
				if ( ! isset( $collected[ $group_key ] ) ) {
					$collected[ $group_key ] = $this->group_metadata( $group_key );
					$collected[ $group_key ]['cards'] = array();
				}
				$collected[ $group_key ]['cards'][] = $card;
			} catch ( Throwable $error ) {
				unset( $error );
				$this->record_diagnostic( $key, 'render_exception', 'fail' );
				do_action( 'supc_adapter_render_error', Contract_Boundary::public_identifier( $key ), 'render_exception' );
			}
		}
		$ordered = array();
		foreach ( self::GROUP_ORDER as $group_key ) {
			if ( isset( $collected[ $group_key ] ) ) {
				$ordered[ $group_key ] = $collected[ $group_key ];
			}
		}
		return $ordered;
	}

	/**
	 * @param array<string,mixed> $contract Registration snapshot.
	 * @return array<string,string>|null
	 */
	private function card_from_adapter( string $key, Adapter $adapter, int $user_id, array $contract ): ?array {
		$label       = $adapter->label();
		$description = $adapter->description();
		$icon        = $adapter->icon();
		$route       = $adapter->start_url( $user_id );
		if (
			$label !== trim( $label ) ||
			$description !== trim( $description ) ||
			$icon !== trim( $icon ) ||
			$route !== trim( $route ) ||
			! Contract_Boundary::bounded_text( $label, 1, self::MAX_LABEL_BYTES ) ||
			! Contract_Boundary::bounded_text( $description, 1, self::MAX_DESCRIPTION_BYTES, true ) ||
			! Contract_Boundary::bounded_text( $icon, 1, self::MAX_ICON_BYTES ) ||
			! Contract_Boundary::bounded_text( $route, 1, self::MAX_ROUTE_BYTES ) ||
			1 !== preg_match( '/^(?:dashicons-)?[a-z][a-z0-9-]{0,62}$/D', $icon )
		) {
			$this->record_diagnostic( $key, 'invalid_display_metadata', 'fail' );
			return null;
		}

		$url = $this->validate_internal_route( $route );
		if ( '' === $url ) {
			$this->record_diagnostic( $key, 'invalid_route', 'fail' );
			return null;
		}
		$privacy = $contract['privacy_classification'] ?? '';
		if ( ! is_string( $privacy ) || ! in_array( $privacy, array( 'public', 'private', 'sensitive' ), true ) ) {
			$this->record_diagnostic( $key, 'invalid_privacy', 'fail' );
			return null;
		}
		return array(
			'key'           => $key,
			'label'         => $label,
			'description'   => $description,
			'url'           => $url,
			'icon_class'    => str_starts_with( $icon, 'dashicons-' ) ? $icon : 'dashicons-' . $icon,
			'privacy'       => $privacy,
			'privacy_label' => $this->privacy_label( $privacy ),
		);
	}

	/** @param array<string,string> $card */
	private function render_card( array $card ): string {
		$html  = '<li class="supc-create-card" data-supc-type="' . esc_attr( $card['key'] ) . '" data-supc-privacy="' . esc_attr( $card['privacy'] ) . '">';
		$html .= '<a class="supc-create-card__link" href="' . esc_url( $card['url'] ) . '"><span class="supc-create-card__icon dashicons ' . esc_attr( $card['icon_class'] ) . '" aria-hidden="true"></span>';
		$html .= '<span class="supc-create-card__body"><span class="supc-create-card__title">' . esc_html( $card['label'] ) . '</span><span class="supc-create-card__description">' . esc_html( $card['description'] ) . '</span>';
		$html .= '<span class="supc-create-card__meta"><span class="supc-create-card__privacy">' . esc_html( $card['privacy_label'] ) . '</span><span class="supc-create-card__continue">' . esc_html__( 'Continue', 'sabri-universal-post-composer' ) . '<span class="supc-create-card__arrow" aria-hidden="true">›</span></span></span></span></a></li>';
		return $html;
	}

	private function notice( string $type, string $title, string $message, string $url = '', string $action = '' ): string {
		$heading_id = wp_unique_id( 'supc-notice-heading-' );
		$role       = 'disabled' === $type ? 'alert' : 'status';
		$html       = '<section class="supc-create-notice supc-create-notice--' . esc_attr( $type ) . '" role="' . esc_attr( $role ) . '" aria-labelledby="' . esc_attr( $heading_id ) . '"><h2 id="' . esc_attr( $heading_id ) . '">' . esc_html( $title ) . '</h2><p>' . esc_html( $message ) . '</p>';
		if ( '' !== $url && '' !== $action ) {
			$html .= '<p><a class="supc-create-notice__action" href="' . esc_url( $url ) . '">' . esc_html( $action ) . '</a></p>';
		}
		return $html . '</section>';
	}

	private function surface_state_changed_to_disabled(): bool {
		return Safe_Mode::disabled();
	}

	private function integration_unavailable_notice(): string {
		return $this->notice( 'unavailable', __( 'Authorized creation services are temporarily unavailable.', 'sabri-universal-post-composer' ), __( 'A required native module, route, or adapter contract is unavailable or misconfigured. No content was created.', 'sabri-universal-post-composer' ) );
	}

	private function is_surface_request(): bool {
		if ( Page_Resolver::is_create_request() ) {
			return true;
		}
		global $post;
		return $post instanceof \WP_Post && has_shortcode( (string) $post->post_content, 'sabri_universal_composer' );
	}

	private function canonical_group( string $group ): string {
		return in_array( $group, self::GROUP_ORDER, true ) ? $group : 'other';
	}

	/** @return array{label:string,description:string} */
	private function group_metadata( string $group ): array {
		$groups = array(
			'publishing' => array( 'label' => __( 'Publishing', 'sabri-universal-post-composer' ), 'description' => __( 'Posts, updates, news, cases, research summaries, and other authorized publications.', 'sabri-universal-post-composer' ) ),
			'knowledge'  => array( 'label' => __( 'Knowledge and Learning', 'sabri-universal-post-composer' ), 'description' => __( 'Lessons, encyclopedia entries, academic resources, and structured educational work.', 'sabri-universal-post-composer' ) ),
			'media'      => array( 'label' => __( 'Media', 'sabri-universal-post-composer' ), 'description' => __( 'Videos, reels, PDF documents, and other supported media formats.', 'sabri-universal-post-composer' ) ),
			'commerce'   => array( 'label' => __( 'Commerce', 'sabri-universal-post-composer' ), 'description' => __( 'Verified marketplace listings and other authorized commercial content.', 'sabri-universal-post-composer' ) ),
			'other'      => array( 'label' => __( 'Other Authorized Content', 'sabri-universal-post-composer' ), 'description' => __( 'Additional creation workflows supplied by compatible native modules.', 'sabri-universal-post-composer' ) ),
		);
		return $groups[ $group ];
	}

	private function privacy_label( string $privacy ): string {
		$labels = array(
			'public'    => __( 'Public content', 'sabri-universal-post-composer' ),
			'private'   => __( 'Restricted content', 'sabri-universal-post-composer' ),
			'sensitive' => __( 'Sensitive workflow', 'sabri-universal-post-composer' ),
		);
		return $labels[ $privacy ];
	}

	private function validate_internal_route( string $route ): string {
		if ( $route !== trim( $route ) || ! Contract_Boundary::bounded_text( $route, 1, self::MAX_ROUTE_BYTES ) || str_contains( $route, '\\' ) ) {
			return '';
		}
		$validated = wp_validate_redirect( $route, '' );
		if ( '' === $validated ) {
			return '';
		}
		if ( str_starts_with( $validated, '/' ) ) {
			return str_starts_with( $validated, '//' ) ? '' : $validated;
		}
		$target = wp_parse_url( $validated );
		$home   = wp_parse_url( home_url( '/' ) );
		if ( ! is_array( $target ) || ! is_array( $home ) ) {
			return '';
		}
		$target_scheme = strtolower( (string) ( $target['scheme'] ?? '' ) );
		$home_scheme   = strtolower( (string) ( $home['scheme'] ?? '' ) );
		$target_host   = strtolower( (string) ( $target['host'] ?? '' ) );
		$home_host     = strtolower( (string) ( $home['host'] ?? '' ) );
		if ( 'https' !== $target_scheme || 'https' !== $home_scheme || '' === $target_host || $target_host !== $home_host || isset( $target['user'] ) || isset( $target['pass'] ) ) {
			return '';
		}
		$target_port = isset( $target['port'] ) ? (int) $target['port'] : 443;
		$home_port   = isset( $home['port'] ) ? (int) $home['port'] : 443;
		return $target_port === $home_port ? $validated : '';
	}

	/** @return array<string,mixed> */
	private function not_evaluated_row( string $code ): array {
		return array( 'key' => 'create_surface_diagnostics', 'status' => 'warning', 'count' => 1, 'error_count' => 0, 'warning_count' => 1, 'codes' => array( $code ) );
	}

	private function record_diagnostic( string $key, string $code, string $severity ): void {
		if ( count( $this->diagnostics ) >= self::MAX_DIAGNOSTICS ) {
			$this->diagnostics['[surface-limit]'] = array( 'code' => 'create_surface_diagnostic_limit_reached', 'severity' => 'fail' );
			return;
		}
		$key = Contract_Boundary::diagnostic_storage_key( $key, 'surface' );
		$this->diagnostics[ $key . ':' . $code ] = array( 'code' => $code, 'severity' => 'fail' === $severity ? 'fail' : 'warning' );
	}
}
