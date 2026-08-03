<?php
/**
 * Private, metadata-only My Content workspace.
 *
 * File 23 remains the Founder/Doctor operations and review dashboard. This
 * surface only reconnects the signed-in user to File 22 orchestration sessions
 * and native-owner destinations.
 *
 * @package SabriUniversalPostComposer
 */

declare(strict_types=1);

namespace Sabri\UniversalComposer\Presentation;

use Sabri\UniversalComposer\Core\Page_Resolver;
use Sabri\UniversalComposer\Core\Permission_Resolver;
use Sabri\UniversalComposer\Core\Registry;
use Sabri\UniversalComposer\Core\Session_Store;
use Sabri\UniversalComposer\Core\Workflow_Coordinator;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class My_Content_Workspace {
	private const VIEWS = array( 'all', 'drafts', 'submitted', 'changes_requested', 'scheduled', 'published', 'rejected', 'archived' );

	public function __construct(
		private Registry $registry,
		private Workflow_Coordinator $coordinator,
		private Session_Store $sessions
	) {
	}

	public function render(): string {
		if ( headers_sent() ) {
			return '<p role="status">' . esc_html__( 'My Content is unavailable because its private response boundary could not be applied before output began.', 'sabri-universal-post-composer' ) . '</p>';
		}
		foreach ( array( 'DONOTCACHEPAGE', 'DONOTCACHEOBJECT', 'DONOTCACHEDB' ) as $constant ) {
			if ( ! defined( $constant ) ) {
				define( $constant, true );
			}
		}
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
		header( 'Vary: Cookie', false );
		do_action( 'litespeed_control_set_nocache', 'sabri-universal-post-composer-my-content' );

		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return '<p role="status">' . esc_html__( 'Sign in to view your content workflow sessions.', 'sabri-universal-post-composer' ) . '</p>';
		}
		if ( ! ( new Permission_Resolver() )->account_is_eligible( $user_id ) ) {
			return '<p role="status">' . esc_html__( 'Your current account state does not permit access to Composer workflow metadata.', 'sabri-universal-post-composer' ) . '</p>';
		}
		$view = isset( $_GET['supc_view'] ) && is_string( $_GET['supc_view'] ) ? sanitize_key( wp_unslash( $_GET['supc_view'] ) ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
		$view = in_array( $view, self::VIEWS, true ) ? $view : 'all';
		$items = array_values( array_filter( $this->sessions->list_owned( $user_id, 100 ), fn ( array $session ): bool => $this->matches_view( $session, $view ) ) );
		wp_enqueue_style( 'supc-my-content', SUPC_URL . 'assets/css/my-content.css', array( 'supc-create-surface' ), SUPC_VERSION );
		return $this->html( $items, $view, $user_id );
	}

	/** @param array<int,array<string,mixed>> $items */
	private function html( array $items, string $view, int $user_id ): string {
		$heading = wp_unique_id( 'supc-my-content-' );
		$html  = '<section class="supc-my-content" aria-labelledby="' . esc_attr( $heading ) . '">';
		$html .= '<header><p class="supc-create__eyebrow">' . esc_html__( 'Universal Post Composer', 'sabri-universal-post-composer' ) . '</p><h2 id="' . esc_attr( $heading ) . '">' . esc_html__( 'My Content', 'sabri-universal-post-composer' ) . '</h2>';
		$html .= '<p>' . esc_html__( 'This private workspace lists orchestration metadata only. Content bodies, moderation truth and permanent records remain with their native modules.', 'sabri-universal-post-composer' ) . '</p></header>';
		$html .= '<nav aria-label="' . esc_attr__( 'Filter content workflow sessions', 'sabri-universal-post-composer' ) . '"><ul class="supc-my-content__tabs" role="list">';
		foreach ( self::VIEWS as $candidate ) {
			$url   = add_query_arg( 'supc_view', $candidate );
			$label = 'all' === $candidate ? __( 'All', 'sabri-universal-post-composer' ) : ucwords( str_replace( '_', ' ', $candidate ) );
			$html .= '<li><a' . ( $candidate === $view ? ' aria-current="page"' : '' ) . ' href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a></li>';
		}
		$html .= '</ul></nav>';
		if ( array() === $items ) {
			$html .= '<p class="supc-my-content__empty">' . esc_html__( 'No matching workflow sessions were found.', 'sabri-universal-post-composer' ) . '</p></section>';
			return $html;
		}
		$html .= '<ul class="supc-my-content__list" role="list">';
		foreach ( $items as $session ) {
			$html .= $this->card( $session, $user_id );
		}
		$html .= '</ul></section>';
		return $html;
	}

	/** @param array<string,mixed> $session */
	private function card( array $session, int $user_id ): string {
		$key     = (string) $session['adapter_key'];
		$adapter = $this->registry->get( $key );
		$label   = null === $adapter ? $key : $adapter->label();
		$create_url = Page_Resolver::url();
		$continue   = '' === $create_url ? '' : add_query_arg(
			array( 'type' => $key, 'session' => (string) $session['session_uuid'] ),
			$create_url
		);
		$native_url = '';
		if ( is_string( $session['native_reference'] ) && '' !== $session['native_reference'] ) {
			$result = $this->coordinator->canonical_url_read_only( $user_id, $key, $session['native_reference'] );
			$native_url = $result instanceof WP_Error ? '' : $result;
		}
		$html  = '<li class="supc-my-content__card"><div><h3>' . esc_html( $label ) . '</h3>';
		$html .= '<dl><div><dt>' . esc_html__( 'Composer', 'sabri-universal-post-composer' ) . '</dt><dd>' . esc_html( (string) $session['composer_state'] ) . '</dd></div>';
		$html .= '<div><dt>' . esc_html__( 'Review', 'sabri-universal-post-composer' ) . '</dt><dd>' . esc_html( (string) $session['review_state'] ) . '</dd></div>';
		$html .= '<div><dt>' . esc_html__( 'Publication', 'sabri-universal-post-composer' ) . '</dt><dd>' . esc_html( (string) $session['publication_state'] ) . '</dd></div>';
		$html .= '<div><dt>' . esc_html__( 'Safety hold', 'sabri-universal-post-composer' ) . '</dt><dd>' . esc_html( (string) $session['hold_state'] ) . '</dd></div></dl>';
		$html .= '<p>' . esc_html( sprintf( __( 'Last updated: %s UTC', 'sabri-universal-post-composer' ), (string) $session['updated_at'] ) ) . '</p></div><div class="supc-my-content__actions">';
		if ( in_array( (string) $session['composer_state'], array( 'new', 'editing', 'autosaved', 'conflicted' ), true ) && '' !== $continue ) {
			$html .= '<a class="button button-primary" href="' . esc_url( $continue ) . '">' . esc_html__( 'Continue editing', 'sabri-universal-post-composer' ) . '</a>';
		}
		if ( '' !== $native_url ) {
			$html .= '<a class="button" href="' . esc_url( $native_url ) . '">' . esc_html__( 'Open native record', 'sabri-universal-post-composer' ) . '</a>';
		}
		$html .= '</div></li>';
		return $html;
	}

	/** @param array<string,mixed> $session */
	private function matches_view( array $session, string $view ): bool {
		return match ( $view ) {
			'drafts'            => in_array( (string) $session['composer_state'], array( 'new', 'editing', 'autosaved', 'conflicted' ), true ),
			'submitted'         => in_array( (string) $session['review_state'], array( 'submitted', 'under_review' ), true ),
			'changes_requested' => 'changes_requested' === (string) $session['review_state'],
			'scheduled'         => 'scheduled' === (string) $session['publication_state'],
			'published'         => 'published' === (string) $session['publication_state'],
			'rejected'          => 'rejected' === (string) $session['review_state'],
			'archived'          => in_array( (string) $session['publication_state'], array( 'archived', 'deleted' ), true ) || 'abandoned' === (string) $session['composer_state'],
			default             => true,
		};
	}
}
