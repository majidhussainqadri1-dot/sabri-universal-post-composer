<?php
/**
 * Accessible Universal Create gateway surface.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

namespace Sabri\UniversalComposer\Presentation;

use Sabri\UniversalComposer\Contracts\Adapter;
use Sabri\UniversalComposer\Core\Page_Resolver;
use Sabri\UniversalComposer\Core\Registry;
use Sabri\UniversalComposer\Core\Safe_Mode;
use Throwable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Create_Surface {
	public function __construct( private Registry $registry ) {
	}

	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function enqueue_assets(): void {
		if ( ! $this->is_surface_request() ) {
			return;
		}

		wp_enqueue_style(
			'supc-create-surface',
			SUPC_URL . 'assets/css/create-surface.css',
			array( 'dashicons' ),
			SUPC_VERSION
		);
	}

	public function render(): string {
		if ( Safe_Mode::disabled() ) {
			return $this->notice(
				'disabled',
				__( 'Content creation is temporarily unavailable.', 'sabri-universal-post-composer' ),
				__( 'Published platform content remains available. Please try again after the creation service is restored.', 'sabri-universal-post-composer' )
			);
		}

		if ( ! is_user_logged_in() ) {
			$login_url = wp_login_url( Page_Resolver::url() );
			return $this->notice(
				'login',
				__( 'Sign in to create platform content.', 'sabri-universal-post-composer' ),
				__( 'Only authorized platform accounts can open a native publishing workflow.', 'sabri-universal-post-composer' ),
				$login_url,
				__( 'Sign In', 'sabri-universal-post-composer' )
			);
		}

		$user_id = get_current_user_id();
		$groups  = $this->collect_groups( $user_id );

		if ( array() === $groups ) {
			return $this->notice(
				'empty',
				__( 'No authorized content type is currently available.', 'sabri-universal-post-composer' ),
				__( 'Your account may not have publishing permission, or the required native module may be unavailable.', 'sabri-universal-post-composer' )
			);
		}

		$heading_id = wp_unique_id( 'supc-create-heading-' );
		$intro_id   = wp_unique_id( 'supc-create-intro-' );
		$html       = '<section class="supc-create" aria-labelledby="' . esc_attr( $heading_id ) . '" aria-describedby="' . esc_attr( $intro_id ) . '">';
		$html      .= '<header class="supc-create__hero">';
		$html      .= '<p class="supc-create__eyebrow">' . esc_html__( 'Universal Post Composer', 'sabri-universal-post-composer' ) . '</p>';
		$html      .= '<h2 id="' . esc_attr( $heading_id ) . '">' . esc_html__( 'What would you like to create?', 'sabri-universal-post-composer' ) . '</h2>';
		$html      .= '<p id="' . esc_attr( $intro_id ) . '">' . esc_html__( 'Choose one authorized content type. The selected native module remains the permanent owner of drafts, media, moderation, publication, and canonical URLs.', 'sabri-universal-post-composer' ) . '</p>';
		$html      .= '</header>';
		$html      .= '<div class="supc-create__groups">';

		foreach ( $groups as $group_key => $group ) {
			$group_heading_id = wp_unique_id( 'supc-group-heading-' );
			$html            .= '<section class="supc-create-group" data-supc-group="' . esc_attr( $group_key ) . '" aria-labelledby="' . esc_attr( $group_heading_id ) . '">';
			$html            .= '<div class="supc-create-group__heading">';
			$html            .= '<h3 id="' . esc_attr( $group_heading_id ) . '">' . esc_html( $group['label'] ) . '</h3>';
			$html            .= '<p>' . esc_html( $group['description'] ) . '</p>';
			$html            .= '</div>';
			$html            .= '<ul class="supc-create-grid" role="list">';

			foreach ( $group['cards'] as $card ) {
				$html .= $this->render_card( $card );
			}

			$html .= '</ul></section>';
		}

		$html .= '</div>';
		$html .= '<aside class="supc-create__boundary" aria-label="' . esc_attr__( 'Content ownership notice', 'sabri-universal-post-composer' ) . '">';
		$html .= '<strong>' . esc_html__( 'One gateway, one native record.', 'sabri-universal-post-composer' ) . '</strong> ';
		$html .= esc_html__( 'File 22 routes you to the authorized native workflow and does not create a duplicate permanent content record.', 'sabri-universal-post-composer' );
		$html .= '</aside>';
		$html .= '</section>';

		return $html;
	}

	/**
	 * @return array<string, array{label:string,description:string,cards:array<int, array<string,string>>}>
	 */
	public function collect_groups( int $user_id ): array {
		$groups = array();

		foreach ( $this->registry->available_for_user( $user_id ) as $key => $adapter ) {
			try {
				$card = $this->card_from_adapter( $key, $adapter, $user_id );
				if ( null === $card ) {
					continue;
				}

				$group_key = $this->canonical_group( $adapter->group() );
				if ( ! isset( $groups[ $group_key ] ) ) {
					$groups[ $group_key ] = $this->group_metadata( $group_key );
					$groups[ $group_key ]['cards'] = array();
				}

				$groups[ $group_key ]['cards'][] = $card;
			} catch ( Throwable $error ) {
				do_action( 'supc_adapter_render_error', $key, get_class( $error ) );
			}
		}

		return $groups;
	}

	/**
	 * @return array<string,string>|null
	 */
	private function card_from_adapter( string $key, Adapter $adapter, int $user_id ): ?array {
		$url = wp_validate_redirect( $adapter->start_url( $user_id ), '' );
		if ( '' === $url ) {
			do_action( 'supc_adapter_invalid_start_url', $key );
			return null;
		}

		$privacy = $this->canonical_privacy( $adapter->privacy_classification() );

		return array(
			'key'           => $key,
			'label'         => $adapter->label(),
			'description'   => $adapter->description(),
			'url'           => $url,
			'icon_class'    => $this->icon_class( $adapter->icon() ),
			'privacy'       => $privacy,
			'privacy_label' => $this->privacy_label( $privacy ),
		);
	}

	/**
	 * @param array<string,string> $card Card data.
	 */
	private function render_card( array $card ): string {
		$html  = '<li class="supc-create-card" data-supc-type="' . esc_attr( $card['key'] ) . '" data-supc-privacy="' . esc_attr( $card['privacy'] ) . '">';
		$html .= '<a class="supc-create-card__link" href="' . esc_url( $card['url'] ) . '">';
		$html .= '<span class="supc-create-card__icon dashicons ' . esc_attr( $card['icon_class'] ) . '" aria-hidden="true"></span>';
		$html .= '<span class="supc-create-card__body">';
		$html .= '<span class="supc-create-card__title">' . esc_html( $card['label'] ) . '</span>';
		$html .= '<span class="supc-create-card__description">' . esc_html( $card['description'] ) . '</span>';
		$html .= '<span class="supc-create-card__meta">';
		$html .= '<span class="supc-create-card__privacy">' . esc_html( $card['privacy_label'] ) . '</span>';
		$html .= '<span class="supc-create-card__continue">' . esc_html__( 'Continue', 'sabri-universal-post-composer' ) . '<span aria-hidden="true"> →</span></span>';
		$html .= '</span></span></a></li>';
		return $html;
	}

	private function notice( string $type, string $title, string $message, string $url = '', string $action = '' ): string {
		$heading_id = wp_unique_id( 'supc-notice-heading-' );
		$html       = '<section class="supc-create-notice supc-create-notice--' . esc_attr( $type ) . '" role="status" aria-labelledby="' . esc_attr( $heading_id ) . '">';
		$html      .= '<h2 id="' . esc_attr( $heading_id ) . '">' . esc_html( $title ) . '</h2>';
		$html      .= '<p>' . esc_html( $message ) . '</p>';

		if ( '' !== $url && '' !== $action ) {
			$html .= '<p><a class="supc-create-notice__action" href="' . esc_url( $url ) . '">' . esc_html( $action ) . '</a></p>';
		}

		return $html . '</section>';
	}

	private function is_surface_request(): bool {
		if ( Page_Resolver::is_create_request() ) {
			return true;
		}

		global $post;
		return $post instanceof \WP_Post && has_shortcode( (string) $post->post_content, 'sabri_universal_composer' );
	}

	private function canonical_group( string $group ): string {
		$group = sanitize_key( $group );
		return in_array( $group, array( 'publishing', 'knowledge', 'media', 'commerce' ), true ) ? $group : 'other';
	}

	/**
	 * @return array{label:string,description:string}
	 */
	private function group_metadata( string $group ): array {
		$groups = array(
			'publishing' => array(
				'label'       => __( 'Publishing', 'sabri-universal-post-composer' ),
				'description' => __( 'Posts, updates, news, cases, research summaries, and other authorized publications.', 'sabri-universal-post-composer' ),
			),
			'knowledge'  => array(
				'label'       => __( 'Knowledge and Learning', 'sabri-universal-post-composer' ),
				'description' => __( 'Lessons, encyclopedia entries, academic resources, and structured educational work.', 'sabri-universal-post-composer' ),
			),
			'media'      => array(
				'label'       => __( 'Media', 'sabri-universal-post-composer' ),
				'description' => __( 'Videos, reels, PDF documents, and other supported media formats.', 'sabri-universal-post-composer' ),
			),
			'commerce'   => array(
				'label'       => __( 'Commerce', 'sabri-universal-post-composer' ),
				'description' => __( 'Verified marketplace listings and other authorized commercial content.', 'sabri-universal-post-composer' ),
			),
			'other'      => array(
				'label'       => __( 'Other Authorized Content', 'sabri-universal-post-composer' ),
				'description' => __( 'Additional creation workflows supplied by compatible native modules.', 'sabri-universal-post-composer' ),
			),
		);

		return $groups[ $group ];
	}

	private function canonical_privacy( string $privacy ): string {
		$privacy = sanitize_key( $privacy );
		return in_array( $privacy, array( 'public', 'private', 'sensitive' ), true ) ? $privacy : 'private';
	}

	private function privacy_label( string $privacy ): string {
		$labels = array(
			'public'    => __( 'Public content', 'sabri-universal-post-composer' ),
			'private'   => __( 'Restricted content', 'sabri-universal-post-composer' ),
			'sensitive' => __( 'Sensitive workflow', 'sabri-universal-post-composer' ),
		);
		return $labels[ $privacy ];
	}

	private function icon_class( string $icon ): string {
		$icon = sanitize_html_class( $icon );
		return '' !== $icon ? 'dashicons-' . $icon : 'dashicons-edit';
	}
}
